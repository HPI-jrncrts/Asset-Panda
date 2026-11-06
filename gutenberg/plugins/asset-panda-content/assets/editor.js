/**
 * Asset Panda Content — the article editor (builder v1, 2026-11).
 *
 * Loaded only in the block editor for resources, press releases and posts
 * (includes/editor.php). Two jobs, both cosmetic — neither changes a saved
 * byte of the post:
 *
 * 1. Key content looks as it will on the page. On the front end
 *    callouts.php marks Key Takeaways / Quick Answer / FAQ blocks with
 *    classes the stylesheet hooks; here the same test adds the same classes
 *    to the block in the canvas (editor.BlockListBlock), so a writer sees the
 *    box while typing the label.
 * 2. The page header over the title: the type eyebrow ("Blog", "Case
 *    Studies"…) and the featured image where the article header shows it,
 *    drawn by editor.php's CSS from two custom properties set here on the
 *    canvas document.
 *
 * Buildless: global wp.* packages only.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.data || ! wp.element || ! wp.compose ) {
		return;
	}

	var CFG = window.AP_CONTENT_EDITOR || {};
	var el = wp.element.createElement;
	var useSelect = wp.data.useSelect;

	var LABEL = CFG.keyLabels ? new RegExp( '^\\s*(?:' + CFG.keyLabels + ')\\s*:?\\s*$', 'i' ) : null;
	var HEAD = CFG.keyHeads ? new RegExp( '^\\s*(?:' + CFG.keyHeads + ')\\s*:?\\s*$', 'i' ) : null;
	var FAQ = /\bFAQs?\b|^frequently\s+asked\s+questions\b/i;
	var LEAD = /^\s*<(strong|b)\b[^>]*>([\s\S]*?)<\/\1>/i;

	function plain( html ) {
		return String( html || '' )
			.replace( /<[^>]*>/g, ' ' )
			.replace( /&nbsp;/g, ' ' )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	/* Same tests as callouts.php: a bold label opening a paragraph or quote, a heading that is a label, an FAQ H2. */
	function leadsWithLabel( content ) {
		var m = LEAD.exec( String( content || '' ) );
		return !! ( m && LABEL && LABEL.test( plain( m[ 2 ] ) ) );
	}

	/*
	 * ctx is a string from the store (primitives only, so useSelect never sees a new object and the
	 * canvas never re-renders in a loop): for a paragraph its parent block's name, for a quote
	 * 'label' when its first paragraph opens with a bold label.
	 */
	function keyClass( name, attributes, ctx ) {
		if ( name === 'core/paragraph' ) {
			// A paragraph inside a marked quote box keeps the box's marking only (as callouts.php does).
			return ctx !== 'core/quote' && leadsWithLabel( attributes.content ) ? 'ap26-key ap26-key--para' : '';
		}
		if ( name === 'core/quote' ) {
			return ctx === 'label' ? 'ap26-key ap26-key--quote' : '';
		}
		if ( name === 'core/heading' ) {
			var text = plain( attributes.content );
			if ( HEAD && HEAD.test( text ) ) {
				return 'ap26-key__title ap26-key__head';
			}
			if ( ( attributes.level || 2 ) === 2 && text.length <= 120 && FAQ.test( text ) ) {
				return 'ap26-faq__head';
			}
		}
		return '';
	}

	var withKeyClasses = wp.compose.createHigherOrderComponent( function ( BlockListBlock ) {
		return function ( props ) {
			var ctx = useSelect(
				function ( select ) {
					var store = select( 'core/block-editor' );
					if ( props.name === 'core/paragraph' ) {
						var parent = store.getBlockRootClientId( props.clientId );
						return parent ? store.getBlockName( parent ) || '' : '';
					}
					if ( props.name === 'core/quote' ) {
						var first = store.getBlockOrder( props.clientId )[ 0 ];
						if ( first && store.getBlockName( first ) === 'core/paragraph' ) {
							return leadsWithLabel( ( store.getBlockAttributes( first ) || {} ).content ) ? 'label' : '';
						}
					}
					return '';
				},
				[ props.name, props.clientId ]
			);
			var cls = keyClass( props.name, props.attributes || {}, ctx );
			if ( ! cls ) {
				return el( BlockListBlock, props );
			}
			return el( BlockListBlock, Object.assign( {}, props, { className: [ props.className, cls ].filter( Boolean ).join( ' ' ) } ) );
		};
	}, 'apContentKeyClasses' );

	wp.hooks.addFilter( 'editor.BlockListBlock', 'asset-panda-content/key-classes', withKeyClasses );

	/* ---- The page header over the title ---------------------------------- */

	var lastDoc = null;
	var lastKey = '';

	function canvas() {
		var frame = document.querySelector( 'iframe[name="editor-canvas"]' );
		if ( frame && frame.contentDocument && frame.contentDocument.body ) {
			return frame.contentDocument;
		}
		// Not iframed (an older editor): the canvas is this document.
		return document.querySelector( '.editor-styles-wrapper' ) ? document : null;
	}

	function decodeName( s ) {
		var t = document.createElement( 'textarea' );
		t.innerHTML = String( s || '' );
		return t.value;
	}

	function headerState() {
		var editor = wp.data.select( 'core/editor' );
		var core = wp.data.select( 'core' );
		if ( ! editor || ! core ) {
			return { url: '', eyebrow: '' };
		}
		var url = '';
		var media = editor.getEditedPostAttribute( 'featured_media' );
		if ( media ) {
			var m = core.getEntityRecord( 'postType', 'attachment', media );
			if ( m ) {
				var sizes = ( m.media_details && m.media_details.sizes ) || {};
				var pick = sizes.large || sizes.full;
				url = ( pick && pick.source_url ) || m.source_url || '';
			}
		}
		var eyebrow = CFG.postLabel || '';
		if ( CFG.postType === 'resource' ) {
			var tax = core.getTaxonomy && core.getTaxonomy( 'resource-types' );
			var field = ( tax && tax.rest_base ) || 'resource-types';
			var ids = editor.getEditedPostAttribute( field ) || [];
			if ( ids.length ) {
				var term = core.getEntityRecord( 'taxonomy', 'resource-types', ids[ 0 ] );
				if ( term && term.name ) {
					eyebrow = decodeName( term.name );
				}
			}
		}
		return { url: url, eyebrow: eyebrow };
	}

	function paint() {
		var doc = canvas();
		if ( ! doc ) {
			return;
		}
		var state = headerState();
		var key = state.url + '|' + state.eyebrow;
		if ( doc === lastDoc && key === lastKey ) {
			return;
		}
		lastDoc = doc;
		lastKey = key;
		var root = doc.documentElement;
		root.classList.toggle( 'ap-ed-featured', !! state.url );
		root.classList.toggle( 'ap-ed-eyebrow', !! state.eyebrow );
		if ( state.url ) {
			root.style.setProperty( '--ap-ed-featured', 'url("' + state.url.replace( /["\\\n\r]/g, '' ) + '")' );
		} else {
			root.style.removeProperty( '--ap-ed-featured' );
		}
		if ( state.eyebrow ) {
			root.style.setProperty( '--ap-ed-eyebrow', JSON.stringify( state.eyebrow ) );
		} else {
			root.style.removeProperty( '--ap-ed-eyebrow' );
		}
	}

	var queued = false;
	wp.data.subscribe( function () {
		if ( queued ) {
			return;
		}
		queued = true;
		window.requestAnimationFrame( function () {
			queued = false;
			paint();
		} );
	} );
	// The canvas iframe is rebuilt when the preview device changes; repaint it without waiting for a store change.
	window.setInterval( paint, 2000 );
} )( window.wp );
