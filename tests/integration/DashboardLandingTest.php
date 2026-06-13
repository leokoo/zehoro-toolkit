<?php
/**
 * Dashboard landing router — the `zehoro_landing` extension point.
 *
 * The Zehoro top-level menu page delegates to an add-on (Pro's "Start Here"
 * home) when something hooks `zehoro_landing`, and otherwise renders the
 * Modules grid — so a Free-only install is unchanged. Pins both branches.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\Admin\Dashboard;

class DashboardLandingTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function tear_down(): void {
		remove_all_actions( 'zehoro_landing' );
		parent::tear_down();
	}

	public function test_landing_delegates_to_hook_when_claimed() {
		add_action( 'zehoro_landing', static function () {
			echo 'CLAIMED-LANDING-MARKER';
		} );

		ob_start();
		( new Dashboard() )->render_landing();
		$out = (string) ob_get_clean();

		$this->assertStringContainsString( 'CLAIMED-LANDING-MARKER', $out );
	}

	public function test_landing_falls_back_to_modules_grid_when_unclaimed() {
		ob_start();
		( new Dashboard() )->render_landing();
		$out = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'CLAIMED-LANDING-MARKER', $out );
		// The modules grid renders the standard admin wrap.
		$this->assertStringContainsString( 'wrap', $out );
	}

	public function test_landing_denies_non_admin() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );
		add_action( 'zehoro_landing', static function () {
			echo 'CLAIMED-LANDING-MARKER';
		} );

		ob_start();
		( new Dashboard() )->render_landing();
		$this->assertSame( '', (string) ob_get_clean() );
	}
}
