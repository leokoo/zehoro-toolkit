/**
 * LKST CTA Swap — progressive-disclosure swap between a CTA-button group
 * and a hidden form. Vanilla JS, zero dependencies, ~50 lines.
 *
 * Usage: see src/Modules/CtaSwap.php docblock.
 *
 * @package LK\SiteToolkit
 */
( function () {
	'use strict';

	// Track which group is currently swapped open so ESC + back-button can reverse.
	let openState = null; // { group, form, trigger }

	/**
	 * Locate the swap group an opening trigger belongs to.
	 */
	function getGroupFor( trigger ) {
		return trigger.closest( '[data-lkst-swap-group]' );
	}

	/**
	 * Open the swap: hide group, show form, move focus into form.
	 */
	function openSwap( trigger ) {
		const targetSelector = trigger.getAttribute( 'data-lkst-swap-target' );
		if ( ! targetSelector ) return;

		const form = document.querySelector( targetSelector );
		if ( ! form ) return;

		const group = getGroupFor( trigger );
		if ( ! group ) return;

		group.hidden = true;
		group.setAttribute( 'aria-hidden', 'true' );
		form.hidden = false;
		form.removeAttribute( 'aria-hidden' );

		openState = { group, form, trigger };

		// Focus the first focusable inside the form for keyboard accessibility.
		const firstFocusable = form.querySelector(
			'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]):not([data-lkst-swap-back]), a[href]'
		);
		if ( firstFocusable ) firstFocusable.focus();
	}

	/**
	 * Close the swap: hide form, show group, return focus to the original trigger.
	 */
	function closeSwap() {
		if ( ! openState ) return;

		openState.form.hidden = true;
		openState.form.setAttribute( 'aria-hidden', 'true' );
		openState.group.hidden = false;
		openState.group.removeAttribute( 'aria-hidden' );

		if ( openState.trigger ) openState.trigger.focus();
		openState = null;
	}

	// Event delegation so dynamically-added markup also works.
	document.addEventListener( 'click', function ( e ) {
		const opener = e.target.closest( '[data-lkst-swap-target]' );
		if ( opener ) {
			e.preventDefault();
			openSwap( opener );
			return;
		}

		const back = e.target.closest( '[data-lkst-swap-back]' );
		if ( back ) {
			e.preventDefault();
			closeSwap();
		}
	} );

	// ESC closes the open swap (standard expected behaviour for revealed UI).
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && openState ) {
			closeSwap();
		}
	} );
}() );
