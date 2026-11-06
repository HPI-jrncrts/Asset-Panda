/*
 * integration-hero — swaps the split hero's video poster for the player.
 *
 * The play control is a real link to the video (it works with no script).
 * With the script, a click puts the player in the frame — a youtube-nocookie /
 * Vimeo iframe or a <video controls> for a file, built from the data-embed URL
 * render.php resolved — starts it, and focuses it. Same behaviour as the
 * integration-detail overview video.
 *
 * Delegated and idempotent: an instance already wired is skipped.
 */
( function () {
	'use strict';

	function play( link ) {
		var src = link.getAttribute( 'data-embed' );
		var frame = link.closest( '.ap-integration-hero__media' );
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
		el.className = 'ap-integration-hero__player';
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
		if ( root.hasAttribute( 'data-ap-ihero-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-ap-ihero-ready', '' );
		root.addEventListener( 'click', function ( e ) {
			var link = e.target.closest && e.target.closest( '[data-ap-ihero-play]' );
			if ( link && ! e.ctrlKey && ! e.metaKey && ! e.shiftKey && play( link ) ) {
				e.preventDefault();
			}
		} );
	}

	function boot() {
		var roots = document.querySelectorAll( '[data-ap-integration-hero]' );
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
