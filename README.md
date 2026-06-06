# Zehoro Toolkit

A lightweight, builder-agnostic editorial toolkit for WordPress — the **free base for [Zehoro Toolkit Pro](https://github.com/leokoo/zehoro-toolkit-pro)**. Delivers highly-optimized, schema-aware frontend components like a Wirecutter-style Table of Contents, Author Boxes, FAQ/Steps with JSON-LD, and post-type-aware Article schema.

Originally developed for Bricks Builder, it has been fully refactored into a builder-agnostic architecture, meaning it works flawlessly with Bricks, EtchWP, Gutenberg, or Elementor.

## 🚀 Key Features

### 1. Wirecutter-Style Table of Contents
A smart, zero-dependency TOC that automatically parses post content and generates anchored headings.
- **Desktop:** Renders as a full-width sticky bar.
- **Mobile:** Transforms into a sleek, bottom-sheet dropdown menu.
- **Dynamic Scroll-Spy:** The header text updates dynamically as the user scrolls past sections.
- **Seamless Marquee:** If a heading is too long for a mobile screen, it elegantly fades and triggers an infinite horizontal CSS scroll.

### 2. Essential Editorial Utilities
- **Author Box (`[lkst_author_box]`):** A beautiful author profile card supporting custom taglines, credential chips, and integrated social links (E-E-A-T `sameAs` schema).
- **Article Schema (E-E-A-T):** Post-type-aware JSON-LD (BlogPosting, Recipe, Review, Service…). Skips automatically when Yoast / SEOPress / RankMath / AIOSEO / SureRank already emits schema.
- **FAQ & Steps:** Q&A and how-to blocks with JSON-LD — AI-citable, passage-level content for the AI-search era.
- **Category Pills (`[lkst_top_category_pills]`):** Dynamic term queries to display the most popular categories/tags for custom archive layouts.
- **Home Filter Pills (`[lkst_home_filter_pills]`):** Curated cross-CPT topic navigation for editorial hubs.

## 🛠 Architecture & Performance

- **Builder Agnostic:** Outputs raw, semantic HTML. All modules are invoked via standard WordPress shortcodes or auto-injected.
- **Zero Dependencies:** No jQuery, no third-party libraries. All Javascript is vanilla and uses modern browser APIs (`IntersectionObserver`, `requestAnimationFrame`).
- **Strict OOP Design:** Custom SPL autoloading. Every module is isolated within the `Zehoro` namespace.
- **Clean Uninstallation:** Adheres to WordPress VIP standards. Removing the plugin safely wipes all custom options, user meta, and post meta without leaving orphaned database rows.

## 📦 Installation

1. Download the latest release from the repository.
2. Upload the `zehoro-toolkit` folder to your `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Navigate to **Zehoro Toolkit > Modules** to selectively enable the features you need.

## 🎨 Visual Configuration

The plugin does not force structural spacing or typography, ensuring it inherits your site's core framework (like ACSS). However, brand colors can be mapped to the toolkit's CSS variables by navigating to **Zehoro Toolkit > Visual Styles**.

---
*Developed for leokoo.com*