# Architecture decisions

## Viewer isolation: scoped reset, not Shadow DOM

**Decision: scoped-reset CSS, no Shadow DOM.**

What was weighed:

* **Fullscreen API**: `requestFullscreen()` on a host element works, but the
  browser's fullscreen backdrop/stacking behaves inconsistently for shadow
  descendants in older Safari (< 16.4), still common on managed desktops.
* **PDF.js canvas sizing**: canvases render fine in a shadow root, but
  `getBoundingClientRect`-based measurement (which StPageFlip does
  internally on `window.resize`) reads fine too — this was not a blocker.
* **StPageFlip**: attaches document-level pointer listeners and looks up
  elements by class; not verified to pierce the shadow boundary for its
  swipe hit-testing in all cases. This *was* a blocker risk.
* **Focus management**: keyboard traversal across a shadow boundary works
  in modern browsers, but `:focus-visible` polyfilling and WP theme skip
  links interact badly.
* **WP script loader**: no first-class way to inject styles into a shadow
  root; we would have to fetch/inline our stylesheet manually, which
  breaks style-based customization by site owners (a design goal: theming
  via `--bindr-*` custom properties must be reachable from theme CSS).

The scoped reset (`all: revert` under `.bindr-viewer` + bindr-prefixed
classes + custom properties) achieves the needed isolation with none of
those risks, and lets site owners intentionally restyle the viewer from
their own CSS. Documented `!important` uses (each required to defeat theme
rules that also use `!important` or inline styles):

1. `.bindr-viewer--fs-fake { position: fixed !important; height: 100% !important; }`
   — fake-fullscreen must escape any transformed/positioned theme ancestor.
2. `templates/fullpage.php` inline style: `html, body { margin/padding
   !important }` and hiding non-plugin `body >` children — the full-page
   template shares the document with `wp_head()` output from arbitrary
   plugins/themes.

## Scoped reset refinements found in browser testing

Two real-world corrections to a naive
`.bindr-viewer, .bindr-viewer * { all: revert; }`:

1. **SVG icons vanish in Chromium.** SVG presentation attributes (`d`,
   `fill`, …) participate in the author cascade origin, and Chromium
   implements `d` as a CSS property — `all: revert` erases the icon path
   geometry while leaving the element in place. The reset therefore
   excludes `svg`/`path`, and toolbar icons re-assert
   `fill: currentColor` explicitly.
2. **StPageFlip's injected stylesheet loses the cascade.** The library
   injects `.stf__*` structural rules at runtime; depending on injection
   order the reset beats them and the book layout collapses. Its rules
   (pinned at 2.0.7) are re-asserted in our stylesheet scoped under
   `.bindr-viewer`, which outranks the reset deterministically. The reset
   itself is wrapped in `:where()` so it stays at the specificity of a
   single class and every later `.bindr-*` rule wins.

Verified headless (Chromium engine) against WordPress 6.8 + Twenty
Twenty-Five: book centered, spread layout correct, icons visible, zero
console errors, no external requests.

## Analytics nonce vs. page caching

The REST event endpoint requires a nonce minted into the viewer config at
render time. WordPress nonces live ~24 h, so pages cached longer serve an
expired token and their events are rejected with 403. Accepted trade-off
for v1: analytics silently degrade on long-cached pages rather than
weakening the endpoint. Documented in readme.txt FAQ; a refresh endpoint
is a future candidate.

## Session identity

`session_hash = wp_hash( ip | user-agent | UTC date )`, computed
server-side only, truncated to 32 chars. Rotates daily by construction, is
keyed with the site's auth salts, and is never derivable client-side.
Uniques are therefore *daily* uniques; cross-day visitor linking is
impossible by design (privacy > precision).

## Page count extraction

Client-side via PDF.js in the admin edit screen (hidden fields filled on
PDF selection), not server-side. Shared hosting frequently lacks
Imagick-with-PDF/Ghostscript; requiring it would exclude exactly the
low-resource hosts this plugin targets. If WordPress core generated a PDF
preview image on upload (it does when Imagick is available), it is reused
as the cover; otherwise covers render client-side.
