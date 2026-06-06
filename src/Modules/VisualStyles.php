<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;
use Zehoro\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Visual Styles module.
 *
 * Registers the styles slug in the module registry so it appears on the
 * Dashboard and the Visual Styles settings page is reachable. CSS custom
 * properties are injected by Plugin::enqueue_assets() via wp_add_inline_style(),
 * which outputs AFTER the stylesheet <link> tag and wins the cascade.
 * Nothing to hook on the frontend.
 */
class VisualStyles implements ModuleInterface {

    public static function register(): void {
        Plugin::register_module( 'styles', self::class, [
            'title'         => 'Visual Styles',
            'desc'          => 'Customize brand colors for Author Box, CTAs, and Category Pills.',
            'default'       => true,
            'settings_page' => 'lkst-styles',
        ] );
    }

    public function init(): void {}
}
