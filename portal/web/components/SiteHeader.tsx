import Link from "next/link";

export default function SiteHeader({
  signedIn,
  onSignOut,
}: {
  signedIn: boolean;
  onSignOut?: () => void;
}) {
  return (
    <header className="border-b border-border bg-surface">
      <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
        <Link href="/" className="font-semibold tracking-tight text-foreground">
          Demie Photography
        </Link>
        <nav className="flex items-center gap-6 text-sm text-muted">
          {signedIn ? (
            <>
              <Link href="/projects" className="hover:text-foreground">
                My projects
              </Link>
              <button
                type="button"
                onClick={onSignOut}
                className="cursor-pointer hover:text-foreground"
              >
                Sign out
              </button>
            </>
          ) : (
            <Link href="/login" className="hover:text-foreground">
              Sign in
            </Link>
          )}
        </nav>
      </div>
    </header>
  );
}
