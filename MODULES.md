# Leokoo Site Toolkit — Module Reference

**Plugin:** Leokoo Site Toolkit (Free)
**Version:** 1.3.1
**Namespace:** `LK\SiteToolkit\Modules`
**Source:** `src/Modules/`

---

## Native Gutenberg Blocks

These modules register `block.json`-based Gutenberg blocks. Compiled blocks live in `build/` and use JSX/webpack. Server-side rendered (SSR) blocks use vanilla JS editor scripts in `assets/blocks/` — no build step needed.

| Module file | Slug | Block name | Type | Description |
|---|---|---|---|---|
| `Callout.php` | `callout` | Callout Block | compiled | Visual callout / notice / alert for long-form content. Variants: info, warning, success, danger. |
| `ProsCons.php` | `pros_cons` | Pros & Cons | compiled | Wirecutter-style pros/cons box. Registers three blocks: `pros-cons` (wrapper), `pros`, `cons`. |
| `TLDR.php` | `tldr` | TL;DR / Key Takeaways | compiled | Styled summary box for the top of articles. Native Gutenberg block. |
| `Steps.php` | `steps` | Steps / Process | SSR | Numbered how-to steps. Auto-emits `HowTo` JSON-LD schema. Editor: vanilla JS repeater with move up/down controls. |
| `Testimonial.php` | `testimonial` | Testimonial | SSR | Static testimonial card: photo, name, role/company, quote. Three layout variants (card / minimal / highlight). |
| `StatCallout.php` | `stat_callout` | Stat Callout | SSR | Large bold number + label for B2B/SaaS content marketing. Optional source citation. Three layout variants. |
| `InlineProduct.php` | `inline_product` | Inline Product Mention | SSR | Compact horizontal product card for mid-content references: thumbnail, name, one-liner, CTA button. Configurable link rel. |

---

## Shortcode Modules

These modules register `[lkst_*]` shortcodes and/or auto-inject into `the_content`.

| Module file | Slug | Shortcode(s) | Description |
|---|---|---|---|
| `AuthorBox.php` | `author_box` | `[lkst_author_box]` `[lkst_author_socials]` | Full author card with biography, social icons, and CTA buttons. Resolves author outside the loop (safe for Bricks/Etch). Settings page: `lkst-author-box`. |
| `CategoryPills.php` | `category_pills` | `[lkst_top_category_pills]` | Dynamic category/tag pill links for archives. Accepts `limit` attribute. |
| `ContentBox.php` | `content_box` | `[lkst_box]` | Manual CTA or email-capture box. Types: `cta` (external form shortcode) or `email` (built-in AJAX form with webhook delivery). Migrates from legacy `basic_cta` slug. |
| `Disclaimer.php` | `disclosure` | *(auto-inject)* | Legal/medical/custom disclaimer. Auto-appends styled box to post content. Configurable per post type. **Note:** internal slug is `disclosure` for backward compatibility. |
| `FAQ.php` | `faq` | `[lkst_faq question="..."]` | Styled FAQ accordions with automatic `FAQPage` JSON-LD schema. Defers schema to Yoast/RankMath/SureRank when active. |
| `LastUpdated.php` | `last_updated` | `[lkst_last_updated]` | Freshness badge showing last modified date. Optional auto-inject. Optionally emits structured data into `<head>`. |
| `NewsTicker.php` | `news_ticker` | `[lkst_ticker_posts]` | Horizontally scrolling marquee of recent post titles. |
| `PostNav.php` | `post_nav` | `[lkst_post_nav]` | Previous/Next post navigation links. |
| `ReadingTime.php` | `reading_time` | `[lkst_read_time]` | Calculates and displays estimated reading time. Strips shortcode tags before word count. |
| `TableOfContents.php` | `table_of_contents` | `[lkst_toc]` | Wirecutter-style collapsible TOC. Parses H2/H3 from raw `post_content`, injects anchor IDs, auto-injects or manual via shortcode. Settings page: `lkst-toc-settings`. |

---

## Schema / SEO Modules

| Module file | Slug | Description |
|---|---|---|
| `ArticleSchema.php` | `article_schema` | Post-type-aware JSON-LD schema. Maps post types to: `BlogPosting`, `Recipe`, `Review`, `Service`, `Product`, `WebPage`, `Article`. Auto-skips when Yoast / SEOPress / RankMath / AIOSEO / SureRank is active. Gutenberg sidebar meta box shows schema preview. |

---

## UI / Display Modules

| Module file | Slug | Description |
|---|---|---|
| `ReadingProgress.php` | `reading_progress` | 3px sticky progress bar at top of screen, fills as user scrolls. |
| `VisualStyles.php` | `styles` | Brand color customisation for Author Box, CTAs, and Category Pills via CSS custom properties. |

---

## Site Utility Modules

| Module file | Slug | Description |
|---|---|---|
| `ArchiveCleanup.php` | `archive_cleanup` | Removes "Category:", "Tag:", "Author:" prefixes from archive titles globally via `get_the_archive_title_prefix` filter. |
| `RSSSupport.php` | `rss_support` | Includes custom post types in the main site RSS feed. |

---

## Planned — Not Yet Built

All four originally-planned free blocks have been built (see Native Gutenberg Blocks above). Future additions TBD.
