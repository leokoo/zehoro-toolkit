/**
 * Modules page — client-side filter UX.
 *
 * Adapted from the WPExtended pattern in
 * zorasi-roadmaps/specs/phase-0-module-filtering.md. Vanilla JS, no jQuery.
 *
 * State precedence on initial load:
 *   URL params  >  localStorage  >  defaults
 *
 * After interaction, both localStorage and the URL update in sync so deep
 * links are shareable ("here are my inactive Pro SEO modules").
 *
 * @param {Object} data Localized config from PHP: { storageKey, defaults }
 */
( function ( data ) {
	'use strict';

	var STORAGE_KEY = data.storageKey || 'zehoroModuleSettings';
	var DEFAULTS    = data.defaults || { search: '', status: 'all', layout: 'grid' };
	var DEBOUNCE_MS = 300;
	var STAGGER_MS  = 30; // 30ms per card — feels tactile without being slow

	var els = {};
	var state = loadInitialState();

	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		els.root          = document.querySelector( '#zehoro-modules-grid' );
		els.search        = document.querySelector( '#zehoro-modules-search' );
		els.searchClear   = document.querySelector( '.zehoro-module-filters__search-clear' );
		els.pills         = document.querySelectorAll( '.zehoro-status-pill' );
		els.layoutBtns    = document.querySelectorAll( '.zehoro-module-filters__layout-button' );
		els.empty         = document.querySelector( '#zehoro-modules-empty' );
		els.totalCounter  = document.querySelector( '#zehoro-modules-result-visible' );
		els.liveRegion    = document.querySelector( '.zehoro-live-region' );

		if ( ! els.root || ! els.search ) return; // not on the modules page

		// Restore from state.
		els.search.value = state.search;
		setActivePill( state.status );
		setActiveLayout( state.layout );
		updateSearchClearVisibility();

		bindEvents();
		applyFilter( /* skipAnimation = */ true );
	}

	function bindEvents() {
		// Debounced search.
		var searchTimer = null;
		els.search.addEventListener( 'input', function () {
			updateSearchClearVisibility();
			clearTimeout( searchTimer );
			searchTimer = setTimeout( function () {
				state.search = ( els.search.value || '' ).trim().toLowerCase();
				persistState();
				applyFilter();
			}, DEBOUNCE_MS );
		} );

		if ( els.searchClear ) {
			els.searchClear.addEventListener( 'click', function () {
				els.search.value = '';
				state.search = '';
				updateSearchClearVisibility();
				persistState();
				applyFilter();
				els.search.focus();
			} );
		}

		els.pills.forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {
				state.status = pill.dataset.status || 'all';
				setActivePill( state.status );
				persistState();
				applyFilter();
			} );
		} );

		els.layoutBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				state.layout = btn.dataset.layout || 'grid';
				setActiveLayout( state.layout );
				persistState( /* skipUrlForLayout = */ true );
				applyLayout();
			} );
		} );
	}

	function applyFilter( skipAnimation ) {
		var q       = state.search;
		var tokens  = q ? q.split( /\s+/ ).filter( Boolean ) : [];
		var status  = state.status;
		var cards   = els.root.querySelectorAll( '.lkst-module-card' );
		var visible = 0;

		cards.forEach( function ( card, idx ) {
			var hay      = ( card.dataset.moduleHaystack || '' ).toLowerCase();
			var isActive = card.dataset.moduleActive === '1';
			var tier     = card.dataset.moduleTier || 'free';

			var matchesSearch = tokens.length === 0
				|| tokens.every( function ( t ) { return hay.indexOf( t ) !== -1; } );
			var matchesStatus = status === 'all'
				|| ( status === 'active'   && isActive )
				|| ( status === 'inactive' && ! isActive )
				|| ( status === 'free'     && tier === 'free' )
				|| ( status === 'pro'      && tier === 'pro' );

			var show = matchesSearch && matchesStatus;

			if ( show ) {
				visible++;
				card.style.display = '';
				card.classList.remove( 'lkst-module-card--fade-out' );
				if ( ! skipAnimation ) {
					card.style.setProperty( '--stagger-delay', ( visible * STAGGER_MS ) + 'ms' );
					card.classList.remove( 'lkst-module-card--fade-in' );
					// Force reflow so the animation re-triggers cleanly.
					void card.offsetWidth;
					card.classList.add( 'lkst-module-card--fade-in' );
				}
			} else {
				card.style.display = 'none';
				card.classList.remove( 'lkst-module-card--fade-in' );
			}
		} );

		// Hide category sections that have no visible cards after filtering.
		els.root.querySelectorAll( '.zehoro-module-category' ).forEach( function ( section ) {
			var anyVisible = Array.prototype.some.call(
				section.querySelectorAll( '.lkst-module-card' ),
				function ( c ) { return c.style.display !== 'none'; }
			);
			section.style.display = anyVisible ? '' : 'none';
		} );

		if ( els.totalCounter ) els.totalCounter.textContent = String( visible );
		if ( els.empty ) els.empty.style.display = visible === 0 ? 'block' : 'none';
		if ( els.root  ) els.root.style.display  = visible === 0 ? 'none'  : '';

		announce( visible );
	}

	function applyLayout() {
		if ( ! els.root ) return;
		els.root.classList.remove( 'zehoro-modules--grid', 'zehoro-modules--list' );
		els.root.classList.add( state.layout === 'list' ? 'zehoro-modules--list' : 'zehoro-modules--grid' );
	}

	function setActivePill( status ) {
		els.pills.forEach( function ( pill ) {
			pill.setAttribute( 'aria-pressed', pill.dataset.status === status ? 'true' : 'false' );
		} );
	}

	function setActiveLayout( layout ) {
		els.layoutBtns.forEach( function ( btn ) {
			btn.setAttribute( 'aria-pressed', btn.dataset.layout === layout ? 'true' : 'false' );
		} );
		applyLayout();
	}

	function updateSearchClearVisibility() {
		if ( ! els.searchClear ) return;
		els.searchClear.style.display = els.search.value ? 'inline-flex' : 'none';
	}

	function announce( visible ) {
		if ( ! els.liveRegion ) return;
		// String built server-side — JS just injects the count.
		els.liveRegion.textContent = String( visible ) + ' modules shown';
	}

	function loadInitialState() {
		var stored = {};
		try { stored = JSON.parse( localStorage.getItem( STORAGE_KEY ) ) || {}; } catch ( e ) { stored = {}; }

		var url    = new URLSearchParams( window.location.search );

		return {
			search: url.get( 'search' ) || stored.search || DEFAULTS.search,
			status: url.get( 'status' ) || stored.status || DEFAULTS.status,
			layout: stored.layout       || DEFAULTS.layout,
		};
	}

	function persistState( skipUrlForLayout ) {
		try {
			localStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
		} catch ( e ) { /* private window etc. — silently ignore */ }

		// Update URL (without reload) so deep links are shareable.
		// Layout is a personal preference — kept in localStorage only.
		var url = new URL( window.location.href );
		if ( state.search )                     url.searchParams.set( 'search', state.search ); else url.searchParams.delete( 'search' );
		if ( state.status && state.status !== 'all' ) url.searchParams.set( 'status', state.status ); else url.searchParams.delete( 'status' );
		window.history.replaceState( {}, '', url.toString() );
	}
} )( window.zehoroModulesAdmin || {} );
