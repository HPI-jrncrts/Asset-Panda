/*
 * story-slider — slide switching and the video facade.
 *
 * Every slide is rendered and shares one grid cell; this toggles .is-active on
 * the slides and dots, slides the progress bar, and keeps inactive slides out
 * of the tab order. A video slide shows a poster button until clicked, then
 * swaps in a youtube-nocookie player; leaving the slide puts the poster back
 * so the video stops.
 *
 * Delegated and idempotent: keys off data-ap-story, handles several sliders on
 * one page, and marks each so a second run is a no-op.
 */
( function () {
	'use strict';

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
		var n = slides.length;
		var cur = 0;

		function restore() {
			var frames = root.querySelectorAll( '.apc-media--video iframe' );
			for ( var i = 0; i < frames.length; i++ ) {
				var f = frames[ i ];
				if ( f.__apPoster && ! f.closest( '.apc-slide' ).classList.contains( 'is-active' ) ) {
					f.parentNode.replaceChild( f.__apPoster, f );
				}
			}
		}

		function go( index ) {
			cur = ( ( index % n ) + n ) % n;
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
			for ( var b = 0; b < dots.length; b++ ) {
				dots[ b ].classList.toggle( 'is-active', b === cur );
			}
			if ( bar ) {
				bar.style.transform = 'translateX(' + cur * 100 + '%)';
			}
			restore();
		}

		if ( n > 1 ) {
			if ( prev ) {
				prev.addEventListener( 'click', function () { go( cur - 1 ); } );
			}
			if ( next ) {
				next.addEventListener( 'click', function () { go( cur + 1 ); } );
			}
			Array.prototype.forEach.call( dots, function ( dot, j ) {
				dot.addEventListener( 'click', function () { go( j ); } );
			} );
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
			if ( window.dataLayer ) {
				window.dataLayer.push( { event: 'ap_video_play', video_id: id, video_title: btn.getAttribute( 'data-title' ) || '', page_path: location.pathname } );
			}
		} );
	}

	function boot() {
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
