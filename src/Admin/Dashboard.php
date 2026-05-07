<?php
namespace LK\SiteToolkit\Admin;

use LK\SiteToolkit\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin dashboard: registers menus, settings pages, and the modules toggle.
 *
 * @package LK\SiteToolkit\Admin
 */
class Dashboard {

	/** @var array Currently active module slugs. */
	private array $active;

	public function __construct( array $active = [] ) {
		$this->active = $active ?: get_option( 'lkst_active_modules', Plugin::DEFAULT_MODULES );
	}

	public function init(): void {
		add_action( 'admin_menu',             [ $this, 'register_menus' ], 9 );
		add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init',             [ $this, 'register_settings' ] );
	}

	public function register_settings(): void {
		register_setting( 'lkst_author_box_group', 'lkst_cta_primary_label',   [ 'default' => 'Read the articles',   'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'lkst_author_box_group', 'lkst_cta_primary_url',     [ 'default' => '/blog/',              'sanitize_callback' => 'esc_url_raw' ] );
		register_setting( 'lkst_author_box_group', 'lkst_cta_secondary_label', [ 'default' => 'Get the newsletter',  'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'lkst_author_box_group', 'lkst_cta_secondary_url',   [ 'default' => '#newsletter',         'sanitize_callback' => 'esc_url_raw' ] );
		register_setting( 'lkst_rss_group', 'lkst_rss_post_types', [
			'default'           => [ 'post' ],
			'sanitize_callback' => function ( $input ) {
				if ( ! is_array( $input ) ) return [ 'post' ];
				$valid = array_keys( get_post_types( [ 'public' => true ] ) );
				return array_values( array_filter( array_map( 'sanitize_key', $input ), fn( $pt ) => in_array( $pt, $valid, true ) ) );
			},
		] );

		$sanitize_hex = fn( $value, $default ) => preg_match( '/^#[0-9a-fA-F]{3,8}$/', sanitize_text_field( $value ) ) ? sanitize_text_field( $value ) : $default;
		register_setting( 'lkst_styles_group', 'lkst_color_primary',          [ 'default' => '#E8A020', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#E8A020' ) ] );
		register_setting( 'lkst_styles_group', 'lkst_color_primary_contrast', [ 'default' => '#0F1A2E', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#0F1A2E' ) ] );
		register_setting( 'lkst_styles_group', 'lkst_color_secondary',        [ 'default' => '#1ECFC4', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#1ECFC4' ) ] );
		register_setting( 'lkst_styles_group', 'lkst_color_bg_dark',          [ 'default' => '#0F1A2E', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#0F1A2E' ) ] );
		register_setting( 'lkst_styles_group', 'lkst_color_bg_light',         [ 'default' => '#F5F0E8', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#F5F0E8' ) ] );
	}

	public function register_menus(): void {
		add_menu_page(
			__( 'Leokoo Site Toolkit', 'leokoo-site-toolkit' ),
			__( 'Site Toolkit', 'leokoo-site-toolkit' ),
			'manage_options',
			'lkst-dashboard',
			[ $this, 'render_dashboard_page' ],
			'dashicons-admin-generic',
			80
		);

		add_submenu_page(
			'lkst-dashboard',
			__( 'Modules', 'leokoo-site-toolkit' ),
			__( 'Modules', 'leokoo-site-toolkit' ),
			'manage_options',
			'lkst-dashboard',
			[ $this, 'render_dashboard_page' ]
		);

		if ( in_array( 'author_box', $this->active, true ) ) {
			add_submenu_page( 'lkst-dashboard', __( 'Author Box Settings', 'leokoo-site-toolkit' ), __( 'Author Box', 'leokoo-site-toolkit' ), 'manage_options', 'lkst-author-box', [ $this, 'render_author_box_settings_page' ] );
		}

		if ( in_array( 'content_cta', $this->active, true ) ) {
			// CTAAdmin is already initialised in Plugin::init(); reuse a fresh instance for the render callback.
			add_submenu_page( 'lkst-dashboard', __( 'Content CTAs', 'leokoo-site-toolkit' ), __( 'Content CTAs', 'leokoo-site-toolkit' ), 'manage_options', 'lkst-content-ctas',
				[ new CTAAdmin( new \LK\SiteToolkit\Modules\ContentCTA() ), 'render_page' ]
			);
		}

		if ( in_array( 'table_of_contents', $this->active, true ) ) {
			add_submenu_page( 'lkst-dashboard', __( 'Table of Contents', 'leokoo-site-toolkit' ), __( 'Table of Contents', 'leokoo-site-toolkit' ), 'manage_options', 'lkst-toc-settings',
				[ new \LK\SiteToolkit\Modules\TableOfContents(), 'render_page' ]
			);
		}

		if ( in_array( 'rss_support', $this->active, true ) ) {
			add_submenu_page( 'lkst-dashboard', __( 'RSS Feed Settings', 'leokoo-site-toolkit' ), __( 'RSS Feed', 'leokoo-site-toolkit' ), 'manage_options', 'lkst-rss-feed', [ $this, 'render_rss_feed_settings_page' ] );
		}

		if ( in_array( 'styles', $this->active, true ) ) {
			add_submenu_page( 'lkst-dashboard', __( 'Visual Styles', 'leokoo-site-toolkit' ), __( 'Visual Styles', 'leokoo-site-toolkit' ), 'manage_options', 'lkst-styles', [ $this, 'render_styles_settings_page' ] );
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'lkst-' ) === false ) return;
		wp_enqueue_style( 'lkst-admin-css', LKST_URL . 'assets/admin.css', [], LKST_VERSION );
		if ( strpos( $hook, 'lkst-styles' ) !== false ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'lkst-admin-js', LKST_URL . 'assets/admin.js', [ 'wp-color-picker', 'jquery' ], LKST_VERSION, true );
		}
	}

	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// POST handler: save modules, then redirect (POST-Redirect-GET pattern).
		// Without a redirect the user can re-submit by refreshing, and the browser
		// shows a "resubmit form?" warning.
		if ( isset( $_POST['lkst_save_modules'] ) && check_admin_referer( 'lkst_modules_action', 'lkst_modules_nonce' ) ) {
			$new_active = isset( $_POST['modules'] ) ? array_keys( $_POST['modules'] ) : [];
			update_option( 'lkst_active_modules', $new_active );
			wp_safe_redirect( add_query_arg( [ 'page' => 'lkst-dashboard', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$active = get_option( 'lkst_active_modules', Plugin::DEFAULT_MODULES );

		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="updated notice is-dismissible"><p>' . esc_html__( 'Modules updated successfully.', 'leokoo-site-toolkit' ) . '</p></div>';
		}

		$modules = [
			'reading_time'      => [ 'title' => __( 'Reading Time',          'leokoo-site-toolkit' ), 'desc' => __( 'Calculates and displays estimated reading time. Use [lkst_read_time].', 'leokoo-site-toolkit' ), 'settings_link' => '' ],
			'post_nav'          => [ 'title' => __( 'Post Navigation',       'leokoo-site-toolkit' ), 'desc' => __( 'Renders Previous/Next post navigation links. Use [lkst_post_nav].', 'leokoo-site-toolkit' ), 'settings_link' => '' ],
			'author_box'        => [ 'title' => __( 'Author Box',            'leokoo-site-toolkit' ), 'desc' => __( 'Full author card with biography, social icons, and CTA buttons. Use [lkst_author_box].', 'leokoo-site-toolkit' ), 'settings_link' => admin_url( 'admin.php?page=lkst-author-box' ) ],
			'category_pills'    => [ 'title' => __( 'Category Pills',        'leokoo-site-toolkit' ), 'desc' => __( 'Dynamic category/tag pills for archives. Use [lkst_top_category_pills].', 'leokoo-site-toolkit' ), 'settings_link' => '' ],
			'news_ticker'       => [ 'title' => __( 'News Ticker',           'leokoo-site-toolkit' ), 'desc' => __( 'Horizontal scrolling marquee for recent posts. Use [lkst_ticker_posts].', 'leokoo-site-toolkit' ), 'settings_link' => '' ],
			'table_of_contents' => [ 'title' => __( 'Table of Contents',    'leokoo-site-toolkit' ), 'desc' => __( 'Wirecutter-style TOC. Auto-injects at the top of posts, or use [lkst_toc].', 'leokoo-site-toolkit' ), 'settings_link' => '' ],
			'content_cta'       => [ 'title' => __( 'Content CTAs',          'leokoo-site-toolkit' ), 'desc' => __( 'Unified Power, Middle, and Sidebar CTA engine with category overrides.', 'leokoo-site-toolkit' ), 'settings_link' => admin_url( 'admin.php?page=lkst-content-ctas' ) ],
			'rss_support'       => [ 'title' => __( 'RSS CPT Support',       'leokoo-site-toolkit' ), 'desc' => __( 'Include custom post types in your main site RSS feed.', 'leokoo-site-toolkit' ), 'settings_link' => admin_url( 'admin.php?page=lkst-rss-feed' ) ],
			'archive_cleanup'   => [ 'title' => __( 'Archive Title Cleanup', 'leokoo-site-toolkit' ), 'desc' => __( 'Removes "Category:", "Tag:", etc. prefixes from archive titles.', 'leokoo-site-toolkit' ), 'settings_link' => '' ],
			'styles'            => [ 'title' => __( 'Visual Styles',         'leokoo-site-toolkit' ), 'desc' => __( 'Customize brand colors for Author Box, CTAs, and Category Pills.', 'leokoo-site-toolkit' ), 'settings_link' => admin_url( 'admin.php?page=lkst-styles' ) ],
		];
		?>
		<div class="wrap lkst-dashboard">
			<h1><?php esc_html_e( 'Leokoo Site Toolkit — Modules', 'leokoo-site-toolkit' ); ?></h1>
			<p><?php esc_html_e( 'Enable or disable specific features of the toolkit. Only active modules load their code.', 'leokoo-site-toolkit' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'lkst_modules_action', 'lkst_modules_nonce' ); ?>
				<div class="lkst-modules-grid">
					<?php foreach ( $modules as $slug => $data ) :
						$is_active = in_array( $slug, $active, true );
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
									<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Configure', 'leokoo-site-toolkit' ); ?>
								</a>
							<?php else : ?>
								<span style="color:#a7aaad;font-style:italic;font-size:12px;"><?php esc_html_e( 'No settings needed', 'leokoo-site-toolkit' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<p class="submit">
					<input type="submit" name="lkst_save_modules" class="button button-primary" value="<?php esc_attr_e( 'Save Module Settings', 'leokoo-site-toolkit' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	public function render_styles_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Visual Styles', 'leokoo-site-toolkit' ); ?></h1>
			<p><?php esc_html_e( 'Customize the colors used across all toolkit modules.', 'leokoo-site-toolkit' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'lkst_styles_group' ); ?>
				<table class="form-table">
					<tr><th><label for="lkst_color_primary"><?php esc_html_e( 'Primary Brand Color', 'leokoo-site-toolkit' ); ?></label></th>
						<td><input type="text" id="lkst_color_primary" name="lkst_color_primary" value="<?php echo esc_attr( get_option( 'lkst_color_primary', '#E8A020' ) ); ?>" class="lkst-color-picker"></td></tr>
					<tr><th><label for="lkst_color_primary_contrast"><?php esc_html_e( 'Primary Contrast Color', 'leokoo-site-toolkit' ); ?></label></th>
						<td><input type="text" id="lkst_color_primary_contrast" name="lkst_color_primary_contrast" value="<?php echo esc_attr( get_option( 'lkst_color_primary_contrast', '#0F1A2E' ) ); ?>" class="lkst-color-picker"></td></tr>
					<tr><th><label for="lkst_color_secondary"><?php esc_html_e( 'Secondary Brand Color', 'leokoo-site-toolkit' ); ?></label></th>
						<td><input type="text" id="lkst_color_secondary" name="lkst_color_secondary" value="<?php echo esc_attr( get_option( 'lkst_color_secondary', '#1ECFC4' ) ); ?>" class="lkst-color-picker"></td></tr>
					<tr><th><label for="lkst_color_bg_dark"><?php esc_html_e( 'Dark Background', 'leokoo-site-toolkit' ); ?></label></th>
						<td><input type="text" id="lkst_color_bg_dark" name="lkst_color_bg_dark" value="<?php echo esc_attr( get_option( 'lkst_color_bg_dark', '#0F1A2E' ) ); ?>" class="lkst-color-picker"></td></tr>
					<tr><th><label for="lkst_color_bg_light"><?php esc_html_e( 'Light Background', 'leokoo-site-toolkit' ); ?></label></th>
						<td><input type="text" id="lkst_color_bg_light" name="lkst_color_bg_light" value="<?php echo esc_attr( get_option( 'lkst_color_bg_light', '#F5F0E8' ) ); ?>" class="lkst-color-picker"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function render_rss_feed_settings_page(): void {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$exclude    = [ 'attachment', 'page', 'bricks_template', 'etch_template', 'elementor_library', 'ifso_triggers' ];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RSS Feed Support', 'leokoo-site-toolkit' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'lkst_rss_group' ); ?>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Include Post Types', 'leokoo-site-toolkit' ); ?></th>
						<td><?php
							$selected = get_option( 'lkst_rss_post_types', [ 'post' ] );
							foreach ( $post_types as $slug => $pt ) :
								if ( in_array( $slug, $exclude, true ) ) continue;
								?>
								<label style="display:block;margin-bottom:5px;">
									<input type="checkbox" name="lkst_rss_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected, true ) ); ?>>
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

	public function render_author_box_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Author Box Settings', 'leokoo-site-toolkit' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'lkst_author_box_group' ); ?>
				<h2><?php esc_html_e( 'CTA Buttons', 'leokoo-site-toolkit' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="lkst_cta_p_label"><?php esc_html_e( 'Primary button label', 'leokoo-site-toolkit' ); ?></label></th>
						<td><input type="text" id="lkst_cta_p_label" name="lkst_cta_primary_label" value="<?php echo esc_attr( get_option( 'lkst_cta_primary_label', 'Read the articles' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="lkst_cta_p_url"><?php esc_html_e( 'Primary button URL', 'leokoo-site-toolkit' ); ?></label></th>
						<td><input type="text" id="lkst_cta_p_url" name="lkst_cta_primary_url" value="<?php echo esc_attr( get_option( 'lkst_cta_primary_url', '/blog/' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="lkst_cta_s_label"><?php esc_html_e( 'Secondary button label', 'leokoo-site-toolkit' ); ?></label></th>
						<td><input type="text" id="lkst_cta_s_label" name="lkst_cta_secondary_label" value="<?php echo esc_attr( get_option( 'lkst_cta_secondary_label', 'Get the newsletter' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="lkst_cta_s_url"><?php esc_html_e( 'Secondary button URL', 'leokoo-site-toolkit' ); ?></label></th>
						<td><input type="text" id="lkst_cta_s_url" name="lkst_cta_secondary_url" value="<?php echo esc_attr( get_option( 'lkst_cta_secondary_url', '#newsletter' ) ); ?>" class="regular-text"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}