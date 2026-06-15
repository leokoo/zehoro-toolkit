<?php
/**
 * Maintenance\DataEraser — the Danger Zone / opt-in-uninstall wipe.
 *
 * Pins: the opt-in defaults OFF (so a plain uninstall preserves data), and
 * erase() removes canonical + legacy options (incl. the migration flag), post
 * meta, user meta, and transients.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Maintenance\DataEraser;

class DataEraserTest extends WP_UnitTestCase {

	public function test_delete_on_uninstall_defaults_off_and_reflects_option() {
		$this->assertFalse( DataEraser::delete_on_uninstall(), 'default must be OFF — temporary uninstall keeps data' );
		update_option( DataEraser::DELETE_ON_UNINSTALL_OPTION, true );
		$this->assertTrue( DataEraser::delete_on_uninstall() );
	}

	public function test_erase_removes_canonical_legacy_and_flag_options() {
		update_option( 'zehoro_active_modules', [ 'toc' ] ); // canonical (in migrator map)
		update_option( 'zehoro_color_primary', '#fff' );      // canonical (in map)
		update_option( 'lkst_color_primary', '#000' );         // legacy (map key)
		update_option( 'zehoro_rename_migration_v1', '1' );    // the migration flag
		update_option( DataEraser::DELETE_ON_UNINSTALL_OPTION, true );

		DataEraser::erase();

		foreach ( [ 'zehoro_active_modules', 'zehoro_color_primary', 'lkst_color_primary', 'zehoro_rename_migration_v1', DataEraser::DELETE_ON_UNINSTALL_OPTION ] as $opt ) {
			$this->assertFalse( get_option( $opt, false ), "$opt should be gone" );
		}
	}

	public function test_erase_removes_post_meta_user_meta_and_transients() {
		$pid = self::factory()->post->create();
		update_post_meta( $pid, 'zehoro_no_cta', '1' );
		$uid = self::factory()->user->create();
		update_user_meta( $uid, 'zehoro_social_x', 'https://x.com/acme' );
		set_transient( 'zehoro_demo_cache', 'v', HOUR_IN_SECONDS );

		DataEraser::erase();

		$this->assertSame( '', get_post_meta( $pid, 'zehoro_no_cta', true ) );
		$this->assertSame( '', get_user_meta( $uid, 'zehoro_social_x', true ) );
		$this->assertFalse( get_transient( 'zehoro_demo_cache' ) );
	}
}
