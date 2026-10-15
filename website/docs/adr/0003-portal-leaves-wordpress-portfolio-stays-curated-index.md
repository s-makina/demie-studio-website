# 0003 — Client portal leaves WordPress; portfolio page stays as curated Gallery index

WordPress keeps the brochure site plus a lightweight `portfolio/` index (one Gallery = one card, per-gallery "Show on portfolio" gate, inline `?project=` detail via the existing Gallery renderer). The client-download portal (Project = client-owned Gallery, private links, per-photo + background ZIP with close-and-return, 2,000-media scale) moves to a separate Spring Boot + Next.js + Postgres app.

**Status:** Accepted
**Date:** 2026-10-03

## Considered Options

- **All in WordPress** (extend `demie_gallery` with client + token + ZIP via WP-Cron): rejected — current `_demie_g_media` JSON + per-item queries won't carry 2,000-media galleries, and chunked ZIPs + resumable jobs want a real job store.
- **Full rebuild in Next.js**: rejected — throws away seeded CPTs, 13 Gallery layouts, and Fancybox work for no portal benefit; brochure stays in WP untouched.
- **Chosen split**: WP owns curation/discovery; portal owns delivery/downloads and its own `project_media` table later.

## Consequences

- This plan adds only `_demie_g_show_on_portfolio` + `page-portfolio.php`; no auth, token, or ZIP code lands in WP now.
- `Project` (CONTEXT.md) is a forward term for a client-owned Gallery whose storage lives in the future portal, not a second WP CPT — no Gallery/Project silo split.
- Portfolio detail URLs (`portfolio/?project=slug`) are disposable: the portal may take over per-project URLs later without data migration.
