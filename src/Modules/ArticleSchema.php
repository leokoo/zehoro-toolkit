<?php
namespace LK\SiteToolkit\Modules;

use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class ArticleSchema implements ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'article_schema', self::class, [
            'title'   => 'Article Schema (E-E-A-T)',
            'desc'    => 'Automatically outputs JSON-LD Article schema in the head of single posts.',
            'default' => true
        ] );
    }

    public function init(): void {
        add_action( 'wp_head', [ $this, 'output_schema' ] );
    }

    public function output_schema(): void {
        if ( ! is_single() ) return;

        global $post;

        $author_id   = $post->post_author;
        $author_name = get_the_author_meta( 'display_name', $author_id );
        $author_url  = get_author_posts_url( $author_id );

        $author_schema = [
            '@type' => 'Person',
            'name'  => $author_name,
            'url'   => $author_url,
        ];

        // Override URL if the user has a custom website entered in their profile
        $custom_author_url = get_the_author_meta( 'user_url', $author_id );
        if ( ! empty( $custom_author_url ) ) {
            $author_schema['url'] = esc_url( $custom_author_url );
        }

        // Add social links from AuthorBox if available (lkst_author_linkedin, etc)
        $linkedin = get_the_author_meta( 'lkst_author_linkedin', $author_id );
        $twitter  = get_the_author_meta( 'lkst_author_twitter', $author_id );
        $same_as  = [];
        if ( ! empty( $linkedin ) ) $same_as[] = esc_url( $linkedin );
        if ( ! empty( $twitter ) ) $same_as[] = esc_url( $twitter );
        if ( ! empty( $same_as ) ) {
            $author_schema['sameAs'] = $same_as;
        }

        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post->ID ),
            ],
            'headline'         => get_the_title( $post->ID ),
            'datePublished'    => get_the_date( 'c', $post->ID ),
            'dateModified'     => get_the_modified_date( 'c', $post->ID ),
            'author'           => $author_schema,
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'url'   => home_url(),
            ],
        ];

        // Publisher Logo
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
            if ( $logo_url ) {
                $schema['publisher']['logo'] = [
                    '@type' => 'ImageObject',
                    'url'   => esc_url( $logo_url ),
                ];
            }
        }

        // Featured Image
        $thumbnail_id = get_post_thumbnail_id( $post->ID );
        if ( $thumbnail_id ) {
            $image_url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
            if ( $image_url ) {
                $schema['image'] = esc_url( $image_url );
            }
        }

        // Word Count
        $word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
        if ( $word_count > 0 ) {
            $schema['wordCount'] = $word_count;
        }

        echo "\n<!-- LKST Article Schema -->\n";
        echo '<script type="application/ld+json">';
        echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        echo "</script>\n";
    }
}
