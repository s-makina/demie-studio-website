"use client";

import { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import InvitePanel from "@/components/InvitePanel";
import MediaGrid from "@/components/MediaGrid";
import ProjectSettings from "@/components/ProjectSettings";
import SharePanel from "@/components/SharePanel";
import SiteHeader from "@/components/SiteHeader";
import UploadPanel from "@/components/UploadPanel";
import {
  accountAccess,
  ApiError,
  fetchMe,
  fetchProject,
  isAbort,
  logout,
  type Me,
  type Project,
} from "@/lib/api";

export default function ProjectDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const projectId = Number(id);
  const router = useRouter();
  const [me, setMe] = useState<Me | null>(null);
  const [project, setProject] = useState<Project | null>(null);
  const [refreshKey, setRefreshKey] = useState(0);
  const [error, setError] = useState<string | null>(() =>
    !Number.isInteger(projectId) || projectId <= 0 ? "That project link looks broken." : null
  );

  const isAdmin = me?.role === "admin";

  useEffect(() => {
    if (error) {
      return;
    }
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
        setProject(await fetchProject(projectId, controller.signal));
      } catch (e: unknown) {
        if (!isAbort(e)) {
          if (e instanceof ApiError && e.status === 401) {
            router.replace("/login");
            return;
          }
          setError(e instanceof Error ? e.message : "Could not load this project.");
        }
      }
    })();
    return () => controller.abort();
  }, [projectId, error, router]);

  const onSignOut = useCallback(async () => {
    try {
      await logout();
    } finally {
      router.replace("/login");
    }
  }, [router]);

  const onUploaded = useCallback(() => {
    // Reload the grid from the first page so new uploads stream in.
    setRefreshKey((key) => key + 1);
  }, []);

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader signedIn onSignOut={onSignOut} />

      <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-12">
        {error && (
          <div className="rounded-lg border border-border bg-surface-2 p-6 text-center">
            <p role="alert" className="text-sm text-red-400">
              {error}
            </p>
            <Link
              href="/projects"
              className="mt-4 inline-block rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
            >
              Back to my projects
            </Link>
          </div>
        )}

        {!error && project === null && <p className="text-muted">Loading gallery…</p>}

        {project && (
          <>
            <div className="mb-8">
              <Link href="/projects" className="text-sm text-muted hover:text-foreground">
                ← {isAdmin ? "Shoots" : "My projects"}
              </Link>
              <div className="mt-2 flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-semibold">{project.name}</h1>
                {project.status === "archived" && (
                  <span className="rounded-full border border-border bg-surface-2 px-2.5 py-0.5 text-xs text-muted">
                    Archived
                  </span>
                )}
              </div>
              {project.clientName && (
                <p className="mt-1 text-sm text-muted">{project.clientName}</p>
              )}
            </div>
            {isAdmin && (
              <ProjectSettings project={project} onChange={setProject} />
            )}
            {isAdmin && <UploadPanel projectId={project.id} onUploaded={onUploaded} />}
            {isAdmin && <InvitePanel projectId={project.id} />}
            {isAdmin && <SharePanel projectId={project.id} />}
            <MediaGrid
              access={accountAccess(project.id)}
              isAdmin={isAdmin}
              refreshKey={refreshKey}
            />
          </>
        )}
      </main>
    </div>
  );
}
