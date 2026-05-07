<?php
namespace LK\SiteToolkit\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Dashboard {
    public function init() {
        add_action( 'admin_menu', [ $this, 'register_menus' ], 9 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'lkst_author_box_group', 'lkst_cta_primary_label',   [ 'default' => 'Read the articles', 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'lkst_author_box_group', 'lkst_cta_primary_url',     [ 'default' => '/blog/',            'sanitize_callback' => 'esc_url_raw' ] );
        register_setting( 'lkst_author_box_group', 'lkst_cta_secondary_label', [ 'default' => 'Get the newsletter', 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'lkst_author_box_group', 'lkst_cta_secondary_url',   [ 'default' => '#newsletter',       'sanitize_callback' => 'esc_url_raw' ] );
        register_setting( 'lkst_rss_group', 'lkst_rss_post_types', [
            'default'           => [ 'post' ],
            'sanitize_callback' => function( $input ) {
                if ( ! is_array( $input ) ) return [ 'post' ];
                $valid = array_keys( get_post_types( [ 'public' => true ] ) );
                return array_values( array_filter( array_map( 'sanitize_key', $input ), fn( $pt ) => in_array( $pt, $valid, true ) ) );
            },
        ] );

        $sanitize_hex = function( $value, $default ) {
            $value = sanitize_text_field( $value );
            return preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ? $value : $default;
        };
        register_setting( 'lkst_styles_group', 'lkst_color_primary',          [ 'default' => '#E8A020', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#E8A020' ) ] );
        register_setting( 'lkst_styles_group', 'lkst_color_primary_contrast', [ 'default' => '#0F1A2E', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#0F1A2E' ) ] );
        register_setting( 'lkst_styles_group', 'lkst_color_secondary',        [ 'default' => '#1ECFC4', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#1ECFC4' ) ] );
        register_setting( 'lkst_styles_group', 'lkst_color_bg_dark',          [ 'default' => '#0F1A2E', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#0F1A2E' ) ] );
        register_setting( 'lkst_styles_group', 'lkst_color_bg_light',         [ 'default' => '#F5F0E8', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#F5F0E8' ) ] );
    }

    public function register_menus() {
        $active_modules = get_option( 'lkst_active_modules', [
            'reading_time', 'post_nav', 'author_box', 'category_pills', 
            'news_ticker', 'content_cta', 'rss_support', 'archive_cleanup'
        ] );

        add_menu_page(
            'Leokoo Site Toolkit',
            'Site Toolkit',
            'manage_options',
            'lkst-dashboard',
            [ $this, 'render_dashboard_page' ],
            'dashicons-admin-generic',
            80
        );

        add_submenu_page(
            'lkst-dashboard',
            'Modules',
            'Modules',
            'manage_options',
            'lkst-dashboard',
            [ $this, 'render_dashboard_page' ]
        );

        if ( in_array( 'author_box', $active_modules ) ) {
            add_submenu_page(
                'lkst-dashboard',
                'Author Box Settings',
                'Author Box',
                'manage_options',
                'lkst-author-box',
                [ $this, 'render_author_box_settings_page' ]
            );
        }

        if ( in_array( 'content_cta', $active_modules ) ) {
            add_submenu_page(
                'lkst-dashboard',
                'Content CTAs',
                'Content CTAs',
                'manage_options',
                'lkst-content-ctas',
                [ new \LK\SiteToolkit\Modules\ContentCTA(), 'render_page' ]
            );
        }

        if ( in_array( 'table_of_contents', $active_modules ) ) {
            add_submenu_page(
                'lkst-dashboard',
                'Table of Contents',
                'Table of Contents',
                'manage_options',
                'lkst-toc-settings',
                [ new \LK\SiteToolkit\Modules\TableOfContents(), 'render_page' ]
            );
        }

        if ( in_array( 'rss_support', $active_modules ) ) {
            add_submenu_page(
                'lkst-dashboard',
                'RSS Feed Settings',
                'RSS Feed',
                'manage_options',
                'lkst-rss-feed',
                [ $this, 'render_rss_feed_settings_page' ]
            );
        }

        if ( in_array( 'styles', $active_modules ) ) {
            add_submenu_page(
                'lkst-dashboard',
                'Visual Styles',
                'Visual Styles',
                'manage_options',
                'lkst-styles',
                [ $this, 'render_styles_settings_page' ]
            );
        }
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'lkst-' ) === false ) return;
        wp_enqueue_style( 'lkst-admin-css', LKST_URL . 'assets/admin.css', [], LKST_VERSION );
        if ( strpos( $hook, 'lkst-styles' ) !== false ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'lkst-admin-js', LKST_URL . 'assets/admin.js', [ 'wp-color-picker', 'jquery' ], LKST_VERSION, true );
        }
    }

    public function render_dashboard_page() {
        $active_modules = get_option( 'lkst_active_modules', [
            'reading_time', 'post_nav', 'author_box', 'category_pills', 
            'news_ticker', 'content_cta', 'rss_support', 'archive_cleanup'
        ] );

        $modules = [
            'reading_time' => [
                'title' => 'Reading Time',
                'desc'  => 'Calculates and displays estimated reading time. Use [lkst_read_time].',
                'settings_link' => '',
            ],
            'post_nav' => [
                'title' => 'Post Navigation',
                'desc'  => 'Renders Previous/Next post navigation links. Use [lkst_post_nav].',
                'settings_link' => '',
            ],
            'author_box' => [
                'title' => 'Author Box',
                'desc'  => 'Display a full author card with biography, social icons, and call-to-action buttons. Use [lkst_author_box].',
                'settings_link' => admin_url( 'admin.php?page=lkst-author-box' ),
            ],
            'category_pills' => [
                'title' => 'Category Pills',
                'desc'  => 'Dynamic post category or tag pills for archives. Use [lkst_top_category_pills].',
                'settings_link' => '',
            ],
            'news_ticker' => [
                'title' => 'News Ticker',
                'desc'  => 'Horizontal scrolling marquee for recent posts. Use [lkst_ticker_posts].',
                'settings_link' => '',
            ],
            'table_of_contents' => [
                'title' => 'Table of Contents',
                'desc'  => 'Wirecutter-style TOC. Auto-injects at the top of posts, or use [lkst_toc].',
                'settings_link' => '',
            ],
            'content_cta' => [
                'title' => 'Content CTAs',
                'desc'  => 'Unified engine for Power CTAs, Middle CTAs, and Sidebar CTAs with category overrides.',
                'settings_link' => admin_url( 'admin.php?page=lkst-content-ctas' ),
            ],
            'rss_support' => [
                'title' => 'RSS CPT Support',
                'desc'  => 'Include custom post types in your main site RSS feed.',
                'settings_link' => admin_url( 'admin.php?page=lkst-rss-feed' ),
            ],
            'archive_cleanup' => [
                'title' => 'Archive Title Cleanup',
                'desc'  => 'Removes prefixes like "Category:", "Tag:", etc. from archive titles globally.',
                'settings_link' => '',
            ],
            'styles' => [
                'title' => 'Visual Styles',
                'desc'  => 'Customize colors for Author Box, CTAs, and Category Pills to match your brand.',
                'settings_link' => admin_url( 'admin.php?page=lkst-styles' ),
            ],
        ];

        if ( isset( $_POST['lkst_save_modules'] ) && check_admin_referer( 'lkst_modules_action', 'lkst_modules_nonce' ) ) {
            $new_active = isset( $_POST['modules'] ) ? array_keys( $_POST['modules'] ) : [];
            update_option( 'lkst_active_modules', $new_active );
            $active_modules = $new_active;
            echo '<div class="updated"><p>Modules updated successfully.</p></div>';
        }
        ?>
        <div class="wrap lkst-dashboard">
            <h1>Leokoo Site Toolkit &mdash; Modules</h1>
            <p>Enable or disable specific features of the toolkit. Only active modules will load their code, keeping your site fast.</p>
            <form method="post" action="">
                <?php wp_nonce_field( 'lkst_modules_action', 'lkst_modules_nonce' ); ?>
                <div class="lkst-modules-grid">
                    <?php foreach ( $modules as $slug => $data ) : 
                        $is_active = in_array( $slug, $active_modules );
                        ?>
                        <div class="lkst-module-card <?php echo $is_active ? 'active' : 'inactive'; ?>">
                            <div class="lkst-module-header">
                                <h3 class="lkst-module-title"><?php echo esc_html( $data['title'] ); ?></h3>
                                <label class="lkst-switch">
                                    <input type="checkbox" name="modules[<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( $is_active ); ?>>
                                    <span class="lkst-slider"></span>
                                </label>
                            </div>
                            <p class="lkst-module-desc"><?php echo esc_html( $data['desc'] ); ?></p>
                            <div class="lkst-module-footer">
                                <?php if ( ! empty( $data['settings_link'] ) ) : ?>
                                    <a href="<?php echo esc_url( $data['settings_link'] ); ?>" class="lkst-configure-link">
                                        <span class="dashicons dashicons-admin-generic"></span> Configure
                                    </a>
                                <?php else : ?>
                                    <span class="lkst-no-settings" style="color:#a7aaad; font-style:italic; font-size:12px;">No settings needed</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="submit">
                    <input type="submit" name="lkst_save_modules" id="submit" class="button button-primary" value="Save Module Settings">
                </p>
            </form>
        </div>
        <?php
    }

    public function render_styles_settings_page() {
        ?>
        <div class="wrap">
            <h1>Visual Styles</h1>
            <p>Customize the colors used across all toolkit modules (Author Box, CTAs, Category Pills, etc.).</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_styles_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="lkst_color_primary">Primary Brand Color</label></th>
                        <td>
                            <input type="text" id="lkst_color_primary" name="lkst_color_primary" value="<?php echo esc_attr( get_option( 'lkst_color_primary', '#E8A020' ) ); ?>" class="lkst-color-picker" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lkst_color_primary_contrast">Primary Contrast Color</label></th>
                        <td>
                            <input type="text" id="lkst_color_primary_contrast" name="lkst_color_primary_contrast" value="<?php echo esc_attr( get_option( 'lkst_color_primary_contrast', '#0F1A2E' ) ); ?>" class="lkst-color-picker" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lkst_color_secondary">Secondary Brand Color</label></th>
                        <td>
                            <input type="text" id="lkst_color_secondary" name="lkst_color_secondary" value="<?php echo esc_attr( get_option( 'lkst_color_secondary', '#1ECFC4' ) ); ?>" class="lkst-color-picker" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lkst_color_bg_dark">Dark Background</label></th>
                        <td>
                            <input type="text" id="lkst_color_bg_dark" name="lkst_color_bg_dark" value="<?php echo esc_attr( get_option( 'lkst_color_bg_dark', '#0F1A2E' ) ); ?>" class="lkst-color-picker" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lkst_color_bg_light">Light Background</label></th>
                        <td>
                            <input type="text" id="lkst_color_bg_light" name="lkst_color_bg_light" value="<?php echo esc_attr( get_option( 'lkst_color_bg_light', '#F5F0E8' ) ); ?>" class="lkst-color-picker" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_rss_feed_settings_page() {
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $exclude    = [ 'attachment', 'page', 'bricks_template', 'etch_template', 'elementor_library', 'ifso_triggers' ];
        ?>
        <div class="wrap">
            <h1>RSS Feed Support</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_rss_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Include Post Types</th>
                        <td>
                            <?php 
                            $selected = get_option( 'lkst_rss_post_types', [ 'post' ] );
                            foreach ( $post_types as $slug => $pt ) : 
                                if ( in_array( $slug, $exclude ) ) continue;
                                ?>
                                <label style="display:block; margin-bottom:5px;">
                                    <input type="checkbox" name="lkst_rss_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected ) ); ?>>
                                    <?php echo esc_html( $pt->label ); ?> (<code><?php echo esc_html( $slug ); ?></code>)
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_author_box_settings_page() {
        ?>
        <div class="wrap">
            <h1>Author Box Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_author_box_group' ); ?>
                <h2>CTA Buttons</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="lkst_cta_p_label">Primary button label</label></th>
                        <td><input type="text" id="lkst_cta_p_label" name="lkst_cta_primary_label" value="<?php echo esc_attr( get_option( 'lkst_cta_primary_label', 'Read the articles' ) ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_cta_p_url">Primary button URL</label></th>
                        <td><input type="text" id="lkst_cta_p_url" name="lkst_cta_primary_url" value="<?php echo esc_attr( get_option( 'lkst_cta_primary_url', '/blog/' ) ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_cta_s_label">Secondary button label</label></th>
                        <td><input type="text" id="lkst_cta_s_label" name="lkst_cta_secondary_label" value="<?php echo esc_attr( get_option( 'lkst_cta_secondary_label', 'Get the newsletter' ) ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_cta_s_url">Secondary button URL</label></th>
                        <td><input type="text" id="lkst_cta_s_url" name="lkst_cta_secondary_url" value="<?php echo esc_attr( get_option( 'lkst_cta_secondary_url', '#newsletter' ) ); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}