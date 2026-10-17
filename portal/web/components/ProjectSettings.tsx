"use client";

import { useCallback, useState } from "react";
import { ConfirmDialog, useToasts, ToastStack } from "@/components/console-ui";
import { updateProject, type Project } from "@/lib/api";

/**
 * Photographer project settings (Task 18): rename + archive/unarchive with
 * confirm. Rendered admin-only next to SharePanel on the detail page.
 */
export default function ProjectSettings({
  project,
  onChange,
}: {
  project: Project;
  onChange: (next: Project) => void;
}) {
  const { toasts, push, dismiss } = useToasts();
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(project.name);
  const [saving, setSaving] = useState(false);
  const [confirmArchive, setConfirmArchive] = useState(false);
  const archived = project.status === "archived";

  const onRename = useCallback(async () => {
    if (!name.trim()) {
      push("Give the shoot a name.");
      return;
    }
    setSaving(true);
    try {
      onChange(await updateProject(project.id, { name: name.trim() }));
      setEditing(false);
    } catch (e) {
      push(e instanceof Error ? e.message : "Could not rename the project.");
    } finally {
      setSaving(false);
    }
  }, [name, project.id, onChange, push]);

  const onArchiveToggle = useCallback(async () => {
    setSaving(true);
    try {
      onChange(
        await updateProject(project.id, {
          status: archived ? "active" : "archived",
        })
      );
      setConfirmArchive(false);
    } catch (e) {
      push(e instanceof Error ? e.message : "Could not update the project.");
    } finally {
      setSaving(false);
    }
  }, [archived, project.id, onChange, push]);

  return (
    <section
      aria-label="Project settings"
      className="mb-6 rounded-2xl border border-white/[0.08] bg-surface p-6 shadow-xl"
    >
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <div>
          <h2 className="font-serif text-xl tracking-wide text-foreground">Shoot Settings</h2>
          <p className="mt-0.5 text-xs text-muted">Project configuration and visibility</p>
        </div>
        {archived && (
          <span className="rounded-full border border-white/10 bg-surface-2 px-3 py-1 text-xs font-medium text-muted">
            Archived — hidden from client
          </span>
        )}
      </div>

      {editing ? (
        <div className="mt-4 flex flex-wrap items-center gap-2.5">
          <input
            autoFocus
            value={name}
            onChange={(e) => setName(e.target.value)}
            aria-label="Project name"
            className="min-w-0 flex-1 rounded-xl border border-white/[0.08] bg-surface-2 px-4 py-2 text-sm text-foreground placeholder:text-muted-dim focus:border-accent/40 focus:outline-none focus:ring-2 focus:ring-accent/50"
          />
          <button
            type="button"
            onClick={() => void onRename()}
            disabled={saving}
            className="rounded-full bg-accent px-5 py-2 text-xs font-medium text-background transition hover:bg-accent-hover disabled:opacity-50"
          >
            {saving ? "Saving…" : "Save"}
          </button>
          <button
            type="button"
            onClick={() => {
              setName(project.name);
              setEditing(false);
            }}
            className="text-xs text-muted transition hover:text-foreground"
          >
            Cancel
          </button>
        </div>
      ) : (
        <div className="mt-4 flex flex-wrap items-center gap-2.5">
          <button
            type="button"
            onClick={() => {
              setName(project.name);
              setEditing(true);
            }}
            className="rounded-full border border-white/10 bg-surface-2 px-4 py-2 text-xs font-medium text-foreground transition hover:border-accent/40 hover:text-accent"
          >
            Rename
          </button>
          <button
            type="button"
            onClick={() => setConfirmArchive(true)}
            className="rounded-full border border-white/10 bg-surface-2 px-4 py-2 text-xs font-medium text-foreground transition hover:border-accent/40 hover:text-accent"
          >
            {archived ? "Unarchive" : "Archive"}
          </button>
        </div>
      )}

      <ConfirmDialog
        open={confirmArchive}
        title={archived ? "Unarchive this shoot?" : "Archive this shoot?"}
        body={
          archived
            ? "The client will see it in their shoot list again."
            : "The client stops seeing it immediately. Photos, links, and analytics are preserved — nothing is deleted."
        }
        confirmLabel={archived ? "Unarchive" : "Archive"}
        busy={saving}
        onCancel={() => setConfirmArchive(false)}
        onConfirm={() => void onArchiveToggle()}
      />
      <ToastStack toasts={toasts} onDismiss={dismiss} />
    </section>
  );
}
