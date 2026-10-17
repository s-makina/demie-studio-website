"use client";

import { useCallback, useRef, useState } from "react";
import { ToastStack, useToasts } from "@/components/console-ui";
import {
  abortUpload,
  completeUpload,
  initUpload,
  isAbort,
  kindForFile,
  uploadPartUrl,
} from "@/lib/api";

const PART_SIZE = 8 * 1024 * 1024; // 8MB chunks (S3 needs ≥5MB non-tail parts).

interface FileState {
  id: number;
  file: File;
  preview: string | null;
  progress: number; // 0..1
  status: "queued" | "uploading" | "done" | "error";
  error?: string;
}

let fileSeq = 1;

function contentTypeFor(file: File): string {
  if (file.type) {
    return file.type;
  }
  const lower = file.name.toLowerCase();
  if (/\.(jpg|jpeg)$/.test(lower)) return "image/jpeg";
  if (/\.png$/.test(lower)) return "image/png";
  if (/\.webp$/.test(lower)) return "image/webp";
  if (/\.gif$/.test(lower)) return "image/gif";
  if (/\.(mp4|m4v)$/.test(lower)) return "video/mp4";
  if (/\.mov$/.test(lower)) return "video/quicktime";
  if (/\.webm$/.test(lower)) return "video/webm";
  return "application/octet-stream";
}

/**
 * Photographer upload dropzone (Task 20): drag-and-drop with file previews,
 * sequential init → direct-S3 PUT → complete, per-file progress and
 * retry-in-place. Upload order is display order. Thumbs stream into the grid
 * below as derivatives finish (parent refreshes on each completion).
 */
