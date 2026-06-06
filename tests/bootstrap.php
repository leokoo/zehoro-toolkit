<?php
/**
 * PHPUnit bootstrap for Leokoo Site Toolkit.
 *
 * Loads the WordPress test suite (via wp-phpunit) and then the plugin so
 * all `WP_UnitTestCase`-based integration tests see the plugin's modules
 * registered as if a real site activated them.
 *
 * @package Zehoro\Tests
 */

// Resolve the wp-phpunit install dir. Default to the composer-installed
// location; allow override via WP_TESTS_DIR env var (used by CI containers
// that download the WP test suite separately).
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests_dir ) {
    $wp_tests_dir = __DIR__ . '/../vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
    fwrite(
        STDERR,
        "WP test suite not found at {$wp_tests_dir}/includes/functions.php\n" .
        "Run composer install, or set WP_TESTS_DIR to a manual install.\n"
    );
    exit( 1 );
}

// wp-phpunit looks up wp-tests-config.php via the WP_PHPUNIT__TESTS_CONFIG env
// var. We set it to a config bundled with this repo (tests/wp-tests-config.php).
$config_path = __DIR__ . '/wp-tests-config.php';
if ( ! file_exists( $config_path ) ) {
    fwrite(
        STDERR,
        "Missing tests/wp-tests-config.php — copy the template and set DB credentials.\n"
    );
    exit( 1 );
}
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . $config_path );

// Required for PHPUnit polyfills (compat across PHPUnit 5-12 + WP test suite).
require_once __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Load WP test bootstrap. This pulls in the WP core + test factories.
require_once $wp_tests_dir . '/includes/functions.php';

// Ensure the plugin is loaded BEFORE WP fires `plugins_loaded` so its module
// registrations land in the normal hook order.
tests_add_filter( 'muplugins_loaded', function () {
    require_once dirname( __DIR__ ) . '/leokoo-site-toolkit.php';
} );

// Start up the WP testing environment.
require $wp_tests_dir . '/includes/bootstrap.php';
