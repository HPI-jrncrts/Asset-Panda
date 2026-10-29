/*
 * Asset Panda UI — motion runtime.
 *
 * ONE IntersectionObserver for the whole document. Components never write
 * observer code; they only mark up elements with data-ap-reveal (see
 * motion.css for the attribute vocabulary and the safety contract).
 *
 * It also animates <details> accordions that opt in with data-ap-accordion
 * (the FAQ components): one delegated click listener for the whole document.
 *
 * Rules this file exists to honour:
 *   - Delegated. Keys off [data-ap-reveal] / [data-ap-accordion]; knows
 *     nothing about components.
 *   - Idempotent. Safe to load twice and safe with N instances on a page.
 *   - No layout thrash. Never reads geometry in a scroll handler — the
 *     observer does the measuring, off the main thread. The accordion measures
 *     once per click, never per frame.
 *   - Fails visible. If anything here throws, .ap-motion is never set (or is
 *     removed), and motion.css leaves every element at its visible rest state;
 *     an accordion that fails to animate simply opens or closes.
 */
( function () {
	'use strict';

	var ROOT = document.documentElement;
	// Section reveals are off on the solution, hub, product, assist, Ursa/AIP and readiness page families
	// (QA 2026-10-29, Joseph: "remove the scroll behavior on the sections", every hub and solution page).
	// motion.css retimes their scroll-linked entrances; here every one-shot reveal is stripped and the
	// automatic planner stays out. Pages led by the homepage hero keep their motion.
	var NOREVEAL = !! document.querySelector( '.ap-solution-hero, .ap-hub-hero, .ap-assist-hero, .ap-orbit-hero, .ap-vhero, .ap-readiness-hero' );
	if ( NOREVEAL ) {
		ROOT.classList.add( 'ap-noreveal' );
	}
	function stripReveals( scope ) {
		var nodes = scope.querySelectorAll ? scope.querySelectorAll( '[data-ap-reveal]' ) : [];
		for ( var i = 0; i < nodes.length; i++ ) {
			nodes[ i ].removeAttribute( 'data-ap-reveal' );
			nodes[ i ].classList.add( 'ap-reveal-done' );
		}
		if ( scope.nodeType === 1 && scope.hasAttribute && scope.hasAttribute( 'data-ap-reveal' ) ) {
			scope.removeAttribute( 'data-ap-reveal' );
			scope.classList.add( 'ap-reveal-done' );
		}
	}

	// Idempotence: a second copy of this script must do nothing.
	if ( ROOT.hasAttribute( 'data-ap-motion-ready' ) ) {
		return;
	}
	ROOT.setAttribute( 'data-ap-motion-ready', '' );

	// Respect the user's OS setting. Without .ap-motion, motion.css never
	// applies a hidden pre-state, so everything simply renders at rest; and
	// accordions keep the browser's own instant open/close.
	var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' );
	// Under reduced motion the script still classifies the green outline CTAs (so they fill green with white
	// text on hover, instantly — QA 2026-10-29), then stops before any animation is wired.
	var REDUCED = !! ( reduced && reduced.matches );

	/* ---- CTA hover: highlight + edge refraction (QA 2026-10-29, Joseph) ----
	 * Replaces the liquid fill. Finds every CTA by what it looks like — a green outline (green text on a green
	 * border) or the brand gradient — and tags it .ap-cta-outline / .ap-cta-grad. motion.css gives both the plain
	 * Bootstrap-style highlight (dev.to #7: the outline fills green with white text, the gradient deepens), and the
	 * script adds one SVG per button holding a shade layer and a light that runs once around the button's edge in the
	 * brand colours on hover and back on leave (dev.to #10 "Button N° 045": a dashed stroke whose offset moves the
	 * dash from before the path's start to past its end). The SVG sits under the label (z-index -1 in the button's own
	 * stacking context) and is sized to the button in pixels, so the corner radius stays round.
	 * Under reduced motion the buttons are tagged (for the instant highlight) but get no SVG.
	 */
	var CTA_SEL = 'a[class*="btn"], button[class*="btn"], a[class*="cta"], a[class*="-free"], a[class*="-demo"], a[class*="-sales"]';
	var SVGNS = 'http://www.w3.org/2000/svg';

	function ctaKind( el ) {
		var cs = getComputedStyle( el );
		var bg = cs.backgroundImage || '';
		var border = cs.borderTopColor;
		var fill = cs.backgroundColor;
		var green = function ( c ) {
			return c === 'rgb(0, 169, 145)' || c === 'rgb(0, 128, 110)';
		};
		// Green text on a green border is an outline CTA even when its stylesheet parks a gradient image for its own
		// hover (the header Free Demo does).
		if ( green( border ) && green( cs.color ) && ! green( fill ) ) {
			return 'outline';
		}
		if ( bg.indexOf( 'linear-gradient' ) !== -1 && ( bg.indexOf( '80, 68, 225' ) !== -1 || bg.indexOf( '0, 169, 145' ) !== -1 ) ) {
			return 'grad';
		}
		if ( green( border ) && bg === 'none' && ! green( fill ) ) {
			return 'outline';
		}
		return '';
	}

	function ctaSetup() {
		if ( document.getElementById( 'ap-cta-defs' ) ) {
			return;
		}
		var svg = document.createElementNS( SVGNS, 'svg' );
		svg.setAttribute( 'id', 'ap-cta-defs' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );
		svg.setAttribute( 'style', 'position:absolute;width:0;height:0;overflow:hidden' );
		svg.innerHTML = '<defs><linearGradient id="ap-cta-ray" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#a5a0ff"/><stop offset=".35" stop-color="#6c63ff"/><stop offset=".55" stop-color="#ffffff"/><stop offset=".75" stop-color="#3fe0c5"/><stop offset="1" stop-color="#00a991"/></linearGradient></defs>';
		document.body.appendChild( svg );
	}

	// Size each button's SVG to the button in pixels (viewBox = box), so the rect's corner radius matches.
	function ctaFit( el ) {
		var svg = el.querySelector( ':scope > .ap-cta-fx' );
		if ( ! svg ) {
			return;
		}
		var w = el.offsetWidth;
		var h = el.offsetHeight;
		if ( ! w || ! h ) {
			return;
		}
		var cs = getComputedStyle( el );
		var r = Math.min( parseFloat( cs.borderTopLeftRadius ) || 0, h / 2 );
		svg.setAttribute( 'viewBox', '0 0 ' + w + ' ' + h );
		var rects = svg.querySelectorAll( 'rect' );
		for ( var i = 0; i < rects.length; i++ ) {
			var inset = rects[ i ].classList.contains( 'ap-cta-fx__ray' ) ? 1 : 0;
			rects[ i ].setAttribute( 'x', inset );
			rects[ i ].setAttribute( 'y', inset );
			rects[ i ].setAttribute( 'width', Math.max( 0, w - 2 * inset ) );
			rects[ i ].setAttribute( 'height', Math.max( 0, h - 2 * inset ) );
			rects[ i ].setAttribute( 'rx', Math.max( 0, r - inset ) );
		}
		// The ray's dash and travel are set in pixels from the measured perimeter (pathLength on a rect is not
		// honoured everywhere).
		var ray = svg.querySelector( '.ap-cta-fx__ray' );
		var rw = Math.max( 0, w - 2 ), rh = Math.max( 0, h - 2 ), rr = Math.max( 0, r - 1 );
		var per = 2 * ( rw + rh ) - ( 8 - 2 * Math.PI ) * Math.min( rr, rw / 2, rh / 2 );
		try {
			per = ray.getTotalLength() || per;
		} catch ( err ) {
			// keep the formula
		}
		svg.style.setProperty( '--ap-cta-p', per.toFixed( 1 ) + 'px' );
	}
	var ctaRO = typeof window.ResizeObserver === 'function' ? new ResizeObserver( function ( entries ) {
		for ( var i = 0; i < entries.length; i++ ) {
			ctaFit( entries[ i ].target );
		}
	} ) : null;

	function cta( scope ) {
		var nodes = scope.querySelectorAll ? scope.querySelectorAll( CTA_SEL ) : [];
		for ( var i = 0; i < nodes.length; i++ ) {
			var el = nodes[ i ];
			if ( el.hasAttribute( 'data-ap-cta' ) ) {
				continue;
			}
			var kind = ctaKind( el );
			el.setAttribute( 'data-ap-cta', kind || 'none' );
			if ( ! kind ) {
				continue;
			}
			el.classList.add( 'ap-cta-' + kind );
			if ( REDUCED ) {
				continue;
			}
			var ecs = getComputedStyle( el );
			if ( ecs.position === 'static' ) {
				el.style.position = 'relative';
			}
			el.style.setProperty( '--ap-cta-bw', ecs.borderTopWidth );
			var svg = document.createElementNS( SVGNS, 'svg' );
			svg.setAttribute( 'class', 'ap-cta-fx' );
			svg.setAttribute( 'aria-hidden', 'true' );
			svg.setAttribute( 'focusable', 'false' );
			svg.setAttribute( 'preserveAspectRatio', 'none' );
			svg.innerHTML = '<rect class="ap-cta-fx__shade"/><rect class="ap-cta-fx__ray"/>';
			el.appendChild( svg );
			ctaFit( el );
			if ( ctaRO ) {
				ctaRO.observe( el );
			}
		}
	}

	// A button can change look after load: the header's Free Demo is the gradient primary for US visitors and turns
	// into a green outline when the geo lookup adds html.ap-intl. When the root's classes change, any button whose
	// kind no longer matches is untagged and classified again.
	function uncta( el ) {
		var fx = el.querySelector( ':scope > .ap-cta-fx' );
		if ( fx ) {
			if ( ctaRO ) {
				ctaRO.unobserve( el );
			}
			el.removeChild( fx );
		}
		el.classList.remove( 'ap-cta-grad', 'ap-cta-outline' );
		el.removeAttribute( 'data-ap-cta' );
	}
	var ctaRecheckPending = false;
	function ctaRecheck() {
		ctaRecheckPending = false;
		var done = document.querySelectorAll( '[data-ap-cta]' );
		for ( var r = 0; r < done.length; r++ ) {
			var el = done[ r ];
			var was = el.getAttribute( 'data-ap-cta' );
			// Measured without the tag classes, so the stylesheet's own look decides.
			var tagged = el.classList.contains( 'ap-cta-outline' ) ? 'ap-cta-outline' : ( el.classList.contains( 'ap-cta-grad' ) ? 'ap-cta-grad' : '' );
			if ( tagged ) {
				el.classList.remove( tagged );
			}
			var now = ctaKind( el ) || 'none';
			if ( tagged ) {
				el.classList.add( tagged );
			}
			if ( now !== was ) {
				uncta( el );
				cta( el.parentNode || document );
			}
		}
	}

	try {
		if ( ! REDUCED ) {
			ctaSetup();
		}
		cta( document );
		if ( typeof window.MutationObserver === 'function' ) {
			new MutationObserver( function () {
				if ( ! ctaRecheckPending ) {
					ctaRecheckPending = true;
					( window.requestAnimationFrame || setTimeout )( ctaRecheck );
				}
			} ).observe( ROOT, { attributes: true, attributeFilter: [ 'class' ] } );
		}
	} catch ( e ) {
		// A failure here leaves every button on its component's own hover.
	}
	if ( REDUCED ) {
		return;
	}

	/* ---- Accordion ----
	 * <div data-ap-accordion> around <details> rows. Without this script the
	 * rows still open and close natively (instantly). With it, a click on a
	 * row's <summary> animates the row's height from where it is to where it is
	 * going, fades the answer in or out on the same curve, and keeps exclusive
	 * groups (<details name>) exclusive by closing the open sibling on the same
	 * animation. While a row closes it carries data-ap-closing, so component CSS
	 * can drop the open look at the start of the motion instead of the end.
	 * Duration and easing come from the motion tokens, so the height, the
	 * chevron and the colour all move together. A second click mid-animation
	 * reverses from the current height — no jump.
	 */
	function accordions() {
		if ( typeof Element.prototype.animate !== 'function' || ! Element.prototype.closest ) {
			return;
		}

		var timing = null;

		function toMs( value, fallback ) {
			var n = parseFloat( value );
			if ( ! isFinite( n ) ) {
				return fallback;
			}
			return /ms\s*$/.test( value ) ? n : n * 1000;
		}

		function getTiming() {
			if ( ! timing ) {
				var cs = getComputedStyle( ROOT );
				timing = {
					duration: toMs( cs.getPropertyValue( '--ap-dur-base' ), 300 ),
					easing: ( cs.getPropertyValue( '--ap-ease-standard' ) || '' ).trim() || 'ease',
				};
			}
			return timing;
		}

		function px( v ) {
			return parseFloat( v ) || 0;
		}

		function summaryOf( row ) {
			for ( var c = row.firstElementChild; c; c = c.nextElementSibling ) {
				if ( c.nodeName === 'SUMMARY' ) {
					return c;
				}
			}
			return null;
		}

		function isOpen( row ) {
			return row.open && ! row.hasAttribute( 'data-ap-closing' );
		}

		// Exclusive groups are managed here once the script runs, so the browser's
		// own <details name> exclusivity cannot snap a sibling shut mid-animation.
		function claim( list ) {
			if ( list.__apAccClaimed ) {
				return;
			}
			list.__apAccClaimed = true;
			var named = list.querySelectorAll( 'details[name]' );
			for ( var i = 0; i < named.length; i++ ) {
				named[ i ].setAttribute( 'data-ap-group', named[ i ].getAttribute( 'name' ) );
				named[ i ].removeAttribute( 'name' );
			}
		}

		function settle( row, state ) {
			if ( row.__apAcc !== state ) {
				return; // superseded by a later click
			}
			if ( ! state.open ) {
				row.open = false;
				row.removeAttribute( 'data-ap-closing' );
			}
			row.style.overflow = '';
			row.style.boxSizing = '';
			state.h.cancel();
			for ( var i = 0; i < state.f.length; i++ ) {
				state.f[ i ].cancel();
			}
			row.__apAcc = null;
		}

		function animate( row, open ) {
			var t = getTiming();
			var summary = summaryOf( row );
			var body = [];
			for ( var c = row.firstElementChild; c; c = c.nextElementSibling ) {
				if ( c !== summary ) {
					body.push( c );
				}
			}
			var prev = row.__apAcc;
			var from = row.getBoundingClientRect().height;
			var fromOpacity = open ? 0 : 1;
			if ( prev ) {
				if ( body[ 0 ] ) {
					fromOpacity = parseFloat( getComputedStyle( body[ 0 ] ).opacity );
				}
				prev.h.cancel();
				for ( var p = 0; p < prev.f.length; p++ ) {
					prev.f[ p ].cancel();
				}
			}

			var to;
			if ( open ) {
				row.removeAttribute( 'data-ap-closing' );
				row.open = true;
				to = row.getBoundingClientRect().height;
			} else {
				row.setAttribute( 'data-ap-closing', '' );
				var cs = getComputedStyle( row );
				to = ( summary ? summary.getBoundingClientRect().height : 0 ) +
					px( cs.borderTopWidth ) + px( cs.borderBottomWidth ) +
					px( cs.paddingTop ) + px( cs.paddingBottom );
			}

			row.style.overflow = 'hidden';
			row.style.boxSizing = 'border-box';
			var state = {
				open: open,
				h: row.animate( { height: [ from + 'px', to + 'px' ] }, { duration: t.duration, easing: t.easing } ),
				f: [],
			};
			for ( var b = 0; b < body.length; b++ ) {
				state.f.push( body[ b ].animate( { opacity: [ fromOpacity, open ? 1 : 0 ] }, { duration: t.duration, easing: t.easing, fill: 'forwards' } ) );
			}
			row.__apAcc = state;
			state.h.onfinish = function () {
				settle( row, state );
			};
		}

		document.addEventListener( 'click', function ( event ) {
			if ( event.defaultPrevented || event.button > 0 || ! event.target || ! event.target.closest ) {
				return;
			}
			var summary = event.target.closest( 'summary' );
			if ( ! summary ) {
				return;
			}
			var link = event.target.closest( 'a' );
			if ( link && summary.contains( link ) ) {
				return;
			}
			var row = summary.parentNode;
			if ( ! row || row.nodeName !== 'DETAILS' || summaryOf( row ) !== summary ) {
				return;
			}
			var list = row.closest( '[data-ap-accordion]' );
			if ( ! list ) {
				return;
			}
			event.preventDefault();
			var open = ! isOpen( row );
			try {
				claim( list );
				if ( open && row.hasAttribute( 'data-ap-group' ) ) {
					var group = row.getAttribute( 'data-ap-group' );
					var rows = list.querySelectorAll( 'details[data-ap-group]' );
					for ( var i = 0; i < rows.length; i++ ) {
						if ( rows[ i ] !== row && rows[ i ].getAttribute( 'data-ap-group' ) === group && isOpen( rows[ i ] ) ) {
							animate( rows[ i ], false );
						}
					}
				}
				animate( row, open );
			} catch ( e ) {
				// Never leave a row stuck: fall back to the plain toggle.
				row.removeAttribute( 'data-ap-closing' );
				row.style.overflow = '';
				row.style.boxSizing = '';
				row.open = open;
			}
		} );
	}

	try {
		accordions();
	} catch ( e ) {
		// The rows keep their native, instant open/close.
	}

	/* ---- Scroll-in for every section (QA 2026-10-28) ----
	 * Only a few components carried data-ap-reveal, so most sections sat still
	 * while the page scrolled. This picks the content blocks of every kit
	 * section below the first screen — the section's heading block and, where a
	 * row holds three or more cards, each card — and lets them rise into place.
	 * Where the browser supports scroll-driven animations they move WITH the
	 * scroll (motion.css [data-ap-sr]: a short, staggered entry range, so it
	 * reads as smooth rather than as a late pop); elsewhere they fall back to
	 * the one-shot reveal below. Never touched: heroes, header/footer, sliders,
	 * widgets, anything already animated or positioned, or already on screen.
	 */
	// Scroll-LINKED entrances are off (QA 2026-10-28 PM): tied to the scroll
	// position, anything in the bottom ~220px of the screen — and everything
	// inside a sticky column — sat half-transparent on screen and read as broken
	// buttons, stats and cards. Every block now uses the one-shot reveal below:
	// it starts 24px into view and always finishes fully visible.
	var scrollDriven = false;

	function inSticky( el ) {
		for ( var a = el.parentElement; a && a !== document.body; a = a.parentElement ) {
			if ( getComputedStyle( a ).position === 'sticky' ) {
				return true;
			}
		}
		return false;
	}

	var SKIP = /(^|\s)ap-(site-header|site-footer|page-toc|demo-booking|demo-confirm|login-splash|resource-search|story-slider|case-slider|case-studies|demo-stories|plans-picker|pricing-calculator|roi-calculator)\b|hero\b/;

	function eligible( el, vh ) {
		var box = el.getBoundingClientRect();
		if ( box.height < 16 || box.width < 16 || box.top < vh ) {
			return false;
		}
		var cs = getComputedStyle( el );
		return cs.display !== 'none' && cs.visibility !== 'hidden' &&
			cs.animationName === 'none' && cs.transform === 'none' &&
			( cs.position === 'static' || cs.position === 'relative' ) &&
			! el.hasAttribute( 'data-ap-sr' ) && ! inSticky( el );
	}

	function visibleKids( el ) {
		var out = [];
		for ( var c = el.firstElementChild; c; c = c.nextElementSibling ) {
			if ( c.offsetWidth > 0 && c.offsetHeight > 0 && c.nodeName !== 'SCRIPT' && c.nodeName !== 'STYLE' ) {
				out.push( c );
			}
		}
		return out;
	}

	function pickItems( root ) {
		var node = root;
		for ( var guard = 0; guard < 6 && visibleKids( node ).length === 1; guard++ ) {
			node = visibleKids( node )[ 0 ];
		}
		var out = [];
		var kids = visibleKids( node );
		for ( var i = 0; i < kids.length; i++ ) {
			var grand = visibleKids( kids[ i ] );
			var d = getComputedStyle( kids[ i ] ).display;
			if ( grand.length >= 3 && ( d.indexOf( 'grid' ) !== -1 || d.indexOf( 'flex' ) !== -1 ) ) {
				for ( var g = 0; g < grand.length; g++ ) {
					out.push( grand[ g ] );
				}
			} else {
				out.push( kids[ i ] );
			}
		}
		return out;
	}

	function autoReveal() {
		if ( NOREVEAL ) {
			stripReveals( document );
			return;
		}
		var main = document.querySelector( 'main' ) || document.querySelector( '.wp-site-blocks' );
		if ( ! main ) {
			return;
		}
		var vh = window.innerHeight || ROOT.clientHeight;
		var roots = [];
		( function walk( el ) {
			for ( var c = el.firstElementChild; c; c = c.nextElementSibling ) {
				var cls = typeof c.className === 'string' ? c.className : '';
				if ( /(^|\s)ap-[a-z0-9-]+/.test( cls ) ) {
					roots.push( c );
				} else if ( c.firstElementChild ) {
					walk( c );
				}
			}
		} )( main );

		// Reads first (all geometry and styles), then writes, so the pass costs one layout.
		var plan = [];
		for ( var r = 0; r < roots.length; r++ ) {
			if ( SKIP.test( roots[ r ].className ) ) {
				continue;
			}
			var items = pickItems( roots[ r ] );
			var n = 0;
			for ( var i = 0; i < items.length; i++ ) {
				if ( ! items[ i ].closest( '[data-ap-reveal]' ) && ! items[ i ].querySelector( '[data-ap-reveal]' ) && eligible( items[ i ], vh ) ) {
					plan.push( [ items[ i ], Math.min( n++, 5 ) ] );
				}
			}
		}
		// Component-authored reveals move with the scroll too, where they can.
		if ( scrollDriven ) {
			var authored = document.querySelectorAll( '[data-ap-reveal]' );
			for ( var a = 0; a < authored.length; a++ ) {
				if ( eligible( authored[ a ], vh ) ) {
					plan.push( [ authored[ a ], Math.min( parseInt( authored[ a ].getAttribute( 'data-ap-reveal-delay' ), 10 ) || 0, 5 ), true ] );
				}
			}
		}
		for ( var p = 0; p < plan.length; p++ ) {
			var el = plan[ p ][ 0 ];
			if ( scrollDriven ) {
				if ( plan[ p ][ 2 ] ) {
					el.removeAttribute( 'data-ap-reveal' );
				}
				el.setAttribute( 'data-ap-sr', '' );
				el.style.setProperty( '--ap-sr-i', String( plan[ p ][ 1 ] ) );
			} else if ( ! plan[ p ][ 2 ] ) {
				el.setAttribute( 'data-ap-reveal', '' );
				if ( plan[ p ][ 1 ] ) {
					el.setAttribute( 'data-ap-reveal-delay', String( plan[ p ][ 1 ] ) );
				}
			}
		}
		if ( scrollDriven ) {
			ROOT.classList.add( 'ap-sdriven' );
		}
	}

	try {
		autoReveal();
	} catch ( e ) {
		// Sections simply stay still.
	}

	// No IntersectionObserver (or an old engine)? Leave content visible.
	if ( typeof window.IntersectionObserver !== 'function' ) {
		return;
	}

	try {
		// Anything already on screen when this footer script runs is shown as it
		// is — hiding it now would flash it out and back in. One geometry read,
		// once, before the hidden pre-state exists.
		var first = document.querySelectorAll( '[data-ap-reveal]' );
		var vh = window.innerHeight || ROOT.clientHeight;
		for ( var f = 0; f < first.length; f++ ) {
			var box = first[ f ].getBoundingClientRect();
			if ( box.top < vh && box.bottom > 0 ) {
				first[ f ].classList.add( 'ap-inview', 'ap-reveal-done' );
				first[ f ].setAttribute( 'data-ap-observed', '' );
			}
		}

		ROOT.classList.add( 'ap-motion' );

		var done = function ( event ) {
			// transitionend bubbles: only the element's own opacity transition
			// ends its reveal. A child's hover transition finishing first used to
			// switch the reveal's transition off mid-flight and snap it.
			if ( event.target !== this || event.propertyName !== 'opacity' ) {
				return;
			}
			this.classList.add( 'ap-reveal-done' );
			this.removeEventListener( 'transitionend', done );
		};

		var reveal = function ( el ) {
			el.classList.add( 'ap-inview' );
			observer.unobserve( el );
			// Hand the element back to its component once the reveal has finished
			// (its own hover transitions resume). The timeout covers a reveal whose
			// transitionend never fires (hidden mid-way, display toggled).
			el.addEventListener( 'transitionend', done );
			setTimeout( function () {
				el.classList.add( 'ap-reveal-done' );
			}, 2000 );
		};

		var observer = new IntersectionObserver(
			function ( entries ) {
				var vh2 = window.innerHeight || ROOT.clientHeight;
				var group = [];
				for ( var i = 0; i < entries.length; i++ ) {
					var entry = entries[ i ];
					if ( ! entry.isIntersecting || entry.target.classList.contains( 'ap-inview' ) ) {
						continue;
					}
					var el = entry.target;
					// A staggered item brings its staggered siblings in with it when
					// they sit on screen vertically but are clipped sideways — logos in
					// a marquee or cards in a horizontal track never intersect on their
					// own and used to fade in one by one at the edge, or not at all.
					// Reads first (layout is clean inside an observer callback)...
					if ( el.hasAttribute( 'data-ap-reveal-delay' ) && el.parentNode ) {
						var sibs = el.parentNode.children;
						for ( var s = 0; s < sibs.length; s++ ) {
							var sib = sibs[ s ];
							if ( sib !== el && sib.hasAttribute( 'data-ap-reveal' ) && ! sib.classList.contains( 'ap-inview' ) ) {
								var r = sib.getBoundingClientRect();
								if ( r.top < vh2 && r.bottom > 0 ) {
									group.push( sib );
								}
							}
						}
					}
					group.push( el );
				}
				// ...then writes.
				for ( var g = 0; g < group.length; g++ ) {
					if ( ! group[ g ].classList.contains( 'ap-inview' ) ) {
						reveal( group[ g ] );
					}
				}
			},
			{
				// Start the moment an element's top edge crosses the bottom of
				// the viewport (less a hair, so a sliver doesn't trigger it), so
				// the reveal reads as part of the scroll rather than a late pop.
				rootMargin: '0px 0px -24px 0px',
				threshold: 0,
			}
		);

		function observe( scope ) {
			if ( NOREVEAL ) {
				stripReveals( scope );
				return;
			}
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
							if ( ! added[ j ].classList.contains( 'ap-cta-fx' ) && ! ( added[ j ].closest && added[ j ].closest( '.ap-cta-fx' ) ) ) {
								cta( added[ j ] );
							}
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
