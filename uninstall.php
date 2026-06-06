<?php
/**
 * Uninstall handler — Zehoro Toolkit.
 *
 * This file is executed automatically when the user clicks "Delete" in the WordPress admin.
 * It ensures the plugin leaves no orphaned options, post meta, or user meta behind.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Clean up all global options
$options = [
    'lkst_active_modules',
    'lkst_cta_primary_label',
    'lkst_cta_primary_url',
    'lkst_cta_secondary_label',
    'lkst_cta_secondary_url',
    'lkst_color_primary',
    'lkst_color_primary_contrast',
    'lkst_color_secondary',
    'lkst_color_bg_dark',
    'lkst_color_bg_light',
    'lkst_content_cta_settings',
    'lkst_rss_post_types'
];

foreach ( $options as $opt ) {
    delete_option( $opt );
}

global $wpdb;

// 2. Clean up per-post meta (CTA suppressions)
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'lkst_no_cta'" );

// 3. Clean up user profile meta (Author Box data)
$user_meta_keys = [
    'lkst_social_facebook',
    'lkst_social_linkedin',
    'lkst_social_x',
    'lkst_social_youtube',
    'lkst_author_tagline',
    'lkst_chip_1',
    'lkst_chip_2',
    'lkst_chip_3'
];

$placeholders = implode( ',', array_fill( 0, count( $user_meta_keys ), '%s' ) );
$wpdb->query( $wpdb->prepare( 
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ($placeholders)", 
    ...$user_meta_keys 
) );
// 4. Clean up plugin transients (CategoryPills cache, etc.)
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lkst_%' OR option_name LIKE '_transient_timeout_lkst_%'" );