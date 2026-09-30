/*
 * case-studies — slider controls and autoplay.
 *
 * Progressive enhancement. The track is a CSS scroll-snap list and works with
 * keyboard, touch and trackpad before this file runs. This reveals the
 * previous/next arrows and dots (rendered hidden), loops at either end, and
 * advances every 5 seconds like the live homepage — paused while the pointer
 * is over the slider or it has focus, stopped for the visit after a touch,
 * and never started when the visitor prefers reduced motion.
 *
 * Delegated and idempotent: keys off data-ap-cases attributes, handles several
 * sliders on one page, and marks each so a second run is a no-op. The scroll
 * handler only reads scrollLeft.
 */
( function () {
	'use strict';

	var INTERVAL = 5000;
	var calm = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

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

		var timer = null;
		var held = false;
		var touched = false;

		function stop() {
			if ( timer ) {
				clearInterval( timer );
				timer = null;
			}
		}

		function start() {
			stop();
			if ( calm || held || touched ) {
				return;
			}
			timer = setInterval( function () {
				if ( ! document.hidden ) {
					go( current() + 1 );
				}
			}, INTERVAL );
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

		root.addEventListener( 'mouseenter', function () {
			held = true;
			stop();
		} );
		root.addEventListener( 'mouseleave', function () {
			held = false;
			start();
		} );
		root.addEventListener( 'focusin', function () {
			held = true;
			stop();
		} );
		root.addEventListener( 'focusout', function ( e ) {
			if ( ! root.contains( e.relatedTarget ) ) {
				held = false;
				start();
			}
		} );
		track.addEventListener(
			'touchstart',
			function () {
				touched = true;
				stop();
			},
			{ passive: true }
		);

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
