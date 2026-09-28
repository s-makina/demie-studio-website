#!/usr/bin/env python3
"""
deploy_theme.py — sync the Demie Photography theme from the dev folder to the live site.

What it does
------------
1. Copies every changed file from the dev theme into the live WP theme folder.
2. Bumps the theme version (DEMIE_VERSION in functions.php) so browsers
   drop their cached CSS/JS — no more "my edits are not applying".
3. (Optional) Fetches the live homepage and brand.css to PROVE the new
   version is actually being served.
4. (Optional) Rebuilds demie-photography-theme.zip for manual WP admin upload.
5. (Optional) --watch mode: re-syncs automatically whenever a file changes.

Usage
-----
    python deploy_theme.py                 # sync + bump version + verify
    python deploy_theme.py --no-bump       # sync + verify, keep the version
    python deploy_theme.py --zip           # also rebuild the deliverable zip
    python deploy_theme.py --watch         # keep running, auto-sync on save
    python deploy_theme.py --dry-run       # show what would change, touch nothing

The paths below match this machine. Edit DEV_THEME / LIVE_THEME if they move.
"""

import argparse
import filecmp
import fnmatch
import hashlib
import re
import shutil
import subprocess
import sys
import time
import urllib.request
import zipfile
from pathlib import Path

# --------------------------------------------------------------------------
# Configuration — adjust here if the project moves
# --------------------------------------------------------------------------
PROJECT_ROOT = Path(r"C:\xampp\htdocs\other\demie-studio-website")
DEV_THEME    = PROJECT_ROOT / "demie-photography-theme"
LIVE_THEME   = Path(r"C:\xampp\htdocs\other\demie-studio-wp\wp-content\themes"
                    r"\demie-photography-theme")
SITE_URL     = "http://localhost/other/demie-studio-wp"
ZIP_PATH     = PROJECT_ROOT / "demie-photography-theme.zip"

# Files that never get copied to the live theme (dev-only clutter).
EXCLUDE_PATTERNS = [
    "*.DS_Store", "Thumbs.db", "*.bak", "*~", "*.tmp",
    ".git*", "*.md",
]

VERSION_FILE = "functions.php"
VERSION_RE   = re.compile(r"(define\(\s*'DEMIE_VERSION'\s*,\s*')([\d.]+)('\))")


# --------------------------------------------------------------------------
# Helpers
# --------------------------------------------------------------------------
def log(msg: str) -> None:
    print(msg, flush=True)


def is_excluded(rel_path: Path) -> bool:
    """True if a relative path matches one of the exclude patterns."""
    name = rel_path.name
    parts = rel_path.parts
    for pat in EXCLUDE_PATTERNS:
        if fnmatch.fnmatch(name, pat) or any(fnmatch.fnmatch(p, pat) for p in parts):
            return True
    return False


def collect_files(root: Path) -> dict:
    """Map of relative-path -> Path for every file under root (excludes applied)."""
    out = {}
    if not root.is_dir():
        return out
    for p in root.rglob("*"):
        if p.is_file() and not is_excluded(p.relative_to(root)):
            out[p.relative_to(root).as_posix()] = p
    return out


def md5(path: Path) -> str:
    h = hashlib.md5()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def changed_files() -> tuple[list, list, list]:
    """Return (to_copy, to_delete, identical) comparing dev vs live trees."""
    dev  = collect_files(DEV_THEME)
    live = collect_files(LIVE_THEME)

    to_copy, identical = [], []
    for rel, dev_path in dev.items():
        live_path = LIVE_THEME / rel
        if not live_path.is_file():
            to_copy.append(rel)
        elif md5(dev_path) != md5(live_path):
            to_copy.append(rel)
        else:
            identical.append(rel)

    to_delete = [rel for rel in live if rel not in dev]
    return to_copy, to_delete, identical


def copy_file(rel: str) -> None:
    src = DEV_THEME / rel
    dst = LIVE_THEME / rel
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(src, dst)


def read_version() -> str:
    text = (DEV_THEME / VERSION_FILE).read_text(encoding="utf-8")
    m = VERSION_RE.search(text)
    return m.group(2) if m else "0.0.0"


def bump_version() -> str:
    """Increment the patch segment of DEMIE_VERSION in the dev theme."""
    fp = DEV_THEME / VERSION_FILE
    text = fp.read_text(encoding="utf-8")
    m = VERSION_RE.search(text)
    if not m:
        log(f"  !! Could not find DEMIE_VERSION in {VERSION_FILE} — skipping bump")
        return read_version()

    old = m.group(2)
    parts = old.split(".")
    parts[-1] = str(int(parts[-1]) + 1)
    new = ".".join(parts)
    fp.write_text(VERSION_RE.sub(lambda mo: mo.group(1) + new + mo.group(3), text),
                  encoding="utf-8")
    log(f"  ~ Version bumped: {old} -> {new}")
    return new


