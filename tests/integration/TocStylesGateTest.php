<?php
/**
 * TableOfContents::force_styles_when_toc_renders — the hotfix that loads
 * the stylesheet when an auto-injected TOC will actually render (the case
 * has_zehoro_content() can't see). Pins: forces styles for a ≥2-heading
 * singular post, leaves a sub-2 post alone, and never downgrades an
 * already-true gate.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\TableOfContents;

class TocStylesGateTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$GLOBALS['lkst_toc_items'] = null;
		$GLOBALS['wp_scripts']     = null; // fresh enqueue registry per test
	}

	public function test_forces_styles_when_a_toc_will_render() {
		$post_id = self::factory()->post->create( [
			'post_content' => '<h2>First</h2><p>x</p><h2>Second</h2><p>y</p>',
		] );
		$this->go_to( get_permalink( $post_id ) );

		$toc = new TableOfContents();
		$toc->preparse_toc_headings();                 // populates $lkst_toc_items

		$this->assertTrue( $toc->force_styles_when_toc_renders( false ) );
	}

	public function test_does_not_force_for_fewer_than_two_headings() {
		$GLOBALS['lkst_toc_items'] = [ [ 'level' => '2', 'id' => 'a', 'text' => 'A' ] ];
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$this->assertFalse( ( new TableOfContents() )->force_styles_when_toc_renders( false ) );
	}

	public function test_never_downgrades_an_already_true_gate() {
		$this->assertTrue( ( new TableOfContents() )->force_styles_when_toc_renders( true ) );
	}

	public function test_enqueues_toc_js_when_an_auto_injected_toc_will_render() {
		$post_id = self::factory()->post->create( [
			'post_content' => '<h2>First</h2><p>x</p><h2>Second</h2><p>y</p>',
		] );
		$this->go_to( get_permalink( $post_id ) );

		$toc = new TableOfContents();
		$toc->preparse_toc_headings();
		$toc->maybe_enqueue_toc_js();

		$this->assertTrue( wp_script_is( 'zehoro-toc', 'enqueued' ), 'auto-injected TOC must still load its click-to-open script' );
	}

	public function test_does_not_enqueue_toc_js_without_a_toc() {
		$post_id = self::factory()->post->create( [ 'post_content' => '<p>no headings here</p>' ] );
		$this->go_to( get_permalink( $post_id ) );

		$toc = new TableOfContents();
		$toc->preparse_toc_headings();
		$toc->maybe_enqueue_toc_js();

		$this->assertFalse( wp_script_is( 'zehoro-toc', 'enqueued' ) );
	}
}
