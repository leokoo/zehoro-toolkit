<?php
/**
 * Uninstall handler — Zehoro Toolkit.
 *
 * Data is PRESERVED by default. WordPress runs this whenever the plugin is
 * deleted, but it can't tell a permanent removal from a temporary
 * uninstall-then-reinstall — so wiping unconditionally would lose a user's
 * settings the moment they remove the plugin to troubleshoot. Instead we only
 * erase when the operator has explicitly opted in (Settings → Danger Zone →
 * "delete all data when I delete the plugin"). The same eraser powers the
 * Danger Zone's "Erase all data now" button for an on-demand wipe.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once __DIR__ . '/src/Migration/ZehoroRenameMigrator.php';
require_once __DIR__ . '/src/Maintenance/DataEraser.php';

if ( \Zehoro\Maintenance\DataEraser::delete_on_uninstall() ) {
    \Zehoro\Maintenance\DataEraser::erase();
}
// Otherwise: leave every option, meta, and transient in place — a reinstall
// picks up exactly where the user left off.
