<?php
namespace LK\SiteToolkit\Modules;
use LK\SiteToolkit\Core\Plugin;

use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * News Ticker Module
 *
 * Outputs a continuously scrolling marquee of recent post titles.
 * Separator and link colors use CSS classes (.lkst-ticker__sep, .lkst-ticker__link)
 * so they pick up CSS custom properties and can be overridden per-site.
 * Usage: [lkst_ticker_posts]
 *
 * @package LK\SiteToolkit\Modules
 */
class NewsTicker implements \LK\SiteToolkit\Core\ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'news_ticker', self::class, [
            'title'   => 'News Ticker',
            'desc'    => 'Horizontal scrolling marquee for recent posts. Use [lkst_ticker_posts].',
            'default' => true
        ] );
    }


    public function init(): void {
        add_shortcode( 'lkst_ticker_posts', [ $this, 'render' ] );
    }

    public function render(): string {
        $post_types = apply_filters( 'lkst/ticker/post_types', [ 'post' ] );
        $posts = get_posts( [
            'post_type'      => $post_types,
            'posts_per_page' => 15,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        if ( empty( $posts ) ) return '';

        // Use CSS classes so the separator color inherits --lkst-primary-color
        // and theme overrides apply without touching this file.
        $sep   = '<span class="lkst-ticker__sep">&#9670;</span>';
        $items = '';
        foreach ( $posts as $p ) {
            $items .= '<a href="' . esc_url( get_permalink( $p->ID ) ) . '" class="lkst-ticker__link">' . esc_html( $p->post_title ) . '</a>' . $sep;
        }
        $track_content = $items . $items; // duplicate for seamless looping
        return '<div class="lkst-ticker"><div class="lkst-ticker__wrap"><div class="lkst-ticker__track">' . $track_content . '</div></div></div>';
    }
}