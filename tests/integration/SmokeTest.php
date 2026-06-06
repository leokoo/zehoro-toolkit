<?php
/**
 * Smoke test — does the plugin load + register at least one default module?
 *
 * This is the canary that proves the entire bootstrap chain (composer →
 * wp-phpunit → WP core at /tmp/wordpress/ → plugin's own autoloader →
 * Plugin::register_module() invocations) works end-to-end. If this fails,
 * nothing else can.
 *
 * @package Zehoro\Tests\Integration
 */

class SmokeTest extends WP_UnitTestCase {

    public function test_plugin_main_constant_is_defined() {
        $this->assertTrue(
            defined( 'ZEHORO_VERSION' ),
            'ZEHORO_VERSION constant should be defined when the plugin is loaded'
        );
    }

    public function test_plugin_class_loaded() {
        $this->assertTrue(
            class_exists( '\\Zehoro\\Core\\Plugin' ),
            'Plugin class should be autoloaded'
        );
        $this->assertTrue(
            interface_exists( '\\Zehoro\\Core\\ModuleInterface' ),
            'ModuleInterface should be autoloaded'
        );
    }

    public function test_at_least_one_module_registered() {
        $registered = \Zehoro\Core\Plugin::get_registered_modules();
        $this->assertIsArray( $registered );
        $this->assertNotEmpty(
            $registered,
            'At least one module should self-register on plugins_loaded'
        );
    }

    public function test_known_module_appears_in_registry() {
        $registered = \Zehoro\Core\Plugin::get_registered_modules();
        $this->assertArrayHasKey(
            'table_of_contents',
            $registered,
            'TableOfContents module should be in the registry (it defaults to active)'
        );
        $this->assertSame( 'Table of Contents', $registered['table_of_contents']['title'] );
    }
}