def verify(version: str) -> bool:
    """Fetch the live homepage + brand.css and confirm the version is served."""
    ok = True
    page_url = f"{SITE_URL}/"
    css_url  = f"{SITE_URL}/wp-content/themes/demie-photography-theme/assets/css/brand.css?ver={version}"

    try:
        with urllib.request.urlopen(page_url, timeout=10) as r:
            html = r.read().decode("utf-8", errors="replace")
        if f"brand.css?ver={version}" in html:
            log(f"  + Homepage links brand.css?ver={version}  (cache-bust live)")
        else:
            log(f"  !! Homepage does NOT link brand.css?ver={version}")
            ok = False

        with urllib.request.urlopen(css_url, timeout=10) as r:
            css = r.read().decode("utf-8", errors="replace")
        marker = "Image height guards"
        if marker in css and md5_path_bytes(css) == md5(DEV_THEME / "assets/css/brand.css"):
            log(f"  + Live brand.css is the deployed version (guards present, md5 match)")
        elif marker in css:
            log(f"  + Live brand.css contains the guards (md5 differs — check for drift)")
        else:
            log(f"  !! Live brand.css is MISSING the guards")
            ok = False
    except Exception as e:
        log(f"  !! Could not reach {SITE_URL}: {e}")
        ok = False

    return ok


def md5_path_bytes(text: str) -> str:
    return hashlib.md5(text.encode("utf-8")).hexdigest()


def build_zip() -> None:
    """Rebuild the WP-admin-ready deliverable zip (theme folder at root)."""
    log(f"  ~ Building {ZIP_PATH.name} ...")
    if ZIP_PATH.exists():
        ZIP_PATH.unlink()
    with zipfile.ZipFile(ZIP_PATH, "w", zipfile.ZIP_DEFLATED) as z:
        for rel, path in sorted(collect_files(DEV_THEME).items()):
            z.write(path, f"{DEV_THEME.name}/{rel}")
    size_mb = ZIP_PATH.stat().st_size / (1024 * 1024)
    log(f"  + Zip built: {ZIP_PATH} ({size_mb:.1f} MB, "
        f"{len(list(collect_files(DEV_THEME).items()))} files)")


def watch_loop(args) -> None:
    """Re-run the deploy whenever any dev-theme file changes."""
    log("Watch mode — deploy on every save. Ctrl+C to stop.")
    last = snapshot()
    cooldown = 1.0
    while True:
        time.sleep(cooldown)
        now = snapshot()
        if now != last:
            changed = {k for k in set(now) | set(last) if now.get(k) != last.get(k)}
            log(f"\n[{time.strftime('%H:%M:%S')}] {len(changed)} file(s) changed")
            run_deploy(args)
            last = snapshot()


def snapshot() -> dict:
    return {rel: md5(p) for rel, p in collect_files(DEV_THEME).items()}


def run_deploy(args) -> None:
    to_copy, to_delete, identical = changed_files()

    log("")
    log("=" * 62)
    log(f"Deploy: dev theme -> live site")
    log(f"  dev : {DEV_THEME}")
    log(f"  live: {LIVE_THEME}")
    log("=" * 62)

    if not DEV_THEME.is_dir():
        log(f"!! Dev theme folder not found: {DEV_THEME}")
        sys.exit(1)
    if not LIVE_THEME.is_dir():
        log(f"!! Live theme folder not found: {LIVE_THEME}")
        sys.exit(1)

    if args.dry_run:
        if to_copy or to_delete:
            log(f"\nWould copy {len(to_copy)} file(s):")
            for rel in to_copy[:20]:
                log(f"    {rel}")
            if len(to_copy) > 20:
                log(f"    ... and {len(to_copy) - 20} more")
            if to_delete:
                log(f"Would delete {len(to_delete)} stale file(s): {to_delete[:10]}")
        else:
            log("\nNothing to do — dev and live are identical.")
        return

    if not to_copy and not to_delete and not args.force:
        log("\nDev and live themes are identical — nothing to copy.")
    else:
        # 1) Sync files
        for rel in to_copy:
            copy_file(rel)
            log(f"  + copied {rel}")
        for rel in to_delete:
            (LIVE_THEME / rel).unlink()
            log(f"  - deleted stale {rel}")
        log(f"  + {len(to_copy)} copied, {len(to_delete)} deleted, "
            f"{len(identical)} already identical")

    # 2) Bump version unless asked not to (skip when there was nothing to copy
    #    and --force was not passed, to avoid pointless cache busts)
    version = read_version()
    if args.bump and (to_copy or args.force):
        version = bump_version()
        copy_file(VERSION_FILE)
        log(f"  + copied {VERSION_FILE} (version {version})")

    # 3) Optional zip
    if args.zip:
        build_zip()

    # 4) Verify the live site actually serves the new version
    log("")
    log("Verifying live site ...")
    if verify(version):
        log("  + LIVE SITE IS UP TO DATE")
    else:
        log("  !! Verification failed — see messages above")
        sys.exit(2)


# --------------------------------------------------------------------------
# CLI
# --------------------------------------------------------------------------
def main() -> None:
    ap = argparse.ArgumentParser(
        description="Sync the Demie theme from dev to the live WP install.")
    ap.add_argument("--dry-run", action="store_true",
                    help="show what would change without touching anything")
    ap.add_argument("--no-bump", dest="bump", action="store_false",
                    help="do not bump DEMIE_VERSION")
    ap.add_argument("--zip", action="store_true",
                    help="also rebuild demie-photography-theme.zip")
    ap.add_argument("--force", action="store_true",
                    help="bump version + verify even if nothing changed")
    ap.add_argument("--watch", action="store_true",
                    help="keep running and auto-deploy on file changes")
    args = ap.parse_args()

    if args.watch:
        run_deploy(args)   # initial full deploy
        try:
            watch_loop(args)
        except KeyboardInterrupt:
            log("\nWatch stopped.")
    else:
        run_deploy(args)


if __name__ == "__main__":
    main()
