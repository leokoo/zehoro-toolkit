<?php
/**
 * Schema coexistence — FAQ + Last Updated now honor the CENTRAL policy.
 *
 * Before: only Article Schema obeyed the central `zehoro_schema_output`
 * (auto|always|never) + the `zehoro/emit_schema` filter. FAQ ran a parallel
 * policy and Last Updated had none — so a user-level "never" silenced Article
 * Schema but left duplicate FAQPage / dateModified JSON-LD. These pin that all
 * three schema emitters route through `SeoPlugin::should_emit_schema()`.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Modules\FAQ;
use Zehoro\Modules\LastUpdated;
use Zehoro\Compat\SeoPlugin;

class SchemaCoexistenceTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( SeoPlugin::OPTION );
		remove_all_filters( 'zehoro/emit_schema' );
		parent::tear_down();
	}

	private function faq_schema(): string {
		$faq = new FAQ();
		$faq->render_shortcode( [ 'question' => 'Does it coexist?' ], 'Yes.' );
		ob_start();
		$faq->output_schema();
		return (string) ob_get_clean();
	}

	private function last_updated_schema(): string {
		global $wpdb;
		$post_id = $this->factory->post->create();
		$wpdb->update(
			$wpdb->posts,
			[
				'post_date'         => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
				'post_date_gmt'     => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
				'post_modified'     => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			],
			[ 'ID' => $post_id ]
		);
		clean_post_cache( $post_id );
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		( new LastUpdated() )->output_schema();
		return (string) ob_get_clean();
	}

	// ── Default (auto, no SEO plugin) → all emit ──────────────────────────────

	public function test_faq_emits_by_default() {
		$this->assertStringContainsString( '"FAQPage"', $this->faq_schema() );
	}

	public function test_last_updated_emits_by_default() {
		$this->assertStringContainsString( '"dateModified"', $this->last_updated_schema() );
	}

	// ── Central "never" → silences FAQ + Last Updated too (the fix) ────────────

	public function test_central_never_silences_faq() {
		update_option( SeoPlugin::OPTION, 'never' );
		$out = $this->faq_schema();
		$this->assertStringNotContainsString( '"FAQPage"', $out, 'a global never must silence FAQ schema' );
	}

	public function test_central_never_silences_last_updated() {
		update_option( SeoPlugin::OPTION, 'never' );
		$this->assertStringNotContainsString( '"dateModified"', $this->last_updated_schema(), 'a global never must silence dateModified schema' );
	}

	// ── The zehoro/emit_schema filter also gates both ─────────────────────────

	public function test_emit_schema_filter_false_silences_faq() {
		add_filter( 'zehoro/emit_schema', '__return_false' );
		$this->assertStringNotContainsString( '"FAQPage"', $this->faq_schema() );
	}

	public function test_emit_schema_filter_false_silences_last_updated() {
		add_filter( 'zehoro/emit_schema', '__return_false' );
		$this->assertStringNotContainsString( '"dateModified"', $this->last_updated_schema() );
	}

	// ── Per-type off still wins (FAQ) ─────────────────────────────────────────

	public function test_faq_per_type_off_still_disables() {
		update_option( 'zehoro_faq_schema_mode', 'off' );
		$out = $this->faq_schema();
		delete_option( 'zehoro_faq_schema_mode' );
		$this->assertStringNotContainsString( '"FAQPage"', $out );
	}
}
