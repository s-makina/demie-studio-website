# Slotfy Booking Integration — Design & Implementation Plan

**Date:** 2026-10-22
**Status:** Approved for implementation
**Guide:** `C:\xampp\htdocs\saas\slotfy\docs\integration\embed-widget-guide.md`

## Goal

Put the Slotfy online booking flow on the Demie Photography website with a
dedicated `/booking` page, dark/gold styling that matches demie-v2, and
tenant/origin settings editable in the WordPress admin.

## Scope

In scope:

- Dedicated Booking page template embedding `https://slotfy.app/widget.js`.
- Demie Settings section for enable toggle, tenant slug, and Slotfy base URL.
- All "Book" calls to action route to `/booking`.
- Payment return banner at `/booking?payment=return`.
- Graceful fallback when booking is disabled, misconfigured, or the widget errors.
- Seed the Booking page and menu entry; update the booking FAQ copy.
- Fix the stale dev-theme path in `website/deploy_theme.py`.

Out of scope:

- Per-service deep links (`data-service-id`).
- Two-way synchronisation of WordPress services with Slotfy services.
- Payment gateway configuration on the Slotfy side.
- Shortcode/block for embedding the widget in arbitrary content.

## Slotfy fixes required by this integration

Verification against the local Slotfy build exposed two defects that prevent the
widget from rendering at all. Both are fixed as part of this work:

1. `GET /api/settings/booking` was not `@Public()`. The embed calls it without
   a token; the 401 triggered the global client interceptor, which redirected
   the iframe to `/login`.
2. The embed's provider step, service-details fetch, and availability calls
   passed an empty tenant slug, so the backend received no tenant context and
   returned 500/empty results. They now pass the `tenantSlug` from the route.

Files: `slotfy/backend/src/settings/settings.controller.ts` and
`slotfy/frontend/src/app/embed/[tenantSlug]/booking/page.tsx`.

## Prerequisites (Slotfy side)

- Tenant slug: `demie-photography` (currently exists, status `trial`).
- Public services with providers that have working hours.
- Currency, timezone, and payment gateway configured if deposits/fees apply.
- Because the widget has no category filter, only services that should be
  bookable from the website should be public in Slotfy. The tenant currently
  exposes 15 services while WordPress lists 6; curation is an owner task.

## Architecture

### Configuration

Add three keys to the existing `demie_settings` option:

| Key | Default | Notes |
| :--- | :--- | :--- |
| `slotfy_enabled` | `1` | Booking page renders the widget |
| `slotfy_tenant` | `demie-photography` | Sanitised as a slug |
| `slotfy_base_url` | `https://slotfy.app` | Validated `http(s)` URL, trailing slash trimmed |

The payment return URL is derived from the Booking page permalink, not stored,
so it is correct on localhost and production without extra configuration.

### Rendering

`inc/booking.php` owns all Slotfy knowledge:

- `demie_slotfy_settings()` — normalised settings array.
- `demie_slotfy_enabled()` — enabled flag, non-empty slug, valid base URL.
- `demie_slotfy_booking_url()` — permalink of the `booking` page (home fallback).
- `demie_render_slotfy_widget()` — prints the container div and script tag with
  escaped `data-*` attributes, or returns false when not configured.

`page-booking.php` (Template Name: Booking) composes:

1. Shared page titlebar ("Book a Session").
2. Optional payment-return banner when `$_GET['payment'] === 'return'`.
3. Charcoal section containing the widget.
4. Fallback panel with phone, WhatsApp, and contact links when the widget
   cannot be rendered.
5. A small inline script that reveals a "having trouble" note on
   `slotfy:error` (progressive enhancement; no dependency on Slotfy's API).

### Styling

The widget is themed via attributes, so it paints correctly on first load:

| Attribute | Value |
| :--- | :--- |
| `data-theme` | `dark` |
| `data-bg` | `#151515` |
| `data-surface-1` | `#1E1C1A` |
| `data-surface-2` | `#252220` |
| `data-surface-3` | `#2E2A26` |
| `data-text-primary` | `#FDFBF7` |
| `data-text-secondary` | `#B9B2A7` |
| `data-border` | `rgba(255,255,255,0.08)` |
| `data-border-strong` | `rgba(255,255,255,0.14)` |
| `data-primary-color` | `#8A6A3C` (4.99:1 with white button text) |
| `data-primary-dark` | `#6F542E` |
| `data-primary-light` | `rgba(197,168,128,0.14)` |
| `data-accent` | `#C5A880` |
| `data-accent-2` | `#D9C8B4` |
| `data-radius` | `12px` |
| `data-font` | `"Plus Jakarta Sans", sans-serif` |
| `data-return-url` | `home_url('/booking/?payment=return')` |

The surrounding section uses the same `#151515` background so auto-resize
height changes are not visible against the page.

### Navigation

- `header.php`: desktop and mobile "Book Your Date" → booking page.
- `template-parts/hero.php`: "Start Your Booking" → booking page.
- `functions.php`: Booking added to the fallback and mobile-fallback menus.
- `inc/seed.php`: Booking page created with the template and appended to the
  primary menu if missing.
- `template-parts/booking.php`: a "Book online now" button added above the
  contact form; the `#booking` anchor is preserved for existing links.

## Error handling

| Condition | Behaviour |
| :--- | :--- |
| Booking disabled | Fallback panel, no script emitted |
| Slug empty or base URL invalid | Fallback panel, no script emitted |
| Slotfy script/iframe error | Inline listener reveals the call-us note |
| Payment returns without config | Banner still shows; confirmation email remains the source of truth |

No CSP exists on the site. The iframe must not be sandboxed; checkout
navigates the top window.

## Files

| File | Change |
| :--- | :--- |
| `website/demie-v2/inc/booking.php` | New renderer + settings accessors |
| `website/demie-v2/inc/settings.php` | Booking settings section + sanitisation |
| `website/demie-v2/page-booking.php` | New Booking template |
| `website/demie-v2/functions.php` | Require, menus, version bump |
| `website/demie-v2/inc/seed.php` | Booking page, menu item, FAQ copy |
| `website/demie-v2/header.php` | CTA target |
| `website/demie-v2/template-parts/hero.php` | CTA target |
| `website/demie-v2/template-parts/booking.php` | Online booking button |
| `website/deploy_theme.py` | Correct dev theme path |

## Verification

1. `php -l` every changed PHP file.
2. Run the seed through a WP-bootstrapped CLI invocation.
3. `GET /booking` → 200, contains `widget.js`, correct tenant slug, base URL,
   and theme attributes.
4. `GET /booking?payment=return` → confirmation banner present.
5. Disable booking in settings → fallback panel rendered, no script tag.
6. Grep CTAs to confirm `/booking` targets.
7. Confirm `http://localhost:3000/embed/demie-photography/booking` and
   `http://localhost:3000/widget.js` respond (already verified).
8. Sync the theme to the live WordPress install.
