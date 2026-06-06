<?php
namespace Zehoro\Core;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core plugin bootstrap.
 *
 * Implements a Module Registry Pattern. Modules self-register their meta
 * and the plugin uses this registry to power the dashboard and frontend.
 *
 * @package Zehoro\Core
 */
class Plugin {

	/** @var array Registered modules meta. Keyed by slug. */
	private static array $registry = [];

	/** @var ModuleInterface[] Live instances of active modules. */
	private array $modules = [];

	/**
	 * Register a module into the toolkit registry.
	 */
	public static function register_module( string $slug, string $class, array $meta ): void {
		self::$registry[ $slug ] = array_merge( $meta, [ 'class' => $class ] );
	}

	/**
	 * Retrieve all registered modules for the dashboard.
	 */
	public static function get_registered_modules(): array {
		return self::$registry;
	}

	public function init(): void {
		// 1. Auto-discover and register all modules
		$dir = __DIR__ . '/../Modules/';
		foreach ( glob( $dir . '*.php' ) as $file ) {
			$class = '\\Zehoro\\Modules\\' . basename( $file, '.php' );
			if ( method_exists( $class, 'register' ) ) {
				$class::register();
			}
		}

		// 2. Fetch active settings
		$default_active = array_keys( array_filter( self::$registry, function( $m ) { return ! empty( $m['default'] ); } ) );
		$active = get_option( 'lkst_active_modules', $default_active );

		// Admin: init dashboard
		if ( is_admin() ) {
			$admin = new \Zehoro\Admin\Dashboard( $active );
			$admin->init();
		}

		// Frontend assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Register custom block category
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ], 10, 2 );

		// 3. Initialise active modules
		foreach ( self::$registry as $slug => $data ) {
			if ( in_array( $slug, $active, true ) ) {
				$class = $data['class'];
				if ( class_exists( $class ) && is_subclass_of( $class, ModuleInterface::class ) ) {
					$module = new $class();
					$module->init();
					$this->modules[ $slug ] = $module;
				}
			}
		}
	}

	public function register_block_category( $categories, $post ) {
		return array_merge(
			[
				[
					'slug'  => 'zehoro-toolkit',
					'title' => __( 'Zehoro Toolkit', 'zehoro-toolkit' ),
					'icon'  => 'admin-tools',
				],
			],
			$categories
		);
	}

	/**
	 * Return a live module instance (or null if not active).
	 */
	public function get_module( string $slug ): ?ModuleInterface {
		return $this->modules[ $slug ] ?? null;
	}

	public function enqueue_assets(): void {
		// Do not load on page-builder canvas previews.
		if ( isset( $_GET['bricks'] ) || isset( $_GET['etchwp'] ) || isset( $_GET['elementor-preview'] ) ) return;

		wp_enqueue_style( 'zehoro-toolkit', ZEHORO_URL . 'assets/style.css', [], ZEHORO_VERSION );

		// Always inject CSS custom properties via wp_add_inline_style.
		$primary   = get_option( 'lkst_color_primary',          '#E8A020' );
		$contrast  = get_option( 'lkst_color_primary_contrast', '#0F1A2E' );
		$secondary = get_option( 'lkst_color_secondary',        '#1ECFC4' );
		$bg_dark   = get_option( 'lkst_color_bg_dark',          '#0F1A2E' );
		$bg_light  = get_option( 'lkst_color_bg_light',         '#F5F0E8' );
		wp_add_inline_style( 'zehoro-toolkit', sprintf(
			':root{--lkst-primary-color:%s;--lkst-primary-contrast:%s;--lkst-secondary-color:%s;--lkst-bg-dark:%s;--lkst-bg-light:%s;}',
			esc_attr( $primary ), esc_attr( $contrast ), esc_attr( $secondary ),
			esc_attr( $bg_dark ), esc_attr( $bg_light )
		) );

		if ( isset( $this->modules['table_of_contents'] ) ) {
			wp_enqueue_script( 'lkst-toc', ZEHORO_URL . 'assets/toc.js', [], ZEHORO_VERSION, true );
		}
	}

	public static function activate(): void {
		// Initialize to scan default modules on activation
		$dir = __DIR__ . '/../Modules/';
		foreach ( glob( $dir . '*.php' ) as $file ) {
			$class = '\\Zehoro\\Modules\\' . basename( $file, '.php' );
			if ( method_exists( $class, 'register' ) ) {
				$class::register();
			}
		}
		$default_active = array_keys( array_filter( self::$registry, function( $m ) { return ! empty( $m['default'] ); } ) );
		
		add_option( 'lkst_active_modules', $default_active );
	}

	public static function deactivate(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lkst_%' OR option_name LIKE '_transient_timeout_lkst_%'" );
		flush_rewrite_rules();
	}
}