<?php
namespace LK\SiteToolkit\Admin;

use LK\SiteToolkit\Modules\ContentCTA;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin layer for the ContentCTA module.
 *
 * Extracted from ContentCTA so the module stays focused on its runtime
 * responsibilities (injection, rendering, shortcodes). All wp-admin hooks
 * live here: settings registration, meta-box, and the settings page UI.
 *
 * @package LK\SiteToolkit\Admin
 */
class CTAAdmin {

	private ContentCTA $cta;

	public function __construct( ContentCTA $cta ) {
		$this->cta = $cta;
	}

	public function init(): void {
		add_action( 'admin_init',     [ $this, 'register_settings' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post',      [ $this, 'save_meta_box' ] );
	}

	public function register_settings(): void {
		register_setting( 'lkst_content_cta_group', 'lkst_content_cta_settings', [
			'sanitize_callback' => [ $this->cta, 'sanitize_settings' ],
			'default'           => ContentCTA::get_defaults(),
		] );

		$settings   = get_option( 'lkst_content_cta_settings', [] );
		$post_types = ! empty( $settings['post_types'] ) ? $settings['post_types'] : [ 'post' ];
		foreach ( $post_types as $pt ) {
			register_post_meta( $pt, 'lkst_no_cta', [
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'boolean',
				'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
			] );
		}
	}

	public function add_meta_box(): void {
		$settings   = get_option( 'lkst_content_cta_settings', [] );
		$post_types = ! empty( $settings['post_types'] ) ? $settings['post_types'] : [ 'post' ];
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'lkst_no_cta_meta',
				__( 'Content CTAs', 'leokoo-site-toolkit' ),
				[ $this, 'render_meta_box' ],
				$pt,
				'side',
				'default'
			);
		}
	}

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'lkst_no_cta_nonce', 'lkst_no_cta_nonce' );
		$checked = (bool) get_post_meta( $post->ID, 'lkst_no_cta', true );
		?>
		<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
			<input type="checkbox" name="lkst_no_cta" value="1" style="margin-top:3px;" <?php checked( $checked ); ?>>
			<span><?php esc_html_e( 'Suppress all CTAs on this post', 'leokoo-site-toolkit' ); ?></span>
		</label>
		<p class="description" style="margin-top:6px;font-size:12px;"><?php esc_html_e( 'When checked, no CTAs (Power, Middle, Sidebar) will appear on this post.', 'leokoo-site-toolkit' ); ?></p>
		<?php
	}

	public function save_meta_box( int $post_id ): void {
		if ( ! isset( $_POST['lkst_no_cta_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lkst_no_cta_nonce'] ) ), 'lkst_no_cta_nonce' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		if ( ! empty( $_POST['lkst_no_cta'] ) ) {
			update_post_meta( $post_id, 'lkst_no_cta', true );
		} else {
			delete_post_meta( $post_id, 'lkst_no_cta' );
		}
	}

	public function render_page(): void {
		$s        = get_option( 'lkst_content_cta_settings', [] );
		$defaults = ContentCTA::get_defaults();
		$s        = array_replace_recursive( $defaults, is_array( $s ) ? $s : [] );

		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$pt_exclude = [ 'attachment', 'page', 'bricks_template', 'etch_template', 'elementor_library', 'ifso_triggers' ];

		$active_pts = $s['post_types'] ?? ['post'];
		$taxonomies = [];
		foreach ( $active_pts as $pt ) {
			$pt_taxonomies = get_object_taxonomies( $pt, 'objects' );
			foreach ( $pt_taxonomies as $tax_name => $tax_obj ) {
				if ( $tax_obj->public && $tax_obj->hierarchical ) {
					$taxonomies[] = $tax_name;
				}
			}
		}
		$taxonomies = array_unique( $taxonomies );
		if ( empty($taxonomies) ) $taxonomies = ['category'];

		$categories = get_terms( [
			'taxonomy'   => $taxonomies,
			'hide_empty' => false,
			'number'     => 500,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( is_wp_error( $categories ) ) $categories = [];
		?>
		<div class="wrap lkst-settings">
			<h1><?php esc_html_e( 'Content CTAs', 'leokoo-site-toolkit' ); ?></h1>
			<p><?php esc_html_e( 'Unified Power + Middle + Sidebar CTA management. Pages are always excluded from injection.', 'leokoo-site-toolkit' ); ?></p>

			<style>
				.lkst-tab-content { display: none; padding: 20px 0; }
				.lkst-tab-content.lkst-active { display: block; }
				.lkst-section { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px 24px; margin-bottom: 20px; }
				.lkst-section h3 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
				.lkst-field-row { display: flex; flex-direction: column; margin-bottom: 14px; }
				.lkst-field-row label { font-weight: 600; margin-bottom: 4px; }
				.lkst-field-row input[type="text"],
				.lkst-field-row input[type="number"],
				.lkst-field-row input[type="url"],
				.lkst-field-row select { max-width: 420px; }
				.lkst-field-row textarea { width: 100%; max-width: 580px; }
				.lkst-hint { color: #646970; font-size: 12px; margin-top: 3px; }
				.lkst-toggle-row { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
				.lkst-image-fields { margin-top: 10px; padding: 12px 16px; background: #f6f7f7; border-radius: 4px; }
				.lkst-bottom-custom-wrap { margin-top: 16px; padding: 16px; background: #f0f6fc; border-radius: 4px; border-left: 3px solid #007cba; }
				.lkst-override-body td { padding: 12px 16px 16px !important; background: #f9f9f9; }
			</style>

			<form method="post" action="options.php">
				<?php settings_fields( 'lkst_content_cta_group' ); ?>

				<h2 class="nav-tab-wrapper" id="lkst-tab-nav">
					<a href="#" class="nav-tab nav-tab-active" data-lkst-tab="lkst-general"><?php esc_html_e( 'General', 'leokoo-site-toolkit' ); ?></a>
					<a href="#" class="nav-tab" data-lkst-tab="lkst-power"><?php esc_html_e( 'Power CTA', 'leokoo-site-toolkit' ); ?></a>
					<a href="#" class="nav-tab" data-lkst-tab="lkst-middle"><?php esc_html_e( 'Middle CTA', 'leokoo-site-toolkit' ); ?></a>
					<a href="#" class="nav-tab" data-lkst-tab="lkst-inject"><?php esc_html_e( 'Content Injection', 'leokoo-site-toolkit' ); ?></a>
					<a href="#" class="nav-tab" data-lkst-tab="lkst-sidebar"><?php esc_html_e( 'Sidebar CTA', 'leokoo-site-toolkit' ); ?></a>
				</h2>

				<div id="lkst-general" class="lkst-tab-content lkst-active">
					<div class="lkst-section">
						<h3><?php esc_html_e( 'Active Post Types', 'leokoo-site-toolkit' ); ?></h3>
						<p class="description" style="margin-top:0;"><?php esc_html_e( 'Controls which post types show the sidebar CTA and have the Content CTAs admin meta box.', 'leokoo-site-toolkit' ); ?></p>
						<?php foreach ( $post_types as $slug => $pt ) :
							if ( in_array( $slug, $pt_exclude, true ) ) continue; ?>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox"
									   name="lkst_content_cta_settings[post_types][]"
									   value="<?php echo esc_attr( $slug ); ?>"
									   <?php checked( in_array( $slug, $s['post_types'], true ) ); ?>>
								<?php echo esc_html( $pt->label ); ?>
								<code style="margin-left:4px;"><?php echo esc_html( $slug ); ?></code>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="lkst-section">
						<h3><?php esc_html_e( 'Inline CTA Injection Post Types', 'leokoo-site-toolkit' ); ?></h3>
						<p class="description" style="margin-top:0;"><?php esc_html_e( 'Controls which post types get Power & Middle CTAs injected into their content. Typically post only.', 'leokoo-site-toolkit' ); ?></p>
						<?php foreach ( $post_types as $slug => $pt ) :
							if ( in_array( $slug, $pt_exclude, true ) ) continue; ?>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox"
									   name="lkst_content_cta_settings[inject_post_types][]"
									   value="<?php echo esc_attr( $slug ); ?>"
									   <?php checked( in_array( $slug, $s['inject_post_types'] ?? ['post'], true ) ); ?>>
								<?php echo esc_html( $pt->label ); ?>
								<code style="margin-left:4px;"><?php echo esc_html( $slug ); ?></code>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div id="lkst-power" class="lkst-tab-content">
					<div class="lkst-section">
						<h3><?php esc_html_e( 'Power CTA — Top', 'leokoo-site-toolkit' ); ?></h3>
						<div class="lkst-toggle-row">
							<input type="hidden" name="lkst_content_cta_settings[power][enabled]" value="0">
							<input type="checkbox" id="power_enabled"
								   name="lkst_content_cta_settings[power][enabled]" value="1"
								   <?php checked( $s['power']['enabled'] ); ?>>
							<label for="power_enabled"><strong><?php esc_html_e( 'Enable Power CTA', 'leokoo-site-toolkit' ); ?></strong></label>
						</div>
						<div class="lkst-field-row">
							<label for="power_paragraph"><?php esc_html_e( 'Insert after paragraph #', 'leokoo-site-toolkit' ); ?></label>
							<input type="number" id="power_paragraph" style="width:80px"
								   name="lkst_content_cta_settings[power][paragraph]"
								   value="<?php echo (int) $s['power']['paragraph']; ?>" min="1" max="60">
						</div>
						<?php $this->render_cta_fields( 'lkst_content_cta_settings[power]', $s['power'] ); ?>
					</div>

					<div class="lkst-section">
						<h3><?php esc_html_e( 'Power CTA — Bottom', 'leokoo-site-toolkit' ); ?></h3>
						<div class="lkst-toggle-row">
							<input type="hidden" name="lkst_content_cta_settings[power][bottom_enabled]" value="0">
							<input type="checkbox" id="power_bottom_enabled"
								   name="lkst_content_cta_settings[power][bottom_enabled]" value="1"
								   <?php checked( $s['power']['bottom_enabled'] ); ?>>
							<label for="power_bottom_enabled"><strong><?php esc_html_e( 'Enable Bottom Power CTA', 'leokoo-site-toolkit' ); ?></strong></label>
						</div>
						<div class="lkst-field-row">
							<label for="power_bottom_min_words"><?php esc_html_e( 'Minimum word count to trigger', 'leokoo-site-toolkit' ); ?></label>
							<input type="number" id="power_bottom_min_words" style="width:100px"
								   name="lkst_content_cta_settings[power][bottom_min_words]"
								   value="<?php echo (int) $s['power']['bottom_min_words']; ?>" min="0">
						</div>
						<div class="lkst-field-row">
							<label for="power_bottom_percent"><?php esc_html_e( 'Position — % of content reached', 'leokoo-site-toolkit' ); ?></label>
							<input type="number" id="power_bottom_percent" style="width:80px"
								   name="lkst_content_cta_settings[power][bottom_percent]"
								   value="<?php echo (int) $s['power']['bottom_percent']; ?>" min="10" max="95">
						</div>
						<div class="lkst-toggle-row" style="margin-top:16px;">
							<input type="hidden" name="lkst_content_cta_settings[power][bottom_custom]" value="0">
							<input type="checkbox" id="power_bottom_custom"
								   name="lkst_content_cta_settings[power][bottom_custom]" value="1"
								   <?php checked( $s['power']['bottom_custom'] ); ?>>
							<label for="power_bottom_custom"><?php esc_html_e( 'Use different content for the bottom CTA', 'leokoo-site-toolkit' ); ?></label>
						</div>
						<div class="lkst-bottom-custom-wrap" id="lkst-bottom-custom-fields"
							 style="<?php echo $s['power']['bottom_custom'] ? '' : 'display:none'; ?>">
							<p style="margin-top:0;"><strong><?php esc_html_e( 'Custom bottom CTA content', 'leokoo-site-toolkit' ); ?></strong></p>
							<?php $this->render_cta_fields( 'lkst_content_cta_settings[power]', $s['power'], 'bottom_' ); ?>
						</div>
					</div>

					<div class="lkst-section">
						<h3><?php esc_html_e( 'Category Overrides', 'leokoo-site-toolkit' ); ?></h3>
						<?php $this->render_cat_overrides( 'power', $s['power']['cat_overrides'] ?? [], $categories ); ?>
					</div>
				</div>

				<div id="lkst-middle" class="lkst-tab-content">
					<div class="lkst-section">
						<h3><?php esc_html_e( 'Middle CTA', 'leokoo-site-toolkit' ); ?></h3>
						<div class="lkst-toggle-row">
							<input type="hidden" name="lkst_content_cta_settings[middle][enabled]" value="0">
							<input type="checkbox" id="middle_enabled"
								   name="lkst_content_cta_settings[middle][enabled]" value="1"
								   <?php checked( $s['middle']['enabled'] ); ?>>
							<label for="middle_enabled"><strong><?php esc_html_e( 'Enable Middle CTA', 'leokoo-site-toolkit' ); ?></strong></label>
						</div>
						<div class="lkst-field-row">
							<label for="middle_min_words"><?php esc_html_e( 'Minimum word count', 'leokoo-site-toolkit' ); ?></label>
							<input type="number" id="middle_min_words" style="width:100px"
								   name="lkst_content_cta_settings[middle][min_words]"
								   value="<?php echo (int) $s['middle']['min_words']; ?>" min="0">
						</div>
						<div class="lkst-field-row">
							<label for="middle_paragraph"><?php esc_html_e( 'Insert after paragraph #', 'leokoo-site-toolkit' ); ?></label>
							<input type="number" id="middle_paragraph" style="width:80px"
								   name="lkst_content_cta_settings[middle][paragraph]"
								   value="<?php echo (int) $s['middle']['paragraph']; ?>" min="1" max="60">
						</div>
						<?php $this->render_cta_fields( 'lkst_content_cta_settings[middle]', $s['middle'] ); ?>
					</div>
					<div class="lkst-section">
						<h3><?php esc_html_e( 'Category Overrides', 'leokoo-site-toolkit' ); ?></h3>
						<?php $this->render_cat_overrides( 'middle', $s['middle']['cat_overrides'] ?? [], $categories ); ?>
					</div>
				</div>

				<div id="lkst-inject" class="lkst-tab-content">
					<div class="lkst-section">
						<h3><?php esc_html_e( 'Category-Based Content Injection', 'leokoo-site-toolkit' ); ?></h3>
						<p><em><?php esc_html_e( '🚧 Roadmap: Full per-category rule management coming in a future update.', 'leokoo-site-toolkit' ); ?></em></p>
					</div>
				</div>

				<div id="lkst-sidebar" class="lkst-tab-content">
					<div class="lkst-section">
						<h3><?php esc_html_e( 'Sidebar CTA', 'leokoo-site-toolkit' ); ?></h3>
						<p><?php esc_html_e( 'Output via [lkst_sidebar_cta]. Only renders on singular posts of the active post types.', 'leokoo-site-toolkit' ); ?></p>
						<?php $this->render_cta_fields( 'lkst_content_cta_settings[sidebar]', $s['sidebar'] ); ?>
					</div>
					<div class="lkst-section">
						<h3><?php esc_html_e( 'Category Overrides', 'leokoo-site-toolkit' ); ?></h3>
						<?php $this->render_cat_overrides( 'sidebar', $s['sidebar']['cat_overrides'] ?? [], $categories ); ?>
					</div>
				</div>

				<?php submit_button( __( 'Save Settings', 'leokoo-site-toolkit' ) ); ?>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			function activateTab(id) {
				$('[data-lkst-tab]').removeClass('nav-tab-active');
				$('.lkst-tab-content').removeClass('lkst-active');
				$('[data-lkst-tab="' + id + '"]').addClass('nav-tab-active');
				$('#' + id).addClass('lkst-active');
				try { sessionStorage.setItem('lkst_cta_tab', id); } catch(e) {}
			}
			$('#lkst-tab-nav [data-lkst-tab]').on('click', function(e) {
				e.preventDefault();
				activateTab($(this).data('lkst-tab'));
			});
			try {
				var saved = sessionStorage.getItem('lkst_cta_tab');
				if (saved && $('#' + saved).length) activateTab(saved);
			} catch(e) {}

			$('#power_bottom_custom').on('change', function() {
				$('#lkst-bottom-custom-fields').toggle(this.checked);
			});

			$(document).on('change', '.lkst-layout-select', function() {
				$(this).closest('.lkst-cta-field-group').find('.lkst-image-fields').toggle($(this).val() !== 'text');
			});

			$(document).on('change', '.lkst-cat-override-toggle', function() {
				$('#' + $(this).data('target')).toggle(this.checked);
			});
		});
		</script>
		<?php
	}

	private function render_cta_fields( string $base, array $data, string $prefix = '' ): void {
		$fn = function( $field ) use ( $base, $prefix ) { return esc_attr( "{$base}[{$prefix}{$field}]" ); };
		$layout    = $data[ $prefix . 'layout' ]    ?? 'text';
		$image_url = $data[ $prefix . 'image_url' ] ?? '';
		$eyebrow   = $data[ $prefix . 'eyebrow' ]   ?? '';
		$heading   = $data[ $prefix . 'heading' ]   ?? '';
		$desc      = $data[ $prefix . 'desc' ]      ?? '';
		$form      = $data[ $prefix . 'form' ]      ?? '';
		$show_img  = ( $layout !== 'text' );
		?>
		<div class="lkst-cta-field-group">
			<div class="lkst-field-row">
				<label><?php esc_html_e( 'Layout', 'leokoo-site-toolkit' ); ?></label>
				<select name="<?php echo $fn('layout'); ?>" class="lkst-layout-select">
					<option value="text"        <?php selected( $layout, 'text' ); ?>><?php esc_html_e( 'Text only', 'leokoo-site-toolkit' ); ?></option>
					<option value="image-left"  <?php selected( $layout, 'image-left' ); ?>><?php esc_html_e( 'Image — Left', 'leokoo-site-toolkit' ); ?></option>
					<option value="image-right" <?php selected( $layout, 'image-right' ); ?>><?php esc_html_e( 'Image — Right', 'leokoo-site-toolkit' ); ?></option>
					<option value="image-top"   <?php selected( $layout, 'image-top' ); ?>><?php esc_html_e( 'Image — Top (full width)', 'leokoo-site-toolkit' ); ?></option>
				</select>
			</div>
			<div class="lkst-image-fields" <?php echo $show_img ? '' : 'style="display:none"'; ?>>
				<div class="lkst-field-row" style="margin-bottom:0;">
					<label><?php esc_html_e( 'Image URL', 'leokoo-site-toolkit' ); ?></label>
					<input type="url" name="<?php echo $fn('image_url'); ?>"
						   value="<?php echo esc_attr( $image_url ); ?>"
						   placeholder="https://&hellip;" class="regular-text">
				</div>
			</div>
			<div class="lkst-field-row" style="margin-top:14px;">
				<label><?php esc_html_e( 'Eyebrow', 'leokoo-site-toolkit' ); ?></label>
				<input type="text" name="<?php echo $fn('eyebrow'); ?>" value="<?php echo esc_attr( $eyebrow ); ?>" class="regular-text">
			</div>
			<div class="lkst-field-row">
				<label><?php esc_html_e( 'Heading', 'leokoo-site-toolkit' ); ?></label>
				<input type="text" name="<?php echo $fn('heading'); ?>" value="<?php echo esc_attr( $heading ); ?>" class="regular-text">
			</div>
			<div class="lkst-field-row">
				<label><?php esc_html_e( 'Description', 'leokoo-site-toolkit' ); ?></label>
				<textarea name="<?php echo $fn('desc'); ?>" rows="3"><?php echo esc_textarea( $desc ); ?></textarea>
			</div>
			<div class="lkst-field-row">
				<label><?php esc_html_e( 'Form shortcode', 'leokoo-site-toolkit' ); ?></label>
				<input type="text" name="<?php echo $fn('form'); ?>" value="<?php echo esc_attr( $form ); ?>" class="regular-text">
			</div>
		</div>
		<?php
	}

	private function render_cat_overrides( string $slot, array $overrides, array $cats ): void {
		if ( empty( $cats ) ) {
			echo '<p><em>' . esc_html__( 'No categories found.', 'leokoo-site-toolkit' ) . '</em></p>';
			return;
		}
		?>
		<table class="widefat striped" style="max-width:860px;">
			<thead><tr>
				<th style="width:34px;"><?php esc_html_e( 'On', 'leokoo-site-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Category', 'leokoo-site-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Status', 'leokoo-site-toolkit' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $cats as $cat ) :
				$cat_id   = (int) $cat->term_id;
				$override = $overrides[ $cat_id ] ?? null;
				$active   = ! empty( $override ) && ( ! empty( $override['heading'] ) || ! empty( $override['form'] ) || ! empty( $override['desc'] ) );
				$row_id   = esc_attr( "lkst-or-{$slot}-{$cat_id}" );
			?>
				<tr>
					<td><input type="checkbox" class="lkst-cat-override-toggle" data-target="<?php echo $row_id; ?>" <?php checked( $active ); ?>></td>
					<td>
						<strong><?php echo esc_html( $cat->name ); ?></strong>
						<span style="color:#999;font-size:11px;"> (<?php echo esc_html( $cat->taxonomy ); ?>)</span>
						<?php if ( $cat->parent ) echo '<span style="color:#999;font-size:11px;"> (' . esc_html__( 'sub', 'leokoo-site-toolkit' ) . ')</span>'; ?>
						<code style="font-size:11px;margin-left:6px;"><?php echo esc_html( $cat->slug ); ?></code>
					</td>
					<td><?php echo $active ? '<span style="color:#00a32a;font-weight:600;">&#10003; ' . esc_html__( 'Active', 'leokoo-site-toolkit' ) . '</span>' : '<span style="color:#c3c4c7;">&#8211;</span>'; ?></td>
				</tr>
				<tr id="<?php echo $row_id; ?>" <?php echo $active ? '' : 'style="display:none"'; ?>>
					<td></td>
					<td colspan="2">
						<?php $this->render_cta_fields( "lkst_content_cta_settings[{$slot}][cat_overrides][{$cat_id}]", $override ?? [] ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}