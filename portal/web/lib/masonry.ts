/**
 * Virtualized masonry layout (Task 22).
 *
 * Tiles are packed greedily into the shortest column (the Google Photos /
 * Masonry.js distribution), using each item's exact thumb aspect ratio so the
 * packed height is known before a single image decodes — no layout shift, and
 * only the visible slice is ever rendered.
 *
 * The packer is incremental: appending a cursor page is O(new items). Per-column
 * node arrays stay top-sorted, so the visible slice costs
 * `O(columns · log n + visible)` with no global re-sort as the window grows.
 */

import type { MediaView } from "@/lib/api";

export interface MasonryNode {
  kind: "tile" | "divider";
  /** Stable React key. */
  key: string;
  /** Index into the window's item array (tiles; -1 for dividers). */
  index: number;
  top: number;
  left: number;
  width: number;
  height: number;
  /** Upload-day identity/label, on tiles and dividers alike. */
  dayKey?: string;
  dayLabel?: string;
}

export interface MasonryMetrics {
  /** Grid container width in px. */
  width: number;
  columns: number;
  /** Gutter between tiles, horizontal and vertical. */
  gap: number;
  /** Height reserved for an in-flow upload-day divider. */
  dividerHeight: number;
  /** Aspect ratio used when a thumb's size is not (yet) known. */
  fallbackAspect: number;
}

export const DEFAULT_ASPECT = 3 / 2;
export const VIDEO_ASPECT = 16 / 9;
/** Guards against absurd tiles from odd crop/rotation metadata. */
const MIN_ASPECT = 0.5;
const MAX_ASPECT = 3.2;
/** Vertical space a divider occupies in the flow. */
export const DIVIDER_HEIGHT = 48;

function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value));
}

/** Breakpoints match the old `columns-2 sm:columns-3 lg:columns-4` classes. */
export function columnCountFor(width: number): number {
  if (width < 640) {
    return 2;
  }
  if (width < 1024) {
    return 3;
  }
  return 4;
}

/** Local calendar day of an upload instant; empty string when unparseable. */
export function dayKeyOf(createdAt: string): string {
  const date = new Date(createdAt);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  return `${date.getFullYear()}-${date.getMonth() + 1}-${date.getDate()}`;
}

export function dayLabelOf(createdAt: string): string {
  const date = new Date(createdAt);
  if (Number.isNaN(date.getTime())) {
    return "Date unknown";
  }
  return date.toLocaleDateString(undefined, {
    day: "numeric",
    month: "long",
    year: "numeric",
  });
}

/** Exact aspect ratio when known, otherwise a kind-appropriate fallback. */
export function aspectOf(item: MediaView, fallback: number): number {
  const width = item.thumbWidth ?? 0;
  const height = item.thumbHeight ?? 0;
  if (width > 0 && height > 0) {
    return clamp(width / height, MIN_ASPECT, MAX_ASPECT);
  }
  return item.kind === "video" ? VIDEO_ASPECT : fallback;
}

/** First index whose `top` is >= target (arrays are top-sorted). */
function lowerBoundByTop(nodes: MasonryNode[], target: number): number {
  let low = 0;
  let high = nodes.length;
  while (low < high) {
    const mid = (low + high) >> 1;
    if (nodes[mid].top < target) {
      low = mid + 1;
    } else {
      high = mid;
    }
  }
  return low;
}

export class MasonryLayout {
  private readonly columns: MasonryNode[][];
  private readonly dividers: MasonryNode[] = [];
  private readonly columnTops: number[];
  private readonly columnWidth: number;
  private lastDay: string | null = null;
  private maxHeight = DIVIDER_HEIGHT;
  private placed = 0;

  /** Total packed height (container height). */
  height = 0;

  constructor(private readonly metrics: MasonryMetrics) {
    const columns = Math.max(1, metrics.columns);
    this.columnWidth =
      (metrics.width - metrics.gap * (columns - 1)) / columns;
    this.columnTops = new Array(columns).fill(0);
    this.columns = Array.from({ length: columns }, () => []);
  }

  get count(): number {
    return this.placed;
  }

  get tileWidth(): number {
    return this.columnWidth;
  }

  /** Adds newly loaded items to the end of the flow. */
  append(items: MediaView[]): void {
    const { gap, dividerHeight, width } = this.metrics;
    for (const item of items) {
      const day = dayKeyOf(item.createdAt);
      let label: string | undefined;
      if (day && day !== this.lastDay) {
        label = dayLabelOf(item.createdAt);
        const top = Math.max(...this.columnTops);
        this.dividers.push({
          kind: "divider",
          key: `day-${day}-${this.placed}`,
          index: -1,
          top,
          left: 0,
          width,
          height: dividerHeight,
          dayKey: day,
          dayLabel: label,
        });
        this.columnTops.fill(top + dividerHeight);
        this.lastDay = day;
      }

      const column = this.shortestColumn();
      const height = Math.round(
        this.columnWidth / aspectOf(item, this.metrics.fallbackAspect)
      );
      this.columns[column].push({
        kind: "tile",
        key: `m-${item.id}`,
        index: this.placed,
        top: this.columnTops[column],
        left: column * (this.columnWidth + gap),
        width: this.columnWidth,
        height,
        dayKey: day || undefined,
        dayLabel: label ?? dayLabelOf(item.createdAt),
      });
      this.columnTops[column] += height + gap;
      this.maxHeight = Math.max(this.maxHeight, height);
      this.placed++;
    }
    const bottom = Math.max(...this.columnTops);
    this.height = this.placed > 0 ? Math.max(0, bottom - gap) : 0;
  }

  private shortestColumn(): number {
    let best = 0;
    for (let i = 1; i < this.columnTops.length; i++) {
      if (this.columnTops[i] < this.columnTops[best]) {
        best = i;
      }
    }
    return best;
  }

  /**
   * Nodes intersecting the viewport band `[top, top + height]`, widened by
   * `overscan` on both sides. A tile may start far above the band and still be
   * visible (tall portraits), so the scan starts one max tile height earlier.
   */
  visible(top: number, height: number, overscan: number): MasonryNode[] {
    const from = top - overscan;
    const to = top + height + overscan;
    const scanFrom = from - this.maxHeight;
    const out: MasonryNode[] = [];

    for (const column of this.columns) {
      for (let i = lowerBoundByTop(column, scanFrom); i < column.length; i++) {
        const node = column[i];
        if (node.top > to) {
          break;
        }
        if (node.top + node.height >= from) {
          out.push(node);
        }
      }
    }

    for (let i = lowerBoundByTop(this.dividers, scanFrom); i < this.dividers.length; i++) {
      const node = this.dividers[i];
      if (node.top > to) {
        break;
      }
      out.push(node);
    }

    out.sort((a, b) => a.top - b.top || a.left - b.left);
    return out;
  }
}
