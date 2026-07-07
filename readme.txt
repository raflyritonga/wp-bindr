=== Bindr ===
Contributors: bindr
Tags: pdf, flipbook, viewer, magazine, document
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn Media Library PDFs into interactive flipbooks. Self-hosted, privacy-friendly, no external services, no accounts.

== Description ==

Bindr turns any PDF in your Media Library into an interactive flipbook with a realistic page-turn effect. Everything runs on your own hosting: the PDF, the viewer, and the reading statistics. No cloud service, no external accounts, no data ever leaves your server.

**Features**

* Create books from Media Library PDFs — no conversion step, no re-upload.
* Embed anywhere with the Bindr Book block or the `[bindr id="123"]` shortcode.
* Distraction-free full-page reading mode at a clean shareable URL, with a back button.
* Built-in privacy-friendly analytics: reads, unique readers, completion rate, downloads — with a dashboard, per-book statistics, and CSV export.
* Works with any theme: the viewer is fully style-isolated and the full-page mode uses its own template.
* Responsive: double-page spreads on desktop, single-page swipe on phones.
* Accessible: full keyboard navigation, screen-reader labels, honors reduced-motion preferences.
* Translation-ready; ships with a complete Bahasa Indonesia translation.
* Light on shared hosting: assets load only on pages with books, old analytics events are pruned automatically.

**Privacy**

No cookies. No personal data. Readers are counted with a salted hash that changes every day, so nobody can be tracked across days — and nothing is ever sent to a third party. This makes the plugin friendly to GDPR and similar privacy regulations out of the box.

== Installation ==

1. Install and activate the plugin.
2. Go to **Bindr → Add New**, give the book a title, and select a PDF from your Media Library.
3. Publish. Copy the shortcode from the "Use This Book" box, or add the **Bindr Book** block to any post or page.
4. Share the book’s own URL for full-page reading mode.

== Frequently Asked Questions ==

= Where are my PDFs and statistics stored? =

Entirely on your own hosting: PDFs stay in your Media Library, statistics live in two tables in your WordPress database. The plugin makes no external HTTP requests.

= Do I need Imagick or Ghostscript on my server? =

No. Page counting and previews happen in the browser via PDF.js. If your server can generate PDF thumbnails, they are used for covers automatically; otherwise the viewer renders the cover itself.

= Does it work with page builders like Elementor or Divi? =

Yes — use the shortcode `[bindr id="123"]` in any text/shortcode widget or module.

= I use a page-caching plugin and analytics look low. Why? =

The analytics token embedded in cached pages expires after about a day. If your cache lifetime is longer than 24 hours, some events are dropped. Reduce the cache TTL for pages containing books, or exclude book URLs from caching.

= Can readers download the original PDF? =

Only if you enable the download button — globally in Settings → Bindr, or per book.

= What happens to my data if I uninstall the plugin? =

By default everything is kept. If you tick "Delete all data on uninstall" in Settings → Bindr first, uninstalling removes books, settings, and analytics tables.

== Screenshots ==

1. A book embedded in a post.
2. Full-page reading mode.
3. Creating a book from a Media Library PDF.
4. The analytics dashboard.

== Changelog ==

= 1.0.0 =
* Initial release: book CPT, Gutenberg block + shortcode, full-page reading mode, local privacy-friendly analytics with CSV export, complete Bahasa Indonesia translation.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
