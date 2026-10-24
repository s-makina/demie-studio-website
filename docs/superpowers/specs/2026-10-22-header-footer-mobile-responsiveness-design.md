# Header & Footer Mobile Responsiveness — Design

**Date:** 2026-10-22
**Scope:** `website/demie-v2` only. Targeted responsive fixes, preserving the existing desktop design language.

## Problem

At phone widths the header wordmark wraps, the hero frame crosses it, the mobile menu cannot scroll and is too transparent, and the hamburger tap target is undersized. The footer has excessive top padding, a wrapping wordmark, a newsletter button that covers the email field at tablet widths, undersized social targets, and a left-crowded bottom bar.

## Decisions

- Fix the existing layouts; do not redesign the header or footer.
- Keep the full-screen menu overlay, but make it solid, scrollable and keyboard accessible.
- Reorder the footer on phones so brand + newsletter come first.
- Widen the footer centre column at tablet widths instead of keeping three cramped equal columns.

## Header

| Item | Behaviour |
| --- | --- |
| Wordmark | Fluid `clamp(1rem, 4.4vw, 1.1rem)` below 768px, `0.1em` tracking, `white-space: nowrap`. Never wraps at 320px. |
| Tagline | Visible from 360px up; hidden below 360px. |
| Menu button | Minimum 44×44px tap target; `aria-expanded` / `aria-controls`. |
| Hero frame | Top edge at 92px below 768px so it starts at the header's bottom edge. |
| Mobile menu | Solid `#121212`; z-index 45 (above WhatsApp at 30); scrollable with safe-area padding; `justify-content: safe center` so tight screens align to start; 48px tap rows; reduced type under 640px height. |
| Menu behaviour | Toggle class `.is-open`, `aria-hidden` + `inert` while closed, `Escape` closes, focus returns to the button, resize ≥1024px closes, body scroll locked via `body.menu-open`. |

## Footer

| Width | Layout |
| --- | --- |
| < 768px | One column, centred; centre column first; 80px top / 64px bottom padding; newsletter stacked; 80px bottom clearance for WhatsApp. |
| 768–1023px | 25% / 50% / 25%; centre content 1rem padding; newsletter stacked. |
| ≥ 1024px | Original 33/33/33; centre padding removed so the wordmark fits. |

- Wordmark: `clamp(1.25rem, 2.2vw, 1.875rem)`, `0.14em` tracking, `nowrap`; custom logo images capped at 100%.
- Newsletter: below 1200px the Subscribe button stacks full-width under the field; 48px/46px heights; 16px input font (prevents iOS zoom). Inline from 1200px.
- Nav links: 0.5rem vertical padding with 4px gaps, preserving visual rhythm while enlarging tap targets.
- Social links: 54×44px on phones.
- Bottom bar: centred column, balanced 14px copyright, no orphaned year.

## Verification

Headless Chrome at 320, 360, 390, 414, 768, 1024 and 1280px:

1. No horizontal overflow outside the Swiper track.
2. Wordmark stays on one line in header and footer.
3. Header height is consistent at 320–767px; hero frame clears it.
4. Menu is reachable and scrollable with eight links at 390×568.
5. Newsletter field has usable width at every breakpoint.
6. Footer social targets meet 44px height on phones.
