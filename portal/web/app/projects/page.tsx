"use client";

import { useCallback, useEffect, useRef, useState } from "react";
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
  type ProjectStatusFilter,
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
  const [filter, setFilter] = useState<ProjectStatusFilter>("active");
  const [nextCursor, setNextCursor] = useState("");
  const [loadingMore, setLoadingMore] = useState(false);
  // Aborting kills in-flight pages on a filter switch: the incoming rows
  // would belong to the previous filter's list.
  const loadMoreRef = useRef<AbortController | null>(null);
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
        // Page 1 of the current filter; tab switches re-run this through
        // `filter` and replace whatever pages are loaded.
        const page = await fetchProjects(filter, { signal: controller.signal });
        setProjects(page.items);
        setNextCursor(page.nextCursor);
      } catch (e: unknown) {
        if (!isAbort(e)) {
          push(e instanceof Error ? e.message : "Could not load projects.");
        }
      }
    })();
    return () => {
      controller.abort();
      loadMoreRef.current?.abort();
      loadMoreRef.current = null;
    };
  }, [router, push, filter]);

  const onFilterChange = useCallback(
    (value: ProjectStatusFilter) => {
      if (value === filter) {
        return;
      }
      setFilter(value);
      setProjects(null);
      setNextCursor("");
      setLoadingMore(false);
      loadMoreRef.current?.abort();
      loadMoreRef.current = null;
    },
    [filter]
  );

  const onLoadMore = useCallback(async () => {
    if (loadingMore || nextCursor === "") {
      return;
    }
    setLoadingMore(true);
    const controller = new AbortController();
    loadMoreRef.current = controller;
    try {
      const page = await fetchProjects(filter, {
        cursor: nextCursor,
        signal: controller.signal,
      });
      setProjects((prev) => (prev ? [...prev, ...page.items] : page.items));
      setNextCursor(page.nextCursor);
    } catch (e: unknown) {
      if (!isAbort(e)) {
        push(e instanceof Error ? e.message : "Could not load more projects.");
      }
    } finally {
      setLoadingMore(false);
      loadMoreRef.current = null;
    }
  }, [filter, nextCursor, loadingMore, push]);

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

  // The API applies the filter server-side and pages newest-first, so the
  // loaded array renders as-is (clients receive memberships only).
  return (
    <div className="flex min-h-screen flex-col bg-background text-foreground">
      <SiteHeader signedIn onSignOut={onSignOut} />

      <main className="mx-auto w-full max-w-7xl flex-1 px-4 sm:px-8 py-10 sm:py-12">
        <div className="mb-8 flex flex-wrap items-end justify-between gap-4 border-b border-white/[0.08] pb-6">
          <div>
            <h1
              className={`font-serif text-3xl sm:text-4xl tracking-tight text-foreground ${
                isAdmin ? "italic" : ""
              }`}
            >
              {isAdmin ? "Your Shoots" : "My Projects"}
            </h1>
            <p className="mt-1.5 text-sm text-muted">
              {isAdmin
                ? "Create a shoot, invite the client, upload the gallery."
                : "Shoots your photographer shared with you."}
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            {isAdmin && projects !== null && (
              <div
                role="group"
                aria-label="Filter projects"
                className="flex items-center rounded-full border border-white/[0.08] bg-surface-2 p-1 text-xs font-medium"
              >
                {(["active", "archived", "all"] as const).map((value) => (
                  <button
                    key={value}
                    type="button"
                    onClick={() => onFilterChange(value)}
                    aria-pressed={filter === value}
                    className={`rounded-full px-3.5 py-1.5 capitalize transition duration-200 ${
                      filter === value
                        ? "bg-accent text-background font-semibold shadow-sm"
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
                className="inline-flex items-center gap-1.5 rounded-full bg-accent px-4 py-2 text-xs font-medium text-background transition duration-200 hover:bg-accent-hover shadow-lg shadow-accent/10"
              >
                <svg
                  width="14"
                  height="14"
                  viewBox="0 0 16 16"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  aria-hidden="true"
                >
                  <path d="M8 3.5v9M3.5 8h9" />
                </svg>
                <span>New Shoot</span>
              </button>
            )}
          </div>
        </div>

        {projects === null && <SkeletonCards count={6} />}

        {projects !== null && projects.length === 0 && (
          <div className="rounded-2xl border border-white/[0.08] bg-surface-2/60 p-10 sm:p-16 text-center backdrop-blur-sm">
            {isAdmin && filter === "active" ? (
              <>
                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-accent/30 bg-accent/10">
                  <span className="font-serif text-2xl italic text-accent">D</span>
                </div>
                <h2 className="font-serif text-2xl sm:text-3xl italic text-foreground">
                  Create your first shoot
                </h2>
                <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">
                  Name the wedding, invite the client by email, then drag the
                  finished photos in — the share link goes out last.
                </p>
                <button
                  type="button"
                  onClick={() => setDialogOpen(true)}
                  className="mt-6 inline-flex items-center gap-2 rounded-full bg-accent px-6 py-2.5 text-xs font-medium text-background transition duration-200 hover:bg-accent-hover shadow-lg shadow-accent/15"
                >
                  <svg
                    width="14"
                    height="14"
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M8 3.5v9M3.5 8h9" />
                  </svg>
                  <span>New Shoot</span>
                </button>
              </>
            ) : isAdmin ? (
              <>
                <h2 className="font-serif text-2xl italic text-foreground">
                  No {filter} shoots
                </h2>
                <p className="mt-2 text-sm text-muted">
                  Switch the filter or create a new shoot to get started.
                </p>
                <button
                  type="button"
                  onClick={() => setDialogOpen(true)}
                  className="mt-6 inline-flex items-center gap-2 rounded-full bg-accent px-5 py-2.5 text-xs font-medium text-background transition duration-200 hover:bg-accent-hover shadow-lg shadow-accent/15"
                >
                  <svg
                    width="14"
                    height="14"
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M8 3.5v9M3.5 8h9" />
                  </svg>
                  <span>New Shoot</span>
                </button>
              </>
            ) : (
              <>
                <h2 className="font-serif text-2xl text-foreground">
                  No projects yet
                </h2>
                <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">
                  When your photographer shares a shoot, it appears here — or open
                  the share link they sent you directly.
                </p>
              </>
            )}
          </div>
        )}

        {projects !== null && projects.length > 0 && (
          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {projects.map((project) => (
              <li key={project.id}>
                <Link
                  href={`/projects/${project.id}`}
                  className="group block overflow-hidden rounded-2xl border border-white/[0.06] bg-surface transition-all duration-300 hover:border-accent/40 hover:shadow-xl hover:shadow-black/50 group-hover:[&_img]:scale-105"
                >
                  <div className="overflow-hidden">
                    <ProjectThumb project={project} />
                  </div>
                  <div className="flex items-start justify-between gap-3 p-5">
                    <div className="min-w-0">
                      <h2 className="truncate font-serif text-lg font-medium text-foreground transition-colors duration-200 group-hover:text-accent">
                        {project.name}
                      </h2>
                      {project.clientName && (
                        <p className="mt-0.5 truncate text-xs text-muted">
                          {project.clientName}
                        </p>
                      )}
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                      {project.status === "archived" && (
                        <span className="rounded-full border border-white/[0.08] bg-surface-2 px-2.5 py-0.5 text-[11px] text-muted">
                          Archived
                        </span>
                      )}
                      <span className="inline-flex items-center text-xs font-medium text-accent transition-transform duration-200 group-hover:translate-x-0.5">
                        Open →
                      </span>
                    </div>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}

        {projects !== null && nextCursor !== "" && (
          <div className="mt-10 flex justify-center">
            <button
              type="button"
              onClick={() => void onLoadMore()}
              disabled={loadingMore}
              className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-surface px-6 py-2.5 text-xs font-medium text-foreground transition duration-200 hover:border-accent/40 hover:text-accent disabled:cursor-not-allowed disabled:opacity-50"
            >
              {loadingMore && (
                <span
                  className="h-3 w-3 animate-spin rounded-full border border-accent border-t-transparent"
                  aria-hidden="true"
                />
              )}
              <span>Load more</span>
            </button>
          </div>
        )}
      </main>

      <Dialog
        open={dialogOpen}
        title="New Shoot"
        description="Name the shoot — you will invite the client from inside it."
        onClose={() => setDialogOpen(false)}
      >
        <div className="space-y-4">
          <div>
            <label
              htmlFor="project-name"
              className="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted"
            >
              Shoot name
            </label>
            <input
              id="project-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Ama & Kofi — Wedding"
              className="w-full rounded-xl border border-white/[0.08] bg-surface px-4 py-2.5 text-sm text-foreground placeholder:text-muted-dim focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent/40"
            />
          </div>
          <div>
            <label
              htmlFor="project-client"
              className="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted"
            >
              Client name <span className="font-normal text-muted-dim">(optional)</span>
            </label>
            <input
              id="project-client"
              value={clientName}
              onChange={(e) => setClientName(e.target.value)}
              placeholder="Ama"
              className="w-full rounded-xl border border-white/[0.08] bg-surface px-4 py-2.5 text-sm text-foreground placeholder:text-muted-dim focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent/40"
            />
          </div>
          {formError && (
            <p role="alert" className="text-xs text-red-400">
              {formError}
            </p>
          )}
          <div className="flex justify-end gap-2.5 pt-2">
            <button
              type="button"
              onClick={() => setDialogOpen(false)}
              disabled={creating}
              className="rounded-full border border-white/10 bg-surface-2 px-4 py-2 text-xs font-medium text-foreground transition hover:border-accent/40 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={() => void onCreate()}
              disabled={creating}
              className="rounded-full bg-accent px-5 py-2 text-xs font-medium text-background transition hover:bg-accent-hover disabled:opacity-50"
            >
              {creating ? "Creating…" : "Create Shoot"}
            </button>
          </div>
        </div>
      </Dialog>

      <ToastStack toasts={toasts} onDismiss={dismiss} />
    </div>
  );
}
