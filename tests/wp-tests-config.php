<?php
/**
 * wp-phpunit test config for Leokoo Site Toolkit.
 *
 * Copy of WordPress's standard wp-tests-config-sample.php with credentials
 * adjusted for the local MySQL set up by composer install + the install-wp-tests
 * script. Override DB credentials via env vars (DB_NAME, DB_USER, DB_PASSWORD,
 * DB_HOST) for CI runs.
 *
 * SAFE TO COMMIT: this points at a throwaway local test database. Real
 * production credentials never live here.
 */

// Path to the WordPress core checkout the test suite installed.
// install-wp-tests.sh puts it at /tmp/wordpress/ by default.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', getenv( 'WP_CORE_DIR' ) ?: '/tmp/wordpress/' );
}

// Test with WP_DEBUG on so notices fail the suite.
define( 'WP_DEBUG', true );

// ── Database ───────────────────────────────────────────────────────────────
// install-wp-tests.sh creates wordpress_test against root@localhost with the
// password you pass it. Match those args.
define( 'DB_NAME',     getenv( 'WP_DB_NAME' )     ?: 'wordpress_test' );
define( 'DB_USER',     getenv( 'WP_DB_USER' )     ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_DB_PASSWORD' ) ?: '' );
define( 'DB_HOST',     getenv( 'WP_DB_HOST' )     ?: 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// ── WP test settings ───────────────────────────────────────────────────────
$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN',     'example.org' );
define( 'WP_TESTS_EMAIL',      'admin@example.org' );
define( 'WP_TESTS_TITLE',      'Test Blog' );
define( 'WP_PHP_BINARY',       'php' );
define( 'WPLANG',              '' );

// Use the wp-phpunit core bundled with composer. install-wp-tests.sh will
// download a fresh copy to /tmp/wordpress/, which this points at.
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
    // Let WP figure it out from ABSPATH.
}
