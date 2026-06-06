<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class Disclaimer implements ModuleInterface {
    public static function register(): void {
        // Keep slug as 'disclosure' so active modules aren't broken on upgrade
        Plugin::register_module( 'disclosure', self::class, [
            'title' => 'Disclaimer',
            'desc'  => 'Automated legal/medical/custom disclaimer compliance notice. Outputs a styled box at the bottom of content.',
            'default' => true,
            'settings_page' => 'lkst-disclaimer'
        ] );
    }

    public function init(): void {
        add_filter( 'the_content', [ $this, 'auto_inject' ], 100 );

        if ( is_admin() ) {
            $this->register_admin_hooks();
        }
    }

    private function register_admin_hooks(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
    }

    public function register_settings(): void {
        register_setting( 'lkst_disclaimer_group', 'lkst_disclaimer_preset', [ 'default' => 'off', 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'lkst_disclaimer_group', 'lkst_disclaimer_custom_text', [ 'default' => '', 'sanitize_callback' => 'wp_kses_post' ] );
        
        register_setting( 'lkst_disclaimer_group', 'lkst_disclaimer_post_types', [
            'default'           => [ 'post' ],
            'sanitize_callback' => function ( $input ) {
                if ( ! is_array( $input ) ) return [];
                $valid = array_keys( get_post_types( [ 'public' => true ] ) );
                return array_values( array_filter( array_map( 'sanitize_key', $input ), fn( $pt ) => in_array( $pt, $valid, true ) ) );
            },
        ] );
    }

    public function register_settings_page(): void {
        add_submenu_page( 'lkst-dashboard', 'Disclaimer Settings', 'Disclaimer', 'manage_options', 'lkst-disclaimer', [ $this, 'render_page' ] );
    }

    public function get_disclaimer_text(): string {
        $preset = get_option( 'lkst_disclaimer_preset', 'off' );
        if ( $preset === 'off' ) return '';

        if ( $preset === 'medical' ) {
            return __( 'This article is for informational purposes only and does not constitute medical advice. Consult a qualified ophthalmologist for diagnosis and treatment.', 'zehoro-toolkit' );
        } elseif ( $preset === 'legal' ) {
            return __( 'This article provides general legal information, not legal advice. For advice specific to your situation, consult a qualified Malaysian lawyer.', 'zehoro-toolkit' );
        } elseif ( $preset === 'custom' ) {
            // Fallback for migration: if custom is empty, check old lkst_disc_text
            $custom = get_option( 'lkst_disclaimer_custom_text', '' );
            if ( empty( $custom ) ) {
                $custom = get_option( 'lkst_disc_text', '' );
            }
            return wp_kses_post( $custom );
        }
        return '';
    }

    public function auto_inject( string $content ): string {
        if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) return $content;

        $post_type = get_post_type();
        $active_pts = get_option( 'lkst_disclaimer_post_types', [ 'post' ] );
        if ( ! in_array( $post_type, $active_pts, true ) ) return $content;

        $text = $this->get_disclaimer_text();
        if ( empty( $text ) ) return $content;

        $bg_color = esc_attr( get_option( 'lkst_color_bg_light', '#F5F0E8' ) );

        $html = sprintf(
            '<div class="lkst-disclaimer" style="margin-top: 2em; padding: 1.5em; background-color: %s; font-size: 0.9em; font-style: italic; border-radius: 4px;">
                <p style="margin: 0;">%s</p>
            </div>',
            $bg_color,
            $text
        );

        return $content . $html;
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $exclude    = [ 'attachment', 'page', 'bricks_template', 'etch_template', 'elementor_library' ];
        $preset     = get_option( 'lkst_disclaimer_preset', 'off' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Disclaimer Settings', 'zehoro-toolkit' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_disclaimer_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Disclaimer Type', 'zehoro-toolkit' ); ?></th>
                        <td>
                            <select name="lkst_disclaimer_preset" id="lkst_disclaimer_preset">
                                <option value="off" <?php selected( $preset, 'off' ); ?>><?php esc_html_e( 'Off', 'zehoro-toolkit' ); ?></option>
                                <option value="medical" <?php selected( $preset, 'medical' ); ?>><?php esc_html_e( 'Medical (Standard)', 'zehoro-toolkit' ); ?></option>
                                <option value="legal" <?php selected( $preset, 'legal' ); ?>><?php esc_html_e( 'Legal (Standard)', 'zehoro-toolkit' ); ?></option>
                                <option value="custom" <?php selected( $preset, 'custom' ); ?>><?php esc_html_e( 'Custom', 'zehoro-toolkit' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Custom Text', 'zehoro-toolkit' ); ?></th>
                        <td>
                            <?php
                            $custom_text = get_option( 'lkst_disclaimer_custom_text', '' );
                            if ( empty($custom_text) && get_option('lkst_disc_text', '') ) {
                                $custom_text = get_option('lkst_disc_text'); // legacy migration
                            }
                            ?>
                            <textarea name="lkst_disclaimer_custom_text" rows="4" class="large-text"><?php echo esc_textarea( $custom_text ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Only used if "Custom" is selected above.', 'zehoro-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Active Post Types', 'zehoro-toolkit' ); ?></th>
                        <td>
                            <?php 
                            $active = get_option( 'lkst_disclaimer_post_types', [ 'post' ] );
                            foreach ( $post_types as $slug => $pt ) :
                                if ( in_array( $slug, $exclude, true ) ) continue; ?>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="lkst_disclaimer_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $active, true ) ); ?>>
                                    <?php echo esc_html( $pt->label ); ?>
                                    <code style="margin-left:4px;"><?php echo esc_html( $slug ); ?></code>
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
}
