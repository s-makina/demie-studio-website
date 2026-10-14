import Link from "next/link";

export default function ProjectsPage() {
  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
          <Link href="/" className="font-semibold tracking-tight text-foreground">
            Demie Photography
          </Link>
          <nav className="flex gap-6 text-sm text-muted">
            <Link
              href="/projects"
              className="text-foreground"
            >
              My projects
            </Link>
            <Link
              href="/login"
              className="hover:text-foreground"
            >
              Sign out
            </Link>
          </nav>
        </div>
      </header>

      <main className="flex flex-1 items-start justify-center px-6 py-12">
        <div className="w-full max-w-3xl">
          <div className="mb-8 flex items-baseline justify-between">
            <h1 className="text-2xl font-semibold">My projects</h1>
            <span className="text-sm text-muted">signed in as photographer@example.com</span>
          </div>

          <div className="rounded-lg border border-border bg-surface-2 p-6 text-center">
            <p className="text-muted">
              No projects yet — or the API base URL is not configured.
            </p>
            <p className="mt-2 text-sm text-muted">
              Set{" "}
              <code className="rounded bg-surface px-1 py-0.5 font-mono text-accent">
                NEXT_PUBLIC_API_URL
              </code>{" "}
              to point at the running portal API, then reload.
            </p>
          </div>

          <section className="mt-8 rounded-lg border border-border bg-surface-2">
            <div className="px-6 py-4 border-b border-border">
              <h2 className="text-sm font-medium uppercase tracking-wide text-muted">
                Placeholder project
              </h2>
            </div>
            <div className="p-6">
              <p className="text-sm text-muted">
                When projects exist, each row shows the client name, photo count, and a
                link into the grid. The public share link lives in project settings
                (Task 14).
              </p>
              <div className="mt-4 flex gap-3">
                <Link
                  href="/projects/placeholder"
                  className="inline-flex items-center rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
                >
                  Open project
                </Link>
                <Link
                  href="/login"
                  className="inline-flex items-center rounded-lg border border-border bg-surface px-4 py-2 text-sm text-foreground transition hover:bg-surface-2"
                >
                  Back to sign in
                </Link>
              </div>
            </div>
          </section>
        </div>
      </main>
    </div>
  );
}
