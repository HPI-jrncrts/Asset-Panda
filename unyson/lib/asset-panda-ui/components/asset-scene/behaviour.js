/**
 * Asset scene, narrow screens (≤1000px): the connector lines run from the Ursa orb up to each card, always behind
 * the cards (QA 2026-10-29, Joseph's sketch), with each card's icon disc on its top edge — the laptop on IT, the
 * truck on Field & Fleet, the shield on Public Safety, cut from the same artwork as desktop.
 *
 * One SVG per stage, under the cards (z-index 0; the cards are 1, the discs 2). The orb is located from the
 * artwork itself: its centre sits at (734, 608) of the 1471 × 1118 file and its radius is ~100 file pixels.
 * Measured on load, resize and font load; delegated, idempotent, one ResizeObserver; no scroll handler.
 */
( function () {
	'use strict';

	var NS = 'http://www.w3.org/2000/svg';
	var MQ = window.matchMedia ? window.matchMedia( '(max-width: 1000px)' ) : null;
	var FILE_W = 1471;
	var FILE_H = 1118;
	var ORB = { x: 734, y: 608, r: 100 };
	// Disc centres in the file, per card slot (the desktop masks' centres).
	var DISCS = {
		it: { x: 0.4969 * FILE_W, y: 0.4157 * FILE_H },
		field: { x: 0.3494 * FILE_W, y: 0.4819 * FILE_H },
		safety: { x: 0.6485 * FILE_W, y: 0.4837 * FILE_H }
	};
	var DISC_FILE_D = 60; // disc diameter in the file
	var DISC_D = 48; // disc diameter on screen

	function slotOf( card ) {
		var m = /ap-asset-scene__card--([a-z]+)/.exec( card.className );
		return m ? m[ 1 ] : '';
	}

	function clear( stage ) {
		var svg = stage.querySelector( ':scope > .ap-asset-scene__wires' );
		if ( svg ) {
			stage.removeChild( svg );
		}
		var discs = stage.querySelectorAll( '.ap-asset-scene__disc' );
		for ( var i = 0; i < discs.length; i++ ) {
			discs[ i ].parentNode.removeChild( discs[ i ] );
		}
		stage.removeAttribute( 'data-ap-wires' );
	}

	function draw( stage ) {
		if ( ! MQ || ! MQ.matches ) {
			clear( stage );
			return;
		}
		var img = stage.querySelector( '.ap-asset-scene__art img' );
		var cards = stage.querySelectorAll( '.ap-asset-scene__card' );
		if ( ! img || ! cards.length || ! img.complete || ! img.naturalWidth ) {
			return;
		}
		var sr = stage.getBoundingClientRect();
		var ir = img.getBoundingClientRect();
		if ( ! sr.width || ! ir.width ) {
			return;
		}
		var k = ir.width / FILE_W;
		var orb = { x: ir.left - sr.left + ORB.x * k, y: ir.top - sr.top + ORB.y * k, r: ORB.r * k };
		var src = img.currentSrc || img.src;

		var svg = stage.querySelector( ':scope > .ap-asset-scene__wires' );
		if ( ! svg ) {
			svg = document.createElementNS( NS, 'svg' );
			svg.setAttribute( 'class', 'ap-asset-scene__wires' );
			svg.setAttribute( 'aria-hidden', 'true' );
			svg.setAttribute( 'focusable', 'false' );
			stage.insertBefore( svg, stage.firstChild );
		}
		svg.setAttribute( 'viewBox', '0 0 ' + sr.width.toFixed( 1 ) + ' ' + sr.height.toFixed( 1 ) );
		var paths = '';
		var n = cards.length;
		for ( var i = 0; i < n; i++ ) {
			var card = cards[ i ];
			var slot = slotOf( card );
			var cr = card.getBoundingClientRect();
			// The disc sits on the card's top edge, centred.
			var dx = cr.left - sr.left + cr.width / 2;
			var dy = cr.top - sr.top;
			// Lines leave the orb's upper arc, spread left to right in card order (the first card's line is the
			// left one, as sketched), and rise to the discs.
			var a = Math.PI * ( 0.75 - ( n > 1 ? i / ( n - 1 ) : 0.5 ) * 0.5 );
			var sx = orb.x + Math.cos( a ) * orb.r * 0.98;
			var sy = orb.y - Math.abs( Math.sin( a ) ) * orb.r * 0.98;
			var bend = Math.max( 60, ( sy - dy ) * 0.45 );
			paths += '<path d="M ' + sx.toFixed( 1 ) + ' ' + sy.toFixed( 1 ) + ' C ' + sx.toFixed( 1 ) + ' ' + ( sy - bend ).toFixed( 1 ) + ' ' + dx.toFixed( 1 ) + ' ' + ( dy + bend ).toFixed( 1 ) + ' ' + dx.toFixed( 1 ) + ' ' + dy.toFixed( 1 ) + '"/>';
			paths += '<circle cx="' + sx.toFixed( 1 ) + '" cy="' + sy.toFixed( 1 ) + '" r="2.5"/>';

			var disc = card.querySelector( ':scope > .ap-asset-scene__disc' );
			var d = DISCS[ slot ];
			if ( d ) {
				if ( ! disc ) {
					disc = document.createElement( 'span' );
					disc.className = 'ap-asset-scene__disc';
					disc.setAttribute( 'aria-hidden', 'true' );
					card.appendChild( disc );
				}
				var s = DISC_D / DISC_FILE_D;
				disc.style.backgroundImage = 'url("' + src.replace( /"/g, '%22' ) + '")';
				disc.style.backgroundSize = ( FILE_W * s ).toFixed( 1 ) + 'px auto';
				disc.style.backgroundPosition = ( -( d.x * s - DISC_D / 2 ) ).toFixed( 1 ) + 'px ' + ( -( d.y * s - DISC_D / 2 ) ).toFixed( 1 ) + 'px';
			}
		}
		svg.innerHTML = paths;
		stage.setAttribute( 'data-ap-wires', 'drawn' );
	}

	function drawAll() {
		var stages = document.querySelectorAll( '.ap-asset-scene__stage' );
		for ( var i = 0; i < stages.length; i++ ) {
			try {
				draw( stages[ i ] );
			} catch ( e ) {
				// Leave the scene as rendered.
			}
		}
	}

	var pending = null;
	function schedule() {
		if ( pending ) {
			return;
		}
		pending = ( window.requestAnimationFrame || setTimeout )( function () {
			pending = null;
			drawAll();
		} );
	}

	function init() {
		var imgs = document.querySelectorAll( '.ap-asset-scene__art img' );
		for ( var i = 0; i < imgs.length; i++ ) {
			if ( ! imgs[ i ].complete ) {
				imgs[ i ].addEventListener( 'load', schedule );
			}
		}
		if ( typeof window.ResizeObserver === 'function' ) {
			var ro = new ResizeObserver( schedule );
			var stages = document.querySelectorAll( '.ap-asset-scene__stage' );
			for ( var j = 0; j < stages.length; j++ ) {
				ro.observe( stages[ j ] );
			}
		}
		schedule();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	window.addEventListener( 'load', schedule );
	window.addEventListener( 'resize', schedule );
	if ( MQ && MQ.addEventListener ) {
		MQ.addEventListener( 'change', schedule );
	}
	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( schedule );
	}
} )();
