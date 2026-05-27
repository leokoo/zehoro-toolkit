# Changelog

All notable changes to the **Leokoo Site Toolkit** will be documented in this file.

## [1.6.0] - 2026-05-27

### Added
- **Home Filter Pills module** (`HomeFilterPills.php`, slug `home_filter_pills`) — new shortcode `[lkst_home_filter_pills]` that renders a cross-CPT navigation pill bar. Each pill is a real anchor that navigates to a category or CPT archive (clean SEO URL, shareable, no JS, no AJAX, no custom taxonomy required). Site-specific destinations are configured via the `lkst/home_filter_pills/items` filter so the module stays generic across sites.
  - Pill item shape: `[ 'label' => ..., 'url' => ..., 'count' => int|array|null ]`
  - Count spec supports three resolver types: `category` (by slug), `tax` (any taxonomy by slug), `cpt` (post type publish count)
  - Active state derived from current URL path (with `aria-current="page"` for screen readers)
  - Two visual schemes via shortcode attr: `scheme="dark"` (default, white-on-dark) or `scheme="light"` (dark-text-on-cream for use on light sections)
  - Reuses `.lkst-cat-pill` shape from `CategoryPills.php`, adds `.lkst-cat-pills--light` and `.is-active` rules to `assets/style.css`
- **`.is-active` state styling** (`assets/style.css`) for `.lkst-cat-pill` — navy bg + cream text. Used by both modules.
- **`.lkst-cat-pills--light` variant** (`assets/style.css`) — cream-bg pill style for sections with light backgrounds. Uses CSS `color-mix()` so the pill auto-tracks the site's `--lkst-primary-contrast` and `--lkst-bg-light` settings.

