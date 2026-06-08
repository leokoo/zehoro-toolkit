<?php
namespace Zehoro\Utils;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Read an option by its canonical zehoro_* name, automatically falling back
 * to the legacy lkst_* name during the rename transition window.
 *
 * Belt-and-suspenders on top of `ZehoroRenameMigrator::run()`. The migrator
 * runs idempotently on `plugins_loaded@1` and copies every legacy key into
 * its canonical twin, so in practice `get_option( 'zehoro_*' )` is enough.
 * This helper exists for the cases where the migrator hasn't run yet
 * (extremely early bootstrap), failed silently, or was disabled by a host.
 *
 * Sunsets in v1.8.0 alongside the cleanup migrator that drops the legacy
 * keys. Until then, every read path on Free uses this; every write path
 * just calls `update_option( 'zehoro_*' )` directly (no legacy mirroring —
 * we want writes to land cleanly on the canonical key).
 *
 * @package Zehoro\Utils
 */
class Option {

	private const UNSET_SENTINEL = '__zehoro_unset__';

	/**
	 * Get an option's value, trying the canonical key first and falling back
	 * to its legacy lkst_* twin if the canonical key is unset.
	 *
	 * @param string $new_key Canonical zehoro_* name.
	 * @param mixed  $default Returned when neither key has a stored value.
	 * @return mixed
	 */
	public static function get( string $new_key, $default = false ) {
		$value = get_option( $new_key, self::UNSET_SENTINEL );
		if ( $value !== self::UNSET_SENTINEL ) {
			return $value;
		}

		$legacy = self::legacy_name_for( $new_key );
		if ( $legacy === null ) {
			return $default;
		}

		$value = get_option( $legacy, self::UNSET_SENTINEL );
		return $value === self::UNSET_SENTINEL ? $default : $value;
	}

	/**
	 * Reverse-lookup the legacy lkst_* name for a canonical zehoro_* key by
	 * scanning the migrator's OPTION_MAP. Returns null when the key wasn't
	 * part of the rename map (i.e. the option is brand-new to zehoro_*,
	 * never had an lkst_* form).
	 */
	private static function legacy_name_for( string $new_key ): ?string {
		if ( ! class_exists( '\\Zehoro\\Migration\\ZehoroRenameMigrator' ) ) {
			return null;
		}
		$legacy = array_search( $new_key, \Zehoro\Migration\ZehoroRenameMigrator::OPTION_MAP, true );
		return $legacy === false ? null : (string) $legacy;
	}
}
