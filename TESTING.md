# Testing

## Automated smoke run — 2026-07-07 (WordPress 6.8.2, PHP 8.2, MariaDB 10.11, headless Chromium)

Executed against a clean Docker WordPress with the built plugin installed:

* ✅ Activation creates `wp_bindr_events` / `wp_bindr_daily` and schedules the daily cron.
* ✅ Flipbook CPT + permalink; full-page mode serves the plugin template with og meta, real `<a>` back link, and deferred assets.
* ✅ Block registered (`bindr/flipbook`); block and shortcode produce identical viewer markup via the shared render callback.
* ✅ Viewer boots in a real browser: flip engine on desktop, slide engine at 390 px viewport, page controls + keyboard arrows work, zero console errors.
* ✅ REST ingestion: valid batch accepted (202), bad nonce rejected (403), unknown/invalid events dropped, page numbers clamped to the book's page count.
* ✅ `sendBeacon` batch fires on `visibilitychange: hidden`.
* ✅ Rollup: per-day aggregation correct (opens, uniques, completes, avg furthest page); events past retention pruned after aggregation; dashboard time-series merges rolled-up days with today's live events.
* ✅ id_ID: admin + viewer strings translated ("Unduh PDF"), plurals work ("5 halaman").
* ✅ No external HTTP requests from any plugin code (browser network audit: only same-origin + PDF.js internal blob URL).
* ✅ Debug log empty with `WP_DEBUG` on.
* ✅ Deactivate → reactivate lossless; uninstall keeps data by default and removes tables/posts/options when "delete data" is enabled.

Manual matrix below still needs a human pass on real themes/devices.

## Theme compatibility matrix

Test each cell for: **(a)** embedded block, **(b)** shortcode, **(c)**
full-page mode, **(d)** mobile viewport (≤ 767 px). Record pass/fail +
notes. Status values: ✅ pass · ⚠️ pass with notes · ❌ fail · ☐ not yet run.

| Theme | Block | Shortcode | Full-page | Mobile | Notes |
|---|---|---|---|---|---|
| Twenty Twenty-Five | ☐ | ☐ | ☐ | ☐ | |
| Twenty Twenty-One | ☐ | ☐ | ☐ | ☐ | |
| Astra | ☐ | ☐ | ☐ | ☐ | |
| GeneratePress | ☐ | ☐ | ☐ | ☐ | |
| Kadence | ☐ | ☐ | ☐ | ☐ | |
| OceanWP | ☐ | ☐ | ☐ | ☐ | |
| Hello Elementor (Elementor shortcode widget) | ☐ | ☐ | ☐ | ☐ | |
| Divi (shortcode module) | ☐ | ☐ | ☐ | ☐ | |
| RTL locale check (any theme, `ar` or `he`) | ☐ | ☐ | ☐ | ☐ | |

## Functional checklist

* [ ] Install → create flipbook → embed → full-page → analytics visible,
      mouse/touch only, no docs needed.
* [ ] PHP 7.4 and 8.2, `WP_DEBUG` on: no notices/warnings.
* [ ] Keyboard-only operation of every viewer control; visible focus.
* [ ] Screen-reader smoke test (VoiceOver/NVDA): viewer region announced
      with book title, buttons labelled.
* [ ] `prefers-reduced-motion: reduce` → no flip animation (instant page
      changes via the slide engine).
* [ ] No external HTTP requests in the browser network tab from plugin code.
* [ ] Deactivate → reactivate: flipbooks, settings, analytics intact.
* [ ] Uninstall with "delete data" off: tables/options remain. With it on:
      tables, options, and CPT posts removed.
* [ ] Event pruning: insert events older than retention, run
      `wp bindr rollup` (or wait for cron), verify old raw rows deleted and
      rollups present.
* [ ] CSV export opens in Excel with correct characters (UTF-8 BOM).
* [ ] id_ID: switch site language to Bahasa Indonesia, review every admin
      screen and the viewer UI.
* [ ] Embed inside a tab/accordion (hidden at load): viewer sizes itself
      correctly when revealed.
* [ ] 20 MB PDF over throttled 5 Mbps: first page visible ≤ 4 s
      (progressive render).
* [ ] Long book (200+ pages): browser memory stays flat while paging
      (≤ 6 live canvases — inspect via `window.Bindr`).

## Performance budgets

| Budget | Target | Actual (build 1.0.0) |
|---|---|---|
| Viewer JS bundle (excl. PDF.js) | ≤ 150 KB gz | ~15 KB gz (viewer 4.8 KB + page-flip 10.4 KB) |
| PDF.js loaded only when needed | required | registered, enqueued only on render |
| Live canvases | ≤ 6 | enforced in `PdfStore.evictOutside()` |
| Assets on pages without flipbooks | 0 | registered-not-enqueued |

## Release gate

* [ ] `composer lint` (PHPCS, WordPress ruleset): 0 errors.
* [ ] `npm run lint:js`: 0 errors.
* [ ] wp.org [plugin-check](https://wordpress.org/plugins/plugin-check/): clean.
* [ ] Matrix table above fully green.
