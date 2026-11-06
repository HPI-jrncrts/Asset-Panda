/*
 * sticky-list — keeps the group index beside a long list in step with the page.
 *
 * The index (desktop only) links to each group. The group crossing a band just
 * above the middle of the viewport is the current one: its link gets
 * aria-current, which CSS turns into its accent rule. A click scrolls to the
 * group (smoothly, unless the visitor prefers reduced motion) and moves focus
 * there so keyboard users continue from it.
 *
 * One IntersectionObserver per list; nothing is measured in a scroll handler.
 * Idempotent: a list already wired is skipped.
 */
( function () {
	'use strict';

	var calm = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

	function init( root ) {
		if ( root.hasAttribute( 'data-ap-sl-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-ap-sl-ready', '' );

		var links = root.querySelectorAll( '.ap-sticky-list__index a[href^="#"]' );
		if ( ! links.length ) {
			return;
		}
		var byId = {};
		var cards = [];
		for ( var i = 0; i < links.length; i++ ) {
			var id = links[ i ].getAttribute( 'href' ).slice( 1 );
			var card = document.getElementById( id );
			if ( card ) {
				byId[ id ] = links[ i ];
				cards.push( card );
			}
		}

		function setActive( id ) {
			for ( var k in byId ) {
				if ( k === id ) {
					byId[ k ].setAttribute( 'aria-current', 'true' );
				} else {
					byId[ k ].removeAttribute( 'aria-current' );
				}
			}
		}

		root.addEventListener( 'click', function ( e ) {
			var a = e.target.closest && e.target.closest( '.ap-sticky-list__index a[href^="#"]' );
			if ( ! a ) {
				return;
			}
			var target = document.getElementById( a.getAttribute( 'href' ).slice( 1 ) );
			if ( ! target ) {
				return;
			}
			e.preventDefault();
			target.scrollIntoView( { behavior: calm ? 'auto' : 'smooth', block: 'start' } );
			if ( ! target.hasAttribute( 'tabindex' ) ) {
				target.setAttribute( 'tabindex', '-1' );
			}
			target.focus( { preventScroll: true } );
			setActive( target.id );
		} );

		if ( ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		var io = new IntersectionObserver(
			function ( entries ) {
				for ( var j = 0; j < entries.length; j++ ) {
					if ( entries[ j ].isIntersecting ) {
						setActive( entries[ j ].target.id );
					}
				}
			},
			{ rootMargin: '-38% 0px -58% 0px' }
		);
		cards.forEach( function ( c ) {
			io.observe( c );
		} );
	}

	function boot() {
		var lists = document.querySelectorAll( '.ap-sticky-list--index' );
		for ( var i = 0; i < lists.length; i++ ) {
			init( lists[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
