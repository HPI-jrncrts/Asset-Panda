/**
 * Asset Panda Content — the "Companies" panel (1.3.25).
 *
 * Joseph, 2026-11-10: "adding the option to edit per list on the side … but
 * instead of per line it will be per listicle." A side panel listing the
 * companies of a "Best …" article (headings "1. Asset Panda", "2. Company
 * name" …). Each company opens to its name, logo, Best for, Free version,
 * Pricing, Platforms, Overview, Features and Limitation; typing there changes
 * the article as you type. Companies can be added, removed and moved; their
 * numbers stay in order. Everything stays editable on the page itself too.
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.components || ! wp.data || ! wp.blocks ) {
		return;
	}
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var C = wp.components;
	var data = wp.data;
	var editorPkg = wp.editor || {};
	var editPost = wp.editPost || {};
	var PluginSidebar = editorPkg.PluginSidebar || editPost.PluginSidebar;
	var MoreItem = editorPkg.PluginSidebarMoreMenuItem || editPost.PluginSidebarMoreMenuItem;
	if ( ! PluginSidebar ) {
		return;
	}
	var BE = wp.blockEditor || {};
	var MediaUpload = BE.MediaUpload;
	var MediaUploadCheck = BE.MediaUploadCheck || function ( p ) {
		return p.children;
	};
	var CFG = window.AP_COMPANIES || {};
	var BOX = CFG.boxClass || 'ap-vendor-logo';
	var PLUGIN = 'ap-companies';
	var SIDEBAR = 'ap-companies-sidebar';
	var DASH = '—';

	/* ---- Text helpers ---------------------------------------------------- */

	function html( v ) {
		if ( v == null ) {
			return '';
		}
		if ( typeof v === 'string' ) {
			return v;
		}
		return typeof v.toHTMLString === 'function' ? v.toHTMLString() : String( v );
	}
	function decode( s ) {
		var t = document.createElement( 'textarea' );
		t.innerHTML = String( s == null ? '' : s );
		return t.value;
	}
	function text( h ) {
		return decode( String( h || '' ).replace( /<[^>]*>/g, '' ) );
	}
	function esc( s ) {
		return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}
	function escAttr( s ) {
		return esc( s ).replace( /"/g, '&quot;' );
	}
	function norm( s ) {
		return String( s == null ? '' : s ).replace( /\s+/g, ' ' ).trim();
	}
	function be() {
		return data.select( 'core/block-editor' );
	}
	function act() {
		return data.dispatch( 'core/block-editor' );
	}
	function update( clientId, attrs ) {
		act().updateBlockAttributes( clientId, attrs );
	}

	/* ---- Reading the list from the blocks -------------------------------- */

	// Same rule as the page design (ap26x_parse_rank): "3. Freshservice — Best for IT" → 3, Freshservice, Best for IT.
	var RANK_HASH = /^#\s*(\d{1,2})\s*[:.)–—-]?\s*(.+)$/;
	var RANK_NUM = /^(\d{1,2})\s*[:.)–—-]\s*(.+)$/;
	// A detail line: "<strong>Best for:</strong> value" (the colon inside or right after the bold label).
	var LINE = /^(\s*<(strong|b)\b[^>]*>([^<]*?)<\/\2>(\s*:)?)\s?([\s\S]*)$/i;

	function rankOf( block ) {
		if ( ! block || block.name !== 'core/heading' || ( block.attributes.level || 2 ) !== 2 ) {
			return null;
		}
		var h = html( block.attributes.content );
		var t = norm( text( h ) );
		var m = t.match( RANK_HASH ) || t.match( RANK_NUM );
		if ( ! m ) {
			return null;
		}
		var rest = m[ 2 ].trim();
		var sep = rest.match( /^(.*?)(?:\s+[–—-]\s+|:\s+)(.*)$/ );
		var name = sep && sep[ 2 ].trim() ? sep[ 1 ].trim() : rest;
		if ( ! name || name.length > 60 ) {
			return null;
		}
		var link = h.match( /<a\b[^>]*\bhref="([^"]*)"/i );
		return {
			num: parseInt( m[ 1 ], 10 ),
			name: name,
			tagline: sep && sep[ 2 ].trim() ? sep[ 2 ].trim() : '',
			href: link ? decode( link[ 1 ] ) : '',
		};
	}

	function sectionName( block ) {
		return norm( text( html( block.attributes.content ) ) ).toLowerCase().replace( /[:?]+$/, '' );
	}

	function detail( e ) {
		var d = { rank: e.rank, heading: e.heading, ids: [ e.heading.clientId ], logo: null, lines: [], overview: null, features: null };
		var section = '';
		e.body.forEach( function ( b, k ) {
			d.ids.push( b.clientId );
			if ( k === 0 && b.name === 'core/image' ) {
				d.logo = b;
				return;
			}
			if ( b.name === 'core/heading' ) {
				section = sectionName( b );
				return;
			}
			if ( b.name === 'core/paragraph' ) {
				var h = html( b.attributes.content );
				var labeled = false;
				h.split( /<br\s*\/?>/i ).forEach( function ( line, li ) {
					var m = line.match( LINE );
					if ( m && ( /:\s*$/.test( m[ 3 ] ) || m[ 4 ] ) ) {
						labeled = true;
						d.lines.push( {
							clientId: b.clientId,
							line: li,
							label: decode( m[ 3 ] ).replace( /:\s*$/, '' ).trim(),
							value: decode( m[ 5 ].replace( /<[^>]*>/g, '' ) ),
							links: /<a\b/i.test( m[ 5 ] ),
						} );
					}
				} );
				if ( ! labeled && section === 'overview' && ! d.overview ) {
					d.overview = { clientId: b.clientId, value: text( h ), links: /<a\b/i.test( h ) };
				}
				return;
			}
			if ( b.name === 'core/list' && /features$/.test( section ) && ! d.features ) {
				d.features = {
					clientId: b.clientId,
					value: b.innerBlocks.map( function ( li ) {
						return text( html( li.attributes.content ) );
					} ).join( '\n' ),
					links: b.innerBlocks.some( function ( li ) {
						return /<a\b/i.test( html( li.attributes.content ) );
					} ),
				};
			}
		} );
		return d;
	}

	// The numbered companies, in order: each from its heading to the next level-2 heading.
	function entriesOf( blocks ) {
		var list = [];
		var cur = null;
		blocks.forEach( function ( b ) {
			if ( b.name === 'core/heading' && ( b.attributes.level || 2 ) === 2 ) {
				if ( cur ) {
					list.push( cur );
					cur = null;
				}
				var r = rankOf( b );
				if ( r ) {
					cur = { rank: r, heading: b, body: [] };
				}
				return;
			}
			if ( cur ) {
				cur.body.push( b );
			}
		} );
		if ( cur ) {
			list.push( cur );
		}
		return list.map( detail );
	}

	/* ---- Writing back ---------------------------------------------------- */

	function setHeading( e, change ) {
		var b = be().getBlock( e.heading.clientId );
		if ( ! b ) {
			return;
		}
		var cur = rankOf( b ) || e.rank;
		var name = change.name != null ? change.name : cur.name;
		var tagline = change.tagline != null ? change.tagline : cur.tagline;
		update( b.clientId, {
			content: cur.num + '. ' + ( cur.href ? '<a href="' + escAttr( cur.href ) + '">' + esc( name ) + '</a>' : esc( name ) ) + ( norm( tagline ) ? ' ' + DASH + ' ' + esc( tagline ) : '' ),
		} );
	}

	function setLine( item, value ) {
		var b = be().getBlock( item.clientId );
		if ( ! b ) {
			return;
		}
		var parts = html( b.attributes.content ).split( /<br\s*\/?>/i );
		var m = ( parts[ item.line ] || '' ).match( LINE );
		if ( ! m ) {
			return;
		}
		parts[ item.line ] = m[ 1 ] + ' ' + esc( value );
		update( b.clientId, { content: parts.join( '<br>' ) } );
	}

	function setFeatures( item, value ) {
		var items = String( value ).split( /\n/ ).map( norm ).filter( Boolean ).map( function ( t ) {
			return wp.blocks.createBlock( 'core/list-item', { content: esc( t ) } );
		} );
		if ( items.length ) {
			act().replaceInnerBlocks( item.clientId, items, false );
		}
	}

	function setLogo( e, media ) {
		if ( ! media || ! media.url ) {
			return;
		}
		var alt = /^asset\s*panda\b/i.test( e.rank.name ) ? 'Asset Panda logo' : e.rank.name + ' logo';
		var logo = e.logo && be().getBlock( e.logo.clientId );
		if ( logo ) {
			update( logo.clientId, { url: media.url, id: media.id, alt: alt } );
			return;
		}
		var root = be().getBlockRootClientId( e.heading.clientId ) || undefined;
		act().insertBlock(
			wp.blocks.createBlock( 'core/image', { url: media.url, id: media.id, alt: alt, className: BOX } ),
			be().getBlockIndex( e.heading.clientId ) + 1,
			root,
			false
		);
	}

	// Numbers follow the order on the page: "1." for the first company, "2." for the next…
	function renumber() {
		entriesOf( be().getBlocks() ).forEach( function ( e, k ) {
			if ( e.rank.num === k + 1 ) {
				return;
			}
			var h = html( e.heading.attributes.content );
			var next = h.replace( /^(\s*(?:<[^>]+>\s*)*)(#\s*)?\d{1,2}/, function ( all, pre, hash ) {
				return pre + ( hash || '' ) + ( k + 1 );
			} );
			if ( next !== h ) {
				update( e.heading.clientId, { content: next } );
			}
		} );
	}

	function moveEntry( list, k, dir ) {
		var j = k + dir;
		if ( j < 0 || j >= list.length ) {
			return;
		}
		var first = dir < 0 ? list[ j ] : list[ k ];
		var second = dir < 0 ? list[ k ] : list[ j ];
		var root = be().getBlockRootClientId( first.heading.clientId ) || undefined;
		// The later company's blocks go in front of the earlier one's heading.
		act().moveBlocksToPosition( second.ids, root, root, be().getBlockIndex( first.heading.clientId ) );
		renumber();
	}

	function removeEntry( e ) {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( 'Remove "' + e.rank.name + '" and everything under its heading (logo, details, overview, features)?' ) ) {
			return;
		}
		act().removeBlocks( e.ids, false );
		renumber();
	}

	function addEntry( list ) {
		var B = wp.blocks.createBlock;
		var last = list[ list.length - 1 ];
		var root = last ? be().getBlockRootClientId( last.heading.clientId ) || undefined : undefined;
		var index = last ? be().getBlockIndex( last.ids[ last.ids.length - 1 ] ) + 1 : be().getBlocks().length;
		var blocks = [
			B( 'core/heading', { level: 2, content: ( list.length + 1 ) + '. Company name' } ),
			B( 'core/image', { className: BOX } ),
			B( 'core/paragraph', { content: '<strong>Best for:</strong> Lorem ipsum dolor sit amet<br><strong>Free Version:</strong> Yes / No<br><strong>Pricing:</strong> Lorem ipsum<br><strong>Platforms:</strong> All platforms' } ),
			B( 'core/heading', { level: 3, content: 'Overview' } ),
			B( 'core/paragraph', { content: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.' } ),
			B( 'core/heading', { level: 3, content: 'Features' } ),
			B( 'core/list', {}, [
				B( 'core/list-item', { content: 'Lorem ipsum dolor sit' } ),
				B( 'core/list-item', { content: 'Consectetur adipiscing elit' } ),
				B( 'core/list-item', { content: 'Sed do eiusmod tempor' } ),
			] ),
			B( 'core/paragraph', { content: '<strong>Limitation:</strong> Lorem ipsum dolor sit amet, consectetur adipiscing elit.' } ),
		];
		act().insertBlocks( blocks, index, root, false );
		renumber();
		return blocks[ 0 ].clientId;
	}

	function showOnPage( clientId ) {
		act().selectBlock( clientId );
		var frame = document.querySelector( 'iframe[name="editor-canvas"]' );
		var doc = ( frame && frame.contentDocument ) || document;
		var node = doc.querySelector( '[data-block="' + clientId + '"]' );
		if ( node && node.scrollIntoView ) {
			node.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	}

	/* ---- The panel ------------------------------------------------------- */

	// A text box that keeps what is being typed (spaces included) while the article follows along.
	function Field( props ) {
		var state = useState( props.value );
		var local = state[ 0 ];
		var setLocal = state[ 1 ];
		useEffect( function () {
			if ( norm( props.value ) !== norm( local ) ) {
				setLocal( props.value );
			}
		}, [ props.value ] ); // eslint-disable-line react-hooks/exhaustive-deps
		var Control = props.multiline ? C.TextareaControl : C.TextControl;
		return el( Control, {
			label: props.label,
			help: props.help,
			value: local,
			rows: props.rows,
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
			onChange: function ( v ) {
				setLocal( v );
				props.onChange( v );
			},
		} );
	}

	var HINTS = {
		'best for': 'Who it suits best, in a few words.',
		'free version': 'For example: Yes, No, Yes (7-day trial).',
		pricing: 'For example: $24/user/month, Available upon request.',
		'pricing model': 'For example: Asset-based, Per user.',
		platforms: 'For example: Web, iOS, and Android.',
		'platforms supported': 'For example: Web, iOS, and Android.',
		limitation: 'One sentence: where it falls short.',
	};

	function gap() {
		return el( 'div', { style: { height: 12 } } );
	}

	function Company( props ) {
		var e = props.entry;
		var r = e.rank;
		var logoUrl = e.logo ? e.logo.attributes.url || '' : '';
		var linkNote = 'Has links: changing it here keeps the words and drops the links. To keep them, edit it on the page.';
		var rows = [
			el( Field, {
				key: 'name',
				label: 'Company name',
				value: r.name,
				onChange: function ( v ) {
					if ( norm( v ) ) {
						setHeading( e, { name: v } );
					}
				},
			} ),
			gap(),
			el( Field, {
				key: 'tagline',
				label: 'After the name (optional)',
				help: 'Shown after a dash, e.g. "Best Overall Asset Management Software".',
				value: r.tagline,
				onChange: function ( v ) {
					setHeading( e, { tagline: v } );
				},
			} ),
			gap(),
			el( 'div', { key: 'logo', className: 'ap-companies__logo' },
				el( 'p', { style: { margin: '0 0 6px', fontWeight: 500 } }, 'Logo' ),
				logoUrl
					? el( 'img', { src: logoUrl, alt: '', style: { display: 'block', maxWidth: '100%', maxHeight: 56, margin: '0 0 8px' } } )
					: el( 'p', { style: { margin: '0 0 8px', color: '#757575' } }, 'No logo yet: the page shows the company’s initials.' ),
				MediaUpload
					? el( MediaUploadCheck, null, el( MediaUpload, {
						allowedTypes: [ 'image' ],
						value: e.logo ? e.logo.attributes.id : undefined,
						onSelect: function ( media ) {
							setLogo( e, media );
						},
						render: function ( o ) {
							return el( C.Button, { variant: 'secondary', onClick: o.open }, logoUrl ? 'Replace logo' : 'Upload or choose logo' );
						},
					} ) )
					: null
			),
		];
		function lineField( line, i ) {
			rows.push( gap() );
			rows.push( el( Field, {
				key: 'line' + i,
				label: line.label,
				help: line.links ? linkNote : HINTS[ line.label.toLowerCase() ],
				value: line.value,
				onChange: function ( v ) {
					setLine( line, v );
				},
			} ) );
		}
		// The details first (Best for, Free version, Pricing, Platforms); Limitation after Features, as on the page.
		function isLate( line ) {
			return /^limitations?$/i.test( line.label );
		}
		e.lines.forEach( function ( line, i ) {
			if ( ! isLate( line ) ) {
				lineField( line, i );
			}
		} );
		if ( e.overview ) {
			rows.push( gap() );
			rows.push( el( Field, {
				key: 'overview',
				label: 'Overview',
				multiline: true,
				rows: 5,
				help: e.overview.links ? linkNote : undefined,
				value: e.overview.value,
				onChange: function ( v ) {
					update( e.overview.clientId, { content: esc( v ) } );
				},
			} ) );
		}
		if ( e.features ) {
			rows.push( gap() );
			rows.push( el( Field, {
				key: 'features',
				label: 'Features (one per line)',
				multiline: true,
				rows: 6,
				help: e.features.links ? linkNote : undefined,
				value: e.features.value,
				onChange: function ( v ) {
					setFeatures( e.features, v );
				},
			} ) );
		}
		e.lines.forEach( function ( line, i ) {
			if ( isLate( line ) ) {
				lineField( line, i );
			}
		} );
		rows.push( gap() );
		rows.push( el( 'div', { key: 'actions', style: { display: 'flex', flexWrap: 'wrap', gap: 8 } },
			el( C.Button, { variant: 'secondary', size: 'compact', onClick: function () {
				showOnPage( e.heading.clientId );
			} }, 'Show on page' ),
			el( C.Button, { variant: 'tertiary', size: 'compact', disabled: props.k === 0, onClick: function () {
				moveEntry( props.list, props.k, -1 );
			} }, 'Move up' ),
			el( C.Button, { variant: 'tertiary', size: 'compact', disabled: props.k === props.list.length - 1, onClick: function () {
				moveEntry( props.list, props.k, 1 );
			} }, 'Move down' ),
			el( C.Button, { variant: 'tertiary', size: 'compact', isDestructive: true, onClick: function () {
				removeEntry( e );
			} }, 'Remove' )
		) );
		return el.apply( null, [ C.PanelBody, { title: r.num + '. ' + r.name, initialOpen: !! props.open } ].concat( rows ) );
	}

	function Companies() {
		var blocks = data.useSelect( function ( select ) {
			return select( 'core/block-editor' ).getBlocks();
		}, [] );
		var opened = useState( '' );
		var list = entriesOf( blocks );
		var add = el( C.Button, {
			variant: 'primary',
			onClick: function () {
				opened[ 1 ]( addEntry( list ) );
			},
		}, 'Add a company' );
		if ( list.length < 2 ) {
			return el( C.PanelBody, null,
				el( 'p', null, 'This panel lists the companies of a “Best …” article: headings numbered “1. Asset Panda”, “2. Company name” and so on. This article has ' + ( list.length ? 'only one' : 'none' ) + ' yet.' ),
				add
			);
		}
		return el( Fragment, null,
			el( C.PanelBody, null,
				el( 'p', { style: { margin: 0 } }, list.length + ' companies. Open one to change its name, logo and details: the article changes as you type. Everything can still be edited on the page too.' )
			),
			list.map( function ( e, k ) {
				return el( Company, { key: e.heading.clientId, entry: e, k: k, list: list, open: opened[ 0 ] === e.heading.clientId } );
			} ),
			el( C.PanelBody, null, add )
		);
	}

	wp.plugins.registerPlugin( PLUGIN, {
		icon: 'editor-ol',
		render: function () {
			return el( Fragment, null,
				MoreItem ? el( MoreItem, { target: SIDEBAR, icon: 'editor-ol' }, 'Companies' ) : null,
				el( PluginSidebar, { name: SIDEBAR, title: 'Companies', icon: 'editor-ol' }, el( Companies ) )
			);
		},
	} );

	// A draft of a "Best …" article opens with this panel (once per draft per browser session).
	var checked = false;
	data.subscribe( function () {
		if ( checked ) {
			return;
		}
		var ed = data.select( 'core/editor' );
		if ( ! ed || ! ed.getCurrentPostId || ! ed.getCurrentPostId() ) {
			return;
		}
		if ( typeof ed.__unstableIsEditorReady === 'function' && ! ed.__unstableIsEditorReady() ) {
			return;
		}
		var blocks = be().getBlocks();
		if ( ! blocks.length ) {
			return;
		}
		checked = true;
		var status = ed.getEditedPostAttribute( 'status' );
		if ( ( status !== 'draft' && status !== 'auto-draft' ) || entriesOf( blocks ).length < 3 ) {
			return;
		}
		var key = 'ap-companies-open:' + ed.getCurrentPostId();
		try {
			if ( window.sessionStorage.getItem( key ) ) {
				return;
			}
			window.sessionStorage.setItem( key, '1' );
		} catch ( err ) {}
		// After the editor has finished choosing its own sidebar (opened earlier, it is switched back).
		window.setTimeout( function () {
			var target = PLUGIN + '/' + SIDEBAR;
			var ifaceSel = data.select( 'core/interface' );
			var active = ifaceSel && ifaceSel.getActiveComplementaryArea ? ifaceSel.getActiveComplementaryArea( 'core' ) : null;
			if ( active && ! /^edit-post\/(document|block)$/.test( active ) ) {
				return; // The writer already opened another panel.
			}
			var post = data.dispatch( 'core/edit-post' );
			var iface = data.dispatch( 'core/interface' );
			if ( post && typeof post.openGeneralSidebar === 'function' ) {
				post.openGeneralSidebar( target );
			} else if ( iface && typeof iface.enableComplementaryArea === 'function' ) {
				iface.enableComplementaryArea( 'core', target );
			}
		}, 1500 );
	} );
}( window.wp ) );
