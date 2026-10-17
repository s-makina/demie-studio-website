"use client";

import { useCallback, useMemo, useRef, useState } from "react";

export interface TimelineMark {
  /** Absolute index of the first item of that upload day. */
  index: number;
  label: string;
}

interface TimelineRailProps {
  /** Total media in the project. */
  total: number;
  /** Absolute index of the window's first loaded item. */
  offset: number;
  /** Loaded items in the window. */
  loaded: number;
  /** Absolute index of the topmost item currently on screen. */
  current: number;
  /** Upload-day ticks inside the loaded window (unique per day). */
  marks: TimelineMark[];
  /** A seek request is in flight. */
  busy: boolean;
  /** Jump the gallery to an absolute index. */
  onSeek: (offset: number) => void;
}

/**
 * Fixed right-edge scrubber over the whole project (Task 22). The thumb tracks
 * the on-screen position, the highlighted band shows which slice is loaded, and
 * ticks mark visited upload days. Dragging previews a target; releasing seeks to
 * it. Seeking is forward-only in the API, so this rail is the way back into a
 * deep archive.
 */
export default function TimelineRail({
  total,
  offset,
  loaded,
  current,
  marks,
  busy,
  onSeek,
}: TimelineRailProps) {
  const trackRef = useRef<HTMLDivElement>(null);
  const [dragIndex, setDragIndex] = useState<number | null>(null);

  const span = Math.max(1, total - 1);
  const pct = useCallback((index: number) => (index / span) * 100, [span]);

  const indexFromClientY = useCallback(
    (clientY: number) => {
      const rect = trackRef.current?.getBoundingClientRect();
      if (!rect || rect.height === 0) {
        return 0;
      }
      const fraction = Math.min(1, Math.max(0, (clientY - rect.top) / rect.height));
      return Math.round(fraction * span);
    },
    [span]
  );

  const label = useMemo(() => {
    const index = dragIndex ?? current;
    let best: TimelineMark | null = null;
    for (const mark of marks) {
      if (mark.index <= index && (!best || mark.index > best.index)) {
        best = mark;
      }
    }
    const position = `No. ${Math.min(total, Math.max(1, index + 1)).toLocaleString()} of ${total.toLocaleString()}`;
    return best ? `${best.label} · ${position}` : position;
  }, [dragIndex, current, marks, total]);

  const release = useCallback(() => {
    const target = dragIndex;
    setDragIndex(null);
    if (target !== null && target !== offset) {
      onSeek(target);
    }
  }, [dragIndex, offset, onSeek]);

  // Everything is already loaded: there is nowhere to seek.
  if (total <= 1 || total <= loaded) {
    return null;
  }

  const shown = dragIndex ?? current;
  const step = Math.max(1, Math.round(total / 100));

  return (
    <div className="pointer-events-none fixed right-2 top-1/2 z-30 hidden -translate-y-1/2 sm:block">
      <div className="pointer-events-auto flex flex-col items-center gap-2">
        {offset > 0 && (
          <button
            type="button"
            onClick={() => onSeek(0)}
            className="rounded-full border border-white/10 bg-surface-glass px-2.5 py-1 text-[9px] font-medium uppercase tracking-widest2 text-muted shadow-lg backdrop-blur-xl transition hover:border-accent/40 hover:text-accent"
          >
            Start
          </button>
        )}

        <div
          ref={trackRef}
          role="slider"
          tabIndex={0}
          aria-label="Timeline — jump through this shoot"
          aria-orientation="vertical"
          aria-valuemin={0}
          aria-valuemax={total - 1}
          aria-valuenow={Math.min(total - 1, Math.max(0, shown))}
          aria-valuetext={label}
          onPointerDown={(event) => {
            event.currentTarget.setPointerCapture(event.pointerId);
            setDragIndex(indexFromClientY(event.clientY));
          }}
          onPointerMove={(event) => {
            if (dragIndex !== null) {
              setDragIndex(indexFromClientY(event.clientY));
            }
          }}
          onPointerUp={release}
          onPointerCancel={() => setDragIndex(null)}
          onKeyDown={(event) => {
            const clampTo = (value: number) =>
              Math.min(total - 1, Math.max(0, value));
            if (event.key === "ArrowUp" || event.key === "ArrowLeft") {
              event.preventDefault();
              onSeek(clampTo(current - step));
            } else if (event.key === "ArrowDown" || event.key === "ArrowRight") {
              event.preventDefault();
              onSeek(clampTo(current + step));
            } else if (event.key === "Home") {
              event.preventDefault();
              onSeek(0);
            } else if (event.key === "End") {
              event.preventDefault();
              onSeek(total - 1);
            }
          }}
          className="group relative flex h-[52vh] w-6 cursor-pointer touch-none items-center justify-center outline-none"
        >
          {/* Track */}
          <span className="absolute h-full w-[3px] rounded-full bg-white/[0.10] transition group-hover:bg-white/[0.16] group-focus-visible:ring-2 group-focus-visible:ring-accent/50" />

          {/* Loaded-window band */}
          <span
            className="absolute w-[3px] rounded-full bg-accent/25"
            style={{
              top: `${pct(offset)}%`,
              height: `${Math.max(0.8, pct(Math.min(total - 1, offset + loaded - 1)) - pct(offset))}%`,
            }}
          />

          {/* Upload-day ticks */}
          {marks.map((mark) => (
            <span
              key={`${mark.index}-${mark.label}`}
              aria-hidden="true"
              title={mark.label}
              className="absolute h-px w-3 bg-white/25"
              style={{ top: `${pct(mark.index)}%` }}
            />
          ))}

          {/* Thumb */}
          <span
            aria-hidden="true"
            className={`absolute h-3.5 w-3.5 rounded-full border border-background bg-accent shadow-[0_0_10px_rgba(212,167,90,0.5)] transition-transform ${
              busy || dragIndex !== null ? "scale-125" : "group-hover:scale-110"
            }`}
            style={{ top: `${pct(Math.min(total - 1, Math.max(0, shown)))}%` }}
          />
        </div>

        <span className="pointer-events-none w-16 text-center text-[9px] font-medium uppercase tracking-widest2 text-muted-dim">
          {Math.min(total, Math.max(1, shown + 1)).toLocaleString()}
          <span className="text-white/20"> / </span>
          {total.toLocaleString()}
        </span>
      </div>

      {/* Drag / keyboard readout */}
      {(dragIndex !== null || busy) && (
        <div className="pointer-events-none absolute right-8 top-1/2 -translate-y-1/2 whitespace-nowrap rounded-full border border-white/10 bg-surface-glass px-3 py-1.5 text-[10px] font-medium text-foreground shadow-xl backdrop-blur-xl">
          {busy ? "Jumping…" : label}
        </div>
      )}
    </div>
  );
}
