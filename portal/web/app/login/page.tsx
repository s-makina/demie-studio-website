"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import SiteHeader from "@/components/SiteHeader";
import { API_URL, fetchMe } from "@/lib/api";

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // Already signed in (session cookie alive)? Skip the form.
  useEffect(() => {
    let cancelled = false;
    fetchMe()
      .then(() => {
        if (!cancelled) {
          router.replace("/projects");
        }
      })
      .catch(() => {
        // Not signed in (or remount-aborted) — stay on the form.
      });
    return () => {
      cancelled = true;
    };
  }, [router]);

  async function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      const response = await fetch(`${API_URL}/auth/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ email, password }),
      });

      if (response.ok) {
        router.push("/projects");
        return;
      }

      setError(
        response.status === 401
          ? "Email or password is incorrect."
          : "Sign-in failed. Please try again."
      );
    } catch {
      setError("Could not reach the server. Check your connection.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader signedIn={false} />

      <main className="flex flex-1 items-center justify-center px-4 py-12 sm:px-6">
        <div className="w-full max-w-md rounded-2xl border border-white/[0.08] bg-surface/80 p-8 shadow-2xl backdrop-blur-xl sm:p-10">
          <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-full border border-accent/40 bg-accent/10 shadow-lg shadow-accent/5">
            <span className="font-serif text-2xl italic text-accent">D</span>
          </div>

          <h1 className="text-center font-serif text-3xl italic tracking-wide text-foreground">
            Welcome Back
          </h1>
          <p className="mt-2 text-center text-xs leading-relaxed text-muted">
            Photographers sign in below. Clients access shoots directly through their private delivery links.
          </p>

          {!API_URL && (
            <p className="mt-6 rounded-xl border border-white/[0.08] bg-surface-2 px-4 py-3 text-xs text-muted">
              Set{" "}
              <code className="rounded bg-surface px-1.5 py-0.5 font-mono text-accent">
                NEXT_PUBLIC_API_URL
              </code>{" "}
              to point at the portal API.
            </p>
          )}

          <form className="mt-8 space-y-5" onSubmit={onSubmit}>
            <div>
              <label htmlFor="email" className="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">
                Email
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full rounded-xl border border-white/[0.08] bg-surface-2/90 px-4 py-2.5 text-sm text-foreground placeholder:text-muted-dim focus:border-accent/40 focus:outline-none focus:ring-2 focus:ring-accent/50"
                placeholder="you@example.com"
              />
            </div>

            <div>
              <label htmlFor="password" className="mb-1.5 block text-xs font-medium uppercase tracking-wider text-muted">
                Password
              </label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full rounded-xl border border-white/[0.08] bg-surface-2/90 px-4 py-2.5 text-sm text-foreground placeholder:text-muted-dim focus:border-accent/40 focus:outline-none focus:ring-2 focus:ring-accent/50"
                placeholder="••••••••"
              />
            </div>

            {error && (
              <p role="alert" className="rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-2.5 text-xs text-rose-300">
                {error}
              </p>
            )}

            <button
              type="submit"
              disabled={submitting}
              className="w-full rounded-full bg-accent px-5 py-3 text-xs font-medium uppercase tracking-wider text-background shadow-lg shadow-accent/10 transition hover:bg-accent-hover disabled:opacity-60"
            >
              {submitting ? "Signing in…" : "Sign in"}
            </button>
          </form>

          <p className="mt-8 text-center text-xs text-muted">
            Received an invitation? Use your personalized invite link to activate access.
          </p>
        </div>
      </main>
    </div>
  );
}