### Rationale
Replaces an ill-fitting Bricks `filter-radio` + AJAX pattern that had been deployed on leokoo.com home page (v1.9 of that site). The Bricks filter required a custom taxonomy + backfill across all existing posts + a `save_post` auto-tagger + `?brx_lkflr=` URL pollution + DOM-order surgery (filter must render after loop, see Bricks build-system gotcha G32) + ~80 lines of CSS to hide native radio inputs. Replaced with this anchor-based pattern (matches FGB Malaysia's `fgb_category_pills` shape and the standard WordPress archive-navigation idiom).

## [1.5.2] - 2026-05-23

### Fixed (footgun removal)
- **Author Box CTA defaults** — previous default URLs (`/blog/` for "Read the articles" and `#newsletter` for "Get the newsletter") silently rendered broken links on any site that didn't set the options. Defaults are now **empty** — both buttons hide unless the site owner explicitly configures them via:
  - **wp_options**: `lkst_cta_primary_url`, `lkst_cta_secondary_url` (set via `update_option()` or wp-cli)
  - **filters**: `lkst/author_box/cta_primary`, `lkst/author_box/cta_secondary`
- Default primary CTA label updated from `Read the articles` → `Read more articles` (more conventional phrasing).

### Migration notes
- Sites that **were relying on the default `/blog/` button**: that URL was always broken on sites whose post archive isn't literally `/blog/`. The button now hides instead of misleading. To restore: set `lkst_cta_primary_url` to your actual archive URL (often `/news/`, `/articles/`, or whatever the site uses).
- Sites that **set the option themselves**: no impact — your saved value is honoured.

## [1.5.1] - 2026-05-23

### Changed (visual — heads up if you rely on the legacy badge look)
- **Last Updated badge — unstyled by default.** The `[lkst_last_updated]` shortcode previously rendered with hard-coded inline styles (small uppercase pill, cream background, dark text). That meant the badge looked the same no matter where it was placed and was extremely difficult to override from a theme or builder context. The default output is now an unstyled `<span class="lkst-last-updated">Updated: <time>...</time></span>` that inherits surrounding typography.
- **Pill look is now opt-in.** Pass `variant="pill"` to restore the legacy editorial-pill styling: `[lkst_last_updated variant="pill"]`. Or add the `.lkst-last-updated--pill` modifier class manually in custom markup. The stylesheet still ships the pill rules, just under the new modifier class.
- **`label` attribute** added — `[lkst_last_updated label="Last edited:"]` to customise the prefix. Pass an empty string to omit the label entirely (just the date).
- **Markup is now semantic** — wrapper changed from `<div>` to `<span>` (inline), and the date is wrapped in a `<time datetime="ISO-8601">` element for screen readers and structured-data crawlers.
- **`lkst-editorial-block` class removed from the wrapper.** It was misleading: the badge isn't an editorial block, it's a freshness signal. Sites that styled against this class should switch to `.lkst-last-updated` or `.lkst-last-updated--pill`.

### Migration notes
- Sites with **auto-inject enabled** will see the badge change from a styled pill to plain inline text at the top of single posts. To restore the pill: disable auto-inject in *Site Toolkit → Last Updated*, then place `[lkst_last_updated variant="pill"]` manually.
- Sites with **theme/builder overrides targeting `.lkst-last-updated { ... }`**: those rules now apply only to the unstyled default. To target the pill, switch your selector to `.lkst-last-updated--pill`.
- **No data migration is needed** — the change is purely presentational.

## [1.5.0] - 2026-05-22

### Added
- **CTA Swap module:** progressive-disclosure pattern for swapping CTA-button groups inline with hidden forms. Data-attribute API — `data-lkst-swap-group`, `data-lkst-swap-target`, `data-lkst-swap-back`. No shortcode, no PHP rendering — author marks up the buttons and form however they want, the module ships only the ~50-line vanilla-JS swap behaviour + minimal hidden-state CSS. Common pattern used by Substack, Mailchimp, ConvertKit etc. for newsletter signups, donation flows, multi-step CTAs. Disabled by default — enable via **Site Toolkit → Modules**.
  - ESC key closes an open swap
  - Focus is moved to the first focusable element in the form when opened
  - Focus is restored to the originating trigger when closed
  - Vanilla JS, zero dependencies, footer-loaded, respects `prefers-reduced-motion`

## [1.4.1] - 2026-05-22
### Added
- **Reading Time Bricks dynamic tag:** `{lkst_read_time}` is now registered as a Bricks dynamic data tag, mirroring the existing `[lkst_read_time]` shortcode. Use either inside a Bricks element setting to render the estimated reading time for the current post.

### Fixed
- **ReadingTime docblock:** Replaced an escaped apostrophe (`\'`) in the file header comment with a straight apostrophe — purely cosmetic, no behavioural change.

## [1.4.0] - 2026-05-13
### Added
- **Steps / Process Block:** SSR Gutenberg block for numbered how-to steps. Emits HowTo JSON-LD schema automatically (defers to SEO plugins when active).
- **Testimonial Block:** Static testimonial card with avatar, quote, name, role, and company. Three layout variants: card, minimal, highlight.
- **Stat Callout Block:** Large-number callout block for B2B/SaaS content. Supports centred, left-aligned, and highlighted-box layouts with optional source citation.
- **Inline Product Mention Block:** Compact horizontal product card for mid-content affiliate references. Image, name, one-liner, and CTA button with configurable `rel`.

### Changed
- **Build pipeline:** Migrated all four new block editors from vanilla JS (`wp.element.createElement`) to JSX via `@wordpress/scripts`. Source in `src/blocks/`; compiled output in `build/`. Legacy blocks (callout, pros/cons, tldr) are unaffected.

### Fixed
- **ArticleSchema:** Detects WP Review Pro (`MTS_WP_REVIEW_DB_TABLE`) and suppresses duplicate JSON-LD output when that plugin is active. Filterable via `lkst_article_schema_suppress_wp_review`.

## [1.3.1] - 2026-05-13
### Fixed
- **MODULES.md:** Corrected module reference — marked all four Stage 1 blocks as built.

## [1.3.0] - 2026-05-13
### Added
- **Article Schema Module:** Automatically outputs valid JSON-LD Article/BlogPosting schema for single posts, including author sameAs social links and dateModified signals.
- **Reading Progress Bar:** Added a lightweight, high-performance scroll progress bar at the top of the viewport.
- **Disclaimer Presets:** Refactored the Affiliate Disclosure module into a global Disclaimer module with standard presets for Medical and Legal sites.

## [1.2.1] - 2026-05-13
### Added
- **Plugin Update Checker:** Integrated the PUC library to enable native WordPress dashboard updates directly from GitHub.
- **Custom Block Category:** Registered a dedicated "Leokoo Site Toolkit" category in the Gutenberg editor to group all native toolkit blocks together.

### Fixed
- **TOC Regex:** Fixed an inverted regex capture group that was causing the Table of Contents anchor IDs to include unwanted quotation marks.
- **Block Assets:** Corrected the path mapping in `block.json` for the Callout and Pros & Cons blocks so they properly load their compiled assets in the editor.

## [1.2.0] - 2026-05-11
### Changed
- **Pro Refactoring:** Extracted resource-intensive features (Intelligent Content CTAs, CTA Admin, Inline Posts, Freshness Log, Pros/Cons Schema) and moved them to the new Leokoo Site Toolkit Pro add-on to maintain a lightweight core.

## [1.10.0] - 2026-05-06

### Added
- **Wirecutter-Style TOC:** A highly optimized, builder-agnostic Table of Contents module that automatically parses content and generates a sticky header with a mobile bottom-sheet dropdown.
- **Seamless TOC Marquee:** Long TOC headings are now elegantly constrained to a single line. If the text overflows the screen, it automatically triggers a continuous, seamless horizontal scrolling marquee.
- **TOC Settings Page:** Added a dedicated dashboard for the TOC to select supported post types and toggle between 'Auto-inject' or 'Shortcode Only' insertion methods.
- **Plugin Meta:** Added the Plugin URI (`https://leokoo.com`) to the WordPress plugins list.

### Changed
- **Typography Refinements:** Tuned TOC typography to strictly follow Wirecutter's hierarchy (12px bold label, 15px normal heading).

### Fixed
- **Layout Spacing:** Removed aggressive, hardcoded margins (`3rem`) from the Author Box and Content CTAs, allowing Bricks Builder (or any active theme) to naturally dictate structural spacing.
- **CTA Grid & Gaps:** Fixed the 200px image-width stretching bug in the Content CTAs and injected specific overrides to remove stubborn `15px` gaps generated by Fluent Forms.
- **Mobile Stacking:** Forced Content CTAs into a single-column layout on screens under 900px to prevent input field crushing.
- **Injection Guards:** Loosened overly strict WordPress loop guards that were preventing CTAs from rendering properly within the Bricks Builder DOM structure.


## [1.0.0] - 2026-05-06

### Added
- **Initial Release:** Complete architectural rewrite and rebranding from the legacy "Bricks Site Toolkit".
- **OOP Architecture:** Refactored all features into isolated, strictly-typed module classes under the `LK\SiteToolkit` namespace.
- **Builder Agnosticism:** Replaced all Bricks-specific `{echo:}` functions with standard WordPress shortcodes (e.g., `[lkst_read_time]`, `[lkst_post_nav]`).
- **Self-Contained Output:** Updated the News Ticker to output its own DOM wrappers (`.lkst-ticker`, `.lkst-ticker__wrap`), making it compatible with any builder or text area.
- **Flexible CTAs:** Content CTAs no longer strictly require a form shortcode to inject. A heading-only CTA will now successfully render.

### Fixed
- **Taxonomy Lock-in:** The "deepest match" category override logic now dynamically queries all hierarchical taxonomies attached to a post type (fixing custom post type compatibility).
- **Injection Safety:** Added strict `in_the_loop()` and `is_main_query()` guards to the CTA injection engine to prevent leaks into sidebars, widgets, or nested shortcodes.
- **Bottom CTA Math:** Fixed an edge case where the Bottom Power CTA would fail to calculate its correct injection position if the Middle CTA was disabled.
- **Data Integrity:** Rewrote the CTA settings parser to strictly allowlist valid keys, preventing internal logic flags (like `bottom_enabled`) from corrupting renderer data.

### Security
- **Sanitization:** Added strict, core-native sanitization callbacks (`sanitize_text_field`, `esc_url_raw`, `sanitize_hex_color`) to all dashboard options upon registration.
- **Error Handling:** Added `is_wp_error()` guards to all term lookups to prevent fatal crashes if a custom taxonomy goes offline.
