"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import {
  abortUpload,
  completeUpload,
  initUpload,
  isAbort,
  kindForFile,
  uploadPartUrl,
} from "@/lib/api";
import { deleteQueuedFile, listQueuedFiles, putQueuedFile } from "@/lib/uploadQueue";

/** S3 needs ≥5MB for all but the tail part. */
const PART_SIZE = 8 * 1024 * 1024;

export interface FileState {
  id: number;
  /** Stable API idempotency key, generated once when the file was queued. */
  clientKey: string;
  file: File;
  preview: string | null;
  progress: number; // 0..1
  status: "queued" | "uploading" | "done" | "error";
  error?: string;
}

export interface UploadQueue {
  files: FileState[];
  running: boolean;
  /** Files still to send (queued plus previously failed). */
  queued: number;
  /** Files already finalised. */
  done: number;
  addFiles: (incoming: FileList | File[]) => void;
  startAll: () => void;
  /** Ask the running batch to halt once the current file settles. */
  stop: () => void;
  retryOne: (id: number) => void;
  removeOne: (id: number) => void;
  clearDone: () => void;
}

let fileSeq = 1;

/**
 * Per-queued-file key the API dedupes on. It must survive retries — Retry
 * re-sends the same key so init/complete converge instead of duplicating.
 */
function freshClientKey(): string {
  return typeof crypto !== "undefined" && "randomUUID" in crypto
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

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
 * The upload queue state machine (Task 20), lifted out of the upload panel so
 * the queue can be rendered in two places at once: the gallery column shows the
 * pending tiles, the sidebar owns the dropzone and the controls.
 *
 * Sequential init → direct-S3 PUT → complete, per-file progress and retry-in-
 * place. Upload order is display order. Each queued file gets a stable
 * `clientKey` the API dedupes on, so retries (even after a complete whose
 * response was lost) never duplicate media. The same key also drives the
 * persisted queue ({@link ../lib/uploadQueue | IndexedDB}): a page refresh
 * mid-batch restores the records (bytes included) and unfinished files resume
 * under their original key — already-finalized files settle via
 * `alreadyUploaded` in one cheap round trip per file.
 */
export function useUploadQueue(
  projectId: number,
  {
    onUploaded,
    onReject,
  }: {
    /** A file finished (or was replayed as already finalised). */
    onUploaded: () => void;
    /** A dropped file was not a photo or video and was skipped. */
    onReject: (fileName: string) => void;
  }
): UploadQueue {
  const [files, setFiles] = useState<FileState[]>([]);
  const [running, setRunning] = useState(false);
  const cancelRef = useRef(false);

  /* Callers rebuild these on most renders; keeping them in refs lets the
   * handlers below stay identity-stable. */
  const onUploadedRef = useRef(onUploaded);
  const onRejectRef = useRef(onReject);
  useEffect(() => {
    onUploadedRef.current = onUploaded;
    onRejectRef.current = onReject;
  }, [onUploaded, onReject]);

  const addFiles = useCallback((incoming: FileList | File[]) => {
    const accepted: FileState[] = [];
    for (const file of Array.from(incoming)) {
      if (!kindForFile(file)) {
        onRejectRef.current(file.name);
        continue;
      }
      const clientKey = freshClientKey();
      accepted.push({
        id: fileSeq++,
        clientKey,
        file,
        preview: file.type.startsWith("image/")
          ? URL.createObjectURL(file)
          : null,
        progress: 0,
        status: "queued",
      });
      // Persist the record (bytes included) so a page refresh mid-batch can
      // restore and resume it under the same key. No-op when IndexedDB is
      // unavailable — the in-memory queue then uploads exactly as before.
      void putQueuedFile({ projectId, clientKey, file });
    }
    if (accepted.length > 0) {
      setFiles((prev) => [...prev, ...accepted]);
    }
  }, [projectId]);

  /* Restore the persisted queue (lib/uploadQueue) after a page refresh:
   * every record hydrates as `queued` regardless of how far the batch got,
   * and the API's idempotent init settles already-finished files in one
   * cheap round trip. Existing entries win (addFiles may have run first);
   * the clientKey dedupe also absorbs StrictMode double-invocation.
   */
  useEffect(() => {
    void (async () => {
      const entries = await listQueuedFiles(projectId);
      if (entries.length === 0) {
        return;
      }
      setFiles((prev) => {
        const have = new Set(prev.map((f) => f.clientKey));
        const restored: FileState[] = entries
          .filter((entry) => !have.has(entry.clientKey))
          .map((entry) => ({
            id: fileSeq++,
            clientKey: entry.clientKey,
            file: entry.file,
            preview: entry.file.type.startsWith("image/")
              ? URL.createObjectURL(entry.file)
              : null,
            progress: 0,
            status: "queued" as const,
          }));
        return restored.length > 0 ? [...prev, ...restored] : prev;
      });
    })();
  }, [projectId]);

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
          clientUploadId: entry.clientKey,
        });
        mediaId = init.mediaId;
        if (!init.uploadId) {
          // Already finalized under this clientKey (a previous attempt's
          // complete landed but its response was lost). Replay as success.
          setFile(entry.id, { status: "done", progress: 1 });
          // Committed: the record leaves the resume queue — the gallery now
          // owns the photo.
          void deleteQueuedFile(projectId, entry.clientKey);
          onUploadedRef.current();
          return;
        }
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
        // Committed: the record leaves the resume queue — the gallery now
        // owns the photo.
        void deleteQueuedFile(projectId, entry.clientKey);
        onUploadedRef.current();
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
    [projectId, setFile]
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

  const stop = useCallback(() => {
    cancelRef.current = true;
  }, []);

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
      if (entry) {
        // The user is dropping the bytes too — no resume record should
        // outlive the tile (delete is idempotent; StrictMode may re-run the
        // updater).
        void deleteQueuedFile(projectId, entry.clientKey);
        if (entry.preview) {
          URL.revokeObjectURL(entry.preview);
        }
      }
      return prev.filter((f) => f.id !== id);
    });
  }, [projectId]);

  const clearDone = useCallback(() => {
    setFiles((prev) => {
      for (const f of prev) {
        if (f.status === "done") {
          void deleteQueuedFile(projectId, f.clientKey);
          if (f.preview) {
            URL.revokeObjectURL(f.preview);
          }
        }
      }
      return prev.filter((f) => f.status !== "done");
    });
  }, [projectId]);

  const queued = files.filter(
    (f) => f.status === "queued" || f.status === "error"
  ).length;
  const done = files.filter((f) => f.status === "done").length;

  return {
    files,
    running,
    queued,
    done,
    addFiles,
    startAll,
    stop,
    retryOne,
    removeOne,
    clearDone,
  };
}
