<?php
/**
 * Uninstall handler — Zehoro Toolkit.
 *
 * Runs when the user deletes the plugin. Leaves no orphaned options, post meta,
 * user meta, or transients behind — for BOTH the canonical `zehoro_*` keys
 * (live since the v1.7.0 rename) and the legacy `lkst_*` keys kept around for
 * rollback. The option list is driven off the rename migrator's authoritative
 * key map so it can never drift from what the plugin actually writes.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Options ───────────────────────────────────────────────────────────────
$option_keys = [
    'zehoro_rename_migration_v1',   // the migrator flag — else a reinstall skips migration and resurrects stale state
    'lkst_content_cta_settings',    // pre-map legacy key (predates the migrator map)
    'zehoro_content_cta_settings',
];

// Pull every canonical + legacy option name from the migrator map (DRY — stays
// current as the map grows). require_once is safe in uninstall context (WP core,
// hence ABSPATH, is loaded) and the file is a side-effect-free class definition.
$migrator = __DIR__ . '/src/Migration/ZehoroRenameMigrator.php';
if ( file_exists( $migrator ) ) {
    require_once $migrator;
    if ( class_exists( '\\Zehoro\\Migration\\ZehoroRenameMigrator' ) ) {
        foreach ( \Zehoro\Migration\ZehoroRenameMigrator::OPTION_MAP as $old => $new ) {
            $option_keys[] = $old;
            $option_keys[] = $new;
        }
    }
}

foreach ( array_unique( $option_keys ) as $opt ) {
    delete_option( $opt );
}

// 2. Per-post meta (CTA suppression — both prefixes) ─────────────────────────
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('lkst_no_cta','zehoro_no_cta')" );

// 3. User profile meta (Author Box + dismissed notices) — both prefixes, all
//    users (object_id 0 + delete_all). delete_metadata() is a no-op for keys
//    that aren't user meta, so listing the canonical option-named keys here too
//    is harmless belt-and-braces.
$user_meta_keys = [
    'lkst_social_facebook',  'zehoro_social_facebook',
    'lkst_social_linkedin',  'zehoro_social_linkedin',
    'lkst_social_x',         'zehoro_social_x',
    'lkst_social_youtube',   'zehoro_social_youtube',
    'lkst_author_tagline',   'zehoro_author_tagline',
    'lkst_author_linkedin',  'zehoro_author_linkedin',
    'lkst_author_twitter',   'zehoro_author_twitter',
    'lkst_chip_1', 'lkst_chip_2', 'lkst_chip_3',
    'zehoro_chip_1', 'zehoro_chip_2', 'zehoro_chip_3',
    'zehoro_seo_coexist_dismissed',
];
foreach ( $user_meta_keys as $key ) {
    delete_metadata( 'user', 0, $key, '', true );
}

// 4. Transients (caches) — both prefixes. The zehoro_ sweep also clears any Pro
//    caches if Pro happens to be installed; that's harmless (caches regenerate,
//    and Pro can't run without Free anyway). Options are deleted by exact name
//    above, so no Pro *option* is ever touched.
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_lkst_%'
        OR option_name LIKE '_transient_timeout_lkst_%'
        OR option_name LIKE '_transient_zehoro_%'
        OR option_name LIKE '_transient_timeout_zehoro_%'"
);
