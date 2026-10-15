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
      className="mb-6 rounded-xl border border-border bg-surface-2 p-5"
    >
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-sm font-semibold">Upload</h2>
        {files.length > 0 && (
          <p className="text-xs text-muted" aria-live="polite">
            {done}/{files.length} done
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
        className={`mt-3 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-8 text-center transition ${
          dragOver ? "border-accent bg-surface" : "border-border hover:border-accent/60"
        }`}
      >
        <p className="font-medium">Drag photos or videos here</p>
        <p className="mt-1 text-sm text-muted">
          or <span className="text-accent">browse files</span> — uploads one by
          one, in order
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
        <div className="mt-4">
          <ul className="grid grid-cols-3 gap-2 sm:grid-cols-4">
            {files.map((entry) => (
              <li
                key={entry.id}
                className="overflow-hidden rounded-lg border border-border bg-surface"
              >
                <div className="relative aspect-square">
                  {entry.preview ? (
                    <img
                      src={entry.preview}
                      alt=""
                      className="h-full w-full object-cover"
                    />
                  ) : (
                    <div className="flex h-full w-full items-center justify-center text-xs text-muted">
                      {entry.file.type.startsWith("video/")
                        ? "video"
                        : entry.file.name.slice(-12)}
                    </div>
                  )}
                  {entry.status === "done" && (
                    <span className="absolute right-1.5 top-1.5 rounded-full bg-green-500 px-2 py-0.5 text-[11px] font-medium text-white">
                      Done
                    </span>
                  )}
                  {entry.status === "error" && (
                    <span className="absolute right-1.5 top-1.5 rounded-full bg-red-500 px-2 py-0.5 text-[11px] font-medium text-white">
                      Failed
                    </span>
                  )}
                </div>
                <div className="p-2">
                  <p className="truncate text-[11px] text-muted" title={entry.file.name}>
                    {entry.file.name}
                  </p>
                  <div
                    role="progressbar"
                    aria-valuenow={Math.round(entry.progress * 100)}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={`Upload progress for ${entry.file.name}`}
                    className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-2"
                  >
                    <div
                      className={`h-full rounded-full transition-all ${
                        entry.status === "error" ? "bg-red-400" : "bg-accent"
                      }`}
                      style={{ width: `${Math.round(entry.progress * 100)}%` }}
                    />
                  </div>
                  <div className="mt-1.5 flex justify-between text-[11px]">
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
                          className="text-muted hover:text-foreground"
                        >
                          Remove
                        </button>
                      </>
                    ) : entry.status === "done" ? (
                      <span className="text-muted">Uploaded in order</span>
                    ) : entry.status === "uploading" ? (
                      <span className="text-muted">
                        {Math.round(entry.progress * 100)}%
                      </span>
                    ) : (
                      <button
                        type="button"
                        onClick={() => removeOne(entry.id)}
                        className="text-muted hover:text-foreground"
                      >
                        Remove
                      </button>
                    )}
                  </div>
                  {entry.status === "error" && entry.error && (
                    <p role="alert" className="mt-1 text-[11px] text-red-400">
                      {entry.error}
                    </p>
                  )}
                </div>
              </li>
            ))}
          </ul>

          <div className="mt-4 flex flex-wrap items-center gap-2">
            <button
              type="button"
              onClick={() => void startAll()}
              disabled={running || queued === 0}
              className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 disabled:opacity-50"
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
                className="rounded-lg border border-border bg-surface px-4 py-2 text-sm transition hover:bg-surface-2"
              >
                Stop after this file
              </button>
            )}
            {done > 0 && !running && (
              <button
                type="button"
                onClick={clearDone}
                className="text-sm text-muted hover:text-foreground"
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
