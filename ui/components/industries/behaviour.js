/**
 * Industries: the sector tabs scroll to their card instead of jumping (QA 2026-10-29, Joseph: "when clicking the
 * filter, it should not immediately jump to the card").
 *
 * Delegated and idempotent: one click listener per section, keyed off .ap-sector-tab[href^="#"]. The target's
 * top lands just under the sticky site header; the address bar still gets the card's #anchor. Reduced-motion
 * users keep the instant jump.
 */
( function () {
	'use strict';

	var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' );

	function headerOffset() {
		var header = document.querySelector( '.ap-site-header' );
		var h = header ? header.getBoundingClientRect().height : 0;
		return ( h > 0 && h < 200 ? h : 72 ) + 16;
	}

	function init( root ) {
		if ( root.hasAttribute( 'data-ap-industries-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-ap-industries-ready', '' );

		root.addEventListener( 'click', function ( event ) {
			var tab = event.target.closest ? event.target.closest( '.ap-sector-tab[href^="#"]' ) : null;
			if ( ! tab || ! root.contains( tab ) ) {
				return;
			}
			var id = tab.getAttribute( 'href' ).slice( 1 );
			var card = id ? document.getElementById( id ) : null;
			if ( ! card ) {
				return;
			}
			event.preventDefault();
			var top = card.getBoundingClientRect().top + window.pageYOffset - headerOffset();
			try {
				window.scrollTo( { top: top, behavior: reduced && reduced.matches ? 'auto' : 'smooth' } );
			} catch ( e ) {
				window.scrollTo( 0, top );
			}
			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', '#' + id );
			}
		} );
	}

	function boot() {
		var roots = document.querySelectorAll( '.ap-industries' );
		for ( var i = 0; i < roots.length; i++ ) {
			init( roots[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
