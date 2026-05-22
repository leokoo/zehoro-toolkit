<?php
/**
 * Plugin Name:  Leokoo Site Toolkit
 * Plugin URI:   https://leokoo.com
 * Description:  Modular utility suite for WordPress sites.
 * Version:      1.4.1
 * Author:       Leo Koo
 * Author URI:   https://leokoo.com
 * Text Domain:  leokoo-site-toolkit
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Prevent the plugin running twice when WordPress loads two copies from
// different folder names (e.g. leokoo-site-toolkit-main/ alongside
// leokoo-site-toolkit/). The second copy returns immediately.
if ( defined( 'LKST_VERSION' ) ) return;

define( 'LKST_VERSION', '1.4.1' );
define( 'LKST_DIR',     plugin_dir_path( __FILE__ ) );
define( 'LKST_URL',     plugin_dir_url( __FILE__ ) );

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

// --- Auto-updater (GitHub) ---
require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

$lkst_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/leokoo/leokoo-site-toolkit/',
    __FILE__,
    'leokoo-site-toolkit'
);
// Use Pro token if available (avoids GitHub API rate-limit on unauthenticated calls)
$gh_token = get_option( 'lkst_pro_github_token', '' );
if ( empty( $gh_token ) && defined( 'LKST_GITHUB_TOKEN' ) ) {
    $gh_token = LKST_GITHUB_TOKEN;
}
if ( ! empty( $gh_token ) ) {
    $lkst_updater->setAuthentication( $gh_token );
}
$lkst_updater->setBranch( 'main' );
$lkst_updater->getVcsApi()->enableReleaseAssets();

// Initialize the core plugin
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'leokoo-site-toolkit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    $plugin = new \LK\SiteToolkit\Core\Plugin();
    $plugin->init();
} );

register_activation_hook( __FILE__, function() {
    \LK\SiteToolkit\Core\Plugin::activate();
} );

register_deactivation_hook( __FILE__, function() {
    \LK\SiteToolkit\Core\Plugin::deactivate();
} );
// Add Settings link on the plugin page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
    $settings_link = '<a href="admin.php?page=lkst-dashboard">' . __( 'Settings', 'leokoo-site-toolkit' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );