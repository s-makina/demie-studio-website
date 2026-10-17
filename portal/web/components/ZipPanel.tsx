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
    <div className="mb-6 space-y-3">
      {/* Infinite shimmer keyframe for building state */}
      <style>{`
        @keyframes zipShimmer {
          0% { transform: translateX(-100%); }
          100% { transform: translateX(350%); }
        }
      `}</style>

      {/* Main toolbar */}
      <div className="glass-panel-subtle rounded-2xl p-3 sm:px-4 sm:py-3 shadow-lg shadow-black/10">
        {!selectMode ? (
          <div className="flex w-full flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2.5">
              <button
                type="button"
                onClick={() => void start()}
                disabled={requesting}
                className="inline-flex items-center gap-2 rounded-full bg-accent px-4 py-2 text-xs font-semibold tracking-wide text-background shadow-sm transition hover:bg-accent-hover hover:shadow-md hover:shadow-accent/20 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <svg
                  className="h-3.5 w-3.5"
                  viewBox="0 0 16 16"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  aria-hidden="true"
                >
                  <path d="M8 2v9m0 0l-3.5-3.5M8 11l3.5-3.5M2 14h12" />
                </svg>
                <span>{requesting ? "Starting…" : "Download All"}</span>
              </button>
              <button
                type="button"
                onClick={() => onSelectModeChange(true)}
                aria-pressed="false"
                className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-surface-2 px-4 py-2 text-xs font-medium text-foreground transition hover:border-accent/40 hover:bg-surface-3"
              >
                <svg
                  className="h-3.5 w-3.5 text-muted"
                  viewBox="0 0 16 16"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.75"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  aria-hidden="true"
                >
                  <rect x="2" y="2" width="12" height="12" rx="3" />
                  <path d="m5 8 2 2 4-4" />
                </svg>
                <span>Select Photos</span>
              </button>
            </div>

            {loadedIds.length > 0 && (
              <span className="hidden text-xs text-muted sm:inline">
                {loadedIds.length} {loadedIds.length === 1 ? "photo" : "photos"} visible
              </span>
            )}
          </div>
        ) : (
          <div className="flex w-full flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2 sm:gap-2.5">
              <div className="flex items-center gap-2 rounded-full border border-accent/20 bg-accent/10 px-3 py-1.5 text-xs font-medium text-foreground">
                <span className="h-1.5 w-1.5 rounded-full bg-accent animate-pulse" />
                <span>
                  <strong className="font-semibold text-accent">{selectedIds.size}</strong> selected
                </span>
              </div>
              <button
                type="button"
                onClick={onSelectAllVisible}
                className="rounded-full border border-white/10 bg-surface-2 px-3.5 py-1.5 text-xs font-medium text-foreground transition hover:border-accent/40 hover:bg-surface-3"
              >
                All visible ({loadedIds.length})
              </button>
              <button
                type="button"
                onClick={onClearSelection}
                disabled={selectedIds.size === 0}
                className="rounded-full border border-white/10 bg-surface-2 px-3.5 py-1.5 text-xs font-medium text-muted transition hover:border-accent/40 hover:text-foreground hover:bg-surface-3 disabled:pointer-events-none disabled:opacity-40"
              >
                Clear
              </button>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={() => void start([...selectedIds])}
                disabled={requesting || selectedIds.size === 0}
                className="inline-flex items-center gap-2 rounded-full bg-accent px-4 py-1.5 text-xs font-semibold tracking-wide text-background shadow-sm transition hover:bg-accent-hover hover:shadow-md hover:shadow-accent/20 disabled:cursor-not-allowed disabled:opacity-40"
              >
                <svg
                  className="h-3.5 w-3.5"
                  viewBox="0 0 16 16"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  aria-hidden="true"
                >
                  <path d="M8 2v9m0 0l-3.5-3.5M8 11l3.5-3.5M2 14h12" />
                </svg>
                <span>
                  {requesting
                    ? "Starting…"
                    : `Download Selected (${selectedIds.size})`}
                </span>
              </button>
              <button
                type="button"
                onClick={() => onSelectModeChange(false)}
                aria-pressed="true"
                className="rounded-full border border-white/10 bg-surface-2 px-3.5 py-1.5 text-xs font-medium text-foreground transition hover:border-accent/40 hover:bg-surface-3"
              >
                Done
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Inline Toast Error */}
      {jobError && (
        <div
          role="alert"
          className="flex items-center justify-between gap-3 rounded-2xl border border-red-500/20 bg-red-950/20 px-4 py-3 text-xs text-red-200 backdrop-blur-md"
        >
          <div className="flex items-center gap-2.5">
            <span className="h-2 w-2 shrink-0 rounded-full bg-red-400" />
            <span className="leading-relaxed">{jobError}</span>
          </div>
          <button
            type="button"
            onClick={() => setJobError(null)}
            className="shrink-0 text-muted transition hover:text-foreground text-xs"
            aria-label="Dismiss error"
          >
            ✕
          </button>
        </div>
      )}

      {/* ZIP Progress section */}
      {job && (
        <div aria-live="polite">
          {building && (
            <div className="glass-panel-subtle space-y-3 rounded-2xl border border-white/[0.08] p-4">
              <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                <div className="flex items-center gap-2.5">
                  <span className="relative flex h-2 w-2 shrink-0">
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75" />
                    <span className="relative inline-flex h-2 w-2 rounded-full bg-accent" />
                  </span>
                  <span className="font-medium text-foreground">
                    Preparing {job.mediaCount} photo{job.mediaCount === 1 ? "" : "s"}…
                  </span>
                </div>
                <span className="text-[11px] text-muted">
                  You can close this tab — the link will remain ready here later.
                </span>
              </div>

              {/* Animated gold progress bar with infinite shimmer */}
              <div className="relative h-1.5 w-full overflow-hidden rounded-full bg-surface-3">
                <div
                  className="absolute inset-y-0 w-2/5 rounded-full bg-gradient-to-r from-transparent via-accent to-transparent shadow-[0_0_12px_rgba(212,167,90,0.5)]"
                  style={{
                    animation: "zipShimmer 1.8s ease-in-out infinite",
                  }}
                />
              </div>
            </div>
          )}

          {job.status === "ready" && (
            <div className="glass-panel-subtle flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-emerald-500/20 bg-emerald-950/15 p-4 backdrop-blur-md">
              <div className="flex items-center gap-3">
                <span className="relative flex h-2.5 w-2.5 shrink-0">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60" />
                  <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.6)]" />
                </span>
                <div>
                  <p className="text-xs font-semibold text-foreground">
                    ZIP archive ready
                  </p>
                  <p className="text-[11px] text-muted">
                    {job.mediaCount} photo{job.mediaCount === 1 ? "" : "s"}
                    {job.sizeBytes !== null && ` · ${formatSize(job.sizeBytes)}`}
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <a
                  href={zipDownloadUrl(access, job.id)}
                  className="inline-flex items-center gap-2 rounded-full bg-accent px-5 py-2 text-xs font-semibold text-background shadow-md shadow-accent/20 transition hover:bg-accent-hover hover:shadow-lg hover:shadow-accent/30"
                >
                  <svg
                    className="h-3.5 w-3.5"
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M8 2v9m0 0l-3.5-3.5M8 11l3.5-3.5M2 14h12" />
                  </svg>
                  <span>Save ZIP</span>
                </a>
                <button
                  type="button"
                  onClick={dismiss}
                  className="rounded-full border border-white/10 bg-surface-2 px-3.5 py-2 text-xs font-medium text-muted transition hover:border-accent/40 hover:text-foreground hover:bg-surface-3"
                >
                  New ZIP
                </button>
              </div>
            </div>
          )}

          {job.status === "failed" && (
            <div className="glass-panel-subtle flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-red-500/20 bg-red-950/20 p-4 backdrop-blur-md">
              <div className="flex items-center gap-3">
                <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-red-400 shadow-[0_0_8px_rgba(248,113,113,0.6)]" />
                <div>
                  <p role="alert" className="text-xs font-semibold text-red-200">
                    Build failed{job.error ? `: ${job.error}` : "."}
                  </p>
                  <p className="text-[11px] text-muted">
                    Nothing was charged — please try again.
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => void start(selectMode ? [...selectedIds] : undefined)}
                  className="rounded-full border border-white/10 bg-surface-2 px-4 py-2 text-xs font-medium text-foreground transition hover:border-accent/40 hover:bg-surface-3"
                >
                  Try again
                </button>
                <button
                  type="button"
                  onClick={dismiss}
                  className="rounded-full border border-white/10 bg-surface-2 px-3.5 py-2 text-xs font-medium text-muted transition hover:border-accent/40 hover:text-foreground hover:bg-surface-3"
                >
                  Dismiss
                </button>
              </div>
            </div>
          )}

          {job.status === "expired" && (
            <div className="glass-panel-subtle flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/[0.08] p-4 backdrop-blur-md">
              <div className="flex items-center gap-3">
                <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-muted-dim" />
                <div>
                  <p className="text-xs font-semibold text-foreground">
                    Archive expired
                  </p>
                  <p className="text-[11px] text-muted">
                    Files are kept for 7 days. You can regenerate the archive anytime.
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => void start(selectMode ? [...selectedIds] : undefined)}
                  className="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-background shadow-sm transition hover:bg-accent-hover"
                >
                  Rebuild ZIP
                </button>
                <button
                  type="button"
                  onClick={dismiss}
                  className="rounded-full border border-white/10 bg-surface-2 px-3.5 py-2 text-xs font-medium text-muted transition hover:border-accent/40 hover:text-foreground hover:bg-surface-3"
                >
                  Dismiss
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
