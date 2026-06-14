<?php
/**
 * SEO-plugin coexistence — the canonical detector + schema-output decision.
 *
 * Pins: detection (via the extensible filter), the coexist-by-default
 * stand-down, the user overrides (always/never), the legacy force filter,
 * the `zehoro/emit_schema` filter, and that ArticleSchema delegates to it.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Compat\SeoPlugin;
use Zehoro\Modules\ArticleSchema;

class SeoPluginCompatTest extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'zehoro/seo_plugins' );
		remove_all_filters( 'zehoro/emit_schema' );
		remove_all_filters( 'zehoro_article_schema_force' );
		delete_option( SeoPlugin::OPTION );
		parent::tear_down();
	}

	/** Simulate an active schema-emitting SEO plugin via the public filter. */
	private function fake_seo_active(): void {
		add_filter( 'zehoro/seo_plugins', static fn() => [
			'fakeseo' => [ 'label' => 'Fake SEO', 'check' => static fn() => true ],
		] );
	}

	public function test_no_seo_plugin_by_default() {
		$this->assertNull( SeoPlugin::detect() );
		$this->assertFalse( SeoPlugin::active() );
		$this->assertSame( '', SeoPlugin::label() );
		$this->assertTrue( SeoPlugin::should_emit_schema() ); // free to emit
	}

	public function test_detects_via_filter() {
		$this->fake_seo_active();
		$d = SeoPlugin::detect();
		$this->assertSame( 'fakeseo', $d['slug'] );
		$this->assertSame( 'Fake SEO', $d['label'] );
		$this->assertTrue( SeoPlugin::active() );
		$this->assertSame( 'Fake SEO', SeoPlugin::label() );
	}

	public function test_coexist_by_default_stands_schema_down() {
		$this->fake_seo_active();
		$this->assertFalse( SeoPlugin::should_emit_schema() );
	}

	public function test_user_override_always_and_never() {
		$this->fake_seo_active();
		update_option( SeoPlugin::OPTION, 'always' );
		$this->assertTrue( SeoPlugin::should_emit_schema() ); // keep Zehoro's schema despite the SEO plugin

		update_option( SeoPlugin::OPTION, 'never' );
		$this->assertFalse( SeoPlugin::should_emit_schema() ); // never emit, even with no SEO plugin
		remove_all_filters( 'zehoro/seo_plugins' );
		$this->assertFalse( SeoPlugin::should_emit_schema() );
	}

	public function test_legacy_force_filter_still_emits() {
		$this->fake_seo_active();
		add_filter( 'zehoro_article_schema_force', '__return_true' );
		$this->assertTrue( SeoPlugin::should_emit_schema() );
	}

	public function test_emit_schema_filter_overrides() {
		add_filter( 'zehoro/emit_schema', '__return_false' );
		$this->assertFalse( SeoPlugin::should_emit_schema() ); // no SEO plugin, but a dev forced it off
	}

	public function test_article_schema_delegates_to_detector() {
		// seo_plugin_active() == "suppress ours" == ! should_emit_schema().
		$this->assertFalse( ArticleSchema::seo_plugin_active() );
		$this->fake_seo_active();
		$this->assertTrue( ArticleSchema::seo_plugin_active() );
	}
}
