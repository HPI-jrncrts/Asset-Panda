/*
 * integration-detail — keeps the part index in step with the page, and swaps
 * the overview video's poster for the player.
 *
 * Index: the part crossing the band from just under the sticky header to 40%
 * down the viewport is the current one — the lowest part in that band wins, so
 * a part becomes current as its top passes the 40% line (a short part keeps the
 * dot while it is read); at the foot of the page a short last part that can no
 * longer reach the band lights up once fully in view. Its link gets
 * aria-current, which CSS turns into the coloured dot. A click scrolls to the
 * part (smoothly, instantly under reduced motion), moves focus there so keyboard
 * users carry on from it, records the anchor in the address bar without a jump,
 * and pins that part as current until the reader scrolls (wheel, touch, scroll
 * keys or a press outside the index). Observers only; nothing is measured on
 * scroll.
 *
 * Video: the play control is a real link to the video (it works with no
 * script). With the script, a click puts the player in the frame — a
 * youtube-nocookie / Vimeo iframe or a <video controls> for a file, built from
 * the data-embed URL render.php resolved — starts it, and focuses it.
 *
 * Delegated and idempotent: an instance already wired is skipped.
 */
( function () {
	'use strict';

	var calm = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

	function stickyTop( root ) {
		var v = parseFloat( window.getComputedStyle( root ).getPropertyValue( '--ap-idet-sticky-top' ) );
		return isFinite( v ) ? v : 104;
	}

	function initIndex( root ) {
		var links = root.querySelectorAll( '.ap-integration-detail__nav a[href^="#"]' );
		if ( ! links.length ) {
			return;
		}
		var parts = [];
		var byId = {};
		for ( var i = 0; i < links.length; i++ ) {
			var id = decodeURIComponent( links[ i ].getAttribute( 'href' ).slice( 1 ) );
			var part = document.getElementById( id );
			if ( part ) {
				byId[ id ] = links[ i ];
				parts.push( part );
			}
		}
		var current = parts.length ? parts[ 0 ].id : '';
		var pinned = ''; // the part a click jumped to, held current until the reader scrolls

		function setActive( id ) {
			if ( ! byId[ id ] ) {
				return;
			}
			current = id;
			for ( var k in byId ) {
				if ( k === id ) {
					byId[ k ].setAttribute( 'aria-current', 'true' );
				} else {
					byId[ k ].removeAttribute( 'aria-current' );
				}
			}
		}

		root.addEventListener( 'click', function ( e ) {
			var a = e.target.closest && e.target.closest( '.ap-integration-detail__nav a[href^="#"]' );
			if ( ! a ) {
				return;
			}
			var target = document.getElementById( decodeURIComponent( a.getAttribute( 'href' ).slice( 1 ) ) );
			if ( ! target ) {
				return;
			}
			e.preventDefault();
			// Layout position (offsetTop), not the painted one: a part still easing in
			// on the site-wide scroll-in is drawn 28px low, which scrollIntoView keeps.
			var y = 0;
			for ( var el = target; el; el = el.offsetParent ) {
				y += el.offsetTop;
			}
			window.scrollTo( { top: Math.max( 0, y - stickyTop( root ) ), behavior: calm ? 'auto' : 'smooth' } );
			if ( ! target.hasAttribute( 'tabindex' ) ) {
				target.setAttribute( 'tabindex', '-1' );
			}
			target.focus( { preventScroll: true } );
			pinned = target.id;
			setActive( target.id );
			if ( window.history && window.history.replaceState ) {
				try {
					window.history.replaceState( null, '', '#' + target.id );
				} catch ( err ) {
					// file:// previews and sandboxed frames refuse; the jump already happened.
				}
			}
		} );

		if ( ! ( 'IntersectionObserver' in window ) || ! parts.length ) {
			return;
		}

		// A clicked part stays current until the reader scrolls on their own: the
		// smooth scroll to it passes other parts through the band, and a short part
		// can sit with the next part's top already inside the band. Programmatic
		// scrolling fires none of these events; wheel, touch, scroll keys and a
		// press outside the index (the scrollbar included) release the pin.
		function release( e ) {
			if ( ! pinned ) {
				return;
			}
			if ( 'mousedown' === e.type && e.target.closest && e.target.closest( '.ap-integration-detail__nav' ) ) {
				return;
			}
			if ( 'keydown' === e.type && ! /^(ArrowUp|ArrowDown|PageUp|PageDown|Home|End| |Spacebar)$/.test( e.key ) ) {
				return;
			}
			pinned = '';
		}
		[ 'wheel', 'touchstart', 'keydown', 'mousedown' ].forEach( function ( type ) {
			window.addEventListener( type, release, { passive: true } );
		} );

		var bandTop = Math.round( stickyTop( root ) );
		var inBand = {};
		var io = new IntersectionObserver(
			function ( entries ) {
				for ( var j = 0; j < entries.length; j++ ) {
					inBand[ entries[ j ].target.id ] = entries[ j ].isIntersecting;
				}
				if ( pinned ) {
					return;
				}
				for ( var p = parts.length - 1; p >= 0; p-- ) {
					if ( inBand[ parts[ p ].id ] ) {
						if ( parts[ p ].id !== current ) {
							setActive( parts[ p ].id );
						}
						return;
					}
				}
				// No part in the band: between two parts the current one stays; back
				// above the first part (a jump to the top) the index starts again at 01.
				// One read per observer callback, never per scroll frame.
				if ( parts[ 0 ].id !== current && parts[ 0 ].getBoundingClientRect().top >= window.innerHeight * 0.4 ) {
					setActive( parts[ 0 ].id );
				}
			},
			// The band: from just under the sticky header to 40% down the viewport.
			{ rootMargin: '-' + bandTop + 'px 0px -60% 0px' }
		);
		parts.forEach( function ( part ) {
			io.observe( part );
		} );

		// Page foot: a short last part may never reach the band. Once it is fully in
		// view, if the page cannot scroll far enough to bring its top into the band,
		// it becomes current (one read per callback).
		var last = parts[ parts.length - 1 ];
		if ( parts.length > 1 ) {
			new IntersectionObserver(
				function ( entries ) {
					var en = entries[ entries.length - 1 ];
					if ( pinned || ! en.isIntersecting || en.intersectionRatio < 0.99 || current === last.id ) {
						return;
					}
					var doc = document.documentElement;
					var room = doc.scrollHeight - ( window.scrollY + window.innerHeight );
					var toBand = last.getBoundingClientRect().top - window.innerHeight * 0.4;
					if ( room < toBand ) {
						setActive( last.id );
					}
				},
				{ threshold: [ 0.99, 1 ] }
			).observe( last );
		}
	}

	function play( link ) {
		var src = link.getAttribute( 'data-embed' );
		var frame = link.closest( '.ap-integration-detail__media' );
		if ( ! src || ! frame ) {
			return false;
		}
		var el;
		if ( 'video' === link.getAttribute( 'data-kind' ) ) {
			el = document.createElement( 'video' );
			el.src = src;
			el.controls = true;
			el.playsInline = true;
			el.setAttribute( 'playsinline', '' );
			el.setAttribute( 'aria-label', link.getAttribute( 'data-title' ) || '' );
		} else {
			el = document.createElement( 'iframe' );
			el.src = src;
			el.title = link.getAttribute( 'data-title' ) || '';
			el.setAttribute( 'allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen' );
			el.setAttribute( 'allowfullscreen', '' );
			el.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		}
		el.className = 'ap-integration-detail__player';
		frame.appendChild( el );
		frame.classList.add( 'is-playing' );
		link.parentNode.removeChild( link );
		el.focus( { preventScroll: true } );
		if ( 'VIDEO' === el.nodeName && el.play ) {
			var started = el.play();
			if ( started && started.catch ) {
				started.catch( function () {} );
			}
		}
		return true;
	}

	function init( root ) {
		if ( root.hasAttribute( 'data-ap-idet-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-ap-idet-ready', '' );
		initIndex( root );
		root.addEventListener( 'click', function ( e ) {
			var link = e.target.closest && e.target.closest( '[data-ap-idet-play]' );
			if ( link && ! e.ctrlKey && ! e.metaKey && ! e.shiftKey && play( link ) ) {
				e.preventDefault();
			}
		} );
	}

	function boot() {
		var roots = document.querySelectorAll( '[data-ap-integration-detail]' );
		for ( var i = 0; i < roots.length; i++ ) {
			init( roots[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
