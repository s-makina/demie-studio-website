# Demie Photography — WordPress Theme Usage

Theme folder: `demie-photography-theme/` · Deliverable: `demie-photography-theme.zip` (46.8 MB)

## Install on the live site

1. WP Admin → **Appearance → Themes → Add New → Upload Theme** → choose `demie-photography-theme.zip` → **Install** → **Activate**.
   - If the upload is rejected for size (46.8 MB exceeds some hosts' `upload_max_filesize`), either upload the extracted folder via FTP/cPanel File Manager to `wp-content/themes/` and activate, or ask the host to raise the limit.
2. **Pages → Add New** and create these pages (titles can differ, slugs matter for the fallback menu):
   | Title | Slug | Page template (Attributes → Template) |
   | --- | --- | --- |
   | Home | `home` | — (leave default) |
   | Gallery | `gallery` | **Gallery** |
   | About Us | `about-us` | **About Us** |
   | Services | `services` | **Services** |
   | Blog | `blog` | **Blog** |
   | Contact | `contact` | **Contact** |
3. **Settings → Reading**: *Your homepage displays* → **A static page** → Homepage: `Home`.
4. **Settings → Reading**: *Your homepage displays* → Posts page: `Blog` (optional; the Blog page template already lists posts).
5. **Appearance → Menus**: create a menu, add your pages, assign to **Primary Navigation**. Also create one for **Footer Links (Left/Right)** if you want custom footer links — otherwise the theme auto-fills sensible defaults.
6. **Appearance → Customize → Site Identity**: optionally upload a logo (the theme shows the "Demie Photography" wordmark until you do). Set the **Site Icon** (favicon) here too.

## How content works

- **Homepage**: fully built by `front-page.php` — slider, services, about, portfolio grid, counters, testimonials, latest posts (live WP loop), contact form, Instagram strip. Edit text in `front-page.php`.
- **Gallery**: shows posts from the **gallery category** (12 latest) in the masonry grid; until you publish gallery posts it shows the template's placeholder tiles. Add posts with featured images, assign the `gallery` category.
- **Blog**: normal WP posts render through `page-blog.php` (grid) and `single.php` (article page).
- **About**: intro section editable from the WP editor (Page → Edit); FAQ + form are in `page-about-us.php`.
- **Contact form**: AJAX post to `admin-ajax.php`, sanitized, mailed via `wp_mail` to **demiestudios@gmail.com**. Deliverability depends on the host's mail — install an SMTP plugin (e.g. WP Mail SMTP) and point it at the studio's mailbox for reliable delivery. Change the recipient in `functions.php` (`demie_email()`).
- **WhatsApp button**: floats bottom-right on every page (`wa.me/265884444802`).

## Local preview

A copy is already installed in the local WP at `C:\xampp\htdocs\other\demie-studio-wp` and activated (pages created, front page set): <http://localhost/other/demie-studio-wp/>

## Files of interest

```
functions.php              setup, menus, asset enqueues, brand helpers
inc/contact.php            AJAX form handler + shared form markup
template-parts/page-titlebar.php
front-page.php             homepage sections
page-about-us.php / page-services.php / page-gallery.php / page-blog.php / page-contact.php
header.php / footer.php    preloader, menus, search modal, footer, WhatsApp float
assets/css/brand.css       brand overrides (logo wordmark, WhatsApp button)
```
