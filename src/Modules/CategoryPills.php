<?php
namespace LK\SiteToolkit\Modules;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Category Pills Module
 *
 * Automatically detects the current archive context and renders a horizontal list
 * of dynamic category/taxonomy pills representing the most popular terms.
 * Usage: [lkst_top_category_pills limit="8"]
 */
class CategoryPills {
    public function init() {
        add_shortcode( 'lkst_top_category_pills', [ $this, 'render' ] );
    }

    public function render( $atts ) {
        global $wpdb;
        $atts = shortcode_atts( [ 'limit' => 8 ], $atts );
        $limit = max( 1, (int) $atts['limit'] );

        $post_type = apply_filters( 'lkst/category_pills/post_type', null );
        $taxonomy  = apply_filters( 'lkst/category_pills/taxonomy',  null );

        if ( is_category() || is_tag() || is_tax() ) {
            $obj      = get_queried_object();
            $taxonomy = $taxonomy ?: ( $obj->taxonomy ?? 'category' );
            $excl_id  = (int) ( $obj->term_id ?? 0 );
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT t.term_id, t.name, t.slug, tt.count AS n
                 FROM {$wpdb->terms} t
                 JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                 WHERE tt.taxonomy = %s AND t.term_id != %d AND tt.count > 0
                 ORDER BY n DESC LIMIT %d",
                $taxonomy, $excl_id, $limit
            ) );
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

        if ( empty( $rows ) ) return '';
        return $this->render_pills( $rows, $taxonomy );
    }

    private function render_pills( $rows, $taxonomy ) {
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