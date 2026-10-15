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
      className="fixed inset-0 z-50 flex flex-col bg-black/90"
      onClick={onClose}
    >
      <div
        className="flex items-center justify-between px-4 py-3 text-sm text-foreground"
        onClick={(e) => e.stopPropagation()}
      >
        <span className="text-muted">
          {index + 1} / {items.length}
          {item.kind === "video" ? " · video" : ""}
        </span>
        <div className="flex items-center gap-3">
          <a
            href={downloadUrl(access, item.id)}
            className="rounded-lg bg-accent px-4 py-2 font-medium text-background transition hover:bg-accent-hover"
          >
            Download
          </a>
          {isAdmin && item.status !== "uploading" && (
            <button
              type="button"
              onClick={() => onRemove?.(item)}
              className="rounded-lg border border-border px-3 py-2 text-red-300 transition hover:bg-surface-2"
            >
              Remove
            </button>
          )}
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="rounded-lg border border-border px-3 py-2 text-foreground transition hover:bg-surface-2"
          >
            ✕
          </button>
        </div>
      </div>

      <div
        className="relative flex flex-1 items-center justify-center px-4 pb-4"
        onClick={(e) => e.stopPropagation()}
      >
        {hasPrev && (
          <button
            type="button"
            onClick={prev}
            aria-label="Previous photo"
            className="absolute left-2 z-10 rounded-full border border-border bg-surface/80 px-3 py-2 text-foreground transition hover:bg-surface-2 sm:left-4"
          >
            ‹
          </button>
        )}

        {fullSrc ? (
          <img
            src={fullSrc}
            alt=""
            className="max-h-full max-w-full rounded-lg object-contain"
          />
        ) : (
          <p className="text-muted">
            {item.derivativeStatus === "failed"
              ? "Preview failed — use Download for the original."
              : "Preview still processing — use Download for the original."}
          </p>
        )}

        {hasNext && (
          <button
            type="button"
            onClick={next}
            aria-label="Next photo"
            className="absolute right-2 z-10 rounded-full border border-border bg-surface/80 px-3 py-2 text-foreground transition hover:bg-surface-2 sm:right-4"
          >
            ›
          </button>
        )}
      </div>
    </div>
  );
}
