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
		// A Pro (or any) add-on can claim the landing page by hooking
		// `zehoro_landing` (e.g. Pro's "Start Here" home). When nothing claims
		// it, the landing IS the Modules grid, exactly as before — so a Free-only
		// install is unchanged. Pro registers its hook on plugins_loaded, before
		// this admin_menu@9 runs, so this check is reliable.
		$has_landing = has_action( 'zehoro_landing' );

		add_menu_page(
			__( 'Zehoro Toolkit', 'zehoro-toolkit' ),
			__( 'Zehoro', 'zehoro-toolkit' ),
			'manage_options',
			'zehoro-dashboard',
			[ $this, 'render_landing' ],
			'dashicons-admin-generic',
			58 // mirrors where Pro's GSC used to sit so the icon stays in the same lane
		);

		add_submenu_page(
			'zehoro-dashboard',
			$has_landing ? __( 'Start Here', 'zehoro-toolkit' ) : __( 'Modules', 'zehoro-toolkit' ),
			$has_landing ? __( 'Start Here', 'zehoro-toolkit' ) : __( 'Modules', 'zehoro-toolkit' ),
			'manage_options',
			'zehoro-dashboard',
			[ $this, 'render_landing' ]
		);

		// When the landing is claimed, the Modules grid keeps its own item.
		if ( $has_landing ) {
			add_submenu_page(
				'zehoro-dashboard',
				__( 'Modules', 'zehoro-toolkit' ),
				__( 'Modules', 'zehoro-toolkit' ),
				'manage_options',
				'zehoro-modules',
				[ $this, 'render_dashboard_page' ]
			);
		}

		// Per-module settings pages: registered with parent_slug = null so they
		// stay accessible at ?page=<slug> but don't clutter the sidebar.
		// Discovery flows through the Modules grid's "Configure" links.
		// Phase 0 module refactor — task #36.
		if ( in_array( 'author_box', $this->active, true ) ) {
			add_submenu_page( null, __( 'Author Box Settings', 'zehoro-toolkit' ), __( 'Author Box', 'zehoro-toolkit' ), 'manage_options', 'zehoro-author-box', [ $this, 'render_author_box_settings_page' ] );
		}

		if ( in_array( 'table_of_contents', $this->active, true ) ) {
			add_submenu_page( null, __( 'Table of Contents', 'zehoro-toolkit' ), __( 'Table of Contents', 'zehoro-toolkit' ), 'manage_options', 'zehoro-toc-settings',
				[ new \Zehoro\Modules\TableOfContents(), 'render_page' ]
			);
		}

		if ( in_array( 'rss_support', $this->active, true ) ) {
			add_submenu_page( null, __( 'RSS Feed Settings', 'zehoro-toolkit' ), __( 'RSS Feed', 'zehoro-toolkit' ), 'manage_options', 'zehoro-rss-feed', [ $this, 'render_rss_feed_settings_page' ] );
		}

		if ( in_array( 'styles', $this->active, true ) ) {
			add_submenu_page( null, __( 'Visual Styles', 'zehoro-toolkit' ), __( 'Visual Styles', 'zehoro-toolkit' ), 'manage_options', 'zehoro-styles', [ $this, 'render_styles_settings_page' ] );
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'zehoro-' ) === false && strpos( $hook, 'lkst-' ) === false ) return;
		wp_enqueue_style( 'zehoro-admin-css', ZEHORO_URL . 'assets/admin.css', [], ZEHORO_VERSION );

		// Modules-page-only assets (filter UX + per-card REST toggle). The grid
		// renders on the landing (zehoro-dashboard) when unclaimed, and on its
		// own zehoro-modules page when Pro claims the landing.
		if ( strpos( $hook, 'lkst-dashboard' ) !== false || strpos( $hook, 'zehoro-dashboard' ) !== false || strpos( $hook, 'zehoro-modules' ) !== false ) {
			wp_enqueue_style( 'zehoro-modules-admin', ZEHORO_URL . 'assets/admin/modules.css', [ 'dashicons' ], ZEHORO_VERSION );
			wp_enqueue_script( 'zehoro-modules-admin', ZEHORO_URL . 'assets/admin/modules.js', [], ZEHORO_VERSION, true );
			wp_localize_script( 'zehoro-modules-admin', 'zehoroModulesAdmin', [
				'storageKey' => 'zehoroModuleSettings',
				'defaults'   => [
					'search' => '',
					'status' => 'all',
					'layout' => 'grid',
					'group'  => 'all',
				],
				'rest'       => [
					'root'        => esc_url_raw( rest_url( 'zehoro/v1/' ) ),
					'nonce'       => wp_create_nonce( 'wp_rest' ),
					'toggleRoute' => 'modules/{slug}/toggle',
					'bulkRoute'   => 'modules/bulk',
				],
				'i18n'       => [
					'toggleFailed'       => __( 'Could not save — check your connection and try again.', 'zehoro-toolkit' ),
					/* translators: {scope} is "all modules" or a category name */
					'bulkEnableConfirm'  => __( 'Enable {scope}?', 'zehoro-toolkit' ),
					'bulkDisableConfirm' => __( 'Disable {scope}? Their features stop working until re-enabled.', 'zehoro-toolkit' ),
					'allModules'         => __( 'all modules', 'zehoro-toolkit' ),
					/* translators: %s: category name */
					'groupModules'       => __( 'all modules in “{group}”', 'zehoro-toolkit' ),
					/* translators: {count}: module count, {title}: preset name */
					'presetConfirm'      => __( 'Enable the {count} modules in “{title}”? Nothing will be disabled.', 'zehoro-toolkit' ),
				],
			] );
		}

		if ( strpos( $hook, 'zehoro-styles' ) !== false ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'zehoro-admin-js', ZEHORO_URL . 'assets/admin.js', [ 'wp-color-picker', 'jquery' ], ZEHORO_VERSION, true );
		}
	}

	/**
	 * Persona presets — curated module sets for the site archetypes we
	 * design for (see the persona test in zorasi-roadmaps loop-first
	 * spec). Slugs that aren't registered on this install (e.g. Pro
	 * modules without Pro active) are silently dropped, so counts shown
	 * to the user are always real. Extend or replace via the
	 * `zehoro_module_presets` filter.
	 *
	 * @return array<string, array{title: string, desc: string, slugs: string[]}>
	 */
	private function presets(): array {
		$presets = [
			'affiliate' => [
				'title' => __( 'Affiliate marketer', 'zehoro-toolkit' ),
				'desc'  => __( 'Product boxes, comparisons, verdicts and disclosure for monetised reviews — plus the GSC loop with affiliate-click conversion tracking, so you see which post earns.', 'zehoro-toolkit' ),
				'slugs' => [
					'product_box', 'product_roundup', 'product_verdict', 'comparison_table',
					'versus_box', 'review_box', 'pros_cons', 'pros_cons_schema', 'coupon_box',
					'inline_product', 'disclosure', 'table_of_contents', 'last_updated',
					'article_schema', 'entity_map', 'freshness_log', 'content_stream',
					'google_search_console', 'ctr_rescue', 'cannibalisation_check',
					'refresh_trigger', 'orphan_check', 'edit_log',
				],
			],
			'publisher' => [
				'title' => __( 'Content publisher / blogger', 'zehoro-toolkit' ),
				'desc'  => __( 'E-E-A-T signals (author box, freshness, FAQ, schema), editorial blocks, the rewrite workflow and the full GSC loop — which post to fix next, and proof the fix worked.', 'zehoro-toolkit' ),
				'slugs' => [
					'author_box', 'table_of_contents', 'tldr', 'faq', 'callout', 'stat_callout',
					'steps', 'testimonial', 'last_updated', 'freshness_log', 'article_schema',
					'entity_map', 'rewrite_context', 'ai_visibility', 'inline_subscribe',
					'category_pills', 'google_search_console', 'ctr_rescue',
					'cannibalisation_check', 'refresh_trigger', 'orphan_check', 'edit_log',
				],
			],
			'local' => [
				'title' => __( 'Local service business', 'zehoro-toolkit' ),
				'desc'  => __( 'Content that converts to enquiries: sticky CTA, WhatsApp/form conversion tracking via the stream, trust blocks — and the GSC loop tuned to service pages.', 'zehoro-toolkit' ),
				'slugs' => [
					'google_search_console', 'ctr_rescue', 'cannibalisation_check',
					'refresh_trigger', 'orphan_check', 'edit_log', 'entity_map',
					'content_stream', 'sticky_bar', 'faq', 'testimonial', 'steps',
					'article_schema', 'last_updated', 'freshness_log', 'rewrite_context',
					'ai_visibility', 'table_of_contents',
				],
			],
		];

		$presets    = apply_filters( 'zehoro_module_presets', $presets );
		$registered = array_keys( Plugin::get_registered_modules() );

		foreach ( $presets as $key => $preset ) {
			$slugs = array_values( array_intersect( (array) ( $preset['slugs'] ?? [] ), $registered ) );
			if ( [] === $slugs ) {
				unset( $presets[ $key ] );
				continue;
			}
			$presets[ $key ]['slugs'] = $slugs;
		}

		return $presets;
	}

	/** Collapsible "Recommended setups" panel above the filter bar. */
	private function render_presets(): void {
		$presets = $this->presets();
		if ( [] === $presets ) return;
		?>
		<details class="zehoro-presets">
			<summary><?php esc_html_e( 'Recommended setups — enable a curated module set for your kind of site', 'zehoro-toolkit' ); ?></summary>
			<div class="zehoro-presets__grid">
				<?php foreach ( $presets as $preset ) : ?>
					<div class="zehoro-presets__card">
						<h3><?php echo esc_html( $preset['title'] ); ?></h3>
						<p><?php echo esc_html( $preset['desc'] ); ?></p>
						<button type="button" class="button button-primary zehoro-preset-btn"
							data-preset-title="<?php echo esc_attr( $preset['title'] ); ?>"
							data-preset-slugs="<?php echo esc_attr( (string) wp_json_encode( $preset['slugs'] ) ); ?>">
							<?php
							/* translators: %d: number of modules in the preset */
							echo esc_html( sprintf( __( 'Enable these %d modules', 'zehoro-toolkit' ), count( $preset['slugs'] ) ) );
							?>
						</button>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="description" style="margin:10px 2px 2px;"><?php esc_html_e( 'Presets only enable modules — nothing you already use gets turned off. You can fine-tune with the switches below afterwards.', 'zehoro-toolkit' ); ?></p>
		</details>
		<?php
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

	/**
	 * The Zehoro landing page. An add-on (Pro's "Start Here" home) can claim it
	 * by hooking `zehoro_landing`; otherwise it's the Modules grid — so a
	 * Free-only install is unchanged.
	 */
	public function render_landing(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		if ( has_action( 'zehoro_landing' ) ) {
			do_action( 'zehoro_landing' );
			return;
		}
		$this->render_dashboard_page();
	}

	/**
	 * Suite groups — commodity module groups that collapse into ONE card on the
	 * grid (the Kadence-Blocks model: one "Blocks" card, individual blocks
	 * toggled inside). group => [ label, desc ]. Filterable.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function suite_defs(): array {
		return (array) apply_filters( 'zehoro/module_suites', [
			'editorial_blocks' => [
				'label' => __( 'Blocks', 'zehoro-toolkit' ),
				'desc'  => __( 'Ready-made content blocks for your posts — callouts, FAQs, pros & cons, comparison and review boxes, and more. Turn the set on, then switch off any you do not use.', 'zehoro-toolkit' ),
			],
			'schema' => [
				'label' => __( 'Schema', 'zehoro-toolkit' ),
				'desc'  => __( 'Structured data that helps Google show rich results for your articles, FAQs and how-tos. Pauses automatically if a dedicated SEO plugin already handles it.', 'zehoro-toolkit' ),
			],
			'reading_ux' => [
				'label' => __( 'Reading & Trust', 'zehoro-toolkit' ),
				'desc'  => __( 'Reader-facing touches that build trust — a table of contents, last-updated and freshness stamps, and disclosure notices.', 'zehoro-toolkit' ),
			],
		] );
	}

	/**
	 * Partition the per-module list into suite cards (collapsed commodity
	 * groups) + regular module cards. Pure → unit-testable.
	 *
	 * @param array<string,array<string,mixed>>  $modules    slug => card data (incl. group / is_active / tier / title)
	 * @param array<string,array<string,string>> $suite_defs group => [label, desc]
	 * @return array{regular: array<string,array<string,mixed>>, suites: array<string,array<string,mixed>>}
	 */
	public static function collapse_suites( array $modules, array $suite_defs ): array {
		$regular = [];
		$suites  = [];
		foreach ( $modules as $slug => $data ) {
			$g = (string) ( $data['group'] ?? 'other' );
			if ( ! isset( $suite_defs[ $g ] ) ) {
				$regular[ $slug ] = $data;
				continue;
			}
			if ( ! isset( $suites[ $g ] ) ) {
				$suites[ $g ] = [
					'suite'   => $g,
					'title'   => (string) $suite_defs[ $g ]['label'],
					'desc'    => (string) $suite_defs[ $g ]['desc'],
					'group'   => $g,
					'members' => [],
					'active'  => 0,
					'total'   => 0,
				];
			}
			$suites[ $g ]['members'][] = [
				'slug'      => $slug,
				'title'     => (string) ( $data['title'] ?? $slug ),
				'is_active' => ! empty( $data['is_active'] ),
			];
			$suites[ $g ]['total']++;
			if ( ! empty( $data['is_active'] ) ) $suites[ $g ]['active']++;
		}
		foreach ( $suites as &$s ) {
			usort( $s['members'], fn( $a, $b ) => strcasecmp( (string) $a['title'], (string) $b['title'] ) );
		}
		unset( $s );
		return [ 'regular' => $regular, 'suites' => $suites ];
	}

	/** Render one suite card: a master toggle + an expandable list of sub-toggles. */
	private function render_suite_card( array $s ): void {
		$members  = (array) $s['members'];
		$slugs    = implode( ',', array_map( static fn( $m ) => $m['slug'], $members ) );
		$active   = (int) $s['active'];
		$total    = (int) $s['total'];
		$names    = implode( ' ', array_map( static fn( $m ) => $m['slug'] . ' ' . $m['title'], $members ) );
		$haystack = strtolower( $s['suite'] . ' ' . $s['title'] . ' ' . $s['desc'] . ' ' . $names );
		?>
		<div
			class="lkst-module-card zehoro-suite-card <?php echo $active > 0 ? 'active' : 'inactive'; ?> tier-free"
			data-module-slug="suite_<?php echo esc_attr( $s['suite'] ); ?>"
			data-suite="<?php echo esc_attr( $s['suite'] ); ?>"
			data-suite-members="<?php echo esc_attr( $slugs ); ?>"
			data-module-haystack="<?php echo esc_attr( $haystack ); ?>"
			data-module-active="<?php echo $active > 0 ? '1' : '0'; ?>"
			data-module-tier="free"
			data-module-group="<?php echo esc_attr( $s['group'] ); ?>"
		>
			<div class="lkst-module-header">
				<h3 class="lkst-module-title">
					<?php echo esc_html( $s['title'] ); ?>
					<span class="lkst-type-badge lkst-type-badge--suite"><?php echo esc_html( sprintf( /* translators: %d: number of features */ _n( '%d feature', '%d features', $total, 'zehoro-toolkit' ), $total ) ); ?></span>
				</h3>
				<label class="lkst-switch zehoro-suite-master">
					<input type="checkbox" <?php checked( $active === $total && $total > 0 ); ?>>
					<span class="lkst-slider"></span>
				</label>
			</div>
			<p class="lkst-module-desc"><?php echo esc_html( $s['desc'] ); ?></p>
			<details class="zehoro-suite-details">
				<summary class="zehoro-suite-summary"><span class="zehoro-suite-count"><?php echo (int) $active; ?></span><?php printf( esc_html__( ' of %d on — manage individually', 'zehoro-toolkit' ), (int) $total ); ?></summary>
				<ul class="zehoro-suite-members">
					<?php foreach ( $members as $m ) : ?>
						<li class="zehoro-suite-member" data-module-slug="<?php echo esc_attr( $m['slug'] ); ?>">
							<label class="lkst-switch lkst-switch--sm">
								<input type="checkbox" name="modules[<?php echo esc_attr( $m['slug'] ); ?>]" value="1" <?php checked( $m['is_active'] ); ?>>
								<span class="lkst-slider"></span>
							</label>
							<span class="zehoro-suite-member__title"><?php echo esc_html( $m['title'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		</div>
		<?php
	}

	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// POST handler: save modules, then redirect (POST-Redirect-GET pattern).
		// Without a redirect the user can re-submit by refreshing, and the browser
		// shows a "resubmit form?" warning.
		if ( isset( $_POST['zehoro_save_modules'] ) && check_admin_referer( 'zehoro_modules_action', 'zehoro_modules_nonce' ) ) {
			// Sanitise + intersect against the real registry so junk keys can't be
			// persisted (parity with the REST bulk route). Nonce + capability are
			// already enforced above; this is defence-in-depth for the noscript path.
			$posted     = ( isset( $_POST['modules'] ) && is_array( $_POST['modules'] ) )
				? array_map( 'sanitize_key', array_keys( wp_unslash( $_POST['modules'] ) ) )
				: [];
			$new_active = array_values( array_intersect( $posted, array_keys( \Zehoro\Core\Plugin::get_registered_modules() ) ) );
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
			// Content Box is folded into Content Stream for Pro users — hide its
			// card when Stream is active. Stream owns box composition; Content Box
			// is its renderer, kept active as a dependency. Its settings stay
			// reachable from the Content Stream page.
			if ( 'content_box' === $slug && in_array( 'content_stream', $active, true ) ) {
				continue;
			}
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
				'type'          => $data['type'] ?? 'module',
				'needs'         => (array) ( $data['needs'] ?? [] ),
			];
			if ( $is_active ) $active_count++;
			if ( $tier === 'pro' ) $pro_count++;
		}
		$total = count( $modules );

		uasort( $modules, fn( $a, $b ) => strcasecmp( $a['title'], $b['title'] ) );

		// Collapse the commodity groups (Blocks / Schema / Reading & Trust) into
		// single suite cards — one card with sub-toggles inside (Kadence-Blocks
		// model), so the grid shows ~15 things, not ~50.
		$suite_defs = self::suite_defs();
		$partition  = self::collapse_suites( $modules, $suite_defs );

		// Combined, alphabetised render list (regular module cards + suite cards).
		$render_list = [];
		foreach ( $partition['regular'] as $slug => $d ) {
			$render_list[] = [ 'type' => 'module', 'slug' => $slug, 'data' => $d, 'sort' => (string) ( $d['title'] ?? $slug ) ];
		}
		foreach ( $partition['suites'] as $sk => $s ) {
			$render_list[] = [ 'type' => 'suite', 'data' => $s, 'sort' => (string) $s['title'] ];
		}
		usort( $render_list, fn( $a, $b ) => strcasecmp( $a['sort'], $b['sort'] ) );

		// Counts off the COLLAPSED view: a suite is one card, active if any member is on.
		$total        = count( $render_list );
		$active_count = 0;
		$pro_count    = 0;
		foreach ( $render_list as $item ) {
			if ( 'suite' === $item['type'] ) {
				if ( $item['data']['active'] > 0 ) $active_count++;
			} else {
				if ( ! empty( $item['data']['is_active'] ) ) $active_count++;
				if ( 'pro' === ( $item['data']['tier'] ?? 'free' ) ) $pro_count++;
			}
		}

		// Left-sidebar group nav counts, off the collapsed view (a suite = 1).
		$category_order = Plugin::group_labels(); // translated, extractable literals
		$group_counts   = array_fill_keys( array_keys( $category_order ), 0 );
		foreach ( $render_list as $item ) {
			$g = ( 'suite' === $item['type'] ) ? $item['data']['group'] : ( $item['data']['group'] ?? 'other' );
			if ( ! isset( $group_counts[ $g ] ) ) $g = 'other';
			$group_counts[ $g ]++;
		}
		$group_counts = array_filter( $group_counts ); // drop empty groups
		?>
		<div class="wrap lkst-dashboard">
			<h1><?php esc_html_e( 'Zehoro Toolkit — Modules', 'zehoro-toolkit' ); ?></h1>
			<p><?php esc_html_e( 'Enable or disable specific features of the toolkit. Only active modules load their code.', 'zehoro-toolkit' ); ?></p>

			<div class="zehoro-modules-layout">
			<aside class="zehoro-module-nav" aria-label="<?php esc_attr_e( 'Module groups', 'zehoro-toolkit' ); ?>">
				<ul class="zehoro-module-nav__list">
					<li>
						<a href="#" class="zehoro-module-nav__link" data-group="all" aria-current="true">
							<?php esc_html_e( 'All modules', 'zehoro-toolkit' ); ?>
							<span class="zehoro-module-nav__count">(<?php echo (int) $total; ?>)</span>
						</a>
					</li>
					<?php foreach ( $group_counts as $cat_slug => $count ) : ?>
						<li>
							<a href="#" class="zehoro-module-nav__link" data-group="<?php echo esc_attr( $cat_slug ); ?>" aria-current="false">
								<?php echo esc_html( $category_order[ $cat_slug ] ?? ucfirst( $cat_slug ) ); ?>
								<span class="zehoro-module-nav__count">(<?php echo (int) $count; ?>)</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</aside>
			<div class="zehoro-modules-main">
			<?php $this->render_presets(); ?>
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
				<div class="zehoro-module-filters__bulk" aria-label="<?php esc_attr_e( 'Bulk actions', 'zehoro-toolkit' ); ?>">
				<button type="button" class="button zehoro-bulk-btn" data-bulk="enable"><?php esc_html_e( 'Enable all', 'zehoro-toolkit' ); ?></button>
				<button type="button" class="button zehoro-bulk-btn" data-bulk="disable"><?php esc_html_e( 'Disable all', 'zehoro-toolkit' ); ?></button>
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
				<div id="zehoro-modules-grid" class="zehoro-modules--grid">
				<?php foreach ( $render_list as $item ) :
					if ( 'suite' === $item['type'] ) { $this->render_suite_card( $item['data'] ); continue; }
					$slug      = $item['slug'];
					$data      = $item['data'];
					$is_active = $data['is_active'];
					$tier      = $data['tier'];
					// Search haystack = slug + title + description + keywords.
					// Keywords give modules a per-author "make this findable" surface.
					$haystack  = strtolower(
						$slug . ' ' . $data['title'] . ' ' . $data['desc'] . ' ' . implode( ' ', $data['keywords'] )
						. ' ' . $data['type'] . ' ' . implode( ' ', $data['needs'] )
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
									<span class="lkst-tier-badge lkst-tier-badge--pro">PRO</span>
								<?php endif; ?>
								<?php if ( in_array( $data['type'], [ 'block', 'tool' ], true ) ) : ?>
									<span class="lkst-type-badge lkst-type-badge--<?php echo esc_attr( $data['type'] ); ?>"><?php echo esc_html( 'block' === $data['type'] ? __( 'Block', 'zehoro-toolkit' ) : __( 'Tool', 'zehoro-toolkit' ) ); ?></span>
								<?php endif; ?>
								<?php foreach ( $data['needs'] as $need ) :
									$nlabel = [ 'ai' => __( 'AI', 'zehoro-toolkit' ), 'gsc' => __( 'GSC', 'zehoro-toolkit' ) ][ $need ] ?? '';
									if ( '' === $nlabel ) continue; ?>
									<span class="lkst-need-badge lkst-need-badge--<?php echo esc_attr( $need ); ?>"><?php echo esc_html( $nlabel ); ?></span>
								<?php endforeach; ?>
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
								<span class="lkst-module-footer__hint"><?php esc_html_e( 'No settings needed', 'zehoro-toolkit' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $data['docs'] ) ) : ?>
								<a href="<?php echo esc_url( $data['docs'] ); ?>" target="_blank" rel="noopener" class="lkst-module-footer__docs" title="<?php esc_attr_e( 'Documentation', 'zehoro-toolkit' ); ?>">
									<span class="dashicons dashicons-external"></span>
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
				</div>
				<div id="zehoro-modules-empty" class="zehoro-no-modules">
					<?php esc_html_e( 'No modules match the current filter.', 'zehoro-toolkit' ); ?>
				</div>
				<noscript>
					<p class="submit">
						<input type="submit" name="zehoro_save_modules" class="button button-primary" value="<?php esc_attr_e( 'Save Module Settings', 'zehoro-toolkit' ); ?>">
					</p>
				</noscript>
			</form>
			</div><!-- /.zehoro-modules-main -->
			</div><!-- /.zehoro-modules-layout -->
		</div>

		<?php /* Filter UX JS lives in assets/admin/modules.js — enqueued by enqueue_assets(). */ ?>
		<?php
	}

	public function render_styles_settings_page(): void {
		?>
		<div class="wrap">
			<p style="margin-top:14px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=zehoro-dashboard' ) ); ?>" class="button button-secondary">&larr; <?php esc_html_e( 'Back to Modules', 'zehoro-toolkit' ); ?></a></p>
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
			<p style="margin-top:14px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=zehoro-dashboard' ) ); ?>" class="button button-secondary">&larr; <?php esc_html_e( 'Back to Modules', 'zehoro-toolkit' ); ?></a></p>
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
			<p style="margin-top:14px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=zehoro-dashboard' ) ); ?>" class="button button-secondary">&larr; <?php esc_html_e( 'Back to Modules', 'zehoro-toolkit' ); ?></a></p>
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