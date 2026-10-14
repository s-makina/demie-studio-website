import Link from "next/link";

export default function LoginPage() {
  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
          <Link href="/" className="font-semibold tracking-tight text-foreground">
            Demie Photography
          </Link>
          <nav className="flex gap-6 text-sm text-muted">
            <Link
              href="/login"
              className="hover:text-foreground"
            >
              Sign in
            </Link>
          </nav>
        </div>
      </header>

      <main className="flex flex-1 items-center justify-center px-6 py-12">
        <div className="w-full max-w-sm">
          <h1 className="mb-1 text-2xl font-semibold">Sign in</h1>
          <p className="mb-8 text-muted">
            Photographers sign in here. Clients use their share link — no login needed.
          </p>

          <form className="space-y-5">
            <div>
              <label
                htmlFor="email"
                className="mb-1.5 block text-sm font-medium text-foreground"
              >
                Email
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                required
                className="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-foreground placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent"
                placeholder="you@example.com"
              />
            </div>

            <div>
              <label
                htmlFor="password"
                className="mb-1.5 block text-sm font-medium text-foreground"
              >
                Password
              </label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                className="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-foreground placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent"
                placeholder="••••••••"
              />
            </div>

            <button
              type="submit"
              className="w-full rounded-lg bg-accent px-4 py-2.5 font-medium text-background transition hover:bg-accent-hover"
            >
              Sign in
            </button>
          </form>

          <p className="mt-8 text-center text-sm text-muted">
            Forgot your password?{" "}
            <Link href="/login/forgot" className="underline hover:text-foreground">
              Reset it
            </Link>
          </p>
        </div>
      </main>
    </div>
  );
}
