/*
 * Asset Panda UI — motion runtime.
 *
 * ONE IntersectionObserver for the whole document. Components never write
 * observer code; they only mark up elements with data-ap-reveal (see
 * motion.css for the attribute vocabulary and the safety contract).
 *
 * Rules this file exists to honour:
 *   - Delegated. Keys off [data-ap-reveal]; knows nothing about components.
 *   - Idempotent. Safe to load twice and safe with N instances on a page.
 *   - No layout thrash. Never reads geometry in a scroll handler — the
 *     observer does the measuring, off the main thread.
 *   - Fails visible. If anything here throws, .ap-motion is never set (or is
 *     removed), and motion.css leaves every element at its visible rest state.
 */
( function () {
	'use strict';

	var ROOT = document.documentElement;

	// Idempotence: a second copy of this script must do nothing.
	if ( ROOT.hasAttribute( 'data-ap-motion-ready' ) ) {
		return;
	}
	ROOT.setAttribute( 'data-ap-motion-ready', '' );

	// Respect the user's OS setting. Without .ap-motion, motion.css never
	// applies a hidden pre-state, so everything simply renders at rest.
	var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' );
	if ( reduced && reduced.matches ) {
		return;
	}

	// No IntersectionObserver (or an old engine)? Leave content visible.
	if ( typeof window.IntersectionObserver !== 'function' ) {
		return;
	}

	try {
		ROOT.classList.add( 'ap-motion' );

		var observer = new IntersectionObserver(
			function ( entries ) {
				for ( var i = 0; i < entries.length; i++ ) {
					var entry = entries[ i ];
					if ( ! entry.isIntersecting ) {
						continue;
					}
					var el = entry.target;
					el.classList.add( 'ap-inview' );
					observer.unobserve( el );

					// Drop will-change once the transition has finished, so a
					// long page doesn't hold dozens of promoted layers.
					el.addEventListener(
						'transitionend',
						function () {
							this.classList.add( 'ap-reveal-done' );
						},
						{ once: true }
					);
				}
			},
			{
				// Fire slightly before the element's top edge arrives, so the
				// reveal reads as part of the scroll rather than a late pop.
				rootMargin: '0px 0px -10% 0px',
				threshold: 0.01,
			}
		);

		function observe( scope ) {
			var nodes = scope.querySelectorAll( '[data-ap-reveal]' );
			for ( var i = 0; i < nodes.length; i++ ) {
				var el = nodes[ i ];
				if ( el.hasAttribute( 'data-ap-observed' ) ) {
					continue;
				}
				el.setAttribute( 'data-ap-observed', '' );
				observer.observe( el );
			}
		}

		observe( document );

		// Pick up markup injected after load — block-editor previews, tabs and
		// carousels that mount their panels lazily. Attribute filtering keeps
		// this cheap; the data-ap-observed guard keeps it idempotent.
		if ( typeof window.MutationObserver === 'function' ) {
			new MutationObserver( function ( records ) {
				for ( var i = 0; i < records.length; i++ ) {
					var added = records[ i ].addedNodes;
					for ( var j = 0; j < added.length; j++ ) {
						if ( added[ j ].nodeType === 1 ) {
							observe( added[ j ] );
							if ( added[ j ].hasAttribute( 'data-ap-reveal' ) &&
								! added[ j ].hasAttribute( 'data-ap-observed' ) ) {
								added[ j ].setAttribute( 'data-ap-observed', '' );
								observer.observe( added[ j ] );
							}
						}
					}
				}
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	} catch ( e ) {
		// Any failure: revert to the visible-at-rest state and stay quiet.
		ROOT.classList.remove( 'ap-motion' );
	}
} )();
