"use client";

import type { UploadQueue } from "@/lib/useUploadQueue";

/**
 * The queued files, rendered as the leading tiles of the gallery grid so they
 * read as part of the shoot rather than as a cramped list in the sidebar.
 *
 * The column count and gutter mirror `columnCountFor()` and `GAP` in
 * `lib/masonry.ts`, so these tiles line up exactly with the packed masonry
 * below them. They are deliberately inert beyond Remove/Retry: a pending file
 * has no uploaded original, so it takes no part in selection, ZIP or the
 * lightbox.
 *
 * Status is carried by three non-overlapping regions, which matters because a
 * tile is only ~150px wide at the 2-column breakpoint: a Done badge in the top
 * right (finished tiles take no actions, so nothing can collide with it),
 * Remove/Retry in the top left, and the progress footer along the bottom.
 */
export default function PendingUploads({ queue }: { queue: UploadQueue }) {
  const { files, running, retryOne, removeOne } = queue;

  if (files.length === 0) {
    return null;
  }

  return (
    <ul
      aria-label="Photos and videos waiting to upload"
      className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
    >
      {files.map((entry) => {
        const percent = Math.round(entry.progress * 100);
        const failed = entry.status === "error";
        const done = entry.status === "done";
        return (
          <li
            key={entry.id}
            aria-busy={entry.status === "uploading" ? true : undefined}
            className={`group relative aspect-square overflow-hidden rounded-xl border bg-surface-2 transition-all duration-300 ${
              failed
                ? "border-rose-500/50"
                : "border-white/[0.08] hover:border-accent/50"
            }`}
          >
            {entry.preview ? (
              <img
                src={entry.preview}
                alt=""
                className={`h-full w-full object-cover transition-transform duration-500 will-change-transform group-hover:scale-[1.03] ${
                  failed ? "opacity-40" : ""
                }`}
              />
            ) : (
              <div className="flex h-full w-full flex-col items-center justify-center gap-2 p-3 pb-8 text-center">
                <svg
                  className="h-7 w-7 text-muted-dim"
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
                <span className="max-w-full truncate font-mono text-[10px] text-muted">
                  {entry.file.type.startsWith("video/")
                    ? "video"
                    : entry.file.name.slice(-12)}
                </span>
              </div>
            )}

            {/* In-flight spinner: covers the tile (dimmed) while bytes are
                actually being sent. Rendered before the progress footer in DOM
                order so the footer stays visible on top of the dim layer. */}
            {entry.status === "uploading" && (
              <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-black/40"
              >
                <svg
                  className="h-8 w-8 animate-spin text-accent"
                  viewBox="0 0 24 24"
                  fill="none"
                >
                  <circle
                    className="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    strokeWidth="4"
                  />
                  <path
                    className="opacity-90"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                  />
                </svg>
              </div>
            )}

            {done && (
              <span className="absolute right-2.5 top-2.5 z-10 rounded-full bg-emerald-500/90 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white shadow-lg backdrop-blur-sm">
                Done
              </span>
            )}

            {/* Remove (queued) / Retry (failed). Revealed on hover and keyboard
                focus, mirroring the gallery's own Remove affordance; a failed
                tile keeps them out permanently since that is the tile that
                needs an answer. `hover:none` covers touch devices, where hover
                never fires and the control would otherwise be unreachable. */}
            {!done && (
              <div
                className={`absolute left-2.5 top-2.5 z-10 flex items-center gap-1.5 transition ${
                  failed
                    ? "opacity-100"
                    : "opacity-0 focus-within:opacity-100 group-focus-within:opacity-100 group-hover:opacity-100 [@media(hover:none)]:opacity-100"
                }`}
              >
                {failed && (
                  <button
                    type="button"
                    onClick={() => retryOne(entry.id)}
                    disabled={running}
                    className="rounded-full bg-accent px-2.5 py-1 text-[11px] font-medium text-background shadow-lg transition hover:bg-accent-hover disabled:opacity-50"
                  >
                    Retry
                  </button>
                )}
                {entry.status !== "uploading" && (
                  <button
                    type="button"
                    onClick={() => removeOne(entry.id)}
                    aria-label={`Remove ${entry.file.name}`}
                    title="Remove"
                    className="rounded-full border border-white/10 bg-black/60 px-2.5 py-1 text-[11px] font-medium text-white/80 shadow-lg backdrop-blur-md transition hover:border-red-500/50 hover:bg-red-500 hover:text-white"
                  >
                    Remove
                  </button>
                )}
              </div>
            )}

            {!done && (
              <div className="absolute inset-x-0 bottom-0 z-10 flex flex-col gap-1 bg-gradient-to-t from-black/80 to-transparent px-2.5 pb-2 pt-6">
                {failed && entry.error && (
                  <p
                    role="alert"
                    title={entry.error}
                    className="truncate text-[10px] text-rose-300"
                  >
                    {entry.error}
                  </p>
                )}
                <div className="flex items-center gap-2">
                  <span
                    className={`shrink-0 text-[10px] font-medium tabular-nums ${
                      failed ? "text-rose-300" : "text-accent"
                    }`}
                  >
                    {percent}%
                  </span>
                  <div
                    role="progressbar"
                    aria-valuenow={percent}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={`Upload progress for ${entry.file.name}`}
                    className="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-white/20"
                  >
                    <div
                      className={`h-full rounded-full transition-all duration-300 ${
                        failed ? "bg-rose-500" : "bg-accent"
                      }`}
                      style={{ width: `${percent}%` }}
                    />
                  </div>
                </div>
              </div>
            )}

            {done && (
              <div className="absolute inset-x-0 bottom-0 h-1 bg-emerald-500" />
            )}
          </li>
        );
      })}
    </ul>
  );
}
