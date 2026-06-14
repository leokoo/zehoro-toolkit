<?php
namespace Zehoro\Compat;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO-plugin coexistence — the canonical detector behind Zehoro's positioning.
 *
 * Zehoro is a content-business operating system, NOT an SEO plugin: it owns the
 * loop (decide → produce → be-cited → learn), the entity layer, conversion +
 * revenue, and the cockpit — and deliberately does NOT own the SEO-OUTPUT
 * plumbing (meta tags, sitemaps, canonicals, robots). Its ONE output overlap is
 * structured data (JSON-LD), so when a dedicated SEO plugin is active that
 * already emits schema, Zehoro coexists by default: it stands its own schema
 * down to avoid duplicate/competing structured data — overridable by the user
 * (option) or a developer (filter).
 *
 * This is the single source of truth for "is an SEO plugin present?" — replacing
 * the per-module, inconsistent checks ArticleSchema / FAQ used to each carry.
 * Extend the recognised set with the `zehoro/seo_plugins` filter.
 *
 * @package Zehoro\Compat
 */
final class SeoPlugin {

	public const OPTION = 'zehoro_schema_output'; // auto | always | never

	/**
	 * slug => [ label, check() ] for SEO plugins that emit structured data.
	 *
	 * @return array<string,array{label:string,check:callable}>
	 */
	private static function known(): array {
		$list = [
			'yoast'         => [ 'label' => 'Yoast SEO',         'check' => static fn() => defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ],
			'seopress'      => [ 'label' => 'SEOPress',          'check' => static fn() => defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' ) ],
			'rankmath'      => [ 'label' => 'Rank Math',         'check' => static fn() => defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ],
			'aioseo'        => [ 'label' => 'All in One SEO',    'check' => static fn() => defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ],
			'surerank'      => [ 'label' => 'SureRank',          'check' => static fn() => defined( 'SURERANK_VERSION' ) || class_exists( 'SureRank\\Core\\SureRank' ) || class_exists( 'SureRank\\SureRank' ) ],
			'slim_seo'      => [ 'label' => 'Slim SEO',          'check' => static fn() => defined( 'SLIM_SEO_VER' ) || defined( 'SLIM_SEO_DIR' ) ],
			'seo_framework' => [ 'label' => 'The SEO Framework', 'check' => static fn() => defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ) ],
			'squirrly'      => [ 'label' => 'Squirrly SEO',      'check' => static fn() => defined( 'SQ_VERSION' ) ],
			'schema_pro'    => [ 'label' => 'Schema Pro',        'check' => static fn() => defined( 'BSF_AIOSRS_PRO_VER' ) || defined( 'BSF_AIOSRS_PRO_FILE' ) ],
		];

		$filtered = apply_filters( 'zehoro/seo_plugins', $list );
		return is_array( $filtered ) && ! empty( $filtered ) ? $filtered : $list;
	}

	/**
	 * The first detected schema-emitting SEO plugin, or null.
	 *
	 * @return array{slug:string,label:string}|null
	 */
	public static function detect(): ?array {
		foreach ( self::known() as $slug => $def ) {
			$check = $def['check'] ?? null;
			if ( is_callable( $check ) && $check() ) {
				return [ 'slug' => (string) $slug, 'label' => (string) ( $def['label'] ?? $slug ) ];
			}
		}
		return null;
	}

	public static function active(): bool {
		return null !== self::detect();
	}

	public static function label(): string {
		$d = self::detect();
		return $d ? $d['label'] : '';
	}

	/**
	 * Should Zehoro emit its own structured data?
	 *
	 * Coexist-by-default: NO when a dedicated SEO plugin is active. The user can
	 * override (option `zehoro_schema_output` = always|never), and developers can
	 * override via the `zehoro/emit_schema` filter (or the legacy
	 * `zehoro_article_schema_force` filter, kept for back-compat).
	 */
	public static function should_emit_schema(): bool {
		$mode = (string) get_option( self::OPTION, 'auto' );

		if ( 'always' === $mode || (bool) apply_filters( 'zehoro_article_schema_force', false ) ) {
			$emit = true;
		} elseif ( 'never' === $mode ) {
			$emit = false;
		} else {
			$emit = ! self::active(); // auto
		}

		return (bool) apply_filters( 'zehoro/emit_schema', $emit );
	}
}
