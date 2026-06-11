<?php
/**
 * Plugin::register_module() — metadata auto-enrichment.
 *
 * Pins the WPExtended-pattern contract: every module registered with the
 * registry gets a tier, group, order, has_settings, keywords value — either
 * what the module passed explicitly, or what auto-detection inferred.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Core\Plugin;

class RegisterModuleMetadataTest extends WP_UnitTestCase {

	private array $registry_snapshot = [];

	public function set_up() {
		parent::set_up();
		$this->registry_snapshot = $this->read_registry();
		$this->write_registry( [] );
	}

	public function tear_down() {
		$this->write_registry( $this->registry_snapshot );
		parent::tear_down();
	}

	// ── Auto-detected tier ──────────────────────────────────────────────────

	public function test_free_namespace_class_auto_detects_tier_free() {
		Plugin::register_module( 'foo', '\\Zehoro\\Modules\\FooModule', [
			'title' => 'Foo',
			'desc'  => 'A test module',
			'default' => true,
		] );

		$registered = Plugin::get_registered_modules();
		$this->assertSame( 'free', $registered['foo']['tier'] );
	}

	public function test_pro_namespace_class_auto_detects_tier_pro() {
		Plugin::register_module( 'bar', '\\Zehoro\\Pro\\Modules\\BarModule', [
			'title' => 'Bar',
			'desc'  => 'A pro test module',
			'default' => true,
		] );

		$registered = Plugin::get_registered_modules();
		$this->assertSame( 'pro', $registered['bar']['tier'] );
	}

	public function test_explicit_tier_overrides_auto_detection() {
		// Pro-namespaced class can be flagged as free explicitly (rare but valid —
		// e.g. a module physically in the Pro repo that's actually shipped free).
		Plugin::register_module( 'baz', '\\Zehoro\\Pro\\Modules\\BazModule', [
			'title' => 'Baz',
			'desc'  => 'Pro repo, free tier',
			'default' => true,
			'tier'  => 'free',
		] );

		$this->assertSame( 'free', Plugin::get_registered_modules()['baz']['tier'] );
	}

	// ── Auto-detected group ────────────────────────────────────────────────

	public function test_known_slug_auto_detects_group() {
		Plugin::register_module( 'table_of_contents', '\\Zehoro\\Modules\\TableOfContents', [
			'title' => 'TOC',
			'desc'  => '...',
			'default' => true,
		] );
		Plugin::register_module( 'google_search_console', '\\Zehoro\\Pro\\Modules\\GoogleSearchConsole', [
			'title' => 'GSC',
			'desc'  => '...',
			'default' => true,
		] );

		$registered = Plugin::get_registered_modules();
		$this->assertSame( 'reading_ux', $registered['table_of_contents']['group'] );
		$this->assertSame( 'seo',        $registered['google_search_console']['group'] );
	}

	public function test_unknown_slug_falls_into_other_group() {
		Plugin::register_module( 'something_brand_new', '\\Zehoro\\Modules\\SomethingBrandNew', [
			'title' => 'Brand new',
			'desc'  => '...',
			'default' => false,
		] );

		$this->assertSame( 'other', Plugin::get_registered_modules()['something_brand_new']['group'] );
	}

	public function test_explicit_group_overrides_auto_detection() {
		// table_of_contents would default to reading_ux; override to admin.
		Plugin::register_module( 'table_of_contents', '\\Zehoro\\Modules\\TableOfContents', [
			'title' => 'TOC',
			'desc'  => '...',
			'default' => true,
			'group' => 'admin',
		] );

		$this->assertSame( 'admin', Plugin::get_registered_modules()['table_of_contents']['group'] );
	}

	// ── has_settings / order / keywords defaults ────────────────────────────

	public function test_has_settings_derives_from_settings_page_presence() {
		Plugin::register_module( 'with_settings', '\\Zehoro\\Modules\\WithSettings', [
			'title' => 'With',
			'desc'  => '...',
			'default' => true,
			'settings_page' => 'zehoro-with-settings',
		] );
		Plugin::register_module( 'no_settings', '\\Zehoro\\Modules\\NoSettings', [
			'title' => 'No',
			'desc'  => '...',
			'default' => true,
		] );

		$registered = Plugin::get_registered_modules();
		$this->assertTrue( $registered['with_settings']['has_settings'] );
		$this->assertFalse( $registered['no_settings']['has_settings'] );
	}

	public function test_order_defaults_to_100() {
		Plugin::register_module( 'foo', '\\Zehoro\\Modules\\Foo', [
			'title' => 'Foo',
			'desc'  => '...',
			'default' => true,
		] );
		$this->assertSame( 100, Plugin::get_registered_modules()['foo']['order'] );
	}

	public function test_keywords_defaults_to_empty_array() {
		Plugin::register_module( 'foo', '\\Zehoro\\Modules\\Foo', [
			'title' => 'Foo',
			'desc'  => '...',
			'default' => true,
		] );
		$this->assertSame( [], Plugin::get_registered_modules()['foo']['keywords'] );
	}

	public function test_keywords_must_be_array_not_string() {
		// Defensive: caller passes a comma-separated string by accident.
		// We don't try to split it — we just coerce to empty.
		Plugin::register_module( 'foo', '\\Zehoro\\Modules\\Foo', [
			'title'    => 'Foo',
			'desc'     => '...',
			'default'  => true,
			'keywords' => 'a,b,c',
		] );
		$this->assertSame( [], Plugin::get_registered_modules()['foo']['keywords'] );
	}

	// ── GROUPS taxonomy ─────────────────────────────────────────────────────

	public function test_groups_taxonomy_includes_other_for_unknown_slugs() {
		$this->assertArrayHasKey( 'other', Plugin::GROUPS );
	}

	public function test_groups_taxonomy_order_is_stable() {
		// The sidebar nav reads Plugin::GROUPS verbatim — order changes here
		// reorder the user-visible nav. Pin the current order.
		$expected = [
			'editorial_blocks', 'schema', 'reading_ux', 'seo',
			'conversion',       'ai',     'workflow',   'admin',
			'other',
		];
		$this->assertSame( $expected, array_keys( Plugin::GROUPS ) );
	}

	// ── Auto-detected type (block / tool / module) ──────────────────────────

	public function test_type_auto_detects_block_tool_and_module() {
		Plugin::register_module( 'coupon_box',     '\\Zehoro\\Pro\\Modules\\CouponBox',     [ 'title' => 'Coupon', 'desc' => '...', 'default' => true ] );
		Plugin::register_module( 'topical_gap',    '\\Zehoro\\Pro\\Modules\\TopicalGap',    [ 'title' => 'Gap',    'desc' => '...', 'default' => true ] );
		Plugin::register_module( 'content_stream', '\\Zehoro\\Pro\\Modules\\ContentStream', [ 'title' => 'Stream', 'desc' => '...', 'default' => true ] );

		$r = Plugin::get_registered_modules();
		$this->assertSame( 'block',  $r['coupon_box']['type'] );
		$this->assertSame( 'tool',   $r['topical_gap']['type'] );
		$this->assertSame( 'module', $r['content_stream']['type'] );
	}

	public function test_explicit_type_overrides_auto_detection() {
		Plugin::register_module( 'coupon_box', '\\Zehoro\\Pro\\Modules\\CouponBox', [ 'title' => 'Coupon', 'desc' => '...', 'default' => true, 'type' => 'tool' ] );
		$this->assertSame( 'tool', Plugin::get_registered_modules()['coupon_box']['type'] );
	}

	// ── Auto-detected capability needs (ai / gsc) ───────────────────────────

	public function test_needs_auto_detects_ai_and_gsc() {
		Plugin::register_module( 'rewrite_context', '\\Zehoro\\Pro\\Modules\\RewriteContext', [ 'title' => 'Rewrite', 'desc' => '...', 'default' => true ] );
		Plugin::register_module( 'ctr_rescue',      '\\Zehoro\\Pro\\Modules\\CTRRescue',      [ 'title' => 'CTR',     'desc' => '...', 'default' => true ] );
		Plugin::register_module( 'topical_gap',     '\\Zehoro\\Pro\\Modules\\TopicalGap',     [ 'title' => 'Gap',     'desc' => '...', 'default' => true ] );

		$r = Plugin::get_registered_modules();
		$this->assertContains( 'ai',  $r['rewrite_context']['needs'] );
		$this->assertContains( 'gsc', $r['ctr_rescue']['needs'] );
		// Topical Gap is deterministic crawl + token-diff — NOT an AI module.
		$this->assertSame( [], $r['topical_gap']['needs'] );
	}

	public function test_needs_defaults_to_empty_array() {
		Plugin::register_module( 'foo', '\\Zehoro\\Modules\\Foo', [ 'title' => 'Foo', 'desc' => '...', 'default' => true ] );
		$this->assertSame( [], Plugin::get_registered_modules()['foo']['needs'] );
	}

	// ── helpers ─────────────────────────────────────────────────────────────

	private function read_registry(): array {
		$ref  = new \ReflectionClass( Plugin::class );
		$prop = $ref->getProperty( 'registry' );
		return (array) $prop->getValue();
	}

	private function write_registry( array $value ): void {
		$ref  = new \ReflectionClass( Plugin::class );
		$prop = $ref->getProperty( 'registry' );
		$prop->setValue( null, $value );
	}
}
