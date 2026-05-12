<?php
namespace LK\SiteToolkit\Modules;
use LK\SiteToolkit\Core\Plugin;

use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Category Pills Module
 *
 * Renders a horizontal list of dynamic category/taxonomy pills.
 * Results are cached in transients (12 h) and invalidated on term changes.
 * Usage: [lkst_top_category_pills limit="8"]
 *
 * @package LK\SiteToolkit\Modules
 */
class CategoryPills implements \LK\SiteToolkit\Core\ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'category_pills', self::class, [
            'title'   => 'Category Pills',
            'desc'    => 'Dynamic post category or tag pills for archives. Use [lkst_top_category_pills].',
            'default' => true
        ] );
    }


    public function init(): void {
        add_shortcode( 'lkst_top_category_pills', [ $this, 'render' ] );
        // Invalidate cache when taxonomy terms change
        add_action( 'created_term',         [ $this, 'clear_cache' ] );
        add_action( 'edited_term_taxonomy',  [ $this, 'clear_cache' ] );
        add_action( 'delete_term',           [ $this, 'clear_cache' ] );
    }

    public function clear_cache(): void {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lkst_pills_%' OR option_name LIKE '_transient_timeout_lkst_pills_%'" );
    }

    public function render( $atts ): string {
        global $wpdb;
        $atts  = shortcode_atts( [ 'limit' => 8 ], $atts );
        $limit = max( 1, (int) $atts['limit'] );

        $post_type = apply_filters( 'lkst/category_pills/post_type', null );
        $taxonomy  = apply_filters( 'lkst/category_pills/taxonomy',  null );

        if ( is_category() || is_tag() || is_tax() ) {
            $obj      = get_queried_object();
            $taxonomy = $taxonomy ?: ( $obj->taxonomy ?? 'category' );
            $excl_id  = (int) ( $obj->term_id ?? 0 );

            $cache_key = 'lkst_pills_' . md5( $taxonomy . $excl_id . $limit );
            $rows = get_transient( $cache_key );
            if ( false === $rows ) {
                $rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT t.term_id, t.name, t.slug, tt.count AS n
                     FROM {$wpdb->terms} t
                     JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                     WHERE tt.taxonomy = %s AND t.term_id != %d AND tt.count > 0
                     ORDER BY n DESC LIMIT %d",
                    $taxonomy, $excl_id, $limit
                ) );
                set_transient( $cache_key, $rows, 12 * HOUR_IN_SECONDS );
            }

            if ( empty( $rows ) ) return '';
            return $this->render_pills( $rows, $taxonomy );
        }

        if ( is_post_type_archive() ) {
            $post_type = $post_type ?: get_query_var( 'post_type' );
            if ( ! $taxonomy ) {
                $tax_objs  = get_object_taxonomies( $post_type, 'objects' );
                $taxonomy  = ! empty( $tax_objs ) ? array_key_first( $tax_objs ) : 'category';
            }
        } elseif ( is_home() ) {
            $post_type = $post_type ?: 'post';
            $taxonomy  = $taxonomy  ?: 'category';
        } else {
            return '';
        }

        $cache_key = 'lkst_pills_' . md5( $taxonomy . $post_type . $limit );
        $rows = get_transient( $cache_key );
        if ( false === $rows ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT t.term_id, t.name, t.slug, COUNT(tr.object_id) AS n
                 FROM {$wpdb->terms} t
                 JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                 JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                 WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status = 'publish'
                 GROUP BY t.term_id ORDER BY n DESC LIMIT %d",
                $taxonomy, $post_type, $limit
            ) );
            set_transient( $cache_key, $rows, 12 * HOUR_IN_SECONDS );
        }

        if ( empty( $rows ) ) return '';
        return $this->render_pills( $rows, $taxonomy );
    }

    private function render_pills( array $rows, string $taxonomy ): string {
        $html = '<div class="lkst-cat-pills">';
        foreach ( $rows as $row ) {
            $url = get_term_link( (int) $row->term_id, $taxonomy );
            if ( is_wp_error( $url ) ) continue;
            $name  = html_entity_decode( $row->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            $count = (int) $row->n;
            $badge = $count > 1 ? '<span class="lkst-pill-count">' . $count . '</span>' : '';
            $html .= sprintf(
                '<a href="%s" class="lkst-cat-pill">%s%s</a>',
                esc_url( $url ), esc_html( $name ), $badge
            );
        }
        return $html . '</div>';
    }
}