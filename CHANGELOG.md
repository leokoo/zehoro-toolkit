# Changelog

All notable changes to the **Zehoro Toolkit** will be documented in this file.

## [1.24.2] - 2026-06-18

### Fixed — the email-capture box (Content Box `type="email"`, also the ContentStream "email" slot) was silently broken by an incomplete rename
- A half-finished `lkst_* → zehoro_*` rename left the email box's render, its inline JS, and its AJAX handler disagreeing — so the widget didn't work:
  - **The form never submitted.** The inline submit handler gated on `classList.contains('zehoro-box-email-form')`, but the form's class was `lkst-box-email-form` — so the guard always failed, `preventDefault()` never ran, and the form did a broken full-page reload instead of the AJAX submit. (Fixed the JS to match the form, keeping the widget's identifiers internally consistent with its `lkst-`-named nonce/action/message.)
  - **The download link was dropped.** `handle_submission()` read `$_POST['lkst_box_file_url']`, but the form field is `zehoro_box_file_url` — so the file URL passed to the delivery webhook was always empty.
  - **The per-shortcode webhook override was ignored.** Same mismatch on `zehoro_box_webhook` vs `lkst_box_webhook` — the per-article webhook was never read, so it always fell back to the global setting.
- The handler now reads the field names the form actually sends (with `lkst_box_*` fallbacks so any page cached before the rename keeps working). **+4 regression tests** (165 green) that pin the render↔JS↔handler contract: the form's class must match its JS submit hook, and its fields/nonce/action must match what the handler reads — so this class of rename-drift fails loudly next time.

## [1.24.1] - 2026-06-15

### Fixed — suite-card members now expose their settings link
- When a module is collapsed into a **suite card** (the Blocks / Schema / Reading & Trust grouping from 1.23.0), its row showed only a toggle — so the settings page for FAQ, Article Schema, Last Updated, Content Box, etc. was reachable **only by typing the URL**. Each suite-member row now shows a ⚙ settings link (when the module has a settings page), so nothing is buried. `collapse_suites()` carries `settings_link` into each member.

## [1.24.0] - 2026-06-15

