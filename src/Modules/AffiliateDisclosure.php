<?php
namespace LK\SiteToolkit\Modules;
use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class AffiliateDisclosure implements ModuleInterface {
    public static function register(): void {
        Plugin::register_module( 'disclosure', self::class, [
            'title' => 'Affiliate Disclosure',
            'desc'  => 'Automated FTC/PDPA compliance notice. Outputs a styled box at the top of content or via [lkst_disclosure].',
            'default' => true,
            'settings_page' => 'lkst-disclosure'
        ] );
    }

        public function init(): void {
        add_shortcode( 'lkst_disclosure', [ $this, 'render_shortcode' ] );
        add_filter( 'the_content', [ $this, 'auto_inject' ], 15 ); // Runs after TOC (usually 10)

        if ( is_admin() ) {
            $this->register_admin_hooks();
        }
    }

    private function register_admin_hooks(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_meta_box' ] );
    }

    public function register_settings(): void {
        register_setting( 'lkst_disclosure_group', 'lkst_disc_text', [ 'default' => 'We may earn a commission if you make a purchase through one of our links.', 'sanitize_callback' => 'wp_kses_post' ] );
        register_setting( 'lkst_disclosure_group', 'lkst_disc_link', [ 'default' => '', 'sanitize_callback' => 'esc_url_raw' ] );
        register_setting( 'lkst_disclosure_group', 'lkst_disc_link_text', [ 'default' => 'Read our disclosure policy.', 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'lkst_disclosure_group', 'lkst_disc_auto', [ 'default' => '1', 'sanitize_callback' => 'absint' ] );
        
        register_setting( 'lkst_disclosure_group', 'lkst_disc_post_types', [
            'default'           => [ 'wps_reviews', 'wps_guides_cpt' ],
            'sanitize_callback' => function ( $input ) {
                if ( ! is_array( $input ) ) return [];
                $valid = array_keys( get_post_types( [ 'public' => true ] ) );
                return array_values( array_filter( array_map( 'sanitize_key', $input ), fn( $pt ) => in_array( $pt, $valid, true ) ) );
            },
        ] );
    }

    public function register_settings_page(): void {
        add_submenu_page( null, 'Affiliate Disclosure', 'Affiliate Disclosure', 'manage_options', 'lkst-disclosure', [ $this, 'render_page' ] );
    }

        public function add_meta_box(): void {
        $active_pts = get_option( 'lkst_disc_post_types', [ 'wps_reviews', 'wps_guides_cpt' ] );
        foreach ( $active_pts as $pt ) {
            add_meta_box(
                'lkst_disclosure_meta',
                __( 'Affiliate Disclosure', 'leokoo-site-toolkit' ),
                [ $this, 'render_meta_box' ],
                $pt,
                'side',
                'default'
            );
        }
    }

    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'lkst_disclosure_nonce', 'lkst_disclosure_nonce' );
        $checked = (bool) get_post_meta( $post->ID, 'lkst_no_disclosure', true );
        ?>
        <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
            <input type="checkbox" name="lkst_no_disclosure" value="1" style="margin-top:3px;" <?php checked( $checked ); ?>>
            <span><?php esc_html_e( 'Hide affiliate disclosure on this post', 'leokoo-site-toolkit' ); ?></span>
        </label>
        <?php
    }

    public function save_meta_box( int $post_id ): void {
        if ( ! isset( $_POST['lkst_disclosure_nonce'] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lkst_disclosure_nonce'] ) ), 'lkst_disclosure_nonce' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( ! empty( $_POST['lkst_no_disclosure'] ) ) {
            update_post_meta( $post_id, 'lkst_no_disclosure', true );
        } else {
            delete_post_meta( $post_id, 'lkst_no_disclosure' );
        }
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $exclude    = [ 'attachment', 'page', 'bricks_template', 'etch_template', 'elementor_library' ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Affiliate Disclosure Settings', 'leokoo-site-toolkit' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_disclosure_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="lkst_disc_text"><?php esc_html_e( 'Disclosure Text', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><textarea name="lkst_disc_text" id="lkst_disc_text" rows="3" class="large-text"><?php echo esc_textarea(get_option('lkst_disc_text', 'We may earn a commission if you make a purchase through one of our links.')); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_disc_link"><?php esc_html_e( 'Policy URL (Optional)', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="url" name="lkst_disc_link" id="lkst_disc_link" value="<?php echo esc_attr(get_option('lkst_disc_link', '')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_disc_link_text"><?php esc_html_e( 'Policy Link Text', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="text" name="lkst_disc_link_text" id="lkst_disc_link_text" value="<?php echo esc_attr(get_option('lkst_disc_link_text', 'Read our disclosure policy.')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="lkst_disc_auto"><?php esc_html_e( 'Auto Inject at Top', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="checkbox" name="lkst_disc_auto" id="lkst_disc_auto" value="1" <?php checked(get_option('lkst_disc_auto', '1')); ?>></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Auto Inject Post Types', 'leokoo-site-toolkit' ); ?></th>
                        <td><?php
                            $selected = get_option( 'lkst_disc_post_types', [ 'wps_reviews', 'wps_guides_cpt' ] );
                            foreach ( $post_types as $slug => $pt ) :
                                if ( in_array( $slug, $exclude, true ) ) continue;
                                ?>
                                <label style="display:block;margin-bottom:5px;">
                                    <input type="checkbox" name="lkst_disc_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected, true ) ); ?>>
                                    <?php echo esc_html( $pt->label ); ?>
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

    public function render_shortcode(): string {
        $text = get_option( 'lkst_disc_text', 'We may earn a commission if you make a purchase through one of our links.' );
        $link = get_option( 'lkst_disc_link', '' );
        $link_text = get_option( 'lkst_disc_link_text', 'Read our disclosure policy.' );

        if ( empty( $text ) ) return '';

        $html = '<div class="lkst-affiliate-disclosure lkst-editorial-block" style="background-color: var(--lkst-bg-light, #F5F0E8); border-radius: 4px; padding: 12px 16px; margin-bottom: 2em; font-size: 13px; color: #4A5568; line-height: 1.5; display: flex; gap: 10px; align-items: flex-start;">';
        $html .= '<span style="font-size:16px; line-height:1;">ℹ️</span>';
        $html .= '<div>' . wp_kses_post( $text );
        if ( ! empty( $link ) ) {
            $html .= ' <a href="' . esc_url( $link ) . '" style="color: var(--lkst-primary-color, #E8A020); text-decoration: underline;" target="_blank" rel="nofollow">' . esc_html( $link_text ) . '</a>';
        }
        $html .= '</div></div>';

        return $html;
    }

        public function auto_inject( $content ) {
        if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) return $content;

        if ( ! get_option( 'lkst_disc_auto', '1' ) ) return $content;

        $active_pts = get_option( 'lkst_disc_post_types', [ 'wps_reviews', 'wps_guides_cpt' ] );
        if ( ! in_array( get_post_type(), $active_pts, true ) ) return $content;

        if ( get_post_meta( get_the_ID(), 'lkst_no_disclosure', true ) ) return $content;

        $disclosure = $this->render_shortcode();
        if ( $disclosure ) {
            // We inject it directly before the first paragraph or heading, 
            // but if there's a TOC already injected, this will flow right after it because of priority 15.
            return $disclosure . $content;
        }

        return $content;
    }
}