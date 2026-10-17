"use client";

import { useCallback, useState } from "react";
import {
  CopyButton,
  Dialog,
  ToastStack,
  useToasts,
} from "@/components/console-ui";
import {
  acceptUrl,
  assignMember,
  inviteClient,
  isAbort,
} from "@/lib/api";

/**
 * Invite-to-project (Task 19): email + name → invite + auto-assign to the
 * open project. Shows the one-time accept link with copy — the photographer
 * forwards it out-of-band (WhatsApp etc.), same once-only pattern as the
 * share link. Existing active accounts are just linked, no new token.
 */
export default function InvitePanel({ projectId }: { projectId: number }) {
  const { toasts, push, dismiss } = useToasts();
  const [open, setOpen] = useState(false);
  const [email, setEmail] = useState("");
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);
  const [acceptLink, setAcceptLink] = useState<string | null>(null);
  const [linkedExisting, setLinkedExisting] = useState(false);

  const onInvite = useCallback(async () => {
    if (!email.trim()) {
      push("Enter the client email.");
      return;
    }
    setBusy(true);
    try {
      const invite = await inviteClient(email.trim(), name.trim());
      await assignMember(projectId, invite.email);
      setLinkedExisting(!invite.tokenIssued);
      setAcceptLink(
        invite.tokenIssued && invite.inviteToken
          ? acceptUrl(invite.inviteToken)
          : null
      );
    } catch (e) {
      if (!isAbort(e)) {
        push(e instanceof Error ? e.message : "Could not invite the client.");
      }
    } finally {
      setBusy(false);
    }
  }, [email, name, projectId, push]);

  const close = useCallback(() => {
    setOpen(false);
    setAcceptLink(null);
    setLinkedExisting(false);
  }, []);

  return (
    <section
      aria-label="Client invite"
      className="mb-6 rounded-2xl border border-white/[0.08] bg-surface p-6 shadow-xl"
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-serif text-xl tracking-wide text-foreground">Client Access</h2>
          <p className="mt-0.5 text-xs text-muted">
            Invite client accounts with personalized shoot access
          </p>
        </div>
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="rounded-full bg-accent px-5 py-2 text-xs font-medium text-background transition hover:bg-accent-hover"
        >
          Invite client
        </button>
      </div>
      <p className="mt-3 text-xs leading-relaxed text-muted">
        The client signs in and sees this shoot in their list. No automated email is
        dispatched — copy the one-time link and forward it directly.
      </p>

      <Dialog
        open={open}
        title="Invite client to this shoot"
        description="They set a password at the link, then sign in to view and download high-resolution media."
        onClose={close}
      >
        {!acceptLink && !linkedExisting ? (
          <div className="space-y-4">
            <div>
              <label htmlFor="invite-email" className="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">
                Client email
              </label>
              <input
                id="invite-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="ama@example.com"
                autoComplete="email"
                className="w-full rounded-xl border border-white/[0.08] bg-surface px-4 py-2.5 text-sm text-foreground placeholder:text-muted-dim focus:border-accent/40 focus:outline-none focus:ring-2 focus:ring-accent/50"
              />
            </div>
            <div>
              <label htmlFor="invite-name" className="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">
                Client name <span className="font-normal text-muted-dim">(optional)</span>
              </label>
              <input
                id="invite-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Ama"
                autoComplete="off"
                className="w-full rounded-xl border border-white/[0.08] bg-surface px-4 py-2.5 text-sm text-foreground placeholder:text-muted-dim focus:border-accent/40 focus:outline-none focus:ring-2 focus:ring-accent/50"
              />
            </div>
            <div className="flex justify-end gap-2.5 pt-2">
              <button
                type="button"
                onClick={close}
                disabled={busy}
                className="rounded-full border border-white/10 bg-surface-2 px-4 py-2 text-xs font-medium text-foreground transition hover:bg-surface-3 disabled:opacity-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => void onInvite()}
                disabled={busy}
                className="rounded-full bg-accent px-5 py-2 text-xs font-medium text-background transition hover:bg-accent-hover disabled:opacity-50"
              >
                {busy ? "Inviting…" : "Invite + add to shoot"}
              </button>
            </div>
          </div>
        ) : linkedExisting ? (
          <div className="space-y-4">
            <div className="flex items-center gap-2.5 text-xs text-foreground">
              <span className="h-2 w-2 rounded-full bg-emerald-400 ring-4 ring-emerald-400/20" />
              <span>
                <strong className="font-semibold text-accent">{email.trim()}</strong> already has an account — added to this shoot.
              </span>
            </div>
            <div className="flex justify-end pt-2">
              <button
                type="button"
                onClick={close}
                className="rounded-full bg-accent px-5 py-2 text-xs font-medium text-background transition hover:bg-accent-hover"
              >
                Done
              </button>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="flex items-center gap-2.5 text-xs text-foreground">
              <span className="h-2 w-2 rounded-full bg-emerald-400 ring-4 ring-emerald-400/20" />
              <span>Invited — forward this one-time link to the client:</span>
            </div>
            {acceptLink && (
              <div className="flex flex-wrap items-center gap-2">
                <code className="min-w-0 flex-1 break-all rounded-xl border border-white/[0.06] bg-surface-2 px-3.5 py-2.5 font-mono text-xs text-foreground/90 selection:bg-accent/20">
                  {acceptLink}
                </code>
                <CopyButton value={acceptLink} label="Copy invite link" />
              </div>
            )}
            <p className="text-xs text-muted">
              The link functions once — after setting their password it is invalidated.
            </p>
            <div className="flex justify-end pt-2">
              <button
                type="button"
                onClick={close}
                className="rounded-full bg-accent px-5 py-2 text-xs font-medium text-background transition hover:bg-accent-hover"
              >
                Done
              </button>
            </div>
          </div>
        )}
      </Dialog>
      <ToastStack toasts={toasts} onDismiss={dismiss} />
    </section>
  );
}
