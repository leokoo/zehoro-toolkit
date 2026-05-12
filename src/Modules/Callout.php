<?php
namespace LK\SiteToolkit\Modules;
use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class Callout implements ModuleInterface {
    public static function register(): void {
        Plugin::register_module( 'callout', self::class, [
            'title' => 'Callout Blocks (Native Block)',
            'desc'  => 'Visual breaks for long-form content. Use the native Gutenberg Callout block.',
            'default' => true
        ] );
    }
    public function init(): void {
        add_action( 'init', [ $this, 'register_blocks' ] );
    }
    public function register_blocks(): void {
        register_block_type( LKST_DIR . 'build/callout' );
    }
}