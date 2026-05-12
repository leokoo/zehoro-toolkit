<?php
namespace LK\SiteToolkit\Modules;
use LK\SiteToolkit\Core\Plugin;
use LK\SiteToolkit\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class TLDR implements ModuleInterface {
    public static function register(): void {
        Plugin::register_module( 'tldr', self::class, [
            'title' => 'TL;DR / Key Takeaways (Native Block)',
            'desc'  => 'A styled native Gutenberg block for the top of your articles.',
            'default' => true
        ] );
    }
    
    public function init(): void {
        add_action( 'init', [ $this, 'register_block' ] );
    }
    
    public function register_block(): void {
        register_block_type( LKST_DIR . 'build/tldr' );
    }
}