### Added — Danger Zone: your data is safe on uninstall
- **Deleting (or temporarily uninstalling) the plugin no longer wipes your settings.** WordPress can't tell a permanent removal from a delete-then-reinstall, so Zehoro now **preserves all data by default** — a reinstall picks up exactly where you left off.
- New **Danger Zone** at the bottom of the dashboard with two controls: an opt-in checkbox — *"Delete all Zehoro data when I delete this plugin"* (off by default) — and an **"Erase all data now"** button (with a confirm) for an immediate clean wipe.
- Both run a shared `Maintenance\DataEraser` that clears every canonical `zehoro_*` and legacy `lkst_*` option, post meta, user meta, and transient, then flushes the object cache (so it's correct on Redis/Memcached sites too). `uninstall.php` now calls the same eraser — but only when the opt-in is set.

## [1.23.1] - 2026-06-15

### Fixed — uninstall completeness + a hardening (5-agent audit)
- **Uninstall now cleans canonical `zehoro_*` data, not just legacy `lkst_*`.** After the v1.7.0 rename the plugin writes `zehoro_*` keys, but `uninstall.php` still only removed the old `lkst_*` ones — so deleting the plugin orphaned ~30 options (plus the migration flag, which made a reinstall *skip* migration and resurrect stale state). `uninstall.php` is now driven off the rename migrator's authoritative key map and clears both prefixes' options, post meta, user meta, and transients.
- **Module-save (noscript path) hardened:** the form handler now `sanitize_key()`s the posted module slugs and intersects them against the real registry before saving (parity with the REST route) — nonce + capability were already enforced; this stops junk keys accumulating in `zehoro_active_modules`.
- De-branded a leftover demo placeholder ("Acme Corp" → "Acme Inc.") in the Testimonial block for naming consistency.

## [1.23.0] - 2026-06-15

### Added — module "suites" (the Kadence-Blocks model, for onboarding)
- The Modules grid now collapses the commodity groups into **single suite cards with sub-toggles inside**, so a new user meets ~15 cards instead of ~50. Three suites: **Blocks** (the 16 editorial blocks), **Schema**, and **Reading & Trust**. Each suite card has a **master toggle** (turn the whole set on/off at once, via the existing bulk route) and an **expandable list** to disable individual features — exactly how Kadence Blocks works. The master shows an *indeterminate* state when only some members are on.
- The spine modules (CTR Rescue, the entity layer, GSC diagnostics, etc.) stay as distinct cards — they are not co-activated and they are the differentiated features.
- `Dashboard::collapse_suites()` is the pure, unit-tested partition the grid renders from; suites are filterable via `zehoro/module_suites`.
- **Browser-verified** (Playwright): the master cascade, per-member toggle (master → indeterminate), regular-card toggles, search-by-block-name, and the left-nav counts all work and persist; zero JS errors.

## [1.22.2] - 2026-06-15

### Fixed — module grouping (onboarding)
- **Ten loop/intelligence modules were falling into the "Other" group** on the Modules grid because the slug→group map hadn't kept pace as the spine shipped. Grouped them: Cannibalisation, Refresh Trigger, Orphan Check, Topical Gap, Entity Index, DataForSEO and GA4 → **SEO**; AI Visibility + Rewrite-with-Context → **AI Assistance**; Edit Log → **Admin & Plumbing**. "Other" should now be empty (or close to it).

## [1.22.1] - 2026-06-14

### Added — licensing + wordpress.org distribution readiness
- **GPLv2-or-later license** declared in the plugin header (`License` + `License URI`) and the full canonical GPLv2 text shipped as `LICENSE` (the previous `LICENSE` was a 6-line stub — only the license *header*, missing the actual terms).
- **GitHub auto-updater now guarded with `file_exists()`** so it cleanly no-ops when the package ships without `vendor/`. A wordpress.org build (which must exclude the self-hosted updater per repo rules) drops in with **no source fork** — GitHub / self-hosted installs keep auto-updates exactly as before (`vendor/` present), while a wp.org build lets wordpress.org serve updates.
- **`.distignore`** — excludes dev tooling, tests, and `vendor/` (the updater) from a wordpress.org build, while keeping every runtime dir (`src/`, `build/`, `assets/`, `languages/`).
- **`readme.txt`** (wordpress.org format) — leads with SEO-plugin coexistence + plug-and-play (no API keys) positioning.

No functional change to any plugin feature.

## [1.22.0] - 2026-06-14

### Added — SEO-plugin coexistence
- **One canonical SEO-plugin detector** (`Zehoro\Compat\SeoPlugin`), replacing the per-module, inconsistent checks `ArticleSchema` and `FAQ` each carried (different plugin lists; one checked `SureRank\SureRank`, the other `SureRank\Core\SureRank`). Now recognises **Yoast, SEOPress, Rank Math, AIOSEO, SureRank, Slim SEO, The SEO Framework, Squirrly, Schema Pro** — extensible via the `zehoro/seo_plugins` filter.
- **Coexist by default:** when a dedicated SEO plugin is active, Zehoro pauses its own structured-data (schema) output to avoid duplicate markup — the principle that *Zehoro is a content-business toolkit, not an SEO plugin*: it owns the loop / entity / conversion features, never the SEO-output plumbing, and its one overlap (JSON-LD) yields to the specialist.
- **Visible + overridable:** a new admin notice (on Zehoro screens) names the detected plugin and offers a one-click **"Use Zehoro's schema instead"** (option `zehoro_schema_output` = `auto`/`always`/`never`) + a persistent dismiss. The editor schema meta box now names the plugin too. Dev override: the `zehoro/emit_schema` filter (and the legacy `zehoro_article_schema_force`, still honoured).

### Changed
- Regenerated `languages/zehoro-toolkit.pot` (169 strings) for the new copy.

## [1.21.2] - 2026-06-14

### Internationalization (translation-ready)
- **Fixed the one un-extractable string.** The module-grid category labels were translated through a variable (`__( $label, … )`), which i18n tooling can't extract — so they'd never be translatable. They now come from `Plugin::group_labels()`, which maps each group key to a **literal** `__()` call (falling back to the English label for any future key). Every user-facing string is now wrapped with the correct, literal `zehoro-toolkit` text domain (220 call sites verified).
- **Added the `Domain Path: /languages` header**, a bundled **`languages/zehoro-toolkit.pot`** (161 strings) for translators, and moved `load_plugin_textdomain()` to the `init` hook (avoids the WP 6.7+ "just-in-time textdomain" notice). On wordpress.org, translations are also served automatically from translate.wordpress.org.

## [1.21.1] - 2026-06-14

### Fixed
- **The GitHub-token lookup for auto-updates is now consistent with Pro.** The Free updater read the legacy `lkst_pro_github_token` option, while Pro reads/writes the canonical `zehoro_pro_github_token` — so a token set the canonical way left Free's updater unauthenticated (GitHub API rate-limit / private-repo update failures). Free now reads `zehoro_pro_github_token` first, falling back to the legacy `lkst_pro_github_token`. *(External review.)*

## [1.21.0] - 2026-06-14

### Added
- **`zehoro_landing` extension point** — the top-level Zehoro menu page now delegates to whoever hooks `zehoro_landing` (Pro's "Start Here" home claims it), falling back to the **Modules grid** when nothing does. So a Free-only install is unchanged; with Pro active, the landing becomes the Start-Here dashboard and the Modules grid moves to its own **Modules** submenu (its filter/REST assets follow it). `Dashboard::render_landing()` + a conditional menu in `register_menus()`.

## [1.20.2] - 2026-06-12

### Changed
- **Content Box folds into Content Stream for Pro users (IA pass).** When Content Stream is active, the Content Box card is hidden from the Modules grid — Stream owns box composition and keeps Content Box active as its renderer (so the injected forms can't break). Content Box's settings stay reachable from the Content Stream page.

## [1.20.1] - 2026-06-12

### Changed
- **Sidebar curation (IA pass).** The Zehoro admin sidebar now surfaces only daily-driver surfaces; everything else stays in the Modules grid (reachable via each card's *Configure* link). **Content Box** and **Disclaimer** settings moved out of the sidebar (Content Box is being folded into Content Stream for Pro users). No settings were removed — only the menu placement changed.

## [1.20.0] - 2026-06-12

### Added
- **Module cards now carry type + capability pills.** Each card shows a `Block` or `Tool` pill (plain behaviour modules stay unbadged to avoid badge-soup), an `AI` pill on modules that use a BYOK LLM (Rewrite with Context, AI Visibility, EntityMap), and a `GSC` pill on the Search-Console-fed modules (CTR Rescue, Cannibalisation, Refresh Trigger, Orphan Check). **Topical Gap deliberately carries no AI pill** — it's deterministic crawl + token-diff. The registry auto-derives `type`/`needs` from the slug (each overridable per module via `register_module()`), and the pills are part of the card search index. The existing `PRO` tier badge already covered tier, so the redundant "(Pro)" suffix was dropped from Pro module titles (Pro 1.47.1).

### Fixed
- **The Table of Contents rendered but wouldn't open** — clicking the bar did nothing. `toc.js` (the click-to-expand handler) was only enqueued when the `[zehoro_toc]` *shortcode* was present, so every auto-injected TOC loaded styled but inert (same bug class as the v1.19.0 stylesheet gate, one layer down). The script now loads whenever a TOC will actually render, via a shared `toc_will_render()` check used by both the style gate and the script enqueue so they can't disagree. (Enqueue moved out of `Plugin::enqueue_assets` into `TableOfContents`.)

## [1.19.1] - 2026-06-12

### Fixed
- **Mid-post CTA card rendered with an invisible heading** (cream text on white). The CTA wrapper had already been renamed to `zehoro-midpost-cta` but its child elements and *all* the CSS still used `lkst-midpost-cta*`, so the dark card background (which the cream heading depends on) never applied. Completed the rename — `lkst-midpost-cta*`, `lkst-cta-image*`, and the `lkst-sidebar-cta` modifier → `zehoro-*` — across `ContentBox.php` render and `style.css` in lockstep. (Surfaced by the v1.19.0 stylesheet-gate fix, which finally loaded the CSS that exposed the half-rename.)

## [1.19.0] - 2026-06-12

### Fixed
- **Table of Contents rendered unstyled** on posts where it auto-injects. The global stylesheet gate scans *stored* post content, so it never saw the runtime-injected TOC and skipped loading `style.css` + the CSS variables. The TOC now hooks the `zehoro/load_global_styles` filter and forces the stylesheet when a ≥2-entry TOC is actually coming.

### Changed
- **TOC markup renamed `lkst-toc-*` → `zehoro-toc-*`** (wrapper, header, title, list, items, depth classes, `data-zehoro-toc` attribute) across the rendered HTML, `style.css`, and `toc.js` in lockstep. The `[lkst_toc]` shortcode alias and `lkst_toc_settings` option fallback are unchanged, so existing posts keep working.

## [1.18.0] - 2026-06-10
- Live module counts, recommended setups, shortcode copy fixes

## [1.17.0] - 2026-06-10
- Bulk module toggles

## [1.16.0] - 2026-06-10
- Sidebar rename Site Toolkit → Zehoro + back-to-Modules links

## [1.15.1] - 2026-06-09
- Flat alphabetical grid (fix v1.14.0 redundant category sections)

## [1.15.0] - 2026-06-09
- Conditional enqueue gating (49 KB saved per page)

## [1.14.0] - 2026-06-09
- Modules page sidebar group nav + URL state

## [1.13.0] - 2026-06-09
- Per-module submenus hidden from Site Toolkit sidebar

## [1.12.0] - 2026-06-09
- REST toggle endpoint (no Save button)

## [1.11.0] - 2026-06-09
- Modules JS + CSS extracted to assets/

## [1.10.0] - 2026-06-09
- Module metadata: tier, group, order, keywords, badges

## [1.9.0] - 2026-06-09
- Modules page: category grouping

## [1.8.0] - 2026-06-09
- Search + status filter on Modules page

## [1.7.0] - 2026-06-08
- Full lkst → zehoro rename

## [1.6.1] - 2026-06-06

### Changed — renamed Leokoo Site Toolkit → Zehoro Toolkit
- Plugin renamed to **Zehoro Toolkit** (the free base for Zehoro Toolkit Pro). Namespace `LK\SiteToolkit\` → `Zehoro\`, constants `LKST_*` → `ZEHORO_*`, slug/folder/text-domain/block-category/handles/PUC → `zehoro-toolkit`, GitHub repo → `leokoo/zehoro-toolkit`.
- Filter `lkst_article_schema` → `zehoro_article_schema` (the old name still fires via `apply_filters_deprecated`).
- Removed 4 modules native WordPress core already provides / that add no value: **PostNav** (`core/post-navigation-link`), **ReadingTime** (`core/post-time-to-read`), **ReadingProgress**, **NewsTicker**.
- Stored `lkst_` data (options, post/user meta, shortcodes, CSS classes, `lkst/` theme filters) intentionally unchanged — no migration.

### Internal — test coverage backfill

- **AuthorBox: 3 → 21 tests** (`tests/integration/AuthorBoxTest.php`). Original 3 tests covered the v1.5.2 empty-default-URL regression on the primary CTA — `render_box`'s secondary-CTA path and the entire `render_socials` standalone shortcode (166 untested lines) were not covered. Expanded to add: secondary CTA empty-URL hide / configured render / filter override / independence from primary; identity rendering (tagline, bio with nl2br, chips 1–3 in source order, partial chips); in-box socials section markup; `[lkst_author_socials]` shortcode end-to-end (no URLs → empty, single platform, all four platforms with correct dashicon mapping, `esc_url` drops `javascript:` protocol, unconfigured platforms omitted).
- **TableOfContents: 0 → 21 tests** (`tests/integration/TableOfContentsTest.php`). Same regression class as ContentStream (Pro): `preg_replace_callback` over `the_content`, builder-preview short-circuits, structural injection point. Covers `sanitize_settings` (non-array fallback, unknown post types dropped, invalid `insertion` falls back to `auto`); auto-insertion (TOC prepended only when ≥ 2 headings, anchor IDs injected for headings without one, existing IDs preserved, h2 vs h3 depth class); shortcode-mode (`[lkst_toc]` placeholder replaced when valid, stripped when under 2 headings per the module's Bug 4 comment, no auto-inject); short-circuits (post-type filter, Bricks/Etchwp/Elementor previews, global processing-flag re-entry guard); `preparse_toc_headings` populates global `$lkst_toc_items`; `render_shortcode` returns empty under 2 items and renders when ≥ 2.
- No shipped behavior changed. New tests run against the current v1.6.0 surface via `composer test`.

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
- **Custom Block Category:** Registered a dedicated "Zehoro Toolkit" category in the Gutenberg editor to group all native toolkit blocks together.

### Fixed
- **TOC Regex:** Fixed an inverted regex capture group that was causing the Table of Contents anchor IDs to include unwanted quotation marks.
- **Block Assets:** Corrected the path mapping in `block.json` for the Callout and Pros & Cons blocks so they properly load their compiled assets in the editor.

## [1.2.0] - 2026-05-11
### Changed
- **Pro Refactoring:** Extracted resource-intensive features (Intelligent Content CTAs, CTA Admin, Inline Posts, Freshness Log, Pros/Cons Schema) and moved them to the new Zehoro Toolkit Pro add-on to maintain a lightweight core.

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
