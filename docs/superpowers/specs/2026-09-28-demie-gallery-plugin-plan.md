# Implementation Plan — Demie Gallery Plugin

**Spec:** docs/adr/0002-gallery-as-plugin.md (decision + rationale)
**Domain terms:** CONTEXT.md — Gallery, Gallery Media Item, Gallery Page, Portfolio Item
**Goal:** A standalone plugin (`demie-gallery/`) that manages Galleries in admin (ordered photo/video lists) and renders them anywhere via `[demie_gallery]` using the 13 Kimono portfolio layouts, with pagination. The Gallery page becomes a thin wrapper over the shortcode.

## Conventions carried over from the theme

- Prefix everything `demie_g_` / `Demie_G_` (theme owns `demie_`).
- Text domain `demie-gallery`.
- Media list stored as post meta `_demie_g_media` = JSON array of items:
  `{"type":"attachment","id":123}` or `{"type":"embed","url":"https://youtu.be/…"}`
  (normalize: array of objects; attachment items may later carry `caption` override).
- Layout = template file per layout in `templates/`; one shared renderer feeds it.

---

## Step 1 — Scaffold + CPT + activation

**Build**

1. `demie-gallery/demie-gallery.php`
   - Header: Plugin Name: Demie Gallery, Version 0.1.0, Text Domain.
   - Constants: `DEMIE_G_VERSION`, `DEMIE_G_DIR`, `DEMIE_G_URI`.
   - Activation hook: register CPT + `flush_rewrite_rules()`.
   - `require` `inc/cpt.php`, `inc/media-list.php`, `inc/admin-ui.php`, `inc/shortcode.php`, `inc/render.php`, `inc/ajax.php`, `inc/assets.php`.
   - Deactivate (admin_notice only, not fatal) if the theme's Fancybox isn't enqueued — degrade gracefully per ADR.

2. `inc/cpt.php` — register `demie_gallery`:
   - `public => false`, `show_ui => true`, `menu_icon => 'dashicons-format-gallery'`, menu position 26.
   - `supports => ['title']` (media list lives in a metabox; description field added in Step 3).
   - Labels: Galleries / Gallery / Add New Gallery.

3. Admin list columns: cover thumbnail (first media item), media count, shortcode hint (`[demie_gal… id="12"]` copied to clipboard on click).

**Verify**
- Plugin activates with no errors; "Galleries" menu appears; Add New Gallery works; no notices.

---

## Step 2 — Data layer: ordered media list

**Build**

`inc/media-list.php`:
- `demie_g_get_media($gallery_id)` → parsed array of items, normalized shapes, filtered of dangling attachments (post Deleted/never existed).
- `demie_g_count($gallery_id)`; `demie_g_cover($gallery_id)` → first attachment thumbnail URL (or embed thumbnail via oEmbeddiscover for embeds).
- Media kind helper: `demie_g_kind($item)` → `photo|video` (attachment: `wp_attachment_is('video')`; embed: always `video`).
- Item HTML resolver: attachment photo → `wp_get_attachment_image_src('large')`; attachment video → video poster thumbnail (`get_the_post_thumbnail` if set, else generic play-icon tile); embed → `wp_oembed_get` HTML + thumbnail via oEmbed `thumbnail_url`.
- Sanitizers: `demie_g_sanitize_media_list($raw)` used on save and read.

**Verify**
- Unit-ish check via a WP-CLI/eval snippet or a temporary admin test page: seed a gallery meta with 2 photos + 1 youtu.be link; resolver returns correct URLs/kinds; dangling IDs are dropped.

---

## Step 3 — Admin UI: drag-and-drop builder

**Build**

