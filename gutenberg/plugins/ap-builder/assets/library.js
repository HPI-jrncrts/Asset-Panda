/**
 * AP Builder — the library under the block editor (builder v2, 2026-11).
 *
 * Six cabinets, smallest to largest: Visuals (the kit's art and the Media
 * Library, with upload), Animations, Containers, Sections (every section
 * design used on the site, with its real content), Layouts (whole pages from
 * the Template Library) and AI. Clicking an item adds it after the selected
 * block (or at the end of the page); a visual clicked just after an image on
 * the page was clicked replaces that image instead.
 *
 * Mounted into the "Asset Panda Library" meta box (includes/library.php).
 * Buildless: global wp.* packages only.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.data || ! wp.blocks || ! wp.blockEditor ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var useSelect = wp.data.useSelect;
	var C = wp.components;
	var BE = wp.blockEditor;
	var blocks = wp.blocks;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;

	var CFG = window.AP_BUILDER || {};
	var NS = '/' + ( CFG.ns || 'ap-builder/v1' );

	/* ---- Editor helpers -------------------------------------------------- */

	function editor() {
		return wp.data.select( 'core/block-editor' );
	}

	function notice( type, message ) {
		wp.data.dispatch( 'core/notices' ).createNotice( type, message, { type: 'snackbar', isDismissible: true } );
	}

	/*
	 * The page's own content. Pages open with their template shown (header,
	 * page body, footer); the template is locked, and the page's blocks live
	 * inside its Post Content block. Null when the editor shows the post alone.
	 */
	function postContentId() {
		var be = editor();
		var found = be.getBlocksByName ? be.getBlocksByName( 'core/post-content' ) : [];
		return found && found.length ? found[ 0 ] : null;
	}

	/* Where new blocks go: after the selected block when that spot takes them, else at the end of the page's content. */
	function insertionPoint( name ) {
		var be = editor();
		var sel = be.getSelectedBlockClientId();
		if ( sel ) {
			var root = be.getBlockRootClientId( sel ) || undefined;
			if ( be.canInsertBlockType( name, root ) ) {
				return { rootClientId: root, index: be.getBlockIndex( sel ) + 1 };
			}
			if ( be.canInsertBlockType( name, sel ) ) {
				return { rootClientId: sel, index: be.getBlockCount( sel ) }; // A container or the page body itself.
			}
		}
		var pc = postContentId();
		if ( pc ) {
			return { rootClientId: pc, index: be.getBlockCount( pc ) };
		}
		return { rootClientId: undefined, index: be.getBlockCount() };
	}

	/* After the selected block (inside a container when one is selected), else at the end of the page. */
	function insert( list, what ) {
		if ( ! list || ! list.length ) {
			return;
		}
		var point = insertionPoint( list[ 0 ].name );
		wp.data.dispatch( 'core/block-editor' ).insertBlocks( list, point.index, point.rootClientId, true );
		if ( editor().getBlock( list[ 0 ].clientId ) ) {
			notice( 'success', ( what || __( 'Item', 'ap-builder' ) ) + ' ' + __( 'added to the page.', 'ap-builder' ) );
		} else {
			notice( 'error', __( 'This spot of the page cannot take new blocks. Select a block in the page content first.', 'ap-builder' ) );
		}
	}

	function parse( markup ) {
		return blocks.parse( markup || '' ).filter( function ( b ) {
			return b && b.name && b.name !== 'core/freeform' && ! ( b.name === 'core/missing' );
		} );
	}

	function useSelectedBlock() {
		return useSelect( function ( select ) {
			var b = select( 'core/block-editor' ).getSelectedBlock();
			return b ? { clientId: b.clientId, name: b.name, attributes: b.attributes } : null;
		}, [] );
	}

	function blockTitle( name ) {
		var t = name && blocks.getBlockType( name );
		return t ? t.title : name || '';
	}

	/* ---- Small UI pieces ------------------------------------------------- */

	function Chips( props ) {
		return el(
			'div',
			{ className: 'ap-builder__chips', role: 'group', 'aria-label': props.label },
			props.options.map( function ( o ) {
				return el(
					'button',
					{
						key: o.value,
						type: 'button',
						className: 'ap-builder__chip' + ( props.value === o.value ? ' is-active' : '' ),
						'aria-pressed': props.value === o.value,
						onClick: function () {
							props.onChange( o.value );
						},
					},
					o.label,
					o.count !== undefined ? el( 'span', { className: 'ap-builder__count' }, o.count ) : null
				);
			} )
		);
	}

	/* Renders its children only once it has scrolled into view (previews are costly). */
	function Lazy( props ) {
		var ref = useRef( null );
		var state = useState( false );
		var seen = state[ 0 ];
		var setSeen = state[ 1 ];
		useEffect( function () {
			if ( seen || ! ref.current ) {
				return undefined;
			}
			if ( ! window.IntersectionObserver ) {
				setSeen( true );
				return undefined;
			}
			var io = new window.IntersectionObserver(
				function ( entries ) {
					if ( entries.some( function ( e ) {
						return e.isIntersecting;
					} ) ) {
						setSeen( true );
						io.disconnect();
					}
				},
				{ rootMargin: '200px' }
			);
			io.observe( ref.current );
			return function () {
				io.disconnect();
			};
		}, [ seen ] );
		return el( 'div', { ref: ref, className: 'ap-builder__lazy' }, seen ? props.children : el( 'div', { className: 'ap-builder__placeholder' } ) );
	}

	function Preview( props ) {
		return el(
			'div',
			{ className: 'ap-builder__preview', 'aria-hidden': 'true' },
			el( BE.BlockPreview, { blocks: props.blocks, viewportWidth: props.width || 1400 } )
		);
	}

	function Card( props ) {
		return el(
			'div',
			{ className: 'ap-builder__card' + ( props.wide ? ' is-wide' : '' ) },
			el(
				'button',
				{ type: 'button', className: 'ap-builder__card-main', onClick: props.onClick, title: props.hint || '' },
				props.media,
				el(
					'span',
					{ className: 'ap-builder__card-text' },
					el( 'strong', null, props.title ),
					props.meta ? el( 'span', { className: 'ap-builder__meta' }, props.meta ) : null
				)
			),
			props.actions ? el( 'div', { className: 'ap-builder__card-actions' }, props.actions ) : null
		);
	}

	function useFetch( path ) {
		var s = useState( { loading: true, data: null, error: null } );
		useEffect( function () {
			var live = true;
			apiFetch( { path: path } )
				.then( function ( data ) {
					if ( live ) {
						s[ 1 ]( { loading: false, data: data, error: null } );
					}
				} )
				.catch( function ( e ) {
					if ( live ) {
						s[ 1 ]( { loading: false, data: null, error: ( e && e.message ) || 'Error' } );
					}
				} );
			return function () {
				live = false;
			};
		}, [ path ] );
		return s[ 0 ];
	}

	function Loading( props ) {
		if ( props.state.loading ) {
			return el( 'p', { className: 'ap-builder__status' }, el( C.Spinner ), ' ', __( 'Loading…', 'ap-builder' ) );
		}
		if ( props.state.error ) {
			return el( C.Notice, { status: 'error', isDismissible: false }, props.state.error );
		}
		return null;
	}

	function matches( q, parts ) {
		if ( ! q ) {
			return true;
		}
		var hay = parts.join( ' ' ).toLowerCase();
		return q
			.toLowerCase()
			.split( /\s+/ )
			.every( function ( w ) {
				return hay.indexOf( w ) !== -1;
			} );
	}

	/* ---- Visuals --------------------------------------------------------- */

	function useImageTarget() {
		var s = useState( null );
		useEffect( function () {
			function read() {
				s[ 1 ]( window.APBuilder && window.APBuilder.imageTarget ? window.APBuilder.imageTarget() : null );
			}
			read();
			window.addEventListener( 'ap-builder-image-target', read );
			var t = window.setInterval( read, 5000 ); // The target expires two minutes after the click.
			return function () {
				window.removeEventListener( 'ap-builder-image-target', read );
				window.clearInterval( t );
			};
		}, [] );
		return s[ 0 ];
	}

	/* What clicking an image does right now, and doing it. */
	function imageAction( target, selected ) {
		if ( target ) {
			return { label: __( 'Replace the image you clicked in', 'ap-builder' ) + ' ' + target.blockTitle, kind: 'target' };
		}
		if ( selected && selected.name === 'core/image' ) {
			return { label: __( 'Replace the selected image', 'ap-builder' ), kind: 'core-image' };
		}
		if ( selected && selected.name === 'assetpanda/visual' ) {
			return { label: __( 'Replace the selected visual', 'ap-builder' ), kind: 'visual' };
		}
		return { label: __( 'Add it as a visual after the selected block', 'ap-builder' ), kind: 'insert' };
	}

	function chooseImage( url, alt, isArt, action, selected ) {
		var dispatch = wp.data.dispatch( 'core/block-editor' );
		if ( action.kind === 'target' && window.APBuilder.applyImage( url, alt ) ) {
			notice( 'success', __( 'Image replaced.', 'ap-builder' ) );
			return;
		}
		if ( action.kind === 'core-image' ) {
			dispatch.updateBlockAttributes( selected.clientId, { url: url, alt: alt || selected.attributes.alt || '', id: undefined } );
			notice( 'success', __( 'Image replaced.', 'ap-builder' ) );
			return;
		}
		if ( action.kind === 'visual' ) {
			var patch = { image: url };
			if ( alt ) {
				patch.alt = alt;
			}
			dispatch.updateBlockAttributes( selected.clientId, patch );
			notice( 'success', __( 'Image replaced.', 'ap-builder' ) );
			return;
		}
		if ( blocks.getBlockType( 'assetpanda/visual' ) ) {
			insert(
				[ blocks.createBlock( 'assetpanda/visual', { image: url, alt: alt || '', shape: isArt ? 'none' : 'rounded', size: isArt ? 's' : 'l' } ) ],
				__( 'Visual', 'ap-builder' )
			);
		} else {
			insert( [ blocks.createBlock( 'core/image', { url: url, alt: alt || '' } ) ], __( 'Image', 'ap-builder' ) );
		}
	}

	var ART_GROUPS = [
		{ value: 'all', label: __( 'All', 'ap-builder' ) },
		{ value: 'pandas', label: __( 'Pandas', 'ap-builder' ) },
		{ value: 'orbs', label: __( 'Orbs & glows', 'ap-builder' ) },
		{ value: 'icons', label: __( 'Icons', 'ap-builder' ) },
		{ value: 'badges', label: __( 'Badges & logos', 'ap-builder' ) },
		{ value: 'illustrations', label: __( 'Illustrations', 'ap-builder' ) },
	];

	function SiteArt( props ) {
		var state = useFetch( NS + '/art' );
		var g = useState( 'pandas' );
		if ( state.loading || state.error ) {
			return el( Loading, { state: state } );
		}
		var all = state.data || [];
		var list = all.filter( function ( a ) {
			return ( g[ 0 ] === 'all' || a.group === g[ 0 ] ) && matches( props.query, [ a.name, a.group ] );
		} );
		return el(
			Fragment,
			null,
			el( Chips, {
				label: __( 'Kind of art', 'ap-builder' ),
				value: g[ 0 ],
				onChange: g[ 1 ],
				options: ART_GROUPS.map( function ( o ) {
					return {
						value: o.value,
						label: o.label,
						count: all.filter( function ( a ) {
							return o.value === 'all' || a.group === o.value;
						} ).length,
					};
				} ),
			} ),
			el(
				'div',
				{ className: 'ap-builder__grid is-small' },
				list.map( function ( a ) {
					return el( Card, {
						key: a.url,
						title: a.name,
						hint: props.action.label,
						media: el( 'span', { className: 'ap-builder__thumb is-art' }, el( 'img', { src: a.url, alt: '', loading: 'lazy' } ) ),
						onClick: function () {
							chooseImage( a.url, '', true, props.action, props.selected );
						},
					} );
				} )
			)
		);
	}

	function MediaLibrary( props ) {
		var s = useState( { items: [], page: 1, more: true, loading: false, error: null } );
		var st = s[ 0 ];
		var set = s[ 1 ];
		var fileRef = useRef( null );
		var q = props.query;

		function load( page, reset ) {
			set( function ( prev ) {
				return Object.assign( {}, prev, { loading: true, error: null } );
			} );
			apiFetch( {
				path:
					'/wp/v2/media?media_type=image&per_page=40&page=' +
					page +
					( q ? '&search=' + encodeURIComponent( q ) : '' ) +
					'&_fields=id,source_url,alt_text,title,media_details',
			} )
				.then( function ( items ) {
					set( function ( prev ) {
						return { items: ( reset ? [] : prev.items ).concat( items ), page: page, more: items.length === 40, loading: false, error: null };
					} );
				} )
				.catch( function ( e ) {
					set( function ( prev ) {
						return Object.assign( {}, prev, { loading: false, more: false, error: e && e.code === 'rest_post_invalid_page_number' ? null : ( e && e.message ) || 'Error' } );
					} );
				} );
		}

		useEffect(
			function () {
				var t = window.setTimeout( function () {
					load( 1, true );
				}, 250 );
				return function () {
					window.clearTimeout( t );
				};
			},
			[ q ]
		);

		function upload( files ) {
			Array.prototype.slice.call( files || [] ).forEach( function ( file ) {
				var body = new window.FormData();
				body.append( 'file', file, file.name );
				notice( 'info', __( 'Uploading', 'ap-builder' ) + ' ' + file.name + '…' );
				apiFetch( { path: '/wp/v2/media', method: 'POST', body: body } )
					.then( function ( item ) {
						set( function ( prev ) {
							return Object.assign( {}, prev, { items: [ item ].concat( prev.items ) } );
						} );
						notice( 'success', file.name + ' ' + __( 'uploaded. Add its alt text in the Media Library if it shows something.', 'ap-builder' ) );
					} )
					.catch( function ( e ) {
						notice( 'error', ( e && e.message ) || __( 'Upload failed.', 'ap-builder' ) );
					} );
			} );
		}

		function thumb( item ) {
			var sizes = ( item.media_details && item.media_details.sizes ) || {};
			var pick = sizes.medium || sizes.thumbnail || sizes.medium_large;
			return ( pick && pick.source_url ) || item.source_url;
		}

		return el(
			Fragment,
			null,
			CFG.canUpload
				? el(
						'div',
						{ className: 'ap-builder__row' },
						el( C.Button, { variant: 'secondary', icon: 'upload', onClick: function () {
							fileRef.current.click();
						} }, __( 'Upload images', 'ap-builder' ) ),
						el( 'input', {
							ref: fileRef,
							type: 'file',
							accept: 'image/*',
							multiple: true,
							hidden: true,
							onChange: function ( e ) {
								upload( e.target.files );
								e.target.value = '';
							},
						} ),
						el( 'span', { className: 'ap-builder__meta' }, __( 'Uploads go to the Media Library and appear first here.', 'ap-builder' ) )
				  )
				: null,
			st.error ? el( C.Notice, { status: 'error', isDismissible: false }, st.error ) : null,
			el(
				'div',
				{ className: 'ap-builder__grid is-small' },
				st.items.map( function ( item ) {
					var title = ( item.title && ( item.title.rendered || item.title.raw ) ) || '';
					return el( Card, {
						key: item.id,
						title: title.replace( /<[^>]+>/g, '' ) || '#' + item.id,
						meta: item.alt_text ? '' : __( 'no alt text', 'ap-builder' ),
						hint: props.action.label,
						media: el( 'span', { className: 'ap-builder__thumb' }, el( 'img', { src: thumb( item ), alt: '', loading: 'lazy' } ) ),
						onClick: function () {
							chooseImage( item.source_url, item.alt_text || '', false, props.action, props.selected );
						},
					} );
				} )
			),
			st.loading ? el( 'p', { className: 'ap-builder__status' }, el( C.Spinner ) ) : null,
			! st.loading && st.more
				? el( C.Button, { variant: 'secondary', onClick: function () {
						load( st.page + 1, false );
				  } }, __( 'Show more images', 'ap-builder' ) )
				: null
		);
	}

	function Visuals( props ) {
		var tab = useState( 'art' );
		var target = useImageTarget();
		var selected = useSelectedBlock();
		var action = imageAction( target, selected );
		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'ap-builder__banner' + ( action.kind === 'insert' ? '' : ' is-replace' ) },
				el( 'span', null, __( 'Click an image to:', 'ap-builder' ) + ' ', el( 'strong', null, action.label ) ),
				target
					? el( C.Button, { variant: 'link', onClick: function () {
							window.APBuilder.clearImageTarget();
					  } }, __( 'Cancel', 'ap-builder' ) )
					: el( 'span', { className: 'ap-builder__meta' }, __( 'Tip: click an image on the page first to replace it with one from here.', 'ap-builder' ) )
			),
			el( Chips, {
				label: __( 'Source', 'ap-builder' ),
				value: tab[ 0 ],
				onChange: tab[ 1 ],
				options: [
					{ value: 'art', label: __( 'Site art', 'ap-builder' ) },
					{ value: 'media', label: __( 'Media Library', 'ap-builder' ) },
				],
			} ),
			tab[ 0 ] === 'art'
				? el( SiteArt, { query: props.query, action: action, selected: selected } )
				: el( MediaLibrary, { query: props.query, action: action, selected: selected } )
		);
	}

	/* ---- Videos (the Video Library) -------------------------------------- */

	function currentPostId() {
		var ed = wp.data.select( 'core/editor' );
		return ed ? ed.getCurrentPostId() : 0;
	}

	/* The kit sheets the video sections need, in the canvas (same ids as the kit editor's, so never twice). */
	function ensureKitCss( doc, names ) {
		var kit = window.AP_UI_KIT;
		if ( ! kit || ! kit.url || ! doc || ! doc.head ) {
			return;
		}
		[ 'src/tokens.css', 'src/motion.css' ]
			.concat(
				names.map( function ( n ) {
					return 'components/' + n + '/style.css';
				} )
			)
			.forEach( function ( rel ) {
				var id = 'ap-ui-ed-' + rel.replace( /[^a-z0-9]+/gi, '-' );
				if ( doc.getElementById( id ) ) {
					return;
				}
				var link = doc.createElement( 'link' );
				link.id = id;
				link.rel = 'stylesheet';
				link.href = kit.url + '/' + rel + '?ver=' + encodeURIComponent( kit.ver );
				doc.head.appendChild( link );
			} );
	}

	/* The page's "videos featured on this page" spot, added once when a video is first featured on it. */
	function ensurePageSpot() {
		var has = editor()
			.getBlocksByName( 'ap-builder/videos' )
			.some( function ( id ) {
				return ( editor().getBlockAttributes( id ) || {} ).mode === 'page';
			} );
		if ( ! has ) {
			insert( [ blocks.createBlock( 'ap-builder/videos', { mode: 'page', look: 'band' } ) ], __( 'Video spot for this page', 'ap-builder' ) );
		}
	}

	function Videos( props ) {
		var tick = useState( 0 );
		var state = useFetch( NS + '/videos?t=' + tick[ 0 ] );
		var link = useState( '' );
		var busy = useState( false );
		var here = useState( true );
		var pageId = currentPostId();

		function refresh() {
			tick[ 1 ]( tick[ 0 ] + 1 );
		}

		function addVideo() {
			busy[ 1 ]( true );
			apiFetch( { path: NS + '/videos', method: 'POST', data: { url: link[ 0 ], page: here[ 0 ] ? pageId : 0 } } )
				.then( function ( v ) {
					link[ 1 ]( '' );
					notice( 'success', v.title + ' ' + __( 'is in the Video Library.', 'ap-builder' ) );
					if ( here[ 0 ] ) {
						ensurePageSpot();
					}
					refresh();
				} )
				.catch( function ( e ) {
					notice( 'error', ( e && e.message ) || __( 'That video could not be added.', 'ap-builder' ) );
				} )
				.finally( function () {
					busy[ 1 ]( false );
				} );
		}

		function toggleHere( v, on ) {
			apiFetch( { path: NS + '/videos/' + v.id + '/page', method: 'POST', data: { page: pageId, on: on } } ).then(
				function () {
					if ( on ) {
						ensurePageSpot();
					}
					notice( 'success', on ? __( 'Featured on this page.', 'ap-builder' ) : __( 'No longer featured on this page.', 'ap-builder' ) );
					refresh();
				},
				function ( e ) {
					notice( 'error', ( e && e.message ) || 'Error' );
				}
			);
		}

		var data = state.data || { videos: [], places: [] };
		var placeName = {};
		( data.places || [] ).forEach( function ( p ) {
			placeName[ p.slug ] = p.name;
		} );
		var list = ( data.videos || [] ).filter( function ( v ) {
			return matches( props.query, [ v.title, v.body ] );
		} );

		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'ap-builder__row' },
				el( C.TextControl, {
					className: 'ap-builder__grow',
					label: __( 'Add a YouTube video to the library', 'ap-builder' ),
					hideLabelFromVision: true,
					placeholder: __( 'Paste a YouTube link…', 'ap-builder' ),
					value: link[ 0 ],
					onChange: link[ 1 ],
					__nextHasNoMarginBottom: true,
				} ),
				el( C.CheckboxControl, { label: __( 'Feature it on this page', 'ap-builder' ), checked: here[ 0 ], onChange: here[ 1 ], __nextHasNoMarginBottom: true } ),
				el( C.Button, { variant: 'primary', isBusy: busy[ 0 ], disabled: busy[ 0 ] || ! /youtu/.test( link[ 0 ] ), onClick: addVideo }, __( 'Add video', 'ap-builder' ) )
			),
			el(
				'div',
				{ className: 'ap-builder__row' },
				el( C.Button, { variant: 'secondary', onClick: function () {
					insert( [ blocks.createBlock( 'ap-builder/videos', { mode: 'place', place: 'tutorials', look: 'theater' } ) ], __( 'Tutorials gallery', 'ap-builder' ) );
				} }, __( 'Add the tutorials gallery', 'ap-builder' ) ),
				el( C.Button, { variant: 'secondary', onClick: function () {
					insert( [ blocks.createBlock( 'ap-builder/videos', { mode: 'page', look: 'band' } ) ], __( 'Video spot for this page', 'ap-builder' ) );
				} }, __( 'Add this page’s video spot', 'ap-builder' ) ),
				data.manage ? el( 'a', { href: data.manage, target: '_blank', rel: 'noopener' }, __( 'Manage the Video Library', 'ap-builder' ) ) : null
			),
			el( 'p', { className: 'ap-builder__meta' }, __( 'Click a video to add it to the page. “Feature here” shows it in this page’s video spot. Where else a video shows (the Video Tutorials page, an integration page) is set in the Video Library.', 'ap-builder' ) ),
			state.loading || state.error ? el( Loading, { state: state } ) : null,
			el(
				'div',
				{ className: 'ap-builder__grid' },
				list.map( function ( v ) {
					var onHere = ( v.pages || [] ).indexOf( pageId ) !== -1;
					return el( Card, {
						key: v.id,
						title: v.title,
						meta: ( v.places || [] )
							.map( function ( p ) {
								return placeName[ p ] || p;
							} )
							.concat( onHere ? [ __( 'featured here', 'ap-builder' ) ] : [] )
							.join( ' · ' ) || __( 'not placed yet', 'ap-builder' ),
						media: el( 'span', { className: 'ap-builder__thumb' }, v.thumb ? el( 'img', { src: v.thumb, alt: '', loading: 'lazy' } ) : null ),
						onClick: function () {
							insert( [ blocks.createBlock( 'ap-builder/videos', { mode: 'pick', ids: [ v.id ], look: 'band' } ) ], v.title );
						},
						actions: [
							el( C.Button, { key: 'here', variant: 'secondary', size: 'small', onClick: function () {
								toggleHere( v, ! onHere );
							} }, onHere ? __( 'Remove from here', 'ap-builder' ) : __( 'Feature here', 'ap-builder' ) ),
						],
					} );
				} )
			),
			! state.loading && ! list.length ? el( 'p', { className: 'ap-builder__meta' }, __( 'No videos yet. Paste a YouTube link above, or import the site’s videos under Video Library → Settings.', 'ap-builder' ) ) : null
		);
	}

	/* The Video Library block's editor: its settings in the sidebar, the real render as the preview. */
	function VideosEdit( p ) {
		var a = p.attributes;
		var set = p.setAttributes;
		var ref = useRef( null );
		var lib = useFetch( NS + '/videos' );
		useEffect( function () {
			if ( ref.current ) {
				ensureKitCss( ref.current.ownerDocument, [ 'page-videos', 'media-band' ] );
			}
		}, [] );
		var places = ( ( lib.data && lib.data.places ) || [] ).map( function ( t ) {
			return { value: t.slug, label: t.name };
		} );
		var videos = ( lib.data && lib.data.videos ) || [];
		function field( key, label, area ) {
			return el( area ? C.TextareaControl : C.TextControl, {
				key: key,
				label: label,
				value: a[ key ] || '',
				onChange: function ( v ) {
					var patch = {};
					patch[ key ] = v;
					set( patch );
				},
			} );
		}
		return el(
			Fragment,
			null,
			el(
				BE.InspectorControls,
				null,
				el(
					C.PanelBody,
					{ title: __( 'Which videos', 'ap-builder' ), initialOpen: true },
					el( C.SelectControl, {
						label: __( 'Show', 'ap-builder' ),
						value: a.mode,
						options: [
							{ value: 'place', label: __( 'The videos of a placement', 'ap-builder' ) },
							{ value: 'page', label: __( 'The videos featured on this page', 'ap-builder' ) },
							{ value: 'pick', label: __( 'Videos I pick', 'ap-builder' ) },
						],
						onChange: function ( v ) {
							set( { mode: v } );
						},
					} ),
					a.mode === 'place'
						? el( C.SelectControl, { label: __( 'Placement', 'ap-builder' ), value: a.place, options: places.length ? places : [ { value: 'tutorials', label: 'Video Tutorials page' } ], onChange: function ( v ) {
								set( { place: v } );
						  } } )
						: null,
					a.mode === 'pick'
						? el(
								'div',
								{ className: 'ap-builder__pick' },
								videos.map( function ( v ) {
									var ids = a.ids || [];
									return el( C.CheckboxControl, {
										key: v.id,
										label: v.title,
										checked: ids.indexOf( v.id ) !== -1,
										onChange: function ( on ) {
											set( { ids: on ? ids.concat( [ v.id ] ) : ids.filter( function ( i ) {
												return i !== v.id;
											} ) } );
										},
									} );
								} )
						  )
						: null,
					el( C.TextControl, {
						type: 'number',
						label: __( 'At most (0 = all)', 'ap-builder' ),
						value: a.limit || 0,
						onChange: function ( v ) {
							set( { limit: parseInt( v, 10 ) || 0 } );
						},
					} )
				),
				el(
					C.PanelBody,
					{ title: __( 'Look', 'ap-builder' ), initialOpen: true },
					el( C.SelectControl, {
						label: __( 'Layout', 'ap-builder' ),
						value: a.look,
						options: [
							{ value: 'theater', label: __( 'Video gallery (player + playlist, as /tutorials/)', 'ap-builder' ) },
							{ value: 'band', label: __( 'One video in a band (the first video)', 'ap-builder' ) },
						],
						onChange: function ( v ) {
							set( { look: v } );
						},
					} ),
					el( C.SelectControl, {
						label: __( 'Background', 'ap-builder' ),
						value: a.tone,
						options: [
							{ value: 'white', label: __( 'White', 'ap-builder' ) },
							{ value: 'soft', label: __( 'Soft off-white panel', 'ap-builder' ) },
						],
						onChange: function ( v ) {
							set( { tone: v } );
						},
					} ),
					field( 'eyebrow', __( 'Eyebrow label', 'ap-builder' ) ),
					field( 'title', a.look === 'band' ? __( 'Heading (empty = the video’s title)', 'ap-builder' ) : __( 'Heading', 'ap-builder' ) ),
					field( 'intro', a.look === 'band' ? __( 'Copy (empty = the video’s description)', 'ap-builder' ) : __( 'Intro', 'ap-builder' ), true ),
					a.look === 'band' ? field( 'cta_label', __( 'Button text', 'ap-builder' ) ) : field( 'playlist_title', __( 'Playlist label', 'ap-builder' ) ),
					a.look === 'band' ? field( 'cta_url', __( 'Button link', 'ap-builder' ) ) : null
				)
			),
			el(
				'div',
				BE.useBlockProps( { ref: ref } ),
				el( wp.serverSideRender, {
					block: 'ap-builder/videos',
					attributes: a,
					httpMethod: 'POST',
					EmptyResponsePlaceholder: function () {
						return el(
							C.Placeholder,
							{ icon: 'video-alt3', label: __( 'Video Library', 'ap-builder' ) },
							a.mode === 'page'
								? __( 'No videos are featured on this page yet. Use “Feature here” in the Videos cabinet below, or pick this page under a video’s “Where it shows” in the Video Library.', 'ap-builder' )
								: a.mode === 'pick'
								? __( 'Pick videos in the block sidebar.', 'ap-builder' )
								: __( 'No published videos have this placement yet.', 'ap-builder' )
						);
					},
				} )
			)
		);
	}

	if ( wp.serverSideRender && ! blocks.getBlockType( 'ap-builder/videos' ) ) {
		blocks.registerBlockType( 'ap-builder/videos', {
			apiVersion: 3, // Like every block on the site: one below 3 takes the editor out of its iframe.
			title: __( 'Video Library', 'ap-builder' ),
			icon: 'video-alt3',
			category: 'media',
			edit: VideosEdit,
			save: function () {
				return null;
			},
		} );
	}

	/* ---- Animations ------------------------------------------------------ */

	var ANIM_GROUPS = { ambient: __( 'Always moving', 'ap-builder' ), hover: __( 'On hover', 'ap-builder' ), scroll: __( 'When scrolled to', 'ap-builder' ) };

	function currentAnimation( className ) {
		var m = String( className || '' ).match( /(?:^|\s)(ap-anim-[a-z-]+)/ );
		return m ? m[ 1 ] : '';
	}

	function setAnimation( selected, cls ) {
		var rest = String( selected.attributes.className || '' )
			.split( /\s+/ )
			.filter( function ( c ) {
				return c && c.indexOf( 'ap-anim-' ) !== 0;
			} );
		if ( cls ) {
			rest.push( cls );
		}
		wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( selected.clientId, { className: rest.join( ' ' ) || undefined } );
	}

	function Animations( props ) {
		var selected = useSelectedBlock();
		var anims = ( CFG.animations || [] ).filter( function ( a ) {
			return matches( props.query, [ a.label, a.note, a.group ] );
		} );
		var supports = selected && blocks.hasBlockSupport( selected.name, 'customClassName', true );
		var current = selected ? currentAnimation( selected.attributes.className ) : '';
		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'ap-builder__banner' + ( selected ? ' is-replace' : '' ) },
				selected
					? el(
							'span',
							null,
							__( 'Click an animation to apply it to:', 'ap-builder' ) + ' ',
							el( 'strong', null, blockTitle( selected.name ) ),
							current ? ' (' + __( 'now:', 'ap-builder' ) + ' ' + current.replace( 'ap-anim-', '' ) + ')' : ''
					  )
					: el( 'span', null, __( 'Select a block on the page (an image, a visual, a box, a whole section), then click an animation.', 'ap-builder' ) ),
				selected && current
					? el( C.Button, { variant: 'link', isDestructive: true, onClick: function () {
							setAnimation( selected, '' );
							notice( 'success', __( 'Animation removed.', 'ap-builder' ) );
					  } }, __( 'Remove animation', 'ap-builder' ) )
					: null
			),
			Object.keys( ANIM_GROUPS ).map( function ( group ) {
				var list = anims.filter( function ( a ) {
					return a.group === group;
				} );
				if ( ! list.length ) {
					return null;
				}
				return el(
					'div',
					{ key: group, className: 'ap-builder__group' },
					el( 'h3', { className: 'ap-builder__group-title' }, ANIM_GROUPS[ group ] ),
					group === 'scroll' ? el( 'p', { className: 'ap-builder__meta' }, __( 'Runs on the live page as visitors scroll; use it below the first screen. In the editor the block stays in place.', 'ap-builder' ) ) : null,
					el(
						'div',
						{ className: 'ap-builder__grid is-small' },
						list.map( function ( a ) {
							return el( Card, {
								key: a.class,
								title: a.label + ( current === a.class ? ' ✓' : '' ),
								meta: a.note,
								media: el( 'span', { className: 'ap-builder__thumb is-anim' }, el( 'span', { className: 'ap-builder__demo ' + a.class.replace( 'ap-anim-', 'ap-demo-' ) } ) ),
								onClick: function () {
									if ( ! selected ) {
										notice( 'warning', __( 'Select a block on the page first.', 'ap-builder' ) );
										return;
									}
									if ( ! supports ) {
										notice( 'warning', __( 'This kind of block cannot take an animation. Wrap it in a Container and animate the container.', 'ap-builder' ) );
										return;
									}
									setAnimation( selected, a.class );
									notice( 'success', a.label + ' ' + __( 'applied.', 'ap-builder' ) );
								},
							} );
						} )
					)
				);
			} )
		);
	}

	/* ---- Containers ------------------------------------------------------ */

	function starter( dark ) {
		return [
			blocks.createBlock( 'core/heading', { level: 2, content: __( 'Add a heading', 'ap-builder' ) } ),
			blocks.createBlock( 'core/paragraph', { content: __( 'Write something here, or add any block: text, images, visuals, columns, a section.', 'ap-builder' ) } ),
			blocks.createBlock( 'core/buttons', {}, [
				blocks.createBlock( 'core/button', { text: __( 'Free Demo', 'ap-builder' ), url: '/get-a-demo/', className: dark ? 'is-style-outline' : undefined } ),
			] ),
		];
	}

	function twoColumns() {
		return [
			blocks.createBlock( 'core/columns', { verticalAlignment: 'center' }, [
				blocks.createBlock( 'core/column', {}, [ blocks.createBlock( 'core/image', {} ) ] ),
				blocks.createBlock( 'core/column', {}, starter( false ) ),
			] ),
		];
	}

	var CONTAINERS = [
		{ id: 'card', label: __( 'White card', 'ap-builder' ), note: __( 'Bordered white box with a soft shadow.', 'ap-builder' ), attrs: { look: 'card' } },
		{ id: 'lavender', label: __( 'Lavender panel', 'ap-builder' ), note: __( 'Soft indigo panel.', 'ap-builder' ), attrs: { look: 'lavender' } },
		{ id: 'gradient', label: __( 'Gradient band', 'ap-builder' ), note: __( 'Indigo-to-teal band across the page, centred text.', 'ap-builder' ), attrs: { look: 'gradient', width: 'full', padding: 'xl', text_align: 'center' }, dark: true },
		{ id: 'navy', label: __( 'Navy band', 'ap-builder' ), note: __( 'Deep navy band across the page.', 'ap-builder' ), attrs: { look: 'navy', width: 'full', padding: 'xl', text_align: 'center' }, dark: true },
		{ id: 'glow', label: __( 'Navy band with glow', 'ap-builder' ), note: __( 'Navy with an indigo glow rising from the bottom.', 'ap-builder' ), attrs: { look: 'navy', width: 'full', padding: 'xl', text_align: 'center', decoration: 'glow' }, dark: true },
		{ id: 'glass', label: __( 'Glass card', 'ap-builder' ), note: __( 'Frosted card over soft orbs.', 'ap-builder' ), attrs: { look: 'glass', decoration: 'orbs' } },
		{ id: 'wash', label: __( 'Soft colour wash', 'ap-builder' ), note: __( 'The blog header’s pink, indigo and teal wash.', 'ap-builder' ), attrs: { look: 'wash', width: 'wide', padding: 'xl', text_align: 'center' } },
		{ id: 'outline', label: __( 'Outline box', 'ap-builder' ), note: __( 'Indigo outline, no fill.', 'ap-builder' ), attrs: { look: 'outline' } },
		{ id: 'panda', label: __( 'Card with panda', 'ap-builder' ), note: __( 'White card, the panda peeking from the corner.', 'ap-builder' ), attrs: { look: 'card', decoration: 'panda' } },
		{ id: 'grid', label: __( 'Dot-grid panel', 'ap-builder' ), note: __( 'Lavender panel with a fading dot grid.', 'ap-builder' ), attrs: { look: 'lavender', decoration: 'grid' } },
		{ id: 'orbs', label: __( 'Panel with orbs', 'ap-builder' ), note: __( 'Lavender panel with indigo and teal orbs.', 'ap-builder' ), attrs: { look: 'lavender', decoration: 'orbs' } },
		{ id: 'columns', label: __( 'Card: image + text', 'ap-builder' ), note: __( 'Two columns in a card: an image beside a heading, text and button.', 'ap-builder' ), attrs: { look: 'card' }, inner: twoColumns },
		{ id: 'image', label: __( 'Photo background', 'ap-builder' ), note: __( 'A photo behind the content with a dark wash. You pick the photo.', 'ap-builder' ), attrs: { look: 'plain', width: 'wide', padding: 'xl', overlay: 'dark' }, dark: true, needsImage: true },
	];

	function containerBlock( preset, image ) {
		var attrs = Object.assign( {}, preset.attrs );
		if ( image ) {
			attrs.background_image = image;
		}
		if ( attrs.width !== 'full' && CFG.postType !== 'page' ) {
			attrs.width = attrs.width === 'wide' ? 'content' : 'narrow'; // Articles: the box stays in the text column.
		}
		return blocks.createBlock( 'assetpanda/container', attrs, preset.inner ? preset.inner() : starter( !! preset.dark ) );
	}

	function addContainer( preset ) {
		if ( ! preset.needsImage || ! window.wp.media ) {
			insert( [ containerBlock( preset ) ], preset.label );
			return;
		}
		var frame = window.wp.media( {
			title: __( 'Choose the background photo', 'ap-builder' ),
			button: { text: __( 'Use this photo', 'ap-builder' ) },
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			insert( [ containerBlock( preset, att && att.url ) ], preset.label );
		} );
		frame.open();
	}

	function Containers( props ) {
		if ( ! blocks.getBlockType( 'assetpanda/container' ) ) {
			return el( C.Notice, { status: 'warning', isDismissible: false }, __( 'Containers need the Asset Panda UI Kit 1.3.179 or newer.', 'ap-builder' ) );
		}
		var list = CONTAINERS.filter( function ( p ) {
			return matches( props.query, [ p.label, p.note ] );
		} );
		return el(
			Fragment,
			null,
			el( 'p', { className: 'ap-builder__meta' }, __( 'A container is a styled box you can put anything in. Add one, then type in it or drop visuals, columns and sections inside. Change its look, width, corners and decoration in the block sidebar.', 'ap-builder' ) ),
			el(
				'div',
				{ className: 'ap-builder__grid' },
				list.map( function ( p ) {
					return el( Card, {
						key: p.id,
						title: p.label,
						meta: p.note,
						media: el( Lazy, null, el( Preview, { blocks: [ containerBlock( p ) ], width: 1200 } ) ),
						onClick: function () {
							addContainer( p );
						},
					} );
				} )
			)
		);
	}

	/* ---- Sections -------------------------------------------------------- */

	var SECTION_GROUPS = [
		{ value: 'all', label: __( 'All', 'ap-builder' ) },
		{ value: 'heroes', label: __( 'Heroes', 'ap-builder' ) },
		{ value: 'features', label: __( 'Features & cards', 'ap-builder' ) },
		{ value: 'proof', label: __( 'Proof, stats & stories', 'ap-builder' ) },
		{ value: 'media', label: __( 'Media & dashboards', 'ap-builder' ) },
		{ value: 'compare', label: __( 'Comparison & pricing', 'ap-builder' ) },
		{ value: 'faq', label: __( 'FAQ', 'ap-builder' ) },
		{ value: 'cta', label: __( 'CTAs & forms', 'ap-builder' ) },
		{ value: 'other', label: __( 'Other', 'ap-builder' ) },
	];

	var markupCache = {};

	function sectionMarkup( key ) {
		if ( ! markupCache[ key ] ) {
			markupCache[ key ] = apiFetch( { path: NS + '/sections/' + key } ).then( function ( r ) {
				return r.markup;
			} );
		}
		return markupCache[ key ];
	}

	function SectionPreview( props ) {
		var s = useState( null );
		useEffect(
			function () {
				var live = true;
				sectionMarkup( props.item.key ).then(
					function ( m ) {
						if ( live ) {
							s[ 1 ]( parse( m ) );
						}
					},
					function () {
						if ( live ) {
							s[ 1 ]( [] );
						}
					}
				);
				return function () {
					live = false;
				};
			},
			[ props.item.key ]
		);
		if ( ! s[ 0 ] ) {
			return el( 'div', { className: 'ap-builder__placeholder' }, el( C.Spinner ) );
		}
		return s[ 0 ].length ? el( Preview, { blocks: s[ 0 ], width: 1440 } ) : el( 'div', { className: 'ap-builder__placeholder' }, '—' );
	}

	function addSection( item ) {
		sectionMarkup( item.key ).then(
			function ( m ) {
				insert( parse( m ), item.title );
			},
			function ( e ) {
				notice( 'error', ( e && e.message ) || __( 'That section could not be loaded.', 'ap-builder' ) );
			}
		);
	}

	function Sections( props ) {
		var state = useFetch( NS + '/sections' );
		var g = useState( 'all' );
		var shown = useState( 12 );
		useEffect(
			function () {
				shown[ 1 ]( 12 );
			},
			[ g[ 0 ], props.query ]
		);
		if ( state.loading || state.error ) {
			return el( Loading, { state: state } );
		}
		var all = state.data || [];
		var list = all.filter( function ( i ) {
			return ( g[ 0 ] === 'all' || i.group === g[ 0 ] ) && matches( props.query, [ i.title, i.heading, i.source, i.variant, i.group ] );
		} );
		return el(
			Fragment,
			null,
			el( Chips, {
				label: __( 'Kind of section', 'ap-builder' ),
				value: g[ 0 ],
				onChange: g[ 1 ],
				options: SECTION_GROUPS.map( function ( o ) {
					return {
						value: o.value,
						label: o.label,
						count: all.filter( function ( i ) {
							return o.value === 'all' || i.group === o.value;
						} ).length,
					};
				} ),
			} ),
			el( 'p', { className: 'ap-builder__meta' }, __( 'Every section design on the site, with the content of the page it comes from. Add one, then click its text and images to make it yours.', 'ap-builder' ) ),
			el(
				'div',
				{ className: 'ap-builder__grid is-wide' },
				list.slice( 0, shown[ 0 ] ).map( function ( item ) {
					return el( Card, {
						key: item.key,
						wide: true,
						title: item.title + ( item.heading ? ': ' + item.heading : '' ),
						meta: [ item.variant, __( 'from', 'ap-builder' ) + ' ' + item.source, item.uses > 1 ? item.uses + ' ' + __( 'uses', 'ap-builder' ) : '' ]
							.filter( Boolean )
							.join( ' · ' ),
						media: el( Lazy, null, el( SectionPreview, { item: item } ) ),
						onClick: function () {
							addSection( item );
						},
					} );
				} )
			),
			list.length > shown[ 0 ]
				? el( C.Button, { variant: 'secondary', onClick: function () {
						shown[ 1 ]( shown[ 0 ] + 12 );
				  } }, __( 'Show more sections', 'ap-builder' ) + ' (' + ( list.length - shown[ 0 ] ) + ')' )
				: null
		);
	}

	/* ---- Layouts --------------------------------------------------------- */

	function placeLayout( item, replace ) {
		if ( replace && ! window.confirm( __( 'Replace everything on this page with the layout? You can undo it with Ctrl+Z.', 'ap-builder' ) ) ) {
			return;
		}
		apiFetch( { path: NS + '/layouts/' + item.key } ).then(
			function ( r ) {
				var list = parse( r.markup );
				if ( replace ) {
					// Only the page's content: with the template shown, resetBlocks would replace the header and footer too.
					var pc = postContentId();
					if ( pc ) {
						wp.data.dispatch( 'core/block-editor' ).replaceInnerBlocks( pc, list );
					} else {
						wp.data.dispatch( 'core/block-editor' ).resetBlocks( list );
					}
					notice( 'success', item.label + ' ' + __( 'layout placed. Click any text or image to change it.', 'ap-builder' ) );
				} else {
					insert( list, item.label );
				}
			},
			function ( e ) {
				notice( 'error', ( e && e.message ) || __( 'That layout could not be loaded.', 'ap-builder' ) );
			}
		);
	}

	function Layouts( props ) {
		var state = useFetch( NS + '/layouts' );
		if ( state.loading || state.error ) {
			return el( Loading, { state: state } );
		}
		if ( state.data.unavailable ) {
			return el( C.Notice, { status: 'warning', isDismissible: false }, __( 'Layouts come from the AP Template Library plugin, which is not active.', 'ap-builder' ) );
		}
		var list = ( state.data.items || [] ).filter( function ( i ) {
			return matches( props.query, [ i.label, i.group, i.description, i.source ] );
		} );
		list.sort( function ( a, b ) {
			return ( b.post_type === CFG.postType ) - ( a.post_type === CFG.postType );
		} );
		return el(
			Fragment,
			null,
			el( 'p', { className: 'ap-builder__meta' }, __( 'Whole pages built from real published pages of the site. “Add” puts the layout after the selected block; “Replace page” swaps the whole page for it (Ctrl+Z undoes).', 'ap-builder' ) ),
			el(
				'div',
				{ className: 'ap-builder__grid' },
				list.map( function ( item ) {
					return el( Card, {
						key: item.key,
						title: item.label,
						meta: item.group + ' · ' + __( 'from', 'ap-builder' ) + ' ' + item.source + ( item.post_type !== CFG.postType ? ' · ' + item.post_type : '' ),
						hint: item.description,
						media: el( 'span', { className: 'ap-builder__thumb is-layout' }, item.thumb ? el( 'img', { src: item.thumb, alt: '', loading: 'lazy' } ) : el( 'span', { className: 'dashicons dashicons-layout' } ) ),
						onClick: function () {
							placeLayout( item, false );
						},
						actions: [
							el( C.Button, { key: 'add', variant: 'secondary', size: 'small', onClick: function () {
								placeLayout( item, false );
							} }, __( 'Add', 'ap-builder' ) ),
							el( C.Button, { key: 'replace', variant: 'tertiary', size: 'small', isDestructive: true, onClick: function () {
								placeLayout( item, true );
							} }, __( 'Replace page', 'ap-builder' ) ),
						],
					} );
				} )
			)
		);
	}

	/* ---- AI -------------------------------------------------------------- */

	/* Same test as the server's ap_builder_ai_not_copy(): keys that are never copy. */
	var NOT_COPY = /^(x|y|w|h|mx|my|ix|iw|zoom|left|right|top|end|height|width|anchor|slot|tone|link_tone|accent|cls|class|state|kind|key|format|fit|crop|float|flag|form_id|video_id|video_start|video_end|hover_slot|hover_bg|style|size|type|group|markup|icon|icon_svg|image|logo|background|avatar|photo|mark|className|lock|metadata|align)$|(^|_)(url|href|svg|id|w|h|image|logo|icon|alt)$/;

	function isCopy( text ) {
		return typeof text === 'string' && text.trim() !== '' && ! /^(https?:)?\/|<[a-z]/i.test( text.trim() ) && text.length <= 1200;
	}

	/* The selected block's text: path => text. Core text blocks give their content as plain text. */
	function textFields( block ) {
		var out = {};
		if ( ! block ) {
			return out;
		}
		var a = block.attributes || {};
		if ( /^core\/(paragraph|heading|button|pullquote|verse|preformatted)$/.test( block.name ) ) {
			var key = block.name === 'core/button' ? 'text' : block.name === 'core/pullquote' ? 'value' : 'content';
			var plain = String( a[ key ] || '' ).replace( /<[^>]+>/g, '' );
			if ( plain.trim() ) {
				out[ key ] = plain;
			}
			return out;
		}
		Object.keys( a ).forEach( function ( k ) {
			if ( NOT_COPY.test( k ) ) {
				return;
			}
			if ( isCopy( a[ k ] ) ) {
				out[ k ] = a[ k ];
			} else if ( Array.isArray( a[ k ] ) ) {
				a[ k ].slice( 0, 8 ).forEach( function ( row, i ) {
					if ( row && typeof row === 'object' ) {
						Object.keys( row ).forEach( function ( f ) {
							if ( ! NOT_COPY.test( f ) && isCopy( row[ f ] ) ) {
								out[ k + '.' + i + '.' + f ] = row[ f ];
							}
						} );
					}
				} );
			}
		} );
		return out;
	}

	function applyFields( block, fields ) {
		var attrs = block.attributes;
		var patch = {};
		Object.keys( fields ).forEach( function ( path ) {
			var parts = path.split( '.' );
			if ( parts.length === 1 ) {
				patch[ parts[ 0 ] ] = fields[ path ];
				return;
			}
			var key = parts[ 0 ];
			var rows = ( patch[ key ] || attrs[ key ] || [] ).map( function ( r ) {
				return Object.assign( {}, r );
			} );
			if ( rows[ +parts[ 1 ] ] ) {
				rows[ +parts[ 1 ] ][ parts[ 2 ] ] = fields[ path ];
			}
			patch[ key ] = rows;
		} );
		wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( block.clientId, patch );
	}

	function AI() {
		var status = useFetch( NS + '/ai/status' );
		var mode = useState( 'build' );
		var prompt = useState( '' );
		var busy = useState( false );
		var result = useState( null );
		var error = useState( null );
		var selectedId = useSelect( function ( select ) {
			return select( 'core/block-editor' ).getSelectedBlockClientId();
		}, [] );

		if ( status.loading || status.error ) {
			return el( Loading, { state: status } );
		}
		if ( status.data.status !== 'ready' ) {
			return el(
				C.Notice,
				{ status: 'info', isDismissible: false },
				el( 'p', null, el( 'strong', null, __( 'The AI helper needs an AI provider.', 'ap-builder' ) ) ),
				el(
					'p',
					null,
					status.data.status === 'unsupported'
						? __( 'This WordPress version has no AI client (WordPress 7.0 or newer has it).', 'ap-builder' )
						: __( 'An administrator connects one under Settings → Connectors (Anthropic Claude, Google Gemini or OpenAI) by installing the connector and adding its API key. The helper then works for everyone who edits pages.', 'ap-builder' )
				),
				status.data.status !== 'unsupported' ? el( 'p', null, el( 'a', { href: status.data.connectors || CFG.connectors, target: '_blank', rel: 'noopener' }, __( 'Open Settings → Connectors', 'ap-builder' ) ) ) : null
			);
		}

		var selected = selectedId ? editor().getBlock( selectedId ) : null;

		function run() {
			busy[ 1 ]( true );
			error[ 1 ]( null );
			result[ 1 ]( null );
			var data = { mode: mode[ 0 ], prompt: prompt[ 0 ] };
			if ( mode[ 0 ] === 'rewrite' ) {
				data.fields = textFields( selected );
			}
			apiFetch( { path: NS + '/ai', method: 'POST', data: data } )
				.then( function ( r ) {
					result[ 1 ]( Object.assign( { mode: mode[ 0 ], clientId: selected && selected.clientId }, r ) );
				} )
				.catch( function ( e ) {
					error[ 1 ]( ( e && e.message ) || 'Error' );
				} )
				.finally( function () {
					busy[ 1 ]( false );
				} );
		}

		var fieldsNow = mode[ 0 ] === 'rewrite' ? textFields( selected ) : {};
		var canRun = ! busy[ 0 ] && ( mode[ 0 ] === 'build' ? prompt[ 0 ].trim().length > 4 : Object.keys( fieldsNow ).length > 0 );
		var r = result[ 0 ];

		return el(
			'div',
			{ className: 'ap-builder__ai' },
			el( Chips, {
				label: __( 'What the AI does', 'ap-builder' ),
				value: mode[ 0 ],
				onChange: function ( v ) {
					mode[ 1 ]( v );
					result[ 1 ]( null );
				},
				options: [
					{ value: 'build', label: __( 'Build sections from a description', 'ap-builder' ) },
					{ value: 'rewrite', label: __( 'Rewrite the selected block', 'ap-builder' ) },
				],
			} ),
			mode[ 0 ] === 'rewrite'
				? el(
						'p',
						{ className: 'ap-builder__meta' },
						selected
							? __( 'Selected:', 'ap-builder' ) + ' ' + blockTitle( selected.name ) + ' — ' + Object.keys( fieldsNow ).length + ' ' + __( 'text fields', 'ap-builder' )
							: __( 'Select a block on the page first (a paragraph, a heading, or a whole section).', 'ap-builder' )
				  )
				: el( 'p', { className: 'ap-builder__meta' }, __( 'The AI picks from the site’s own section designs and writes their text, so what it builds always matches the site. It never invents numbers, customers or quotes.', 'ap-builder' ) ),
			el( C.TextareaControl, {
				label: mode[ 0 ] === 'build' ? __( 'Describe what you need', 'ap-builder' ) : __( 'How should it change? (optional)', 'ap-builder' ),
				placeholder:
					mode[ 0 ] === 'build'
						? __( 'e.g. A section for school districts about finding lost Chromebooks, with three benefits and a demo button', 'ap-builder' )
						: __( 'e.g. Shorter and friendlier, for IT managers', 'ap-builder' ),
				value: prompt[ 0 ],
				rows: 3,
				onChange: prompt[ 1 ],
			} ),
			el( C.Button, { variant: 'primary', isBusy: busy[ 0 ], disabled: ! canRun, onClick: run }, busy[ 0 ] ? __( 'Working…', 'ap-builder' ) : __( 'Generate', 'ap-builder' ) ),
			error[ 0 ] ? el( C.Notice, { status: 'error', isDismissible: false }, error[ 0 ] ) : null,
			r && r.mode === 'build'
				? el(
						'div',
						{ className: 'ap-builder__result' },
						r.note ? el( 'p', null, r.note ) : null,
						el( Preview, { blocks: parse( r.markup ), width: 1440 } ),
						el(
							'div',
							{ className: 'ap-builder__row' },
							el( C.Button, { variant: 'primary', onClick: function () {
								insert( parse( r.markup ), __( 'AI sections', 'ap-builder' ) );
							} }, __( 'Add to the page', 'ap-builder' ) ),
							el( C.Button, { variant: 'secondary', onClick: run, disabled: busy[ 0 ] }, __( 'Try again', 'ap-builder' ) )
						)
				  )
				: null,
			r && r.mode === 'rewrite'
				? el(
						'div',
						{ className: 'ap-builder__result' },
						Object.keys( r.fields || {} ).length
							? el(
									'table',
									{ className: 'ap-builder__diff' },
									el(
										'tbody',
										null,
										Object.keys( r.fields ).map( function ( p ) {
											return el( 'tr', { key: p }, el( 'th', null, p ), el( 'td', null, el( 'del', null, fieldsNow[ p ] || '' ), el( 'ins', null, r.fields[ p ] ) ) );
										} )
									)
							  )
							: el( 'p', null, __( 'The AI kept the text as it is.', 'ap-builder' ) ),
						el(
							'div',
							{ className: 'ap-builder__row' },
							el( C.Button, {
								variant: 'primary',
								disabled: ! Object.keys( r.fields || {} ).length,
								onClick: function () {
									var block = editor().getBlock( r.clientId );
									if ( ! block ) {
										notice( 'error', __( 'That block is no longer on the page.', 'ap-builder' ) );
										return;
									}
									applyFields( block, r.fields );
									result[ 1 ]( null );
									notice( 'success', __( 'Text updated. Ctrl+Z undoes it.', 'ap-builder' ) );
								},
							}, __( 'Apply to the block', 'ap-builder' ) ),
							el( C.Button, { variant: 'secondary', onClick: run, disabled: busy[ 0 ] }, __( 'Try again', 'ap-builder' ) )
						)
				  )
				: null
		);
	}

	/* ---- The library ----------------------------------------------------- */

	var CABINETS = [
		{ name: 'visuals', title: __( 'Visuals', 'ap-builder' ), view: Visuals },
		{ name: 'videos', title: __( 'Videos', 'ap-builder' ), view: Videos },
		{ name: 'animations', title: __( 'Animations', 'ap-builder' ), view: Animations },
		{ name: 'containers', title: __( 'Containers', 'ap-builder' ), view: Containers },
		{ name: 'sections', title: __( 'Sections', 'ap-builder' ), view: Sections },
		{ name: 'layouts', title: __( 'Layouts', 'ap-builder' ), view: Layouts },
		{ name: 'ai', title: __( 'AI', 'ap-builder' ), view: AI },
	];

	function Library() {
		var q = useState( '' );
		var tab = useState( function () {
			try {
				return window.localStorage.getItem( 'ap-builder-tab' ) || 'sections';
			} catch ( e ) {
				return 'sections';
			}
		} );
		var cabinet = CABINETS.filter( function ( c ) {
			return c.name === tab[ 0 ];
		} )[ 0 ] || CABINETS[ 4 ];
		return el(
			'div',
			{ className: 'ap-builder__app' },
			el(
				'div',
				{ className: 'ap-builder__head' },
				el(
					'div',
					{ className: 'ap-builder__tabs', role: 'tablist' },
					CABINETS.map( function ( c ) {
						return el(
							'button',
							{
								key: c.name,
								type: 'button',
								role: 'tab',
								'aria-selected': c.name === cabinet.name,
								className: 'ap-builder__tab' + ( c.name === cabinet.name ? ' is-active' : '' ),
								onClick: function () {
									tab[ 1 ]( c.name );
									try {
										window.localStorage.setItem( 'ap-builder-tab', c.name );
									} catch ( e ) {}
								},
							},
							c.title
						);
					} )
				),
				cabinet.name !== 'ai'
					? el( C.SearchControl, { className: 'ap-builder__search', value: q[ 0 ], onChange: q[ 1 ], placeholder: __( 'Search', 'ap-builder' ) + ' ' + cabinet.title.toLowerCase() + '…', __nextHasNoMarginBottom: true } )
					: null
			),
			el( 'div', { className: 'ap-builder__body', role: 'tabpanel' }, el( cabinet.view, { query: q[ 0 ], key: cabinet.name } ) )
		);
	}

	/*
	 * The bottom pane starts closed and, with no saved height, grows over the
	 * whole page. Give it 42% of the screen, and open it on a person's first
	 * visit; after that WordPress keeps whatever they set (per user).
	 */
	function preparePane() {
		var prefs = wp.data.select( 'core/preferences' );
		var set = wp.data.dispatch( 'core/preferences' );
		if ( ! prefs || ! set ) {
			return;
		}
		if ( ! prefs.get( 'core/edit-post', 'metaBoxesMainOpenHeight' ) ) {
			set.set( 'core/edit-post', 'metaBoxesMainOpenHeight', Math.round( window.innerHeight * 0.42 ) );
		}
		try {
			if ( window.localStorage.getItem( 'ap-builder-pane' ) ) {
				return;
			}
			window.localStorage.setItem( 'ap-builder-pane', '1' );
		} catch ( e ) {
			return;
		}
		set.set( 'core/edit-post', 'metaBoxesMainIsOpen', true );
	}

	function mount() {
		var root = document.getElementById( 'ap-builder-library-root' );
		if ( ! root || root.getAttribute( 'data-mounted' ) ) {
			return !! root;
		}
		root.setAttribute( 'data-mounted', '1' );
		preparePane();
		if ( wp.element.createRoot ) {
			wp.element.createRoot( root ).render( el( Library ) );
		} else {
			wp.element.render( el( Library ), root );
		}
		return true;
	}

	// The meta box area is drawn after the editor starts; wait for the box.
	wp.domReady
		? wp.domReady( function () {
				if ( mount() ) {
					return;
				}
				var tries = 0;
				var t = window.setInterval( function () {
					if ( mount() || ++tries > 120 ) {
						window.clearInterval( t );
					}
				}, 500 );
		  } )
		: window.addEventListener( 'load', mount );
} )( window.wp );
