<?php
namespace Zehoro\Admin;

use Zehoro\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin dashboard: registers menus, settings pages, and the modules toggle.
 *
 * @package Zehoro\Admin
 */
class Dashboard {

	/** @var array Currently active module slugs. */
	private array $active;

	public function __construct( array $active = [] ) {
		$registered = Plugin::get_registered_modules();
		$default_active = array_keys( array_filter( $registered, function($m) { return ! empty( $m['default'] ); } ) );
		$this->active = $active ?: \Zehoro\Utils\Option::get( 'zehoro_active_modules', $default_active );
	}

	public function init(): void {
		add_action( 'admin_menu',             [ $this, 'register_menus' ], 9 );
		add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init',             [ $this, 'register_settings' ] );
	}

	public function register_settings(): void {
		register_setting( 'zehoro_author_box_group', 'zehoro_cta_primary_label',   [ 'default' => 'Read the articles',   'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'zehoro_author_box_group', 'zehoro_cta_primary_url',     [ 'default' => '/blog/',              'sanitize_callback' => 'esc_url_raw' ] );
		register_setting( 'zehoro_author_box_group', 'zehoro_cta_secondary_label', [ 'default' => 'Get the newsletter',  'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'zehoro_author_box_group', 'zehoro_cta_secondary_url',   [ 'default' => '#newsletter',         'sanitize_callback' => 'esc_url_raw' ] );
		register_setting( 'zehoro_rss_group', 'zehoro_rss_post_types', [
			'default'           => [ 'post' ],
			'sanitize_callback' => function ( $input ) {
				if ( ! is_array( $input ) ) return [ 'post' ];
				$valid = array_keys( get_post_types( [ 'public' => true ] ) );
				return array_values( array_filter( array_map( 'sanitize_key', $input ), fn( $pt ) => in_array( $pt, $valid, true ) ) );
			},
		] );

		$sanitize_hex = fn( $value, $default ) => preg_match( '/^#[0-9a-fA-F]{3,8}$/', sanitize_text_field( $value ) ) ? sanitize_text_field( $value ) : $default;
		register_setting( 'zehoro_styles_group', 'zehoro_color_primary',          [ 'default' => '#E8A020', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#E8A020' ) ] );
		register_setting( 'zehoro_styles_group', 'zehoro_color_primary_contrast', [ 'default' => '#0F1A2E', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#0F1A2E' ) ] );
		register_setting( 'zehoro_styles_group', 'zehoro_color_secondary',        [ 'default' => '#1ECFC4', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#1ECFC4' ) ] );
		register_setting( 'zehoro_styles_group', 'zehoro_color_bg_dark',          [ 'default' => '#0F1A2E', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#0F1A2E' ) ] );
		register_setting( 'zehoro_styles_group', 'zehoro_color_bg_light',         [ 'default' => '#F5F0E8', 'sanitize_callback' => fn( $v ) => $sanitize_hex( $v, '#F5F0E8' ) ] );
	}

	public function register_menus(): void {
		add_menu_page(
			__( 'Zehoro Toolkit', 'zehoro-toolkit' ),
			__( 'Site Toolkit', 'zehoro-toolkit' ),
			'manage_options',
			'zehoro-dashboard',
			[ $this, 'render_dashboard_page' ],
			'dashicons-admin-generic',
			80
		);

		add_submenu_page(
			'zehoro-dashboard',
			__( 'Modules', 'zehoro-toolkit' ),
			__( 'Modules', 'zehoro-toolkit' ),
			'manage_options',
			'zehoro-dashboard',
			[ $this, 'render_dashboard_page' ]
		);

		if ( in_array( 'author_box', $this->active, true ) ) {
			add_submenu_page( 'zehoro-dashboard', __( 'Author Box Settings', 'zehoro-toolkit' ), __( 'Author Box', 'zehoro-toolkit' ), 'manage_options', 'zehoro-author-box', [ $this, 'render_author_box_settings_page' ] );
		}

		if ( in_array( 'table_of_contents', $this->active, true ) ) {
			add_submenu_page( 'zehoro-dashboard', __( 'Table of Contents', 'zehoro-toolkit' ), __( 'Table of Contents', 'zehoro-toolkit' ), 'manage_options', 'zehoro-toc-settings',
				[ new \Zehoro\Modules\TableOfContents(), 'render_page' ]
			);
		}

		if ( in_array( 'rss_support', $this->active, true ) ) {
			add_submenu_page( 'zehoro-dashboard', __( 'RSS Feed Settings', 'zehoro-toolkit' ), __( 'RSS Feed', 'zehoro-toolkit' ), 'manage_options', 'zehoro-rss-feed', [ $this, 'render_rss_feed_settings_page' ] );
		}

		if ( in_array( 'styles', $this->active, true ) ) {
			add_submenu_page( 'zehoro-dashboard', __( 'Visual Styles', 'zehoro-toolkit' ), __( 'Visual Styles', 'zehoro-toolkit' ), 'manage_options', 'zehoro-styles', [ $this, 'render_styles_settings_page' ] );
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'zehoro-' ) === false && strpos( $hook, 'lkst-' ) === false ) return;
		wp_enqueue_style( 'zehoro-admin-css', ZEHORO_URL . 'assets/admin.css', [], ZEHORO_VERSION );

		// Modules-page-only assets (filter UX).
		if ( strpos( $hook, 'lkst-dashboard' ) !== false || strpos( $hook, 'zehoro-dashboard' ) !== false ) {
			wp_enqueue_style( 'zehoro-modules-admin', ZEHORO_URL . 'assets/admin/modules.css', [ 'dashicons' ], ZEHORO_VERSION );
			wp_enqueue_script( 'zehoro-modules-admin', ZEHORO_URL . 'assets/admin/modules.js', [], ZEHORO_VERSION, true );
			wp_localize_script( 'zehoro-modules-admin', 'zehoroModulesAdmin', [
				'storageKey' => 'zehoroModuleSettings',
				'defaults'   => [
					'search' => '',
					'status' => 'all',
					'layout' => 'grid',
				],
			] );
		}

		if ( strpos( $hook, 'zehoro-styles' ) !== false ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'zehoro-admin-js', ZEHORO_URL . 'assets/admin.js', [ 'wp-color-picker', 'jquery' ], ZEHORO_VERSION, true );
		}
	}

	/**
	 * Deprecated. Group categorisation lives in Plugin::register_module() now
	 * — modules are tagged with a 'group' field at registration time via the
	 * auto-detection map in Plugin::detect_group(). Kept here only as a
	 * pass-through so any callers we missed in the refactor don't break.
	 *
	 * @deprecated Since v1.10.0 — read $module['group'] off the registry instead.
	 */
	public static function infer_category( string $slug ): string {
		return Plugin::get_registered_modules()[ $slug ]['group'] ?? 'other';
	}

	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// POST handler: save modules, then redirect (POST-Redirect-GET pattern).
		// Without a redirect the user can re-submit by refreshing, and the browser
		// shows a "resubmit form?" warning.
		if ( isset( $_POST['zehoro_save_modules'] ) && check_admin_referer( 'zehoro_modules_action', 'zehoro_modules_nonce' ) ) {
			$new_active = isset( $_POST['modules'] ) ? array_keys( $_POST['modules'] ) : [];
			update_option( 'zehoro_active_modules', $new_active );
			wp_safe_redirect( add_query_arg( [ 'page' => 'zehoro-dashboard', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$registered = Plugin::get_registered_modules();
		$default_active = array_keys( array_filter( $registered, function($m) { return ! empty( $m['default'] ); } ) );
		$active = \Zehoro\Utils\Option::get( 'zehoro_active_modules', $default_active );

		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="updated notice is-dismissible"><p>' . esc_html__( 'Modules updated successfully.', 'zehoro-toolkit' ) . '</p></div>';
		}

		$modules = [];
		$active_count = 0;
		$pro_count    = 0;
		foreach ( $registered as $slug => $data ) {
			$is_active = in_array( $slug, $active, true );
			$tier      = $data['tier'] ?? 'free';
			$modules[ $slug ] = [
				'title'         => $data['title'] ?? $slug,
				'desc'          => $data['desc'] ?? '',
				'settings_link' => ! empty( $data['settings_page'] ) ? admin_url( 'admin.php?page=' . $data['settings_page'] ) : '',
				'is_active'     => $is_active,
				'group'         => $data['group'] ?? 'other',
				'tier'          => $tier,
				'docs'          => $data['docs'] ?? '',
				'keywords'      => $data['keywords'] ?? [],
				'order'         => (int) ( $data['order'] ?? 100 ),
			];
			if ( $is_active ) $active_count++;
			if ( $tier === 'pro' ) $pro_count++;
		}
		$total = count( $modules );

		// Sort within each group by 'order' (lower first), then by title.
		uasort( $modules, function( $a, $b ) {
			$cmp = $a['order'] <=> $b['order'];
			return $cmp !== 0 ? $cmp : strcasecmp( $a['title'], $b['title'] );
		} );

		// Canonical group order + display labels — source of truth is in
		// Plugin::GROUPS so the modules registry, dashboard, and (eventually)
		// the REST API all agree on the taxonomy.
		$category_order = Plugin::GROUPS;
		foreach ( $category_order as $k => $label ) {
			$category_order[ $k ] = __( $label, 'zehoro-toolkit' );
		}

		// Bucket modules by group, preserving sort order within each.
		$grouped = array_fill_keys( array_keys( $category_order ), [] );
		foreach ( $modules as $slug => $data ) {
			$cat = isset( $grouped[ $data['group'] ] ) ? $data['group'] : 'other';
			$grouped[ $cat ][ $slug ] = $data;
		}
		// Drop empty groups so the headings only render where there's content.
		$grouped = array_filter( $grouped, fn( $g ) => ! empty( $g ) );
		?>
		<div class="wrap lkst-dashboard">
			<h1><?php esc_html_e( 'Zehoro Toolkit — Modules', 'zehoro-toolkit' ); ?></h1>
			<p><?php esc_html_e( 'Enable or disable specific features of the toolkit. Only active modules load their code.', 'zehoro-toolkit' ); ?></p>

			<div class="zehoro-module-filters">
				<div class="zehoro-module-filters__search">
					<span class="dashicons dashicons-search"></span>
					<input
						type="search"
						id="zehoro-modules-search"
						class="zehoro-module-filters__search-input"
						placeholder="<?php esc_attr_e( 'Search modules…', 'zehoro-toolkit' ); ?>"
						autocomplete="off"
						aria-label="<?php esc_attr_e( 'Search modules', 'zehoro-toolkit' ); ?>"
					>
					<button type="button" class="zehoro-module-filters__search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'zehoro-toolkit' ); ?>">×</button>
				</div>
				<div class="zehoro-module-filters__status" role="tablist" aria-label="<?php esc_attr_e( 'Filter by status', 'zehoro-toolkit' ); ?>">
					<button type="button" class="zehoro-status-pill" data-status="all"      aria-pressed="true"><?php  esc_html_e( 'All',      'zehoro-toolkit' ); ?> <span class="zehoro-status-pill__count">(<?php echo (int) $total; ?>)</span></button>
					<button type="button" class="zehoro-status-pill" data-status="active"   aria-pressed="false"><?php esc_html_e( 'Active',   'zehoro-toolkit' ); ?> <span class="zehoro-status-pill__count">(<?php echo (int) $active_count; ?>)</span></button>
					<button type="button" class="zehoro-status-pill" data-status="inactive" aria-pressed="false"><?php esc_html_e( 'Inactive', 'zehoro-toolkit' ); ?> <span class="zehoro-status-pill__count">(<?php echo (int) ( $total - $active_count ); ?>)</span></button>
					<button type="button" class="zehoro-status-pill" data-status="free"     aria-pressed="false"><?php esc_html_e( 'Free',     'zehoro-toolkit' ); ?> <span class="zehoro-status-pill__count">(<?php echo (int) ( $total - $pro_count ); ?>)</span></button>
					<button type="button" class="zehoro-status-pill" data-status="pro"      aria-pressed="false"><?php esc_html_e( 'Pro',      'zehoro-toolkit' ); ?> <span class="zehoro-status-pill__count">(<?php echo (int) $pro_count; ?>)</span></button>
				</div>
				<div class="zehoro-module-filters__layout" role="tablist" aria-label="<?php esc_attr_e( 'Layout', 'zehoro-toolkit' ); ?>">
					<button type="button" class="zehoro-module-filters__layout-button" data-layout="grid" aria-pressed="true"  aria-label="<?php esc_attr_e( 'Grid layout', 'zehoro-toolkit' ); ?>" title="<?php esc_attr_e( 'Grid', 'zehoro-toolkit' ); ?>">
						<span class="dashicons dashicons-grid-view"></span>
					</button>
					<button type="button" class="zehoro-module-filters__layout-button" data-layout="list" aria-pressed="false" aria-label="<?php esc_attr_e( 'List layout', 'zehoro-toolkit' ); ?>" title="<?php esc_attr_e( 'List', 'zehoro-toolkit' ); ?>">
						<span class="dashicons dashicons-list-view"></span>
					</button>
				</div>
				<div class="zehoro-module-filters__total">
					<?php
					printf(
						/* translators: 1: visible count 2: total count */
						esc_html__( 'Showing %1$s of %2$s', 'zehoro-toolkit' ),
						'<span id="zehoro-modules-result-visible">' . (int) $total . '</span>',
						(int) $total
					);
					?>
				</div>
			</div>
			<div class="zehoro-live-region" role="status" aria-live="polite"></div>

			<form method="post" action="">
				<?php wp_nonce_field( 'zehoro_modules_action', 'zehoro_modules_nonce' ); ?>
				<div id="zehoro-modules-grid">
				<?php foreach ( $grouped as $cat_slug => $cat_modules ) : ?>
					<section
						class="zehoro-module-category"
						data-category-slug="<?php echo esc_attr( $cat_slug ); ?>"
					>
						<h2 class="zehoro-module-category__title">
							<?php echo esc_html( $category_order[ $cat_slug ] ?? ucfirst( $cat_slug ) ); ?>
							<span class="zehoro-module-category__count">· <?php echo (int) count( $cat_modules ); ?></span>
						</h2>
						<div class="lkst-modules-grid zehoro-modules--grid">
						<?php foreach ( $cat_modules as $slug => $data ) :
							$is_active = $data['is_active'];
							$tier      = $data['tier'];
							// Search haystack = slug + title + description + keywords.
							// Keywords give modules a per-author "make this findable" surface.
							$haystack  = strtolower(
								$slug . ' ' . $data['title'] . ' ' . $data['desc'] . ' ' . implode( ' ', $data['keywords'] )
							);
						?>
							<div
								class="lkst-module-card <?php echo $is_active ? 'active' : 'inactive'; ?> tier-<?php echo esc_attr( $tier ); ?>"
								data-module-slug="<?php echo esc_attr( $slug ); ?>"
								data-module-haystack="<?php echo esc_attr( $haystack ); ?>"
								data-module-active="<?php echo $is_active ? '1' : '0'; ?>"
								data-module-tier="<?php echo esc_attr( $tier ); ?>"
								data-module-group="<?php echo esc_attr( $data['group'] ); ?>"
							>
								<div class="lkst-module-header">
									<h3 class="lkst-module-title">
										<?php echo esc_html( $data['title'] ); ?>
										<?php if ( $tier === 'pro' ) : ?>
											<span class="lkst-tier-badge lkst-tier-badge--pro" style="display:inline-block;font-size:9px;font-weight:700;color:#fff;background:#8a1f2b;padding:1px 6px;border-radius:8px;margin-left:6px;text-transform:uppercase;letter-spacing:0.5px;vertical-align:1px;">PRO</span>
										<?php endif; ?>
									</h3>
									<label class="lkst-switch">
										<input type="checkbox" name="modules[<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( $is_active ); ?>>
										<span class="lkst-slider"></span>
									</label>
								</div>
								<p class="lkst-module-desc"><?php echo esc_html( $data['desc'] ); ?></p>
								<div class="lkst-module-footer">
									<?php if ( ! empty( $data['settings_link'] ) ) : ?>
										<a href="<?php echo esc_url( $data['settings_link'] ); ?>" class="lkst-configure-link">
											<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Configure', 'zehoro-toolkit' ); ?>
										</a>
									<?php else : ?>
										<span style="color:#a7aaad;font-style:italic;font-size:12px;"><?php esc_html_e( 'No settings needed', 'zehoro-toolkit' ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $data['docs'] ) ) : ?>
										<a href="<?php echo esc_url( $data['docs'] ); ?>" target="_blank" rel="noopener" style="margin-left:auto;font-size:12px;color:#646970;text-decoration:none;" title="<?php esc_attr_e( 'Documentation', 'zehoro-toolkit' ); ?>">
											<span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;"></span>
										</a>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
				</div>
				<div id="zehoro-modules-empty" class="zehoro-no-modules">
					<?php esc_html_e( 'No modules match the current filter.', 'zehoro-toolkit' ); ?>
				</div>
				<p class="submit">
					<input type="submit" name="zehoro_save_modules" class="button button-primary" value="<?php esc_attr_e( 'Save Module Settings', 'zehoro-toolkit' ); ?>">
				</p>
			</form>
		</div>

		<?php /* Filter UX JS lives in assets/admin/modules.js — enqueued by enqueue_assets(). */ ?>
		<?php
	}

	public function render_styles_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Visual Styles', 'zehoro-toolkit' ); ?></h1>
			<p><?php esc_html_e( 'Customize the colors used across all toolkit modules.', 'zehoro-toolkit' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'zehoro_styles_group' ); ?>
				<table class="form-table">
					<tr><th><label for="zehoro_color_primary"><?php esc_html_e( 'Primary Brand Color', 'zehoro-toolkit' ); ?></label></th>
						<td><input type="text" id="zehoro_color_primary" name="zehoro_color_primary" value="<?php echo esc_attr( \Zehoro\Utils\Option::get( 'zehoro_color_primary', '#E8A020' ) ); ?>" class="lkst-color-picker"></td></tr>
					<tr><th><label for="zehoro_color_primary_contrast"><?php esc_html_e( 'Primary Contrast Color', 'zehoro-toolkit' ); ?></label></th>
						<td><input type="text" id="zehoro_color_primary_contrast" name="zehoro_color_primary_contrast" value="<?php echo esc_attr( \Zehoro\Utils\Option::get( 'zehoro_color_primary_contrast', '#0F1A2E' ) ); ?>" class="lkst-color-picker"></td></tr>
					<tr><th><label for="zehoro_color_secondary"><?php esc_html_e( 'Secondary Brand Color', 'zehoro-toolkit' ); ?></label></th>
						<td><input type="text" id="zehoro_color_secondary" name="zehoro_color_secondary" value="<?php echo esc_attr( \Zehoro\Utils\Option::get( 'zehoro_color_secondary', '#1ECFC4' ) ); ?>" class="lkst-color-picker"></td></tr>
					<tr><th><label for="zehoro_color_bg_dark"><?php esc_html_e( 'Dark Background', 'zehoro-toolkit' ); ?></label></th>
						<td><input type="text" id="zehoro_color_bg_dark" name="zehoro_color_bg_dark" value="<?php echo esc_attr( \Zehoro\Utils\Option::get( 'zehoro_color_bg_dark', '#0F1A2E' ) ); ?>" class="lkst-color-picker"></td></tr>
					<tr><th><label for="zehoro_color_bg_light"><?php esc_html_e( 'Light Background', 'zehoro-toolkit' ); ?></label></th>
						<td><input type="text" id="zehoro_color_bg_light" name="zehoro_color_bg_light" value="<?php echo esc_attr( \Zehoro\Utils\Option::get( 'zehoro_color_bg_light', '#F5F0E8' ) ); ?>" class="lkst-color-picker"></td></tr>
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
			<h1><?php esc_html_e( 'RSS Feed Support', 'zehoro-toolkit' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'zehoro_rss_group' ); ?>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Include Post Types', 'zehoro-toolkit' ); ?></th>
						<td><?php
							$selected = \Zehoro\Utils\Option::get( 'zehoro_rss_post_types', [ 'post' ] );
							foreach ( $post_types as $slug => $pt ) :
								if ( in_array( $slug, $exclude, true ) ) continue;
								?>
								<label style="display:block;margin-bottom:5px;">
									<input type="checkbox" name="zehoro_rss_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected, true ) ); ?>>
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
			<h1><?php esc_html_e( 'Author Box Settings', 'zehoro-toolkit' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'zehoro_author_box_group' ); ?>
				<h2><?php esc_html_e( 'CTA Buttons', 'zehoro-toolkit' ); ?></h2>
				<table class="form-table">
					<tr><th><label for="zehoro_cta_p_label"><?php esc_html_e( 'Primary button label', 'zehoro-toolkit' ); ?></label></th>
						<td><input type="text" id="zehoro_cta_p_label" name="zehoro_cta_primary_label" value="<?php echo esc_attr( \Zehoro\Utils\Option::get( 'zehoro_cta_primary_label', 'Read the articles' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="zehoro_cta_p_url"><?php esc_html_e( 'Primary button URL', 'zehoro-toolkit' ); ?></label></th>
						<td><input type="text" id="zehoro_cta_p_url" name="zehoro_cta_primary_url" value="<?php echo esc_attr( \Zehoro\Utils\Option::get( 'zehoro_cta_primary_url', '/blog/' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="zehoro_cta_s_label"><?php esc_html_e( 'Secondary button label', 'zehoro-toolkit' ); ?></label></th>
						<td><input type="text" id="zehoro_cta_s_label" name="zehoro_cta_secondary_label" value="<?php echo esc_attr( \Zehoro\Utils\Option::get( 'zehoro_cta_secondary_label', 'Get the newsletter' ) ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="zehoro_cta_s_url"><?php esc_html_e( 'Secondary button URL', 'zehoro-toolkit' ); ?></label></th>
						<td><input type="text" id="zehoro_cta_s_url" name="zehoro_cta_secondary_url" value="<?php echo esc_attr( \Zehoro\Utils\Option::get( 'zehoro_cta_secondary_url', '#newsletter' ) ); ?>" class="regular-text"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}