`inc/admin-ui.php` + `assets/admin.css` + `assets/admin.js`:
1. Metabox "Gallery Media" on `demie_gallery`:
   - "Add Media" button → `wp.media` frame, `multiple: true`, library type `image,video`.
   - Selected items append to a sortable thumbnail grid (`jQuery UI Sortable`, already in WP admin). Each tile: thumb, type badge (Photo/Video), remove ×, drag handle. Embed items get their oEmbed thumbnail.
   - "Add Video Link" input: paste YouTube/Vimeo URL → validate against `wp_oembed_get`/allowed hosts → append as embed item.
2. Persist via one hidden input (`_demie_g_media` JSON) synced live by admin.js on every add/remove/reorder; saved in `save_post`.
3. Optional "Short Description" metabox field (`_demie_g_desc` textarea) shown as intro by layouts.
4. Nonce `demie_g_save_gallery`; capability check `current_user_can('edit_post', $post_id)`; only on our CPT screen.
5. Admin list page uses `demie_g_cover()`.

**Verify**
- Create "Chikwawa Wedding": add 3 photos via Media Library, reorder by drag, remove one, add a YouTube link; save; reload — order and items persist. First-item thumbnail shows in the list table.

---

## Step 4 — Renderer + shortcode (layout: masonry first)

**Build**

1. `inc/shortcode.php`: register `demie_gallery`.
   - Attrs: `id|slug|latest`, `layout` (default `masonry`), `per_page` (default 24), `pagination` (default `load_more`), `columns` (default `3`).
   - Resolve gallery: explicit `id`/`slug`, else latest published `demie_gallery` (menu_order ASC fallback date DESC — decide: **date DESC**).
   - Conflicting attrs resolve gracefully (ADR): `pagination="none"` → render all, ignore `per_page`; no gallery found → admin-only notice, silent for visitors.
2. `inc/render.php`:
   - `demie_g_render_gallery($args)` — single source of truth; returns HTML string (used by shortcode, page wrapper, AJAX).
   - Slice the ordered list server-side for page 1; `data-demie-gallery` attr block on wrapper carries `gallery_id|page|per_page|layout|total` for AJAX.
   - `paginate_links` for `numbered` using `?gallery-page=N` query var (read via `get_query_var` + `query_vars` filter).
   - `none` → full render, no pager.
