<?php
namespace LK\SiteToolkit\Modules;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Table of Contents Module
 * Usage: [lkst_toc]
 */
class TableOfContents {
    public function init() {
        if ( is_admin() ) {
            add_action( 'admin_init', [ $this, 'register_settings' ] );
        }
        add_action( 'wp', [ $this, 'preparse_toc_headings' ], 10 );
        add_filter( 'the_content', [ $this, 'process_content' ], 15 );
        add_shortcode( 'lkst_toc', [ $this, 'render_shortcode' ] );
    }

    public static function get_defaults() {
        return [
            'post_types' => [ 'post', 'reviews', 'buying-guides' ],
            'insertion'  => 'auto',
        ];
    }

    public function register_settings() {
        register_setting( 'lkst_toc_group', 'lkst_toc_settings', [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => static::get_defaults(),
        ] );
    }

    public function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) return static::get_defaults();
        $out = [];
        $out['post_types'] = [];
        if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
            $valid_pts = array_keys( get_post_types( [ 'public' => true ] ) );
            foreach ( $input['post_types'] as $pt ) {
                $pt = sanitize_key( $pt );
                if ( in_array( $pt, $valid_pts, true ) ) {
                    $out['post_types'][] = $pt;
                }
            }
        }
        $out['insertion'] = ( isset( $input['insertion'] ) && $input['insertion'] === 'shortcode' ) ? 'shortcode' : 'auto';
        return $out;
    }

    /**
     * Runs on the `wp` hook — before Bricks renders any elements.
     * Pre-parses post headings so the [lkst_toc] shortcode in a sidebar
     * element can render immediately, without waiting for the_content.
     */
    public function preparse_toc_headings() {
        if ( ! is_singular() ) return;
        $settings = get_option( 'lkst_toc_settings', static::get_defaults() );
        if ( ! in_array( get_post_type(), $settings['post_types'], true ) ) return;

        global $lkst_toc_items;
        if ( ! empty( $lkst_toc_items ) ) return;

        $post = get_post();
        if ( ! $post ) return;

        $content        = do_shortcode( $post->post_content );
        $pattern        = '/<h([2-3])([^>]*)>(.*?)<\/h\1>/is';
        $lkst_toc_items = [];

        preg_replace_callback( $pattern, function( $matches ) use ( &$lkst_toc_items ) {
            $level = $matches[1];
            $attrs = $matches[2];
            $text  = strip_tags( $matches[3] );
            if ( preg_match( '/id=[\'"]([^\'"]+)[\'"]/i', $attrs, $id_m ) ) {
                $id = $id_m[1];
            } else {
                $id = sanitize_title( $text );
                if ( empty( $id ) ) $id = 'section-' . count( $lkst_toc_items );
            }
            $lkst_toc_items[] = [ 'level' => $level, 'id' => $id, 'text' => trim( $text ) ];
            return $matches[0];
        }, $content );
    }

    public function process_content( $content ) {
        if ( ! is_singular() ) return $content;
        if ( isset( $_GET['bricks'] ) || isset( $_GET['etchwp'] ) || isset( $_GET['elementor-preview'] ) ) return $content;

        global $lkst_toc_processing, $lkst_toc_items;
        if ( $lkst_toc_processing ) return $content;

        $settings  = get_option( 'lkst_toc_settings', static::get_defaults() );
        $post_type = get_post_type();
        if ( ! in_array( $post_type, $settings['post_types'], true ) ) return $content;

        $pattern   = '/<h([2-3])([^>]*)>(.*?)<\/h\1>/is';
        $new_items = [];

        $content = preg_replace_callback( $pattern, function( $matches ) use ( &$new_items ) {
            $level      = $matches[1];
            $attributes = $matches[2];
            $text       = strip_tags( $matches[3] );
            if ( preg_match( '/id=[\'"]([^\'"]+)[\'"]/i', $attributes, $id_matches ) ) {
                $id = $id_matches[1];
            } else {
                $id          = sanitize_title( $text );
                if ( empty( $id ) ) $id = 'section-' . uniqid();
                $attributes .= ' id="' . esc_attr( $id ) . '"';
            }
            $new_items[] = [ 'level' => $level, 'id' => $id, 'text' => trim( $text ) ];
            return "<h{$level}{$attributes}>{$matches[3]}</h{$level}>";
        }, $content );

        $items = ! empty( $lkst_toc_items ) ? $lkst_toc_items : $new_items;

        if ( empty( $items ) || count( $items ) < 2 ) {
            $content = str_replace( '[lkst_toc]', '', $content );
            return $content;
        }

        $toc_html = $this->build_toc_html( $items );

        if ( strpos( $content, '[lkst_toc]' ) !== false ) {
            $content = str_replace( '[lkst_toc]', $toc_html, $content );
        } elseif ( $settings['insertion'] === 'auto' ) {
            $content = $toc_html . $content;
        }

        return $content;
    }

    public function render_shortcode() {
        global $lkst_toc_items;
        if ( ! empty( $lkst_toc_items ) && count( $lkst_toc_items ) >= 2 ) {
            return $this->build_toc_html( $lkst_toc_items );
        }
        return '[lkst_toc]';
    }

    private function build_toc_html( $items ) {
        $html  = '<div class="lkst-toc-wrapper" data-lkst-toc>';
        $html .= '<div class="lkst-toc-header">';
        $html .= '<div class="lkst-toc-title"><strong>BROWSE</strong> <span class="lkst-toc-sep">|</span> <div class="lkst-toc-active-text-wrapper"><span class="lkst-toc-active-text">Sections in this article</span></div></div>';
        $html .= '<button class="lkst-toc-toggle" aria-expanded="false" aria-label="Toggle table of contents">';
        $html .= '<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '<div class="lkst-toc-body"><ul class="lkst-toc-list">';
        foreach ( $items as $item ) {
            $depth_class = ( $item['level'] == '3' ) ? ' lkst-toc-depth-3' : ' lkst-toc-depth-2';
            $html .= '<li class="lkst-toc-item' . $depth_class . '"><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a></li>';
        }
        $html .= '</ul></div>';
        $html .= '</div>';
        return $html;
    }

    public function render_page() {
        $s          = get_option( 'lkst_toc_settings', static::get_defaults() );
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $pt_exclude = [ 'attachment', 'bricks_template', 'etch_template', 'elementor_library', 'ifso_triggers' ];
        ?>
        <div class="wrap">
            <h1>Table of Contents Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_toc_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Insertion Method</th>
                        <td>
                            <label>
                                <input type="radio" name="lkst_toc_settings[insertion]" value="auto" <?php checked( $s['insertion'], 'auto' ); ?>>
                                <strong>Auto-inject</strong> (Automatically adds the TOC to the very top of the post content)
                            </label><br><br>
                            <label>
                                <input type="radio" name="lkst_toc_settings[insertion]" value="shortcode" <?php checked( $s['insertion'], 'shortcode' ); ?>>
                                <strong>Shortcode Only</strong> (Only renders where you place the <code>[lkst_toc]</code> shortcode in your builder or content)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Active Post Types</th>
                        <td>
                            <?php foreach ( $post_types as $slug => $pt ) :
                                if ( in_array( $slug, $pt_exclude, true ) ) continue; ?>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox"
                                           name="lkst_toc_settings[post_types][]"
                                           value="<?php echo esc_attr( $slug ); ?>"
                                           <?php checked( in_array( $slug, $s['post_types'], true ) ); ?>>
                                    <?php echo esc_html( $pt->label ); ?>
                                    <code style="margin-left:4px;"><?php echo esc_html( $slug ); ?></code>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">Select which post types the TOC should be generated for.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }
}