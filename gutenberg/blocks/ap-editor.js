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
		shape: 'Shape',
		size: 'Size',
		fit: 'Fit',
		effect: 'Effect',
		position: 'Position',
		caption: 'Caption',
		look: 'Box style',
		width: 'Width',
		padding: 'Inside space',
		text_align: 'Text alignment',
		radius: 'Corners',
		background_image: 'Background image URL',
		overlay: 'Wash over the background image',
		decoration: 'Decoration',
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

	/*
	 * Blocks registered on the server only (snippet 6's ap26/* blocks): their
	 * editor half is registered here too, so the editor shows the real block
	 * instead of "Your site doesn't include support for this block". [title,
	 * dashicon, shown in the inserter]. Unlisted ones get a humanised name and
	 * stay out of the inserter (they belong to templates).
	 */
	var SERVER_BLOCKS = {
		'ap26/cta-mini': [ 'Mini CTA', 'megaphone', true ],
		'ap26/post-header': [ 'Blog post header', 'heading', false ],
		'ap26/share-bar': [ 'Share bar', 'share', false ],
		'ap26/sticky-bar': [ 'Sticky title bar', 'sticky', false ],
		'ap26/related-posts': [ 'Related posts', 'grid-view', false ],
		'ap26/blog-hero': [ 'Blog hero', 'cover-image', false ],
		'ap26/category-hero': [ 'Category hero', 'cover-image', false ],
		'ap26/blog-grid': [ 'Blog post grid', 'grid-view', false ],
		'ap-content/page-hero': [ 'Article page title', 'heading', false ],
	};

	/* Friendlier inserter names for the kit blocks writers use in articles. */
	var TITLES = {
		'assetpanda/visual': 'Visual (image element)',
		'assetpanda/container': 'Container (styled box)',
		'assetpanda/article-cta': 'Article CTA box',
		'assetpanda/page-toc': 'Table of contents',
		'assetpanda/compare-table': 'Comparison table',
	};

	/* Per-block labels, where the kit-wide LABELS name would mislead. */
	var BLOCK_LABELS = {
		'ap26/cta-mini': {
			heading: 'Heading',
			text: 'Line of copy',
			button: 'Button text (empty = "Talk to an Expert")',
			url: 'Button link (empty = /get-a-demo/)',
		},
	};

	/*
	 * Where a field shows on the block, for fields the preview may draw with a
	 * default (an empty `button` still renders "Talk to an Expert"), so value
	 * matching cannot find them. Every other block is matched by value.
	 */
	var HINTS = {
		'ap26/cta-mini': {
			heading: '.ap26-cta-mini__heading',
			text: '.ap26-cta-mini__text',
			button: '.ap26-cta-mini__btn',
		},
	};

	/* Whole-site chrome is edited in its template part, and is too large to scan on every preview. */
	var LIVE_OFF = {
		'assetpanda/site-header': true,
		'assetpanda/site-footer': true,
	};

	function labelFor( key, blockName ) {
		var own = blockName && BLOCK_LABELS[ blockName ];
		if ( own && own[ key ] ) {
			return __( own[ key ], 'assetpanda' );
		}
		if ( LABELS[ key ] ) {
			return __( LABELS[ key ], 'assetpanda' );
		}
		return key.replace( /_/g, ' ' ).replace( /^./, function ( c ) {
			return c.toUpperCase();
		} );
	}

	/* ---------------------------------------------------------------------
	 * Live editing (builder v1, 2026-11): the preview is the editor.
	 *
	 * The block still previews its real server render. Each text the render
	 * shows is matched back to the attribute (or repeater row field) holding it,
	 * and becomes editable in place: click, type, click away (Enter commits,
	 * Esc reverts). Each image matched to an image field opens the Media
	 * Library on click and takes the new file's alt text. Links are edited from
	 * the block toolbar. The sidebar keeps every field for anything the
	 * preview does not show.
	 *
	 * Matching is by value, in document order, so it needs nothing per block;
	 * HINTS covers the few fields a preview draws from a default. Editor only:
	 * none of this reaches the front end.
	 * ------------------------------------------------------------------- */

	var useRef = element.useRef;
	var useEffect = element.useEffect;
	var BlockControls = blockEditor.BlockControls;
	var ToolbarGroup = components.ToolbarGroup;
	var ToolbarButton = components.ToolbarButton;
	var Dropdown = components.Dropdown;

	/* Keys that hold geometry, tokens, ids or markup, never copy a reader sees. */
	var NOT_COPY =
		/^(x|y|w|h|mx|my|ix|iw|zoom|left|right|top|end|height|width|display_h|anchor|slot|tone|link_tone|accent|cls|class|state|kind|key|format|fit|crop|float|flag|when_key|form_id|video_id|video_start|video_end|hover_slot|hover_bg|style|size|type|group|markup|boxed|framed|wide|new_tab|bold|min|max|step|dot)$|(^|_)(url|href|svg|id|w|h)$/;
	var IMAGE_KEY = /(^|_)(image|logo|icon|avatar|photo|background|img|mark|image_hover)$/;
	var LINK_KEY = /(^|_)url$/;
	var ARIA_HIDDEN = '[aria-hidden="true"]';

	var decoder = document.createElement( 'textarea' );

	function decode( s ) {
		if ( s.indexOf( '&' ) === -1 ) {
			return s;
		}
		decoder.innerHTML = s;
		return decoder.value;
	}

	/* Compare text as a reader sees it: tags dropped, entities decoded, whitespace collapsed. */
	function norm( s ) {
		return decode( String( s ).replace( /<[^>]*>/g, ' ' ) )
			.replace( /[\s ​]+/g, ' ' )
			.trim();
	}

	function hasMarkup( s ) {
		return /<[a-z!\/][^>]*>/i.test( String( s ) );
	}

	function isImageValue( v ) {
		return typeof v === 'string' && /(\.(png|jpe?g|webp|avif|gif|svg)([?#]|$))/i.test( v.trim() );
	}

	/* An uploads file and its served copies (`x.jpg`, `x-1024x768.jpg`, `x.jpg.w828.webp`) share one stem. */
	function imageStem( url ) {
		var u = String( url || '' ).split( /[?#]/ )[ 0 ];
		var b = u.substring( u.lastIndexOf( '/' ) + 1 ).toLowerCase();
		try {
			b = decodeURIComponent( b );
		} catch ( e ) {}
		return b
			.replace( /(\.w\d+)?\.webp$/, '' )
			.replace( /\.(png|jpe?g|webp|avif|gif|svg)$/, '' )
			.replace( /-(\d+x\d+|scaled|rotated)$/, '' );
	}

	function own( obj, key ) {
		return !! obj && Object.prototype.hasOwnProperty.call( obj, key );
	}

	/* The alt-text field that belongs to an image field, in the same scope (attributes or row). */
	function altKeyFor( key, scope ) {
		var c = [ key + '_alt', key.replace( /image$/, 'alt' ) ];
		if ( key === 'image' ) {
			c.push( 'alt' );
		}
		for ( var i = 0; i < c.length; i++ ) {
			if ( c[ i ] !== key && own( scope, c[ i ] ) ) {
				return c[ i ];
			}
		}
		return '';
	}

	/* The text field that names a link field (cta_url → cta_label, url → link_label / label / button). */
	function linkTextFor( key, scope ) {
		var c = [ key.replace( /url$/, 'label' ) ];
		if ( key === 'url' || key === 'link_url' ) {
			c.push( 'link_label', 'label', 'button', 'title', 'name' );
		}
		for ( var i = 0; i < c.length; i++ ) {
			if ( c[ i ] !== key && own( scope, c[ i ] ) && typeof scope[ c[ i ] ] === 'string' && scope[ c[ i ] ] !== '' ) {
				return scope[ c[ i ] ];
			}
		}
		return '';
	}

	function isMultiline( key ) {
		return !! TEXTAREAS[ key ];
	}

	/*
	 * Every field the editor can show: { path, key, value, scope }. path is
	 * [ key ] for an attribute, [ key, row, field ] for a repeater row field, and
	 * [ key, row, list, item, field ] for the copy of a list inside a row (a tab's
	 * items, an FAQ topic's questions) — kit 1.3.194, so the text of a tab or topic
	 * opened in the preview can be edited in place too.
	 */
	function nestedFields( key, i, row, out ) {
		Object.keys( row ).forEach( function ( sub ) {
			var list = row[ sub ];
			if ( ! Array.isArray( list ) ) {
				return;
			}
			list.forEach( function ( item, j ) {
				if ( ! item || typeof item !== 'object' || Array.isArray( item ) ) {
					return;
				}
				Object.keys( item ).forEach( function ( f ) {
					if ( typeof item[ f ] === 'string' && ! NOT_COPY.test( f ) && ! IMAGE_KEY.test( f ) && ! LINK_KEY.test( f ) ) {
						out.push( { path: [ key, i, sub, j, f ], key: f, value: item[ f ], scope: item } );
					}
				} );
			} );
		} );
	}

	function fieldList( name, schema, attributes ) {
		var out = [];
		Object.keys( schema ).forEach( function ( key ) {
			var attr = schema[ key ];
			if ( SKIP[ key ] || attr.type === 'object' ) {
				return;
			}
			if ( attr.type === 'array' ) {
				if ( NO_REPEATER[ name + '.' + key ] ) {
					return;
				}
				var shape = ROWS[ name + '.' + key ] || [ 'title', 'body' ];
				( attributes[ key ] || [] ).forEach( function ( row, i ) {
					if ( ! row || typeof row !== 'object' ) {
						return;
					}
					shape.forEach( function ( f ) {
						if ( typeof row[ f ] === 'string' ) {
							out.push( { path: [ key, i, f ], key: f, value: row[ f ], scope: row } );
						}
					} );
					nestedFields( key, i, row, out );
				} );
				return;
			}
			if ( attr.type === 'string' && ! attr.enum ) {
				var v = attributes[ key ];
				out.push( { path: [ key ], key: key, value: typeof v === 'string' ? v : '', scope: attributes } );
			}
		} );
		return out;
	}

	function getPath( attributes, path ) {
		var cur = attributes;
		for ( var i = 0; i < path.length; i++ ) {
			if ( cur === null || cur === undefined ) {
				return undefined;
			}
			cur = cur[ path[ i ] ];
		}
		return cur;
	}

	/* A copy of node with value written at path: every array and object on the way is copied, never mutated. */
	function setIn( node, path, value ) {
		if ( ! path.length ) {
			return value;
		}
		var head = path[ 0 ];
		var rest = path.slice( 1 );
		if ( Array.isArray( node ) ) {
			return node.map( function ( item, i ) {
				return i === head ? setIn( item, rest, value ) : item;
			} );
		}
		var copy = Object.assign( {}, node || {} );
		copy[ head ] = setIn( copy[ head ], rest, value );
		return copy;
	}

	/* The attribute patch that writes value at path (rows copied, never mutated). */
	function patchFor( attributes, path, value ) {
		var patch = {};
		patch[ path[ 0 ] ] = setIn( attributes[ path[ 0 ] ], path.slice( 1 ), value );
		return patch;
	}

	/* The text a field should take from an edited element: <br> and block breaks become \n. */
	function readText( node ) {
		var out = '';
		( function walk( n ) {
			for ( var c = n.firstChild; c; c = c.nextSibling ) {
				if ( c.nodeType === 3 ) {
					out += c.data;
				} else if ( c.nodeType === 1 ) {
					var tag = c.nodeName.toUpperCase();
					if ( tag === 'BR' ) {
						out += '\n';
					} else if ( tag !== 'SVG' && tag !== 'IMG' && tag !== 'STYLE' && tag !== 'SCRIPT' ) {
						if ( /^(DIV|P|LI)$/.test( tag ) && out && out.slice( -1 ) !== '\n' ) {
							out += '\n';
						}
						walk( c );
					}
				}
			}
		} )( node );
		return out.replace( / /g, ' ' );
	}

	var NO_EDIT_TAGS = { SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, TEMPLATE: 1, SVG: 1, IFRAME: 1, VIDEO: 1, SELECT: 1, TEXTAREA: 1, INPUT: 1 };

	function visible( node ) {
		var r = node.getBoundingClientRect();
		return r.width > 2 && r.height > 2;
	}

	/*
	 * Mark the preview: data-ap-path (JSON) on each element showing a field's
	 * text, data-ap-image on each image showing an image field.
	 */
	function bindPreview( host, name, schema, attributes ) {
		var marked = host.querySelectorAll( '[data-ap-path]' );
		for ( var m = 0; m < marked.length; m++ ) {
			if ( ! marked[ m ].hasAttribute( 'data-ap-editing' ) ) {
				marked[ m ].removeAttribute( 'data-ap-path' );
				marked[ m ].removeAttribute( 'data-ap-kind' );
				marked[ m ].removeAttribute( 'title' );
			}
		}

		var all = host.querySelectorAll( '*' );
		var nodes = [];
		var imgs = [];
		for ( var i = 0; i < all.length; i++ ) {
			var n = all[ i ];
			var tag = n.nodeName.toUpperCase();
			if ( NO_EDIT_TAGS[ tag ] || n.closest( 'svg, [data-ap-noedit]' ) ) {
				continue;
			}
			// Hidden copies (a marquee's duplicate set) sit in an aria-hidden wrapper. An image's own
			// aria-hidden is the kit's alt-text pass (ap_ui_alt_pass), which still leaves it editable.
			if ( ( tag === 'IMG' ? n.parentElement : n ).closest( ARIA_HIDDEN ) ) {
				continue;
			}
			if ( tag === 'IMG' ) {
				imgs.push( n );
			} else {
				nodes.push( { node: n, text: null } );
			}
		}

		var taken = [];
		function free( node ) {
			for ( var t = 0; t < taken.length; t++ ) {
				if ( taken[ t ] === node || taken[ t ].contains( node ) || node.contains( taken[ t ] ) ) {
					return false;
				}
			}
			return true;
		}
		function mark( node, field, kind ) {
			taken.push( node );
			node.setAttribute( 'data-ap-path', JSON.stringify( field.path ) );
			node.setAttribute( 'data-ap-kind', kind );
			node.setAttribute( 'title', kind === 'image' ? __( 'Click to replace this image', 'assetpanda' ) : __( 'Click to edit', 'assetpanda' ) );
		}

		var fields = fieldList( name, schema, attributes );
		var hints = HINTS[ name ] || {};

		// 1. Hinted fields first: they are found by selector, whatever they show.
		fields.forEach( function ( f ) {
			if ( f.path.length === 1 && hints[ f.key ] ) {
				var hinted = host.querySelector( hints[ f.key ] );
				if ( hinted && free( hinted ) ) {
					mark( hinted, f, 'text' );
				}
			}
		} );

		// 2. Images, by file.
		var usedImg = [];
		fields.forEach( function ( f ) {
			if ( ! IMAGE_KEY.test( f.key ) || ! isImageValue( f.value ) ) {
				return;
			}
			var stem = imageStem( f.value );
			for ( var k = 0; k < imgs.length; k++ ) {
				if ( usedImg.indexOf( imgs[ k ] ) !== -1 ) {
					continue;
				}
				if ( imageStem( imgs[ k ].getAttribute( 'src' ) ) === stem ) {
					usedImg.push( imgs[ k ] );
					mark( imgs[ k ], f, 'image' );
					return;
				}
			}
		} );

		// 3. Text, by value. Fields sharing one text ("Asset Panda" as a column name and three
		//    winner pills) are matched as a group, in order, and only when the preview shows that
		//    text exactly as often as the fields hold it; otherwise they stay sidebar-only, so a
		//    click can never edit a different field than the one it shows.
		var groups = {};
		var order = [];
		fields.forEach( function ( f ) {
			if ( f.path.length === 1 && hints[ f.key ] ) {
				return;
			}
			if ( NOT_COPY.test( f.key ) || IMAGE_KEY.test( f.key ) || ! f.value || hasMarkup( f.value ) ) {
				return;
			}
			var want = norm( f.value );
			if ( ! want ) {
				return;
			}
			if ( ! groups[ want ] ) {
				groups[ want ] = [];
				order.push( want );
			}
			groups[ want ].push( f );
		} );

		// The deepest free, visible elements showing exactly `want`, in document order.
		function showing( want ) {
			var found = [];
			for ( var j = 0; j < nodes.length; j++ ) {
				var entry = nodes[ j ];
				if ( entry.text === null ) {
					entry.text = norm( entry.node.textContent );
				}
				if ( entry.text !== want || ! free( entry.node ) ) {
					continue;
				}
				var best = entry.node;
				for ( ;; ) {
					var deeper = null;
					for ( var c = 0; c < best.children.length; c++ ) {
						if ( norm( best.children[ c ].textContent ) === want ) {
							deeper = best.children[ c ];
							break;
						}
					}
					if ( ! deeper || NO_EDIT_TAGS[ deeper.nodeName.toUpperCase() ] ) {
						break;
					}
					best = deeper;
				}
				var inside = found.some( function ( prev ) {
					return prev.contains( best ) || best.contains( prev );
				} );
				if ( ! inside && visible( best ) ) {
					found.push( best );
				}
			}
			return found;
		}

		order.forEach( function ( want ) {
			var group = groups[ want ];
			var shown = showing( want );
			if ( group.length === 1 && shown.length >= 1 ) {
				mark( shown[ 0 ], group[ 0 ], 'text' );
			} else if ( shown.length === group.length ) {
				group.forEach( function ( f, i ) {
					mark( shown[ i ], f, 'text' );
				} );
			}
		} );
	}

	/* Editor-only affordances, printed once into the canvas document (the editor iframe). */
	var LIVE_CSS =
		'.ap-live [data-ap-kind="text"]{cursor:text;outline:1px dashed transparent;outline-offset:3px;border-radius:2px;transition:outline-color .15s}' +
		'.ap-live:hover [data-ap-kind="text"]{outline-color:rgba(80,68,225,.28)}' +
		'.ap-live [data-ap-kind="text"]:hover{outline:2px dashed #5044e1}' +
		'.ap-live [data-ap-editing]{outline:2px solid #5044e1!important;background:rgba(80,68,225,.06);cursor:text}' +
		'.ap-live img[data-ap-kind="image"]{cursor:pointer;transition:outline-color .15s;outline:3px solid transparent;outline-offset:2px}' +
		'.ap-live img[data-ap-kind="image"]:hover{outline-color:#00a991}' +
		'.ap-live a{cursor:default}';

	/*
	 * Kit stylesheets inside the canvas, so a block looks as it does on the
	 * page. The front end inlines them per rendered component; in the editor
	 * each block adds its own components' sheets (window.AP_UI_KIT.css, from
	 * the bootstrap) the first time it shows.
	 */
	function ensureStyles( doc, name ) {
		var kit = window.AP_UI_KIT;
		if ( ! doc || ! doc.head ) {
			return;
		}
		if ( ! doc.getElementById( 'ap-live-css' ) ) {
			var s = doc.createElement( 'style' );
			s.id = 'ap-live-css';
			s.textContent = LIVE_CSS;
			doc.head.appendChild( s );
		}
		if ( ! kit || ! kit.url ) {
			return;
		}
		var sheets = [ 'src/tokens.css', 'src/motion.css', 'src/anim.css' ];
		( kit.css[ name ] || [] ).forEach( function ( c ) {
			sheets.push( 'components/' + c + '/style.css' );
		} );
		sheets.forEach( function ( rel ) {
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

	/* The Media Library, for an image field: sets the file and, when the file has one, its alt text. */
	function pickImage( field, attributes, setAttributes ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		var frame = window.wp.media( {
			title: __( 'Replace image', 'assetpanda' ),
			button: { text: __( 'Use this image', 'assetpanda' ) },
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			if ( ! att || ! att.url ) {
				return;
			}
			var patch = patchFor( attributes, field.path, att.url );
			var altKey = altKeyFor( field.key, field.scope );
			var alt = String( att.alt || '' ).trim();
			if ( altKey && alt ) {
				var altPath = field.path.length === 1 ? [ altKey ] : [ field.path[ 0 ], field.path[ 1 ], altKey ];
				var merged = Object.assign( {}, attributes, patch );
				Object.assign( patch, patchFor( merged, altPath, alt ) );
			}
			setAttributes( patch );
			if ( altKey && ! alt && window.wp.data ) {
				window.wp.data.dispatch( 'core/notices' ).createNotice(
					'warning',
					__( 'This image has no alt text in the Media Library. Add a short description of it in the block sidebar.', 'assetpanda' ),
					{ type: 'snackbar', isDismissible: true }
				);
			}
		} );
		frame.open();
	}

	/* Toolbar dropdown with every link of the block (button, card links, logo links). */
	function linksControl( name, schema, attributes, setAttributes ) {
		var links = [];
		Object.keys( schema ).forEach( function ( key ) {
			var attr = schema[ key ];
			if ( SKIP[ key ] ) {
				return;
			}
			if ( attr.type === 'string' && LINK_KEY.test( key ) && ! IMAGE_KEY.test( key.replace( /_url$/, '' ) ) ) {
				links.push( { path: [ key ], label: linkTextFor( key, attributes ) || labelFor( key, name ), value: attributes[ key ] || '' } );
			}
			if ( attr.type === 'array' && ! NO_REPEATER[ name + '.' + key ] ) {
				var shape = ROWS[ name + '.' + key ] || [];
				shape.forEach( function ( f ) {
					if ( ! LINK_KEY.test( f ) ) {
						return;
					}
					( attributes[ key ] || [] ).forEach( function ( row, i ) {
						if ( row && typeof row === 'object' ) {
							links.push( {
								path: [ key, i, f ],
								label: ( linkTextFor( f, row ) || labelFor( key, name ) + ' ' + ( i + 1 ) ) + ' — ' + labelFor( f, name ),
								value: row[ f ] || '',
							} );
						}
					} );
				} );
			}
		} );
		if ( ! links.length ) {
			return null;
		}
		return el(
			BlockControls,
			{ group: 'other' },
			el(
				ToolbarGroup,
				null,
				el( Dropdown, {
					popoverProps: { placement: 'bottom-start' },
					renderToggle: function ( t ) {
						return el( ToolbarButton, {
							icon: 'admin-links',
							label: __( 'Edit links', 'assetpanda' ),
							onClick: t.onToggle,
							'aria-expanded': t.isOpen,
						} );
					},
					renderContent: function () {
						return el(
							'div',
							{ style: { padding: '12px', width: '320px', maxHeight: '60vh', overflowY: 'auto' } },
							links.map( function ( l ) {
								return el( TextControl, {
									key: l.path.join( '.' ),
									label: l.label,
									value: l.value,
									placeholder: '/get-a-demo/',
									onChange: function ( v ) {
										setAttributes( patchFor( attributes, l.path, v ) );
									},
								} );
							} )
						);
					},
				} )
			)
		);
	}

	/*
	 * Interactive sections in the builder (kit 1.3.193). The canvas gets the kit's
	 * stylesheets but not its scripts, so sliders, chip and tab switchers and the
	 * calculators sat still and the text on a hidden slide or tab could only be
	 * edited in the sidebar. These components' own behaviour.js files now run in
	 * the canvas. Left out on purpose: anything that loads a third party (HubSpot,
	 * Cal.com, Calendly, YouTube players, Cookiebot), fetches, moves markup out of
	 * its block, or only follows the page's scroll.
	 */
	var EDITOR_BEHAVIOURS = {
		'case-slider': 1,
		'case-studies': 1,
		'demo-stories': 1,
		'story-slider': 1,
		'page-reviews': 1,
		'plans-compare': 1,
		'plans-picker': 1,
		'feature-cards': 1,
		'integration-directory': 1,
		'page-directory': 1,
		'page-faq-hub': 1,
		'pricing-calculator': 1,
		'roi-calculator': 1,
	};
	var SLIDER_ROOTS = '[data-ap-case-slider], [data-ap-cases], [data-ap-story]';
	var BOOT_EVENT = 'ap-editor-boot';

	/*
	 * Run a component script in the canvas document. Each script starts with
	 * `if ( document.readyState === 'loading' ) addEventListener( 'DOMContentLoaded', boot )`;
	 * while it runs that check reads 'loading' and the listener goes on BOOT_EVENT
	 * instead, so boot() can be run again for every fresh server preview (boot is
	 * idempotent per section) while the script's page-wide listeners are added once.
	 */
	function runScript( doc, code, url ) {
		var add = doc.addEventListener;
		try {
			Object.defineProperty( doc, 'readyState', {
				configurable: true,
				get: function () {
					return 'loading';
				},
			} );
			doc.addEventListener = function ( type, fn, opts ) {
				return add.call( doc, type === 'DOMContentLoaded' ? BOOT_EVENT : type, fn, opts );
			};
			var s = doc.createElement( 'script' );
			s.textContent = code + '\n//# sourceURL=' + url;
			( doc.head || doc.documentElement ).appendChild( s );
		} finally {
			delete doc.readyState;
			delete doc.addEventListener;
		}
	}

	/* Load one component's behaviour.js into the canvas, once per canvas document. */
	function loadBehaviour( doc, comp ) {
		var kit = window.AP_UI_KIT;
		var loaded = doc.__apEdBehaviours || ( doc.__apEdBehaviours = {} );
		if ( ! loaded[ comp ] ) {
			var url = kit.url + '/components/' + comp + '/behaviour.js?ver=' + encodeURIComponent( kit.ver );
			loaded[ comp ] = window
				.fetch( url, { credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.ok ? r.text() : '';
				} )
				.then( function ( code ) {
					if ( code ) {
						runScript( doc, code, url );
					}
				} )
				.catch( function () {} );
		}
		return loaded[ comp ];
	}

	/* Start the interactive parts of a freshly rendered preview. Resolves once they are running. */
	function runBehaviours( host, name ) {
		var kit = window.AP_UI_KIT;
		var comps = kit && kit.url && kit.css && kit.css[ name ] ? kit.css[ name ].filter( function ( c ) {
			return EDITOR_BEHAVIOURS[ c ];
		} ) : [];
		if ( ! comps.length || ! window.Promise || ! window.fetch ) {
			return Promise.resolve();
		}
		var doc = host.ownerDocument;
		// No autoplay in the editor: a slide must never move away while its text is being edited.
		Array.prototype.forEach.call( host.querySelectorAll( SLIDER_ROOTS ), function ( root ) {
			root.setAttribute( 'data-ap-autoplay', '0' );
		} );
		return Promise.all( comps.map( function ( c ) {
			return loadBehaviour( doc, c );
		} ) ).then( function () {
			var ev = doc.createEvent( 'Event' );
			ev.initEvent( BOOT_EVENT, false, false );
			doc.dispatchEvent( ev );
		} );
	}

	/*
	 * The live preview: ServerSideRender plus the in-place editing layer.
	 * Delegated listeners on the wrapper read the latest props from a ref, so
	 * a re-render never leaves a stale handler behind.
	 */
	function LivePreview( props ) {
		var hostRef = useRef( null );
		var latest = useRef( props );
		latest.current = props;

		useEffect( function () {
			var host = hostRef.current;
			if ( ! host ) {
				return undefined;
			}
			var doc = host.ownerDocument;
			ensureStyles( doc, props.name );

			/*
			 * What the editor has opened, picked or moved to in the preview, by position:
			 * accordion rows (<details>), tabs (radio inputs), chips, slider dots and other
			 * pressed / current / expanded buttons. The preview is re-rendered on the server
			 * after every edit, which would snap all of it back to the saved page (first row
			 * open, first tab, first slide) mid-edit; restore() puts it back.
			 */
			var TOGGLES = 'input[type="radio"], input[type="checkbox"]';
			var ACTIVE = '[aria-current="true"], [aria-current="page"], [aria-pressed="true"], [aria-expanded="true"]';
			var shown = null;
			var restoring = false;
			function output() {
				return host.firstElementChild && host.firstElementChild.firstElementChild;
			}
			function snapshot() {
				// Only a preview that has had its state put back (data-ap-ed-ready) is read: a fresh render
				// still showing the saved page's defaults must never overwrite what the editor had open.
				var out = output();
				if ( restoring || ! out || ! out.hasAttribute( 'data-ap-ed-ready' ) ) {
					return;
				}
				var buttons = host.querySelectorAll( 'button' );
				var active = [];
				for ( var b = 0; b < buttons.length; b++ ) {
					if ( buttons[ b ].matches( ACTIVE ) ) {
						active.push( b );
					}
				}
				shown = {
					open: Array.prototype.map.call( host.querySelectorAll( 'details' ), function ( d ) {
						return d.open;
					} ),
					checked: Array.prototype.map.call( host.querySelectorAll( TOGGLES ), function ( input ) {
						return input.checked;
					} ),
					active: active,
				};
			}
			function restore() {
				if ( ! shown ) {
					return;
				}
				var rows = host.querySelectorAll( 'details' );
				shown.open.forEach( function ( open, i ) {
					if ( rows[ i ] && rows[ i ].open !== open ) {
						rows[ i ].open = open;
					}
				} );
				var inputs = host.querySelectorAll( TOGGLES );
				shown.checked.forEach( function ( checked, i ) {
					var input = inputs[ i ];
					if ( input && input.checked !== checked ) {
						input.checked = checked;
						var ev = doc.createEvent( 'Event' );
						ev.initEvent( 'change', true, false );
						input.dispatchEvent( ev ); // A script-driven filter follows the restored choice.
					}
				} );
				var buttons = host.querySelectorAll( 'button' );
				shown.active.forEach( function ( i ) {
					if ( buttons[ i ] && ! buttons[ i ].matches( ACTIVE ) ) {
						buttons[ i ].click(); // The section's own script moves to that slide / chip / panel.
					}
				} );
			}

			var frame = 0;
			function rebind() {
				var win = doc.defaultView || window;
				win.cancelAnimationFrame( frame );
				frame = win.requestAnimationFrame( function () {
					// A fresh server preview: start its sliders and switchers, then put back what was showing.
					var out = output();
					if ( out && ! out.hasAttribute( 'data-ap-ed-seen' ) && ! host.querySelector( '.components-spinner' ) ) {
						out.setAttribute( 'data-ap-ed-seen', '' );
						runBehaviours( host, latest.current.name ).then( function () {
							if ( ! out.isConnected ) {
								return; // Replaced by a newer render meanwhile; that one restores itself.
							}
							restoring = true; // The clicks that replay the state are not the editor's own.
							try {
								restore();
							} finally {
								restoring = false;
							}
							out.setAttribute( 'data-ap-ed-ready', '' );
							rebind(); // Text on the slide or tab now showing gets marked.
						} );
					}
					if ( host.querySelector( '[data-ap-editing]' ) ) {
						return;
					}
					var p = latest.current;
					var type = blocks.getBlockType( p.name );
					bindPreview( host, p.name, ( type && type.attributes ) || {}, p.attributes );
				} );
			}
			var observer = new ( doc.defaultView || window ).MutationObserver( rebind );
			observer.observe( host, { childList: true, subtree: true } );
			rebind();

			function fieldOf( node ) {
				var p = latest.current;
				var path = JSON.parse( node.getAttribute( 'data-ap-path' ) );
				var type = blocks.getBlockType( p.name );
				var list = fieldList( p.name, ( type && type.attributes ) || {}, p.attributes );
				for ( var i = 0; i < list.length; i++ ) {
					if ( list[ i ].path.join( '.' ) === path.join( '.' ) ) {
						return list[ i ];
					}
				}
				return { path: path, key: path[ path.length - 1 ], value: getPath( p.attributes, path ) || '', scope: {} };
			}

			function stop( node, commit ) {
				if ( ! node.hasAttribute( 'data-ap-editing' ) ) {
					return;
				}
				var before = node.getAttribute( 'data-ap-before' ) || '';
				var field = fieldOf( node );
				var text = readText( node );
				text = isMultiline( field.key ) ? text.replace( /[ \t]+\n/g, '\n' ).replace( /\n{3,}/g, '\n\n' ) : text.replace( /\s*\n\s*/g, ' ' );
				text = text.replace( /^[ \t\n]+|[ \t\n]+$/g, '' );
				node.removeAttribute( 'data-ap-editing' );
				node.removeAttribute( 'data-ap-before' );
				node.removeAttribute( 'contenteditable' );
				if ( ! commit ) {
					node.textContent = before;
					return;
				}
				var current = getPath( latest.current.attributes, field.path );
				if ( norm( text ) !== norm( current || '' ) || ( '' === norm( current || '' ) && text !== '' ) ) {
					latest.current.setAttributes( patchFor( latest.current.attributes, field.path, text ) );
				}
			}

			function onMouseDown( e ) {
				var node = e.target.closest && e.target.closest( '[data-ap-path]' );
				if ( ! node || ! host.contains( node ) ) {
					return;
				}
				if ( node.getAttribute( 'data-ap-kind' ) === 'image' ) {
					return;
				}
				if ( node.hasAttribute( 'data-ap-editing' ) ) {
					return;
				}
				node.setAttribute( 'data-ap-editing', '' );
				node.setAttribute( 'data-ap-before', node.textContent );
				node.contentEditable = 'plaintext-only';
				if ( node.contentEditable !== 'plaintext-only' ) {
					node.contentEditable = 'true';
				}
			}

			/*
			 * An accordion row in the preview. The click handler below cancels the
			 * browser's own toggle (so a click can never leave the editor), so rows are
			 * opened and closed here instead: clicking the question text opens the row
			 * and edits the question (never closes it mid-edit); clicking anywhere else
			 * on the row (the +/− icon, the padding) opens or closes it. A click made by
			 * the keyboard while typing in the question is left alone.
			 */
			function toggleRow( summary, e ) {
				var details = summary.parentElement;
				if ( ! details || details.nodeName.toUpperCase() !== 'DETAILS' || details.querySelector( 'summary' ) !== summary ) {
					return;
				}
				var onText = e.target.closest( '[data-ap-kind="text"]' );
				if ( onText && e.detail === 0 && onText.hasAttribute( 'data-ap-editing' ) ) {
					return;
				}
				var open = onText ? true : ! details.open;
				if ( details.open !== open ) {
					details.open = open; // Opening one row of a group closes the others.
				}
				snapshot();
				rebind(); // A row that was closed when the preview was marked gets its answer marked now.
			}

			/* After a click or a change in the preview: remember what shows, and mark the text that just came into view. */
			var settle = 0;
			function afterInteraction() {
				if ( restoring ) {
					return;
				}
				var win = doc.defaultView || window;
				win.clearTimeout( settle );
				settle = win.setTimeout( function () {
					snapshot();
					rebind();
				}, 120 );
			}

			function onClick( e ) {
				var link = e.target.closest && e.target.closest( 'a, summary, button' );
				if ( link && host.contains( link ) ) {
					e.preventDefault(); // Never navigate (or toggle) out of the editor.
				}
				// A video poster stays a poster in the editor (no YouTube player while editing).
				var facade = e.target.closest && e.target.closest( '.apc-vid, [data-yt], [data-embed], [data-ap-video]' );
				if ( facade && host.contains( facade ) ) {
					e.stopPropagation();
				}
				afterInteraction();
				var summary = e.target.closest && e.target.closest( 'summary' );
				if ( summary && host.contains( summary ) ) {
					toggleRow( summary, e );
				}
				var node = e.target.closest && e.target.closest( '[data-ap-path]' );
				if ( ! node || ! host.contains( node ) ) {
					return;
				}
				if ( node.getAttribute( 'data-ap-kind' ) === 'image' ) {
					e.preventDefault();
					var picked = fieldOf( node );
					setImageTarget( latest.current.clientId, picked );
					pickImage( picked, latest.current.attributes, latest.current.setAttributes );
					return;
				}
				if ( node.hasAttribute( 'data-ap-editing' ) && doc.activeElement !== node ) {
					node.focus();
				}
			}

			function onKeyDown( e ) {
				var node = e.target.closest && e.target.closest( '[data-ap-editing]' );
				if ( ! node ) {
					return;
				}
				// The block editor must not treat typing here as block commands (Enter = new block, Backspace = delete block).
				e.stopPropagation();
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					stop( node, false );
					node.blur();
				} else if ( e.key === 'Enter' ) {
					e.preventDefault();
					if ( e.shiftKey && isMultiline( fieldOf( node ).key ) ) {
						doc.execCommand( 'insertLineBreak' );
					} else {
						node.blur();
					}
				}
			}

			function onPaste( e ) {
				var node = e.target.closest && e.target.closest( '[data-ap-editing]' );
				if ( ! node || node.contentEditable === 'plaintext-only' ) {
					return;
				}
				e.preventDefault();
				var text = ( e.clipboardData || window.clipboardData ).getData( 'text' );
				doc.execCommand( 'insertText', false, text );
			}

			function onFocusOut( e ) {
				var node = e.target.closest && e.target.closest( '[data-ap-editing]' );
				if ( node ) {
					stop( node, true );
				}
			}

			host.addEventListener( 'mousedown', onMouseDown, true );
			host.addEventListener( 'click', onClick, true );
			host.addEventListener( 'keydown', onKeyDown );
			host.addEventListener( 'paste', onPaste );
			host.addEventListener( 'focusout', onFocusOut );
			host.addEventListener( 'change', afterInteraction ); // A tab or filter picked through its label.
			return function () {
				observer.disconnect();
				( doc.defaultView || window ).cancelAnimationFrame( frame );
				( doc.defaultView || window ).clearTimeout( settle );
				host.removeEventListener( 'change', afterInteraction );
				host.removeEventListener( 'mousedown', onMouseDown, true );
				host.removeEventListener( 'click', onClick, true );
				host.removeEventListener( 'keydown', onKeyDown );
				host.removeEventListener( 'paste', onPaste );
				host.removeEventListener( 'focusout', onFocusOut );
			};
		}, [] );

		// POST, not GET: a full page section's attributes (7–13 KB of JSON, some of it markup) would
		// otherwise travel in the URL, where length limits and the host's WAF can turn the preview
		// into "The response is not a valid JSON response".
		return el(
			'div',
			{ ref: hostRef, className: 'ap-live' },
			el( ServerSideRender, { block: props.name, attributes: props.attributes, httpMethod: 'POST' } )
		);
	}

	/* The site header and footer: no in-place editing (LIVE_OFF), but still styled as on the page. */
	function StyledPreview( props ) {
		var ref = useRef( null );
		useEffect( function () {
			if ( ref.current ) {
				ensureStyles( ref.current.ownerDocument, props.name );
			}
		}, [] );
		return el(
			'div',
			{ ref: ref },
			el( ServerSideRender, { block: props.name, attributes: props.attributes, httpMethod: 'POST' } )
		);
	}

	/**
	 * One scalar control, chosen from the attribute's registered schema.
	 */
	function scalarControl( key, schema, value, onChange, blockName ) {
		if ( schema.type === 'boolean' ) {
			return el( ToggleControl, {
				key: key,
				label: labelFor( key, blockName ),
				checked: !! value,
				onChange: onChange,
			} );
		}

		if ( schema.enum ) {
			return el( SelectControl, {
				key: key,
				label: labelFor( key, blockName ),
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
				label: labelFor( key, blockName ),
				value: value,
				onChange: function ( v ) {
					onChange( parseInt( v, 10 ) || 0 );
				},
			} );
		}

		var field = el( TEXTAREAS[ key ] ? TextareaControl : TextControl, {
			key: key,
			label: labelFor( key, blockName ),
			value: value || '',
			onChange: onChange,
		} );

		// Image fields: pick or upload from the Media Library instead of pasting a URL.
		if ( IMAGE_KEY.test( key ) && window.wp && window.wp.media ) {
			return el(
				'div',
				{ key: key },
				field,
				el(
					Button,
					{
						variant: 'secondary',
						style: { marginTop: '-8px', marginBottom: '16px' },
						onClick: function () {
							var frame = window.wp.media( {
								title: __( 'Choose image', 'assetpanda' ),
								button: { text: __( 'Use this image', 'assetpanda' ) },
								library: { type: 'image' },
								multiple: false,
							} );
							frame.on( 'select', function () {
								var att = frame.state().get( 'selection' ).first().toJSON();
								if ( att && att.url ) {
									onChange( att.url );
								}
							} );
							frame.open();
						},
					},
					value ? __( 'Replace image', 'assetpanda' ) : __( 'Choose image', 'assetpanda' )
				)
			);
		}
		return field;
	}

	/*
	 * Image target (builder library, 2026-11): the last image clicked on a
	 * block's preview. The Media Library opens at once (upload or pick); the
	 * library panel under the editor can fill the same image instead, through
	 * window.APBuilder.applyImage(), for two minutes after the click.
	 */
	var imageTarget = null;

	function setImageTarget( clientId, field ) {
		imageTarget = { clientId: clientId, path: field.path, key: field.key, scope: field.scope, at: Date.now() };
		window.dispatchEvent( new window.CustomEvent( 'ap-builder-image-target', { detail: { active: true } } ) );
	}

	window.APBuilder = Object.assign( window.APBuilder || {}, {
		imageTarget: function () {
			if ( ! imageTarget || Date.now() - imageTarget.at > 120000 ) {
				return null;
			}
			var block = wp.data.select( 'core/block-editor' ).getBlock( imageTarget.clientId );
			return block ? { clientId: imageTarget.clientId, blockTitle: ( blocks.getBlockType( block.name ) || {} ).title || block.name } : null;
		},
		clearImageTarget: function () {
			imageTarget = null;
			window.dispatchEvent( new window.CustomEvent( 'ap-builder-image-target', { detail: { active: false } } ) );
		},
		applyImage: function ( url, alt ) {
			if ( ! this.imageTarget() ) {
				return false;
			}
			var store = wp.data.select( 'core/block-editor' );
			var attributes = store.getBlockAttributes( imageTarget.clientId ) || {};
			var patch = patchFor( attributes, imageTarget.path, url );
			var altKey = altKeyFor( imageTarget.key, imageTarget.scope || {} );
			if ( altKey && alt ) {
				var altPath = imageTarget.path.length === 1 ? [ altKey ] : [ imageTarget.path[ 0 ], imageTarget.path[ 1 ], altKey ];
				Object.assign( patch, patchFor( Object.assign( {}, attributes, patch ), altPath, alt ) );
			}
			wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( imageTarget.clientId, patch );
			this.clearImageTarget();
			return true;
		},
	} );

	/* ---- Container: a styled box with inner blocks ------------------------ */

	var CONTAINER_TEMPLATE = [
		[ 'core/heading', { level: 2, placeholder: 'Heading' } ],
		[ 'core/paragraph', { placeholder: 'Write something, or add any block here.' } ],
	];

	/* The same classes as ui/components/container/render.php, so the kit's CSS draws the box in the editor. */
	function containerClasses( a ) {
		var c = [
			'ap-container',
			'ap-container--' + ( a.look || 'card' ),
			'ap-container--w-' + ( a.width || 'content' ),
			'ap-container--pad-' + ( a.padding || 'l' ),
			'ap-container--align-' + ( a.text_align || 'left' ),
			'ap-container--radius-' + ( a.radius || 'l' ),
		];
		if ( a.background_image ) {
			c.push( 'ap-container--has-bg', 'ap-container--overlay-' + ( a.overlay || 'none' ) );
		}
		if ( a.decoration && a.decoration !== 'none' ) {
			c.push( 'ap-container--deco-' + a.decoration );
		}
		return c.join( ' ' );
	}

	function containerEdit( props ) {
		var a = props.attributes;
		var boxRef = useRef( null );
		useEffect( function () {
			if ( boxRef.current ) {
				ensureStyles( boxRef.current.ownerDocument, props.name );
			}
		}, [] );
		var type = blocks.getBlockType( props.name );
		var schema = ( type && type.attributes ) || {};
		var controls = Object.keys( schema )
			.filter( function ( key ) {
				return ! SKIP[ key ];
			} )
			.map( function ( key ) {
				return scalarControl(
					key,
					schema[ key ],
					a[ key ],
					function ( v ) {
						var patch = {};
						patch[ key ] = v;
						props.setAttributes( patch );
					},
					props.name
				);
			} );
		var style = {};
		if ( a.background_image ) {
			style[ '--ap-container-bg' ] = 'url("' + String( a.background_image ).replace( /["\\\n\r]/g, '' ) + '")';
		}
		if ( a.decoration === 'panda' && window.AP_UI_KIT ) {
			style[ '--ap-container-panda' ] = 'url("' + window.AP_UI_KIT.url + '/components/container/assets/panda.svg")';
		}
		var inner = blockEditor.useInnerBlocksProps( { className: 'ap-container__inner' }, { template: CONTAINER_TEMPLATE } );
		return el(
			Fragment,
			null,
			el( InspectorControls, null, el( PanelBody, { title: __( 'Box', 'assetpanda' ), initialOpen: true }, controls ) ),
			el(
				'div',
				blockEditor.useBlockProps(),
				el(
					'section',
					{ ref: boxRef, className: containerClasses( a ), style: style },
					a.decoration && a.decoration !== 'none' ? el( 'span', { className: 'ap-container__deco', 'aria-hidden': 'true' } ) : null,
					el( 'div', inner )
				)
			)
		);
	}

	/**
	 * A repeater panel for an array attribute.
	 */
	function repeater( blockName, key, rows, setRows, open ) {
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
					el( 'strong', null, labelFor( key, blockName ) + ' ' + ( index + 1 ) ),
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
						label: labelFor( field, blockName ),
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
			{ key: key, title: labelFor( key, blockName ), initialOpen: open !== false },
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

		var live = ! LIVE_OFF[ name ];
		var arrayPanels = [];
		var optionFields = [];
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
					repeater(
						name,
						key,
						attributes[ key ] || [],
						function ( next ) {
							var patch = {};
							patch[ key ] = next;
							setAttributes( patch );
						},
						! live
					)
				);
				return;
			}

			// Looks and switches (enums, toggles, numbers) are the block's options; strings are its copy.
			var isOption = attr.type === 'boolean' || attr.type === 'number' || attr.type === 'integer' || !! attr.enum;
			( isOption ? optionFields : scalarFields ).push(
				scalarControl(
					key,
					attr,
					attributes[ key ],
					function ( v ) {
						var patch = {};
						patch[ key ] = v;
						setAttributes( patch );
					},
					name
				)
			);
		} );

		return el(
			Fragment,
			null,
			live ? linksControl( name, schema, attributes, setAttributes ) : null,
			el(
				InspectorControls,
				null,
				live
					? el(
							PanelBody,
							{ title: __( 'Editing this block', 'assetpanda' ), initialOpen: true },
							el(
								'p',
								{ style: { margin: 0 } },
								__(
									'Click any text on the block to change it (Enter or click away to keep it, Esc to undo). Click an image to replace it from the Media Library. Links are under the link icon in the block toolbar. Every field is also listed below.',
									'assetpanda'
								)
							)
					  )
					: null,
				optionFields.length
					? el( PanelBody, { title: __( 'Options', 'assetpanda' ), initialOpen: true }, optionFields )
					: null,
				scalarFields.length
					? el( PanelBody, { title: __( 'Text, images and links', 'assetpanda' ), initialOpen: ! live }, scalarFields )
					: null,
				arrayPanels
			),
			el(
				'div',
				blockEditor.useBlockProps(),
				live
					? el( LivePreview, { name: name, clientId: props.clientId, attributes: attributes, setAttributes: setAttributes } )
					: el( StyledPreview, { name: name, attributes: attributes } )
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
		var settings = {
			edit: edit,
			// Dynamic block: the front end comes from render.php via the kit.
			save: function () {
				return null;
			},
		};
		if ( TITLES[ 'assetpanda/' + name ] ) {
			settings.title = TITLES[ 'assetpanda/' + name ];
		}
		if ( name === 'container' ) {
			// Inner blocks are saved in the post; render.php puts them in the kit's box.
			settings.edit = containerEdit;
			settings.save = function () {
				return el( blockEditor.InnerBlocks.Content );
			};
		}
		blocks.registerBlockType( 'assetpanda/' + name, settings );
	} );

	/*
	 * Server-only dynamic blocks (window.AP_UI_SERVER_BLOCKS, printed by the
	 * kit bootstrap: registered in PHP with a render callback and no editor
	 * script). Same editor as the kit blocks; the server render is the preview.
	 */
	( window.AP_UI_SERVER_BLOCKS || [] ).forEach( function ( name ) {
		if ( blocks.getBlockType( name ) ) {
			return;
		}
		var meta = SERVER_BLOCKS[ name ] || [
			name
				.split( '/' )
				.pop()
				.replace( /-/g, ' ' )
				.replace( /^./, function ( c ) {
					return c.toUpperCase();
				} ),
			'block-default',
			false,
		];
		blocks.registerBlockType( name, {
			// 3, like every kit block: one block below 3 takes the whole editor out of its iframe.
			apiVersion: 3,
			title: meta[ 0 ],
			icon: meta[ 1 ],
			category: 'design',
			supports: { html: false, inserter: meta[ 2 ] },
			edit: edit,
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
