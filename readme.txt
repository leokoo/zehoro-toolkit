=== Zehoro Toolkit ===
Contributors: leokoo
Tags: table of contents, faq, schema, author box, structured data
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.22.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editorial toolkit for content sites — E-E-A-T Article schema, Table of Contents, FAQ, author boxes, and content blocks. Coexists with your SEO plugin.

== Description ==

**Zehoro Toolkit** is a lightweight editorial toolkit for content-driven WordPress sites. It adds the on-page building blocks that help readers — and search engines, and AI answer engines — understand your content, without taking over your SEO setup.

It is **plug-and-play**: no API keys, no external accounts, no heavy configuration. Activate it and the blocks and schema are available immediately.

= What you get =

* **Table of Contents** — automatic, accessible, generated from your headings.
* **FAQ block** — renders an accordion *and* the matching FAQ structured data.
* **Article schema (E-E-A-T)** — Article / author / publisher JSON-LD that reflects real authorship and freshness signals.
* **Author boxes** — surface the human behind the post (an E-E-A-T signal that AI answer engines increasingly weigh).
* **Editorial content blocks** — Callout, Pros/Cons, Comparison Table, Review Box and more, for review- and guide-style content.

= Works *alongside* your SEO plugin =

Zehoro is **not** an SEO plugin and does not try to be one. It deliberately does not touch meta titles, sitemaps, canonicals, or robots — leave those to Yoast, Rank Math, SEOPress, AIOSEO, SureRank, Slim SEO, or whichever you run.

The one place the two could overlap is structured data (schema). Zehoro detects an active SEO plugin and, by default, **pauses its own schema output** so you never get duplicate markup. Prefer Zehoro's schema? One click in the settings switches it back on. You stay in control.

= Part of a bigger picture =

Zehoro Toolkit is the free base for **Zehoro Toolkit Pro**, which adds the content-to-revenue loop — Google Search Console–fed recommendations (what to fix next), entity / internal-linking intelligence, conversion tracking, and content ROI. The free toolkit is fully useful on its own; Pro is optional.

== Installation ==

1. In your WordPress admin, go to **Plugins → Add New**, search for "Zehoro Toolkit", and click **Install Now**.
2. Activate the plugin.
3. (Optional) Visit **Zehoro → Start Here** to see the available modules. Everything works with default settings — there is nothing you must configure.

That's it. No keys, no accounts.

== Frequently Asked Questions ==

= Does this conflict with my SEO plugin (Yoast / Rank Math / etc.)? =

No. Zehoro doesn't manage titles, sitemaps, canonicals, or robots, so there's no overlap there. For structured data, Zehoro detects your SEO plugin and stands its own schema down by default to avoid duplicate markup. You can override that in the Zehoro settings if you'd rather Zehoro emit the schema.

= Do I need an API key or an account? =

No. The free toolkit is entirely self-contained and plug-and-play.

= Does it send my data anywhere? =

No. The free plugin does not phone home or transmit your content. (The optional Pro add-on connects only to services *you* configure, such as your own Google Search Console — never without your action.)

= Will it slow my site down? =

It's built to be light: front-end assets load only on pages that actually use a Zehoro block or feature.

= Is it compatible with the block editor and page builders? =

The content features are native Gutenberg blocks. Page builders can use the shortcode / auto-injected equivalents where applicable.

== Screenshots ==

1. The Start Here screen — every available module at a glance.
2. Table of Contents rendered on a post.
3. The FAQ block (accordion plus structured data).
4. SEO-plugin coexistence: Zehoro detects your SEO plugin and stands its schema down, with a one-click override.

== Changelog ==

= 1.22.1 =
* Housekeeping for public distribution: added the GPLv2-or-later license header and the full license text, and guarded the GitHub auto-updater so it cleanly no-ops in repository builds (WordPress.org serves updates). No change to the plugin's features.

= 1.22.0 =
* Added one canonical SEO-plugin detector and **coexist-by-default** behaviour: when a dedicated SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO, SureRank, Slim SEO, The SEO Framework, Squirrly, Schema Pro) is active, Zehoro pauses its own schema to avoid duplicate markup. A new admin notice names the detected plugin and offers a one-click "Use Zehoro's schema instead" override.
* Internationalization: text-domain loading aligned for translation; bundled translation template.

== Upgrade Notice ==

= 1.22.1 =
Licensing and distribution housekeeping. No feature changes.
