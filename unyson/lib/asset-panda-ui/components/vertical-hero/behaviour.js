/**
 * Vertical hero: the connector lines are drawn from where the panda tile and the floating cards actually are.
 *
 * The `lines` / `lines_m` props were authored for one layout; a page whose cards sit elsewhere (Police) had its
 * lines ~130px off (QA 2026-10-29, Joseph). Each line now runs from the tile's edge to the nearest edge of its
 * card, measured from the DOM and written back into the SVG's own coordinate space (1440×550 desktop, 360×680
 * phone), with the gradient and the end dot following. Re-measured on load, resize and font load; idempotent.
 */
( function () {
	'use strict';

	function visible( el ) {
		return !! el && getComputedStyle( el ).display !== 'none' && el.getBoundingClientRect().width > 0;
	}

	function draw( hero ) {
		var badge = hero.querySelector( '.ap-vhero__badge' );
		var cards = hero.querySelectorAll( '.ap-vhero__card' );
		var svg = null;
		var all = hero.querySelectorAll( '.ap-vhero__lines' );
		for ( var s = 0; s < all.length; s++ ) {
			if ( visible( all[ s ] ) ) {
				svg = all[ s ];
				break;
			}
		}
		if ( ! svg || ! badge || ! cards.length || ! svg.viewBox || ! svg.viewBox.baseVal ) {
			return;
		}
		var vb = svg.viewBox.baseVal;
		var sr = svg.getBoundingClientRect();
		if ( ! sr.width || ! sr.height || ! vb.width || ! vb.height ) {
			return;
		}
		var sx = vb.width / sr.width;
		var sy = vb.height / sr.height;
		var br = badge.getBoundingClientRect();
		var bx = br.left + br.width / 2;
		var by = br.top + br.height / 2;
		var tile = Math.min( br.width, br.height ) / 2;
		var lines = svg.querySelectorAll( 'line' );
		var dots = svg.querySelectorAll( 'circle' );
		var grads = svg.querySelectorAll( 'linearGradient' );

		for ( var i = 0; i < lines.length; i++ ) {
			var line = lines[ i ];
			var card = cards[ i ];
			var group = line.parentNode;
			if ( ! card || ! visible( card ) ) {
				if ( group && group !== svg ) {
					group.style.display = 'none';
				}
				continue;
			}
			if ( group && group !== svg ) {
				group.style.display = '';
			}
			var cr = card.getBoundingClientRect();
			var cx = cr.left + cr.width / 2;
			var cy = cr.top + cr.height / 2;
			var dx = cx - bx;
			var dy = cy - by;
			var len = Math.sqrt( dx * dx + dy * dy ) || 1;
			var ux = dx / len;
			var uy = dy / len;
			// Start just outside the tile; end just outside the card's edge, both along the centre-to-centre ray.
			var startX = bx + ux * ( tile + 4 );
			var startY = by + uy * ( tile + 4 );
			var tx = ux !== 0 ? ( cr.width / 2 ) / Math.abs( ux ) : Infinity;
			var ty = uy !== 0 ? ( cr.height / 2 ) / Math.abs( uy ) : Infinity;
			var t = Math.min( tx, ty ) + 6;
			var endX = cx - ux * t;
			var endY = cy - uy * t;
			var x1 = ( startX - sr.left ) * sx;
			var y1 = ( startY - sr.top ) * sy;
			var x2 = ( endX - sr.left ) * sx;
			var y2 = ( endY - sr.top ) * sy;
			line.setAttribute( 'x1', x1.toFixed( 2 ) );
			line.setAttribute( 'y1', y1.toFixed( 2 ) );
			line.setAttribute( 'x2', x2.toFixed( 2 ) );
			line.setAttribute( 'y2', y2.toFixed( 2 ) );
			line.style.setProperty( '--len', Math.sqrt( ( x2 - x1 ) * ( x2 - x1 ) + ( y2 - y1 ) * ( y2 - y1 ) ).toFixed( 2 ) );
			if ( dots[ i ] ) {
				dots[ i ].setAttribute( 'cx', x2.toFixed( 2 ) );
				dots[ i ].setAttribute( 'cy', y2.toFixed( 2 ) );
			}
			if ( grads[ i ] ) {
				grads[ i ].setAttribute( 'x1', x1.toFixed( 2 ) );
				grads[ i ].setAttribute( 'y1', y1.toFixed( 2 ) );
				grads[ i ].setAttribute( 'x2', x2.toFixed( 2 ) );
				grads[ i ].setAttribute( 'y2', y2.toFixed( 2 ) );
			}
		}
		hero.setAttribute( 'data-ap-vhero-lines', 'measured' );
	}

	function drawAll() {
		var heroes = document.querySelectorAll( '.ap-vhero' );
		for ( var i = 0; i < heroes.length; i++ ) {
			try {
				draw( heroes[ i ] );
			} catch ( e ) {
				// Leave the authored lines in place.
			}
		}
	}

	var pending = null;
	function schedule() {
		if ( pending ) {
			return;
		}
		pending = window.requestAnimationFrame ? window.requestAnimationFrame( function () { pending = null; drawAll(); } ) : setTimeout( function () { pending = null; drawAll(); }, 16 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', schedule );
	} else {
		schedule();
	}
	window.addEventListener( 'load', schedule );
	window.addEventListener( 'resize', schedule );
	window.addEventListener( 'orientationchange', schedule );
	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( schedule );
	}
	if ( typeof window.ResizeObserver === 'function' ) {
		var ro = new ResizeObserver( schedule );
		var stages = document.querySelectorAll( '.ap-vhero__stage' );
		for ( var i = 0; i < stages.length; i++ ) {
			ro.observe( stages[ i ] );
		}
	}
} )();
