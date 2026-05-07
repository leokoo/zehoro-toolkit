<?php
/**
 * Plugin Name:  Leokoo Site Toolkit
 * Plugin URI:   https://leokoo.com
 * Description:  Modular utility suite for WordPress sites.
 * Version:      1.10.0
 * Author:       Leo Koo
 * Author URI:   https://leokoo.com
 * Text Domain:  leokoo-site-toolkit
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LKST_VERSION', '1.0.3' );
define( 'LKST_DIR', plugin_dir_path( __FILE__ ) );
define( 'LKST_URL', plugin_dir_url( __FILE__ ) );

// Autoloader for LK\SiteToolkit namespace
spl_autoload_register( function( $class ) {
    $prefix = 'LK\\SiteToolkit\\';
    $base_dir = LKST_DIR . 'src/';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) return;
    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
    if ( file_exists( $file ) ) require $file;
} );

// Initialize the core plugin
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'leokoo-site-toolkit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    $plugin = new \LK\SiteToolkit\Core\Plugin();
    $plugin->init();
} );

register_activation_hook( __FILE__, function() {
    \LK\SiteToolkit\Core\Plugin::activate();
} );