3. `templates/` shared partials: `grid-open.php`, `grid-item.php` (switch on item type), `pager.php`. Masonry = Kimono markup: `effect-gradient has-radius`, `grid gutter-10 clearfix`, `grid-sizer`, spans per Kimono masonry-1.
4. `inc/assets.php`: front styles (`assets/gallery.css`, enqueued when shortcode present) + `assets/gallery.js`:
   - Delegated re-init of Isotope after DOM insert (theme's isotope-init binds directly — not delegated — so plugin must self-init on its own wrapper class, `demie-g-grid`, not rely on theme bindings).
   - Fancybox lightbox spans the whole gallery incl. video items (`data-fancybox="demie-g-{id}"`; embeds open as iframe).

**Verify**
- `[demie_gallery]` on a test page renders the seeded gallery in masonry; lightbox opens photos and the YouTube video; wrong/missing id shows nothing to visitors, notice to admins.

---

## Step 5 — AJAX load-more + numbered pagination

**Build**

1. `inc/ajax.php`: `wp_ajax_demie_g_load_more` + nopriv; inputs: gallery_id, page, per_page, layout, columns; nonce `demie_g_front` (localized with `wp_localize_script`); responds with rendered item HTML + `has_more`; reuses `demie_g_render_gallery` internals — no markup drift.
2. `gallery.js`: click handler appends items, re-inits Isotope, hides button when `has_more=false`, shows spinner.
3. `numbered` mode: rewrite-free `?gallery-page=N` links; works on any page (no core `/page/2/` collision, per ADR).
4. Carousel layout init (Swiper, `swiper-gallery-two` classes) re-run after append — carousel layout ignores pagination (declare `per_page`+pager as no-ops for `carousel`).

**Verify**
- 40-item gallery, per_page 12: Load More appends 12/12/…, Isotope relayouts, button disappears at the end. Numbered links produce page 2 with items 13–24 and stay on the same page URL.

---

## Step 6 — Port the 13 Kimono layouts

**Build**

One template each (markup from the repo's Kimono HTML files), grouped by mechanics:

| Layout key(s) | Source HTML | Mechanic |
|---|---|---|
| `masonry` | project-masonry-1 | Isotope mixed-height (done in Step 4) |
| `masonry-2` | project-masonry-2 | Isotope variant |
| `classic-2` / `classic-3` | project-classic-col-2/3 | Uniform Isotope, columns attr |
| `standard-2` / `standard-3` | project-standard-col-2/3 | Uniform Isotope variant |
| `modern-2` / `modern-3` | project-modern-col-2/3 | Uniform Isotope variant |
| `tiles-2` / `tiles-3` | project-tiles-col-2/3 | Uniform Isotope, edge-to-edge |
| `carousel` | project-carousel | Swiper slider |
| `overlapping` | project-overlapping | CSS effect grid |
| `distortion` | project-distortion | WebGL hover effect (theme already has cursor/displacement assets — reuse; plain-fallback if JS fails) |
| `filterable` (masonry + standard) | project-*-filterable | Isotope + filter buttons: **All / Photos / Videos** (kind from Step 2) |

- Filter buttons: rendered by `pager.php`-style partial; bindings delegated in `gallery.js` (theme's are direct-bound).
- Each template pulls only item data; all HTML conventions stay Kimono (`wptb-item--image`, hover overlay, captions).

**Verify**
- Shortcode with each layout renders on the test page; filterable shows All/Photos/Videos correctly with mixed media; distortion falls back to plain grid with JS disabled.

---

## Step 7 — Gallery page cutover

**Build**

1. `demie-photography-theme/page-gallery.php`: replace the Portfolio-Items loop with `echo do_shortcode('[demie_gallery]')` (latest, masonry, load_more, per_page 24), keep the existing titlebar/heading block.
2. Seed-safe: if the plugin is inactive, page falls back to rendering the old Portfolio grid (keep the old loop behind `if (!shortcode_exists('demie_gallery'))`).
3. Homepage portfolio section: untouched (Portfolio Item keeps its narrowed role).
4. Seed (`inc/seed.php`): on next version bump, create one demo Gallery seeded with the existing 12 portfolio photos so the Gallery page shows real content immediately after upgrade.

**Verify**
- Gallery page renders the demo gallery; deactivate plugin → old grid returns; reactivate → gallery returns. Homepage portfolio unchanged.

---

## Step 8 — Polish + docs

**Build**
- `readme.txt` (or README.md) in plugin: install, shortcode reference table, layout keys, video link support, FAQ.
- Wrap-up: bump theme version only if seed changed (Step 7 seed) so auto-seed runs once.
- CONTEXT.md: no further changes (terms already updated during grilling).

**Verify**
- Full pass: fresh WP + theme + plugin → activate → demo gallery appears on Gallery page; create a second gallery; embed shortcode on About page; all layouts smoke-tested; no PHP notices with `WP_DEBUG` on.

---

## Out of scope (per ADR-0002)

- Client login/download areas — future feature builds on `_demie_g_media` storage, not a new system.
- Per-media tags beyond photo/video kind.
- WooCommerce/shop layouts from Kimono.

## Suggested commit sequence (tiny commits, one per step)

1. `feat(gallery): plugin scaffold + demie_gallery CPT`
2. `feat(gallery): ordered media-list data layer`
3. `feat(gallery): admin drag-and-drop media builder`
4. `feat(gallery): shortcode + masonry renderer + lightbox`
5. `feat(gallery): AJAX load-more + numbered pagination`
6. `feat(gallery): port 13 Kimono layouts`
7. `feat(theme): gallery page renders demie_gallery shortcode`
8. `docs(gallery): readme + seed demo gallery`
