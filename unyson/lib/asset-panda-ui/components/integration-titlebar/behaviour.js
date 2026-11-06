/*
 * integration-titlebar — shows the bar once the page's H1 has fully left the
 * viewport under the sticky site header, and hides it again when the H1 comes
 * back into view (Figma: "Appears only when the entire H1 is no longer in the
 * view port").
 *
 * One IntersectionObserver per bar, watching the heading named by data-watch
 * (fallback: the first H1). The observer's top edge sits at the bar's own top
 * offset, so "out of view" means "gone under the header". Only a class is
 * toggled; nothing is measured in a scroll handler. Idempotent.
 */
( function () {
	'use strict';

	function init( bar ) {
		if ( bar.hasAttribute( 'data-ap-titlebar-ready' ) ) {
			return;
		}
		bar.setAttribute( 'data-ap-titlebar-ready', '' );
		if ( ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		var sel = bar.getAttribute( 'data-watch' ) || '';
		var target = null;
		try {
			target = sel ? document.querySelector( sel ) : null;
		} catch ( e ) {
			target = null;
		}
		target = target || document.querySelector( 'main h1, h1' );
		if ( ! target ) {
			return;
		}

		var top = parseFloat( window.getComputedStyle( bar ).top ) || 0;
		var io = new IntersectionObserver(
			function ( entries ) {
				var e = entries[ entries.length - 1 ];
				// Shown only when the heading is wholly gone ABOVE the header line,
				// not when the page is scrolled back up past it.
				var gone = ! e.isIntersecting && e.boundingClientRect.bottom <= top + 1;
				bar.classList.toggle( 'is-shown', gone );
			},
			{ rootMargin: '-' + Math.round( top ) + 'px 0px 0px 0px', threshold: 0 }
		);
		io.observe( target );
	}

	function boot() {
		var bars = document.querySelectorAll( '[data-ap-titlebar]' );
		for ( var i = 0; i < bars.length; i++ ) {
			init( bars[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
