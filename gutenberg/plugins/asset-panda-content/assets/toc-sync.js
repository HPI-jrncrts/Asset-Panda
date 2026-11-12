/**
 * Asset Panda Content — the "Table of contents" block follows the headings (1.3.26).
 *
 * Joseph, 2026-11-10: "make sure the table of contents updates according to
 * the content update on the editor itself, instead of updating it manually."
 *
 * Whenever the article's headings change, the block's list is rebuilt from
 * its H2 headings (their words as they read now), so the block in the editor
 * shows what the page will show. Entries pointing at something that is not a
 * heading (the FAQ section, a summary box) are kept. The page itself rebuilds
 * the list again when it is drawn (includes/listicle.php), so it is right even
 * for articles never opened here. Rebuilding never marks the post as changed.
 */
( function ( wp ) {
	if ( ! wp || ! wp.data || ! wp.hooks || ! wp.element || ! wp.compose ) {
		return;
	}
	var data = wp.data;
	var el = wp.element.createElement;
	var TOC = 'assetpanda/page-toc';
	var timer = null;
	var lastBlocks = null;

	function html( v ) {
		if ( v == null ) {
			return '';
		}
		return typeof v === 'string' ? v : ( typeof v.toHTMLString === 'function' ? v.toHTMLString() : String( v ) );
	}
	function plain( h ) {
		var t = document.createElement( 'textarea' );
		t.innerHTML = String( h || '' ).replace( /<[^>]*>/g, '' );
		return t.value.replace( /\s+/g, ' ' ).trim();
	}
	// Close to WordPress's sanitize_title, which the page uses for headings without an anchor.
	function slug( text ) {
		var s = String( text ).toLowerCase();
		if ( s.normalize ) {
			s = s.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' );
		}
		return s.replace( /&[a-z]+;/g, '' ).replace( /[^a-z0-9_\s-]/g, '' ).trim().replace( /[\s-]+/g, '-' ) || 'section';
	}
	// "1. Asset Panda — Best Overall …" → "1. Asset Panda", as on the page.
	function label( text ) {
		var m = text.match( /^#?\s*(\d{1,2})\s*[:.)–—-]\s*(.+)$/ );
		if ( m ) {
			var rest = m[ 2 ].trim();
			var sep = rest.match( /^(.*?)(?:\s+[–—-]\s+|:\s+)(.*)$/ );
			var name = sep && sep[ 2 ].trim() ? sep[ 1 ].trim() : rest;
			if ( name && name.length <= 60 ) {
				return m[ 1 ] + '. ' + name;
			}
		}
		return text.length > 60 ? text.slice( 0, 57 ).trim() + '…' : text;
	}

	function flatten( blocks, out ) {
		blocks.forEach( function ( b ) {
			out.push( b );
			if ( b.innerBlocks && b.innerBlocks.length ) {
				flatten( b.innerBlocks, out );
			}
		} );
		return out;
	}

	function itemsFor( all, current ) {
		var items = [];
		var seen = {};
		var where = {}; // anchor → position in the article
		all.forEach( function ( b, i ) {
			if ( b.attributes && typeof b.attributes.anchor === 'string' && b.attributes.anchor ) {
				where[ b.attributes.anchor ] = i;
			}
			if ( b.name !== 'core/heading' || ( b.attributes.level || 2 ) !== 2 ) {
				return;
			}
			var text = plain( html( b.attributes.content ) );
			if ( ! text ) {
				return;
			}
			var anchor = b.attributes.anchor || slug( text );
			var base = anchor;
			for ( var k = 2; seen[ anchor ]; k++ ) {
				anchor = base + '-' + k;
			}
			seen[ anchor ] = true;
			items.push( { label: label( text ), anchor: anchor, at: i } );
		} );
		// The block's own entries that point at another part of the article stay, at that part: a block with that
		// anchor, or the FAQ section (its entry kept where the FAQ block is). Entries pointing nowhere go, as on the page.
		var faqAt = -1;
		all.some( function ( b, i ) {
			if ( /faq/.test( b.name ) ) {
				faqAt = i;
				return true;
			}
			return false;
		} );
		( current || [] ).forEach( function ( item ) {
			if ( ! item || ! item.anchor || ! item.label || seen[ item.anchor ] ) {
				return;
			}
			var at = where[ item.anchor ];
			if ( at === undefined && faqAt >= 0 && ( /faq/i.test( item.anchor ) || /question/i.test( item.label ) ) ) {
				at = faqAt;
			}
			if ( at === undefined ) {
				return;
			}
			seen[ item.anchor ] = true;
			items.push( { label: item.label, anchor: item.anchor, at: at } );
		} );
		items.sort( function ( a, b ) {
			return a.at - b.at;
		} );
		return items.map( function ( item ) {
			return { label: item.label, anchor: item.anchor };
		} );
	}

	function sync() {
		var be = data.select( 'core/block-editor' );
		if ( ! be ) {
			return;
		}
		var all = flatten( be.getBlocks(), [] );
		var tocs = all.filter( function ( b ) {
			return b.name === TOC;
		} );
		if ( ! tocs.length ) {
			return;
		}
		var current = tocs[ 0 ].attributes.items || [];
		var next = itemsFor( all, current );
		if ( next.length < 2 || JSON.stringify( next ) === JSON.stringify( current ) ) {
			return;
		}
		var act = data.dispatch( 'core/block-editor' );
		if ( typeof act.__unstableMarkNextChangeAsNotPersistent === 'function' ) {
			act.__unstableMarkNextChangeAsNotPersistent();
		}
		act.updateBlockAttributes( tocs[ 0 ].clientId, { items: next } );
	}

	data.subscribe( function () {
		var be = data.select( 'core/block-editor' );
		if ( ! be ) {
			return;
		}
		var blocks = be.getBlocks();
		if ( blocks === lastBlocks ) {
			return;
		}
		lastBlocks = blocks;
		clearTimeout( timer );
		timer = setTimeout( sync, 700 );
	} );

	// In the block's settings: say that it fills itself.
	var C = wp.components || {};
	var Inspector = wp.blockEditor && wp.blockEditor.InspectorControls;
	if ( Inspector && C.PanelBody ) {
		wp.hooks.addFilter(
			'editor.BlockEdit',
			'asset-panda-content/toc-sync-note',
			wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
				return function ( props ) {
					if ( props.name !== TOC ) {
						return el( BlockEdit, props );
					}
					return el( wp.element.Fragment, null,
						el( BlockEdit, props ),
						el( Inspector, null,
							el( C.PanelBody, { title: 'Updates itself', initialOpen: true },
								el( 'p', { style: { margin: 0 } }, 'This list follows the article’s section headings (H2): add, rename or remove a heading and the list changes with it, here and on the page. There is nothing to update by hand.' )
							)
						)
					);
				};
			}, 'apTocSyncNote' )
		);
	}
}( window.wp ) );
