/**
 * Asset Panda blocks — one shared editor for every AP block.
 *
 * Every AP block is an adapter over a kit component, so every editor is the
 * same job: show a labelled field per attribute and preview the real server
 * render. Ten copies of that would be ten places for it to rot, so this file
 * derives its controls from the block's own registered attributes — which come
 * from block.json, which is generated from the kit contract.
 *
 * What cannot be derived lives in the three maps below: friendlier labels,
 * which strings deserve a textarea, and the shape of each repeater's rows.
 *
 * Buildless: uses the global wp.* packages, so there is no wp-scripts step.
 */
( function ( blocks, element, blockEditor, components, ServerSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;

	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var Button = components.Button;

	/*
	 * Prop names are consistent across the kit, so one label map covers every
	 * block and every repeater row.
	 */
	var LABELS = {
		title: 'Heading',
		eyebrow: 'Eyebrow label',
		intro: 'Intro paragraph',
		subtitle: 'Body copy',
		lede: 'Lead line',
		kicker: 'Emphasis line',
		statement: 'Statement',
		body: 'Description',
		category: 'Category label',
		label: 'Label',
		value: 'Value',
		description: 'Description',
		quote: 'Quote',
		author: 'Author name',
		role: 'Author role',
		pillar: 'Pillar',
		before: 'Current state',
		after: 'With Asset Panda',
		accent: 'Accent colour',
		image: 'Image URL',
		image_alt: 'Image alt text',
		alt: 'Alt text',
		url: 'Link URL',
		cta_label: 'Button text',
		cta_url: 'Button URL',
		link_label: 'Link text',
		link_url: 'Link URL',
		nav_label: 'Side-nav label',
		nav_title: 'Side-nav accessible name',
		primary_label: 'Primary button text',
		primary_url: 'Primary button URL',
		secondary_label: 'Secondary button text',
		secondary_url: 'Secondary button URL',
		media_image: 'Hero image URL',
		media_alt: 'Hero image alt text',
		badges: 'Compliance badges',
		badges_label: 'Badge row label',
		trust_line: 'Trust line',
		before_title: 'Left column heading',
		after_title: 'Right column heading',
		after_note: 'Right column note',
		outcome_eyebrow: 'Outcome label',
		outcome_title: 'Outcome heading',
		outcome_body: 'Outcome copy',
		items: 'Items',
		rows: 'Rows',
		columns: 'Columns',
		theme: 'Theme',
		layout: 'Layout',
		alignment: 'Alignment',
		reveal: 'Animate on scroll',
		title_accent: 'Gradient heading line',
		closing: 'Closing line',
		pillar_title: 'Left column heading',
		logo: 'Logo URL',
		logo_alt: 'Logo alt text',
		logos: 'Client logos',
		logos_label: 'Logo strip heading',
		logos_accent: 'Logo strip heading (coloured part)',
		icon: 'Sector icon (government, manufacturing, healthcare, education, construction, nonprofit)',
		media_video: 'Hero video URL (.mp4)',
		secondary_mobile_only: 'Secondary button on phones only',
		image_hover: 'Hover preview image URL (optional)',
		q: 'Question',
		a: 'Answer',
		accent: 'Accent colour',
		marquee: 'Scroll on phones',
		open_first: 'Open the first item',
		schema: 'Output FAQ structured data',
		checks: 'Checklist (one per line)',
		tone: 'Chip tone (indigo, rose, mint, slate, amber)',
		text: 'Text',
		note: 'Closing line',
		background: 'Background image URL',
		cta_title: 'CTA heading',
		cta_accent: 'CTA heading (gradient line)',
		cta_sub: 'CTA supporting line',
		cta_logo: 'CTA mark URL',
		cta_label: 'CTA button text',
		cta_url: 'CTA button URL',
		cards: 'Callout chips',
		tips: 'Floating tip cards',
		orbs: 'Image orbs',
		rows: 'Rows',
		zoom: 'Image zoom (percent)',
		title_accent: 'Heading (gradient word)',
		sub_title: 'Closing heading',
		sub_body: 'Closing paragraph',
		quote_sub: 'Line under the quote',
		form_heading: 'Form panel heading',
		form_id: 'Form mount id',
	};

	/*
	 * Attributes that exist on the block but are not kit props. `align` is
	 * declared so AP sections can break out of a block theme's constrained
	 * layout; WordPress renders its own alignment control for it.
	 */
	var SKIP = {
		align: true,
		lock: true,
		metadata: true,
		className: true,
		style: true,
	};

	/* Headings are textareas too, so editors can place a deliberate line break. */
	var TEXTAREAS = {
		title: true,
		intro: true,
		subtitle: true,
		body: true,
		quote: true,
		statement: true,
		description: true,
		before: true,
		after: true,
		outcome_body: true,
		kicker: true,
		closing: true,
	};

	/*
	 * Repeater row shapes. The contract declares these props as `array`; it
	 * cannot describe each row, so the editor needs the shape here.
	 */
	var ROWS = {
		'assetpanda/stats.items': [ 'value', 'label', 'description' ],
		'assetpanda/awards.items': [ 'image', 'alt', 'url' ],
		'assetpanda/hero.badges': [ 'image', 'alt' ],
		'assetpanda/hero.logos': [ 'image', 'alt' ],
		'assetpanda/industries.items': [ 'title', 'body', 'image', 'image_alt', 'icon', 'link_label', 'link_url' ],
		'assetpanda/feature-cards.items': [ 'title', 'body' ],
		'assetpanda/value-props.items': [ 'title', 'body', 'accent' ],
		'assetpanda/comparison.rows': [ 'pillar', 'after', 'before' ],
		'assetpanda/case-studies.items': [
			'category', 'title', 'quote', 'author', 'role', 'logo', 'logo_alt',
			'image', 'image_alt', 'link_label', 'link_url',
		],
		'assetpanda/platform-stack.items': [
			'title', 'body', 'nav_label', 'link_label', 'link_url', 'image', 'image_alt', 'image_hover', 'accent',
		],
		'assetpanda/site-header.login_links': [ 'label', 'note', 'url' ],
		'assetpanda/trust-strip.items': [ 'image', 'alt', 'url' ],
		'assetpanda/faq.items': [ 'q', 'a' ],
		'assetpanda/sticky-list.items': [ 'title', 'icon', 'icon_alt', 'accent', 'checks' ],
		'assetpanda/problem-callouts.cards': [ 'text', 'icon', 'icon_alt', 'tone' ],
		'assetpanda/dashboard-showcase.tips': [ 'title', 'icon', 'icon_alt', 'tone', 'rows' ],
		'assetpanda/dashboard-showcase.orbs': [ 'image', 'alt', 'tone', 'zoom' ],
		'assetpanda/vertical-hero.badges': [ 'image', 'alt' ],
		'assetpanda/vertical-hero.features': [ 'label', 'icon', 'icon_svg' ],
		'assetpanda/dashboard-showcase.ctas': [ 'label', 'url' ],
		'assetpanda/hub-hero.badges': [ 'image', 'alt' ],
		'assetpanda/hub-hero.photos': [ 'image', 'alt', 'x', 'y', 'w', 'h', 'ix', 'iw' ],
		'assetpanda/hub-hero.features': [ 'label', 'icon', 'icon_svg' ],
		'assetpanda/link-cards.items': [ 'title', 'body', 'link_label', 'url', 'image', 'image_top', 'icon', 'icon_svg' ],
		'assetpanda/problem-scene.images': [ 'image', 'alt' ],
		'assetpanda/problem-scene.chips': [ 'text', 'tone', 'icon', 'icon_svg', 'left', 'right', 'top' ],
		'assetpanda/story-slider.items': [
			'title', 'quote', 'author', 'role', 'image', 'image_alt', 'logo', 'logo_alt', 'logo_w', 'logo_h',
			'logo_text', 'logo_style', 'link_label', 'link_url', 'video_id', 'video_start', 'video_end', 'video_title',
		],
		'assetpanda/capability-rows.items': [ 'title', 'body', 'link_label', 'url', 'tone', 'icon', 'icon_svg' ],
		'assetpanda/media-split.links': [ 'label', 'url' ],
		'assetpanda/media-split.images': [ 'image', 'x', 'y', 'w', 'float', 'crop' ],
		'assetpanda/media-split.glows': [ 'x', 'y', 'w', 'h', 'color' ],
		'assetpanda/vertical-hero.cards': [ 'label', 'title', 'status_label', 'tag', 'icon', 'icon_svg', 'avatar', 'x', 'y', 'mx', 'my' ],
	};

	/*
	 * Arrays nested too deeply for a flat repeater (the site header's menu:
	 * items > tabs > groups > links). They keep their contract default and get
	 * no editor fields — a row editor would flatten and destroy the nesting.
	 */
	var NO_REPEATER = {
		'assetpanda/site-header.nav': true,
		'assetpanda/vertical-hero.lines': true,
		'assetpanda/vertical-hero.lines_m': true,
		'assetpanda/vertical-hero.orbs': true,
		'assetpanda/vertical-hero.orbs_m': true,
		'assetpanda/dashboard-showcase.checks': true,
	};

	function labelFor( key ) {
		if ( LABELS[ key ] ) {
			return __( LABELS[ key ], 'assetpanda' );
		}
		return key.replace( /_/g, ' ' ).replace( /^./, function ( c ) {
			return c.toUpperCase();
		} );
	}

	/**
	 * One scalar control, chosen from the attribute's registered schema.
	 */
	function scalarControl( key, schema, value, onChange ) {
		if ( schema.type === 'boolean' ) {
			return el( ToggleControl, {
				key: key,
				label: labelFor( key ),
				checked: !! value,
				onChange: onChange,
			} );
		}

		if ( schema.enum ) {
			return el( SelectControl, {
				key: key,
				label: labelFor( key ),
				value: String( value ),
				options: schema.enum.map( function ( v ) {
					return { label: String( v ), value: String( v ) };
				} ),
				onChange: function ( v ) {
					onChange( schema.type === 'number' ? parseInt( v, 10 ) : v );
				},
			} );
		}

		if ( schema.type === 'number' ) {
			return el( TextControl, {
				key: key,
				type: 'number',
				label: labelFor( key ),
				value: value,
				onChange: function ( v ) {
					onChange( parseInt( v, 10 ) || 0 );
				},
			} );
		}

		return el( TEXTAREAS[ key ] ? TextareaControl : TextControl, {
			key: key,
			label: labelFor( key ),
			value: value || '',
			onChange: onChange,
		} );
	}

	/**
	 * A repeater panel for an array attribute.
	 */
	function repeater( blockName, key, rows, setRows ) {
		var shape = ROWS[ blockName + '.' + key ] || [ 'title', 'body' ];

		function update( index, field, value ) {
			// Never mutate in place — React needs a new reference to re-render.
			setRows(
				rows.map( function ( row, i ) {
					if ( i !== index ) {
						return row;
					}
					var copy = Object.assign( {}, row );
					copy[ field ] = value;
					return copy;
				} )
			);
		}

		function move( from, to ) {
			if ( to < 0 || to >= rows.length ) {
				return;
			}
			var copy = rows.slice();
			copy.splice( to, 0, copy.splice( from, 1 )[ 0 ] );
			setRows( copy );
		}

		var cards = rows.map( function ( row, index ) {
			return el(
				'div',
				{
					key: index,
					style: {
						border: '1px solid #e0e0e0',
						borderRadius: '4px',
						padding: '12px',
						marginBottom: '12px',
					},
				},
				el(
					'div',
					{
						style: {
							display: 'flex',
							justifyContent: 'space-between',
							alignItems: 'center',
							marginBottom: '8px',
						},
					},
					el( 'strong', null, labelFor( key ) + ' ' + ( index + 1 ) ),
					el(
						'div',
						null,
						el( Button, {
							icon: 'arrow-up-alt2',
							label: __( 'Move up', 'assetpanda' ),
							disabled: index === 0,
							onClick: function () {
								move( index, index - 1 );
							},
						} ),
						el( Button, {
							icon: 'arrow-down-alt2',
							label: __( 'Move down', 'assetpanda' ),
							disabled: index === rows.length - 1,
							onClick: function () {
								move( index, index + 1 );
							},
						} ),
						el( Button, {
							icon: 'trash',
							isDestructive: true,
							label: __( 'Remove', 'assetpanda' ),
							onClick: function () {
								setRows(
									rows.filter( function ( _, i ) {
										return i !== index;
									} )
								);
							},
						} )
					)
				),
				shape.map( function ( field ) {
					return el( TEXTAREAS[ field ] ? TextareaControl : TextControl, {
						key: field,
						label: labelFor( field ),
						value: row[ field ] || '',
						onChange: function ( v ) {
							update( index, field, v );
						},
					} );
				} )
			);
		} );

		return el(
			PanelBody,
			{ key: key, title: labelFor( key ), initialOpen: true },
			cards,
			el(
				Button,
				{
					variant: 'primary',
					onClick: function () {
						var blank = {};
						shape.forEach( function ( f ) {
							blank[ f ] = '';
						} );
						setRows( rows.concat( [ blank ] ) );
					},
				},
				__( 'Add', 'assetpanda' )
			)
		);
	}

	function edit( props ) {
		var name = props.name;
		var type = blocks.getBlockType( name );
		var schema = ( type && type.attributes ) || {};
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;

		var arrayPanels = [];
		var scalarFields = [];

		Object.keys( schema ).forEach( function ( key ) {
			var attr = schema[ key ];

			// Block-level concerns, not kit props. WordPress owns their UI (the
			// alignment toolbar), so rendering them as content fields would both
			// duplicate that control and imply they reach the component.
			if ( SKIP[ key ] ) {
				return;
			}

			// Structured geometry (the hero's layout) has no sensible text field.
			if ( attr.type === 'object' ) {
				return;
			}

			if ( attr.type === 'array' ) {
				if ( NO_REPEATER[ name + '.' + key ] ) {
					return;
				}
				arrayPanels.push(
					repeater( name, key, attributes[ key ] || [], function ( next ) {
						var patch = {};
						patch[ key ] = next;
						setAttributes( patch );
					} )
				);
				return;
			}

			scalarFields.push(
				scalarControl( key, attr, attributes[ key ], function ( v ) {
					var patch = {};
					patch[ key ] = v;
					setAttributes( patch );
				} )
			);
		} );

		return el(
			Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Content', 'assetpanda' ), initialOpen: true },
					scalarFields
				),
				arrayPanels
			),
			el(
				'div',
				blockEditor.useBlockProps(),
				el( ServerSideRender, { block: name, attributes: attributes } )
			)
		);
	}

	/*
	 * Register the editor half of every AP block. The blocks themselves are
	 * registered server-side from block.json; this supplies edit/save only.
	 */
	[
		'hero',
		'stats',
		'awards',
		'industries',
		'feature-cards',
		'value-props',
		'comparison',
		'case-studies',
		'platform-stack',
		'mission',
		'site-header',
		'trust-strip',
		'faq',
		'sticky-list',
		'problem-callouts',
		'dashboard-showcase',
		'demo-cta',
		'vertical-hero',
		'hub-hero',
		'link-cards',
		'problem-scene',
		'capability-rows',
		'media-split',
		'story-slider',
	].forEach( function ( name ) {
		blocks.registerBlockType( 'assetpanda/' + name, {
			edit: edit,
			// Dynamic block: the front end comes from render.php via the kit.
			save: function () {
				return null;
			},
		} );
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.i18n
);
