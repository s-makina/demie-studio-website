"use client";

import { useCallback, useEffect, useState } from "react";
import {
  ApiError,
  fetchProjectStats,
  fetchShareStatus,
  isAbort,
  issueShare,
  revokeShare,
  rotateShare,
  shareUrl,
  type ProjectStats,
  type ShareStatus,
} from "@/lib/api";

interface SharePanelProps {
  projectId: number;
}

/**
 * Photographer share + analytics panel (Task 14). Copy link, rotate, revoke
 * (instant deny), and per-project views + downloads. The raw token is only
 * ever returned once by issue/rotate, so after a reload the panel shows
 * active/revoked state without a copyable link until the next rotate.
 */
export default function SharePanel({ projectId }: SharePanelProps) {
  const [status, setStatus] = useState<ShareStatus | null>(null);
  const [stats, setStats] = useState<ProjectStats | null>(null);
  const [link, setLink] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [copied, setCopied] = useState(false);

  const load = useCallback(
    async (signal?: AbortSignal) => {
      try {
        const [nextStatus, nextStats] = await Promise.all([
          fetchShareStatus(projectId, signal),
          fetchProjectStats(projectId, signal),
        ]);
        setStatus(nextStatus);
        setStats(nextStats);
        setError(null);
      } catch (e) {
        if (!isAbort(e)) {
          setError(e instanceof Error ? e.message : "Could not load sharing.");
        }
      }
    },
    [projectId]
  );

  // Mount-once by design: subscribe to the two photographer reads, then paint.
  // setState only runs inside the promise callbacks below (external update),
  // which keeps react-hooks/set-state-in-effect happy.
  useEffect(() => {
    const controller = new AbortController();
    Promise.all([
      fetchShareStatus(projectId, controller.signal),
      fetchProjectStats(projectId, controller.signal),
    ])
      .then(([nextStatus, nextStats]) => {
        setStatus(nextStatus);
        setStats(nextStats);
      })
      .catch((e: unknown) => {
        if (!isAbort(e)) {
          setError(e instanceof Error ? e.message : "Could not load sharing.");
        }
      });
    return () => controller.abort();
  }, [projectId]);

  const onIssue = useCallback(async () => {
    setBusy(true);
    setError(null);
    try {
      const issued = await issueShare(projectId);
      setLink(shareUrl(issued.token));
      await load();
    } catch (e) {
      if (e instanceof ApiError && e.status === 409) {
        setError("A link already exists — rotate to get a fresh one.");
      } else if (!isAbort(e)) {
        setError(e instanceof Error ? e.message : "Could not create the link.");
      }
    } finally {
      setBusy(false);
    }
  }, [projectId, load]);

  const onRotate = useCallback(async () => {
    setBusy(true);
    setError(null);
    try {
      const issued = await rotateShare(projectId);
      setLink(shareUrl(issued.token));
      setCopied(false);
      await load();
    } catch (e) {
      if (!isAbort(e)) {
        setError(e instanceof Error ? e.message : "Could not rotate the link.");
      }
    } finally {
      setBusy(false);
    }
  }, [projectId, load]);

  const onRevoke = useCallback(async () => {
    setBusy(true);
    setError(null);
    try {
      await revokeShare(projectId);
      setLink(null);
      await load();
    } catch (e) {
      if (!isAbort(e)) {
        setError(e instanceof Error ? e.message : "Could not revoke the link.");
      }
    } finally {
      setBusy(false);
    }
  }, [projectId, load]);

  const onCopy = useCallback(async () => {
    if (!link) {
      return;
    }
    try {
      await navigator.clipboard.writeText(link);
      setCopied(true);
    } catch {
      setCopied(false);
      setError("Copy failed — select the link manually.");
    }
  }, [link]);

  return (
    <section
      aria-label="Sharing and analytics"
      className="mb-6 rounded-2xl border border-white/[0.08] bg-surface p-6 shadow-xl"
    >
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <div>
          <h2 className="font-serif text-xl tracking-wide text-foreground">Client Delivery</h2>
          <p className="mt-0.5 text-xs text-muted">Direct viewing link and access tracking</p>
        </div>
      </div>

      {stats && (
        <div className="mt-4 grid grid-cols-2 gap-3" aria-live="polite">
          <div className="rounded-xl border border-white/[0.06] bg-surface-2 p-4">
            <div className="font-serif text-2xl tabular-nums text-accent">
              {stats.views}
            </div>
            <div className="mt-1 text-xs text-muted">
              {stats.views === 1 ? "Gallery View" : "Gallery Views"}
            </div>
          </div>
          <div className="rounded-xl border border-white/[0.06] bg-surface-2 p-4">
            <div className="font-serif text-2xl tabular-nums text-accent">
              {stats.downloads}
            </div>
            <div className="mt-1 text-xs text-muted">
              {stats.downloads === 1 ? "Download" : "Downloads"}
            </div>
          </div>
        </div>
      )}

      {error && (
        <p role="alert" className="mt-3 text-xs text-rose-400">
          {error}
        </p>
      )}

      {!status ? (
        <p className="mt-4 text-xs text-muted">Loading sharing…</p>
      ) : !status.issued ? (
        <div className="mt-4 flex flex-wrap items-center gap-3">
          <p className="text-xs text-muted">No client link yet.</p>
          <button
            type="button"
            onClick={() => void onIssue()}
            disabled={busy}
            className="rounded-full bg-accent px-5 py-2 text-xs font-medium text-background transition hover:bg-accent-hover disabled:opacity-50"
          >
            {busy ? "Working…" : "Create client link"}
          </button>
        </div>
      ) : (
        <div className="mt-4 flex flex-col gap-3.5">
          <div className="flex items-center gap-2 text-xs">
            <span
              className={`inline-block h-2 w-2 rounded-full ring-4 ${
                status.revoked
                  ? "bg-rose-400 ring-rose-400/20"
                  : "bg-emerald-400 ring-emerald-400/20"
              }`}
            />
            <span className={status.revoked ? "font-medium text-rose-300" : "font-medium text-muted"}>
              {status.revoked
                ? "Link revoked — visitors see an invalid-link notice."
                : "Link active — anyone with the link can view and download."}
            </span>
          </div>

          {link ? (
            <div className="flex flex-wrap items-center gap-2">
              <code className="min-w-0 flex-1 break-all rounded-xl border border-white/[0.06] bg-surface-2 px-3.5 py-2.5 font-mono text-xs text-foreground/90 selection:bg-accent/20">
                {link}
              </code>
              <button
                type="button"
                onClick={() => void onCopy()}
                className="shrink-0 rounded-full bg-accent px-4 py-2 text-xs font-medium text-background transition hover:bg-accent-hover"
              >
                {copied ? "Copied" : "Copy link"}
              </button>
            </div>
          ) : (
            !status.revoked && (
              <p className="text-xs text-muted">
                The full link is only shown right after creating or rotating it. Rotate to
                get a fresh copyable link.
              </p>
            )
          )}

          <div className="flex flex-wrap items-center gap-2.5 pt-1">
            <button
              type="button"
              onClick={() => void onRotate()}
              disabled={busy}
              className="rounded-full border border-white/10 bg-surface-2 px-4 py-2 text-xs font-medium text-foreground transition hover:border-accent/40 disabled:opacity-50"
            >
              {busy ? "Working…" : "Rotate link"}
            </button>
            {!status.revoked && (
              <button
                type="button"
                onClick={() => void onRevoke()}
                disabled={busy}
                className="rounded-full border border-rose-500/20 bg-rose-500/5 px-4 py-2 text-xs font-medium text-rose-300 transition hover:border-rose-500/40 hover:bg-rose-500/10 disabled:opacity-50"
              >
                {busy ? "Working…" : "Revoke link"}
              </button>
            )}
            <button
              type="button"
              onClick={() => void load()}
              disabled={busy}
              className="ml-auto text-xs text-muted transition hover:text-foreground disabled:opacity-50"
            >
              Refresh counts
            </button>
          </div>
        </div>
      )}
    </section>
  );
}
