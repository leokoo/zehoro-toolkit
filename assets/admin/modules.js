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
	var DEFAULTS    = data.defaults || { search: '', status: 'all', layout: 'grid', group: 'all' };
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
		els.navLinks      = document.querySelectorAll( '.zehoro-module-nav__link' );
		els.empty         = document.querySelector( '#zehoro-modules-empty' );
		els.totalCounter  = document.querySelector( '#zehoro-modules-result-visible' );
		els.liveRegion    = document.querySelector( '.zehoro-live-region' );

		if ( ! els.root || ! els.search ) return; // not on the modules page

		// Restore from state.
		els.search.value = state.search;
		setActivePill( state.status );
		setActiveLayout( state.layout );
		setActiveGroup( state.group );
		updateSearchClearVisibility();

		bindEvents();
		bindToggleEvents();
		applyFilter( /* skipAnimation = */ true );
	}

	function bindToggleEvents() {
		if ( ! data.rest || ! data.rest.root ) return; // no REST config — fall back to Save button
		var switches = document.querySelectorAll( '.lkst-module-card .lkst-switch input[type="checkbox"]' );
		switches.forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				var card = input.closest( '.lkst-module-card' );
				if ( ! card ) return;
				var slug = card.dataset.moduleSlug || '';
				if ( ! slug ) return;

				input.disabled = true;
				var prevChecked = ! input.checked; // we just flipped — previous is the inverse

				var url = data.rest.root + data.rest.toggleRoute.replace( '{slug}', encodeURIComponent( slug ) );
				fetch( url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce':   data.rest.nonce,
						'Content-Type': 'application/json',
					},
				} )
				.then( function ( r ) { return r.json().then( function ( j ) { return { ok: r.ok, json: j }; } ); } )
				.then( function ( res ) {
					input.disabled = false;
					if ( ! res.ok || ! res.json || ! res.json.success ) {
						input.checked = prevChecked; // rollback UI
						alert( ( data.i18n && data.i18n.toggleFailed ) || 'Toggle failed.' );
						return;
					}
					// Update card state for the filter logic.
					var enabled = !! res.json.enabled;
					card.dataset.moduleActive = enabled ? '1' : '0';
					card.classList.toggle( 'active',   enabled );
					card.classList.toggle( 'inactive', ! enabled );
				} )
				.catch( function () {
					input.disabled = false;
					input.checked = prevChecked;
					alert( ( data.i18n && data.i18n.toggleFailed ) || 'Toggle failed.' );
				} );
			} );
		} );
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

		els.navLinks.forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				state.group = link.dataset.group || 'all';
				setActiveGroup( state.group );
				persistState();
				applyFilter();
			} );
		} );
	}

	function applyFilter( skipAnimation ) {
		var q       = state.search;
		var tokens  = q ? q.split( /\s+/ ).filter( Boolean ) : [];
		var status  = state.status;
		var group   = state.group;
		var cards   = els.root.querySelectorAll( '.lkst-module-card' );
		var visible = 0;

		cards.forEach( function ( card, idx ) {
			var hay      = ( card.dataset.moduleHaystack || '' ).toLowerCase();
			var isActive = card.dataset.moduleActive === '1';
			var tier     = card.dataset.moduleTier || 'free';
			var cardGroup = card.dataset.moduleGroup || 'other';

			var matchesSearch = tokens.length === 0
				|| tokens.every( function ( t ) { return hay.indexOf( t ) !== -1; } );
			var matchesStatus = status === 'all'
				|| ( status === 'active'   && isActive )
				|| ( status === 'inactive' && ! isActive )
				|| ( status === 'free'     && tier === 'free' )
				|| ( status === 'pro'      && tier === 'pro' );
			var matchesGroup = group === 'all' || cardGroup === group;

			var show = matchesSearch && matchesStatus && matchesGroup;

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

	function setActiveGroup( group ) {
		var matched = false;
		els.navLinks.forEach( function ( link ) {
			var isMatch = link.dataset.group === group;
			link.setAttribute( 'aria-current', isMatch ? 'true' : 'false' );
			link.classList.toggle( 'is-active', isMatch );
			if ( isMatch ) matched = true;
		} );
		// Saved group no longer exists (e.g. module that owned the only entry
		// in that group got deactivated) — fall back to "all" instead of
		// silently hiding everything.
		if ( ! matched && group !== 'all' ) {
			state.group = 'all';
			setActiveGroup( 'all' );
		}
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
			group:  url.get( 'group' )  || stored.group  || DEFAULTS.group,
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
		if ( state.group  && state.group  !== 'all' ) url.searchParams.set( 'group',  state.group  ); else url.searchParams.delete( 'group' );
		window.history.replaceState( {}, '', url.toString() );
	}
} )( window.zehoroModulesAdmin || {} );
