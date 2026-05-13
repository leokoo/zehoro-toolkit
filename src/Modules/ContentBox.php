<?php
namespace LK\SiteToolkit\Modules;

use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Content Box Module
 *
 * Manually place a styled CTA or built-in email-capture box anywhere in your
 * content using [lkst_box]. Two modes:
 *   - cta:   eyebrow / heading / desc + external form shortcode (FluentForms etc.)
 *   - email: eyebrow / heading / desc + built-in name+email form → webhook delivery
 *
 * Pro ContentStream (Phase 2) will add auto-injection on top of this renderer;
 * ContentBox itself is always manual shortcode placement.
 *
 * @package LK\SiteToolkit\Modules
 */
class ContentBox implements ModuleInterface {

    // ── Registration ─────────────────────────────────────────────────────────

    public static function register(): void {
        Plugin::register_module( 'content_box', self::class, [
            'title'         => 'Content Box',
            'desc'          => 'Place a styled CTA or email-capture box anywhere with [lkst_box]. Choose a newsletter form shortcode or a built-in file-download form with webhook delivery.',
            'default'       => true,
            'settings_page' => 'lkst-content-box',
        ] );

        // ── One-time migration: basic_cta → content_box ───────────────────
        $active = (array) get_option( 'lkst_active_modules', [] );
        if ( in_array( 'basic_cta', $active, true ) && ! in_array( 'content_box', $active, true ) ) {
            $active   = array_values( array_diff( $active, [ 'basic_cta' ] ) );
            $active[] = 'content_box';
            update_option( 'lkst_active_modules', $active );
        }
    }

    // ── Hooks ────────────────────────────────────────────────────────────────

    public function init(): void {
        add_shortcode( 'lkst_box', [ $this, 'render_shortcode' ] );

        // AJAX: email-capture form submission
        add_action( 'wp_ajax_lkst_box_submit',        [ $this, 'handle_submission' ] );
        add_action( 'wp_ajax_nopriv_lkst_box_submit', [ $this, 'handle_submission' ] );

        if ( is_admin() ) {
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        }
    }

    // ── Settings ─────────────────────────────────────────────────────────────

