<?php
/**
 * Zehoro\Utils\PageContent::has_zehoro_content — enqueue-gate detection.
 *
 * Pins the cheap content-presence check used by the global stylesheet
 * gate in Plugin::enqueue_assets() and the Pro pro-blocks.css gate.
 * Loose-match on purpose: false negatives suppress styling (the bug
 * we're guarding against); false positives cost one extra style load.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Utils\PageContent;

class PageContentTest extends WP_UnitTestCase {

	private function make_post_with( string $content ): \WP_Post {
		$id = self::factory()->post->create( [ 'post_content' => $content ] );
		return get_post( $id );
	}

	public function test_returns_false_for_empty_content() {
		$post = $this->make_post_with( '' );
		$this->assertFalse( PageContent::has_zehoro_content( $post ) );
	}

	public function test_returns_false_for_plain_prose() {
		$post = $this->make_post_with( 'Just a regular paragraph with no markers.' );
		$this->assertFalse( PageContent::has_zehoro_content( $post ) );
	}

	public function test_returns_true_for_legacy_lkst_block() {
		$post = $this->make_post_with( '<!-- wp:lkst/callout --><div>...</div><!-- /wp:lkst/callout -->' );
		$this->assertTrue( PageContent::has_zehoro_content( $post ) );
	}

	public function test_returns_true_for_pro_block() {
		$post = $this->make_post_with( '<!-- wp:lkst-pro/comparison-table --><div>x</div><!-- /wp:lkst-pro/comparison-table -->' );
		$this->assertTrue( PageContent::has_zehoro_content( $post ) );
	}

	public function test_returns_true_for_future_zehoro_block() {
		$post = $this->make_post_with( '<!-- wp:zehoro/new-block --><div>x</div><!-- /wp:zehoro/new-block -->' );
		$this->assertTrue( PageContent::has_zehoro_content( $post ) );
	}

	public function test_returns_true_for_legacy_shortcode() {
		$post = $this->make_post_with( 'Some prose. [lkst_author_box author_id="3"]' );
		$this->assertTrue( PageContent::has_zehoro_content( $post ) );
	}

	public function test_returns_true_for_canonical_shortcode() {
		$post = $this->make_post_with( '[zehoro_toc] then text' );
		$this->assertTrue( PageContent::has_zehoro_content( $post ) );
	}

	public function test_returns_true_for_raw_class_name() {
		// Themes/Bricks/page builders sometimes embed raw HTML with our
		// classes — should still trigger the load.
		$post = $this->make_post_with( '<div class="lkst-callout">Custom inline markup</div>' );
		$this->assertTrue( PageContent::has_zehoro_content( $post ) );
	}

	public function test_returns_true_for_data_attribute_markup() {
		// CtaSwap data-attribute API is a substring of 'lkst-', so it hits
		// the catch-all and gets styled.
		$post = $this->make_post_with( '<button data-lkst-swap-target="#form">Subscribe</button>' );
		$this->assertTrue( PageContent::has_zehoro_content( $post ) );
	}

	public function test_returns_false_when_no_post() {
		// Outside any post (e.g. 404 page, sitemap.xml hooked into the
		// frontend) — guard returns false.
		$this->go_to( home_url( '/?p=999999' ) );
		$this->assertFalse( PageContent::has_zehoro_content() );
	}

	public function test_resolves_current_post_when_no_arg_given() {
		// Simulates the real enqueue call site, which uses the global $post.
		$post = $this->make_post_with( '[lkst_toc]' );
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$this->assertTrue( PageContent::has_zehoro_content() );

		wp_reset_postdata();
	}
}
