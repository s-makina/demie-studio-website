# 0002 — Galleries as a standalone plugin, not theme code

**Status:** Accepted
**Date:** 2026-09-28

## Context

The owner wants real, reusable galleries: created in the admin, holding "a lot of media" (photos and videos, including YouTube/Vimeo links), rendered on any page via shortcode, with pagination and the Kimono template's portfolio layouts. The theme's current gallery story — the Gallery page rendering the newest 12 Portfolio Items in one fixed masonry — cannot carry that weight.

## Alternatives considered

- **Theme module** (`inc/gallery.php`): fewer moving parts on a single site, but gallery data and rendering die with the theme — and this theme is a rebrand of a purchased template that may be replaced.
- **Taxonomy on Media Library items**: native queries, but building a gallery means per-photo tagging; painful at scale, reordering across a gallery is miserable.
- **Custom database tables**: plugin-grade robustness, but the most code and maintenance; overkill for the expected scale.

## Decision

1. **Standalone plugin** (`demie-gallery/`, sibling of the theme in this repo): plugin bootstrap, own admin UI, own assets; the theme stays presentation-only. Gallery content survives theme swaps.
2. **Data model**: a `demie_gallery` CPT. Each Gallery stores an ordered list of **Gallery Media Items**; each item is either a Media Library attachment (photo or uploaded video) or an external video URL (YouTube/Vimeo via oEmbed). Order = display order; drag-and-drop in admin.
3. **Rendering**: `[demie_gallery id|slug|latest layout="…" per_page="…" pagination="…" columns="…"]`. Pagination: `load_more` (AJAX append, nonce-protected), `numbered` (`?gallery-page=N` + `paginate_links`, not core `/page/2/` rewriting), `none` (render all; Fancybox lightbox spans the whole gallery).
4. **Layouts**: all 13 Kimono portfolio variants ported as plugin templates, grouped as masonry, uniform grids (classic/standard/modern/tiles col-2/3), carousel, and the exotic set (overlapping/distortion/filterable). Filterable variants filter on media kind: All | Photos | Videos.
5. **Cutover**: the Gallery page template becomes a thin wrapper rendering `[demie_gallery]` (latest, masonry, load_more). Portfolio Items remain, narrowed to the homepage portfolio section. No data migration.

## Consequences

- One site, one more plugin: activation is now part of deployment; the plugin must be enabled for the Gallery page to work. Accepted — reusability and theme-independence were explicit goals.
- The theme keeps its CPTs for homepage content; only the Gallery page's data source changes. CONTEXT.md updated: Portfolio Item narrowed, Gallery/Gallery Page/Gallery Media Item added.
- Large galleries paginate without loading every attachment; AJAX load-more reuses the server-side renderer, so initial paint and appended markup cannot drift apart.
- Fancybox lightbox continues to come from the theme; the plugin depends on it being present (degrades to plain image links if not).
- If the "gallery handles client downloads" idea resurfaces, it builds on this plugin's per-media storage rather than a new system.
