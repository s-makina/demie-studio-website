# 0001 — CPTs and metaboxes for editable content instead of plugins or Customizer

**Status:** Accepted
**Date:** 2026-09-26
**Supersedes:** the "static sections for the brochure pages" mechanic in `docs/superpowers/specs/2026-09-26-demie-wp-theme-design.md`

## Context

The Demie Photography theme was deliberately built with hardcoded brochure sections (slider, services, testimonials, FAQ, stats, contact details), matching the 99carex precedent. The site owner now needs to manage all page content from wp-admin. The data is a mix of:

- Global scalars (phone, email, location, social URLs) — "Studio Details"
- Repeating collections (services, testimonials, FAQ, slider slides, portfolio items)
- Page-scoped prose/headings/stats (Home, About, Services, Contact)

The theme previously served gallery content from blog posts in a `gallery` category — fake blog posts leaking into feeds and search.

## Alternatives considered

- **ACF / CMB2 plugins** — faster to build, but adds a plugin dependency to a portable brochure theme; support and licensing burden shifts to the client.
- **Customizer** — good for scalars, but panels become unwieldy for 6+ item collections and offer no revision history for prose.
- **Theme options page (hand-rolled)** — full control but we build and maintain the whole UI.
- **Status quo (hardcoded)** — contradicts the owner's requirement; a one-word typo in a testimonial requires a developer.

## Decision

Native WordPress, no plugins:

1. **CPTs**: `demie_service`, `demie_testimonial`, `demie_faq`, `demie_slide`, `demie_portfolio` (non-public; gallery and homepage portfolio render it in a lightbox, no single pages).
2. **Service** has two description fields (short blurb for homepage, full description for the Services page); homepage shows the first four.
3. **Metaboxes** for page-scoped content: a "Home Sections" metabox on the seeded Home page (about prose, stats, headings) and a small "Page Headings" metabox on About/Services/Contact.
4. **Studio Details** live in a metabox on a "Demie Settings" admin page (not the Customizer).
5. **Version-guarded auto-seed** (99carex `inc/seed.php` pattern): pages (Home/About/Services/Gallery/Blog/Contact), CPT entries with the current template copy, and settings values are created on activation/version bump. Empty collections hide their frontend section.

## Consequences

- The client can edit every visible string, number, image, and collection without a developer.
- No plugin dependencies; the theme stays a single zip.
- Gallery no longer abuses blog posts; portfolio content is separated from the blog.
- CONTEXT.md terms (Studio Details, Service, Portfolio Item) are now owner-editable data seeded from the documented brand facts.
- More code to maintain (~5 CPT registrations, metaboxes, seeder); acceptable for a bespoke theme.
- Reversing course later (e.g. to ACF) means a data migration; the metabox field names should therefore be stable and prefixed (`_demie_*`).
