<?php
/**
 * Plugin Name:  Zehoro Toolkit
 * Plugin URI:   https://leokoo.com
 * Description:  Editorial toolkit for WordPress — Article schema (E-E-A-T), Table of Contents, FAQ, author boxes, and content blocks. The free base for Zehoro Toolkit Pro.
 * Version:      1.6.1
 * Author:       Leo Koo
 * Author URI:   https://leokoo.com
 * Text Domain:  zehoro-toolkit
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Prevent the plugin running twice when WordPress loads two copies from
// different folder names (e.g. zehoro-toolkit-main/ alongside
// zehoro-toolkit/). The second copy returns immediately.
if ( defined( 'ZEHORO_VERSION' ) ) return;

define( 'ZEHORO_VERSION', '1.6.1' );
define( 'ZEHORO_DIR',     plugin_dir_path( __FILE__ ) );
define( 'ZEHORO_URL',     plugin_dir_url( __FILE__ ) );

// Autoloader for Zehoro namespace
spl_autoload_register( function( $class ) {
    $prefix = 'Zehoro\\';
    $base_dir = ZEHORO_DIR . 'src/';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) return;
    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
    if ( file_exists( $file ) ) require $file;
} );

// --- Auto-updater (GitHub) ---
require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

$lkst_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/leokoo/zehoro-toolkit/',
    __FILE__,
    'zehoro-toolkit'
);
// Use Pro token if available (avoids GitHub API rate-limit on unauthenticated calls)
$gh_token = get_option( 'lkst_pro_github_token', '' );
if ( empty( $gh_token ) && defined( 'ZEHORO_GITHUB_TOKEN' ) ) {
    $gh_token = ZEHORO_GITHUB_TOKEN;
}
if ( ! empty( $gh_token ) ) {
    $lkst_updater->setAuthentication( $gh_token );
}
$lkst_updater->setBranch( 'main' );
$lkst_updater->getVcsApi()->enableReleaseAssets();

// Initialize the core plugin
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'zehoro-toolkit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    $plugin = new \Zehoro\Core\Plugin();
    $plugin->init();
} );

register_activation_hook( __FILE__, function() {
    \Zehoro\Core\Plugin::activate();
} );

register_deactivation_hook( __FILE__, function() {
    \Zehoro\Core\Plugin::deactivate();
} );
// Add Settings link on the plugin page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
    $settings_link = '<a href="admin.php?page=lkst-dashboard">' . __( 'Settings', 'zehoro-toolkit' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );