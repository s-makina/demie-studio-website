"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import {
  fetchZipJob,
  isAbort,
  requestZip,
  zipDownloadUrl,
  type MediaAccess,
  type ZipJob,
} from "@/lib/api";

function zipIdFromUrl(): number | null {
  if (typeof window === "undefined") {
    return null;
  }
  const raw = new URLSearchParams(window.location.search).get("zip");
  const id = raw == null || raw === "" ? NaN : Number(raw);
  return Number.isInteger(id) && id > 0 ? id : null;
}

function setZipParam(jobId: number | null) {
  const params = new URLSearchParams(window.location.search);
  if (jobId === null) {
    params.delete("zip");
  } else {
    params.set("zip", String(jobId));
  }
  const query = params.toString();
  window.history.replaceState(null, "", query ? `?${query}` : window.location.pathname);
}

function formatSize(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  }
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

interface ZipPanelProps {
  access: MediaAccess;
  /** Ids currently loaded in the grid (what "all visible" means). */
  loadedIds: number[];
  selectedIds: Set<number>;
  selectMode: boolean;
  onSelectModeChange: (on: boolean) => void;
  onSelectAllVisible: () => void;
  onClearSelection: () => void;
}

/**
 * Selection toolbar + background-ZIP progress (Task 13). The job id lives in
 * `?zip=`, so closing the tab and returning to the same URL resumes polling
 * the same build instead of starting a duplicate (the API is idempotent per
 * selection as a second net).
 */
export default function ZipPanel({
  access,
  loadedIds,
  selectedIds,
  selectMode,
  onSelectModeChange,
  onSelectAllVisible,
  onClearSelection,
}: ZipPanelProps) {
  const [job, setJob] = useState<ZipJob | null>(null);
  const [jobError, setJobError] = useState<string | null>(null);
  const [requesting, setRequesting] = useState(false);
  const pollTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  // Latest poll for the timeout chain and the mount effect. The chain cannot
  // name `poll` directly (self-reference inside its own initializer), and
  // the mount effect cannot name it either (sync setState in an effect) —
  // both go through this ref instead.
  const pollRef = useRef<(jobId: number) => Promise<void>>(async () => {});

  const stopPolling = useCallback(() => {
    if (pollTimer.current !== null) {
      clearTimeout(pollTimer.current);
      pollTimer.current = null;
    }
  }, []);

  useEffect(() => stopPolling, [stopPolling]);

  const poll = useCallback(
    async (jobId: number) => {
      stopPolling();
      try {
        const current = await fetchZipJob(access, jobId);
        setJob(current);
        setJobError(null);
        if (current.status === "pending" || current.status === "running") {
          pollTimer.current = setTimeout(() => void pollRef.current(jobId), 2000);
        }
      } catch (e) {
        if (!isAbort(e)) {
          setJobError(e instanceof Error ? e.message : "Could not check ZIP progress.");
        }
      }
    },
    [access, stopPolling]
  );

  useEffect(() => {
    pollRef.current = poll;
  }, [poll]);

  // Resume: a ?zip= id in the URL picks up its build after tab-close/return.
  // Mount-once by design: later navigations own their job state.
  useEffect(() => {
    const resumed = zipIdFromUrl();
    if (resumed !== null) {
      void pollRef.current(resumed);
    }
  }, []);

  const start = useCallback(
    async (mediaIds?: number[]) => {
      stopPolling();
      setRequesting(true);
      setJobError(null);
      try {
        const created = await requestZip(access, mediaIds);
        setZipParam(created.job_id);
        await poll(created.job_id);
      } catch (e) {
        if (!isAbort(e)) {
          setJobError(e instanceof Error ? e.message : "Could not start the ZIP build.");
        }
      } finally {
        setRequesting(false);
      }
    },
    [access, poll, stopPolling]
  );

  const dismiss = useCallback(() => {
    stopPolling();
    setJob(null);
    setJobError(null);
    setZipParam(null);
  }, [stopPolling]);

  const building = job !== null && (job.status === "pending" || job.status === "running");

  return (
    <div className="mb-6 rounded-lg border border-border bg-surface-2 p-4">
      <div className="flex flex-wrap items-center gap-2">
        {!selectMode ? (
          <>
            <button
              type="button"
              onClick={() => void start()}
              disabled={requesting}
              className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 disabled:opacity-50"
            >
              {requesting ? "Starting…" : "Download all as ZIP"}
            </button>
            <button
              type="button"
              onClick={() => onSelectModeChange(true)}
              aria-pressed="false"
              className="rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
            >
              Select photos
            </button>
          </>
        ) : (
          <>
            <button
              type="button"
              onClick={onSelectAllVisible}
              className="rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
            >
              All visible ({loadedIds.length})
            </button>
            <button
              type="button"
              onClick={onClearSelection}
              className="rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
            >
              None
            </button>
            <button
              type="button"
              onClick={() => void start([...selectedIds])}
              disabled={requesting || selectedIds.size === 0}
              className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 disabled:opacity-50"
            >
              {requesting
                ? "Starting…"
                : `Download selected (${selectedIds.size})`}
            </button>
            <button
              type="button"
              onClick={() => onSelectModeChange(false)}
              aria-pressed="true"
              className="rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
            >
              Done
            </button>
          </>
        )}
      </div>

      {jobError && (
        <p role="alert" className="mt-3 text-sm text-red-400">
          {jobError}
        </p>
      )}

      {job && (
        <div className="mt-3 border-t border-border pt-3" aria-live="polite">
          {building && (
            <p className="text-sm text-muted">
              <span className="mr-2 inline-block h-3 w-3 animate-pulse rounded-full bg-accent" />
              Preparing {job.mediaCount} photo{job.mediaCount === 1 ? "" : "s"}…
              You can close this tab — the same link will be ready here later.
            </p>
          )}

          {job.status === "ready" && (
            <div className="flex flex-wrap items-center gap-3">
              <p className="text-sm text-foreground">
                Ready — {job.mediaCount} photo{job.mediaCount === 1 ? "" : "s"}
                {job.sizeBytes !== null && ` · ${formatSize(job.sizeBytes)}`}
              </p>
              <a
                href={zipDownloadUrl(access, job.id)}
                className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110"
              >
                Save ZIP
              </a>
              <button
                type="button"
                onClick={dismiss}
                className="text-sm text-muted hover:text-foreground"
              >
                New ZIP
              </button>
            </div>
          )}

          {job.status === "failed" && (
            <div className="flex flex-wrap items-center gap-3">
              <p role="alert" className="text-sm text-red-400">
                Build failed{job.error ? `: ${job.error}` : "."} Nothing was charged —
                try again.
              </p>
              <button
                type="button"
                onClick={() => void start(selectMode ? [...selectedIds] : undefined)}
                className="rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
              >
                Try again
              </button>
              <button
                type="button"
                onClick={dismiss}
                className="text-sm text-muted hover:text-foreground"
              >
                Dismiss
              </button>
            </div>
          )}

          {job.status === "expired" && (
            <div className="flex flex-wrap items-center gap-3">
              <p className="text-sm text-muted">
                This ZIP expired (files are kept 7 days). Request it again:
              </p>
              <button
                type="button"
                onClick={() => void start(selectMode ? [...selectedIds] : undefined)}
                className="rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
              >
                Rebuild ZIP
              </button>
              <button
                type="button"
                onClick={dismiss}
                className="text-sm text-muted hover:text-foreground"
              >
                Dismiss
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
