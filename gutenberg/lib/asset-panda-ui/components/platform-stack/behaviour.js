/*
 * platform-stack — "currently reading" state for the index and the cards.
 *
 * Progressive enhancement. The index is a list of in-page anchors and works
 * without this file; this adds the active index item (all widths) and marks
 * the card being read with .is-current, which fills it on phones (the CSS
 * only styles .is-current below 768px).
 *
 * One IntersectionObserver per section watches a band across the middle of the
 * viewport; no geometry is read in a scroll handler. From 1280px the cards are
 * sticky and stack, so several sit in the band at once — the newest (highest
 * index) wins there; elsewhere the first one does.
 */
( function () {
	'use strict';

	var desktop = window.matchMedia ? window.matchMedia( '(min-width: 1280px)' ) : null;

	function init( root ) {
		if ( root.hasAttribute( 'data-ap-pstack-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-ap-pstack-ready', '' );

		var panels = root.querySelectorAll( '[data-ap-pstack-panel]' );
		var links = root.querySelectorAll( '[data-ap-pstack-link]' );

		if ( ! panels.length || typeof window.IntersectionObserver !== 'function' ) {
			return;
		}

		function setActive( index, withCard ) {
			var i;
			for ( i = 0; i < links.length; i++ ) {
				var on = links[ i ].getAttribute( 'data-ap-pstack-link' ) === String( index );
				links[ i ].classList.toggle( 'is-active', on );
				if ( on ) {
					links[ i ].setAttribute( 'aria-current', 'true' );
				} else {
					links[ i ].removeAttribute( 'aria-current' );
				}
			}
			for ( i = 0; i < panels.length; i++ ) {
				panels[ i ].classList.toggle(
					'is-current',
					withCard && panels[ i ].getAttribute( 'data-ap-pstack-panel' ) === String( index )
				);
			}
		}

		function clearCards() {
			for ( var i = 0; i < panels.length; i++ ) {
				panels[ i ].classList.remove( 'is-current' );
			}
		}

		var visible = {};

		var observer = new IntersectionObserver(
			function ( entries ) {
				for ( var i = 0; i < entries.length; i++ ) {
					var idx = entries[ i ].target.getAttribute( 'data-ap-pstack-panel' );
					if ( entries[ i ].isIntersecting ) {
						visible[ idx ] = true;
					} else {
						delete visible[ idx ];
					}
				}

				var keys = Object.keys( visible ).map( Number ).sort( function ( a, b ) {
					return a - b;
				} );

				// Out of the band entirely: leave the index where it was, but no
				// card is being read.
				if ( ! keys.length ) {
					clearCards();
					return;
				}

				setActive( desktop && desktop.matches ? keys[ keys.length - 1 ] : keys[ 0 ], true );
			},
			{ rootMargin: '-40% 0px -50% 0px', threshold: 0 }
		);

		for ( var p = 0; p < panels.length; p++ ) {
			observer.observe( panels[ p ] );
		}

		setActive( 0, false );
	}

	function initAll( scope ) {
		var nodes = scope.querySelectorAll( '[data-ap-pstack]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			init( nodes[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll( document );
		} );
	} else {
		initAll( document );
	}
} )();
