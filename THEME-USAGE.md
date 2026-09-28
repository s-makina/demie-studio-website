# Demie Photography — WordPress Theme Usage

Theme folder: `demie-photography-theme/` · Deliverable: `demie-photography-theme.zip` (44.8 MB) · Version 1.1.0

## Install on the live site

1. WP Admin → **Appearance → Themes → Add New → Upload Theme** → choose `demie-photography-theme.zip` → **Install** → **Activate**.
   - If the upload is rejected for size (44.8 MB exceeds some hosts' `upload_max_filesize`), either upload the extracted folder via FTP/cPanel File Manager to `wp-content/themes/` and activate, or ask the host to raise the limit.
2. On activation the theme **seeds itself automatically**: it creates the Home, About Us, Services, Gallery, Blog and Contact pages, fills every section with the current copy (services, testimonials, FAQs, slides, portfolio items), sets the front page and imports the studio's contact details. You normally do **not** need to create pages manually.
   - If you install on a site that already has pages with the same slugs (`home`, `about-us`, …), the seeder reuses them instead of duplicating.
3. **Appearance → Menus**: create a menu, add your pages, assign to **Primary Navigation**. Until a menu is assigned, the theme auto-builds one from the seeded pages.
4. **Appearance → Customize → Site Identity**: optionally upload a logo (the theme shows the "Demie Photography" wordmark until you do). Set the **Site Icon** (favicon) here too.

## How content works — edit in wp-admin, no code

The whole site is managed from the dashboard. Nothing needs to be edited in files.

### Where each thing lives

| Content | Where to edit it |
| --- | --- |
| Phone, email, studio address, social links | **Settings → Demie Settings** |
| Homepage slider slides (title, subtitle, background image) | **Slides** (admin menu) |
| Services (name, short blurb, full description, icon) | **Services** (admin menu) |
| Portfolio items (Gallery page + homepage grid) | **Portfolio Items** (admin menu) |
| Testimonials (quote, author, location, stars, photo) | **Testimonials** (admin menu) |
| FAQs (About page accordion) | **FAQs** (admin menu) |
| Homepage prose, stats, counters, section headings | **Pages → Home → "Demie Home Sections" box** |
| Page headings (label + big two-tone title) on About/Services/Contact/Gallery and any page or post | **"Demie Page Headings" box** on that page/post |
| About & Services body text | The normal page editor (Page → Edit) |
| Blog posts | **Posts** |

**Ordering:** within Slides, Services, Portfolio Items, Testimonials and FAQs, use **Page Attributes → Order** (or drag rows in quick edit) — lower numbers show first. The homepage shows the first **4** services; the Services page shows **all** of them. Empty collections simply hide their section on the site.

### Swapping a service icon (icon picker guide)

Service icons appear on the homepage cards and the Services page. Each service's icon **is its featured image**, so swapping it is a featured-image change:

1. **Services →** open the service (e.g. *Wedding Photography*).
2. Scroll to the **Featured Image** box (right column) and click **Set featured image** (or click the current icon's **Remove** first).
3. Upload your new icon — **SVG is allowed for administrators**, PNG/SVG both work. Square graphics around **106×106 px** look best; transparent background recommended. Use the bundled `assets/img/services/icon-1.svg` … `icon-20.svg` as ready-made alternatives if you just want a different template icon.
4. Click **Set featured image**, then **Update** the service. The icon changes everywhere that service appears (homepage and Services page together — they are one entry).

> The bundled template icons are only fallbacks. Once a service has its own featured image, that image always wins — the seeder will never overwrite an icon you chose.

The same featured-image mechanic drives testimonial photos, slide backgrounds and portfolio images.

### The email the contact form sends to

The form mails via `wp_mail` to the **Email** in **Settings → Demie Settings**. Deliverability depends on the host's mail — install an SMTP plugin (e.g. WP Mail SMTP) and point it at the studio's mailbox for reliable delivery.

## Local preview

A copy is already installed in the local WP at `C:\xampp\htdocs\other\demie-studio-wp` and activated (pages, content, icons and settings seeded): <http://localhost/other/demie-studio-wp/>

## Files of interest (for developers)

```
functions.php              setup, menus, enqueues, version (DEMIE_VERSION)
inc/settings.php           Demie Settings page (Studio Details) + social helpers
inc/cpts.php               5 custom post types + SVG upload allowance + admin columns
inc/metaboxes.php          metabox field framework + Home Sections / Page Headings / Service / Testimonial / Slide fields
inc/template-tags.php      read helpers used by templates (getters, heading rendering)
inc/seed.php               version-guarded auto-seed (pages, CPT content, settings, icons)
inc/contact.php            AJAX form handler + shared form markup
template-parts/page-titlebar.php
front-page.php             homepage sections (all dynamic)
page-about-us.php / page-services.php / page-gallery.php / page-blog.php / page-contact.php
page.php / single.php      generic page + single post (optional custom headings)
header.php / footer.php    preloader, menus, search modal, footer, WhatsApp float
assets/css/brand.css       brand overrides (logo wordmark, WhatsApp button)
```
