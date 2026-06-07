<?php
namespace Zehoro\Migration;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Migrates legacy `lkst_*` option + post-meta keys to canonical `zehoro_*`
 * names on existing installs.
 *
 * Triggered twice (idempotent — wins fire once via the flag):
 *   - register_activation_hook (catches re-activation on existing sites)
 *   - plugins_loaded @ priority 1 (catches plugin-updates that bypass activation)
 *
 * Strategy per spec `specs/db-migration-zehoro-rename.md`:
 *   1. Detect each legacy key (option or post-meta).
 *   2. Copy value to the new canonical key — unless the new key is already populated
 *      (idempotent, safe to re-run; an earlier migration already handled it).
 *   3. Leave the legacy key in place for one release window as rollback safety.
 *      v1.8.0's cleanup migrator drops them.
 *
 * Free-surface only. Pro-side `lkst_pro_*` keys (if any survive after the
 * earlier rename work) get their own ZehoroProRenameMigrator in Phase 3.
 *
 * @package Zehoro\Migration
 */
class ZehoroRenameMigrator {

	/** Per-site flag that this migrator has already run successfully. */
	public const MIGRATION_FLAG = 'zehoro_rename_migration_v1';

	/** Sentinel returned by get_option() when a key is unset (distinguishes from a stored empty string / null / false). */
	private const UNSET_SENTINEL = '__zehoro_unset__';

	/**
	 * Legacy option-key → canonical key map.
	 *
	 * Augmented in Phase 1b after a full audit of every register_setting() /
	 * get_option() call site in Free. The list here is the Phase 1a starter set
	 * derived from the 2026-06-07 inventory of `~/Code/Zorasi/zehoro-toolkit`.
	 *
	 * @var array<string,string>
	 */
	public const OPTION_MAP = [
		// Module activation registry (shared with Pro — Pro flips its writes in Phase 1b)
		'lkst_active_modules'         => 'zehoro_active_modules',

		// Styles (5 colors)
		'lkst_color_primary'          => 'zehoro_color_primary',
		'lkst_color_primary_contrast' => 'zehoro_color_primary_contrast',
		'lkst_color_secondary'        => 'zehoro_color_secondary',
		'lkst_color_bg_dark'          => 'zehoro_color_bg_dark',
		'lkst_color_bg_light'         => 'zehoro_color_bg_light',

		// AuthorBox CTAs
		'lkst_cta_primary_label'      => 'zehoro_cta_primary_label',
		'lkst_cta_primary_url'        => 'zehoro_cta_primary_url',
		'lkst_cta_secondary_label'    => 'zehoro_cta_secondary_label',
		'lkst_cta_secondary_url'      => 'zehoro_cta_secondary_url',

		// AuthorBox profile fields
		'lkst_author_linkedin'        => 'zehoro_author_linkedin',
		'lkst_author_twitter'         => 'zehoro_author_twitter',
		'lkst_author_tagline'         => 'zehoro_author_tagline',
		'lkst_social_facebook'        => 'zehoro_social_facebook',
		'lkst_social_linkedin'        => 'zehoro_social_linkedin',
		'lkst_social_x'               => 'zehoro_social_x',
		'lkst_social_youtube'         => 'zehoro_social_youtube',

		// RSS module
		'lkst_rss_post_types'         => 'zehoro_rss_post_types',

		// FAQ module
		'lkst_faq_schema_mode'        => 'zehoro_faq_schema_mode',

		// LastUpdated module
		'lkst_lu_auto_inject'         => 'zehoro_lu_auto_inject',
		'lkst_lu_schema'              => 'zehoro_lu_schema',
		'lkst_lu_threshold_days'      => 'zehoro_lu_threshold_days',

		// Disclaimer module
		'lkst_disclaimer_preset'      => 'zehoro_disclaimer_preset',
		'lkst_disclaimer_custom_text' => 'zehoro_disclaimer_custom_text',
		'lkst_disclaimer_post_types'  => 'zehoro_disclaimer_post_types',
	];

	/**
	 * Legacy post-meta key → canonical key map.
	 *
	 * Phase 1a ships this empty — Phase 1b's audit will surface the actual
	 * meta keys (likely AuthorBox + FreshnessLog + LastUpdated stored per-post).
	 *
	 * @var array<string,string>
	 */
	public const POST_META_MAP = [];

	// ── public surface ───────────────────────────────────────────────────────

	/**
	 * Run all migrations. Idempotent — second run is a no-op via MIGRATION_FLAG.
	 */
	public static function run(): void {
		if ( get_option( self::MIGRATION_FLAG ) === '1' ) {
			return;
		}

		$migrated_options = 0;
		foreach ( self::OPTION_MAP as $old => $new ) {
			if ( self::migrate_option( $old, $new ) ) {
				$migrated_options++;
			}
		}

		$migrated_meta = 0;
		foreach ( self::POST_META_MAP as $old => $new ) {
			$migrated_meta += self::migrate_post_meta( $old, $new );
		}

		update_option( self::MIGRATION_FLAG, '1', false );

		if ( ( $migrated_options + $migrated_meta ) > 0 && function_exists( 'error_log' ) ) {
			error_log( sprintf(
				'[zehoro/rename] migrated %d option(s) + %d post-meta row(s) from lkst_* to zehoro_*.',
				$migrated_options,
				$migrated_meta
			) );
		}
	}

	/**
	 * Copy a single option value from $old to $new.
	 *
	 * Returns true when a copy happened, false on any no-op:
	 *   - the new key already had a stored value (earlier migration ran), or
	 *   - the legacy key isn't set on this site (fresh install).
	 *
	 * Never deletes $old — the cleanup migrator (v1.8.0) handles removal.
	 */
	public static function migrate_option( string $old, string $new ): bool {
		$existing_new = get_option( $new, self::UNSET_SENTINEL );
		if ( $existing_new !== self::UNSET_SENTINEL ) {
			return false; // new key already has a stored value — keep it
		}

		$legacy = get_option( $old, self::UNSET_SENTINEL );
		if ( $legacy === self::UNSET_SENTINEL ) {
			return false; // no legacy value to migrate
		}

		// autoload=false keeps the new option out of every page load until
		// something explicitly reads it. WP loads autoloaded options into a
		// single SELECT on every request — irrelevant for tiny values but a
		// real cost as we add ~25 of them.
		update_option( $new, $legacy, false );
		return true;
	}

	/**
	 * Rename a post-meta key in-place via a single UPDATE.
	 *
	 * Safe against the case where some posts already have the new key — we
	 * skip those rows via a self-join check (don't clobber an existing new-key
	 * value on the same post).
	 *
	 * @return int Number of rows updated.
	 */
	public static function migrate_post_meta( string $old, string $new ): int {
		global $wpdb;
		if ( ! $wpdb instanceof \wpdb ) return 0;

		/** @phpstan-ignore-next-line PreparedSQL: rename only, no caller-controlled keys */
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} AS pm
			 LEFT JOIN {$wpdb->postmeta} AS pn
			        ON pn.post_id = pm.post_id
			       AND pn.meta_key = %s
			 SET pm.meta_key = %s
			 WHERE pm.meta_key = %s
			   AND pn.meta_id IS NULL",
			$new, $new, $old
		) );

		return is_numeric( $updated ) ? (int) $updated : 0;
	}

	// ── test surface ─────────────────────────────────────────────────────────

	/**
	 * Force the migrator to be runnable again. Test-only — never call from
	 * production code.
	 */
	public static function reset_flag(): void {
		delete_option( self::MIGRATION_FLAG );
	}
}
