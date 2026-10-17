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
  fetchProjectStats,
  isAbort,
  logout,
  type Me,
  type Project,
  type ProjectStats,
} from "@/lib/api";

const TABS = [
  { id: "gallery", label: "Gallery" },
  { id: "delivery", label: "Delivery" },
  { id: "clients", label: "Clients" },
  { id: "settings", label: "Settings" },
] as const;

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
  const [activeTab, setActiveTab] = useState<string>("gallery");
  const [error, setError] = useState<string | null>(() =>
    !Number.isInteger(projectId) || projectId <= 0 ? "That project link looks broken." : null
  );
  const [stats, setStats] = useState<ProjectStats | null>(null);

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
        return;
      }
      // Stats are non-critical — display "—" if this fails.
      try {
        setStats(await fetchProjectStats(projectId, controller.signal));
      } catch {
        // Swallow: the sidebar just shows "—" for stats.
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
    <div className="flex min-h-screen flex-col bg-background text-foreground">
      <SiteHeader signedIn onSignOut={onSignOut} />

      <main className="mx-auto w-full max-w-7xl flex-1 px-4 sm:px-8 py-10 sm:py-12">
        {error && (
          <div className="mx-auto max-w-lg rounded-2xl border border-white/[0.08] bg-surface-2/80 p-8 text-center shadow-2xl backdrop-blur-md">
            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-red-500/20 bg-red-500/10 text-red-400">
              <span className="font-serif text-xl italic">!</span>
            </div>
            <h2 className="font-serif text-2xl italic text-foreground">
              Project Not Found
            </h2>
            <p role="alert" className="mt-2 text-sm text-muted">
              {error}
            </p>
            <Link
              href="/projects"
              className="mt-6 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-surface px-5 py-2 text-xs font-medium text-foreground transition hover:border-accent/40 hover:text-accent"
            >
              ← Back to {isAdmin ? "Shoots" : "My Projects"}
            </Link>
          </div>
        )}

        {!error && project === null && (
          <div className="flex flex-col items-center justify-center py-28 text-center">
            <div className="flex h-10 w-10 animate-pulse items-center justify-center rounded-full border border-accent/30 bg-accent/10">
              <span className="font-serif text-sm italic text-accent">D</span>
            </div>
            <p className="mt-4 animate-pulse font-serif text-lg italic text-muted">
              Loading gallery…
            </p>
          </div>
        )}

        {project && (
          <>
            <div className="border-b border-white/[0.08] pb-6">
              <Link
                href="/projects"
                className="inline-flex items-center gap-1.5 text-xs font-medium uppercase tracking-luxury text-muted transition hover:text-foreground"
              >
                <span>←</span>
                <span>{isAdmin ? "Shoots" : "My Projects"}</span>
              </Link>

              <div className="mt-3 flex flex-wrap items-center gap-3">
                <h1 className="font-serif text-3xl sm:text-4xl italic text-foreground">
                  {project.name}
                </h1>
                {project.status === "archived" && (
                  <span className="rounded-full border border-white/[0.08] bg-surface-2 px-3 py-0.5 text-xs text-muted">
                    Archived
                  </span>
                )}
              </div>
              {project.clientName && (
                <p className="mt-1 text-sm text-muted">{project.clientName}</p>
              )}

              {isAdmin && (
                <div className="mt-6 flex items-center">
                  <div
                    role="tablist"
                    aria-label="Shoot sections"
                    className="inline-flex rounded-full border border-white/[0.08] bg-surface-2 p-1 text-xs font-medium uppercase tracking-wider"
                  >
                    {TABS.map((tab) => {
                      const isActive = activeTab === tab.id;
                      return (
                        <button
                          key={tab.id}
                          role="tab"
                          type="button"
                          id={`tab-${tab.id}`}
                          aria-controls={`panel-${tab.id}`}
                          aria-selected={isActive}
                          onClick={() => setActiveTab(tab.id)}
                          className={`rounded-full px-4 py-2 transition duration-200 ${
                            isActive
                              ? "bg-accent text-background font-semibold shadow-sm"
                              : "text-muted hover:text-foreground"
                          }`}
                        >
                          {tab.label}
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>

            {isAdmin && (
              <div className="mt-8">
                <div
                  role="tabpanel"
                  id="panel-gallery"
                  aria-labelledby="tab-gallery"
                  className={activeTab === "gallery" ? "block" : "hidden"}
                >
                  <div className="flex flex-col-reverse xl:flex-row xl:items-start gap-6">
                    {/* Gallery — left, 75% on xl+ */}
                    <div className="min-w-0 flex-1 xl:w-3/4">
                      <MediaGrid
                        access={accountAccess(project.id)}
                        isAdmin={isAdmin}
                        refreshKey={refreshKey}
                      />
                    </div>

                    {/* Upload + stats sidebar — right, 25% on xl+ */}
                    <aside className="w-full xl:w-1/4 xl:sticky xl:top-6 xl:self-start shrink-0 space-y-5">
                      {/* Quick stats */}
                      <div className="grid grid-cols-3 gap-3">
                        <div className="rounded-xl border border-white/[0.08] bg-surface-2/60 p-4 text-center">
                          <p className="text-2xl font-semibold tabular-nums text-accent">
                            {stats ? stats.views.toLocaleString() : "—"}
                          </p>
                          <p className="mt-1 text-[10px] font-medium uppercase tracking-wider text-muted">
                            Views
                          </p>
                        </div>
                        <div className="rounded-xl border border-white/[0.08] bg-surface-2/60 p-4 text-center">
                          <p className="text-2xl font-semibold tabular-nums text-accent">
                            {stats ? stats.downloads.toLocaleString() : "—"}
                          </p>
                          <p className="mt-1 text-[10px] font-medium uppercase tracking-wider text-muted">
                            Downloads
                          </p>
                        </div>
                        <div className="rounded-xl border border-white/[0.08] bg-surface-2/60 p-4 text-center">
                          <p className={`text-lg font-semibold ${project.status === "active" ? "text-emerald-400" : "text-muted"}`}>
                            {project.status === "active" ? "Active" : "Archived"}
                          </p>
                          <p className="mt-1 text-[10px] font-medium uppercase tracking-wider text-muted">
                            Status
                          </p>
                        </div>
                      </div>

                      <UploadPanel projectId={project.id} onUploaded={onUploaded} />
                    </aside>
                  </div>
                </div>

                <div
                  role="tabpanel"
                  id="panel-delivery"
                  aria-labelledby="tab-delivery"
                  className={activeTab === "delivery" ? "block" : "hidden"}
                >
                  <SharePanel projectId={project.id} />
                </div>

                <div
                  role="tabpanel"
                  id="panel-clients"
                  aria-labelledby="tab-clients"
                  className={activeTab === "clients" ? "block" : "hidden"}
                >
                  <InvitePanel projectId={project.id} />
                </div>

                <div
                  role="tabpanel"
                  id="panel-settings"
                  aria-labelledby="tab-settings"
                  className={activeTab === "settings" ? "block" : "hidden"}
                >
                  <ProjectSettings project={project} onChange={setProject} />
                </div>
              </div>
            )}

            {!isAdmin && (
              <div className="mt-8">
                <MediaGrid
                  access={accountAccess(project.id)}
                  isAdmin={isAdmin}
                  refreshKey={refreshKey}
                />
              </div>
            )}
          </>
        )}
      </main>
    </div>
  );
}
