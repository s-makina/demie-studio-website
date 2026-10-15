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
      className="mb-6 rounded-xl border border-border bg-surface-2 p-5"
    >
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-sm font-semibold">Project settings</h2>
        {archived && (
          <span className="rounded-full border border-border bg-surface px-2.5 py-0.5 text-xs text-muted">
            Archived — hidden from the client
          </span>
        )}
      </div>

      {editing ? (
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <input
            autoFocus
            value={name}
            onChange={(e) => setName(e.target.value)}
            aria-label="Project name"
            className="min-w-0 flex-1 rounded-lg border border-border bg-surface px-3 py-2 focus:outline-none focus:ring-2 focus:ring-accent"
          />
          <button
            type="button"
            onClick={() => void onRename()}
            disabled={saving}
            className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 disabled:opacity-50"
          >
            {saving ? "Saving…" : "Save"}
          </button>
          <button
            type="button"
            onClick={() => {
              setName(project.name);
              setEditing(false);
            }}
            className="text-sm text-muted hover:text-foreground"
          >
            Cancel
          </button>
        </div>
      ) : (
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => {
              setName(project.name);
              setEditing(true);
            }}
            className="rounded-lg border border-border bg-surface px-4 py-2 text-sm transition hover:bg-surface-2"
          >
            Rename
          </button>
          <button
            type="button"
            onClick={() => setConfirmArchive(true)}
            className="rounded-lg border border-border bg-surface px-4 py-2 text-sm transition hover:bg-surface-2"
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
            ? "The client will see it in their list again."
            : "The client stops seeing it immediately. Photos, links and counts are kept — nothing is deleted."
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
