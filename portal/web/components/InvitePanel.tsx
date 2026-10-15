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
      className="mb-6 rounded-xl border border-border bg-surface-2 p-5"
    >
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold">Client access</h2>
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110"
        >
          Invite client
        </button>
      </div>
      <p className="mt-2 text-sm text-muted">
        The client signs in and sees this shoot in their list. No email is
        sent — copy the link and forward it yourself.
      </p>

      <Dialog
        open={open}
        title="Invite client to this shoot"
        description="They set a password at the link, then sign in to view and download."
        onClose={close}
      >
        {!acceptLink && !linkedExisting ? (
          <div className="space-y-4">
            <div>
              <label htmlFor="invite-email" className="mb-1.5 block text-sm font-medium">
                Client email
              </label>
              <input
                id="invite-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="ama@example.com"
                autoComplete="email"
                className="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent"
              />
            </div>
            <div>
              <label htmlFor="invite-name" className="mb-1.5 block text-sm font-medium">
                Client name <span className="font-normal text-muted">(optional)</span>
              </label>
              <input
                id="invite-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Ama"
                autoComplete="off"
                className="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent"
              />
            </div>
            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={close}
                disabled={busy}
                className="rounded-lg border border-border bg-surface-2 px-4 py-2 text-sm transition hover:bg-surface disabled:opacity-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => void onInvite()}
                disabled={busy}
                className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 disabled:opacity-50"
              >
                {busy ? "Inviting…" : "Invite + add to shoot"}
              </button>
            </div>
          </div>
        ) : linkedExisting ? (
          <div className="space-y-4">
            <p className="text-sm">
              <span className="mr-2 inline-block h-2 w-2 rounded-full bg-green-400" />
              {email.trim()} already has an account — added to this shoot.
            </p>
            <div className="flex justify-end">
              <button
                type="button"
                onClick={close}
                className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110"
              >
                Done
              </button>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <p className="text-sm">
              <span className="mr-2 inline-block h-2 w-2 rounded-full bg-green-400" />
              Invited — forward this one-time link to the client:
            </p>
            {acceptLink && (
              <div className="flex flex-wrap items-center gap-2">
                <code className="min-w-0 flex-1 break-all rounded border border-border bg-surface-2 px-3 py-2 text-xs">
                  {acceptLink}
                </code>
                <CopyButton value={acceptLink} label="Copy invite link" />
              </div>
            )}
            <p className="text-xs text-muted">
              The link works once — after they set a password it stops working.
            </p>
            <div className="flex justify-end">
              <button
                type="button"
                onClick={close}
                className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110"
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
