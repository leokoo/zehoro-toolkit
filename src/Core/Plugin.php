<?php
namespace LK\SiteToolkit\Core;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core plugin bootstrap.
 *
 * Owns the canonical DEFAULT_MODULES list (single source of truth),
 * stores live module instances so the admin Dashboard can reuse them,
 * and always injects CSS custom properties via wp_add_inline_style so
 * they override the stylesheet regardless of the styles module toggle.
 *
 * @package LK\SiteToolkit\Core
 */
class Plugin {

	/**
	 * Single source of truth for the default-active module list.
	 * Dashboard and activate() both reference this constant.
	 */
	const DEFAULT_MODULES = [
		'reading_time', 'post_nav', 'author_box', 'category_pills',
		'news_ticker', 'content_cta', 'table_of_contents', 'styles',
		'rss_support', 'archive_cleanup',
	];

	/** @var ModuleInterface[] Keyed by module slug. */
	private array $modules = [];

	public function init(): void {
		$active = get_option( 'lkst_active_modules', self::DEFAULT_MODULES );

		// Admin: init dashboard + CTA admin layer
		if ( is_admin() ) {
			$admin = new \LK\SiteToolkit\Admin\Dashboard( $active );
			$admin->init();

			// CTA admin hooks (settings registration, meta box) live in CTAAdmin, not ContentCTA.
			// Initialise it regardless of whether content_cta is in $active so meta boxes
			// still appear after a toggle — the frontend injection is what needs the module active.
			$cta_admin = new \LK\SiteToolkit\Admin\CTAAdmin( new \LK\SiteToolkit\Modules\ContentCTA() );
			$cta_admin->init();
		}

		// Frontend assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Initialise modules
		$map = [
			'reading_time'      => \LK\SiteToolkit\Modules\ReadingTime::class,
			'post_nav'          => \LK\SiteToolkit\Modules\PostNav::class,
			'author_box'        => \LK\SiteToolkit\Modules\AuthorBox::class,
			'category_pills'    => \LK\SiteToolkit\Modules\CategoryPills::class,
			'news_ticker'       => \LK\SiteToolkit\Modules\NewsTicker::class,
			'table_of_contents' => \LK\SiteToolkit\Modules\TableOfContents::class,
			'content_cta'       => \LK\SiteToolkit\Modules\ContentCTA::class,
		];

		foreach ( $map as $slug => $class ) {
			if ( in_array( $slug, $active, true ) && class_exists( $class ) ) {
				/** @var ModuleInterface $module */
				$module = new $class();
				$module->init();
				$this->modules[ $slug ] = $module;
			}
		}

		// Inline modules (no dedicated class needed)
		if ( in_array( 'archive_cleanup', $active, true ) ) {
			add_filter( 'get_the_archive_title_prefix', '__return_false' );
		}

		if ( in_array( 'rss_support', $active, true ) ) {
			add_filter( 'request', static function ( $qv ) {
				if ( ! isset( $qv['feed'] ) || isset( $qv['post_type'] ) ) return $qv;
				$selected      = get_option( 'lkst_rss_post_types', [ 'post' ] );
				if ( empty( $selected ) ) $selected = [ 'post' ];
				$qv['post_type'] = $selected;
				return $qv;
			} );
		}
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

		wp_enqueue_style( 'leokoo-site-toolkit', LKST_URL . 'assets/style.css', [], LKST_VERSION );

		// Always inject CSS custom properties via wp_add_inline_style.
		// This outputs AFTER the <link> tag, so it wins the cascade over any
		// :root block that may remain in the stylesheet.
		$primary   = get_option( 'lkst_color_primary',          '#E8A020' );
		$contrast  = get_option( 'lkst_color_primary_contrast', '#0F1A2E' );
		$secondary = get_option( 'lkst_color_secondary',        '#1ECFC4' );
		$bg_dark   = get_option( 'lkst_color_bg_dark',          '#0F1A2E' );
		$bg_light  = get_option( 'lkst_color_bg_light',         '#F5F0E8' );
		wp_add_inline_style( 'leokoo-site-toolkit', sprintf(
			':root{--lkst-primary-color:%s;--lkst-primary-contrast:%s;--lkst-secondary-color:%s;--lkst-bg-dark:%s;--lkst-bg-light:%s;}',
			esc_attr( $primary ), esc_attr( $contrast ), esc_attr( $secondary ),
			esc_attr( $bg_dark ), esc_attr( $bg_light )
		) );

		$active = get_option( 'lkst_active_modules', self::DEFAULT_MODULES );
		if ( in_array( 'table_of_contents', $active, true ) ) {
			wp_enqueue_script( 'lkst-toc', LKST_URL . 'assets/toc.js', [], LKST_VERSION, true );
		}
	}

	public static function activate(): void {
		add_option( 'lkst_active_modules', self::DEFAULT_MODULES );
		if ( ! get_option( 'lkst_content_cta_settings' ) ) {
			add_option( 'lkst_content_cta_settings', \LK\SiteToolkit\Modules\ContentCTA::get_defaults() );
		}
	}

	/**
	 * Deactivation hook — flush transient caches.
	 * Does NOT delete persistent options (those only go on uninstall).
	 */
	public static function deactivate(): void {
		global $wpdb;
		// Remove all plugin transients (CategoryPills cache, etc.)
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lkst_%' OR option_name LIKE '_transient_timeout_lkst_%'" );
		flush_rewrite_rules();
	}
}