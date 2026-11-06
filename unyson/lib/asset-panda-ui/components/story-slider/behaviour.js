/*
 * story-slider — slide switching, autoplay, swipe and the video facade.
 *
 * Every slide is rendered and shares one grid cell; this toggles .is-active on
 * the slides and dots, slides the progress bar, and keeps inactive slides out
 * of the tab order. A video slide shows a poster button until clicked, then
 * swaps in a youtube-nocookie player; leaving the slide puts the poster back
 * so the video stops.
 *
 * Autoplay: advances every data-ap-autoplay ms (3000 when the attribute is
 * absent; 0 turns it off), looping. The outgoing slide leaves on the side
 * opposite the incoming one, so each change reads as a swipe in the direction
 * of travel. It pauses while a mouse is over the slider, while keyboard focus
 * is inside it, while a finger is on it (and restarts the count after), while
 * a video plays, while the slider is off screen and while the tab is hidden;
 * it never runs for visitors who prefer reduced motion. Any manual change
 * restarts the count. A horizontal finger swipe moves one slide.
 *
 * Only classes, inline transforms and attributes change — nothing is measured
 * in a scroll handler, and the timer never runs while the slider is off screen.
 *
 * Delegated and idempotent: keys off data-ap-story, handles several sliders on
 * one page, and marks each so a second run is a no-op.
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
		if ( root.hasAttribute( 'data-ap-story-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-ap-story-ready', '' );

		var slides = root.querySelectorAll( '.apc-slide' );
		var dots = root.querySelectorAll( '.apc-dot' );
		var bar = root.querySelector( '.apc-progress-bar' );
		var prev = root.querySelector( '.apc-prev' );
		var next = root.querySelector( '.apc-next' );
		var stage = root.querySelector( '.apc-slider' ) || root;
		var n = slides.length;
		var cur = 0;

		var attr = root.getAttribute( 'data-ap-autoplay' );
		var ms = null === attr || '' === attr ? DEFAULT_MS : parseInt( attr, 10 );
		if ( ! ( ms >= 0 ) ) {
			ms = DEFAULT_MS;
		}
		if ( ms > 0 && ms < 1500 ) {
			ms = 1500; // a slide must stay long enough to be seen at all
		}
		var auto = n > 1 && ms > 0 && ! calm;
		var timer = 0;
		var hovered = false;
		var focused = false;
		var touching = false;
		var playing = false;
		var visible = false;

		function running() {
			return auto && visible && ! hovered && ! focused && ! touching && ! playing && ! document.hidden;
		}

		function schedule() {
			clearTimeout( timer );
			timer = 0;
			var on = running();
			// Screen readers announce a slide change only when a person made it.
			stage.setAttribute( 'aria-live', on ? 'off' : 'polite' );
			if ( on ) {
				timer = setTimeout( function () {
					go( cur + 1, 1 );
				}, ms );
			}
		}

		function restore() {
			var frames = root.querySelectorAll( '.apc-media--video iframe' );
			for ( var i = 0; i < frames.length; i++ ) {
				var f = frames[ i ];
				if ( f.__apPoster && ! f.closest( '.apc-slide' ).classList.contains( 'is-active' ) ) {
					f.parentNode.replaceChild( f.__apPoster, f );
				}
			}
			playing = !! root.querySelector( '.apc-slide.is-active .apc-media--video iframe' );
		}

		// Directional swipe: the incoming slide starts a few px off to the side
		// it comes from, the outgoing one leaves to the other side. The slides'
		// own CSS transitions do the motion; this only sets where they start.
		function swipe( from, to, dir ) {
			if ( calm || ! dir || from === to ) {
				return;
			}
			// Travel distance from the stylesheet (--apc-swipe): wide on desktop,
			// inside the side padding on phones so nothing pokes past the screen.
			var shift = parseFloat( window.getComputedStyle( root ).getPropertyValue( '--apc-swipe' ) ) || 40;
			// Never travel further than the gutter beside the slides (tablet widths), so a
			// moving slide cannot widen the page. One read per change, not per frame.
			var gutter = to.parentNode.getBoundingClientRect().left;
			if ( gutter >= 0 ) {
				// (less the photo's own 22px sideways entrance, which rides on top of this
				// in the side-by-side layout; stacked layouts enter vertically)
				var own = window.innerWidth > 900 ? 26 : 4;
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
			for ( var a = 0; a < n; a++ ) {
				var on = a === cur;
				slides[ a ].classList.toggle( 'is-active', on );
				if ( on ) {
					slides[ a ].removeAttribute( 'aria-hidden' );
				} else {
					slides[ a ].setAttribute( 'aria-hidden', 'true' );
				}
				var focusables = slides[ a ].querySelectorAll( 'a, button' );
				for ( var f = 0; f < focusables.length; f++ ) {
					if ( on ) {
						focusables[ f ].removeAttribute( 'tabindex' );
					} else {
						focusables[ f ].setAttribute( 'tabindex', '-1' );
					}
				}
			}
			// Release the incoming slide's start position in the same frame as the
			// class change, so it transitions from there to its resting place.
			to.style.transform = '';
			clearTimeout( to.__apLeave );
			for ( var b = 0; b < dots.length; b++ ) {
				dots[ b ].classList.toggle( 'is-active', b === cur );
				if ( b === cur ) {
					dots[ b ].setAttribute( 'aria-current', 'true' );
				} else {
					dots[ b ].removeAttribute( 'aria-current' );
				}
			}
			if ( bar ) {
				bar.style.transform = 'translateX(' + cur * 100 + '%)';
			}
			restore();
			schedule();
		}

		if ( n > 1 ) {
			if ( prev ) {
				prev.addEventListener( 'click', function () { go( cur - 1, -1 ); } );
			}
			if ( next ) {
				next.addEventListener( 'click', function () { go( cur + 1, 1 ); } );
			}
			Array.prototype.forEach.call( dots, function ( dot, j ) {
				dot.addEventListener( 'click', function () {
					if ( j !== cur ) {
						go( j, j > cur ? 1 : -1 );
					}
				} );
			} );

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

			// Touch: hold pauses, release restarts the count; a horizontal swipe
			// moves one slide. Passive listeners — vertical page scroll is untouched.
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
				if ( t && ! event.target.closest( '.apc-dot, .apc-arrow-btn, iframe' ) ) {
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
					root.__apStoryVisible = function ( v ) {
						visible = v;
						schedule();
					};
					watcher.observe( root );
				} else {
					visible = true;
					schedule();
				}
			}
		}

		root.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '.apc-vid' );
			if ( ! btn || ! root.contains( btn ) ) {
				return;
			}
			var id = btn.getAttribute( 'data-yt' );
			if ( ! /^[\w-]{6,20}$/.test( id || '' ) ) {
				return;
			}
			var src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0&modestbranding=1&playsinline=1&cc_load_policy=1';
			var start = btn.getAttribute( 'data-start' );
			var end = btn.getAttribute( 'data-end' );
			if ( start ) {
				src += '&start=' + parseInt( start, 10 );
			}
			if ( end ) {
				src += '&end=' + parseInt( end, 10 );
			}
			var frame = document.createElement( 'iframe' );
			frame.setAttribute( 'src', src );
			frame.setAttribute( 'title', btn.getAttribute( 'data-title' ) || 'Video testimonial' );
			frame.setAttribute( 'allow', 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture' );
			frame.setAttribute( 'allowfullscreen', '' );
			frame.__apPoster = btn;
			btn.parentNode.replaceChild( frame, btn );
			// The video holds the slider until the visitor moves on.
			playing = true;
			schedule();
			if ( window.dataLayer ) {
				window.dataLayer.push( { event: 'ap_video_play', video_id: id, video_title: btn.getAttribute( 'data-title' ) || '', page_path: location.pathname } );
			}
		} );
	}

	function boot() {
		if ( ! calm && typeof window.IntersectionObserver === 'function' ) {
			watcher = new IntersectionObserver( function ( entries ) {
				for ( var i = 0; i < entries.length; i++ ) {
					var fn = entries[ i ].target.__apStoryVisible;
					if ( fn ) {
						fn( entries[ i ].isIntersecting );
					}
				}
			}, { threshold: 0.25 } );
		}
		var roots = document.querySelectorAll( '[data-ap-story]' );
		for ( var i = 0; i < roots.length; i++ ) {
			init( roots[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
