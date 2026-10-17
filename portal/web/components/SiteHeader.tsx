import Link from "next/link";

export default function SiteHeader({
  signedIn,
  onSignOut,
  children,
}: {
  signedIn: boolean;
  onSignOut?: () => void;
  children?: React.ReactNode;
}) {
  return (
    <header className="sticky top-0 z-40 w-full border-b border-white/[0.08] bg-[#0a0a0d]/80 backdrop-blur-xl transition-all">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-8 py-3.5">
        <Link href="/" className="group flex items-center gap-3">
          <div className="flex h-9 w-9 items-center justify-center rounded-full border border-accent/40 bg-accent/10 transition group-hover:border-accent group-hover:bg-accent/20">
            <span className="font-serif text-base italic text-accent">D</span>
          </div>
          <div className="flex flex-col">
            <span className="font-serif text-lg tracking-[0.14em] uppercase text-foreground transition group-hover:text-accent">
              Demie
            </span>
            <span className="text-[9px] tracking-widest2 uppercase text-muted font-medium">
              Photography
            </span>
          </div>
        </Link>

        <div className="flex items-center gap-4 sm:gap-6">
          {children}

          <nav className="flex items-center gap-4 sm:gap-6 text-xs tracking-wider uppercase font-medium text-muted">
            {signedIn ? (
              <>
                <Link
                  href="/projects"
                  className="transition hover:text-foreground"
                >
                  Shoots
                </Link>
                <button
                  type="button"
                  onClick={onSignOut}
                  className="cursor-pointer rounded-full border border-white/10 px-3 py-1 text-[11px] text-muted transition hover:border-white/25 hover:text-foreground"
                >
                  Sign out
                </button>
              </>
            ) : (
              <Link
                href="/login"
                className="rounded-full border border-white/10 px-3.5 py-1.5 text-[11px] text-muted transition hover:border-accent/40 hover:text-accent"
              >
                Sign in
              </Link>
            )}
          </nav>
        </div>
      </div>
    </header>
  );
}
