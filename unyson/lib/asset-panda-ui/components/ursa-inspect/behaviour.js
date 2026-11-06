/**
 * ursa-inspect behaviour — the live Ursa Inspection demo in its card.
 *
 * The demo (vendor bundle DUfgs-Z1, 2026-10-28) posts to its parent:
 *   { source: 'ursa-inspection-demo', type: 'resize', height } — from a
 *     ResizeObserver on its own #demo-root, so the height is the content's,
 *     never the frame's (no feedback loop);
 *   { source: 'ursa-inspection-demo', type: 'event', name: 'ready', ... } and
 *     its other engagement events.
 * One delegated message listener matches a message to its frame by
 * event.source, checks the sender's origin against the frame's own src, then:
 *
 * 1. Live (QA 2026-10-29). The first message proves the demo runs in that
 *    frame: the frame loses `inert` and its media block gets --live, which
 *    hides the poster card and the kit's tag (the demo draws its own). Where
 *    the demo's host refuses to be framed (its CSP allows assetpanda.com only)
 *    no message ever comes, so the poster card simply stays; nothing else
 *    reveals the frame, so a refused frame's error page is never uncovered.
 * 2. Height. Each resize message sets that frame's height, so the frame grows
 *    with the demo's content (results included) and never scrolls inside.
 *    Before the first message, or without this script, the frame keeps its CSS
 *    height (the demo's resting height).
 *
 * Keyed off [data-ap-ursa-inspect]; safe to load twice and with several frames.
 * No geometry is read.
 */
( function () {
	'use strict';

	if ( window.__apUrsaInspect ) {
		return;
	}
	window.__apUrsaInspect = true;

	function frames() {
		return document.querySelectorAll( '[data-ap-ursa-inspect] iframe.ap-ursa-inspect__demo' );
	}

	function originOf( frame ) {
		try {
			return new URL( frame.getAttribute( 'src' ), window.location.href ).origin;
		} catch ( e ) {
			return '';
		}
	}

	function live( frame ) {
		if ( frame.hasAttribute( 'inert' ) ) {
			frame.removeAttribute( 'inert' );
		}
		var media = frame.closest ? frame.closest( '.ap-ursa-inspect__media' ) : null;
		if ( media && ! media.classList.contains( 'ap-ursa-inspect__media--live' ) ) {
			media.classList.add( 'ap-ursa-inspect__media--live' );
		}
	}

	window.addEventListener( 'message', function ( event ) {
		var data = event.data;
		if ( ! data || 'object' !== typeof data || 'ursa-inspection-demo' !== data.source ) {
			return;
		}
		var list = frames();
		for ( var i = 0; i < list.length; i++ ) {
			var frame = list[ i ];
			if ( frame.contentWindow !== event.source || originOf( frame ) !== event.origin ) {
				continue;
			}
			if ( 'resize' === data.type ) {
				var height = Number( data.height );
				if ( isFinite( height ) && height >= 200 && height <= 3000 ) {
					frame.style.height = Math.ceil( height ) + 'px';
				}
			}
			live( frame );
			return;
		}
	} );
}() );
