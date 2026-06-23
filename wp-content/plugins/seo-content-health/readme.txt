=== Content Health SEO ===
Contributors: yourname
Tags: seo, meta description, alt text, image optimization, webp
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A unified Content Health Score that combines SEO meta fields and image optimization into one number — instead of treating them as separate plugins.

== Description ==

Most SEO plugins score your meta title and description, then leave image alt text and image file size as a completely separate, manual chore handled by other tools. Content Health SEO combines all four into **one Content Health Score (0-100)** per post:

* SEO Title (custom, with ideal-length guidance and a live Google-style snippet preview)
* Meta Description (custom, with ideal-length guidance)
* Image Alt Text (bulk-fix missing alt text with one-click smart suggestions)
* Image Optimization (auto-compresses uploads, generates WebP copies, and can serve them automatically to supporting browsers)

The score appears as a colored badge in your post list, on the post edit screen, and as a site-wide average on a dedicated dashboard widget — so you can see content quality at a glance instead of jumping between tools.

AI-assisted suggestions (for alt text and meta fields) are available as an **optional** feature if you provide your own Anthropic API key in Settings. Without a key, the plugin uses fast, built-in filename/context-based suggestions — no external dependency required.

== Installation ==

1. Upload the `seo-content-health` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Content Health → Settings** to configure title/description length targets, image quality, and (optionally) AI assist.
4. Edit any post/page to set its SEO Title and Meta Description; check **Content Health → Image Alt Text** and **Image Optimizer** to clean up your media library.

== Frequently Asked Questions ==

= Does this replace Yoast/Rank Math? =
It covers meta titles, meta descriptions, and basic OG tags, plus image alt text and optimization — which most SEO plugins don't combine. If you need advanced schema/structured data, you can run this alongside another SEO plugin (disable duplicate title/description fields to avoid conflicts).

= Do I need an API key? =
No. AI assist is entirely optional. The plugin works fully offline using filename- and context-based suggestions.

= Which image formats are optimized? =
JPEG and PNG uploads are compressed and given a WebP sibling. SVG and GIF are left untouched.

== Changelog ==

= 1.0.0 =
* Initial release: SEO meta fields, image alt text bulk tool, image optimizer, unified Content Health Score, optional AI assist.
