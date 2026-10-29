/*
 * case-studies — slider controls and autoplay.
 *
 * Progressive enhancement. The track is a CSS scroll-snap list and works with
 * keyboard, touch and trackpad before this file runs. This reveals the
 * previous/next arrows and dots (rendered hidden), loops at either end, and
 * advances every data-ap-autoplay ms (3000 when the attribute is absent; 0
 * turns it off) — paused while a mouse is over the slider, while keyboard
 * focus is inside it, while a finger is on it (restarting the count after),
 * while it is off screen and while the tab is hidden, and never started when
 * the visitor prefers reduced motion.
 *
 * Delegated and idempotent: keys off data-ap-cases attributes, handles several
 * sliders on one page, and marks each so a second run is a no-op. The scroll
 * handler only reads scrollLeft.
 */
( function () {
	'use strict';

	var DEFAULT_MS = 3000;
	var calm = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	var watcher = null;

	function keyboardFocus( el ) {
		try {
			return el.matches( ':focus-visible' );
		} catch ( e ) {
			return false;
		}
	}

	function init( root ) {
		if ( root.hasAttribute( 'data-ap-cases-ready' ) ) {
			return;
		}

		var track = root.querySelector( '[data-ap-cases-track]' );
		var controls = root.querySelector( '[data-ap-cases-controls]' );
		if ( ! track || ! controls ) {
			return;
		}

		var slides = track.children.length;
		if ( slides < 2 ) {
			return;
		}

		var prev = controls.querySelector( '[data-ap-cases-prev]' );
		var next = controls.querySelector( '[data-ap-cases-next]' );
		var dots = controls.querySelectorAll( '[data-ap-cases-dot]' );

		root.setAttribute( 'data-ap-cases-ready', '' );
		controls.hidden = false;

		function current() {
			var w = track.clientWidth || 1;
			return Math.max( 0, Math.min( slides - 1, Math.round( track.scrollLeft / w ) ) );
		}

		function go( index ) {
			var n = ( ( index % slides ) + slides ) % slides;
			track.scrollTo( { left: n * track.clientWidth, behavior: calm ? 'auto' : 'smooth' } );
		}

		function sync() {
			var idx = current();
			for ( var i = 0; i < dots.length; i++ ) {
				var on = i === idx;
				dots[ i ].classList.toggle( 'is-active', on );
				if ( on ) {
					dots[ i ].setAttribute( 'aria-current', 'true' );
				} else {
					dots[ i ].removeAttribute( 'aria-current' );
				}
			}
		}

		var attr = root.getAttribute( 'data-ap-autoplay' );
		var ms = null === attr || '' === attr ? DEFAULT_MS : parseInt( attr, 10 );
		if ( ! ( ms >= 0 ) ) {
			ms = DEFAULT_MS;
		}
		if ( ms > 0 && ms < 1500 ) {
			ms = 1500;
		}
		var timer = null;
		var hovered = false;
		var focused = false;
		var touching = false;
		var visible = ! watcher;

		function stop() {
			if ( timer ) {
				clearTimeout( timer );
				timer = null;
			}
		}

		// One timeout per slide, so a manual change always restarts the count.
		function start() {
			stop();
			var on = ! calm && ms > 0 && visible && ! hovered && ! focused && ! touching && ! document.hidden;
			track.setAttribute( 'aria-live', on ? 'off' : 'polite' );
			if ( ! on ) {
				return;
			}
			timer = setTimeout( function () {
				timer = null;
				go( current() + 1 );
				start();
			}, ms );
		}

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				go( current() - 1 );
				start();
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				go( current() + 1 );
				start();
			} );
		}
		for ( var d = 0; d < dots.length; d++ ) {
			dots[ d ].addEventListener( 'click', function () {
				go( parseInt( this.getAttribute( 'data-ap-cases-dot' ), 10 ) || 0 );
				start();
			} );
		}

		root.addEventListener( 'pointerenter', function ( e ) {
			hovered = 'mouse' === e.pointerType;
			start();
		} );
		root.addEventListener( 'pointerleave', function () {
			hovered = false;
			start();
		} );
		root.addEventListener( 'focusin', function ( e ) {
			focused = keyboardFocus( e.target );
			start();
		} );
		root.addEventListener( 'focusout', function ( e ) {
			if ( ! root.contains( e.relatedTarget ) ) {
				focused = false;
				start();
			}
		} );
		track.addEventListener(
			'touchstart',
			function () {
				touching = true;
				stop();
			},
			{ passive: true }
		);
		var release = function () {
			touching = false;
			start();
		};
		track.addEventListener( 'touchend', release, { passive: true } );
		track.addEventListener( 'touchcancel', release, { passive: true } );
		document.addEventListener( 'visibilitychange', start );
		if ( watcher ) {
			root.__apCasesVisible = function ( v ) {
				visible = v;
				start();
			};
			watcher.observe( root );
		}

		var queued = false;
		track.addEventListener(
			'scroll',
			function () {
				if ( queued ) {
					return;
				}
				queued = true;
				window.requestAnimationFrame( function () {
					queued = false;
					sync();
				} );
			},
			{ passive: true }
		);

		sync();
		start();
	}

	function initAll() {
		if ( ! calm && typeof window.IntersectionObserver === 'function' ) {
			watcher = new IntersectionObserver( function ( entries ) {
				for ( var i = 0; i < entries.length; i++ ) {
					var fn = entries[ i ].target.__apCasesVisible;
					if ( fn ) {
						fn( entries[ i ].isIntersecting );
					}
				}
			}, { threshold: 0.25 } );
		}
		var nodes = document.querySelectorAll( '[data-ap-cases]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			init( nodes[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
