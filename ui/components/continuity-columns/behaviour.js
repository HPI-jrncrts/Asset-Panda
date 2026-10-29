/*
 * continuity-columns — the rule's scroll-driven "reflector", where CSS cannot drive it.
 *
 * style.css moves the light band along the timeline rule with a CSS view timeline (see its QA 2026-10-29 block).
 * This script supplies the same progress by hand — --ap-cc-p on each track, 0 as the track enters at the foot of
 * the screen and 1 as it leaves at the top, the number the CSS maps to the band's position — in two cases:
 *   - the browser has no scroll-driven animations (`animation-timeline`), or
 *   - it has them, but an ancestor of the track is a scroll box (overflow hidden / auto / scroll on a wrapper the
 *     page does not scroll), which would pin the view timeline to that box and freeze the band. Those tracks get
 *     [data-ap-cc-beam-js], which turns the CSS animation off for them.
 * Scrolling down therefore runs the band left → right (top → bottom on the vertical timeline), scrolling up runs
 * it back.
 *
 * Rules this file honours (ui/CLAUDE.md): delegated (every [data-ap-cc-beam] track of every .ap-continuity-columns on
 * the page), idempotent (a second copy of the script, or a second run, does nothing), and no layout work in the
 * scroll handler — the handler only queues one requestAnimationFrame, which reads every active track's geometry
 * first and writes the custom properties after. An IntersectionObserver keeps the list of active tracks, so a rule
 * that is off screen (or hidden at this width) is never measured. Reduced motion: the CSS parks the band half-way
 * along and this script stays out.
 */
( function () {
	'use strict';

	var ROOT = document.documentElement;

	if ( ROOT.hasAttribute( 'data-ap-cc-beam-ready' ) ) {
		return;
	}
	ROOT.setAttribute( 'data-ap-cc-beam-ready', '' );

	if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}
	if ( typeof window.IntersectionObserver !== 'function' || typeof window.requestAnimationFrame !== 'function' ) {
		return; // The band rests half-way along (the CSS default), which is the reduced-motion look.
	}

	var SDA = !! ( window.CSS && typeof CSS.supports === 'function' && CSS.supports( 'animation-timeline: view()' ) );

	function scrollBox( value ) {
		return 'visible' !== value && 'clip' !== value;
	}

	// True when a CSS view timeline on this track would follow the page scroll: no ancestor between it and the
	// root is a scroll container. <body>'s overflow is handed to the viewport while <html> keeps the default.
	function cssDrives( track ) {
		if ( ! SDA ) {
			return false;
		}
		var rootStyle = window.getComputedStyle( ROOT );
		var bodyToViewport = ! scrollBox( rootStyle.overflowX ) && ! scrollBox( rootStyle.overflowY );
		for ( var el = track.parentElement; el && el !== ROOT; el = el.parentElement ) {
			if ( el === document.body && bodyToViewport ) {
				continue;
			}
			var cs = window.getComputedStyle( el );
			if ( scrollBox( cs.overflowX ) || scrollBox( cs.overflowY ) ) {
				return false;
			}
		}
		return true;
	}

	var active = [];
	var queued = false;

	function frame() {
		queued = false;
		var vh = window.innerHeight || ROOT.clientHeight;
		var values = [];
		// Reads…
		for ( var i = 0; i < active.length; i++ ) {
			var r = active[ i ].getBoundingClientRect();
			var p = ( vh - r.top ) / ( vh + r.height );
			values.push( Math.max( 0, Math.min( 1, p ) ).toFixed( 4 ) );
		}
		// …then writes.
		for ( var j = 0; j < active.length; j++ ) {
			active[ j ].style.setProperty( '--ap-cc-p', values[ j ] );
		}
	}

	function schedule() {
		if ( ! queued && active.length ) {
			queued = true;
			window.requestAnimationFrame( frame );
		}
	}

	var observer = new IntersectionObserver(
		function ( entries ) {
			for ( var i = 0; i < entries.length; i++ ) {
				var track = entries[ i ].target;
				var at = active.indexOf( track );
				if ( entries[ i ].isIntersecting ) {
					if ( at < 0 ) {
						active.push( track );
					}
				} else if ( at >= 0 ) {
					active.splice( at, 1 );
				}
			}
			schedule();
		},
		{ rootMargin: '12% 0px' }
	);

	var listening = false;

	function init() {
		var tracks = document.querySelectorAll( '.ap-continuity-columns [data-ap-cc-beam]' );
		for ( var i = 0; i < tracks.length; i++ ) {
			var track = tracks[ i ];
			if ( track.hasAttribute( 'data-ap-cc-beam-js' ) || cssDrives( track ) ) {
				continue;
			}
			track.setAttribute( 'data-ap-cc-beam-js', '' );
			observer.observe( track );
			if ( ! listening ) {
				listening = true;
				window.addEventListener( 'scroll', schedule, { passive: true } );
				window.addEventListener( 'resize', schedule );
			}
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
