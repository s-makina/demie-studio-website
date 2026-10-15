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
          setError("This link is invalid or has been revoked. Ask your photographer for a fresh one.");
          return;
        }
        setError(e instanceof Error ? e.message : "Could not load this gallery.");
      });
    return () => controller.abort();
  }, [token]);

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader signedIn={false} />

      <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-12">
        {error && (
          <div className="rounded-lg border border-border bg-surface-2 p-6 text-center">
            <p role="alert" className="text-sm text-red-400">
              {error}
            </p>
          </div>
        )}

        {!error && project === null && <p className="text-muted">Loading gallery…</p>}

        {project && (
          <>
            <div className="mb-8">
              <p className="text-sm text-muted">Shared gallery · no sign-in needed</p>
              <h1 className="mt-2 text-2xl font-semibold">{project.name}</h1>
            </div>
            <MediaGrid access={tokenAccess(token)} />
          </>
        )}
      </main>
    </div>
  );
}
