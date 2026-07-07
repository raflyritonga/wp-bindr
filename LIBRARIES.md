# Bundled third-party libraries

Both libraries are bundled locally in `build/vendor/` (no CDN loading, per
wordpress.org rules and for offline-friendly self-hosting). Versions are
pinned exactly in `package.json`; update deliberately and re-test the full
theme matrix in `TESTING.md` after any bump.

| Library | Version | License | Files shipped | Why this version |
|---|---|---|---|---|
| [PDF.js](https://github.com/mozilla/pdf.js) (`pdfjs-dist`) | **3.11.174** | Apache-2.0 | `pdf.min.js`, `pdf.worker.min.js` (legacy build) | Last major with a UMD build (`window.pdfjsLib`) — loadable through `wp_enqueue_script` on WP ≥ 6.0 without ES-module support, and the `legacy` build keeps older browsers working. 4.x is ESM-only. |
| [StPageFlip](https://github.com/Nodlik/StPageFlip) (`page-flip`) | **2.0.7** | MIT | `page-flip.browser.js` (UMD, `window.St.PageFlip`) | Latest published release. |

Regenerate `build/vendor/` with `npm run vendor` (runs `tools/copy-vendor.js`).
