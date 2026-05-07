<?php
namespace LK\SiteToolkit\Core;

if ( ! defined( 'ABSPATH' ) ) exit;

class Plugin {
    public function init() {
        $active_modules = get_option( 'lkst_active_modules', [
            'reading_time', 'post_nav', 'author_box', 'category_pills',
            'news_ticker', 'content_cta', 'table_of_contents', 'rss_support', 'archive_cleanup'
        ] );

        // Admin functionality
        if ( is_admin() ) {
            $admin = new \LK\SiteToolkit\Admin\Dashboard();
            $admin->init();
        }

        // Enqueue generic frontend assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Init modules
        if ( in_array( 'reading_time', $active_modules ) ) (new \LK\SiteToolkit\Modules\ReadingTime())->init();
        if ( in_array( 'post_nav', $active_modules ) ) (new \LK\SiteToolkit\Modules\PostNav())->init();
        if ( in_array( 'author_box', $active_modules ) ) (new \LK\SiteToolkit\Modules\AuthorBox())->init();
        if ( in_array( 'category_pills', $active_modules ) ) (new \LK\SiteToolkit\Modules\CategoryPills())->init();
        if ( in_array( 'news_ticker', $active_modules ) ) (new \LK\SiteToolkit\Modules\NewsTicker())->init();
        if ( in_array( 'table_of_contents', $active_modules ) ) (new \LK\SiteToolkit\Modules\TableOfContents())->init();
        if ( in_array( 'content_cta', $active_modules ) ) (new \LK\SiteToolkit\Modules\ContentCTA())->init();
        
        // Other smaller modules
        if ( in_array( 'archive_cleanup', $active_modules ) ) {
            add_filter( 'get_the_archive_title_prefix', '__return_false' );
        }
        
        if ( in_array( 'rss_support', $active_modules ) ) {
            add_filter( 'request', function( $qv ) {
                if ( ! isset( $qv['feed'] ) || isset( $qv['post_type'] ) ) return $qv;
                $selected = get_option( 'lkst_rss_post_types', [ 'post' ] );
                if ( empty( $selected ) ) $selected = [ 'post' ];
                $qv['post_type'] = $selected;
                return $qv;
            } );
        }

        // Styles module
        if ( in_array( 'styles', $active_modules ) ) {
            add_action( 'wp_head', [ $this, 'inject_dynamic_styles' ], 1 );
        }
    }

    public function enqueue_assets() {
        // Protect builder canvas. 
        if ( is_admin() ) return;
        if ( isset($_GET['bricks']) && $_GET['bricks'] === 'run' ) return;
        if ( isset($_GET['etchwp']) && $_GET['etchwp'] === 'edit' ) return;
        if ( isset($_GET['elementor-preview']) ) return;

        wp_enqueue_style( 'leokoo-site-toolkit', LKST_URL . 'assets/style.css', [], LKST_VERSION );

        $active_modules = get_option( 'lkst_active_modules', [] );
        if ( in_array( 'table_of_contents', $active_modules ) ) {
            wp_enqueue_script( 'lkst-toc', LKST_URL . 'assets/toc.js', [], LKST_VERSION, true );
        }
    }

    public function inject_dynamic_styles() {
        $primary   = get_option( 'lkst_color_primary', '#E8A020' );
        $contrast  = get_option( 'lkst_color_primary_contrast', '#0F1A2E' );
        $secondary = get_option( 'lkst_color_secondary', '#1ECFC4' );
        $bg_dark   = get_option( 'lkst_color_bg_dark', '#0F1A2E' );
        $bg_light  = get_option( 'lkst_color_bg_light', '#F5F0E8' );
        ?>
        <style id="lkst-dynamic-styles">
            :root {
                --lkst-primary-color: <?php echo esc_attr( $primary ); ?>;
                --lkst-primary-contrast: <?php echo esc_attr( $contrast ); ?>;
                --lkst-secondary-color: <?php echo esc_attr( $secondary ); ?>;
                --lkst-bg-dark: <?php echo esc_attr( $bg_dark ); ?>;
                --lkst-bg-light: <?php echo esc_attr( $bg_light ); ?>;
            }
        </style>
        <?php
    }

    public static function activate() {
        add_option( 'lkst_active_modules', [
            'reading_time', 'post_nav', 'author_box', 'category_pills',
            'news_ticker', 'content_cta', 'table_of_contents', 'rss_support', 'archive_cleanup'
        ] );
        if ( ! get_option( 'lkst_content_cta_settings' ) ) {
            add_option( 'lkst_content_cta_settings', \LK\SiteToolkit\Modules\ContentCTA::get_defaults() );
        }
    }
}