<?php
namespace Zehoro\Modules;
use Zehoro\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CTA Swap Module
 *
 * Progressive-disclosure pattern: a group of CTA buttons can swap inline
 * with a hidden form (e.g. newsletter signup) when the visitor clicks a
 * specific trigger button. Reduces visual clutter while keeping conversion
 * friction low. Common pattern on Substack, Mailchimp, ConvertKit, etc.
 *
 * Pure data-attribute API; no shortcodes, no PHP rendering. Drop the script
 * in (this module enqueues it) and mark up any HTML with the right
 * attributes:
 *
 *   <div data-lkst-swap-group="newsletter-cta">
 *     <a href="/chapters/" class="btn">Visit a chapter</a>
 *     <button type="button" data-lkst-swap-target="#newsletter-form">
 *       Sign up for newsletter
 *     </button>
 *   </div>
 *
 *   <div id="newsletter-form" class="lkst-cta-form" hidden>
 *     <form>...</form>
 *     <button type="button" data-lkst-swap-back>Back</button>
 *   </div>
 *
 * On click of [data-lkst-swap-target], the group hides and the target form
 * appears with focus moved to the first focusable element. Click
 * [data-lkst-swap-back] (or press ESC) to reverse.
 *
 * Layout-agnostic — author styles the buttons and form however they want.
 * Module only ships the swap behaviour + minimal hidden-state CSS.
 *
 * @package Zehoro\Modules
 */
class CtaSwap implements \Zehoro\Core\ModuleInterface {

	public static function register(): void {
		Plugin::register_module( 'cta_swap', self::class, [
			'title'   => 'CTA Swap',
			'desc'    => 'Progressive disclosure — swap CTA buttons inline with a hidden form. Data-attribute API, no shortcode.',
			'default' => false,
		] );
	}

	public function init(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue the tiny JS + CSS pair. Only loaded when this module is active.
	 * Vanilla JS, zero deps. JS in footer so it doesn't block render.
	 */
	public function enqueue(): void {
		wp_enqueue_script(
			'zehoro-cta-swap',
			ZEHORO_URL . 'assets/cta-swap.js',
			[],
			ZEHORO_VERSION,
			true
		);
		wp_enqueue_style(
			'zehoro-cta-swap',
			ZEHORO_URL . 'assets/cta-swap.css',
			[],
			ZEHORO_VERSION
		);
	}
}
