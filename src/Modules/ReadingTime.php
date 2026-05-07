<?php
namespace LK\SiteToolkit\Modules;

use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Reading Time Module
 *
 * Calculates estimated reading time for the current post.
 * Shortcode tags ([fluentform id="3"] etc.) and Gutenberg block comments
 * are stripped before word-counting so they don\'t inflate the estimate.
 * Assumes a reading speed of 200 wpm.
 * Usage: [lkst_read_time]
 *
 * @package LK\SiteToolkit\Modules
 */
class ReadingTime implements ModuleInterface {

    public function init(): void {
        add_shortcode( 'lkst_read_time', [ $this, 'render' ] );
    }

    public function render(): string {
        $content = get_post_field( 'post_content', get_the_ID() );
        // Strip Gutenberg block comments (<!-- wp:paragraph --> etc.) and
        // shortcode tags ([shortcode attr="val"]) before counting words.
        // Both patterns produce phantom words after strip_tags().
        $clean   = preg_replace( [ '/<!--.*?-->/s', '/\[[^\]]*\]/' ], '', $content );
        $words   = str_word_count( strip_tags( $clean ) );
        $minutes = max( 1, (int) round( $words / 200 ) );
        return $minutes . ' min read';
    }
}