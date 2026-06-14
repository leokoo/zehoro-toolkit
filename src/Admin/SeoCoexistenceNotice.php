<?php
namespace Zehoro\Admin;

use Zehoro\Compat\SeoPlugin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO-plugin coexistence notice — makes the (otherwise silent) stand-down
 * visible to the operator/agency.
 *
 * When a dedicated SEO plugin is active, Zehoro pauses its own structured-data
 * output to avoid duplicate schema (see Compat\SeoPlugin). Previously this was
 * silent at the site level; this surfaces it on Zehoro screens, names the
 * detected plugin, and offers the manual override ("use Zehoro's schema
 * instead") + a persistent dismiss.
 *
 * @package Zehoro\Admin
 */
final class SeoCoexistenceNotice {

	private const DISMISS_META = 'zehoro_seo_coexist_dismissed';

	public function init(): void {
		add_action( 'admin_notices', [ $this, 'maybe_render' ] );
		add_action( 'admin_post_zehoro_schema_pref', [ $this, 'handle' ] );
	}

	public function maybe_render(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// Only on Zehoro admin screens (informational, not a global nag).
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'zehoro' ) ) return;

		// Only when actually coexisting: an SEO plugin is active AND we're still
		// on auto (the user hasn't already chosen) AND it isn't dismissed.
		if ( ! SeoPlugin::active() ) return;
		if ( 'auto' !== (string) get_option( SeoPlugin::OPTION, 'auto' ) ) return;
		if ( get_user_meta( get_current_user_id(), self::DISMISS_META, true ) ) return;

		$label   = SeoPlugin::label();
		$keep    = wp_nonce_url( admin_url( 'admin-post.php?action=zehoro_schema_pref&pref=always' ), 'zehoro_schema_pref' );
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=zehoro_schema_pref&pref=dismiss' ), 'zehoro_schema_pref' );

		echo '<div class="notice notice-info"><p>';
		printf(
			/* translators: %s: the detected SEO plugin name */
			esc_html__( 'Zehoro detected %s and is coexisting with it — Zehoro\'s structured-data (schema) output is paused to avoid duplicate markup. This is the recommended setup; Zehoro\'s loop, entity, ROI and reporting features are unaffected.', 'zehoro-toolkit' ),
			'<strong>' . esc_html( $label ) . '</strong>'
		);
		echo '</p><p>';
		echo '<a href="' . esc_url( $keep ) . '" class="button button-secondary">' . esc_html__( 'Use Zehoro’s schema instead', 'zehoro-toolkit' ) . '</a> ';
		echo '<a href="' . esc_url( $dismiss ) . '" style="margin-left:8px;text-decoration:none;">' . esc_html__( 'Got it, dismiss', 'zehoro-toolkit' ) . '</a>';
		echo '</p></div>';
	}

	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'zehoro-toolkit' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'zehoro_schema_pref' );

		$pref = isset( $_GET['pref'] ) ? sanitize_key( wp_unslash( $_GET['pref'] ) ) : '';
		if ( 'always' === $pref ) {
			update_option( SeoPlugin::OPTION, 'always' ); // emit Zehoro's schema even with an SEO plugin present
		} else {
			update_user_meta( get_current_user_id(), self::DISMISS_META, 1 );
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}
}
