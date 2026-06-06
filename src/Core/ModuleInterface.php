<?php
namespace Zehoro\Core;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Every module MUST implement this interface.
 * A missing init() is a fatal TypeError, not a silent no-op.
 *
 * @package Zehoro\Core
 */
interface ModuleInterface {

	/**
	 * Register all hooks / shortcodes for this module.
	 * Called once during Plugin::init().
	 */
	public function init(): void;
}
