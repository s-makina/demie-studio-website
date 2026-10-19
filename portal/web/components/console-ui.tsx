"use client";

import { useCallback, useEffect, useId, useRef, useState } from "react";

/* Shared photographer-console primitives (Luxury Cinematic UI): dialogs with
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
      className="pointer-events-none fixed inset-x-0 bottom-6 z-[70] mx-auto flex w-full max-w-md flex-col items-center gap-2.5 px-4"
    >
      {toasts.map((toast) => (
        <button
          key={toast.id}
          type="button"
          onClick={() => onDismiss(toast.id)}
          className="pointer-events-auto flex w-full items-center justify-between gap-3 rounded-xl border border-white/[0.12] bg-[#141419]/90 px-4 py-3 text-left text-xs font-medium text-foreground shadow-2xl backdrop-blur-xl transition hover:border-accent/40"
        >
          <div className="flex items-center gap-2.5">
            <span className="h-1.5 w-1.5 rounded-full bg-accent" />
            <span>{toast.message}</span>
          </div>
          <span className="text-[10px] text-muted hover:text-foreground">✕</span>
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

  /* Callers usually pass an inline arrow (new identity every render). Keeping it
   * in a ref lets the open/close effect depend on `open` alone — otherwise every
   * keystroke inside the dialog re-ran it and stole focus back to the first
   * field. */
  const onCloseRef = useRef(onClose);
  useEffect(() => {
    onCloseRef.current = onClose;
  }, [onClose]);

  useEffect(() => {
    if (!open) {
      return;
    }
    function onKey(event: KeyboardEvent) {
      if (event.key === "Escape") {
        onCloseRef.current();
      }
    }
    window.addEventListener("keydown", onKey);
    const timer = window.setTimeout(() => {
      const root = firstField.current;
      if (!root) {
        return;
      }
      /* Prefer a real form control; the header's close ✕ button comes first in
       * DOM order, so only fall back to it when the dialog has no fields. */
      const field =
        root.querySelector<HTMLElement>("input, textarea, select") ??
        root.querySelector<HTMLElement>("button");
      field?.focus();
    }, 40);
    document.body.style.overflow = "hidden";
    return () => {
      window.removeEventListener("keydown", onKey);
      window.clearTimeout(timer);
      document.body.style.overflow = "";
    };
  }, [open]);

  if (!open) {
    return null;
  }
  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby={titleId}
      className="fixed inset-0 z-[60] flex items-end justify-center bg-black/80 p-4 sm:items-center backdrop-blur-md transition-opacity"
      onClick={onClose}
    >
      <div
        ref={firstField}
        onClick={(e) => e.stopPropagation()}
        className="w-full max-w-lg rounded-2xl border border-white/[0.12] bg-[#121217] p-6 sm:p-7 shadow-2xl transition-all"
      >
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 id={titleId} className="font-serif text-2xl tracking-wide text-foreground">
              {title}
            </h2>
            {description && (
              <p className="mt-1.5 text-xs leading-relaxed text-muted">{description}</p>
            )}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close dialog"
            className="flex h-7 w-7 items-center justify-center rounded-full text-muted transition hover:bg-white/10 hover:text-foreground"
          >
            ✕
          </button>
        </div>
        <div className="mt-5">{children}</div>
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
      <p className="text-xs leading-relaxed text-muted">{body}</p>
      <div className="mt-6 flex justify-end gap-3">
        <button
          type="button"
          onClick={onCancel}
          disabled={busy}
          className="rounded-full border border-white/10 bg-surface-2 px-4 py-2 text-xs font-medium text-foreground transition hover:bg-surface-3 disabled:opacity-50"
        >
          Cancel
        </button>
        <button
          type="button"
          onClick={onConfirm}
          disabled={busy}
          className="rounded-full bg-red-500/80 px-4 py-2 text-xs font-medium text-white shadow-lg transition hover:bg-red-500 disabled:opacity-50"
        >
          {busy ? "Working…" : confirmLabel}
        </button>
      </div>
    </Dialog>
  );
}

export function CopyButton({
  value,
  label,
  className = "",
}: {
  value: string;
  label?: string;
  className?: string;
}) {
  const [copied, setCopied] = useState(false);
  const onCopy = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2500);
    } catch {
      window.prompt("Copy this link:", value);
    }
  }, [value]);

  return (
    <button
      type="button"
      onClick={() => void onCopy()}
      className={`shrink-0 rounded-full border border-white/10 bg-surface-2 px-3.5 py-1.5 text-xs font-medium text-foreground transition hover:border-accent/40 hover:text-accent ${className}`}
    >
      {copied ? (
        <span className="flex items-center gap-1.5 text-accent">
          <span>✓</span>
          <span>Copied</span>
        </span>
      ) : (
        label ?? "Copy"
      )}
    </button>
  );
}

/** Cover-led card art: warm gradient monogram or photo thumbnail */
export function ProjectCover({
  name,
  thumbUrl,
}: {
  name: string;
  thumbUrl?: string | null;
}) {
  const initial = (name.trim().charAt(0) || "D").toUpperCase();
  return (
    <div className="relative aspect-[16/9] w-full overflow-hidden bg-gradient-to-br from-[#241a0d] via-surface-2 to-surface">
      <div
        aria-hidden="true"
        className="absolute inset-0 opacity-40"
        style={{
          backgroundImage:
            "radial-gradient(circle at 20% 20%, rgba(212,167,90,.35), transparent 55%), radial-gradient(circle at 85% 90%, rgba(212,167,90,.15), transparent 50%)",
        }}
      />
      {thumbUrl ? (
        <img
          src={thumbUrl}
          alt=""
          loading="lazy"
          decoding="async"
          className="absolute inset-0 h-full w-full object-cover transition duration-700 hover:scale-105"
        />
      ) : (
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="flex h-14 w-14 items-center justify-center rounded-full border border-accent/40 bg-black/50 font-serif text-2xl italic text-accent shadow-xl">
            {initial}
          </span>
        </div>
      )}
      <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none" />
    </div>
  );
}

export function SkeletonCards({ count = 4 }: { count?: number }) {
  return (
    <ul aria-label="Loading projects" className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      {Array.from({ length: count }).map((_, i) => (
        <li
          key={i}
          className="animate-pulse overflow-hidden rounded-2xl border border-white/[0.06] bg-surface-2"
        >
          <div className="aspect-[16/9] bg-surface-3/50" />
          <div className="space-y-3 p-5">
            <div className="h-4 w-2/3 rounded-full bg-surface-3/60" />
            <div className="h-3 w-1/3 rounded-full bg-surface-3/40" />
          </div>
        </li>
      ))}
    </ul>
  );
}
