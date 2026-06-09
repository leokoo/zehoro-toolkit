<?php
namespace Zehoro\Utils;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cheap content-presence checks used by enqueue gates.
 *
 * Block-aware enqueue patterns assume each block has its own enqueue path
 * via block.json. Our blocks ship through a single monolithic stylesheet,
 * so the cheapest correct gate is "does this page's content contain any
 * toolkit block / shortcode / class reference at all?" — and only then
 * pay the cost of loading the global CSS.
 *
 * The match is intentionally loose: any of the lkst/zehoro prefixes in
 * block markup, shortcodes, or raw HTML class names counts. A false
 * positive (e.g. the word "lkst" appears in prose) costs us a one-time
 * style load on that single page. A false negative would suppress styling,
 * which is the bug class we're guarding against.
 */
final class PageContent {

	public static function has_zehoro_content( $post = null ): bool {
		if ( ! ( $post instanceof \WP_Post ) ) {
			$post = get_post( $post );
		}
		if ( ! $post ) return false;

		$content = (string) $post->post_content;
		if ( $content === '' ) return false;

		// Block markup — `wp:lkst/*`, `wp:lkst-pro/*`, future `wp:zehoro/*`.
		if ( strpos( $content, 'wp:lkst' )   !== false ) return true;
		if ( strpos( $content, 'wp:zehoro/' ) !== false ) return true;

		// Shortcodes — legacy `[lkst_*]` + canonical `[zehoro_*]`.
		if ( strpos( $content, '[lkst_' )   !== false ) return true;
		if ( strpos( $content, '[zehoro_' ) !== false ) return true;

		// Raw HTML with `class="lkst-*"` / `data-lkst-*` (themes embed these
		// directly for CtaSwap-style data-attribute APIs).
		if ( strpos( $content, 'lkst-' )    !== false ) return true;

		return false;
	}
}
