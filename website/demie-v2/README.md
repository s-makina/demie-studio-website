# Demie v2 — Luxury Editorial theme

Ported from `v2/code.html` (Tailwind + Cormorant Garamond / Plus Jakarta Sans).
Folder: `demie-v2/` · Text domain: `demie-v2` · Version 0.1.0

## What it is

A second, independent theme next to `demie-photography-theme/` (v1, Kimono-based).
Same content layer, new skin: full-screen video hero, editorial masonry portfolio,
featured story, photography/films split, pillars, testimonials, booking CTA with
working AJAX contact form.

## WordPress integration (same as v1)

- Menus: **Primary Navigation**, **Footer Navigation** (Appearance → Menus).
  Without a menu, tasteful fallbacks render automatically.
- Customizer → **V2 Hero** (eyebrow, two-line title, subtitle, video URL, poster)
  and **V2 Content** (statement quote/text, booking title/text).
- CPTs + Studio Details + contact AJAX are reused verbatim from v1 (`inc/`),
  so Slides, Services, Portfolio Items, Testimonials, FAQs and
  Settings → Demie Settings keep working.
- Gallery content comes from the Demie Gallery plugin via the shared
  `demie_v2_gallery_cards()` helper (functions.php): the homepage section
  shows the `homepage` gallery (else latest gallery), the Gallery page shows
  the latest gallery in full. Video items get a "Film" badge. Portfolio Items
  cover a missing/empty plugin; curated Unsplash archives are the last resort
  on the homepage only.
- Contact details default to the CONTEXT.md brand facts
  (+265 884 44 48 02 · demiestudios@gmail.com · Chilomoni, Blantyre, Malawi).

## Files

```
style.css                  theme header
functions.php              setup, Customizer, Tailwind CDN + v2 assets, walkers
header.php / footer.php    v2 nav (scroll state + mobile overlay) / v2 footer
front-page.php             assembles the 8 homepage sections
template-parts/hero.php | statement.php | gallery.php | featured-story.php
               services.php | why.php | testimonials.php | booking.php
               page-titlebar.php
page-gallery.php           Demie Gallery shortcode + portfolio fallback
page-services.php          all Service CPT entries as editorial cards
page-about-us.php          content + FAQs + pillars + testimonials + booking
page-contact.php           studio detail cards + booking form
page-blog.php / index.php / single.php / page.php / 404.php
assets/css/v2.css           extracted v2/code.html <style> + form/prose styles
assets/js/theme-v2.js       nav, mobile menu, reveal-on-scroll, video fallback
assets/js/demie-forms.js    AJAX form binding (copy of v1, same markup contract)
inc/                       contact, settings, cpts, metaboxes, seed, template-tags (from v1)
```

## Preview / activate

1. Copy `demie-v2/` to `wp-content/themes/` and activate in
   Appearance → Themes (auto-seed is idempotent; existing content is reused).
2. Assign menus, then tune copy in Customizer → V2 Hero / V2 Content.

Note: Tailwind loads from CDN (as in `v2/code.html`), so a network connection
is required for full styling; `assets/css/v2.css` carries the bespoke effects.
