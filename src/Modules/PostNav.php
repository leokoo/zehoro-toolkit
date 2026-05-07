<?php
namespace LK\SiteToolkit\Modules;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Post Navigation Module
 *
 * Renders Previous/Next post links with custom markup.
 * Usage: [lkst_post_nav]
 */
class PostNav {
    public function init() {
        add_shortcode( 'lkst_post_nav', [ $this, 'render' ] );
    }

    public function render() {
        $prev = get_previous_post();
        $next = get_next_post();
        if ( ! $prev && ! $next ) return '';

        $html = '<nav class="lkst-post-nav" aria-label="Post navigation">';

        if ( $prev ) {
            $html .= '<a href="' . esc_url( get_permalink( $prev ) ) . '" class="lkst-post-nav__item lkst-post-nav__prev">';
            $html .= '<span class="lkst-post-nav__label">&larr; Previous</span>';
            $html .= '<span class="lkst-post-nav__title">' . esc_html( get_the_title( $prev ) ) . '</span>';
            $html .= '</a>';
        } else {
            $html .= '<span class="lkst-post-nav__item lkst-post-nav__empty"></span>';
        }

        if ( $next ) {
            $html .= '<a href="' . esc_url( get_permalink( $next ) ) . '" class="lkst-post-nav__item lkst-post-nav__next">';
            $html .= '<span class="lkst-post-nav__label">Next &rarr;</span>';
            $html .= '<span class="lkst-post-nav__title">' . esc_html( get_the_title( $next ) ) . '</span>';
            $html .= '</a>';
        } else {
            $html .= '<span class="lkst-post-nav__item lkst-post-nav__empty"></span>';
        }

        $html .= '</nav>';
        return $html;
    }
}