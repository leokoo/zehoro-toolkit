<?php
namespace LK\SiteToolkit\Modules;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Reading Time Module
 *
 * Calculates and outputs estimated reading time for the current post content.
 * Assumes a reading speed of 200 words per minute.
 * Usage: [lkst_read_time]
 */
class ReadingTime {
    public function init() {
        add_shortcode( 'lkst_read_time', [ $this, 'render' ] );
    }

    public function render() {
        $content = get_post_field( 'post_content', get_the_ID() );
        $words   = str_word_count( strip_tags( $content ) );
        $minutes = max( 1, (int) round( $words / 200 ) );
        return $minutes . ' min read';
    }
}