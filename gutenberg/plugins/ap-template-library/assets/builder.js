/**
 * AP Template Library — the builder screen.
 *
 * Plain JS over the WordPress globals (wp.media for images, wp.apiFetch for the REST
 * routes); no build step. Data comes from window.AP_TL_BUILDER (printed by PHP).
 *
 * Every box is a top-level block of the reference page:
 *   kit boxes  (assetpanda/*) edit the block's attributes; fields are chosen from the
 *              attribute's registered type and the value (text, long text, image picker,
 *              link, select, checkbox, repeater, nested fields, JSON for the rest);
 *   core boxes edit a few core blocks (text, list, image, button, embed, HTML) and keep
 *              everything else as it is.
 * "Send to Draft" posts { template, page, blocks: [{ref, name, attrs} | {ref, edits}] }.
 *
 * Nothing user-supplied is ever written with innerHTML.
 */
( function () {
	'use strict';

	var data = window.AP_TL_BUILDER;
	var root = document.getElementById( 'ap-tl-builder' );
	if ( ! data || ! root ) {
		return;
	}
	var T = data.i18n || {};

	/* ------------------------------------------------------------------ helpers */

	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var n = 0;
		return String( str || '' ).replace( /%(\d+)\$s|%s|%d/g, function ( m, pos ) {
			var v = pos ? args[ Number( pos ) - 1 ] : args[ n++ ];
			return v === undefined ? '' : String( v );
		} );
	}

	function clone( v ) {
		return v === undefined ? undefined : JSON.parse( JSON.stringify( v ) );
	}

	function isObj( v ) {
		return v !== null && typeof v === 'object' && ! Array.isArray( v );
	}

	function typeOf( v ) {
		if ( Array.isArray( v ) ) {
			return 'array';
		}
		if ( v === null || v === undefined ) {
			return 'string';
		}
		if ( typeof v === 'object' ) {
			return 'object';
		}
		return typeof v; // string | number | boolean
	}

	function isEmpty( v ) {
		return v === undefined || v === null || v === '' || ( Array.isArray( v ) && ! v.length ) || ( isObj( v ) && ! Object.keys( v ).length );
	}

	/** Element factory. Children are nodes or text (never parsed as HTML). */
	function h( tag, attrs ) {
		var el = document.createElement( tag );
		var key;
		attrs = attrs || {};
		for ( key in attrs ) {
			if ( ! Object.prototype.hasOwnProperty.call( attrs, key ) ) {
				continue;
			}
			var v = attrs[ key ];
			if ( v === null || v === undefined || v === false ) {
				continue;
			}
			if ( key === 'class' ) {
				el.className = v;
			} else if ( key === 'text' ) {
				el.textContent = v;
			} else if ( key === 'value' ) {
				el.value = v;
			} else if ( key === 'checked' ) {
				el.checked = !! v;
			} else if ( key === 'dataset' ) {
				Object.keys( v ).forEach( function ( d ) {
					el.dataset[ d ] = v[ d ];
				} );
			} else if ( key.indexOf( 'on' ) === 0 && typeof v === 'function' ) {
				el.addEventListener( key.slice( 2 ), v );
			} else if ( v === true ) {
				el.setAttribute( key, '' );
			} else {
				el.setAttribute( key, String( v ) );
			}
		}
		for ( var i = 2; i < arguments.length; i++ ) {
			append( el, arguments[ i ] );
		}
		return el;
	}

	function append( el, child ) {
		if ( child === null || child === undefined || child === false ) {
			return;
		}
		if ( Array.isArray( child ) ) {
			child.forEach( function ( c ) {
				append( el, c );
			} );
			return;
		}
		el.appendChild( child instanceof Node ? child : document.createTextNode( String( child ) ) );
	}

	function button( label, onClick, extra ) {
		extra = extra || {};
		return h(
			'button',
			{
				type: 'button',
				class: 'button ' + ( extra.class || '' ),
				'aria-label': extra.aria || null,
				title: extra.title || extra.aria || null,
				disabled: extra.disabled || null,
				onclick: function ( e ) {
					e.preventDefault();
					e.stopPropagation();
					onClick( e );
				},
			},
			label
		);
	}

	function iconButton( dashicon, aria, onClick, disabled ) {
		return h(
			'button',
			{
				type: 'button',
				class: 'button-link ap-tl-icon-btn',
				'aria-label': aria,
				title: aria,
				disabled: disabled || null,
				onclick: function ( e ) {
					e.preventDefault();
					e.stopPropagation();
					onClick( e );
				},
			},
			h( 'span', { class: 'dashicons dashicons-' + dashicon, 'aria-hidden': 'true' } )
		);
	}

	var seq = 0;
	function nextId( prefix ) {
		seq += 1;
		return ( prefix || 'ap-tl-' ) + seq;
	}

	/* ---------------------------------------------------------------- field kinds */

	var RX = {
		image: /(^|_)(image|img|photo|poster|icon|logo|background|bg|avatar|thumbnail|thumb|picture|illustration|screenshot|dashboard|person|cutout|video|mobile_cards)(_|$)/i,
		notImage: /(^|_)(alt|w|h|width|height|svg|text|style|label|title|tone|fit|position|size|class|cls|zoom|mode|color|colour)$/i,
		url: /(^|_)(url|href|link|src)$/i,
		imgValue: /\.(png|jpe?g|gif|svg|webp|avif)(\?\S*)?$/i,
		videoValue: /\.(mp4|webm|mov|m4v)(\?\S*)?$/i,
		urlish: /^((https?:)?\/\/|\/)/i,
		color: /^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i,
		long: /(^|_)(body|lede|intro|subtitle|description|desc|quote|text|content|statement|summary|answer|note|copy|paragraph|caption|bio|excerpt|message)$/i,
		svg: /(^|_)svg$/i,
		markup: /<[a-z][\s\S]*>/i,
	};

	/* Layout and geometry: shown under "Advanced", closed by default. */
	var ADVANCED = {};
	[ 'ratio', 'tablet', 'm_top', 'm_flow', 'layout', 'lines', 'lines_m', 'orbs', 'orbs_m', 'glows', 'scene_width', 'scene_height', 'veil_height', 'content_top', 'pad_top', 'pad_bottom', 'label_width', 'gap', 'title_gap', 'title_gap_m', 'stack', 'image_width', 'image_height', 'visual', 'photo_box' ].forEach( function ( k ) {
		ADVANCED[ k ] = true;
	} );

	var TITLE_KEYS = [ 'title', 'eyebrow', 'heading', 'label', 'name', 'question', 'value', 'kicker', 'text', 'alt', 'quote', 'author' ];

	function isMedia( key, s ) {
		if ( RX.imgValue.test( s ) || RX.videoValue.test( s ) ) {
			return true;
		}
		return RX.image.test( key ) && ! RX.notImage.test( key ) && ( s === '' || RX.urlish.test( s ) );
	}

	function schemaType( schema, value ) {
		var t = schema && schema.type;
		if ( ! t || Array.isArray( t ) || t === 'any' || t === 'null' ) {
			t = typeOf( value );
		}
		return t;
	}

	function rowSamples( schema, value ) {
		var out = [];
		if ( Array.isArray( value ) ) {
			out = out.concat( value );
		}
		if ( schema && Array.isArray( schema.default ) ) {
			out = out.concat( schema.default );
		}
		return out;
	}

	function kindOf( key, schema, value ) {
		schema = schema || {};
		var t = schemaType( schema, value );
		if ( schema.enum && schema.enum.length ) {
			return 'select';
		}
		if ( t === 'boolean' ) {
			return 'checkbox';
		}
		if ( t === 'number' || t === 'integer' ) {
			return 'number';
		}
		if ( t === 'array' ) {
			var rows = rowSamples( schema, value );
			if ( rows.length && rows.every( isObj ) ) {
				return 'repeater';
			}
			if ( ! rows.length && schema.shape && schema.shape.length ) {
				return 'repeater';
			}
			if ( rows.length && rows.every( function ( r ) {
				return typeof r === 'string';
			} ) ) {
				return 'strings';
			}
			return 'json';
		}
		if ( t === 'object' ) {
			if ( ( isObj( value ) && Object.keys( value ).length ) || ( isObj( schema.default ) && Object.keys( schema.default ).length ) ) {
				return 'object';
			}
			return 'json';
		}
		var s = typeof value === 'string' ? value : '';
		if ( RX.svg.test( key ) || /^\s*<svg/i.test( s ) || RX.markup.test( s ) ) {
			return 'code';
		}
		if ( RX.color.test( s.trim() ) ) {
			return 'color';
		}
		if ( isMedia( key, s ) ) {
			return 'media';
		}
		if ( RX.url.test( key ) ) {
			return 'url';
		}
		if ( RX.long.test( key ) || s.length > 90 || s.indexOf( '\n' ) !== -1 ) {
			return 'textarea';
		}
		return 'text';
	}

	function hasTextLeaf( v ) {
		if ( typeof v === 'string' ) {
			var s = v.trim();
			return s !== '' && ! RX.color.test( s ) && isNaN( Number( s ) );
		}
		if ( Array.isArray( v ) ) {
			return v.some( hasTextLeaf );
		}
		if ( isObj( v ) ) {
			return Object.keys( v ).some( function ( k ) {
				return hasTextLeaf( v[ k ] );
			} );
		}
		return false;
	}

	function labelFor( key ) {
		if ( T.labels && T.labels[ key ] ) {
			return T.labels[ key ];
		}
		return String( key )
			.replace( /_/g, ' ' )
			.replace( /\b(url|cta|svg|faq|id|seo|ai|cx|cy|rx|ry|ui)\b/gi, function ( m ) {
				return m.toUpperCase();
			} )
			.replace( /^./, function ( c ) {
				return c.toUpperCase();
			} );
	}

	var parser = new window.DOMParser();
	function plain( s, max ) {
		// DOMParser builds an inert document: no scripts run and no images load.
		var doc = parser.parseFromString( String( s || '' ), 'text/html' );
		var text = ( ( doc.body && doc.body.textContent ) || '' ).replace( /\s+/g, ' ' ).trim();
		max = max || 70;
		return text.length > max ? text.slice( 0, max - 1 ) + '…' : text;
	}

	function rowLabel( row ) {
		if ( typeof row === 'string' ) {
			return plain( row );
		}
		if ( ! isObj( row ) ) {
			return '';
		}
		for ( var i = 0; i < TITLE_KEYS.length; i++ ) {
			var v = row[ TITLE_KEYS[ i ] ];
			if ( typeof v === 'string' && v.trim() ) {
				return plain( v );
			}
		}
		return '';
	}

	/** Field keys of a list of objects, with sample values for each. */
	function rowShape( schema, rows ) {
		var keys = [];
		var samples = {};
		function add( row ) {
			if ( ! isObj( row ) ) {
				return;
			}
			Object.keys( row ).forEach( function ( k ) {
				if ( ! samples[ k ] ) {
					samples[ k ] = [];
					keys.push( k );
				}
				if ( row[ k ] !== undefined && row[ k ] !== null ) {
					samples[ k ].push( row[ k ] );
				}
			} );
		}
		( Array.isArray( rows ) ? rows : [] ).forEach( add );
		( schema && Array.isArray( schema.default ) ? schema.default : [] ).forEach( add );
		( ( schema && schema.shape ) || [] ).forEach( function ( s ) {
			if ( ! samples[ s.key ] ) {
				keys.push( s.key );
				samples[ s.key ] = [ s.type === 'number' ? 0 : s.type === 'boolean' ? false : s.type === 'array' ? [] : '' ];
			}
		} );
		return { keys: keys, samples: samples };
	}

	/** A schema for a nested value, inferred from sample values. */
	function subSchema( samples ) {
		samples = samples || [];
		var first;
		for ( var i = 0; i < samples.length; i++ ) {
			if ( ! isEmpty( samples[ i ] ) ) {
				first = samples[ i ];
				break;
			}
		}
		if ( first === undefined ) {
			first = samples[ 0 ];
		}
		var sch = { type: typeOf( first ) };
		if ( sch.type === 'array' ) {
			sch.default = [];
			samples.forEach( function ( s ) {
				if ( Array.isArray( s ) ) {
					sch.default = sch.default.concat( s );
				}
			} );
		} else if ( sch.type === 'object' ) {
			sch.default = {};
			samples.forEach( function ( s ) {
				if ( isObj( s ) ) {
					Object.keys( s ).forEach( function ( k ) {
						if ( ! ( k in sch.default ) ) {
							sch.default[ k ] = s[ k ];
						}
					} );
				}
			} );
		}
		return sch;
	}

	function firstString( samples ) {
		for ( var i = 0; i < ( samples || [] ).length; i++ ) {
			if ( typeof samples[ i ] === 'string' && samples[ i ] !== '' ) {
				return samples[ i ];
			}
		}
		return undefined;
	}

	/** An empty copy of a value: text cleared, numbers and switches kept (positions stay sensible). */
	function blankOf( v ) {
		if ( typeof v === 'string' ) {
			return '';
		}
		if ( Array.isArray( v ) ) {
			return [];
		}
		if ( isObj( v ) ) {
			var o = {};
			Object.keys( v ).forEach( function ( k ) {
				o[ k ] = blankOf( v[ k ] );
			} );
			return o;
		}
		return v === undefined ? '' : v;
	}

	function newRow( shape, rows ) {
		var last = rows.length ? rows[ rows.length - 1 ] : {};
		var row = {};
		shape.keys.forEach( function ( k ) {
			var v = isObj( last ) && k in last ? last[ k ] : shape.samples[ k ][ 0 ];
			row[ k ] = blankOf( v );
		} );
		return row;
	}

	/* -------------------------------------------------------------------- state */

	var refIndex = {};
	data.blocks.forEach( function ( b ) {
		refIndex[ b.ref ] = b;
	} );

	function boxFromServer( b ) {
		return {
			uid: nextId( 'ap-tl-box-' ),
			ref: b.ref,
			kind: b.kind,
			name: b.name,
			attrs: b.kind === 'kit' ? clone( b.attrs ) : null,
			edits: {},
			open: false,
		};
	}

	function freshPage() {
		var p = clone( data.page );
		if ( ! isObj( p.terms ) ) {
			p.terms = {};
		}
		if ( ! isObj( p.featured ) ) {
			p.featured = { id: 0, url: '', alt: '' };
		}
		return p;
	}

	var state = {
		page: freshPage(),
		boxes: data.blocks.map( boxFromServer ),
	};
	if ( state.boxes[ 0 ] ) {
		state.boxes[ 0 ].open = true;
	}

	var dirty = false;
	var invalid = {};
	var openState = {};
	var savedProgress = data.progress || null;
	var busy = false;

	function markDirty() {
		dirty = true;
	}

	window.addEventListener( 'beforeunload', function ( e ) {
		if ( dirty ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	/* ---------------------------------------------------------------- media */

	function pickMedia( type, onSelect ) {
		if ( ! window.wp || ! window.wp.media ) {
			window.alert( T.noMedia );
			return;
		}
		var frame = window.wp.media( {
			title: type === 'video' ? T.chooseVideo : T.chooseImage,
			button: { text: type === 'video' ? T.useVideo : T.useImage },
			library: { type: type || 'image' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			att.url = sitePath( att.url );
			if ( att.sizes && att.sizes.medium ) {
				att.sizes.medium.url = sitePath( att.sizes.medium.url );
			}
			onSelect( att );
		} );
		frame.open();
	}

	/**
	 * Same-site media as a root-relative path (/wp-content/uploads/…), the form the kit's own
	 * values use, so drafts survive the staging -> www move without a URL rewrite.
	 */
	function sitePath( url ) {
		try {
			var u = new URL( url, window.location.href );
			if ( u.origin === window.location.origin ) {
				return u.pathname + u.search + u.hash;
			}
		} catch ( e ) {}
		return url;
	}

	function previewSrc( url ) {
		url = String( url || '' ).trim();
		return RX.urlish.test( url ) ? url : '';
	}

	function mediaPreview( url ) {
		var src = previewSrc( url );
		if ( ! src ) {
			return h( 'span', { class: 'ap-tl-media__empty', text: T.noImage } );
		}
		if ( RX.videoValue.test( src ) ) {
			return h( 'span', { class: 'ap-tl-media__empty dashicons-before dashicons-format-video', text: src.split( '/' ).pop() } );
		}
		return h( 'img', { src: src, alt: '', loading: 'lazy' } );
	}

	/* ------------------------------------------------------------------ fields */

	/**
	 * One field.
	 *
	 * @param {string}   key    Field key.
	 * @param {Object}   schema { type, enum?, default?, shape? }.
	 * @param {Function} get    Returns the current value.
	 * @param {Function} set    Stores a value.
	 * @param {Object}   opts   { kind?, label?, help?, hint?, path, onMedia?, redraw? }.
	 * @return {HTMLElement}
	 */
	function field( key, schema, get, set, opts ) {
		opts = opts || {};
		var value = get();
		var probe = isEmpty( value ) && opts.hint !== undefined ? opts.hint : value;
		var kind = opts.kind || kindOf( key, schema, probe );
		var id = nextId( 'ap-tl-f-' );
		var label = opts.label || labelFor( key );
		var wrap = h( 'div', { class: 'ap-tl-field ap-tl-field--' + kind, dataset: { key: key, kind: kind } } );
		var help = opts.help ? h( 'p', { class: 'description ap-tl-help', text: opts.help } ) : null;

		function lab() {
			return h( 'label', { class: 'ap-tl-label', for: id }, label );
		}
		function onText( e ) {
			set( e.target.value );
			markDirty();
		}

		switch ( kind ) {
			case 'checkbox':
				wrap.appendChild(
					h(
						'label',
						{ class: 'ap-tl-check', for: id },
						h( 'input', {
							type: 'checkbox',
							id: id,
							checked: !! value,
							onchange: function ( e ) {
								set( e.target.checked );
								markDirty();
							},
						} ),
						' ',
						label
					)
				);
				append( wrap, help );
				return wrap;

			case 'select': {
				var options = ( schema.enum || [] ).slice();
				if ( value !== undefined && value !== null && options.map( String ).indexOf( String( value ) ) === -1 ) {
					options.unshift( value );
				}
				var sel = h( 'select', {
					id: id,
					onchange: function ( e ) {
						var picked = e.target.value;
						var match = ( schema.enum || [] ).filter( function ( o ) {
							return String( o ) === picked;
						} );
						set( match.length ? match[ 0 ] : picked );
						markDirty();
					},
				} );
				options.forEach( function ( o ) {
					sel.appendChild( h( 'option', { value: String( o ), text: String( o ) === '' ? T.none : String( o ) } ) );
				} );
				sel.value = String( value === undefined || value === null ? '' : value );
				append( wrap, [ lab(), sel, help ] );
				return wrap;
			}

			case 'number':
				append( wrap, [
					lab(),
					h( 'input', {
						type: 'number',
						step: 'any',
						id: id,
						class: 'small-text',
						value: value === undefined || value === null ? '' : String( value ),
						oninput: function ( e ) {
							var raw = e.target.value;
							var n = raw === '' ? 0 : Number( raw );
							if ( isFinite( n ) ) {
								set( schema.type === 'integer' ? Math.round( n ) : n );
								markDirty();
							}
						},
					} ),
					help,
				] );
				return wrap;

			case 'textarea':
			case 'code': {
				var text = typeof value === 'string' ? value : '';
				var rows = Math.min( kind === 'code' ? 12 : 10, Math.max( kind === 'code' ? 4 : 2, Math.ceil( text.length / 80 ) + ( text.match( /\n/g ) || [] ).length ) );
				append( wrap, [
					lab(),
					h( 'textarea', { id: id, rows: rows, class: 'widefat' + ( kind === 'code' ? ' code' : '' ), value: text, oninput: onText, spellcheck: kind === 'code' ? 'false' : null } ),
					kind === 'code' ? h( 'p', { class: 'description', text: T.codeNote } ) : null,
					help,
				] );
				return wrap;
			}

			case 'color': {
				var swatch = h( 'span', { class: 'ap-tl-swatch', 'aria-hidden': 'true' } );
				swatch.style.background = RX.color.test( String( value || '' ).trim() ) ? String( value ).trim() : 'transparent';
				append( wrap, [
					lab(),
					h(
						'span',
						{ class: 'ap-tl-color' },
						swatch,
						h( 'input', {
							type: 'text',
							id: id,
							class: 'regular-text code',
							value: typeof value === 'string' ? value : '',
							oninput: function ( e ) {
								set( e.target.value );
								swatch.style.background = RX.color.test( e.target.value.trim() ) ? e.target.value.trim() : 'transparent';
								markDirty();
							},
						} )
					),
					help,
				] );
				return wrap;
			}

			case 'media': {
				var isVideo = RX.videoValue.test( String( probe || '' ) ) || /video/i.test( key );
				var input = h( 'input', { type: 'text', id: id, class: 'widefat ap-tl-media__url', value: typeof value === 'string' ? value : '', placeholder: T.urlPlaceholder } );
				var thumb = h( 'div', { class: 'ap-tl-media__preview' }, mediaPreview( value ) );
				input.addEventListener( 'input', function ( e ) {
					set( e.target.value );
					markDirty();
					thumb.textContent = '';
					thumb.appendChild( mediaPreview( e.target.value ) );
				} );
				append( wrap, [
					lab(),
					h(
						'div',
						{ class: 'ap-tl-media' },
						thumb,
						h(
							'div',
							{ class: 'ap-tl-media__controls' },
							input,
							h(
								'div',
								{ class: 'ap-tl-media__buttons' },
								button( isVideo ? T.chooseVideo : T.chooseImage, function () {
									pickMedia( isVideo ? 'video' : 'image', function ( att ) {
										set( att.url );
										markDirty();
										input.value = att.url;
										thumb.textContent = '';
										thumb.appendChild( mediaPreview( att.url ) );
										if ( opts.onMedia ) {
											opts.onMedia( att );
										}
									} );
								}, { class: 'ap-tl-media__choose' } ),
								button( T.remove, function () {
									set( '' );
									markDirty();
									input.value = '';
									thumb.textContent = '';
									thumb.appendChild( mediaPreview( '' ) );
								}, { class: 'ap-tl-media__remove' } )
							)
						)
					),
					help,
				] );
				return wrap;
			}

			case 'url':
				append( wrap, [ lab(), h( 'input', { type: 'text', id: id, class: 'widefat code', value: typeof value === 'string' ? value : '', placeholder: T.linkPlaceholder, oninput: onText } ), help ] );
				return wrap;

			case 'json': {
				var area = h( 'textarea', { id: id, rows: 6, class: 'widefat code', spellcheck: 'false' } );
				area.value = JSON.stringify( value === undefined ? null : value, null, 2 );
				var err = h( 'p', { class: 'ap-tl-error', hidden: true, text: T.invalidJson } );
				area.addEventListener( 'input', function () {
					try {
						var parsed = JSON.parse( area.value );
						set( parsed );
						delete invalid[ id ];
						err.hidden = true;
						area.classList.remove( 'ap-tl-invalid' );
						markDirty();
					} catch ( e ) {
						invalid[ id ] = true;
						err.hidden = false;
						area.classList.add( 'ap-tl-invalid' );
					}
				} );
				append( wrap, [ lab(), area, err, h( 'p', { class: 'description', text: T.jsonNote } ), help ] );
				return wrap;
			}

			case 'object':
				append( wrap, [ objectFields( key, schema, get, set, label, help, opts ) ] );
				return wrap;

			case 'repeater':
				repeater( wrap, key, schema, get, set, label, help, opts );
				return wrap;

			case 'strings':
				stringList( wrap, key, get, set, label, help, opts );
				return wrap;

			default: // text
				append( wrap, [ lab(), h( 'input', { type: 'text', id: id, class: 'widefat', value: typeof value === 'string' ? value : String( value === undefined || value === null ? '' : value ), oninput: onText } ), help ] );
				return wrap;
		}
	}

	/** Fills an empty alt-text sibling from the chosen attachment. */
	function altFiller( container, key, redraw ) {
		return function ( att ) {
			var obj = container();
			if ( ! isObj( obj ) || ! att ) {
				return;
			}
			var base = key.replace( /_(url|src)$/, '' );
			var candidates = [ key + '_alt', base + '_alt', 'alt' ];
			for ( var i = 0; i < candidates.length; i++ ) {
				var k = candidates[ i ];
				if ( k in obj ) {
					if ( ! obj[ k ] && att.alt ) {
						obj[ k ] = att.alt;
						if ( redraw ) {
							redraw();
						}
					}
					return;
				}
			}
		};
	}

	/** Splits fields into content and "Position & size" (numbers) inside an item or object. */
	function fieldGroup( fields ) {
		var main = h( 'div', { class: 'ap-tl-fields' } );
		var geo = [];
		fields.forEach( function ( f ) {
			if ( f.dataset.kind === 'number' || f.dataset.kind === 'json' ) {
				geo.push( f );
			} else {
				main.appendChild( f );
			}
		} );
		if ( geo.length ) {
			main.appendChild( h( 'details', { class: 'ap-tl-geo' }, h( 'summary', { text: T.positionSize } ), h( 'div', { class: 'ap-tl-fields' }, geo ) ) );
		}
		return main;
	}

	function objectFields( key, schema, get, set, label, help, opts ) {
		var value = get();
		var keys = [];
		var samples = {};
		[ value, schema.default ].forEach( function ( src ) {
			if ( isObj( src ) ) {
				Object.keys( src ).forEach( function ( k ) {
					if ( ! samples[ k ] ) {
						samples[ k ] = [];
						keys.push( k );
					}
					samples[ k ].push( src[ k ] );
				} );
			}
		} );
		function container() {
			var o = get();
			if ( ! isObj( o ) ) {
				o = {};
				set( o );
			}
			return o;
		}
		var box = h( 'fieldset', { class: 'ap-tl-object' }, h( 'legend', { text: label } ), help );
		var redraw = function () {
			var fresh = objectFields( key, schema, get, set, label, help, opts );
			box.replaceWith( fresh );
		};
		var fields = keys.map( function ( k ) {
			return field(
				k,
				subSchema( samples[ k ] ),
				function () {
					var o = get();
					return isObj( o ) ? o[ k ] : undefined;
				},
				function ( v ) {
					container()[ k ] = v;
				},
				{ hint: firstString( samples[ k ] ), path: opts.path + '.' + k, onMedia: altFiller( container, k, redraw ) }
			);
		} );
		box.appendChild( fieldGroup( fields ) );
		return box;
	}

	function moveIn( list, from, to ) {
		if ( to < 0 || to >= list.length ) {
			return false;
		}
		list.splice( to, 0, list.splice( from, 1 )[ 0 ] );
		return true;
	}

	function repeater( wrap, key, schema, get, set, label, help, opts ) {
		function rows() {
			var r = get();
			if ( ! Array.isArray( r ) ) {
				r = [];
				set( r );
			}
			return r;
		}
		function draw() {
			wrap.textContent = '';
			var list = rows();
			var shape = rowShape( schema, list );
			wrap.appendChild( h( 'div', { class: 'ap-tl-repeater__head' }, h( 'span', { class: 'ap-tl-label', text: label } ), h( 'span', { class: 'ap-tl-count', text: fmt( T.itemCount, list.length ) } ) ) );
			append( wrap, help );
			var holder = h( 'div', { class: 'ap-tl-repeater__list' } );
			list.forEach( function ( row, i ) {
				holder.appendChild( itemBox( list, i, shape, draw ) );
			} );
			wrap.appendChild( holder );
			wrap.appendChild(
				button( T.addItem, function () {
					list.push( newRow( shape, list ) );
					openState[ opts.path + '/' + ( list.length - 1 ) ] = true;
					markDirty();
					draw();
					var items = wrap.querySelectorAll( ':scope > .ap-tl-repeater__list > .ap-tl-item' );
					var added = items[ items.length - 1 ];
					if ( added ) {
						var first = added.querySelector( 'input, textarea, select' );
						if ( first ) {
							first.focus();
						}
					}
				}, { class: 'ap-tl-add' } )
			);
		}

		function itemBox( list, i, shape, redraw ) {
			var row = list[ i ];
			var stateKey = opts.path + '/' + i;
			var details = h( 'details', { class: 'ap-tl-item', open: !! openState[ stateKey ] } );
			details.addEventListener( 'toggle', function () {
				openState[ stateKey ] = details.open;
			} );
			var title = h( 'span', { class: 'ap-tl-item__title', text: fmt( T.itemTitle, i + 1, rowLabel( row ) || T.item ) } );
			details.appendChild(
				h(
					'summary',
					{ class: 'ap-tl-item__summary' },
					title,
					h(
						'span',
						{ class: 'ap-tl-item__actions' },
						iconButton( 'arrow-up-alt2', T.moveUp, function () {
							if ( moveIn( list, i, i - 1 ) ) {
								swapOpen( stateKey, opts.path + '/' + ( i - 1 ) );
								markDirty();
								redraw();
							}
						}, i === 0 ),
						iconButton( 'arrow-down-alt2', T.moveDown, function () {
							if ( moveIn( list, i, i + 1 ) ) {
								swapOpen( stateKey, opts.path + '/' + ( i + 1 ) );
								markDirty();
								redraw();
							}
						}, i === list.length - 1 ),
						iconButton( 'admin-page', T.duplicate, function () {
							list.splice( i + 1, 0, clone( list[ i ] ) );
							openState[ opts.path + '/' + ( i + 1 ) ] = true;
							markDirty();
							redraw();
						} ),
						iconButton( 'trash', T.removeItem, function () {
							list.splice( i, 1 );
							markDirty();
							redraw();
						} )
					)
				)
			);
			var updateTitle = function () {
				title.textContent = fmt( T.itemTitle, i + 1, rowLabel( list[ i ] ) || T.item );
			};
			var body;
			if ( isObj( row ) ) {
				var redrawItem = function () {
					openState[ stateKey ] = true;
					redraw();
				};
				body = fieldGroup(
					shape.keys.map( function ( k ) {
						return field(
							k,
							subSchema( shape.samples[ k ] ),
							function () {
								return list[ i ][ k ];
							},
							function ( v ) {
								list[ i ][ k ] = v;
								updateTitle();
							},
							{
								hint: firstString( shape.samples[ k ] ),
								path: stateKey + '.' + k,
								onMedia: altFiller( function () {
									return list[ i ];
								}, k, redrawItem ),
							}
						);
					} )
				);
			} else {
				body = h( 'div', { class: 'ap-tl-fields' }, field( key, { type: typeOf( row ) }, function () {
					return list[ i ];
				}, function ( v ) {
					list[ i ] = v;
					updateTitle();
				}, { kind: typeOf( row ) === 'string' ? undefined : 'json', label: T.value, path: stateKey } ) );
			}
			details.appendChild( h( 'div', { class: 'ap-tl-item__body' }, body ) );
			return details;
		}

		function swapOpen( a, b ) {
			var t = openState[ a ];
			openState[ a ] = openState[ b ];
			openState[ b ] = t;
		}

		draw();
	}

	function stringList( wrap, key, get, set, label, help, opts ) {
		function list() {
			var r = get();
			if ( ! Array.isArray( r ) ) {
				r = [];
				set( r );
			}
			return r;
		}
		function draw() {
			wrap.textContent = '';
			var items = list();
			wrap.appendChild( h( 'div', { class: 'ap-tl-repeater__head' }, h( 'span', { class: 'ap-tl-label', text: label } ), h( 'span', { class: 'ap-tl-count', text: fmt( T.itemCount, items.length ) } ) ) );
			append( wrap, help );
			var ol = h( 'ol', { class: 'ap-tl-strings' } );
			items.forEach( function ( s, i ) {
				var long = String( s ).length > 90 || String( s ).indexOf( '\n' ) !== -1 || ( opts.long === true );
				var input = h( long ? 'textarea' : 'input', {
					type: long ? null : 'text',
					rows: long ? 3 : null,
					class: 'widefat',
					'aria-label': fmt( T.itemTitle, i + 1, '' ),
					value: String( s ),
					oninput: function ( e ) {
						items[ i ] = e.target.value;
						markDirty();
					},
				} );
				ol.appendChild(
					h(
						'li',
						{ class: 'ap-tl-strings__row' },
						input,
						h(
							'span',
							{ class: 'ap-tl-item__actions' },
							iconButton( 'arrow-up-alt2', T.moveUp, function () {
								if ( moveIn( items, i, i - 1 ) ) {
									markDirty();
									draw();
								}
							}, i === 0 ),
							iconButton( 'arrow-down-alt2', T.moveDown, function () {
								if ( moveIn( items, i, i + 1 ) ) {
									markDirty();
									draw();
								}
							}, i === items.length - 1 ),
							iconButton( 'trash', T.removeItem, function () {
								items.splice( i, 1 );
								markDirty();
								draw();
							} )
						)
					)
				);
			} );
			wrap.appendChild( ol );
			wrap.appendChild(
				button( T.addItem, function () {
					items.push( '' );
					markDirty();
					draw();
					var inputs = wrap.querySelectorAll( '.ap-tl-strings__row input, .ap-tl-strings__row textarea' );
					if ( inputs.length ) {
						inputs[ inputs.length - 1 ].focus();
					}
				}, { class: 'ap-tl-add' } )
			);
		}
		draw();
	}

	/* --------------------------------------------------------------- kit boxes */

	function kitBody( box ) {
		var info = ( data.types && data.types[ box.name ] ) || { attributes: {}, help: {}, shapes: {}, registered: false };
		var content = [];
		var display = [];
		var advanced = [];
		var redraw = function () {
			redrawBox( box );
		};
		Object.keys( info.attributes || {} ).forEach( function ( key ) {
			var schema = Object.assign( {}, info.attributes[ key ], { shape: info.shapes && info.shapes[ key ] } );
			var value = box.attrs[ key ];
			var probe = isEmpty( value ) && typeof schema.default === 'string' ? schema.default : value;
			var kind = kindOf( key, schema, probe );
			var el = field(
				key,
				schema,
				function () {
					return box.attrs[ key ];
				},
				function ( v ) {
					box.attrs[ key ] = v;
					if ( TITLE_KEYS.indexOf( key ) !== -1 ) {
						updateBoxSub( box );
					}
				},
				{
					kind: kind,
					help: info.help && info.help[ key ],
					path: box.uid + '.' + key,
					onMedia: altFiller( function () {
						return box.attrs;
					}, key, redraw ),
				}
			);
			if ( ADVANCED[ key ] || kind === 'number' || kind === 'json' || ( ( kind === 'object' || kind === 'repeater' ) && ! isEmpty( value ) && ! hasTextLeaf( value ) ) ) {
				advanced.push( el );
			} else if ( kind === 'select' || kind === 'checkbox' || kind === 'color' ) {
				display.push( el );
			} else {
				content.push( el );
			}
		} );

		var body = [];
		if ( info.description ) {
			body.push( h( 'p', { class: 'description ap-tl-box__desc', text: info.description } ) );
		}
		if ( ! info.registered ) {
			body.push( h( 'div', { class: 'notice notice-warning inline' }, h( 'p', { text: T.unregistered } ) ) );
		}
		if ( refIndex[ box.ref ] && refIndex[ box.ref ].inner ) {
			body.push( h( 'p', { class: 'description', text: T.innerKept } ) );
		}
		body.push( h( 'div', { class: 'ap-tl-fields' }, content.length ? content : h( 'p', { class: 'description', text: T.noContentFields } ) ) );
		if ( display.length ) {
			body.push( h( 'details', { class: 'ap-tl-group', open: true }, h( 'summary', { text: T.displayOptions } ), h( 'div', { class: 'ap-tl-fields ap-tl-fields--inline' }, display ) ) );
		}
		if ( advanced.length ) {
			body.push( h( 'details', { class: 'ap-tl-group ap-tl-group--advanced' }, h( 'summary', { text: T.advanced } ), h( 'p', { class: 'description', text: T.advancedNote } ), h( 'div', { class: 'ap-tl-fields' }, advanced ) ) );
		}
		return body;
	}

	/* -------------------------------------------------------------- core boxes */

	function editValue( box, node, f ) {
		var e = box.edits[ node.path ];
		return e && Object.prototype.hasOwnProperty.call( e, f ) ? e[ f ] : clone( node.fields[ f ] );
	}

	function setEdit( box, node, f, v ) {
		var e = Object.assign( {}, box.edits[ node.path ] || {} );
		e[ f ] = v;
		box.edits[ node.path ] = e;
		markDirty();
	}

	function hasEditor( node ) {
		return !! node.editor || ( node.children || [] ).some( hasEditor );
	}

	function coreEditor( box, node ) {
		var get = function ( f ) {
			return function () {
				return editValue( box, node, f );
			};
		};
		var put = function ( f ) {
			return function ( v ) {
				setEdit( box, node, f, v );
				if ( ! node.path ) {
					updateBoxSub( box );
				}
			};
		};
		var path = box.uid + ':' + node.path;
		switch ( node.editor ) {
			case 'text':
				return field( 'text', { type: 'string' }, get( 'text' ), put( 'text' ), { kind: 'textarea', label: T.text, help: T.inlineHtml, path: path } );
			case 'list': {
				var holder = function () {
					if ( ! box.edits[ node.path ] || ! Array.isArray( box.edits[ node.path ].items ) ) {
						setEdit( box, node, 'items', clone( node.fields.items ) || [] );
					}
					return box.edits[ node.path ].items;
				};
				return field( 'items', { type: 'array' }, holder, put( 'items' ), { kind: 'strings', label: T.listItems, help: T.inlineHtml, path: path } );
			}
			case 'image': {
				var alt = field( 'alt', { type: 'string' }, get( 'alt' ), put( 'alt' ), { kind: 'text', label: T.altText, path: path + '.alt' } );
				var img = field( 'url', { type: 'string' }, get( 'url' ), function ( v ) {
					setEdit( box, node, 'url', v );
					setEdit( box, node, 'id', 0 );
				}, {
					kind: 'media',
					label: T.image,
					path: path + '.url',
					onMedia: function ( att ) {
						setEdit( box, node, 'id', att.id || 0 );
						if ( att.alt && ! editValue( box, node, 'alt' ) ) {
							setEdit( box, node, 'alt', att.alt );
							var input = alt.querySelector( 'input' );
							if ( input ) {
								input.value = att.alt;
							}
						}
					},
				} );
				return [ img, alt ];
			}
			case 'button':
				return [
					field( 'text', { type: 'string' }, get( 'text' ), put( 'text' ), { kind: 'text', label: T.buttonText, path: path + '.t' } ),
					field( 'url', { type: 'string' }, get( 'url' ), put( 'url' ), { kind: 'url', label: T.buttonLink, path: path + '.u' } ),
				];
			case 'embed':
				return field( 'url', { type: 'string' }, get( 'url' ), put( 'url' ), { kind: 'url', label: T.embedUrl, path: path } );
			case 'html':
				return field( 'html', { type: 'string' }, get( 'html' ), put( 'html' ), { kind: 'code', label: T.html, help: data.canUnfiltered ? null : T.htmlFiltered, path: path } );
		}
		return null;
	}

	function nodeEl( box, node, depth ) {
		var cls = node.editor ? 'edit' : node.children && node.children.length ? 'group' : 'static';
		var wrap = h( 'div', { class: 'ap-tl-core ap-tl-core--' + cls + ( depth ? ' ap-tl-core--nested' : '' ) } );
		if ( depth > 0 ) {
			wrap.appendChild( h( 'div', { class: 'ap-tl-core__title', text: node.title } ) );
		}
		if ( node.editor ) {
			append( wrap, coreEditor( box, node ) );
		} else if ( node.children && node.children.length ) {
			var kids = h( 'div', { class: 'ap-tl-core__children' } );
			node.children.forEach( function ( c ) {
				kids.appendChild( nodeEl( box, c, depth + 1 ) );
			} );
			wrap.appendChild( kids );
		} else {
			wrap.appendChild(
				h(
					'div',
					{ class: 'ap-tl-static' },
					h( 'p', { class: 'ap-tl-static__preview', text: node.preview || T.noText } ),
					h( 'p', { class: 'ap-tl-static__note', text: node.kit ? T.nestedKit : T.keptAsIs } )
				)
			);
		}
		return wrap;
	}

	function coreBody( box ) {
		var ref = refIndex[ box.ref ];
		return [ nodeEl( box, ref.node, 0 ) ];
	}

	/* ------------------------------------------------------------------ boxes */

	var boxesWrap;
	var addSelect;

	function boxTitle( box ) {
		if ( box.kind === 'kit' ) {
			var info = data.types && data.types[ box.name ];
			return ( info && info.title ) || box.name;
		}
		return ( refIndex[ box.ref ] && refIndex[ box.ref ].title ) || box.name;
	}

	function boxSub( box ) {
		if ( box.kind === 'kit' ) {
			return rowLabel( box.attrs );
		}
		var node = refIndex[ box.ref ] && refIndex[ box.ref ].node;
		if ( ! node ) {
			return '';
		}
		if ( node.editor === 'text' || node.editor === 'button' ) {
			return plain( editValue( box, node, 'text' ) );
		}
		if ( node.editor === 'list' ) {
			var items = editValue( box, node, 'items' ) || [];
			return plain( items[ 0 ] || '' );
		}
		if ( node.editor === 'image' ) {
			return plain( editValue( box, node, 'alt' ) || String( editValue( box, node, 'url' ) || '' ).split( '/' ).pop() );
		}
		return plain( node.preview || '' );
	}

	function updateBoxSub( box ) {
		var el = document.getElementById( box.uid );
		var sub = el && el.querySelector( '.ap-tl-box__sub' );
		if ( sub ) {
			sub.textContent = boxSub( box );
		}
	}

	function boxBadge( box ) {
		if ( box.kind === 'kit' ) {
			return h( 'span', { class: 'ap-tl-badge ap-tl-badge--kit', text: T.badgeKit } );
		}
		var node = refIndex[ box.ref ] && refIndex[ box.ref ].node;
		return node && hasEditor( node ) ? h( 'span', { class: 'ap-tl-badge ap-tl-badge--core', text: T.badgeCore } ) : h( 'span', { class: 'ap-tl-badge ap-tl-badge--static', text: T.badgeStatic } );
	}

	function boxEl( box, index ) {
		var total = state.boxes.length;
		var section = h( 'section', { class: 'postbox ap-tl-box' + ( box.open ? '' : ' closed' ), id: box.uid, 'aria-label': fmt( T.sectionLabel, index + 1, boxTitle( box ) ) } );
		var bodyId = box.uid + '-body';
		var toggle = h(
			'button',
			{
				type: 'button',
				class: 'ap-tl-box__toggle',
				'aria-expanded': box.open ? 'true' : 'false',
				'aria-controls': bodyId,
				onclick: function () {
					box.open = ! box.open;
					redrawBox( box );
					var t = document.querySelector( '#' + box.uid + ' .ap-tl-box__toggle' );
					if ( t ) {
						t.focus();
					}
				},
			},
			h( 'span', { class: 'ap-tl-box__num', text: String( index + 1 ) } ),
			h( 'span', { class: 'ap-tl-box__names' }, h( 'span', { class: 'ap-tl-box__title', text: boxTitle( box ) } ), h( 'span', { class: 'ap-tl-box__sub', text: boxSub( box ) } ) ),
			boxBadge( box ),
			h( 'span', { class: 'dashicons ap-tl-box__chevron dashicons-arrow-' + ( box.open ? 'up' : 'down' ), 'aria-hidden': 'true' } )
		);
		var actions = h(
			'div',
			{ class: 'ap-tl-box__actions' },
			iconButton( 'arrow-up-alt2', fmt( T.moveSectionUp, boxTitle( box ) ), function () {
				moveBox( box, -1 );
			}, index === 0 ),
			iconButton( 'arrow-down-alt2', fmt( T.moveSectionDown, boxTitle( box ) ), function () {
				moveBox( box, 1 );
			}, index === total - 1 ),
			iconButton( 'admin-page', fmt( T.duplicateSection, boxTitle( box ) ), function () {
				duplicateBox( box );
			} ),
			iconButton( 'trash', fmt( T.removeSection, boxTitle( box ) ), function () {
				removeBox( box );
			} )
		);
		section.appendChild( h( 'div', { class: 'postbox-header ap-tl-box__header' }, toggle, actions ) );
		if ( box.open ) {
			var body = h( 'div', { class: 'inside ap-tl-box__body', id: bodyId } );
			append( body, box.kind === 'kit' ? kitBody( box ) : coreBody( box ) );
			section.appendChild( body );
		}
		return section;
	}

	function redrawBox( box ) {
		var old = document.getElementById( box.uid );
		if ( old ) {
			old.replaceWith( boxEl( box, state.boxes.indexOf( box ) ) );
		}
	}

	function renderBoxes() {
		boxesWrap.textContent = '';
		if ( ! state.boxes.length ) {
			boxesWrap.appendChild( h( 'div', { class: 'notice notice-warning inline' }, h( 'p', { text: T.noSections } ) ) );
		}
		state.boxes.forEach( function ( b, i ) {
			boxesWrap.appendChild( boxEl( b, i ) );
		} );
	}

	function focusBox( box, which ) {
		var el = document.getElementById( box.uid );
		if ( ! el ) {
			return;
		}
		var target = which ? el.querySelector( which ) : null;
		if ( ! target || target.disabled ) {
			target = el.querySelector( '.ap-tl-box__toggle' );
		}
		target.focus();
	}

	function moveBox( box, delta ) {
		var i = state.boxes.indexOf( box );
		if ( moveIn( state.boxes, i, i + delta ) ) {
			markDirty();
			renderBoxes();
			focusBox( box, '.ap-tl-box__actions button:nth-child(' + ( delta < 0 ? 1 : 2 ) + ')' );
			announce( fmt( T.movedTo, boxTitle( box ), state.boxes.indexOf( box ) + 1 ) );
		}
	}

	function duplicateBox( box ) {
		var i = state.boxes.indexOf( box );
		var copy = clone( box );
		copy.uid = nextId( 'ap-tl-box-' );
		copy.open = true;
		state.boxes.splice( i + 1, 0, copy );
		markDirty();
		renderBoxes();
		focusBox( copy );
		announce( fmt( T.duplicated, boxTitle( box ) ) );
	}

	function removeBox( box ) {
		if ( ! window.confirm( fmt( T.confirmRemove, boxTitle( box ) ) ) ) {
			return;
		}
		var i = state.boxes.indexOf( box );
		state.boxes.splice( i, 1 );
		markDirty();
		renderBoxes();
		var next = state.boxes[ Math.min( i, state.boxes.length - 1 ) ];
		if ( next ) {
			focusBox( next );
		}
		announce( fmt( T.removed, boxTitle( box ) ) );
	}

	/* -------------------------------------------------------------- sidebar */

	var statusEl;
	var resultEl;
	var liveEl;
	var sendBtn;
	var saveBtn;
	var progressEl;

	function announce( msg ) {
		if ( liveEl ) {
			liveEl.textContent = '';
			window.setTimeout( function () {
				liveEl.textContent = msg;
			}, 30 );
		}
	}

	function setStatus( msg, type ) {
		statusEl.textContent = '';
		if ( ! msg ) {
			return;
		}
		statusEl.appendChild( h( 'div', { class: 'notice inline notice-' + ( type || 'info' ) }, h( 'p', { text: msg } ) ) );
	}

	function setBusy( on, msg ) {
		busy = on;
		sendBtn.disabled = on;
		saveBtn.disabled = on;
		statusEl.classList.toggle( 'is-busy', on );
		if ( on ) {
			statusEl.textContent = '';
			statusEl.appendChild( h( 'p', { class: 'ap-tl-busy' }, h( 'span', { class: 'spinner is-active' } ), msg ) );
		}
	}

	function termDepths( terms ) {
		var byId = {};
		terms.forEach( function ( t ) {
			byId[ t.id ] = t;
		} );
		return function depth( t ) {
			var d = 0;
			var p = t.parent;
			while ( p && byId[ p ] && d < 10 ) {
				d += 1;
				p = byId[ p ].parent;
			}
			return d;
		};
	}

	function pageSettings() {
		var s = data.settings;
		var p = state.page;
		var body = h( 'div', { class: 'inside ap-tl-page' } );
		var titleId = nextId( 'ap-tl-p-' );
		var slugId = nextId( 'ap-tl-p-' );

		append( body, [
			h( 'p', {}, h( 'label', { class: 'ap-tl-label', for: titleId, text: T.pageTitle } ), h( 'input', { type: 'text', id: titleId, class: 'widefat ap-tl-page-title', required: true, value: p.title || '', oninput: function ( e ) {
				p.title = e.target.value;
				markDirty();
			} } ) ),
			h( 'p', {}, h( 'label', { class: 'ap-tl-label', for: slugId, text: T.slug } ), h( 'input', { type: 'text', id: slugId, class: 'widefat code', value: p.slug || '', placeholder: T.slugAuto, oninput: function ( e ) {
				p.slug = e.target.value;
				markDirty();
			} } ) ),
		] );

		if ( s.hierarchical ) {
			var parentId = nextId( 'ap-tl-p-' );
			var sel = h( 'select', { id: parentId, class: 'widefat', onchange: function ( e ) {
				p.parent = Number( e.target.value ) || 0;
				markDirty();
			} } );
			sel.appendChild( h( 'option', { value: '0', text: T.noParent } ) );
			( s.parents || [] ).forEach( function ( o ) {
				sel.appendChild( h( 'option', { value: String( o.id ), text: new Array( o.depth + 1 ).join( '— ' ) + o.title } ) );
			} );
			sel.value = String( p.parent || 0 );
			body.appendChild( h( 'p', {}, h( 'label', { class: 'ap-tl-label', for: parentId, text: T.parent } ), sel ) );
		}

		( s.taxonomies || [] ).forEach( function ( tax ) {
			var selected = Array.isArray( p.terms[ tax.name ] ) ? p.terms[ tax.name ] : [];
			p.terms[ tax.name ] = selected;
			var depth = termDepths( tax.terms );
			if ( tax.single ) {
				var id = nextId( 'ap-tl-p-' );
				var one = h( 'select', { id: id, class: 'widefat', 'data-taxonomy': tax.name, onchange: function ( e ) {
					p.terms[ tax.name ] = e.target.value ? [ Number( e.target.value ) ] : [];
					markDirty();
				} } );
				one.appendChild( h( 'option', { value: '', text: T.none } ) );
				tax.terms.forEach( function ( t ) {
					one.appendChild( h( 'option', { value: String( t.id ), text: new Array( depth( t ) + 1 ).join( '— ' ) + t.name } ) );
				} );
				one.value = selected.length ? String( selected[ 0 ] ) : '';
				body.appendChild( h( 'p', {}, h( 'label', { class: 'ap-tl-label', for: id, text: tax.label } ), one ) );
				return;
			}
			var list = h( 'ul', { class: 'ap-tl-terms', 'data-taxonomy': tax.name } );
			tax.terms.forEach( function ( t ) {
				var cid = nextId( 'ap-tl-t-' );
				var li = h(
					'li',
					{},
					h( 'input', { type: 'checkbox', id: cid, value: String( t.id ), checked: selected.indexOf( t.id ) !== -1, onchange: function ( e ) {
						var ids = p.terms[ tax.name ].filter( function ( x ) {
							return x !== t.id;
						} );
						if ( e.target.checked ) {
							ids.push( t.id );
						}
						p.terms[ tax.name ] = ids;
						markDirty();
					} } ),
					' ',
					h( 'label', { for: cid, text: t.name } )
				);
				li.style.paddingInlineStart = depth( t ) * 14 + 'px';
				list.appendChild( li );
			} );
			body.appendChild( h( 'fieldset', { class: 'ap-tl-taxonomy' }, h( 'legend', { class: 'ap-tl-label', text: tax.label } ), tax.terms.length ? list : h( 'p', { class: 'description', text: T.noTerms } ) ) );
		} );

		if ( s.excerpt ) {
			var exId = nextId( 'ap-tl-p-' );
			body.appendChild( h( 'p', {}, h( 'label', { class: 'ap-tl-label', for: exId, text: T.excerpt } ), h( 'textarea', { id: exId, rows: 3, class: 'widefat', value: p.excerpt || '', oninput: function ( e ) {
				p.excerpt = e.target.value;
				markDirty();
			} } ) ) );
		}

		if ( s.thumbnail ) {
			var holder = h( 'div', { class: 'ap-tl-featured' } );
			var drawFeatured = function () {
				holder.textContent = '';
				append( holder, [
					h( 'span', { class: 'ap-tl-label', text: T.featured } ),
					h( 'div', { class: 'ap-tl-media__preview ap-tl-media__preview--wide' }, mediaPreview( p.featured.url ) ),
					p.featured.url && ! p.featured.id ? h( 'p', { class: 'description', text: T.featuredFromReference } ) : null,
					h(
						'div',
						{ class: 'ap-tl-media__buttons' },
						button( p.featured.url ? T.replaceImage : T.chooseImage, function () {
							pickMedia( 'image', function ( att ) {
								var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
								p.featured = { id: att.id, url: url, alt: att.alt || '' };
								markDirty();
								drawFeatured();
							} );
						}, { class: 'ap-tl-featured__choose' } ),
						p.featured.url ? button( T.remove, function () {
							p.featured = { id: 0, url: '', alt: '' };
							markDirty();
							drawFeatured();
						} ) : null
					),
				] );
			};
			drawFeatured();
			body.appendChild( holder );
		}

		return h( 'div', { class: 'postbox ap-tl-panel' }, h( 'div', { class: 'postbox-header' }, h( 'h2', { class: 'hndle', text: fmt( T.pageSettings, s.post_type_label ) } ) ), body );
	}

	function publishPanel() {
		statusEl = h( 'div', { class: 'ap-tl-status', 'aria-live': 'polite' } );
		resultEl = h( 'div', { class: 'ap-tl-result' } );
		progressEl = h( 'p', { class: 'description ap-tl-progress-note' } );
		saveBtn = button( T.saveProgress, saveProgress, { class: 'ap-tl-save' } );
		sendBtn = h( 'button', { type: 'button', class: 'button button-primary button-large ap-tl-send', onclick: send }, T.sendToDraft );
		return h(
			'div',
			{ class: 'postbox ap-tl-panel ap-tl-panel--publish' },
			h( 'div', { class: 'postbox-header' }, h( 'h2', { class: 'hndle', text: T.publishPanel } ) ),
			h(
				'div',
				{ class: 'inside' },
				h( 'p', { class: 'description', text: fmt( T.createsDraft, data.settings.post_type_label ) } ),
				h( 'div', { class: 'ap-tl-publish-actions' }, saveBtn, sendBtn ),
				progressEl,
				statusEl,
				resultEl
			)
		);
	}

	function pagePayload() {
		var p = state.page;
		return {
			title: p.title || '',
			slug: p.slug || '',
			parent: p.parent || 0,
			excerpt: p.excerpt || '',
			featured: { id: p.featured.id || 0, url: p.featured.url || '' },
			terms: p.terms || {},
		};
	}

	function boxPayload( b ) {
		return b.kind === 'kit' ? { ref: b.ref, name: b.name, attrs: b.attrs } : { ref: b.ref, edits: b.edits };
	}

	function errorMessage( err ) {
		if ( err && err.message ) {
			return err.message;
		}
		return T.failed;
	}

	function send() {
		if ( busy ) {
			return;
		}
		resultEl.textContent = '';
		if ( ! String( state.page.title || '' ).trim() ) {
			setStatus( T.titleRequired, 'error' );
			var t = root.querySelector( '.ap-tl-page-title' );
			if ( t ) {
				t.focus();
			}
			return;
		}
		if ( Object.keys( invalid ).length ) {
			setStatus( T.fixJson, 'error' );
			return;
		}
		if ( ! state.boxes.length ) {
			setStatus( T.noSections, 'error' );
			return;
		}
		setBusy( true, T.sending );
		window.wp
			.apiFetch( {
				path: data.rest.draft,
				method: 'POST',
				data: { template: data.template.key, page: pagePayload(), blocks: state.boxes.map( boxPayload ) },
			} )
			.then( function ( res ) {
				setBusy( false );
				dirty = false;
				savedProgress = null;
				hideBanner();
				setStatus( '' );
				progressEl.textContent = '';
				resultEl.textContent = '';
				resultEl.appendChild(
					h(
						'div',
						{ class: 'notice notice-success inline ap-tl-success' },
						h( 'p', {}, h( 'strong', { text: T.draftCreated } ), ' ', fmt( T.draftCreatedBody, res.title || state.page.title ) ),
						h(
							'p',
							{ class: 'ap-tl-success__actions' },
							h( 'a', { class: 'button ap-tl-preview-link', href: res.preview_link, target: '_blank', rel: 'noopener' }, T.preview ),
							' ',
							h( 'a', { class: 'button button-primary ap-tl-edit-link', href: res.edit_link }, T.openEditor )
						)
					)
				);
				announce( T.draftCreated );
				resultEl.querySelector( '.ap-tl-edit-link' ).focus();
			} )
			.catch( function ( err ) {
				setBusy( false );
				setStatus( errorMessage( err ), 'error' );
			} );
	}

	function progressState() {
		return JSON.stringify( {
			v: 1,
			hash: data.hash,
			page: state.page,
			boxes: state.boxes.map( function ( b ) {
				return { ref: b.ref, kind: b.kind, name: b.name, attrs: b.attrs, edits: b.edits };
			} ),
		} );
	}

	function saveProgress() {
		if ( busy ) {
			return;
		}
		setBusy( true, T.saving );
		window.wp
			.apiFetch( { path: data.rest.progress, method: 'POST', data: { template: data.template.key, state: progressState() } } )
			.then( function () {
				setBusy( false );
				dirty = false;
				setStatus( '' );
				progressEl.textContent = fmt( T.progressSaved, new Date().toLocaleTimeString() );
				announce( progressEl.textContent );
			} )
			.catch( function ( err ) {
				setBusy( false );
				setStatus( errorMessage( err ), 'error' );
			} );
	}

	/* ----------------------------------------------------- saved progress */

	var bannerEl;

	function hideBanner() {
		if ( bannerEl ) {
			bannerEl.remove();
			bannerEl = null;
		}
	}

	function restore( saved ) {
		var st;
		try {
			st = JSON.parse( saved.state );
		} catch ( e ) {
			setStatus( T.progressUnreadable, 'error' );
			return;
		}
		var boxes = [];
		var dropped = 0;
		( st.boxes || [] ).forEach( function ( b ) {
			var ref = refIndex[ b.ref ];
			if ( ! ref || ref.kind !== b.kind || ( b.kind === 'kit' && ref.name !== b.name ) ) {
				dropped += 1;
				return;
			}
			boxes.push( {
				uid: nextId( 'ap-tl-box-' ),
				ref: b.ref,
				kind: b.kind,
				name: ref.name,
				attrs: b.kind === 'kit' ? ( isObj( b.attrs ) ? b.attrs : clone( ref.attrs ) ) : null,
				edits: b.kind === 'core' && isObj( b.edits ) ? b.edits : {},
				open: false,
			} );
		} );
		if ( boxes[ 0 ] ) {
			boxes[ 0 ].open = true;
		}
		state.boxes = boxes;
		state.page = Object.assign( freshPage(), isObj( st.page ) ? st.page : {} );
		if ( ! isObj( state.page.terms ) ) {
			state.page.terms = {};
		}
		if ( ! isObj( state.page.featured ) ) {
			state.page.featured = { id: 0, url: '', alt: '' };
		}
		dirty = false;
		renderAll();
		setStatus( dropped ? fmt( T.restoredDropped, dropped ) : T.restored, dropped ? 'warning' : 'success' );
	}

	function banner() {
		if ( ! savedProgress ) {
			return null;
		}
		bannerEl = h(
			'div',
			{ class: 'notice notice-info inline ap-tl-banner' },
			h( 'p', { text: fmt( T.progressFound, savedProgress.saved_label ) } ),
			savedProgress.hash && savedProgress.hash !== data.hash ? h( 'p', { text: T.referenceChanged } ) : null,
			h(
				'p',
				{},
				button( T.resume, function () {
					var saved = savedProgress;
					hideBanner();
					restore( saved );
				}, { class: 'button-primary ap-tl-resume' } ),
				' ',
				button( T.discard, function () {
					window.wp.apiFetch( { path: data.rest.progress + '?template=' + encodeURIComponent( data.template.key ), method: 'DELETE' } ).then( function () {
						savedProgress = null;
						hideBanner();
						announce( T.discarded );
					} ).catch( function ( err ) {
						setStatus( errorMessage( err ), 'error' );
					} );
				}, { class: 'ap-tl-discard' } )
			)
		);
		return bannerEl;
	}

	/* ----------------------------------------------------------- layout */

	function addSectionControl() {
		var id = nextId( 'ap-tl-add-' );
		addSelect = h( 'select', { id: id } );
		data.blocks.forEach( function ( b, i ) {
			var label = ( i + 1 ) + '. ' + ( b.kind === 'kit' ? ( data.types[ b.name ] && data.types[ b.name ].title ) || b.name : b.title );
			addSelect.appendChild( h( 'option', { value: String( i ), text: label } ) );
		} );
		return h(
			'div',
			{ class: 'ap-tl-add-section' },
			h( 'label', { for: id, text: T.addSectionLabel } ),
			' ',
			addSelect,
			' ',
			button( T.addSection, function () {
				var b = data.blocks[ Number( addSelect.value ) ];
				if ( ! b ) {
					return;
				}
				var box = boxFromServer( b );
				box.open = true;
				state.boxes.push( box );
				markDirty();
				renderBoxes();
				var el = document.getElementById( box.uid );
				if ( el ) {
					el.scrollIntoView( { block: 'start' } );
					focusBox( box );
				}
			}, { class: 'ap-tl-add-section__btn' } )
		);
	}

	function renderAll() {
		root.textContent = '';
		liveEl = h( 'div', { class: 'screen-reader-text', 'aria-live': 'polite' } );
		var ref = data.reference;
		var head = h(
			'div',
			{ class: 'ap-tl-builder__head' },
			h( 'a', { class: 'ap-tl-back', href: data.urls.library }, '← ', T.backToLibrary ),
			h( 'h1', { class: 'ap-tl-builder__title', text: fmt( T.builderTitle, data.template.label ) } ),
			h(
				'p',
				{ class: 'ap-tl-ref' },
				h( 'span', { class: 'ap-tl-badge', text: data.template.group_label } ),
				' ',
				T.basedOn,
				' ',
				h( 'a', { href: ref.link, target: '_blank', rel: 'noopener', class: 'ap-tl-ref-link' }, ref.title ),
				' ',
				h( 'span', { class: 'description', text: fmt( T.sectionCount, data.blocks.length ) } )
			),
			h( 'p', { class: 'description', text: T.intro } )
		);
		boxesWrap = h( 'div', { class: 'ap-tl-boxes' } );
		var toolbar = h(
			'div',
			{ class: 'ap-tl-toolbar' },
			button( T.expandAll, function () {
				state.boxes.forEach( function ( b ) {
					b.open = true;
				} );
				renderBoxes();
			} ),
			button( T.collapseAll, function () {
				state.boxes.forEach( function ( b ) {
					b.open = false;
				} );
				renderBoxes();
			} ),
			button( T.reset, function () {
				if ( ! window.confirm( T.confirmReset ) ) {
					return;
				}
				state.boxes = data.blocks.map( boxFromServer );
				if ( state.boxes[ 0 ] ) {
					state.boxes[ 0 ].open = true;
				}
				state.page = freshPage();
				markDirty();
				renderAll();
			}, { class: 'ap-tl-reset' } )
		);
		var main = h( 'div', { class: 'ap-tl-builder__main' }, toolbar, boxesWrap, addSectionControl() );
		var side = h( 'div', { class: 'ap-tl-builder__side' }, publishPanel(), pageSettings() );
		append( root, [ head, banner(), h( 'div', { class: 'ap-tl-builder__cols' }, main, side ), liveEl ] );
		renderBoxes();
	}

	renderAll();
	root.classList.add( 'is-ready' );
} )();
