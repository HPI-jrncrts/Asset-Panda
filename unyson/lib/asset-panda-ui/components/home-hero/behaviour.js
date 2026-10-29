/**
 * home-hero behaviour — ported from the live homepage's two inline scripts.
 *
 * 1. Demo frame sizing. The Ursa demo (or its poster) takes live's resting
 *    height for its width (the table below, measured from the vendor bundle).
 *    From 1024px the hero fills the first screen so the logo band sits at its
 *    foot. A real demo iframe also "opens" — grows to the free height — once the
 *    visitor focuses it or drags a file onto the page.
 * 2. Logo band. On desktop the band stays pinned to the bottom of the viewport
 *    until the end of the hero scrolls into view. Live reads geometry on scroll;
 *    here an IntersectionObserver watches a sentinel at the hero's end instead.
 *
 * Keyed off [data-ap-home-hero]; each hero initialises once.
 */
( function () {
	'use strict';

	// [fromWidth, toWidth, heightAtFrom, heightAtTo, ...], interpolated within a band.
	var S = [ 240, 244, 844, 844, 245, 253, 820, 820, 254, 257, 796, 796, 258, 262, 769, 769, 263, 267, 749, 749, 268, 278, 729, 729, 279, 287, 709, 709, 288, 334, 685, 685, 335, 349, 665, 665, 350, 390, 641, 641, 391, 416, 621, 621, 417, 432, 595, 595, 433, 495, 571, 573, 496, 537, 574, 578, 538, 619, 558, 567, 620, 639, 543, 545, 640, 675, 589, 593, 676, 1086, 569, 613, 1087, 1100, 613, 613 ];

	function rest( w ) {
		var h = S[ 2 ];
		if ( w > S[ S.length - 3 ] ) {
			h = S[ S.length - 1 ];
		} else {
			for ( var i = 0; i < S.length; i += 4 ) {
				if ( w >= S[ i ] && w <= S[ i + 1 ] ) {
					h = S[ i + 2 ] + ( S[ i + 3 ] - S[ i + 2 ] ) * ( w - S[ i ] ) / ( ( S[ i + 1 ] - S[ i ] ) || 1 );
					break;
				}
			}
		}
		return Math.ceil( h + 3 );
	}

	// The fixed site header: live's own, or the kit's site-header on staging.
	function siteHeader() {
		var ids = [ 'ap-presentation-header-v5', 'ap-mobile-header-v1' ];
		for ( var i = 0; i < ids.length; i++ ) {
			var e = document.getElementById( ids[ i ] );
			if ( e && e.offsetHeight ) {
				return e;
			}
		}
		var kit = document.querySelector( '[data-ap-site-header]' );
		return kit && kit.offsetHeight ? kit : null;
	}

	function imp( el, p, v ) {
		el.style.setProperty( p, v, 'important' );
	}

	function initFrame( hero ) {
		var f = hero.querySelector( '.ap-ursa-frame' );
		if ( ! f ) {
			return;
		}
		var copy = hero.querySelector( '.ap-hero-copy' );
		var inner = hero.querySelector( '.ap-hero-inner' );
		var band = hero.querySelector( '.ap-client-logo-band' );
		var wrap = hero.querySelector( '.ap-hero-visual-wrap' );
		var live = 'IFRAME' === f.tagName;
		var open = false;
		var top0 = null;
		var mq = window.matchMedia( '(min-width:1024px)' );

		function fit() {
			var w = f.clientWidth;
			if ( ! w ) {
				return;
			}
			var r = rest( w );
			var hd = siteHeader();
			var chrome = wrap ? Math.max( 0, wrap.offsetHeight - f.offsetHeight ) : 0;
			var h = r;
			if ( mq.matches ) {
				var ht = hero.getBoundingClientRect().top;
				var sy = window.pageYOffset || 0;
				if ( null === top0 || 0 === sy ) {
					top0 = hd ? Math.max( 0, Math.round( hd.getBoundingClientRect().bottom - ht ) ) : 0;
				}
				var bh = band ? band.offsetHeight : 0;
				var need = top0 + 20 + Math.max( r + chrome, copy ? copy.offsetHeight : 0 ) + bh;
				var H = Math.max( Math.round( window.innerHeight - ( ht + sy ) ), need );
				imp( hero, '--apu-h', H + 'px' );
				imp( hero, '--apu-top', top0 + 'px' );
				imp( hero, '--apu-band', bh + 'px' );
				if ( open ) {
					h = Math.max( r, H - top0 - bh - 20 - chrome );
				}
			} else if ( open ) {
				h = Math.max( r, Math.round( window.innerHeight - ( hd ? hd.offsetHeight : 0 ) - 20 - chrome ) );
			}
			imp( f, 'height', h + 'px' );
			imp( f, 'min-height', h + 'px' );
			imp( f, 'max-height', h + 'px' );
		}

		if ( live ) {
			var pend = 0;
			var go = function () {
				if ( open ) {
					return;
				}
				open = true;
				hero.classList.add( 'apu-open' );
				fit();
			};
			var chk = function () {
				if ( ! open && ! pend && document.activeElement === f ) {
					pend = setTimeout( go, 500 );
				}
			};
			window.addEventListener( 'blur', function () {
				setTimeout( chk, 0 );
			} );
			var iv = setInterval( function () {
				if ( open ) {
					clearInterval( iv );
				} else {
					chk();
				}
			}, 300 );
			document.addEventListener( 'dragenter', function ( e ) {
				var t = e.dataTransfer && e.dataTransfer.types;
				if ( t && [].indexOf.call( t, 'Files' ) > -1 ) {
					go();
				}
			} );
		}

		var tm;
		window.addEventListener( 'resize', function () {
			clearTimeout( tm );
			tm = setTimeout( fit, 100 );
		} );
		window.addEventListener( 'load', fit );
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( fit );
		}
		if ( window.ResizeObserver ) {
			var ro = new ResizeObserver( function () {
				requestAnimationFrame( fit );
			} );
			if ( inner ) {
				ro.observe( inner );
			}
			if ( copy ) {
				ro.observe( copy );
			}
		}
		fit();
	}

	function initBand( hero ) {
		var band = hero.querySelector( '.ap-client-logo-band' );
		if ( ! band || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		var mq = window.matchMedia( '(min-width: 992px)' );
		var end = document.createElement( 'div' );
		end.setAttribute( 'aria-hidden', 'true' );
		end.style.cssText = 'position:relative;height:1px;margin-top:-1px;pointer-events:none';
		hero.appendChild( end );
		var reached = false;

		function apply() {
			if ( ! mq.matches ) {
				hero.classList.remove( 'ap-logo-band-fixed', 'ap-logo-band-released' );
				return;
			}
			hero.classList.toggle( 'ap-logo-band-fixed', ! reached );
			hero.classList.toggle( 'ap-logo-band-released', reached );
		}

		new IntersectionObserver( function ( entries ) {
			var e = entries[ entries.length - 1 ];
			// The hero's end is on screen, or already scrolled above it.
			reached = e.isIntersecting || e.boundingClientRect.top < 0;
			apply();
		} ).observe( end );
		if ( mq.addEventListener ) {
			mq.addEventListener( 'change', apply );
		} else {
			mq.addListener( apply );
		}
		apply();
	}

	function init() {
		var heroes = document.querySelectorAll( '[data-ap-home-hero]' );
		for ( var i = 0; i < heroes.length; i++ ) {
			if ( heroes[ i ].hasAttribute( 'data-ap-home-hero-ready' ) ) {
				continue;
			}
			heroes[ i ].setAttribute( 'data-ap-home-hero-ready', '' );
			initFrame( heroes[ i ] );
			initBand( heroes[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
