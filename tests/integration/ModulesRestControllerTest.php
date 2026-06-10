<?php
/**
 * REST: POST /zehoro/v1/modules/{slug}/toggle
 *
 * Pins the contract used by assets/admin/modules.js: known slug flips state,
 * unknown slug returns 404, manage_options gate denies non-admins.
 *
 * @package Zehoro\Tests\Integration
 */

use Zehoro\REST\ModulesController;
use Zehoro\Core\Plugin;

class ModulesRestControllerTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		do_action( 'rest_api_init' );
		delete_option( 'zehoro_active_modules' );

		// Seed the registry with a known module so toggle has something
		// to flip. Save + restore to not nuke the bootstrap-registered modules.
		$this->registry_snapshot = $this->read_registry();
		$snap = $this->registry_snapshot;
		$snap['test_module'] = [
			'class'   => '\\Zehoro\\Modules\\TestModule',
			'title'   => 'Test Module',
			'desc'    => '...',
			'default' => false,
			'tier'    => 'free',
			'group'   => 'other',
		];
		// Two more in a distinct group so bulk-by-group has a boundary to respect.
		$snap['seo_alpha'] = [ 'class' => '\\Zehoro\\Modules\\A', 'title' => 'A', 'desc' => '', 'default' => false, 'tier' => 'free', 'group' => 'zz_test_group' ];
		$snap['seo_beta']  = [ 'class' => '\\Zehoro\\Modules\\B', 'title' => 'B', 'desc' => '', 'default' => false, 'tier' => 'free', 'group' => 'zz_test_group' ];
		$this->write_registry( $snap );
	}

	public function tear_down() {
		$this->write_registry( $this->registry_snapshot );
		parent::tear_down();
	}

	private array $registry_snapshot = [];

	// ── Happy path ──────────────────────────────────────────────────────────

	public function test_toggle_flips_inactive_to_active_first_call() {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		$response = $this->do_toggle( 'test_module' );

		$this->assertSame( 200, $response->get_status() );
		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertTrue( $body['enabled'] );
		$this->assertSame( 'test_module', $body['slug'] );

		// Persisted to the option.
		$active = (array) get_option( 'zehoro_active_modules', [] );
		$this->assertContains( 'test_module', $active );
	}

	public function test_toggle_flips_active_to_inactive_second_call() {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		$this->do_toggle( 'test_module' );  // on
		$response = $this->do_toggle( 'test_module' ); // off

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertFalse( $body['enabled'] );

		$active = (array) get_option( 'zehoro_active_modules', [] );
		$this->assertNotContains( 'test_module', $active );
	}

	public function test_toggle_does_not_create_duplicates_when_already_active() {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		// Seed with the slug already present + a stray duplicate to confirm
		// dedup happens on the disable→enable path.
		update_option( 'zehoro_active_modules', [ 'test_module', 'other_one', 'test_module' ] );

		$this->do_toggle( 'test_module' ); // → disable; should drop ALL occurrences
		$this->do_toggle( 'test_module' ); // → enable; should add exactly once

		$active = (array) get_option( 'zehoro_active_modules', [] );
		$count  = 0;
		foreach ( $active as $slug ) if ( $slug === 'test_module' ) $count++;
		$this->assertSame( 1, $count );
	}

	// ── Error paths ─────────────────────────────────────────────────────────

	public function test_toggle_returns_404_for_unregistered_slug() {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		$response = $this->do_toggle( 'never_registered' );

		$this->assertSame( 404, $response->get_status() );
		$body = $response->get_data();
		$this->assertSame( 'module_not_registered', $body['code'] );
	}

	public function test_toggle_denies_non_admin() {
		$editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor );

		$response = $this->do_toggle( 'test_module' );

		$this->assertGreaterThanOrEqual( 401, $response->get_status() );
		$this->assertLessThan( 500, $response->get_status() );

		// Option not touched.
		$active = (array) get_option( 'zehoro_active_modules', [] );
		$this->assertNotContains( 'test_module', $active );
	}

	public function test_toggle_denies_anonymous() {
		wp_set_current_user( 0 );

		$response = $this->do_toggle( 'test_module' );

		$this->assertGreaterThanOrEqual( 401, $response->get_status() );
		$this->assertLessThan( 500, $response->get_status() );
	}

	// ── Bulk endpoint ───────────────────────────────────────────────────────

	public function test_bulk_enable_all_activates_every_registered_module() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$response = $this->do_bulk( true );

		$this->assertSame( 200, $response->get_status() );
		$active = (array) get_option( 'zehoro_active_modules', [] );
		foreach ( array_keys( $this->read_registry() ) as $slug ) {
			$this->assertContains( $slug, $active );
		}
	}

	public function test_bulk_disable_scoped_to_group_leaves_other_groups_alone() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		update_option( 'zehoro_active_modules', [ 'test_module', 'seo_alpha', 'seo_beta' ] );

		$response = $this->do_bulk( false, 'zz_test_group' );

		$this->assertSame( 200, $response->get_status() );
		$body = $response->get_data();
		$this->assertEqualsCanonicalizing( [ 'seo_alpha', 'seo_beta' ], $body['slugs'] );

		$active = (array) get_option( 'zehoro_active_modules', [] );
		$this->assertSame( [ 'test_module' ], $active, 'Only the seo group should have been disabled.' );
	}

	public function test_bulk_enable_group_does_not_duplicate_already_active() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		update_option( 'zehoro_active_modules', [ 'seo_alpha' ] );

		$this->do_bulk( true, 'zz_test_group' );

		$active = array_count_values( (array) get_option( 'zehoro_active_modules', [] ) );
		$this->assertSame( 1, $active['seo_alpha'] );
		$this->assertSame( 1, $active['seo_beta'] );
	}

	public function test_bulk_unknown_group_returns_404() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$response = $this->do_bulk( true, 'no_such_group' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'no_modules_in_group', $response->get_data()['code'] );
	}

	public function test_bulk_denies_non_admin() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$response = $this->do_bulk( true );

		$this->assertGreaterThanOrEqual( 401, $response->get_status() );
		$this->assertLessThan( 500, $response->get_status() );
		$this->assertSame( [], (array) get_option( 'zehoro_active_modules', [] ) );
	}

	// ── helpers ─────────────────────────────────────────────────────────────

	private function do_bulk( bool $enable, string $group = '' ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/zehoro/v1/modules/bulk' );
		$request->set_body_params( [ 'enable' => $enable, 'group' => $group ] );

		return rest_ensure_response( rest_get_server()->dispatch( $request ) );
	}


	private function do_toggle( string $slug ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/zehoro/v1/modules/' . $slug . '/toggle' );
		$request->set_url_params( [ 'slug' => $slug ] );

		$server   = rest_get_server();
		$response = $server->dispatch( $request );

		return rest_ensure_response( $response );
	}

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
