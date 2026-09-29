=== Demie Gallery ===
Contributors: demiephotography
Tags: gallery, portfolio, photography, lightbox, isotope
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Owner-managed Galleries (ordered photos + videos) rendered anywhere with the Kimono portfolio layouts.

== Description ==

Demie Gallery adds a **Galleries** menu to wp-admin. Each Gallery is an ordered
list of Gallery Media Items — Media Library photos, uploaded videos, or
external video links (YouTube/Vimeo) — that you can drop on any page with the
`[demie_gallery]` shortcode.

* Drag-and-drop ordering in the admin; the order is the display order.
* Photos open in the theme's Fancybox lightbox; YouTube/Vimeo links open as
  embedded players.
* 13 Kimono portfolio layouts, with pagination (AJAX load-more or numbered
  links) for large galleries.
* Filters (All / Photos / Videos) on the filterable layouts.
* The Gallery page template renders the latest gallery automatically.

Built for the Demie Photography theme; the lightbox (Fancybox), Isotope and
Swiper come from the theme. Without them galleries still render, just without
those behaviours.

== Installation ==

1. Upload the `demie-gallery` folder to `wp-content/plugins/`, or zip it and
   install via Plugins → Add New → Upload Plugin.
2. Activate **Demie Gallery**. The **Galleries** menu appears in wp-admin.
3. Galleries → Add New Gallery → give it a title → **Add Media** (or paste a
   YouTube/Vimeo URL under **Add Video Link**) → drag tiles to order → Publish.
4. On the Gallery page nothing else to do — it renders the latest gallery. On
   any other page/post, insert the shortcode (see reference below).

== Shortcode reference ==

`[demie_gallery id="" slug="" layout="masonry" per_page="24" pagination="load_more" columns="3"]`

| Attr         | Values | Default | Notes |
|--------------|--------|---------|-------|
| `id`         | gallery post ID | — | Beats `slug` and `latest`. |
| `slug`       | gallery slug | — | Used when no `id`. |
| `layout`     | `masonry`, `masonry-2`, `classic-2`, `classic-3`, `standard-2`, `standard-3`, `modern-2`, `modern-3`, `tiles-2`, `tiles-3`, `carousel`, `overlapping`, `distortion`, `filterable` | `masonry` | Unknown values fall back to masonry. |
| `per_page`   | number | 24 | Items per page for load-more/numbered. |
| `pagination` | `load_more`, `numbered`, `none` | `load_more` | `none` renders everything and ignores `per_page`. |
| `columns`    | 2 / 3  | 3 | Column count for the col-2/col-3 grid layouts. |

Layout notes:

* **carousel** — Swiper slider; `per_page` and `pagination` are no-ops.
* **overlapping** — CSS-effect collage grid; load-more degrades to `none`.
* **distortion** — Kimono's WebGL hover effect needs displacement textures the
  theme does not bundle, so it renders as a plain hover-tilt grid (graceful
  fallback per ADR-0002).
* **filterable** — masonry-style grid with All / Photos / Videos filter
  buttons driven by each item's media kind.

Missing or unpublished `id`/`slug`: visitors see nothing; admins see a notice.

== Video link support ==

Under the gallery editor, paste a YouTube or Vimeo URL into **Add Video Link**.
The URL is validated through WordPress oEmbed before it's added. Embeds are
stored as `{"type":"embed","url":"…"}` in the gallery's media list; their grid
thumbnail comes from the provider (YouTube/Vimeo) when available, otherwise a
generic play tile is shown.

== Frequently Asked Questions ==

= Where does the gallery content live? =

In one post meta field (`_demie_g_media`) on the gallery: a JSON array of
items, `{"type":"attachment","id":123}` or `{"type":"embed","url":"…"}`.
Deleting a Media Library photo removes it from the gallery on the next render
(dangling items are filtered out).

= Can I override the markup? =

Yes — copy any template from `demie-gallery/templates/` into a
`demie-gallery/` folder in your theme and edit it there.

= What happens if I deactivate the plugin? =

The Gallery page falls back to the legacy Portfolio grid. Shortcodes on other
pages render nothing (admins get a notice).

= Does the gallery survive a theme switch? =

Yes — galleries are plugin data. The layouts are tuned for the Demie
Photography theme; on another theme the grid renders but effects/lightbox
depend on what that theme provides.

== Changelog ==

= 0.1.0 =
* Initial release: Galleries CPT, drag-and-drop media builder, video links,
  [demie_gallery] shortcode, 13 layouts, load-more + numbered pagination,
  Gallery page cutover with legacy fallback.
