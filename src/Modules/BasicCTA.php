<?php
namespace LK\SiteToolkit\Modules;
use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Basic CTA Module (Free Version)
 *
 * Provides a lightweight, manually placed shortcode [lkst_cta] for inline conversions.
 * Features zero automated injection logic to keep support overhead non-existent.
 * Pro users upgrade to the 'Content CTAs' module for the full automated sequencing engine.
 *
 * @package LK\SiteToolkit\Modules
 */
class BasicCTA implements ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'basic_cta', self::class, [
            'title'         => 'Basic Inline CTA',
            'desc'          => 'A beautifully styled, manually placed shortcode [lkst_cta] for inline conversions. Zero bloat.',
            'default'       => true,
            'settings_page' => 'lkst-basic-cta'
        ] );
    }

    public function init(): void {
        // If Pro ContentCTA is active it completely supersedes BasicCTA
        // (automated injection + full sequencing engine). Skip registration
        // so [lkst_cta] shortcodes do not clash with Pro-injected CTAs.
        $active = (array) get_option( 'lkst_active_modules', [] );
        if ( in_array( 'content_cta', $active, true ) ) {
            return;
        }

        add_shortcode( 'lkst_cta', [ $this, 'render_shortcode' ] );

        if ( is_admin() ) {
            $this->register_admin_hooks();
        }
    }

    private function register_admin_hooks(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
    }

    public function register_settings(): void {
        register_setting( 'lkst_basic_cta_group', 'lkst_basic_cta_eyebrow', [ 'default' => 'Weekly Brief', 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'lkst_basic_cta_group', 'lkst_basic_cta_heading', [ 'default' => 'Enjoying this? Join the newsletter.', 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'lkst_basic_cta_group', 'lkst_basic_cta_desc',    [ 'default' => '', 'sanitize_callback' => 'wp_kses_post' ] );
        register_setting( 'lkst_basic_cta_group', 'lkst_basic_cta_form',    [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ] );
        
        $sanitize_layout = fn($v) => in_array($v, ['text', 'image-left', 'image-right'], true) ? $v : 'text';
        register_setting( 'lkst_basic_cta_group', 'lkst_basic_cta_layout',  [ 'default' => 'text', 'sanitize_callback' => $sanitize_layout ] );
        register_setting( 'lkst_basic_cta_group', 'lkst_basic_cta_image',   [ 'default' => '', 'sanitize_callback' => 'esc_url_raw' ] );
    }

    public function register_settings_page(): void {
        // Registered in the Module Registry, so we hook it without adding it to the main sidebar menu
        add_submenu_page(
            null,
            __( 'Basic CTA Settings', 'leokoo-site-toolkit' ),
            __( 'Basic CTA', 'leokoo-site-toolkit' ),
            'manage_options',
            'lkst-basic-cta',
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Basic Inline CTA', 'leokoo-site-toolkit' ); ?></h1>
            <p><?php esc_html_e( 'Set your default call-to-action content below. To display it, paste the shortcode ', 'leokoo-site-toolkit' ); ?><code>[lkst_cta]</code><?php esc_html_e( ' anywhere in your content.', 'leokoo-site-toolkit' ); ?></p>
            <p><em><?php esc_html_e( 'You can override these defaults directly in the shortcode: ', 'leokoo-site-toolkit' ); ?><code>[lkst_cta heading="Custom text" form="[other_form_shortcode]"]</code></em></p>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_basic_cta_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="lkst_basic_cta_layout"><?php esc_html_e( 'Layout', 'leokoo-site-toolkit' ); ?></label></th>
                        <td>
                            <select name="lkst_basic_cta_layout" id="lkst_basic_cta_layout">
                                <?php $layout = get_option( 'lkst_basic_cta_layout', 'text' ); ?>
                                <option value="text" <?php selected($layout, 'text'); ?>><?php esc_html_e('Text Only', 'leokoo-site-toolkit'); ?></option>
                                <option value="image-left" <?php selected($layout, 'image-left'); ?>><?php esc_html_e('Image Left', 'leokoo-site-toolkit'); ?></option>
                                <option value="image-right" <?php selected($layout, 'image-right'); ?>><?php esc_html_e('Image Right', 'leokoo-site-toolkit'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lkst_basic_cta_image"><?php esc_html_e( 'Image URL', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="url" id="lkst_basic_cta_image" name="lkst_basic_cta_image" value="<?php echo esc_attr( get_option( 'lkst_basic_cta_image', '' ) ); ?>" class="regular-text" placeholder="https://..."></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_basic_cta_eyebrow"><?php esc_html_e( 'Eyebrow', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="text" id="lkst_basic_cta_eyebrow" name="lkst_basic_cta_eyebrow" value="<?php echo esc_attr( get_option( 'lkst_basic_cta_eyebrow', 'Weekly Brief' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_basic_cta_heading"><?php esc_html_e( 'Heading', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="text" id="lkst_basic_cta_heading" name="lkst_basic_cta_heading" value="<?php echo esc_attr( get_option( 'lkst_basic_cta_heading', 'Enjoying this? Join the newsletter.' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_basic_cta_desc"><?php esc_html_e( 'Description', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><textarea id="lkst_basic_cta_desc" name="lkst_basic_cta_desc" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'lkst_basic_cta_desc', '' ) ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_basic_cta_form"><?php esc_html_e( 'Form Shortcode', 'leokoo-site-toolkit' ); ?></label></th>
                        <td>
                            <input type="text" id="lkst_basic_cta_form" name="lkst_basic_cta_form" value="<?php echo esc_attr( get_option( 'lkst_basic_cta_form', '' ) ); ?>" class="regular-text" placeholder='[fluentform id="1"]'>
                            <p class="description"><?php esc_html_e('Paste your opt-in form shortcode here.', 'leokoo-site-toolkit'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <hr>
            <div style="background:#f0f6fc; border-left:4px solid #72aee6; padding:12px 15px; margin-top:30px;">
                <h3><?php esc_html_e('🚀 Need automated CTAs?', 'leokoo-site-toolkit'); ?></h3>
                <p><?php esc_html_e('Upgrade to the Pro version to unlock the CTA Sequencing Engine. Automatically inject CTAs exactly where you want them based on paragraph counts, scroll percentage, and category overrides without ever typing a shortcode.', 'leokoo-site-toolkit'); ?></p>
            </div>
        </div>
        <?php
    }

    public function render_shortcode( $atts ): string {
        $atts = shortcode_atts( [
            'eyebrow' => get_option( 'lkst_basic_cta_eyebrow', 'Weekly Brief' ),
            'heading' => get_option( 'lkst_basic_cta_heading', 'Enjoying this? Join the newsletter.' ),
            'desc'    => get_option( 'lkst_basic_cta_desc', '' ),
            'form'    => get_option( 'lkst_basic_cta_form', '' ),
            'layout'  => get_option( 'lkst_basic_cta_layout', 'text' ),
            'image'   => get_option( 'lkst_basic_cta_image', '' ),
        ], $atts, 'lkst_cta' );

        if ( empty( $atts['form'] ) && empty( $atts['heading'] ) ) return '';

        // If layout requires an image but none provided, fallback to text
        if ( strpos( $atts['layout'], 'image-' ) !== false && empty( $atts['image'] ) ) {
            $atts['layout'] = 'text';
        }

        $classes = ['lkst-midpost-cta'];
        $classes[] = esc_attr( $atts['layout'] );
        if ( strpos( $atts['layout'], 'image-' ) !== false ) {
            $classes[] = 'has-image';
            $classes[] = 'hide-image-mobile';
        }

        $html = '<div class="' . implode( ' ', $classes ) . '">';

        if ( strpos( $atts['layout'], 'image-' ) !== false && ! empty( $atts['image'] ) ) {
            $html .= '<div class="lkst-cta-image-wrapper"><img src="' . esc_url( $atts['image'] ) . '" class="lkst-cta-image" alt=""></div>';
        }

        $html .= '<div class="lkst-midpost-cta__text">';
        if ( ! empty( $atts['eyebrow'] ) ) {
            $html .= '<small class="lkst-midpost-cta__eyebrow">' . esc_html( $atts['eyebrow'] ) . '</small>';
        }
        if ( ! empty( $atts['heading'] ) ) {
            $html .= '<strong class="lkst-midpost-cta__heading">' . esc_html( $atts['heading'] ) . '</strong>';
        }
        if ( ! empty( $atts['desc'] ) ) {
            $html .= '<span class="lkst-midpost-cta__desc">' . wp_kses_post( $atts['desc'] ) . '</span>';
        }
        $html .= '</div>';

        if ( ! empty( $atts['form'] ) ) {
            $html .= '<div class="lkst-midpost-cta__form">' . do_shortcode( $atts['form'] ) . '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}