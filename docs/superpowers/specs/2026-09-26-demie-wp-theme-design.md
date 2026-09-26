# Demie Photography — WordPress Theme Design

**Date:** 2026-09-26
**Pattern source:** `C:\xampp\htdocs\websites\99carex\99carex-theme` (99carex-theme)
**HTML source:** this repo (Kimono template rebranded to Demie Photography)

## Goal

Convert the static HTML site in this repo into a portable WordPress theme,
uploadable via Appearance → Themes, following the same structure and
conventions as the 99carex theme: flat `page-*.php` templates, `inc/` for
PHP handlers, `template-parts/` for reusable markup, `assets/` for css/js/img,
a versioned enqueue block in `functions.php`, and a zip deliverable in the
project root.

## Decisions (confirmed by user)

| Question | Decision |
| --- | --- |
| Homepage | `index.html` (Home One) |
| Gallery | `project-masonry-1.html` (masonry layout) |
| Shop | **Dropped** — no WooCommerce, no shop pages |
| Enquiry form | AJAX form like 99carex (`inc/contact.php`), mail to demiestudios@gmail.com |
| Editable content | **Changed 2026-09-26** — full content takeover via CPTs + metaboxes (see `docs/adr/0001-cpts-and-metaboxes-for-editable-content.md`); supersedes the "static sections for brochure pages" mechanic below. CPTs: Service (short+full desc), Testimonial, FAQ, Slide, Portfolio (non-public, lightbox). Studio Details metabox on a Demie Settings page. Home Sections metabox on the seeded Home page. Version-guarded auto-seed; empty sections hide. |

## Brand facts (from CONTEXT.md — authoritative)

- Display name: **Demie Photography** (never "Demie Studios"/"Kimono")
- Phone: +265 884 44 48 02 → `tel:+265884444802`, `wa.me/265884444802`
- Email: demiestudios@gmail.com (mailto links)
- Location: Chilomoni, Blantyre, Malawi

## Structure

```
demie-photography-theme/
├── style.css                 ← theme header comment only
├── functions.php             ← DEMIE_VERSION consts, setup, menus, enqueue, titlebar part
├── inc/
│   └── contact.php           ← AJAX handler: sanitize, wp_mail, JSON response (99carex pattern)
├── template-parts/
│   └── page-titlebar.php     ← dark page header w/ bg image + breadcrumbs
├── header.php                ← preloader, cursor, slide-out menu, WP nav (walker), search modal
├── footer.php                ← footer markup; scripts enqueued via wp_footer
├── front-page.php            ← Home One sections (slider/about/projects/experience/testimonials/blog/contact)
├── index.php                 ← blog index fallback
├── page.php                  ← generic page + titlebar
├── single.php                ← single post
├── 404.php                   ← from 404.html
├── page-about-us.php         ← from about.html
├── page-services.php         ← from services-1.html
├── page-gallery.php          ← from project-masonry-1.html
├── page-blog.php             ← from blog-grid.html, WP loop
├── page-contact.php          ← from contact-1.html, AJAX form wired
├── screenshot.png            ← 1200x900 preview
├── assets/
│   ├── css/ main.css brand.css
│   ├── fonts/ (copied)
│   ├── img/ (copied)
│   ├── js/  jquery, bootstrap, theme.js
│   └── vendor/ (all template plugins, copied from plugins/)
└── video/ (copied)
```

## Mechanics

- **Enqueue**: `main.css` → `brand.css` → google-font/preload as in HTML;
  scripts in same order as index.html (jquery → bootstrap → vendor plugins →
  theme.js), all footer, with `DEMIE_VERSION` cache-busting.
- **Menu**: `primary` + `footer-links` locations; `Demie_Walker` outputs the
  exact Kimono `<ul class="main-menu">` / `sub-menu` markup so CSS is untouched;
  fallback builds menu from page list when no menu assigned.
- **Dynamic content**: WP loop for blog index/single; static sections for the
  brochure pages (like 99carex does); WP `get_search_form` modal; titlebar part
  parameterized by title + crumbs.
- **AJAX form**: `wp_ajax_demie_contact` (+nopriv), nonce `demie-contact`,
  sanitize name/email/phone/subject/message, `wp_mail` to
  `demiestudios@gmail.com`, JSON success/error; inline JS in footer.php binds
  the form (localized `demieCtx`).
- **Search modal**: form posts to `home_url('/')` with `s` param.

## Assets note

The template loads only `main.css` + `brand.css` (bundled), plus vendor JS
plugins copied to `assets/vendor/<name>/`. Fonts + img are bundled. `theme.js`
may reference `assets/css/animation.css`-style URLs — verify and repoint to
theme paths during build.

## Out of scope

- WooCommerce/shop, homepage variants 2–26, other gallery layouts, booking/
  login/coming-soon pages, static `shop-*.html`.
