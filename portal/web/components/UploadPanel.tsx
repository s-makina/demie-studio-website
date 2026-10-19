"use client";

import { useRef, useState } from "react";
import type { UploadQueue } from "@/lib/useUploadQueue";

/**
 * Photographer upload dropzone (Task 20). The queue itself lives in
 * {@link useUploadQueue} — owned by the project page and rendered as the
 * leading tiles of the gallery column ({@link PendingUploads}) — so this panel
 * is just the dropzone and the batch controls.
 */
export default function UploadPanel({ queue }: { queue: UploadQueue }) {
  const { files, running, queued, done, addFiles, startAll, stop, clearDone } =
    queue;
  const [dragOver, setDragOver] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const failed = files.filter((f) => f.status === "error").length;

  return (
    <section
      aria-label="Upload photos"
      className="mb-6 rounded-2xl border border-white/[0.08] bg-surface p-6 shadow-xl"
    >
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <div>
          <h2 className="font-serif text-xl tracking-wide text-foreground">
            Upload Media
          </h2>
          <p className="mt-0.5 text-xs text-muted">
            Add photos or videos to this shoot gallery
          </p>
        </div>
        {files.length > 0 && (
          <p
            className="rounded-full border border-white/10 bg-surface-2 px-3 py-1 text-xs font-medium text-muted"
            aria-live="polite"
          >
            <span className="font-semibold text-accent">{done}</span> of{" "}
            {files.length} uploaded
            {failed > 0 && (
              <>
                {" · "}
                <span className="font-semibold text-rose-400">
                  {failed} failed
                </span>
              </>
            )}
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
        <p className="mt-4 font-serif text-lg tracking-wide text-foreground">
          Drag photos or videos here
        </p>
        <p className="mt-1 text-xs text-muted">
          or{" "}
          <span className="font-medium text-accent underline decoration-accent/40 underline-offset-4 transition group-hover:decoration-accent">
            browse files
          </span>{" "}
          — uploads sequentially in display order
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
              onClick={stop}
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
      )}
      {files.length > 0 && (
        <p className="mt-3 text-[11px] leading-relaxed text-muted">
          Selected files appear at the top of the gallery. Remove or retry an
          upload from its tile.
        </p>
      )}
    </section>
  );
}
