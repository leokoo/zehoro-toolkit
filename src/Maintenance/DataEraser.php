<?php
namespace Zehoro\Maintenance;

use Zehoro\Migration\ZehoroRenameMigrator;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Erases ALL Zehoro Toolkit (Free) data — options, post meta, user meta, and
 * transients — for both the canonical `zehoro_*` keys and the legacy `lkst_*`
 * ones. Option names are driven off the rename migrator's authoritative map so
 * they never drift from what the plugin writes.
 *
 * Shared by two callers so they always wipe the exact same set:
 *   - `uninstall.php` — but ONLY when the operator opted into delete-on-uninstall
 *     (default OFF, so a plain/temporary uninstall preserves everything), and
 *   - the Settings → Danger Zone "Erase all data now" button.
 *
 * @package Zehoro\Maintenance
 */
final class DataEraser {

	/** Operator opt-in: wipe all data when the plugin is deleted. Default OFF. */
	public const DELETE_ON_UNINSTALL_OPTION = 'zehoro_delete_data_on_uninstall';

	/** Free-owned keys that live outside the migrator map. */
	private const EXTRA_OPTIONS = [
		'zehoro_rename_migration_v1',   // the migrator flag (else a reinstall skips migration)
		'lkst_content_cta_settings',    // pre-map legacy key
		'zehoro_content_cta_settings',
		self::DELETE_ON_UNINSTALL_OPTION,
	];

	/** Author-box + dismissed-notice user meta (both prefixes; all users). */
	private const USER_META = [
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

	/** Whether the operator opted into wiping data on plugin delete. */
	public static function delete_on_uninstall(): bool {
		return (bool) get_option( self::DELETE_ON_UNINSTALL_OPTION, false );
	}

	/** Wipe everything Free owns. Idempotent. */
	public static function erase(): void {
		global $wpdb;

		// 1. Options — migrator map (canonical + legacy) plus the extras.
		$options = self::EXTRA_OPTIONS;
		if ( class_exists( ZehoroRenameMigrator::class ) ) {
			foreach ( ZehoroRenameMigrator::OPTION_MAP as $old => $new ) {
				$options[] = $old;
				$options[] = $new;
			}
		}
		foreach ( array_unique( $options ) as $opt ) {
			delete_option( $opt );
		}

		// 2. Post meta (CTA suppression — both prefixes).
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('lkst_no_cta','zehoro_no_cta')" );

		// 3. User meta (all users — object_id 0 + delete_all).
		foreach ( self::USER_META as $key ) {
			delete_metadata( 'user', 0, $key, '', true );
		}

		// 4. Transients (caches). The zehoro_ sweep may also clear Pro caches if Pro
		//    is installed — harmless, caches regenerate; no Pro *option* is touched.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_lkst_%'
			    OR option_name LIKE '_transient_timeout_lkst_%'
			    OR option_name LIKE '_transient_zehoro_%'
			    OR option_name LIKE '_transient_timeout_zehoro_%'"
		);

		// The raw post-meta + transient deletes above bypass the object cache, so
		// flush it once — otherwise a persistent cache (Redis/Memcached) keeps
		// serving the now-deleted rows. Fine here: this is a one-time wipe, not a
		// hot path.
		wp_cache_flush();
	}
}
