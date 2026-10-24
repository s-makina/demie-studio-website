# Mobile Hero Frame & Content Padding — Design

**Date:** 2026-10-24
**Scope:** `website/demie-v2` homepage hero only. Desktop composition above 1280px is unchanged.

## Problem

At phone widths the hero's inset frame line sits 20px from the viewport while the
content uses 24px side padding, leaving only a 4px gutter between the frame and
the eyebrow rules, headline, copy and CTAs. The frame feels like it is touching
the content. On short phones the frame bottom drifts below the fold, and at
768–1023px the headline crosses the frame line because the frame drops to a
100px inset while the content still spans the tablet width.

## Decisions

- Keep the existing rectangular frame, ring image and 92px top alignment with
  the fixed header.
- Adopt a balanced inset on phones rather than removing or restyling the frame.
- Leave the desktop frame at its current 100px inset from 1280px up.
- Tie the frame and content padding to CSS custom properties on `#home` so the
  spacing stays in one place.

## Values

| Token | < 768px | ≥ 768px |
| --- | --- | --- |
| Frame top | 92px | 95px |
| Frame side inset | 14px + safe area | `clamp(48px, 8vw, 100px)` + safe area |
| Frame bottom inset | 14px + safe area | 30px + safe area |
| Content side padding | 32px + safe area | 24px + safe area |

- The mobile result is an 18px gutter between the frame and all content.
- Headline size below 640px becomes `clamp(2rem, 9vw, 2.25rem)`, preserving the
  three-line shape at 320px and stepping back to 36px above 400px.
- The scroll cue bottom offset includes the bottom safe area.
- The tablet frame inset becomes fluid so the 72px headline keeps clearance at
  768px without changing the 1280px desktop frame.

## Short phones

For `max-height: 700px` and `max-width: 767.98px`:

- Section padding changes from `6rem 0 5rem` to `5.5rem 0 2.5rem`.
- The hero content top margin changes from 48px to 24px.
- The scroll cue stays hidden.

This lets the frame bottom land inside a 640px-tall viewport on 360×640-class
phones. The 320×568 case may still scroll, which is acceptable at that size.

## Files

- `template-parts/hero.php` — add `hero-content` and `hero-scroll-cue` hooks.
- `assets/css/v2.css` — frame/content variables, fluid headline, short-height
  and safe-area rules.
- `functions.php` — cache-busting theme version bump via the deploy script.

## Verification

Headless Chrome at 320×568, 360×640, 390×844, 414×896, 768×1024, 1024×768 and
1280×800:

1. Every phone width shows at least an 18px gutter from the frame line to text,
   eyebrow rules and CTAs.
2. No horizontal overflow.
3. The 768px headline clears the frame line.
4. The 1280px desktop frame and content positions are unchanged.
5. The 360×640 frame bottom is visible without scrolling.
