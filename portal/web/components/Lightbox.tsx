"use client";

import { useCallback, useEffect } from "react";
import { downloadUrl, type MediaAccess, type MediaView } from "@/lib/api";

/**
 * Lightbox (Tasks 9 + 10). Photo shows the preview derivative; video shows
 * its poster frame (the original streams only as a download — plain media
 * elements can't send the session cookie cross-origin). Download = plain
 * link to the 302 endpoint, which forces save-as via Content-Disposition.
 */
export default function Lightbox({
  items,
  index,
  access,
  onClose,
  onIndex,
  isAdmin,
  onRemove,
}: {
  items: MediaView[];
  index: number;
  access: MediaAccess;
  onClose: () => void;
  onIndex: (index: number) => void;
  isAdmin?: boolean;
  onRemove?: (item: MediaView) => void;
}) {
  const item = items[index];
  const hasPrev = index > 0;
  const hasNext = index < items.length - 1;

  const prev = useCallback(() => {
    if (hasPrev) {
      onIndex(index - 1);
    }
  }, [hasPrev, onIndex, index]);

  const next = useCallback(() => {
    if (hasNext) {
      onIndex(index + 1);
    }
  }, [hasNext, onIndex, index]);

  useEffect(() => {
    function onKey(event: KeyboardEvent) {
      if (event.key === "Escape") {
        onClose();
      } else if (event.key === "ArrowLeft") {
        prev();
      } else if (event.key === "ArrowRight") {
        next();
      }
    }
    window.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [onClose, prev, next]);

  if (!item) {
    return null;
  }

  const fullSrc = item.previewUrl ?? item.thumbUrl;

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={`Photo ${index + 1} of ${items.length}`}
      className="fixed inset-0 z-50 flex flex-col bg-black/95 backdrop-blur-xl animate-in fade-in duration-200"
      onClick={onClose}
    >
      {/* Top floating bar: Counter & Actions */}
      <div
        className="pointer-events-none absolute inset-x-0 top-0 z-30 flex items-center justify-between p-4 sm:p-6"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Slide counter badge */}
        <div className="pointer-events-auto flex items-center gap-2 rounded-full border border-white/10 bg-surface-glass px-4 py-1.5 text-xs font-medium text-foreground backdrop-blur-xl shadow-lg">
          <span className="font-serif text-sm font-semibold italic text-accent">
            {index + 1}
          </span>
          <span className="text-white/20">/</span>
          <span className="text-muted">{items.length}</span>
          {item.kind === "video" && (
            <span className="ml-1.5 rounded-full bg-accent/20 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-widest text-accent">
              Film
            </span>
          )}
        </div>

        {/* Action buttons */}
        <div className="pointer-events-auto flex items-center gap-3">
          <a
            href={downloadUrl(access, item.id)}
            className="inline-flex items-center gap-1.5 rounded-full bg-accent px-4 py-2 text-xs font-medium text-background transition hover:bg-accent-hover shadow-lg"
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            <span>Download</span>
          </a>

          {isAdmin && item.status !== "uploading" && (
            <button
              type="button"
              onClick={() => onRemove?.(item)}
              className="rounded-full border border-red-500/30 bg-red-500/10 px-3.5 py-2 text-xs font-medium text-red-300 backdrop-blur-xl transition hover:bg-red-500/20 hover:border-red-500/50"
            >
              Remove
            </button>
          )}

          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-surface-glass text-muted transition hover:border-accent/40 hover:text-foreground backdrop-blur-xl"
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>
      </div>

      {/* Main image / media stage with generous breathing room */}
      <div
        className="relative flex flex-1 items-center justify-center p-6 sm:p-14 md:p-20"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Previous navigation button */}
        {hasPrev && (
          <button
            type="button"
            onClick={prev}
            aria-label="Previous photo"
            className="absolute left-4 sm:left-6 z-20 flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-full border border-white/10 bg-surface-glass text-foreground backdrop-blur-xl transition hover:border-accent/50 hover:bg-surface-glass/90 hover:scale-105 active:scale-95 shadow-xl"
          >
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.75"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <polyline points="15 18 9 12 15 6" />
            </svg>
          </button>
        )}

        {fullSrc ? (
          <img
            src={fullSrc}
            alt=""
            className="max-h-[82vh] max-w-[88vw] rounded-xl object-contain shadow-2xl transition duration-300 select-none"
          />
        ) : (
          <div className="flex flex-col items-center justify-center p-8 text-center">
            <p className="font-serif text-lg italic text-muted">
              {item.derivativeStatus === "failed"
                ? "Preview failed — use Download for the original."
                : "Preview still processing — use Download for the original."}
            </p>
            <a
              href={downloadUrl(access, item.id)}
              className="mt-4 rounded-full bg-accent px-4 py-2 text-xs font-medium text-background transition hover:bg-accent-hover"
            >
              Download Original
            </a>
          </div>
        )}

        {/* Next navigation button */}
        {hasNext && (
          <button
            type="button"
            onClick={next}
            aria-label="Next photo"
            className="absolute right-4 sm:right-6 z-20 flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-full border border-white/10 bg-surface-glass text-foreground backdrop-blur-xl transition hover:border-accent/50 hover:bg-surface-glass/90 hover:scale-105 active:scale-95 shadow-xl"
          >
            <svg
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.75"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </button>
        )}
      </div>

      {/* Centered floating bottom pill */}
      <div
        className="pointer-events-none absolute inset-x-0 bottom-6 z-30 flex justify-center"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="pointer-events-auto flex items-center gap-4 rounded-full border border-white/10 bg-surface-glass/90 px-4 py-2 shadow-2xl backdrop-blur-xl">
          <button
            type="button"
            onClick={prev}
            disabled={!hasPrev}
            aria-label="Previous photo"
            className="flex h-7 w-7 items-center justify-center rounded-full text-foreground transition hover:text-accent disabled:opacity-30 disabled:hover:text-foreground"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="15 18 9 12 15 6" />
            </svg>
          </button>

          <span className="text-xs font-medium text-muted">
            {index + 1} of {items.length}
          </span>

          <button
            type="button"
            onClick={next}
            disabled={!hasNext}
            aria-label="Next photo"
            className="flex h-7 w-7 items-center justify-center rounded-full text-foreground transition hover:text-accent disabled:opacity-30 disabled:hover:text-foreground"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </button>

          <span className="h-3 w-px bg-white/15" />

          <a
            href={downloadUrl(access, item.id)}
            className="text-xs font-medium text-accent transition hover:text-accent-hover"
          >
            Save High-Res
          </a>
        </div>
      </div>
    </div>
  );
}
