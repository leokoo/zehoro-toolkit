<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;

use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Table of Contents Module
 *
 * Parses H2/H3 headings, injects anchor IDs, and renders a collapsible TOC.
 * The preparse step reads raw post_content (no do_shortcode) so:
 *   - shortcodes are not expanded unnecessarily early
 *   - expensive form/gallery shortcodes don\'t fire outside their normal context
 *   - Gutenberg block HTML already contains headings as plain HTML tags
 * Usage: [lkst_toc]
 *
 * @package Zehoro\Modules
 */
class TableOfContents implements \Zehoro\Core\ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'table_of_contents', self::class, [
            'title'   => 'Table of Contents',
            'desc'    => 'Wirecutter-style TOC. Auto-injects at the top of posts, or use [lkst_toc].',
            'default' => true,
            'settings_page' => 'lkst-toc-settings'
        ] );
    }


    public function init(): void {
        if ( is_admin() ) {
            add_action( 'admin_init', [ $this, 'register_settings' ] );
        }
        add_action( 'wp',          [ $this, 'preparse_toc_headings' ], 10 );
        add_filter( 'the_content', [ $this, 'process_content' ], 15 );
        add_shortcode( 'lkst_toc', [ $this, 'render_shortcode' ] );
    }

    public static function get_defaults(): array {
        return [
            'post_types' => [ 'post', 'reviews', 'buying-guides' ],
            'insertion'  => 'auto',
        ];
    }

    public function register_settings(): void {
        register_setting( 'lkst_toc_group', 'lkst_toc_settings', [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => static::get_defaults(),
        ] );
    }

    public function sanitize_settings( $input ): array {
        if ( ! is_array( $input ) ) return static::get_defaults();
        $out = [ 'post_types' => [] ];
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
     * Pre-parses post headings so a [lkst_toc] shortcode in a sidebar
     * element can render immediately, before the_content processes.
     *
     * Uses raw post_content (no do_shortcode) because:
     *   1. Gutenberg block HTML already contains <h2>/<h3> as plain HTML.
     *   2. Calling do_shortcode() here expands all shortcodes twice, which
     *      causes side effects in forms, galleries, and other stateful shortcodes.
     */
    public function preparse_toc_headings(): void {
        if ( ! is_singular() ) return;
        $settings = get_option( 'lkst_toc_settings', static::get_defaults() );
        if ( ! in_array( get_post_type(), $settings['post_types'], true ) ) return;

        global $lkst_toc_items;
        if ( ! empty( $lkst_toc_items ) ) return;

        $post = get_post();
        if ( ! $post ) return;

        $cache_key = 'lkst_toc_' . $post->ID;
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) && isset( $cached['time'] ) && $cached['time'] === $post->post_modified ) {
            $lkst_toc_items = $cached['items'];
            return;
        }

        // Raw content — no shortcode expansion, no wpautop.
        // Headings in Gutenberg posts are already plain HTML here.
        $content        = $post->post_content;
        $pattern        = '/<h([2-3])([^>]*)>(.*?)<\/h\1>/is';
        $lkst_toc_items = [];

        preg_replace_callback( $pattern, function ( $matches ) use ( &$lkst_toc_items ) {
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
        
        set_transient( $cache_key, [ 'time' => $post->post_modified, 'items' => $lkst_toc_items ], 7 * DAY_IN_SECONDS );
    }

    public function process_content( string $content ): string {
        if ( ! is_singular() ) return $content;
        if ( isset( $_GET['bricks'] ) || isset( $_GET['etchwp'] ) || isset( $_GET['elementor-preview'] ) ) return $content;

        global $lkst_toc_processing, $lkst_toc_items;
        if ( $lkst_toc_processing ) return $content;

        $settings  = get_option( 'lkst_toc_settings', static::get_defaults() );
        $post_type = get_post_type();
        if ( ! in_array( $post_type, $settings['post_types'], true ) ) return $content;

        $pattern   = '/<h([2-3])([^>]*)>(.*?)<\/h\1>/is';
        $new_items = [];

        $content = preg_replace_callback( $pattern, function ( $matches ) use ( &$new_items ) {
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
            // Remove any [lkst_toc] placeholder — not enough headings to render.
            return str_replace( '[lkst_toc]', '', $content );
        }

        $toc_html = $this->build_toc_html( $items );

        if ( strpos( $content, '[lkst_toc]' ) !== false ) {
            $content = str_replace( '[lkst_toc]', $toc_html, $content );
        } elseif ( $settings['insertion'] === 'auto' ) {
            $content = $toc_html . $content;
        }

        return $content;
    }

    /**
     * Shortcode handler — used when the TOC is placed via a builder shortcode element
     * (e.g., a Bricks shortcode widget in the sidebar) rather than inside post content.
     *
     * Returns the built TOC HTML when enough headings exist, or an empty string.
     * Never returns the literal "[lkst_toc]" string — that would render as visible
     * text inside the shortcode element (Bug 4).
     */
    public function render_shortcode(): string {
        global $lkst_toc_items;
        if ( ! empty( $lkst_toc_items ) && count( $lkst_toc_items ) >= 2 ) {
            return $this->build_toc_html( $lkst_toc_items );
        }
        // Not enough headings — output nothing.
        return '';
    }

    private function build_toc_html( array $items ): string {
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

    public function render_page(): void {
        $s          = get_option( 'lkst_toc_settings', static::get_defaults() );
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $pt_exclude = [ 'attachment', 'bricks_template', 'etch_template', 'elementor_library', 'ifso_triggers' ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Table of Contents Settings', 'zehoro-toolkit' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'lkst_toc_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Insertion Method', 'zehoro-toolkit' ); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="lkst_toc_settings[insertion]" value="auto" <?php checked( $s['insertion'], 'auto' ); ?>>
                                <strong><?php esc_html_e( 'Auto-inject', 'zehoro-toolkit' ); ?></strong>
                                <?php esc_html_e( '(Automatically adds the TOC to the very top of the post content)', 'zehoro-toolkit' ); ?>
                            </label><br><br>
                            <label>
                                <input type="radio" name="lkst_toc_settings[insertion]" value="shortcode" <?php checked( $s['insertion'], 'shortcode' ); ?>>
                                <strong><?php esc_html_e( 'Shortcode Only', 'zehoro-toolkit' ); ?></strong>
                                <?php esc_html_e( '(Only renders where you place the [lkst_toc] shortcode)', 'zehoro-toolkit' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Active Post Types', 'zehoro-toolkit' ); ?></th>
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
                            <p class="description"><?php esc_html_e( 'Select which post types the TOC should be generated for.', 'zehoro-toolkit' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save Settings', 'zehoro-toolkit' ) ); ?>
            </form>
        </div>
        <?php
    }
}