    public function register_settings(): void {
        $g = 'lkst_content_box_group';

        $valid_type   = fn( $v ) => in_array( $v, [ 'cta', 'email' ], true ) ? $v : 'cta';
        $valid_layout = fn( $v ) => in_array( $v, [ 'text', 'image-left', 'image-right', 'image-top' ], true ) ? $v : 'text';

        register_setting( $g, 'lkst_box_type',        [ 'default' => 'cta',                                    'sanitize_callback' => $valid_type ] );
        register_setting( $g, 'lkst_box_eyebrow',     [ 'default' => '',                                        'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( $g, 'lkst_box_heading',     [ 'default' => 'Enjoying this? Join the newsletter.',     'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( $g, 'lkst_box_desc',        [ 'default' => '',                                        'sanitize_callback' => 'wp_kses_post' ] );
        register_setting( $g, 'lkst_box_layout',      [ 'default' => 'text',                                    'sanitize_callback' => $valid_layout ] );
        register_setting( $g, 'lkst_box_image_url',   [ 'default' => '',                                        'sanitize_callback' => 'esc_url_raw' ] );
        // CTA-specific
        register_setting( $g, 'lkst_box_form',        [ 'default' => '',                                        'sanitize_callback' => 'sanitize_text_field' ] );
        // Email-capture-specific
        register_setting( $g, 'lkst_box_webhook_url', [ 'default' => '',                                        'sanitize_callback' => 'esc_url_raw' ] );
        register_setting( $g, 'lkst_box_button_text', [ 'default' => 'Get Free Download →',                     'sanitize_callback' => 'sanitize_text_field' ] );
    }

    public function register_settings_page(): void {
        add_submenu_page(
            'lkst-dashboard',
            __( 'Content Box Settings', 'leokoo-site-toolkit' ),
            __( 'Content Box', 'leokoo-site-toolkit' ),
            'manage_options',
            'lkst-content-box',
            [ $this, 'render_page' ]
        );
    }

    // ── Admin settings page ───────────────────────────────────────────────────

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $type        = get_option( 'lkst_box_type',        'cta' );
        $layout      = get_option( 'lkst_box_layout',      'text' );
        $webhook     = get_option( 'lkst_box_webhook_url', '' );
        $button_text = get_option( 'lkst_box_button_text', 'Get Free Download →' );
        $form        = get_option( 'lkst_box_form',        '' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Content Box Settings', 'leokoo-site-toolkit' ); ?></h1>
            <p><?php esc_html_e( 'These are the site-wide defaults. Override any field directly in the shortcode: ', 'leokoo-site-toolkit' ); ?>
               <code>[lkst_box type="email" heading="Download the Guide" file_url="https://..."]</code></p>

            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_content_box_group' ); ?>

                <table class="form-table">

                    <tr>
                        <th><?php esc_html_e( 'Box Type', 'leokoo-site-toolkit' ); ?></th>
                        <td>
                            <select name="lkst_box_type" id="lkst_box_type">
                                <option value="cta"   <?php selected( $type, 'cta' ); ?>><?php esc_html_e( 'Newsletter CTA (external form)', 'leokoo-site-toolkit' ); ?></option>
                                <option value="email" <?php selected( $type, 'email' ); ?>><?php esc_html_e( 'File Download (built-in form + webhook)', 'leokoo-site-toolkit' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Override per shortcode with type="cta" or type="email".', 'leokoo-site-toolkit' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="lkst_box_eyebrow"><?php esc_html_e( 'Eyebrow', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="text" id="lkst_box_eyebrow" name="lkst_box_eyebrow" value="<?php echo esc_attr( get_option( 'lkst_box_eyebrow', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Free Resource', 'leokoo-site-toolkit' ); ?>"></td>
                    </tr>

                    <tr>
                        <th><label for="lkst_box_heading"><?php esc_html_e( 'Heading', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="text" id="lkst_box_heading" name="lkst_box_heading" value="<?php echo esc_attr( get_option( 'lkst_box_heading', 'Enjoying this? Join the newsletter.' ) ); ?>" class="regular-text"></td>
                    </tr>

                    <tr>
                        <th><label for="lkst_box_desc"><?php esc_html_e( 'Description', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><textarea id="lkst_box_desc" name="lkst_box_desc" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'lkst_box_desc', '' ) ); ?></textarea></td>
                    </tr>

                    <tr>
                        <th><label for="lkst_box_layout"><?php esc_html_e( 'Layout', 'leokoo-site-toolkit' ); ?></label></th>
                        <td>
                            <select name="lkst_box_layout" id="lkst_box_layout">
                                <option value="text"        <?php selected( $layout, 'text' ); ?>><?php esc_html_e( 'Text Only', 'leokoo-site-toolkit' ); ?></option>
                                <option value="image-left"  <?php selected( $layout, 'image-left' ); ?>><?php esc_html_e( 'Image Left', 'leokoo-site-toolkit' ); ?></option>
                                <option value="image-right" <?php selected( $layout, 'image-right' ); ?>><?php esc_html_e( 'Image Right', 'leokoo-site-toolkit' ); ?></option>
                                <option value="image-top"   <?php selected( $layout, 'image-top' ); ?>><?php esc_html_e( 'Image Top', 'leokoo-site-toolkit' ); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="lkst_box_image_url"><?php esc_html_e( 'Image URL', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="url" id="lkst_box_image_url" name="lkst_box_image_url" value="<?php echo esc_attr( get_option( 'lkst_box_image_url', '' ) ); ?>" class="regular-text" placeholder="https://"></td>
                    </tr>

                    <!-- CTA: external form shortcode -->
                    <tr class="lkst-box-row-cta" <?php if ( $type !== 'cta' ) echo 'style="display:none"'; ?>>
                        <th><label for="lkst_box_form"><?php esc_html_e( 'Form Shortcode', 'leokoo-site-toolkit' ); ?></label></th>
                        <td>
                            <input type="text" id="lkst_box_form" name="lkst_box_form" value="<?php echo esc_attr( $form ); ?>" class="regular-text" placeholder='[fluentform id="1"]'>
                            <p class="description"><?php esc_html_e( 'Paste your opt-in form shortcode. Accepts any shortcode-based form plugin.', 'leokoo-site-toolkit' ); ?></p>
                        </td>
                    </tr>

                    <!-- Email: webhook + button text -->
                    <tr class="lkst-box-row-email" <?php if ( $type !== 'email' ) echo 'style="display:none"'; ?>>
                        <th><label for="lkst_box_webhook_url"><?php esc_html_e( 'Webhook URL', 'leokoo-site-toolkit' ); ?></label></th>
                        <td>
                            <input type="url" id="lkst_box_webhook_url" name="lkst_box_webhook_url" value="<?php echo esc_attr( $webhook ); ?>" class="regular-text" placeholder="https://hook.make.com/...">
                            <p class="description"><?php esc_html_e( 'POSTs name, email, file_url, post_id, and source to this URL. Works with Make.com, Zapier, or n8n.', 'leokoo-site-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr class="lkst-box-row-email" <?php if ( $type !== 'email' ) echo 'style="display:none"'; ?>>
                        <th><label for="lkst_box_button_text"><?php esc_html_e( 'Button Text', 'leokoo-site-toolkit' ); ?></label></th>
                        <td><input type="text" id="lkst_box_button_text" name="lkst_box_button_text" value="<?php echo esc_attr( $button_text ); ?>" class="regular-text"></td>
                    </tr>

                </table>

                <?php submit_button(); ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Shortcode Reference', 'leokoo-site-toolkit' ); ?></h2>
            <table class="widefat" style="max-width:700px;">
                <thead><tr><th><?php esc_html_e( 'Attribute', 'leokoo-site-toolkit' ); ?></th><th><?php esc_html_e( 'Default', 'leokoo-site-toolkit' ); ?></th><th><?php esc_html_e( 'Notes', 'leokoo-site-toolkit' ); ?></th></tr></thead>
                <tbody>
                    <tr><td><code>type</code></td><td>setting</td><td><?php esc_html_e( '"cta" or "email"', 'leokoo-site-toolkit' ); ?></td></tr>
                    <tr><td><code>eyebrow</code></td><td>setting</td><td><?php esc_html_e( 'Small label above the heading', 'leokoo-site-toolkit' ); ?></td></tr>
                    <tr><td><code>heading</code></td><td>setting</td><td><?php esc_html_e( 'Main headline', 'leokoo-site-toolkit' ); ?></td></tr>
                    <tr><td><code>desc</code></td><td>setting</td><td><?php esc_html_e( 'Body copy (HTML allowed)', 'leokoo-site-toolkit' ); ?></td></tr>
                    <tr><td><code>layout</code></td><td>setting</td><td><?php esc_html_e( 'text / image-left / image-right / image-top', 'leokoo-site-toolkit' ); ?></td></tr>
                    <tr><td><code>image</code></td><td>setting</td><td><?php esc_html_e( 'Image URL (required for image layouts)', 'leokoo-site-toolkit' ); ?></td></tr>
                    <tr><td><code>form</code></td><td>setting</td><td><?php esc_html_e( 'type="cta" only — form shortcode', 'leokoo-site-toolkit' ); ?></td></tr>
                    <tr><td><code>file_url</code></td><td><em><?php esc_html_e( 'none', 'leokoo-site-toolkit' ); ?></em></td><td><?php esc_html_e( 'type="email" only — the download URL passed to the webhook', 'leokoo-site-toolkit' ); ?></td></tr>
                    <tr><td><code>webhook</code></td><td>setting</td><td><?php esc_html_e( 'type="email" only — override the global webhook per article', 'leokoo-site-toolkit' ); ?></td></tr>
                    <tr><td><code>button_text</code></td><td>setting</td><td><?php esc_html_e( 'type="email" only — submit button label', 'leokoo-site-toolkit' ); ?></td></tr>
                </tbody>
            </table>
        </div>

        <script>
        (function() {
            var sel = document.getElementById('lkst_box_type');
            if (!sel) return;
            function toggle() {
                var v = sel.value;
                document.querySelectorAll('.lkst-box-row-cta').forEach(function(r){r.style.display = v==='cta'?'':'none';});
                document.querySelectorAll('.lkst-box-row-email').forEach(function(r){r.style.display = v==='email'?'':'none';});
            }
            sel.addEventListener('change', toggle);
            toggle();
        })();
        </script>
        <?php
    }

    // ── Shortcode renderer ────────────────────────────────────────────────────

    public function render_shortcode( $atts ): string {
        $atts = shortcode_atts( [
            'type'        => get_option( 'lkst_box_type',        'cta' ),
            'eyebrow'     => get_option( 'lkst_box_eyebrow',     '' ),
            'heading'     => get_option( 'lkst_box_heading',     'Enjoying this? Join the newsletter.' ),
            'desc'        => get_option( 'lkst_box_desc',        '' ),
            'layout'      => get_option( 'lkst_box_layout',      'text' ),
            'image'       => get_option( 'lkst_box_image_url',   '' ),
            // CTA-specific
            'form'        => get_option( 'lkst_box_form',        '' ),
            // Email-specific
            'file_url'    => '',
            'webhook'     => get_option( 'lkst_box_webhook_url', '' ),
            'button_text' => get_option( 'lkst_box_button_text', 'Get Free Download →' ),
        ], $atts, 'lkst_box' );

        return $atts['type'] === 'email'
            ? $this->render_email_box( $atts )
            : $this->render_cta_box( $atts );
    }

    // ── CTA box (external form shortcode) ─────────────────────────────────────

    /**
     * Renders using the shared .lkst-midpost-cta CSS classes so it inherits
     * all Pro ContentStream styling automatically in Phase 2.
     */
    public function render_cta_box( array $atts, string $extra_class = '' ): string {
        if ( empty( $atts['form'] ) && empty( $atts['heading'] ) ) return '';

        $layout  = $atts['layout'];
        $img_url = $atts['image'];

        if ( strpos( $layout, 'image-' ) !== false && empty( $img_url ) ) {
            $layout = 'text';
        }

        $classes = [ 'lkst-midpost-cta', esc_attr( $layout ) ];
        if ( $extra_class ) $classes[] = esc_attr( $extra_class );
        if ( strpos( $layout, 'image-' ) !== false ) {
            $classes[] = 'has-image hide-image-mobile';
        }

        $html = '<div class="' . implode( ' ', $classes ) . '">';

        if ( strpos( $layout, 'image-' ) !== false && ! empty( $img_url ) ) {
            $html .= '<div class="lkst-cta-image-wrapper"><img src="' . esc_url( $img_url ) . '" class="lkst-cta-image" alt=""></div>';
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

    // ── Email-capture box (built-in form + webhook) ───────────────────────────

    private function render_email_box( array $atts ): string {
        $primary  = esc_attr( get_option( 'lkst_color_primary',          '#E8A020' ) );
        $contrast = esc_attr( get_option( 'lkst_color_primary_contrast', '#0F1A2E' ) );
        $bg_light = esc_attr( get_option( 'lkst_color_bg_light',         '#F5F0E8' ) );

        $layout  = $atts['layout'];
        $img_url = $atts['image'];
        if ( strpos( $layout, 'image-' ) !== false && empty( $img_url ) ) {
            $layout = 'text';
        }

        $classes = [ 'lkst-midpost-cta', 'email-capture', esc_attr( $layout ) ];
        if ( strpos( $layout, 'image-' ) !== false ) {
            $classes[] = 'has-image hide-image-mobile';
        }

        ob_start();
        ?>
        <div class="<?php echo implode( ' ', $classes ); ?>" style="background-color:<?php echo $bg_light; ?>; border-left:4px solid <?php echo $primary; ?>; border-radius:4px; padding:1.5em 2em; margin:2em 0; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <?php if ( strpos( $layout, 'image-' ) !== false && ! empty( $img_url ) ) : ?>
                <div class="lkst-cta-image-wrapper"><img src="<?php echo esc_url( $img_url ); ?>" class="lkst-cta-image" alt=""></div>
            <?php endif; ?>

            <div class="lkst-midpost-cta__text" style="margin-bottom:1em;">
                <?php if ( ! empty( $atts['eyebrow'] ) ) : ?>
                    <small class="lkst-midpost-cta__eyebrow" style="color:<?php echo $primary; ?>;"><?php echo esc_html( $atts['eyebrow'] ); ?></small>
                <?php endif; ?>
                <?php if ( ! empty( $atts['heading'] ) ) : ?>
                    <strong class="lkst-midpost-cta__heading" style="display:block; color:<?php echo $contrast; ?>; font-size:1.1em; margin:4px 0;">
                        <span style="margin-right:6px;">📄</span><?php echo esc_html( $atts['heading'] ); ?>
                    </strong>
                <?php endif; ?>
                <?php if ( ! empty( $atts['desc'] ) ) : ?>
                    <span class="lkst-midpost-cta__desc"><?php echo wp_kses_post( $atts['desc'] ); ?></span>
                <?php endif; ?>
            </div>

            <form class="lkst-box-email-form" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <input type="text"
                       name="lkst_box_name"
                       placeholder="<?php esc_attr_e( 'Your Name', 'leokoo-site-toolkit' ); ?>"
                       required
                       style="flex:1; min-width:130px; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                <input type="email"
                       name="lkst_box_email"
                       placeholder="<?php esc_attr_e( 'Your Email', 'leokoo-site-toolkit' ); ?>"
                       required
                       style="flex:2; min-width:180px; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                <input type="hidden" name="lkst_box_file_url"  value="<?php echo esc_url( $atts['file_url'] ); ?>">
                <input type="hidden" name="lkst_box_webhook"   value="<?php echo esc_url( $atts['webhook'] ); ?>">
                <input type="hidden" name="action"             value="lkst_box_submit">
                <?php wp_nonce_field( 'lkst_box_nonce', 'lkst_box_security' ); ?>
                <!-- Honeypot: bots fill this, humans don't -->
                <input type="text" name="lkst_box_hp" style="display:none!important" tabindex="-1" autocomplete="off">
                <button type="submit"
                        style="background-color:<?php echo $primary; ?>; color:#fff; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold; font-size:14px; white-space:nowrap;">
                    <?php echo esc_html( $atts['button_text'] ); ?>
                </button>
            </form>

            <div class="lkst-box-message" style="margin-top:8px; font-size:0.88em; display:none;"></div>
        </div>

        <?php if ( ! wp_script_is( 'lkst-box-email', 'done' ) ) : ?>
        <script id="lkst-box-email-js">
        (function() {
            if (typeof window.lkstBoxEmailInit !== 'undefined') return;
            window.lkstBoxEmailInit = true;

            document.addEventListener('submit', function(e) {
                if (!e.target.classList.contains('lkst-box-email-form')) return;
                e.preventDefault();

                var form = e.target;
                var box  = form.closest('.lkst-midpost-cta');
                var msg  = box ? box.querySelector('.lkst-box-message') : form.nextElementSibling;
                var btn  = form.querySelector('button[type="submit"]');
                var originalText = btn.textContent;

                btn.disabled    = true;
                btn.textContent = '<?php echo esc_js( __( 'Sending…', 'leokoo-site-toolkit' ) ); ?>';

                var data = new FormData(form);

                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    body:   data,
                })
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        if (msg) {
                            msg.style.color   = '#2e7d32';
                            msg.textContent   = '<?php echo esc_js( __( 'Success! Check your email for the download link.', 'leokoo-site-toolkit' ) ); ?>';
                            msg.style.display = 'block';
                        }
                        form.reset();
                        btn.textContent = originalText;
                    } else {
                        if (msg) {
                            msg.style.color   = '#c62828';
                            msg.textContent   = res.data || '<?php echo esc_js( __( 'An error occurred. Please try again.', 'leokoo-site-toolkit' ) ); ?>';
                            msg.style.display = 'block';
                        }
                        btn.disabled    = false;
                        btn.textContent = originalText;
                    }
                })
                .catch(function() {
                    if (msg) {
                        msg.style.color   = '#c62828';
                        msg.textContent   = '<?php echo esc_js( __( 'Network error. Please try again.', 'leokoo-site-toolkit' ) ); ?>';
                        msg.style.display = 'block';
                    }
                    btn.disabled    = false;
                    btn.textContent = originalText;
                });
            });
        })();
        </script>
        <?php
        // Mark as printed so we don't emit the script block twice on a page
        // with multiple email-type boxes.
        wp_scripts()->add( 'lkst-box-email', '', [], false );
        wp_scripts()->done[] = 'lkst-box-email';
        endif;

        return ob_get_clean();
    }

    // ── AJAX: email-capture submission ────────────────────────────────────────

    public function handle_submission(): void {
        check_ajax_referer( 'lkst_box_nonce', 'lkst_box_security' );

        // Honeypot: silent success so bots don't know they were discarded
        if ( ! empty( $_POST['lkst_box_hp'] ) ) {
            wp_send_json_success( 'Subscribed.' );
        }

        $email   = sanitize_email( $_POST['lkst_box_email']   ?? '' );
        $name    = sanitize_text_field( $_POST['lkst_box_name']    ?? '' );
        $file    = esc_url_raw( $_POST['lkst_box_file_url']   ?? '' );

        // Per-shortcode webhook overrides global setting
        $webhook = esc_url_raw( $_POST['lkst_box_webhook'] ?? '' );
        if ( empty( $webhook ) ) {
            $webhook = get_option( 'lkst_box_webhook_url', '' );
        }

        if ( empty( $email ) ) {
            wp_send_json_error( __( 'Please enter a valid email address.', 'leokoo-site-toolkit' ) );
        }
        if ( empty( $webhook ) ) {
            wp_send_json_error( __( 'This form is not configured yet. Please contact the site owner.', 'leokoo-site-toolkit' ) );
        }

        $response = wp_remote_post( $webhook, [
            'body'    => [
                'email'    => $email,
                'name'     => $name,
                'file_url' => $file,
                'post_id'  => url_to_postid( wp_get_referer() ),
                'source'   => wp_get_referer(),
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( __( 'Could not reach the delivery server. Please try again shortly.', 'leokoo-site-toolkit' ) );
        }

        wp_send_json_success( __( 'Success! Check your email for the download link.', 'leokoo-site-toolkit' ) );
    }
}
