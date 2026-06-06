<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

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
        register_block_type( ZEHORO_DIR . 'build/pros-cons' );
        register_block_type( ZEHORO_DIR . 'build/pros' );
        register_block_type( ZEHORO_DIR . 'build/cons' );
    }
}