"use client";

import { useCallback, useEffect, useId, useRef, useState } from "react";

/* Shared photographer-console primitives (Phase 6 UI bar): dialogs with
 * focus handling, confirm dialogs for destructive acts, copy buttons with
 * "Copied" feedback, and toast errors instead of bare red text. */

export interface Toast {
  id: number;
  message: string;
}

let toastSeq = 1;

export function useToasts() {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const push = useCallback((message: string) => {
    const id = toastSeq++;
    setToasts((prev) => [...prev.slice(-3), { id, message }]);
    window.setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
    }, 5000);
  }, []);
  const dismiss = useCallback((id: number) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);
  return { toasts, push, dismiss };
}

export function ToastStack({
  toasts,
  onDismiss,
}: {
  toasts: Toast[];
  onDismiss: (id: number) => void;
}) {
  if (toasts.length === 0) {
    return null;
  }
  return (
    <div
      aria-live="polite"
      className="pointer-events-none fixed inset-x-0 bottom-4 z-[70] mx-auto flex w-full max-w-md flex-col items-center gap-2 px-4"
    >
      {toasts.map((toast) => (
        <button
          key={toast.id}
          type="button"
          onClick={() => onDismiss(toast.id)}
          className="pointer-events-auto w-full rounded-lg border border-border bg-surface px-4 py-3 text-left text-sm text-foreground shadow-xl"
        >
          {toast.message}
        </button>
      ))}
    </div>
  );
}

export function Dialog({
  open,
  title,
  description,
  onClose,
  children,
}: {
  open: boolean;
  title: string;
  description?: string;
  onClose: () => void;
  children: React.ReactNode;
}) {
  const titleId = useId();
  const firstField = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) {
      return;
    }
    function onKey(event: KeyboardEvent) {
      if (event.key === "Escape") {
        onClose();
      }
    }
    window.addEventListener("keydown", onKey);
    // Focus the first field when the dialog opens.
    const timer = window.setTimeout(() => {
      const input = firstField.current?.querySelector<
        HTMLInputElement | HTMLButtonElement | HTMLSelectElement
      >("input, button, select, textarea");
      input?.focus();
    }, 30);
    document.body.style.overflow = "hidden";
    return () => {
      window.removeEventListener("keydown", onKey);
      window.clearTimeout(timer);
      document.body.style.overflow = "";
    };
  }, [open, onClose]);

  if (!open) {
    return null;
  }
  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby={titleId}
      className="fixed inset-0 z-[60] flex items-end justify-center bg-black/70 p-4 sm:items-center"
      onClick={onClose}
    >
      <div
        ref={firstField}
        onClick={(e) => e.stopPropagation()}
        className="w-full max-w-md rounded-xl border border-border bg-surface p-6 shadow-2xl"
      >
        <h2 id={titleId} className="text-lg font-semibold">
          {title}
        </h2>
        {description && <p className="mt-1 text-sm text-muted">{description}</p>}
        <div className="mt-4">{children}</div>
      </div>
    </div>
  );
}

export function ConfirmDialog({
  open,
  title,
  body,
  confirmLabel,
  busy,
  onCancel,
  onConfirm,
}: {
  open: boolean;
  title: string;
  body: string;
  confirmLabel: string;
  busy?: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  return (
    <Dialog open={open} title={title} onClose={onCancel}>
      <p className="text-sm text-muted">{body}</p>
      <div className="mt-5 flex justify-end gap-2">
        <button
          type="button"
          onClick={onCancel}
          disabled={busy}
          className="rounded-lg border border-border bg-surface-2 px-4 py-2 text-sm text-foreground transition hover:bg-surface disabled:opacity-50"
        >
          Cancel
        </button>
        <button
          type="button"
          onClick={onConfirm}
          disabled={busy}
          className="rounded-lg bg-red-500/90 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-500 disabled:opacity-50"
        >
          {busy ? "Working…" : confirmLabel}
        </button>
      </div>
    </Dialog>
  );
}

export function CopyButton({ value, label }: { value: string; label?: string }) {
  const [copied, setCopied] = useState(false);
  const onCopy = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2500);
    } catch {
      // Clipboard denied (older Android WebView): select manually instead.
      window.prompt("Copy this link:", value);
    }
  }, [value]);
  return (
    <button
      type="button"
      onClick={() => void onCopy()}
      className="shrink-0 rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
    >
      {copied ? "Copied ✓" : (label ?? "Copy")}
    </button>
  );
}

/** Cover-led card art: warm gradient monogram (photo thumbs load inside). */
export function ProjectCover({
  name,
  thumbUrl,
}: {
  name: string;
  thumbUrl?: string | null;
}) {
  const initial = (name.trim().charAt(0) || "D").toUpperCase();
  return (
    <div className="relative aspect-[16/9] w-full overflow-hidden bg-gradient-to-br from-[#3a2c18] via-surface-2 to-surface">
      <div
        aria-hidden="true"
        className="absolute inset-0 opacity-40"
        style={{
          backgroundImage:
            "radial-gradient(circle at 20% 20%, rgba(212,167,90,.45), transparent 55%), radial-gradient(circle at 85% 90%, rgba(212,167,90,.25), transparent 50%)",
        }}
      />
      {thumbUrl ? (
        // Plain <img>: thumbs are short-lived presigned cross-origin URLs.
        <img
          src={thumbUrl}
          alt=""
          loading="lazy"
          decoding="async"
          className="absolute inset-0 h-full w-full object-cover"
        />
      ) : (
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="flex h-14 w-14 items-center justify-center rounded-full border border-accent/40 bg-black/40 text-2xl font-semibold text-accent">
            {initial}
          </span>
        </div>
      )}
    </div>
  );
}

export function SkeletonCards({ count = 4 }: { count?: number }) {
  return (
    <ul aria-label="Loading projects" className="grid gap-4 sm:grid-cols-2">
      {Array.from({ length: count }).map((_, i) => (
        <li
          key={i}
          className="animate-pulse overflow-hidden rounded-xl border border-border bg-surface-2"
        >
          <div className="aspect-[16/9] bg-surface" />
          <div className="space-y-2 p-5">
            <div className="h-4 w-2/3 rounded bg-surface" />
            <div className="h-3 w-1/3 rounded bg-surface" />
          </div>
        </li>
      ))}
    </ul>
  );
}
