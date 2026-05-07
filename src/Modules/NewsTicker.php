<?php
namespace LK\SiteToolkit\Modules;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * News Ticker Module
 *
 * Outputs a continuously scrolling marquee of recent post titles.
 * Self-contained HTML wrappers ensure compatibility with any page builder.
 * Usage: [lkst_ticker_posts]
 */
class NewsTicker {
    public function init() {
        add_shortcode( 'lkst_ticker_posts', [ $this, 'render' ] );
    }

    public function render() {
        $post_types = apply_filters( 'lkst/ticker/post_types', [ 'post' ] );
        $posts = get_posts( [
            'post_type'      => $post_types,
            'posts_per_page' => 15,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        if ( empty( $posts ) ) return '';
        $sep   = '<span style="color:#E8A020;padding:0 14px;flex-shrink:0;">&#9670;</span>';
        $items = '';
        foreach ( $posts as $p ) {
            $items .= '<a href="' . esc_url( get_permalink( $p->ID ) ) . '" style="color:#F5F0E8;white-space:nowrap;text-decoration:none;flex-shrink:0;">' . esc_html( $p->post_title ) . '</a>' . $sep;
        }
                $track_content = $items . $items;
        return '<div class="lkst-ticker"><div class="lkst-ticker__wrap"><div class="lkst-ticker__track">' . $track_content . '</div></div></div>';
    }
}