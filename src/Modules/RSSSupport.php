<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class RSSSupport implements ModuleInterface {
    public static function register(): void {
        Plugin::register_module( 'rss_support', self::class, [
            'title'   => 'RSS CPT Support',
            'desc'    => 'Include custom post types in your main site RSS feed.',
            'default' => true,
            'settings_page' => 'lkst-rss-feed',
        ] );
    }

    public function init(): void {
        add_filter( 'request', function ( $qv ) {
            if ( ! isset( $qv['feed'] ) || isset( $qv['post_type'] ) ) return $qv;
            $selected = get_option( 'lkst_rss_post_types', [ 'post' ] );
            if ( empty( $selected ) ) $selected = [ 'post' ];
            $qv['post_type'] = $selected;
            return $qv;
        } );
    }
}