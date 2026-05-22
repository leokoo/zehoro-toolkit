<?php
namespace LK\SiteToolkit\Modules;
use LK\SiteToolkit\Core\Plugin;

use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Reading Time Module
 *
 * Calculates estimated reading time for the current post.
 * Shortcode tags ([fluentform id="3"] etc.) and Gutenberg block comments
 * are stripped before word-counting so they don't inflate the estimate.
 * Assumes a reading speed of 200 wpm.
 * Usage: [lkst_read_time] (shortcode) or {lkst_read_time} (Bricks dynamic tag)
 *
 * @package LK\SiteToolkit\Modules
 */
class ReadingTime implements \LK\SiteToolkit\Core\ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'reading_time', self::class, [
            'title'   => 'Reading Time',
            'desc'    => 'Calculates and displays estimated reading time. Use [lkst_read_time] or {lkst_read_time} in Bricks.',
            'default' => true
        ] );
    }

    public function init(): void {
        add_shortcode( 'lkst_read_time', [ $this, 'render' ] );
        add_filter( 'bricks/dynamic_tags_list', [ $this, 'register_bricks_tag' ] );
        add_filter( 'bricks/dynamic_data/render_tag', [ $this, 'render_bricks_tag' ], 10, 3 );
    }

    /**
     * Register {lkst_read_time} as a Bricks dynamic data tag.
     */
    public function register_bricks_tag( array $tags ): array {
        $tags[] = [
            'name'  => '{lkst_read_time}',
            'label' => 'Reading Time',
            'group' => 'LKST',
        ];
        return $tags;
    }

    /**
     * Render the {lkst_read_time} tag when Bricks processes dynamic data.
     *
     * @param string         $tag     The tag name (without braces).
     * @param \WP_Post|int   $post    The current post object or ID.
     * @param string         $context Bricks render context.
     * @return string
     */
    public function render_bricks_tag( string $tag, $post, string $context ): string {
        if ( $tag !== 'lkst_read_time' ) {
            return $tag;
        }
        $post_id = is_object( $post ) ? $post->ID : (int) $post;
        return $this->render_for_post( $post_id );
    }

    /**
     * Core calculation — shared by shortcode and Bricks dynamic tag.
     */
    private function render_for_post( int $post_id ): string {
        $content = get_post_field( 'post_content', $post_id );
        // Strip Gutenberg block comments (<!-- wp:paragraph --> etc.) and
        // shortcode tags ([shortcode attr="val"]) before counting words.
        // Both patterns produce phantom words after strip_tags().
        $clean   = preg_replace( [ '/<!--.*?-->/s', '/\[[^\]]*\]/' ], '', $content );
        $words   = str_word_count( strip_tags( $clean ) );
        $minutes = max( 1, (int) round( $words / 200 ) );
        return $minutes . ' min read';
    }

    /**
     * Shortcode handler: [lkst_read_time]
     */
    public function render(): string {
        return $this->render_for_post( get_the_ID() );
    }
}