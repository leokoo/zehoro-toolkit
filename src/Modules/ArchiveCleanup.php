<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

class ArchiveCleanup implements ModuleInterface {
    public static function register(): void {
        Plugin::register_module( 'archive_cleanup', self::class, [
            'title'   => 'Archive Title Cleanup',
            'desc'    => 'Removes prefixes like "Category:", "Tag:", etc. from archive titles globally.',
            'default' => true,
        ] );
    }

    public function init(): void {
        add_filter( 'get_the_archive_title_prefix', '__return_false' );
    }
}