export default function UploadPanel({
  projectId,
  onUploaded,
}: {
  projectId: number;
  onUploaded: () => void;
}) {
  const { toasts, push, dismiss } = useToasts();
  const [files, setFiles] = useState<FileState[]>([]);
  const [dragOver, setDragOver] = useState(false);
  const [running, setRunning] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const cancelRef = useRef(false);

  const addFiles = useCallback(
    (incoming: FileList | File[]) => {
      const list = Array.from(incoming);
      const accepted: FileState[] = [];
      for (const file of list) {
        if (!kindForFile(file)) {
          push(`"${file.name}" is not a photo or video — skipped.`);
          continue;
        }
        accepted.push({
          id: fileSeq++,
          file,
          preview: file.type.startsWith("image/")
            ? URL.createObjectURL(file)
            : null,
          progress: 0,
          status: "queued",
        });
      }
      if (accepted.length > 0) {
        setFiles((prev) => [...prev, ...accepted]);
      }
    },
    [push]
  );

  const setFile = useCallback((id: number, patch: Partial<FileState>) => {
    setFiles((prev) => prev.map((f) => (f.id === id ? { ...f, ...patch } : f)));
  }, []);

  const uploadOne = useCallback(
    async (entry: FileState, signal: { cancelled: boolean }) => {
      const { file } = entry;
      setFile(entry.id, { status: "uploading", progress: 0, error: undefined });
      let mediaId: number | null = null;
      try {
        const init = await initUpload(projectId, {
          filename: file.name,
          contentType: contentTypeFor(file),
          sizeBytes: file.size,
        });
        mediaId = init.mediaId;
        const totalParts = Math.max(1, Math.ceil(file.size / PART_SIZE));
        const etags: Array<{ partNumber: number; etag: string }> = [];
        let sent = 0;
        for (let partNumber = 1; partNumber <= totalParts; partNumber++) {
          if (signal.cancelled) {
            throw new Error("cancelled");
          }
          const start = (partNumber - 1) * PART_SIZE;
          const chunk = file.slice(start, start + PART_SIZE);
          const { url } = await uploadPartUrl(mediaId, partNumber);
          const put = await fetch(url, { method: "PUT", body: chunk });
          if (!put.ok) {
            throw new Error(`part ${partNumber} upload failed (${put.status})`);
          }
          const etag = put.headers.get("ETag");
          if (!etag) {
            throw new Error(`part ${partNumber} gave no ETag`);
          }
          etags.push({ partNumber, etag });
          sent += chunk.size;
          setFile(entry.id, { progress: file.size ? sent / file.size : 1 });
        }
        await completeUpload(mediaId, etags);
        setFile(entry.id, { status: "done", progress: 1 });
        onUploaded();
      } catch (e) {
        if (signal.cancelled && mediaId !== null) {
          try {
            await abortUpload(mediaId);
          } catch {
            // Best effort — the row will read as a stuck upload otherwise.
          }
        }
        if (!isAbort(e)) {
          setFile(entry.id, {
            status: "error",
            error: e instanceof Error ? e.message : "Upload failed.",
          });
        }
      }
    },
    [projectId, setFile, onUploaded]
  );

  const startAll = useCallback(async () => {
    const pending = files.filter(
      (f) => f.status === "queued" || f.status === "error"
    );
    if (pending.length === 0 || running) {
      return;
    }
    setRunning(true);
    cancelRef.current = false;
    const signal = {
      get cancelled() {
        return cancelRef.current;
      },
    };
    for (const entry of pending) {
      if (cancelRef.current) {
        break;
      }
      // Re-read latest state: a retry may have been queued while running.
      await uploadOne(entry, signal);
    }
    setRunning(false);
  }, [files, running, uploadOne]);

  const retryOne = useCallback(
    (id: number) => {
      const entry = files.find((f) => f.id === id);
      if (!entry || running) {
        return;
      }
      setRunning(true);
      cancelRef.current = false;
      void (async () => {
        await uploadOne(
          { ...entry, status: "queued" },
          {
            get cancelled() {
              return cancelRef.current;
            },
          }
        );
        setRunning(false);
      })();
    },
    [files, running, uploadOne]
  );

  const removeOne = useCallback((id: number) => {
    setFiles((prev) => {
      const entry = prev.find((f) => f.id === id);
      if (entry?.preview) {
        URL.revokeObjectURL(entry.preview);
      }
      return prev.filter((f) => f.id !== id);
    });
  }, []);

  const clearDone = useCallback(() => {
    setFiles((prev) => {
      for (const f of prev) {
        if (f.status === "done" && f.preview) {
          URL.revokeObjectURL(f.preview);
        }
      }
      return prev.filter((f) => f.status !== "done");
    });
  }, []);

  const queued = files.filter(
    (f) => f.status === "queued" || f.status === "error"
  ).length;
  const done = files.filter((f) => f.status === "done").length;

  return (
    <section
      aria-label="Upload photos"
      className="mb-6 rounded-2xl border border-white/[0.08] bg-surface p-6 shadow-xl"
    >
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <div>
          <h2 className="font-serif text-xl tracking-wide text-foreground">Upload Media</h2>
          <p className="mt-0.5 text-xs text-muted">Add photos or videos to this shoot gallery</p>
        </div>
        {files.length > 0 && (
          <p className="rounded-full border border-white/10 bg-surface-2 px-3 py-1 text-xs font-medium text-muted" aria-live="polite">
            <span className="font-semibold text-accent">{done}</span> of {files.length} done
          </p>
        )}
      </div>

      <div
        role="button"
        tabIndex={0}
        aria-label="Drop photos or videos here, or press Enter to browse"
        onClick={() => inputRef.current?.click()}
        onKeyDown={(e) => {
          if (e.key === "Enter" || e.key === " ") {
            inputRef.current?.click();
          }
        }}
        onDragOver={(e) => {
          e.preventDefault();
          setDragOver(true);
        }}
        onDragLeave={() => setDragOver(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDragOver(false);
          if (e.dataTransfer.files.length > 0) {
            addFiles(e.dataTransfer.files);
          }
        }}
        className={`group mt-5 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition-all duration-300 ${
          dragOver
            ? "border-accent bg-accent/5 ring-4 ring-accent/10"
            : "border-accent/30 bg-surface-2/40 hover:border-accent/60 hover:bg-surface-2/70"
        }`}
      >
        <div className="flex h-12 w-12 items-center justify-center rounded-full border border-accent/30 bg-accent/10 text-accent transition duration-300 group-hover:scale-110 group-hover:border-accent/60 group-hover:bg-accent/20">
          <svg
            className="h-6 w-6"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.5}
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"
            />
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"
            />
          </svg>
        </div>
        <p className="mt-4 font-serif text-lg tracking-wide text-foreground">Drag photos or videos here</p>
        <p className="mt-1 text-xs text-muted">
          or <span className="font-medium text-accent underline decoration-accent/40 underline-offset-4 transition group-hover:decoration-accent">browse files</span> — uploads sequentially in display order
        </p>
        <input
          ref={inputRef}
          type="file"
          multiple
          accept="image/*,video/*"
          className="hidden"
          onChange={(e) => {
            if (e.target.files) {
              addFiles(e.target.files);
              e.target.value = "";
            }
          }}
        />
      </div>

      {files.length > 0 && (
        <div className="mt-6">
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {files.map((entry) => (
              <li
                key={entry.id}
                className="overflow-hidden rounded-xl border border-white/[0.06] bg-surface-2 transition hover:border-white/10"
              >
                <div className="relative aspect-square bg-surface-3">
                  {entry.preview ? (
                    <img
                      src={entry.preview}
                      alt=""
                      className="h-full w-full object-cover"
                    />
                  ) : (
                    <div className="flex h-full w-full flex-col items-center justify-center p-3 text-center text-xs text-muted">
                      <svg
                        className="mb-1.5 h-6 w-6 text-muted-dim"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={1.5}
                          d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"
                        />
                      </svg>
                      <span className="truncate max-w-full font-mono text-[10px]">
                        {entry.file.type.startsWith("video/")
                          ? "video"
                          : entry.file.name.slice(-12)}
                      </span>
                    </div>
                  )}
                  {entry.status === "done" && (
                    <span className="absolute right-2 top-2 rounded-full bg-emerald-500/90 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white shadow-lg backdrop-blur-sm">
                      Done
                    </span>
                  )}
                  {entry.status === "error" && (
                    <span className="absolute right-2 top-2 rounded-full bg-rose-500/90 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white shadow-lg backdrop-blur-sm">
                      Failed
                    </span>
                  )}
                </div>
                <div className="p-3">
                  <p className="truncate text-[10px] font-medium text-muted" title={entry.file.name}>
                    {entry.file.name}
                  </p>
                  <div
                    role="progressbar"
                    aria-valuenow={Math.round(entry.progress * 100)}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={`Upload progress for ${entry.file.name}`}
                    className="mt-2 h-1 overflow-hidden rounded-full bg-surface-3"
                  >
                    <div
                      className={`h-full rounded-full transition-all duration-300 ${
                        entry.status === "error" ? "bg-rose-500" : "bg-accent"
                      }`}
                      style={{ width: `${Math.round(entry.progress * 100)}%` }}
                    />
                  </div>
                  <div className="mt-2 flex items-center justify-between text-[11px]">
                    {entry.status === "error" ? (
                      <>
                        <button
                          type="button"
                          onClick={() => retryOne(entry.id)}
                          disabled={running}
                          className="font-medium text-accent hover:underline disabled:opacity-50"
                        >
                          Retry
                        </button>
                        <button
                          type="button"
                          onClick={() => removeOne(entry.id)}
                          className="text-muted transition hover:text-foreground"
                        >
                          Remove
                        </button>
                      </>
                    ) : entry.status === "done" ? (
                      <span className="text-[10px] text-muted">Uploaded in order</span>
                    ) : entry.status === "uploading" ? (
                      <span className="text-[10px] font-medium tabular-nums text-accent">
                        {Math.round(entry.progress * 100)}%
                      </span>
                    ) : (
                      <button
                        type="button"
                        onClick={() => removeOne(entry.id)}
                        className="text-muted transition hover:text-foreground"
                      >
                        Remove
                      </button>
                    )}
                  </div>
                  {entry.status === "error" && entry.error && (
                    <p role="alert" className="mt-1 text-[10px] text-rose-400">
                      {entry.error}
                    </p>
                  )}
                </div>
              </li>
            ))}
          </ul>

          <div className="mt-5 flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={() => void startAll()}
              disabled={running || queued === 0}
              className="rounded-full bg-accent px-5 py-2 text-xs font-medium text-background transition hover:bg-accent-hover disabled:opacity-50"
            >
              {running
                ? "Uploading…"
                : done > 0 && queued > 0
                  ? `Upload ${queued} more`
                  : `Start upload${queued === 1 ? "" : "s"} (${queued})`}
            </button>
            {running && (
              <button
                type="button"
                onClick={() => {
                  cancelRef.current = true;
                }}
                className="rounded-full border border-white/10 bg-surface-2 px-4 py-2 text-xs font-medium text-foreground transition hover:border-accent/40"
              >
                Stop after this file
              </button>
            )}
            {done > 0 && !running && (
              <button
                type="button"
                onClick={clearDone}
                className="text-xs text-muted transition hover:text-foreground"
              >
                Clear finished
              </button>
            )}
          </div>
        </div>
      )}
      <ToastStack toasts={toasts} onDismiss={dismiss} />
    </section>
  );
}
