# Bindr

Turn PDFs from your WordPress Media Library into interactive flipbooks —
self-hosted, privacy-friendly, no external services, no accounts.

Bindr renders PDFs with [PDF.js](https://github.com/mozilla/pdf.js) and a
realistic page-turn effect ([StPageFlip](https://github.com/Nodlik/StPageFlip)),
entirely from your own hosting. The PDF, the viewer, and all reading
statistics stay on your server.

## Features

- **Create** flipbooks from any Media Library PDF — no conversion, no re-upload.
- **Embed** with the `Bindr Flipbook` Gutenberg block or the `[bindr id="123"]`
  shortcode (block and shortcode share one server render path).
- **Full-page reading mode** at a clean shareable URL, served by the plugin's
  own blank-canvas template with a smart back button and social meta.
- **Local analytics**: reads, unique readers, completion rate, downloads —
  dashboard with chart, per-book stats, CSV export. No cookies, no PII;
  readers are counted with a salted hash that rotates daily. Raw events are
  pruned automatically after a configurable retention window.
- **Any theme**: the embedded viewer is style-isolated behind a scoped CSS
  reset with `--bindr-*` custom properties for intentional theming.
- **Responsive & accessible**: book spreads on desktop, single-page swipe on
  phones, full keyboard navigation, screen-reader labels, honors
  `prefers-reduced-motion`.
- **Light on shared hosting**: zero assets on pages without flipbooks, at
  most 6 rendered canvases in memory regardless of book length.
- Ships with a complete Bahasa Indonesia translation.

## Requirements

- WordPress ≥ 6.0
- PHP ≥ 7.4
- MySQL ≥ 5.7 / MariaDB equivalent

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the development setup, build
commands, and pull request guidelines. Bundled third-party libraries are
pinned and documented in [LIBRARIES.md](LIBRARIES.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
