/*
 * hero — respect reduced motion for the autoplaying product video.
 *
 * The video is muted, looped and autoplayed for everyone by the markup, which
 * works with no JavaScript. This only stops it for visitors who have asked
 * their operating system to reduce motion. Idempotent: pausing twice is a
 * no-op, and it handles any number of heroes on a page.
 */
( function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' );

	function apply() {
		if ( ! reduce || ! reduce.matches ) {
			return;
		}
		var videos = document.querySelectorAll( '[data-ap-hero-video]' );
		for ( var i = 0; i < videos.length; i++ ) {
			videos[ i ].removeAttribute( 'autoplay' );
			videos[ i ].pause();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', apply );
	} else {
		apply();
	}
} )();
