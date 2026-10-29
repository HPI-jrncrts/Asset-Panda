/*
 * case-slider — moves between customer stories, on its own and by hand.
 *
 * Live's inline slider script, made per-instance and idempotent: builds one dot
 * per slide, wires the arrows, and slides the progress segment along the line.
 *
 * Autoplay: advances every data-ap-autoplay ms (3000 when the attribute is
 * absent; 0 turns it off), looping. The outgoing story leaves on the side
 * opposite the incoming one, so each change reads as a swipe in the direction
 * of travel. It pauses while a mouse is over the slider, while keyboard focus
 * is inside it, while a finger is on it (restarting the count after), while the
 * slider is off screen and while the tab is hidden; it never runs for visitors
 * who prefer reduced motion. A dot or arrow restarts the count. A horizontal
 * finger swipe moves one story.
 *
 * Only classes, inline transforms and attributes change; nothing is measured
 * in a scroll handler, and the timer never runs while the slider is off screen.
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
		if ( root.hasAttribute( 'data-ap-case-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-ap-case-ready', '' );

		var slides = root.querySelectorAll( '.apg-case-slide' );
		var dots = root.querySelector( '.apg-case-dots' );
		var prev = root.querySelector( '.apg-arrow-prev' );
		var next = root.querySelector( '.apg-arrow-next' );
		var progress = root.querySelector( '.apg-case-progress' );
		var stage = root.querySelector( '.apg-case-slides' ) || root;
		var cur = 0;
		var n = slides.length;

		if ( ! n ) {
			return;
		}

		var attr = root.getAttribute( 'data-ap-autoplay' );
		var ms = null === attr || '' === attr ? DEFAULT_MS : parseInt( attr, 10 );
		if ( ! ( ms >= 0 ) ) {
			ms = DEFAULT_MS;
		}
		if ( ms > 0 && ms < 1500 ) {
			ms = 1500;
		}
		var auto = n > 1 && ms > 0 && ! calm;
		var timer = 0;
		var hovered = false;
		var focused = false;
		var touching = false;
		var visible = false;

		function running() {
			return auto && visible && ! hovered && ! focused && ! touching && ! document.hidden;
		}

		function schedule() {
			clearTimeout( timer );
			timer = 0;
			var on = running();
			stage.setAttribute( 'aria-live', on ? 'off' : 'polite' );
			if ( on ) {
				timer = setTimeout( function () {
					go( cur + 1, 1 );
				}, ms );
			}
		}

		// Directional swipe: the incoming story starts a few px off to the side it
		// comes from and the outgoing one leaves to the other side; the slides'
		// CSS transitions do the motion. Distance comes from --apg-swipe.
		function swipe( from, to, dir ) {
			if ( calm || ! dir || from === to ) {
				return;
			}
			var shift = parseFloat( window.getComputedStyle( root ).getPropertyValue( '--apg-swipe' ) ) || 40;
			// Never travel further than the gutter beside the slides (tablet widths), so a
			// moving story cannot widen the page. One read per change, not per frame.
			var gutter = to.parentNode.getBoundingClientRect().left;
			if ( gutter >= 0 ) {
				// (less the photo's own 22px sideways entrance, which rides on top of this
				// in the side-by-side layout; stacked layouts enter vertically)
				var own = window.innerWidth > 1180 ? 26 : 4;
				shift = Math.min( shift, Math.max( 8, gutter - own ) );
			}
			to.style.transition = 'none';
			to.style.transform = 'translateX(' + ( dir * shift ) + 'px)';
			window.getComputedStyle( to ).transform; // commit the start position (style only, no layout)
			to.style.transition = '';
			from.style.transform = 'translateX(' + ( -dir * shift ) + 'px)';
			clearTimeout( from.__apLeave );
			from.__apLeave = setTimeout( function () {
				if ( ! from.classList.contains( 'is-active' ) ) {
					from.style.transition = 'none';
					from.style.transform = '';
					window.getComputedStyle( from ).transform;
					from.style.transition = '';
				}
			}, 700 );
		}

		function go( index, dir ) {
			var target = ( ( index % n ) + n ) % n;
			var from = slides[ cur ];
			var to = slides[ target ];
			if ( to !== from ) {
				swipe( from, to, dir );
			}
			cur = target;
			for ( var k = 0; k < n; k++ ) {
				slides[ k ].classList.toggle( 'is-active', k === cur );
				if ( dots && dots.children[ k ] ) {
					dots.children[ k ].classList.toggle( 'is-active', k === cur );
					if ( k === cur ) {
						dots.children[ k ].setAttribute( 'aria-current', 'true' );
					} else {
						dots.children[ k ].removeAttribute( 'aria-current' );
					}
				}
			}
			to.style.transform = '';
			clearTimeout( to.__apLeave );
			if ( progress ) {
				progress.style.width = ( 100 / n ) + '%';
				progress.style.transform = 'translateX(' + ( 100 * cur ) + '%)';
			}
			schedule();
		}

		if ( dots ) {
			for ( var j = 0; j < n; j++ ) {
				( function ( idx ) {
					var d = document.createElement( 'button' );
					d.type = 'button';
					d.className = 'apg-case-dot' + ( 0 === idx ? ' is-active' : '' );
					d.setAttribute( 'aria-label', 'Go to case study ' + ( idx + 1 ) );
					d.addEventListener( 'click', function () {
						if ( idx !== cur ) {
							go( idx, idx > cur ? 1 : -1 );
						}
					} );
					dots.appendChild( d );
				} )( j );
			}
		}

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				go( cur - 1, -1 );
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				go( cur + 1, 1 );
			} );
		}

		if ( n > 1 ) {
			root.addEventListener( 'pointerenter', function ( event ) {
				hovered = 'mouse' === event.pointerType;
				schedule();
			} );
			root.addEventListener( 'pointerleave', function () {
				hovered = false;
				schedule();
			} );
			root.addEventListener( 'focusin', function ( event ) {
				focused = keyboardFocus( event.target );
				schedule();
			} );
			root.addEventListener( 'focusout', function ( event ) {
				if ( event.relatedTarget && root.contains( event.relatedTarget ) ) {
					return;
				}
				focused = false;
				schedule();
			} );

			var sx = 0;
			var sy = 0;
			root.addEventListener( 'touchstart', function ( event ) {
				var t = event.touches[ 0 ];
				sx = t.clientX;
				sy = t.clientY;
				touching = true;
				schedule();
			}, { passive: true } );
			var release = function ( event ) {
				touching = false;
				var t = event.changedTouches && event.changedTouches[ 0 ];
				if ( t && ! event.target.closest( '.apg-case-dot, .apg-arrow-btn' ) ) {
					var dx = t.clientX - sx;
					var dy = t.clientY - sy;
					if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) * 1.5 ) {
						go( cur + ( dx < 0 ? 1 : -1 ), dx < 0 ? 1 : -1 );
						return;
					}
				}
				schedule();
			};
			root.addEventListener( 'touchend', release, { passive: true } );
			root.addEventListener( 'touchcancel', release, { passive: true } );

			if ( auto ) {
				document.addEventListener( 'visibilitychange', schedule );
				if ( watcher ) {
					root.__apCaseVisible = function ( v ) {
						visible = v;
						schedule();
					};
					watcher.observe( root );
				} else {
					visible = true;
				}
			}
		}
		go( 0 );
	}

	function initAll() {
		if ( ! calm && typeof window.IntersectionObserver === 'function' ) {
			watcher = new IntersectionObserver( function ( entries ) {
				for ( var i = 0; i < entries.length; i++ ) {
					var fn = entries[ i ].target.__apCaseVisible;
					if ( fn ) {
						fn( entries[ i ].isIntersecting );
					}
				}
			}, { threshold: 0.25 } );
		}
		var nodes = document.querySelectorAll( '[data-ap-case-slider]' );
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
