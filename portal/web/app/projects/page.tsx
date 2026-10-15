"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import SiteHeader from "@/components/SiteHeader";
import { ApiError, fetchMe, fetchProjects, isAbort, logout, type Project } from "@/lib/api";

export default function ProjectsPage() {
  const router = useRouter();
  const [projects, setProjects] = useState<Project[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    (async () => {
      try {
        // Anonymous browsers read as 403 on data routes, same as a
        // forbidden account — so check the session first and bounce to
        // login before any project fetch can misreport.
        await fetchMe(controller.signal);
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
          setError(e instanceof Error ? e.message : "Could not load projects.");
        }
      }
    })();
    return () => controller.abort();
  }, [router]);

  const onSignOut = useCallback(async () => {
    try {
      await logout();
    } finally {
      router.replace("/login");
    }
  }, [router]);

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader signedIn onSignOut={onSignOut} />

      <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-12">
        <h1 className="mb-8 text-2xl font-semibold">My projects</h1>

        {error && (
          <p role="alert" className="rounded-lg border border-border bg-surface-2 px-4 py-3 text-sm text-red-400">
            {error}
          </p>
        )}

        {projects === null && !error && (
          <p className="text-muted">Loading your shoots…</p>
        )}

        {projects !== null && projects.length === 0 && (
          <div className="rounded-lg border border-border bg-surface-2 p-6 text-center">
            <p className="text-muted">
              No projects yet. When your photographer shares a shoot, it appears here —
              or open the share link they sent you directly.
            </p>
          </div>
        )}

        {projects !== null && projects.length > 0 && (
          <ul className="grid gap-4 sm:grid-cols-2">
            {projects.map((project) => (
              <li key={project.id}>
                <Link
                  href={`/projects/${project.id}`}
                  className="block rounded-lg border border-border bg-surface-2 p-6 transition hover:border-accent"
                >
                  <h2 className="font-medium text-foreground">{project.name}</h2>
                  {project.clientName && (
                    <p className="mt-1 text-sm text-muted">{project.clientName}</p>
                  )}
                  <p className="mt-3 text-sm text-accent">Open gallery →</p>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </main>
    </div>
  );
}
