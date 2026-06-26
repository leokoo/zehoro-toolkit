<?php
/**
 * ZehoroRenameMigrator — copy + idempotency + safety coverage.
 *
 * Pins the server-side contract that Phase 1b+1c will rely on: every legacy
 * lkst_* key gets copied to its zehoro_* twin on first migration run, the run
 * is a no-op on subsequent fires, and a pre-existing zehoro_* value is never
 * clobbered by a legacy value.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Migration\ZehoroRenameMigrator;

class ZehoroRenameMigratorTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		ZehoroRenameMigrator::reset_flag();
		foreach ( ZehoroRenameMigrator::OPTION_MAP as $old => $new ) {
			delete_option( $old );
			delete_option( $new );
		}
	}

	// ── migrate_option ───────────────────────────────────────────────────────

	public function test_migrate_option_copies_when_only_legacy_exists() {
		add_option( 'lkst_color_primary', '#abcdef' );

		$migrated = ZehoroRenameMigrator::migrate_option( 'lkst_color_primary', 'zehoro_color_primary' );

		$this->assertTrue( $migrated );
		$this->assertSame( '#abcdef', get_option( 'zehoro_color_primary' ) );
		$this->assertSame( '#abcdef', get_option( 'lkst_color_primary' ), 'legacy key preserved as rollback safety' );
	}

	public function test_migrate_option_is_noop_when_new_already_populated() {
		add_option( 'lkst_color_primary',   '#legacyvalue' );
		add_option( 'zehoro_color_primary', '#newvalue' );

		$migrated = ZehoroRenameMigrator::migrate_option( 'lkst_color_primary', 'zehoro_color_primary' );

		$this->assertFalse( $migrated );
		$this->assertSame( '#newvalue', get_option( 'zehoro_color_primary' ), 'pre-existing new value must NOT be clobbered' );
	}

	public function test_migrate_option_is_noop_when_legacy_absent() {
		// Fresh install scenario — neither key set.
		$migrated = ZehoroRenameMigrator::migrate_option( 'lkst_color_primary', 'zehoro_color_primary' );

		$this->assertFalse( $migrated );
		$this->assertFalse( get_option( 'zehoro_color_primary' ) );
	}

	public function test_migrate_option_preserves_arrays_and_falsy_values() {
		add_option( 'lkst_disclaimer_post_types', [ 'post', 'page', 'product' ] );
		ZehoroRenameMigrator::migrate_option( 'lkst_disclaimer_post_types', 'zehoro_disclaimer_post_types' );
		$this->assertSame( [ 'post', 'page', 'product' ], get_option( 'zehoro_disclaimer_post_types' ) );

		// '0' is a real value, not a "missing" sentinel — must migrate.
		add_option( 'lkst_lu_auto_inject', '0' );
		ZehoroRenameMigrator::migrate_option( 'lkst_lu_auto_inject', 'zehoro_lu_auto_inject' );
		$this->assertSame( '0', get_option( 'zehoro_lu_auto_inject' ) );
	}

	public function test_migrate_option_does_not_overwrite_a_pre_existing_empty_string() {
		// Edge: admin saved an explicitly-empty value on the new key.
		// We MUST preserve the explicit empty, not overwrite with the legacy value.
		add_option( 'zehoro_cta_primary_url', '' );
		add_option( 'lkst_cta_primary_url',   'https://legacy.example/' );

		$migrated = ZehoroRenameMigrator::migrate_option( 'lkst_cta_primary_url', 'zehoro_cta_primary_url' );

		$this->assertFalse( $migrated, 'explicit empty on new key counts as "populated"' );
		$this->assertSame( '', get_option( 'zehoro_cta_primary_url' ) );
	}

	public function test_only_hot_keys_autoload() {
		// The migrated-site query-inflation fix: hot keys (read every / every-content page) join
		// WP's single cached autoload query; module-conditional keys stay out of the bundle.
		add_option( 'lkst_active_modules', [ 'mod_a' ] );      // HOT — read on EVERY request
		add_option( 'lkst_color_primary',  '#abcdef' );        // HOT — CSS variable, every content page
		add_option( 'lkst_toc_settings',   [ 'depth' => 3 ] ); // cold — only when the TOC renders

		ZehoroRenameMigrator::run();

		wp_cache_delete( 'alloptions', 'options' );
		$alloptions = wp_load_alloptions();
		$this->assertArrayHasKey( 'zehoro_active_modules', $alloptions, 'the every-page registry must autoload (the fix)' );
		$this->assertArrayHasKey( 'zehoro_color_primary', $alloptions, 'CSS-variable colours must autoload' );
		$this->assertArrayNotHasKey( 'zehoro_toc_settings', $alloptions, 'a module-conditional setting must NOT autoload — no bundle bloat' );
	}

	// ── run() — orchestration + idempotency ──────────────────────────────────

	public function test_run_processes_every_entry_in_the_option_map() {
		// Seed every legacy option with a distinguishable value.
		foreach ( ZehoroRenameMigrator::OPTION_MAP as $old => $new ) {
			add_option( $old, 'legacy-' . $old );
		}

		ZehoroRenameMigrator::run();

		foreach ( ZehoroRenameMigrator::OPTION_MAP as $old => $new ) {
			$this->assertSame( 'legacy-' . $old, get_option( $new ), "expected $new to carry value from $old" );
		}
	}

	public function test_run_sets_the_migration_flag() {
		$this->assertFalse( get_option( ZehoroRenameMigrator::MIGRATION_FLAG ) );
		ZehoroRenameMigrator::run();
		$this->assertSame( '1', get_option( ZehoroRenameMigrator::MIGRATION_FLAG ) );
	}

	public function test_run_is_a_noop_on_second_call() {
		add_option( 'lkst_active_modules', [ 'mod_a', 'mod_b' ] );
		ZehoroRenameMigrator::run();

		// Mutate the new key as if the user updated it after migration.
		update_option( 'zehoro_active_modules', [ 'mod_a', 'mod_b', 'mod_c' ] );

		// Second run must NOT touch anything (flag short-circuits).
		ZehoroRenameMigrator::run();

		$this->assertSame( [ 'mod_a', 'mod_b', 'mod_c' ], get_option( 'zehoro_active_modules' ), 'second run must not overwrite user mutations' );
	}

	public function test_run_after_flag_reset_re_migrates_safely() {
		add_option( 'lkst_color_primary', '#aaa' );
		ZehoroRenameMigrator::run();
		$this->assertSame( '#aaa', get_option( 'zehoro_color_primary' ) );

		// Simulate a re-activation (admin clicks "Activate" after the rollback
		// of a release). Flag reset + run again — should not double-migrate
		// because the new key already has a value.
		ZehoroRenameMigrator::reset_flag();
		update_option( 'zehoro_color_primary', '#zzz' ); // user changed it post-migration
		ZehoroRenameMigrator::run();

		$this->assertSame( '#zzz', get_option( 'zehoro_color_primary' ), 'user-set value must survive a re-migration' );
	}

	public function test_run_handles_a_fresh_install_with_no_legacy_keys() {
		// Nothing seeded — represents a fresh install.
		ZehoroRenameMigrator::run();

		foreach ( ZehoroRenameMigrator::OPTION_MAP as $old => $new ) {
			$this->assertFalse( get_option( $new ), "fresh install must not auto-create $new" );
		}
		$this->assertSame( '1', get_option( ZehoroRenameMigrator::MIGRATION_FLAG ), 'flag set even on no-op run, so we skip on next page load' );
	}

	// ── shape coverage ───────────────────────────────────────────────────────

	public function test_option_map_covers_the_critical_keys_phase_1a_relies_on() {
		$map = ZehoroRenameMigrator::OPTION_MAP;

		// The shared Free/Pro module registry — must be in the map.
		$this->assertSame( 'zehoro_active_modules', $map['lkst_active_modules'] ?? null );

		// All five style colors.
		$this->assertSame( 'zehoro_color_primary',          $map['lkst_color_primary'] ?? null );
		$this->assertSame( 'zehoro_color_primary_contrast', $map['lkst_color_primary_contrast'] ?? null );
		$this->assertSame( 'zehoro_color_secondary',        $map['lkst_color_secondary'] ?? null );
		$this->assertSame( 'zehoro_color_bg_dark',          $map['lkst_color_bg_dark'] ?? null );
		$this->assertSame( 'zehoro_color_bg_light',         $map['lkst_color_bg_light'] ?? null );

		// CTA pair.
		$this->assertSame( 'zehoro_cta_primary_label',   $map['lkst_cta_primary_label'] ?? null );
		$this->assertSame( 'zehoro_cta_secondary_url',   $map['lkst_cta_secondary_url'] ?? null );
	}

	public function test_option_map_keys_all_start_with_lkst_and_values_with_zehoro() {
		foreach ( ZehoroRenameMigrator::OPTION_MAP as $old => $new ) {
			$this->assertStringStartsWith( 'lkst_',   $old, "legacy key $old must start with lkst_" );
			$this->assertStringStartsWith( 'zehoro_', $new, "canonical key $new must start with zehoro_" );
		}
	}

	public function test_option_map_has_no_duplicate_canonical_keys() {
		$canonical = array_values( ZehoroRenameMigrator::OPTION_MAP );
		$this->assertSame( count( $canonical ), count( array_unique( $canonical ) ), 'two legacy keys mapping to the same canonical key would silently overwrite' );
	}
}
