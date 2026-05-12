<?php
namespace LK\SiteToolkit\Modules;
use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class ProsCons implements ModuleInterface {
    public static function register(): void {
        Plugin::register_module( 'pros_cons', self::class, [
            'title' => 'Semantic Pros & Cons (Native Block)',
            'desc'  => 'Wirecutter-style pros/cons boxes. Use the native Gutenberg Pros & Cons block.',
            'default' => true
        ] );
    }
    public function init(): void {
        add_action( 'init', [ $this, 'register_blocks' ] );
    }
    public function register_blocks(): void {
        register_block_type( LKST_DIR . 'build/pros-cons' );
        register_block_type( LKST_DIR . 'build/pros' );
        register_block_type( LKST_DIR . 'build/cons' );
    }
}