"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import SiteHeader from "@/components/SiteHeader";
import {
  Dialog,
  ProjectCover,
  SkeletonCards,
  ToastStack,
  useToasts,
} from "@/components/console-ui";
import {
  ApiError,
  createProject,
  fetchMe,
  fetchProjects,
  isAbort,
  logout,
  type Me,
  type Project,
} from "@/lib/api";

function ProjectThumb({ project }: { project: Project }) {
  const [thumb, setThumb] = useState<string | null>(null);
  useEffect(() => {
    const controller = new AbortController();
    // Cover = first uploaded photo; failure simply keeps the monogram.
    fetch(
      `${process.env.NEXT_PUBLIC_API_URL ?? ""}/projects/${project.id}/media?limit=1`,
      { credentials: "include", signal: controller.signal }
    )
      .then((r) => (r.ok ? r.json() : null))
      .then((page) => {
        const first = page?.items?.[0];
        if (first?.thumbUrl) {
          setThumb(first.thumbUrl as string);
        }
      })
      .catch(() => {});
    return () => controller.abort();
  }, [project.id]);
  return <ProjectCover name={project.name} thumbUrl={thumb} />;
}

export default function ProjectsPage() {
  const router = useRouter();
  const { toasts, push, dismiss } = useToasts();
  const [me, setMe] = useState<Me | null>(null);
  const [projects, setProjects] = useState<Project[] | null>(null);
  const [filter, setFilter] = useState<"active" | "archived" | "all">("active");
  const [dialogOpen, setDialogOpen] = useState(false);
  const [name, setName] = useState("");
  const [clientName, setClientName] = useState("");
  const [creating, setCreating] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const isAdmin = me?.role === "admin";

  useEffect(() => {
    const controller = new AbortController();
    (async () => {
      try {
        setMe(await fetchMe(controller.signal));
      } catch (e: unknown) {
        if (!isAbort(e)) {
          router.replace("/login");
        }
        return;
      }
      try {
        setProjects(await fetchProjects(controller.signal));
      } catch (e: unknown) {
        if (!isAbort(e)) {
          push(e instanceof Error ? e.message : "Could not load projects.");
        }
      }
    })();
    return () => controller.abort();
  }, [router, push]);

  const onSignOut = useCallback(async () => {
    try {
      await logout();
    } finally {
      router.replace("/login");
    }
  }, [router]);

  const onCreate = useCallback(async () => {
    if (!name.trim()) {
      setFormError("Give the shoot a name.");
      return;
    }
    setCreating(true);
    setFormError(null);
    try {
      const created = await createProject(name.trim(), clientName.trim());
      setProjects((prev) => (prev ? [created, ...prev] : [created]));
      setDialogOpen(false);
      setName("");
      setClientName("");
      router.push(`/projects/${created.id}`);
    } catch (e: unknown) {
      if (e instanceof ApiError && e.status === 403) {
        setFormError("Only the photographer account can create projects.");
      } else {
        setFormError(e instanceof Error ? e.message : "Could not create the project.");
      }
    } finally {
      setCreating(false);
    }
  }, [name, clientName, router]);

  const visible = (projects ?? []).filter((p) => {
    if (!isAdmin) {
      return true; // Clients only ever receive active memberships.
    }
    if (filter === "all") {
      return true;
    }
    return p.status === filter;
  });

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader signedIn onSignOut={onSignOut} />

      <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-12">
        <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold">
              {isAdmin ? "Shoots" : "My projects"}
            </h1>
            <p className="mt-1 text-sm text-muted">
              {isAdmin
                ? "Create a shoot, invite the client, upload the gallery."
                : "Shoots your photographer shared with you."}
            </p>
          </div>
          <div className="flex items-center gap-2">
            {isAdmin && projects !== null && (
              <div
                role="group"
                aria-label="Filter projects"
                className="flex rounded-lg border border-border bg-surface-2 p-1 text-sm"
              >
                {(["active", "archived", "all"] as const).map((value) => (
                  <button
                    key={value}
                    type="button"
                    onClick={() => setFilter(value)}
                    aria-pressed={filter === value}
                    className={`rounded-md px-3 py-1.5 capitalize transition ${
                      filter === value
                        ? "bg-accent text-white"
                        : "text-muted hover:text-foreground"
                    }`}
                  >
                    {value}
                  </button>
                ))}
              </div>
            )}
            {isAdmin && (
              <button
                type="button"
                onClick={() => setDialogOpen(true)}
                className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110"
              >
                New project
              </button>
            )}
          </div>
        </div>

        {projects === null && <SkeletonCards />}

        {projects !== null && visible.length === 0 && (
          <div className="rounded-xl border border-border bg-surface-2 p-10 text-center">
            {isAdmin && filter === "active" ? (
              <>
                <p className="text-lg font-medium">Create your first shoot</p>
                <p className="mx-auto mt-2 max-w-sm text-sm text-muted">
                  Name the wedding, invite the client by email, then drag the
                  finished photos in — the share link goes out last.
                </p>
                <button
                  type="button"
                  onClick={() => setDialogOpen(true)}
                  className="mt-5 rounded-lg bg-accent px-5 py-2.5 text-sm font-medium text-white transition hover:brightness-110"
                >
                  New project
                </button>
              </>
            ) : isAdmin ? (
              <p className="text-sm text-muted">
                No {filter} shoots. Switch the filter or create a new one.
              </p>
            ) : (
              <p className="text-sm text-muted">
                No projects yet. When your photographer shares a shoot, it
                appears here — or open the share link they sent you directly.
              </p>
            )}
          </div>
        )}

        {visible.length > 0 && (
          <ul className="grid gap-4 sm:grid-cols-2">
            {visible.map((project) => (
              <li key={project.id}>
                <Link
                  href={`/projects/${project.id}`}
                  className="block overflow-hidden rounded-xl border border-border bg-surface-2 transition hover:border-accent"
                >
                  <ProjectThumb project={project} />
                  <div className="flex items-start justify-between gap-3 p-5">
                    <div className="min-w-0">
                      <h2 className="truncate font-medium text-foreground">
                        {project.name}
                      </h2>
                      {project.clientName && (
                        <p className="mt-0.5 truncate text-sm text-muted">
                          {project.clientName}
                        </p>
                      )}
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                      {project.status === "archived" && (
                        <span className="rounded-full border border-border bg-surface px-2.5 py-0.5 text-xs text-muted">
                          Archived
                        </span>
                      )}
                      <span className="text-sm text-accent">Open →</span>
                    </div>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </main>

      <Dialog
        open={dialogOpen}
        title="New project"
        description="Name the shoot — you will invite the client from inside it."
        onClose={() => setDialogOpen(false)}
      >
        <div className="space-y-4">
          <div>
            <label
              htmlFor="project-name"
              className="mb-1.5 block text-sm font-medium"
            >
              Shoot name
            </label>
            <input
              id="project-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Ama & Kofi — Wedding"
              className="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent"
            />
          </div>
          <div>
            <label
              htmlFor="project-client"
              className="mb-1.5 block text-sm font-medium"
            >
              Client name <span className="font-normal text-muted">(optional)</span>
            </label>
            <input
              id="project-client"
              value={clientName}
              onChange={(e) => setClientName(e.target.value)}
              placeholder="Ama"
              className="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent"
            />
          </div>
          {formError && (
            <p role="alert" className="text-sm text-red-400">
              {formError}
            </p>
          )}
          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={() => setDialogOpen(false)}
              disabled={creating}
              className="rounded-lg border border-border bg-surface-2 px-4 py-2 text-sm transition hover:bg-surface disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={() => void onCreate()}
              disabled={creating}
              className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition hover:brightness-110 disabled:opacity-50"
            >
              {creating ? "Creating…" : "Create project"}
            </button>
          </div>
        </div>
      </Dialog>

      <ToastStack toasts={toasts} onDismiss={dismiss} />
    </div>
  );
}
