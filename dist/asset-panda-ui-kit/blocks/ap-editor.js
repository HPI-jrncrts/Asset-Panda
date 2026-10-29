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
		mobile_background: 'Phone background image URL',
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
		// FAQ answers (plans-faq starts a paragraph at each blank line).
		a: true,
		// plans-*: one item per line.
		includes: true,
		counts: true,
		never: true,
		everything: true,
		records_body: true,
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
		'assetpanda/industries.items': [ 'title', 'body', 'image', 'icon', 'link_label', 'link_url', 'anchor' ],
		'assetpanda/feature-cards.items': [ 'title', 'body' ],
		'assetpanda/value-props.items': [ 'title', 'body', 'accent' ],
		'assetpanda/comparison.rows': [ 'pillar', 'after', 'before' ],
		'assetpanda/case-studies.items': [
			'category', 'title', 'quote', 'author', 'role', 'logo', 'logo_alt',
			'image', 'image_alt', 'link_label', 'link_url',
		],
		'assetpanda/platform-stack.items': [
			'title', 'body', 'nav_label', 'link_label', 'link_url', 'image', 'image_alt', 'image_hover', 'image_hover_alt',
			'tone', 'link_tone', 'anchor', 'hover_slot', 'hover_bg',
		],
		'assetpanda/site-header.login_links': [ 'label', 'note', 'url' ],
		'assetpanda/site-footer.socials': [ 'label', 'url', 'icon' ],
		'assetpanda/site-footer.apps': [ 'label', 'url', 'image', 'alt', 'w', 'h' ],
		'assetpanda/trust-strip.items': [ 'image', 'alt', 'url' ],
		'assetpanda/faq.items': [ 'q', 'a' ],
		'assetpanda/sticky-list.items': [ 'title', 'icon', 'icon_alt', 'accent', 'checks' ],
		'assetpanda/problem-callouts.cards': [ 'text', 'icon', 'icon_alt', 'tone' ],
		'assetpanda/dashboard-showcase.tips': [ 'title', 'icon', 'icon_alt', 'tone', 'rows' ],
		'assetpanda/dashboard-showcase.orbs': [ 'image', 'alt', 'tone', 'zoom' ],
		'assetpanda/vertical-hero.badges': [ 'image', 'alt' ],
		'assetpanda/vertical-hero.features': [ 'label', 'icon', 'icon_svg' ],
		'assetpanda/assist-hero.features': [ 'label', 'icon', 'icon_svg' ],
		'assetpanda/assist-hero.badges': [ 'image', 'alt' ],
		'assetpanda/assist-hero.tiles': [ 'icon_svg', 'tone' ],
		'assetpanda/orbit-hero.badges': [ 'title', 'note', 'slot', 'icon', 'icon_svg' ],
		'assetpanda/orbit-hero.features': [ 'label', 'icon', 'icon_svg' ],
		'assetpanda/continuity-columns.items': [ 'kicker', 'title', 'body' ],
		'assetpanda/capability-scene.left': [ 'kicker', 'title', 'body' ],
		'assetpanda/capability-scene.right': [ 'kicker', 'title', 'body' ],
		'assetpanda/capability-scene.lines': [ 'accent', 'text' ],
		'assetpanda/asset-scene.items': [ 'kicker', 'title', 'body', 'link_label', 'url', 'slot' ],
		'assetpanda/solution-hero.badges': [ 'image', 'alt' ],
		'assetpanda/solution-hero.features': [ 'label', 'icon', 'icon_svg' ],
		'assetpanda/solution-hero.cards': [ 'slot', 'x', 'y', 'w', 'flo_class', 'markup' ],
		'assetpanda/photo-tags.tags': [ 'text', 'x', 'y', 'end', 'icon_svg' ],
		'assetpanda/intel-dashboard.chips': [ 'cls', 'image', 'boxed', 'framed' ],
		'assetpanda/intel-dashboard.tips': [ 'cls', 'height', 'markup' ],
		'assetpanda/intel-dashboard.ctas': [ 'label', 'url' ],
		'assetpanda/readiness-hero.badges': [ 'image', 'alt' ],
		'assetpanda/readiness-hero.cards': [ 'tone', 'kicker', 'title', 'sub', 'tag', 'icon_svg', 'dot' ],
		'assetpanda/readiness-hero.features': [ 'label', 'icon_svg' ],
		'assetpanda/home-hero.badges': [ 'image', 'alt', 'cls' ],
		'assetpanda/home-hero.logos': [ 'image', 'alt', 'cls', 'w', 'h' ],
		'assetpanda/dashboard-showcase.ctas': [ 'label', 'url' ],
		'assetpanda/hub-hero.badges': [ 'image', 'alt' ],
		'assetpanda/hub-hero.photos': [ 'image', 'alt', 'x', 'y', 'w', 'h', 'ix', 'iw' ],
		'assetpanda/hub-hero.features': [ 'label', 'icon', 'icon_svg' ],
		'assetpanda/link-cards.items': [ 'title', 'body', 'link_label', 'url', 'image', 'image_top', 'icon', 'icon_svg' ],
		'assetpanda/why-cards.items': [ 'title', 'body', 'url', 'link_label', 'icon', 'icon_svg', 'icon_alt' ],
		'assetpanda/solution-faq.items': [ 'q', 'a' ],
		'assetpanda/solution-features.items': [ 'title', 'body', 'icon_svg', 'accent' ],
		'assetpanda/solution-rows.items': [ 'title', 'body', 'tone', 'icon_svg', 'link_label', 'url' ],
		'assetpanda/solution-awards.items': [ 'image', 'alt', 'w', 'h' ],
		'assetpanda/readiness-problem.chips': [ 'text', 'icon_svg' ],
		'assetpanda/solution-trust.items': [ 'image', 'alt', 'fit' ],
		'assetpanda/platform-rows.items': [ 'number', 'keyword', 'title', 'body', 'link_label', 'url' ],
		'assetpanda/platform-faq.items': [ 'q', 'a' ],
		'assetpanda/assist-problem.chips': [ 'label', 'icon_svg', 'x', 'y', 'w', 'mx', 'my' ],
		'assetpanda/assist-problem.bullets': [ 'text' ],
		'assetpanda/pricing-calculator.sliders': [ 'key', 'label', 'min', 'max', 'step', 'value', 'pill', 'pill_one', 'format', 'min_label', 'max_label' ],
		'assetpanda/pricing-calculator.rows': [ 'key', 'icon', 'title', 'note', 'value' ],
		'assetpanda/roi-calculator.groups': [ 'title', 'state' ],
		'assetpanda/roi-calculator.fields': [ 'group', 'key', 'label', 'help', 'value', 'suffix' ],
		'assetpanda/roi-calculator.results': [ 'key', 'label', 'icon' ],
		'assetpanda/compare-table.rows': [ 'label', 'after', 'before', 'winner', 'winner_note', 'after_check', 'before_check', 'icon_svg' ],
		'assetpanda/page-toc.items': [ 'label', 'anchor' ],
		'assetpanda/thanks-steps.items': [ 'label', 'tone', 'title', 'body' ],
		'assetpanda/plans-hero.badges': [ 'image', 'alt', 'h' ],
		'assetpanda/plans-hero.logos': [ 'image', 'alt', 'h' ],
		'assetpanda/plans-picker.chips': [ 'label', 'plan', 'note' ],
		'assetpanda/plans-picker.plans': [
			'name', 'who', 'price', 'price_suffix', 'per', 'annual', 'cta_label', 'cta_url',
			'records', 'records_label', 'plus', 'includes', 'flag',
		],
		'assetpanda/plans-compare.plans': [ 'name', 'price', 'accent' ],
		'assetpanda/plans-compare.rows': [ 'label', 'hint', 'kind', 'values' ],
		'assetpanda/plans-faq.items': [ 'q', 'a' ],
		'assetpanda/demo-booking.compliance': [ 'image', 'alt', 'w', 'h', 'display_h' ],
		'assetpanda/demo-booking.stats': [ 'value', 'label' ],
		'assetpanda/demo-booking.awards': [ 'image', 'alt', 'w', 'h' ],
		'assetpanda/demo-booking.stories': [ 'title', 'quote', 'author', 'role', 'logo', 'logo_alt', 'logo_w', 'logo_h' ],
		'assetpanda/demo-stats.items': [ 'value', 'label' ],
		'assetpanda/demo-stories.items': [
			'category', 'title', 'quote', 'author', 'role', 'link_label', 'link_url', 'image', 'image_alt', 'logo', 'logo_alt', 'logo_w', 'logo_h',
		],
		'assetpanda/demo-footer.apps': [ 'image', 'alt', 'url', 'w', 'h' ],
		'assetpanda/demo-footer.links': [ 'label', 'url' ],
		'assetpanda/demo-footer.social': [ 'image', 'label', 'url' ],
		'assetpanda/case-slider.items': [ 'title', 'quote', 'author', 'role', 'link_label', 'link_url', 'image', 'image_alt', 'logo', 'logo_alt', 'logo_w', 'logo_h', 'logo_text', 'logo_style' ],
		'assetpanda/problem-scene.images': [ 'image', 'alt' ],
		'assetpanda/problem-scene.chips': [ 'text', 'tone', 'icon', 'icon_svg', 'left', 'right', 'top' ],
		'assetpanda/story-slider.items': [
			'category', 'title', 'quote', 'author', 'role', 'image', 'image_alt', 'logo', 'logo_alt', 'logo_w', 'logo_h',
			'logo_text', 'logo_style', 'link_label', 'link_url', 'video_id', 'video_start', 'video_end', 'video_title',
		],
		'assetpanda/capability-rows.items': [ 'title', 'body', 'link_label', 'url', 'tone', 'number', 'icon', 'icon_svg' ],
		'assetpanda/media-split.links': [ 'label', 'url' ],
		'assetpanda/media-split.images': [ 'image', 'x', 'y', 'w', 'float', 'crop' ],
		'assetpanda/media-split.glows': [ 'x', 'y', 'w', 'h', 'color' ],
		'assetpanda/media-split.chips': [ 'label', 'x', 'y', 'w', 'icon', 'icon_svg' ],
		'assetpanda/hub-intel.features': [ 'title', 'body', 'icon', 'icon_alt', 'accent', 'link_label', 'link_url' ],
		'assetpanda/vertical-hero.cards': [ 'label', 'title', 'status_label', 'tag', 'icon', 'icon_svg', 'avatar', 'x', 'y', 'mx', 'my' ],
		'assetpanda/form-hero.badges': [ 'image', 'alt', 'w', 'h' ],
		'assetpanda/card-grid.items': [ 'title', 'body', 'image', 'image_alt', 'image_w', 'image_h', 'icon', 'link_label', 'link_url' ],
		'assetpanda/message-hero.links': [ 'label', 'url', 'style' ],
		'assetpanda/thanks-calendly.tracks': [ 'url', 'event_title', 'duration', 'body', 'when_key' ],
		'assetpanda/login-splash.items': [ 'type', 'size', 'image', 'alt', 'width', 'height', 'wide', 'tag', 'text', 'bold', 'label', 'url', 'new_tab' ],
	};

	/*
	 * Arrays nested too deeply for a flat repeater (the site header's menu:
	 * items > tabs > groups > links). They keep their contract default and get
	 * no editor fields — a row editor would flatten and destroy the nesting.
	 */
	var NO_REPEATER = {
		'assetpanda/site-header.nav': true,
		'assetpanda/site-footer.columns': true,
		'assetpanda/pricing-hero.form_enrich_fields': true,
		'assetpanda/vertical-hero.lines': true,
		'assetpanda/vertical-hero.lines_m': true,
		'assetpanda/vertical-hero.orbs': true,
		'assetpanda/vertical-hero.orbs_m': true,
		'assetpanda/dashboard-showcase.checks': true,
		'assetpanda/tab-showcase.panels': true,
		'assetpanda/photo-tags.sr_list': true,
		'assetpanda/media-split.bullets': true,
		'assetpanda/hub-intel.rows': true,
		'assetpanda/intel-dashboard.checks': true,
		'assetpanda/readiness-hero.lines': true,
		'assetpanda/founder-quote.body': true,
		'assetpanda/form-hero.bullets': true,
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
	 *
	 * The kit prints the live list of its block directories as window.AP_UI_BLOCKS
	 * (asset-panda-ui-kit.php, before this script), so a component added to the
	 * kit is editable without touching this file; the array below is the fallback
	 * for an older bootstrap that does not print it. (QA 2026-10-29: 28 newer
	 * blocks showed as "missing" in the editor because this list had not grown.)
	 */
	( window.AP_UI_BLOCKS || [
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
		'site-footer',
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
		'hub-intel',
		'story-slider',
		'assist-hero',
		'orbit-hero',
		'continuity-columns',
		'capability-scene',
		'asset-scene',
		'tab-showcase',
		'solution-hero',
		'photo-tags',
		'intel-dashboard',
		'readiness-hero',
		'founder-quote',
		'home-hero',
		'why-cards',
		'solution-faq',
		'case-slider',
		'solution-features',
		'solution-rows',
		'solution-cta',
		'solution-awards',
		'readiness-problem',
		'solution-trust',
		'platform-rows',
		'platform-faq',
		'platform-cta',
		'assist-problem',
		'pricing-hero',
		'pricing-calculator',
		'thanks-hero',
		'thanks-steps',
		'plans-hero',
		'plans-picker',
		'plans-compare',
		'plans-faq',
		'demo-booking',
		'demo-stats',
		'demo-stories',
		'demo-footer',
		'demo-confirm',
		'form-hero',
		'media-band',
		'card-grid',
		'message-hero',
		'thanks-calendly',
		'login-splash',
		'roi-calculator',
		'compare-table',
		'page-toc',
		'article-cta',
	] ).forEach( function ( name ) {
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
