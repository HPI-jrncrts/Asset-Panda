/*
 * platform-stack — the index that tracks the card being read.
 *
 * Progressive enhancement over live's markup: the index is a list of in-page
 * anchors and works without this file. This reproduces live's two scripts with
 * IntersectionObservers instead of a scroll handler:
 *
 * - Index (all widths): the active item is the first card in the band 38–68%
 *   down the viewport (live samples points at 38, 48, 58 and 68% and takes the
 *   first card it hits).
 * - Card fill (phones only): the newest card in the band 30.8–56% down the
 *   viewport (live: top past 56%, bottom not yet above 30.8%) gets
 *   .is-mobile-scroll-active, which live's ported rules fill with its accent.
 * - Pinned index (1280px and up): the host pages force overflow-x: hidden on
 *   html and body (live's footer does the same), which defeats position: sticky,
 *   so — like live — the index toggles .ap-js-fixed / .ap-js-bottom. Two sentinels
 *   in the rail cross a line at the sticky offset; sizes are read on load and
 *   resize only, never while scrolling.
 */
( function () {
	'use strict';

	var mq = window.matchMedia ? window.matchMedia.bind( window ) : null;
	var desktop = mq ? mq( '(min-width: 1280px)' ) : null;
	// Phones AND tablets (QA 2026-10-29, Joseph): the scrolled-to card fills and shows its preview below 1280px.
	var phone = mq ? mq( '(max-width: 1279px)' ) : null;
	var FILL = 'is-mobile-scroll-active';

	// Indexes of the targets currently inside an observer's band.
	function bandWatcher( targets, margin, onChange ) {
		var inside = {};
		var io = new IntersectionObserver(
			function ( entries ) {
				for ( var i = 0; i < entries.length; i++ ) {
					var idx = Number( entries[ i ].target.getAttribute( 'data-ap-pstack-panel' ) );
					if ( entries[ i ].isIntersecting ) {
						inside[ idx ] = true;
					} else {
						delete inside[ idx ];
					}
				}
				onChange(
					Object.keys( inside ).map( Number ).sort( function ( a, b ) {
						return a - b;
					} )
				);
			},
			{ rootMargin: margin, threshold: 0 }
		);
		for ( var t = 0; t < targets.length; t++ ) {
			io.observe( targets[ t ] );
		}
		return io;
	}

	function init( root ) {
		if ( root.hasAttribute( 'data-ap-pstack-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-ap-pstack-ready', '' );

		var panels = root.querySelectorAll( '[data-ap-pstack-panel]' );
		var links = root.querySelectorAll( '[data-ap-pstack-link]' );
		var rail = root.querySelector( '.ap-platform-rail' );
		var nav = root.querySelector( '.ap-platform-nav' );

		if ( ! panels.length || typeof window.IntersectionObserver !== 'function' ) {
			return;
		}

		function setNav( index ) {
			for ( var i = 0; i < links.length; i++ ) {
				var on = links[ i ].getAttribute( 'data-ap-pstack-link' ) === String( index );
				links[ i ].classList.toggle( 'is-active', on );
				if ( on ) {
					links[ i ].setAttribute( 'aria-current', 'true' );
				} else {
					links[ i ].removeAttribute( 'aria-current' );
				}
			}
		}

		function setFill( index ) {
			for ( var i = 0; i < panels.length; i++ ) {
				panels[ i ].classList.toggle( FILL, index !== null && i === index );
			}
		}

		// Index: the first card in the 38–68% band; outside the cards, keep the last.
		bandWatcher( panels, '-38% 0px -32% 0px', function ( keys ) {
			if ( keys.length ) {
				setNav( keys[ 0 ] );
			}
		} );

		// Fill: phones only, the newest card in the 30.8–56% band; none outside it.
		bandWatcher( panels, '-30.8% 0px -44% 0px', function ( keys ) {
			setFill( phone && phone.matches && keys.length ? keys[ keys.length - 1 ] : null );
		} );

		setNav( 0 );

		if ( rail && nav && desktop ) {
			pin( root, rail, nav );
		}
	}

	function pin( root, rail, nav ) {
		var start = document.createElement( 'span' );
		var end = document.createElement( 'span' );
		start.className = 'ap-pstack-sentinel';
		end.className = 'ap-pstack-sentinel ap-pstack-sentinel--end';
		start.setAttribute( 'aria-hidden', 'true' );
		end.setAttribute( 'aria-hidden', 'true' );
		rail.insertBefore( start, rail.firstChild );
		rail.appendChild( end );

		var above = { start: false, end: false };
		var size = { top: 104, width: 0 };
		var io = null;

		function reset() {
			nav.classList.remove( 'ap-js-fixed', 'ap-js-bottom' );
			nav.style.removeProperty( 'left' );
			nav.style.removeProperty( 'width' );
			nav.style.removeProperty( 'top' );
		}

		function apply( left ) {
			if ( ! desktop.matches || ! above.start ) {
				reset();
				return;
			}
			if ( above.end ) {
				nav.classList.remove( 'ap-js-fixed' );
				nav.classList.add( 'ap-js-bottom' );
				nav.style.removeProperty( 'left' );
				nav.style.removeProperty( 'top' );
				nav.style.setProperty( 'width', size.width + 'px', 'important' );
				return;
			}
			nav.classList.remove( 'ap-js-bottom' );
			nav.classList.add( 'ap-js-fixed' );
			if ( typeof left === 'number' ) {
				nav.style.setProperty( 'left', left + 'px', 'important' );
			}
			nav.style.setProperty( 'width', size.width + 'px', 'important' );
			nav.style.setProperty( 'top', size.top + 'px', 'important' );
		}

		// Sizes and the observer's line depend on layout: rebuilt on load and resize.
		function build() {
			if ( io ) {
				io.disconnect();
				io = null;
			}
			reset();
			rail.style.removeProperty( 'min-height' );
			if ( ! desktop.matches ) {
				return;
			}
			size.top = parseInt( getComputedStyle( root ).getPropertyValue( '--ap-stack-sticky-top' ), 10 ) || 104;
			size.width = rail.offsetWidth;
			rail.style.minHeight = nav.offsetHeight + 'px';
			root.style.setProperty( '--ap-pstack-nav-h', nav.offsetHeight + 'px' );

			// The band runs from the sticky line to far below the viewport, so a
			// sentinel is above the line exactly when it is not intersecting — even
			// after a jump (End key, anchor) that skips straight past the line.
			io = new IntersectionObserver(
				function ( entries ) {
					var left = null;
					for ( var i = 0; i < entries.length; i++ ) {
						var e = entries[ i ];
						above[ e.target === start ? 'start' : 'end' ] = ! e.isIntersecting;
						left = e.boundingClientRect.left;
					}
					apply( left );
				},
				{ rootMargin: '-' + size.top + 'px 0px 100000px 0px', threshold: 0 }
			);
			io.observe( start );
			io.observe( end );
		}

		var queued = false;
		function rebuild() {
			if ( queued ) {
				return;
			}
			queued = true;
			window.requestAnimationFrame( function () {
				queued = false;
				build();
			} );
		}

		window.addEventListener( 'resize', rebuild );
		window.addEventListener( 'load', rebuild );
		build();
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
