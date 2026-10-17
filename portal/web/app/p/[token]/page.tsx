"use client";

import { use, useEffect, useState } from "react";
import MediaGrid from "@/components/MediaGrid";
import SiteHeader from "@/components/SiteHeader";
import {
  ApiError,
  fetchTokenProject,
  isAbort,
  tokenAccess,
  type TokenProject,
} from "@/lib/api";

export default function TokenProjectPage({
  params,
}: {
  params: Promise<{ token: string }>;
}) {
  const { token } = use(params);
  const [project, setProject] = useState<TokenProject | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    fetchTokenProject(token, controller.signal)
      .then(setProject)
      .catch((e: unknown) => {
        if (isAbort(e)) {
          return;
        }
        if (e instanceof ApiError && e.status === 404) {
          setError(
            "This link is invalid or has been revoked. Ask your photographer for a fresh one."
          );
          return;
        }
        setError(e instanceof Error ? e.message : "Could not load this gallery.");
      });
    return () => controller.abort();
  }, [token]);

  return (
    <div className="flex min-h-screen flex-col bg-background text-foreground selection:bg-accent/20 selection:text-foreground">
      <SiteHeader signedIn={false} />

      {error && (
        <main className="mx-auto flex w-full max-w-xl flex-1 items-center justify-center px-6 py-20">
          <div className="w-full rounded-2xl border border-white/[0.08] bg-surface-2/80 p-8 text-center shadow-2xl backdrop-blur-xl sm:p-10">
            <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full border border-red-500/20 bg-red-500/10 text-red-400">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
              >
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
            </div>
            <h1 className="font-serif text-2xl font-light italic text-foreground sm:text-3xl">
              Gallery Unavailable
            </h1>
            <p role="alert" className="mt-3 text-sm leading-relaxed text-muted">
              {error}
            </p>
            <div className="gold-divider mx-auto my-6 max-w-[120px]" />
            <p className="text-[11px] uppercase tracking-widest2 text-muted-dim font-medium">
              Demie Photography Client Portal
            </p>
          </div>
        </main>
      )}

      {!error && project === null && (
        <div className="flex flex-1 items-center justify-center py-32">
          <div className="flex flex-col items-center gap-4">
            <div className="relative flex h-10 w-10 items-center justify-center">
              <span className="absolute h-full w-full animate-ping rounded-full bg-accent/20" />
              <span className="h-3 w-3 rounded-full bg-accent" />
            </div>
            <p className="font-serif text-lg italic text-muted">
              Preparing your gallery…
            </p>
          </div>
        </div>
      )}

      {project && (
        <>
          <section className="relative overflow-hidden border-b border-white/[0.06] bg-gradient-to-b from-surface-2/50 via-background to-background py-20 sm:py-28 text-center">
            <div
              aria-hidden="true"
              className="pointer-events-none absolute inset-x-0 -top-32 mx-auto h-80 max-w-3xl rounded-full bg-accent/5 blur-3xl"
            />

            <div className="relative mx-auto max-w-4xl px-6">
              <div className="inline-flex items-center gap-2 rounded-full border border-accent/20 bg-accent/[0.07] px-4 py-1.5 text-[10px] sm:text-[11px] font-medium uppercase tracking-luxury text-accent shadow-sm">
                <span className="h-1.5 w-1.5 rounded-full bg-accent animate-pulse" />
                Shared Gallery
              </div>

              <h1 className="mt-6 font-serif text-4xl sm:text-6xl md:text-7xl font-light italic tracking-tight text-foreground">
                {project.name}
              </h1>

              <div className="mt-5 flex items-center justify-center gap-3 text-xs tracking-widest2 uppercase text-muted font-medium">
                <span className="h-px w-8 bg-white/10" />
                <span>Captured by Demie Photography</span>
                <span className="h-px w-8 bg-white/10" />
              </div>

              <div className="mt-12 flex flex-col items-center gap-2.5 text-muted-dim transition hover:text-accent">
                <a
                  href="#gallery-content"
                  className="group flex flex-col items-center gap-2 text-muted transition hover:text-foreground"
                >
                  <span className="text-[10px] tracking-widest2 uppercase font-medium">
                    Scroll to Explore
                  </span>
                  <div className="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-surface-2/50 transition group-hover:border-accent/40 group-hover:bg-accent/10">
                    <svg
                      className="h-4 w-4 animate-bounce text-accent"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      strokeWidth="1.5"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M19.5 8.25l-7.5 7.5-7.5-7.5"
                      />
                    </svg>
                  </div>
                </a>
              </div>
            </div>
          </section>

          <main
            id="gallery-content"
            className="mx-auto w-full max-w-7xl flex-1 px-4 sm:px-8 py-10 sm:py-14"
          >
            <MediaGrid access={tokenAccess(token)} />
          </main>
        </>
      )}
    </div>
  );
}
