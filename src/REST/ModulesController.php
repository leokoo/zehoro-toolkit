<?php
namespace Zehoro\REST;

use Zehoro\Core\Plugin;
use Zehoro\Utils\Option;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST endpoints powering the Modules page.
 *
 * The toggle endpoint replaces the legacy "tick checkboxes → click Save"
 * flow with a per-card switch that flips state immediately on click
 * (matches the WPExtended pattern in
 * `zorasi-roadmaps/specs/phase-0-module-filtering.md`).
 *
 * Endpoint:
 *   POST /wp-json/zehoro/v1/modules/{slug}/toggle
 *     → 200 { success: true,  enabled: bool, slug: string }
 *     → 404 { code: 'module_not_registered', message: '…' } when slug unknown
 *
 * Auth: X-WP-Nonce header (wp_create_nonce('wp_rest')) +
 *       permission_callback current_user_can( 'manage_options' ).
 *
 * @package Zehoro\REST
 */
class ModulesController {

	public const NAMESPACE = 'zehoro/v1';
	public const ROUTE_TOGGLE = '/modules/(?P<slug>[a-z0-9_]+)/toggle';

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, self::ROUTE_TOGGLE, [
			'methods'             => 'POST',
			'callback'            => [ $this, 'toggle' ],
			'permission_callback' => [ $this, 'permission_check' ],
			'args'                => [
				'slug' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
				],
			],
		] );
	}

	public function permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function toggle( WP_REST_Request $request ): WP_REST_Response {
		$slug       = (string) $request->get_param( 'slug' );
		$registered = Plugin::get_registered_modules();

		if ( ! isset( $registered[ $slug ] ) ) {
			return new WP_REST_Response( [
				'code'    => 'module_not_registered',
				'message' => sprintf( 'No module is registered under slug "%s".', $slug ),
			], 404 );
		}

		$active = (array) Option::get( 'zehoro_active_modules', [] );

		if ( in_array( $slug, $active, true ) ) {
			$active  = array_values( array_diff( $active, [ $slug ] ) );
			$enabled = false;
		} else {
			$active[] = $slug;
			$active   = array_values( array_unique( $active ) );
			$enabled  = true;
		}

		update_option( 'zehoro_active_modules', $active );

		return new WP_REST_Response( [
			'success' => true,
			'enabled' => $enabled,
			'slug'    => $slug,
		], 200 );
	}
}
