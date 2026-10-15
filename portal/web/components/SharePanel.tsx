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

  useEffect(() => {
    const controller = new AbortController();
    void load(controller.signal);
    return () => controller.abort();
  }, [load]);

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
      className="mb-6 rounded-lg border border-border bg-surface-2 p-4"
    >
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-sm font-semibold">Client link + views</h2>
        {stats && (
          <p className="text-sm text-muted" aria-live="polite">
            {stats.views} view{stats.views === 1 ? "" : "s"} · {stats.downloads} download
            {stats.downloads === 1 ? "" : "s"}
          </p>
        )}
      </div>

      {error && (
        <p role="alert" className="mt-3 text-sm text-red-400">
          {error}
        </p>
      )}

      {!status ? (
        <p className="mt-3 text-sm text-muted">Loading sharing…</p>
      ) : !status.issued ? (
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <p className="text-sm text-muted">No client link yet.</p>
          <button
            type="button"
            onClick={() => void onIssue()}
            disabled={busy}
            className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 disabled:opacity-50"
          >
            {busy ? "Working…" : "Create client link"}
          </button>
        </div>
      ) : (
        <div className="mt-3 flex flex-col gap-3">
          <p className="text-sm">
            <span
              className={`mr-2 inline-block h-2 w-2 rounded-full ${status.revoked ? "bg-red-400" : "bg-green-400"}`}
            />
            {status.revoked ? "Link revoked — visitors see an invalid-link notice." : "Link active — anyone with it can view and download."}
          </p>

          {link ? (
            <div className="flex flex-wrap items-center gap-2">
              <code className="min-w-0 flex-1 break-all rounded border border-border bg-surface px-3 py-2 text-xs">
                {link}
              </code>
              <button
                type="button"
                onClick={() => void onCopy()}
                className="rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
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

          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              onClick={() => void onRotate()}
              disabled={busy}
              className="rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2 disabled:opacity-50"
            >
              {busy ? "Working…" : "Rotate link"}
            </button>
            {!status.revoked && (
              <button
                type="button"
                onClick={() => void onRevoke()}
                disabled={busy}
                className="rounded-lg border border-border bg-surface px-4 py-2 text-sm text-red-300 transition hover:bg-surface disabled:opacity-50"
              >
                {busy ? "Working…" : "Revoke link"}
              </button>
            )}
            <button
              type="button"
              onClick={() => void load()}
              disabled={busy}
              className="text-sm text-muted hover:text-foreground disabled:opacity-50"
            >
              Refresh counts
            </button>
          </div>
        </div>
      )}
    </section>
  );
}
