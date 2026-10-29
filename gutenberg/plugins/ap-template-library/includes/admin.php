<?php
/**
 * wp-admin screens: the library (template cards), the builder, and settings.
 *
 * Template Library (edit_pages)               admin.php?page=ap-template-library
 *   builder for one template                   admin.php?page=ap-template-library&template=<key>
 * Settings (manage_options)                    admin.php?page=ap-template-library-settings
 *
 * Settings changes post to admin-post.php (capability + nonce checked) and redirect back.
 *
 * @package APTemplateLibrary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_TL_PAGE          = 'ap-template-library';
const AP_TL_SETTINGS_PAGE = 'ap-template-library-settings';

add_action( 'admin_menu', 'ap_tl_admin_menu' );
add_action( 'admin_post_ap_tl_add', 'ap_tl_handle_add' );
add_action( 'admin_post_ap_tl_remove', 'ap_tl_handle_remove' );
add_action( 'admin_post_ap_tl_toggle', 'ap_tl_handle_toggle' );
add_action( 'admin_post_ap_tl_thumb', 'ap_tl_handle_thumb' );

/** Menus. */
function ap_tl_admin_menu() {
	$library = add_menu_page(
		__( 'Template Library', 'ap-template-library' ),
		__( 'Template Library', 'ap-template-library' ),
		'edit_pages',
		AP_TL_PAGE,
		'ap_tl_render_library_page',
		'dashicons-layout',
		21
	);
	add_submenu_page( AP_TL_PAGE, __( 'Template Library', 'ap-template-library' ), __( 'Library', 'ap-template-library' ), 'edit_pages', AP_TL_PAGE, 'ap_tl_render_library_page' );
	$settings = add_submenu_page( AP_TL_PAGE, __( 'Template Library settings', 'ap-template-library' ), __( 'Settings', 'ap-template-library' ), 'manage_options', AP_TL_SETTINGS_PAGE, 'ap_tl_render_settings_page' );

	if ( $library ) {
		add_action( 'load-' . $library, 'ap_tl_load_library_page' );
	}
	if ( $settings ) {
		add_action( 'load-' . $settings, 'ap_tl_load_settings_page' );
	}
}

/** URL of the library (or of one template's builder). */
function ap_tl_library_url( $template = '' ) {
	$args = array( 'page' => AP_TL_PAGE );
	if ( '' !== $template ) {
		$args['template'] = $template;
	}
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/** URL of the settings screen. */
function ap_tl_settings_url( array $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => AP_TL_SETTINGS_PAGE ), $args ), admin_url( 'admin.php' ) );
}

/** The template key requested in the URL ('' = the library). */
function ap_tl_requested_template() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen switch.
	return isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
}

/* -------------------------------------------------------------------------- */
/* Library + builder                                                          */
/* -------------------------------------------------------------------------- */

/** load-* hook of the library page: assets, and the builder's data when a template is open. */
function ap_tl_load_library_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You are not allowed to use the Template Library.', 'ap-template-library' ), 403 );
	}
	wp_enqueue_style( 'ap-tl-admin', ap_tl_url( 'assets/admin.css' ), array( 'dashicons' ), ap_tl_asset_ver( 'assets/admin.css' ) );

	$key = ap_tl_requested_template();
	if ( '' === $key ) {
		wp_enqueue_script( 'ap-tl-library', ap_tl_url( 'assets/library.js' ), array(), ap_tl_asset_ver( 'assets/library.js' ), true );
		return;
	}

	$GLOBALS['ap_tl_builder_error'] = '';
	try {
		$data = ap_tl_builder_data( $key );
	} catch ( Throwable $e ) {
		error_log( '[ap-template-library] builder: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$data = new WP_Error( 'ap_tl_error', __( 'The builder could not read this template. Nothing was changed.', 'ap-template-library' ) );
	}
	if ( is_wp_error( $data ) ) {
		$GLOBALS['ap_tl_builder_error'] = $data->get_error_message();
		return;
	}
	$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	if ( false === $json ) {
		$GLOBALS['ap_tl_builder_error'] = __( 'The builder could not read this template (its content could not be encoded).', 'ap-template-library' );
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'ap-tl-builder', ap_tl_url( 'assets/builder.js' ), array( 'wp-api-fetch', 'media-editor' ), ap_tl_asset_ver( 'assets/builder.js' ), true );
	wp_add_inline_script( 'ap-tl-builder', 'window.AP_TL_BUILDER = ' . $json . ';', 'before' );
	add_filter(
		'admin_body_class',
		static function ( $classes ) {
			return $classes . ' ap-tl-builder-screen';
		}
	);
}

/**
 * Everything the builder script needs for one template.
 *
 * @param string $key Template key.
 * @return array|WP_Error
 */
function ap_tl_builder_data( $key ) {
	$template = ap_tl_get_template( $key );
	if ( ! $template ) {
		return new WP_Error( 'ap_tl_unknown_template', __( 'That template does not exist (it may have been removed in Template Library settings).', 'ap-template-library' ) );
	}
	$context = ap_tl_template_context( $template );
	if ( 'missing' === $context['status'] ) {
		return new WP_Error( 'ap_tl_reference_missing', __( 'Reference missing: the page this template is built from is not published on this site, so the template cannot be used. An administrator can point the template at another page in Template Library settings.', 'ap-template-library' ) );
	}
	if ( 'no_post_type' === $context['status'] ) {
		/* translators: %s: post type name. */
		return new WP_Error( 'ap_tl_no_post_type', sprintf( __( 'This template creates "%s" content, which is not registered on this site (is its plugin active?).', 'ap-template-library' ), $context['post_type'] ) );
	}
	if ( ! ap_tl_user_can_create( $context['post_type'] ) ) {
		return new WP_Error( 'ap_tl_forbidden', __( 'You are not allowed to create this kind of content.', 'ap-template-library' ) );
	}

	$reference = $context['post'];
	$blocks    = ap_tl_builder_blocks( $reference );
	$page      = ap_tl_page_settings( $template, $context );

	$progress = null;
	$saved    = ap_tl_get_progress( get_current_user_id() );
	if ( isset( $saved[ $template['key'] ]['state'] ) ) {
		$entry    = $saved[ $template['key'] ];
		$progress = array(
			'state'       => (string) $entry['state'],
			'saved'       => (int) ( $entry['saved'] ?? 0 ),
			'saved_label' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $entry['saved'] ?? time() ) ),
			'hash'        => (string) ( $entry['hash'] ?? '' ),
		);
	}

	return array(
		'template'      => array(
			'key'         => $template['key'],
			'label'       => $template['label'],
			'group_label' => $template['group_label'],
			'description' => $template['description'],
		),
		'reference'     => array(
			'id'    => (int) $reference->ID,
			'title' => html_entity_decode( get_the_title( $reference ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'link'  => (string) get_permalink( $reference ),
		),
		'blocks'        => $blocks['blocks'],
		'types'         => $blocks['types'],
		'hash'          => $blocks['hash'],
		'page'          => $page['page'],
		'settings'      => $page['settings'],
		'progress'      => $progress,
		'canUnfiltered' => current_user_can( 'unfiltered_html' ),
		'rest'          => array(
			'draft'    => '/' . AP_TL_REST_NS . '/draft',
			'progress' => '/' . AP_TL_REST_NS . '/progress',
		),
		'urls'          => array( 'library' => ap_tl_library_url() ),
		'i18n'          => ap_tl_builder_strings(),
	);
}

/** Interface strings for builder.js. */
function ap_tl_builder_strings() {
	return array(
		'backToLibrary'         => __( 'Back to Template Library', 'ap-template-library' ),
		/* translators: %s: template name. */
		'builderTitle'          => __( 'New page from: %s', 'ap-template-library' ),
		'basedOn'               => __( 'Based on', 'ap-template-library' ),
		/* translators: %d: number of sections. */
		'sectionCount'          => __( '(%d sections)', 'ap-template-library' ),
		'intro'                 => __( 'Each box is a section of the template, pre-filled with the reference page\'s content. Open a box to change its text and images, use the arrows to reorder, duplicate or remove sections, then press "Send to Draft". The draft opens in WordPress, where you can keep editing and publish it.', 'ap-template-library' ),
		'expandAll'             => __( 'Expand all', 'ap-template-library' ),
		'collapseAll'           => __( 'Collapse all', 'ap-template-library' ),
		'reset'                 => __( 'Reset to template', 'ap-template-library' ),
		'confirmReset'          => __( 'Discard every change on this screen and start again from the template?', 'ap-template-library' ),
		'addSectionLabel'       => __( 'Add a section from this template:', 'ap-template-library' ),
		'addSection'            => __( 'Add section', 'ap-template-library' ),
		'noSections'            => __( 'There are no sections. Add one from the list below, or reset to the template.', 'ap-template-library' ),
		/* translators: 1: position, 2: section name. */
		'sectionLabel'          => __( 'Section %1$s: %2$s', 'ap-template-library' ),
		/* translators: %s: section name. */
		'moveSectionUp'         => __( 'Move "%s" up', 'ap-template-library' ),
		/* translators: %s: section name. */
		'moveSectionDown'       => __( 'Move "%s" down', 'ap-template-library' ),
		/* translators: %s: section name. */
		'duplicateSection'      => __( 'Duplicate "%s"', 'ap-template-library' ),
		/* translators: %s: section name. */
		'removeSection'         => __( 'Remove "%s"', 'ap-template-library' ),
		/* translators: %s: section name. */
		'confirmRemove'         => __( 'Remove the section "%s"? You can add it back from the list at the bottom.', 'ap-template-library' ),
		/* translators: 1: section name, 2: position. */
		'movedTo'               => __( '%1$s moved to position %2$s.', 'ap-template-library' ),
		/* translators: %s: section name. */
		'duplicated'            => __( '%s duplicated.', 'ap-template-library' ),
		/* translators: %s: section name. */
		'removed'               => __( '%s removed.', 'ap-template-library' ),
		'badgeKit'              => __( 'Section', 'ap-template-library' ),
		'badgeCore'             => __( 'Content', 'ap-template-library' ),
		'badgeStatic'           => __( 'Kept as is', 'ap-template-library' ),
		'displayOptions'        => __( 'Display options', 'ap-template-library' ),
		'advanced'              => __( 'Advanced: layout and geometry', 'ap-template-library' ),
		'advancedNote'          => __( 'Positions, sizes and other design values copied from the reference. Change them only if you know the layout.', 'ap-template-library' ),
		'noContentFields'       => __( 'This section has no text or image fields; it is kept as designed.', 'ap-template-library' ),
		'unregistered'          => __( 'This section\'s block is not registered on this site (is the Asset Panda UI Kit plugin active?). Its fields are read from the saved values.', 'ap-template-library' ),
		'innerKept'             => __( 'Blocks nested inside this section are kept as they are.', 'ap-template-library' ),
		'keptAsIs'              => __( 'Kept exactly as on the reference. You can edit it in the block editor after the draft is created.', 'ap-template-library' ),
		'nestedKit'             => __( 'A section nested in a container is kept as it is. Edit it in the block editor after the draft is created.', 'ap-template-library' ),
		'noText'                => __( '(no text)', 'ap-template-library' ),
		'positionSize'          => __( 'Position and size', 'ap-template-library' ),
		/* translators: %d: number of items. */
		'itemCount'             => __( '%d items', 'ap-template-library' ),
		'addItem'               => __( 'Add item', 'ap-template-library' ),
		/* translators: 1: item number, 2: item label. */
		'itemTitle'             => __( '%1$s. %2$s', 'ap-template-library' ),
		'item'                  => __( 'Item', 'ap-template-library' ),
		'value'                 => __( 'Value', 'ap-template-library' ),
		'moveUp'                => __( 'Move up', 'ap-template-library' ),
		'moveDown'              => __( 'Move down', 'ap-template-library' ),
		'duplicate'             => __( 'Duplicate', 'ap-template-library' ),
		'removeItem'            => __( 'Remove', 'ap-template-library' ),
		'remove'                => __( 'Remove', 'ap-template-library' ),
		'none'                  => __( '— None —', 'ap-template-library' ),
		'chooseImage'           => __( 'Choose image', 'ap-template-library' ),
		'replaceImage'          => __( 'Replace image', 'ap-template-library' ),
		'useImage'              => __( 'Use this image', 'ap-template-library' ),
		'chooseVideo'           => __( 'Choose video', 'ap-template-library' ),
		'useVideo'              => __( 'Use this video', 'ap-template-library' ),
		'noImage'               => __( 'No image', 'ap-template-library' ),
		'noMedia'               => __( 'The media library is not available on this screen.', 'ap-template-library' ),
		'urlPlaceholder'        => __( 'Image URL, or choose from the media library', 'ap-template-library' ),
		'linkPlaceholder'       => __( '/page-path/ or https://…', 'ap-template-library' ),
		'codeNote'              => __( 'Markup (for example an SVG icon). Unsafe tags are removed when the draft is created.', 'ap-template-library' ),
		'invalidJson'           => __( 'This is not valid JSON yet; the last valid value is kept.', 'ap-template-library' ),
		'jsonNote'              => __( 'Structured value (JSON).', 'ap-template-library' ),
		'text'                  => __( 'Text', 'ap-template-library' ),
		'inlineHtml'            => __( 'Inline HTML is allowed: links, bold, italics.', 'ap-template-library' ),
		'listItems'             => __( 'List items', 'ap-template-library' ),
		'image'                 => __( 'Image', 'ap-template-library' ),
		'altText'               => __( 'Alt text', 'ap-template-library' ),
		'buttonText'            => __( 'Button text', 'ap-template-library' ),
		'buttonLink'            => __( 'Button link', 'ap-template-library' ),
		'embedUrl'              => __( 'Embed URL (YouTube, Vimeo…)', 'ap-template-library' ),
		'html'                  => __( 'HTML', 'ap-template-library' ),
		'htmlFiltered'          => __( 'Scripts and other unsafe markup are removed when the draft is created.', 'ap-template-library' ),
		'publishPanel'          => __( 'Create draft', 'ap-template-library' ),
		/* translators: %s: post type label, e.g. Page or Resource. */
		'createsDraft'          => __( 'Creates a new %s as a draft. Nothing is published: you publish it from WordPress when it is ready.', 'ap-template-library' ),
		'saveProgress'          => __( 'Save progress', 'ap-template-library' ),
		'sendToDraft'           => __( 'Send to Draft', 'ap-template-library' ),
		'sending'               => __( 'Creating the draft…', 'ap-template-library' ),
		'saving'                => __( 'Saving progress…', 'ap-template-library' ),
		/* translators: %s: time. */
		'progressSaved'         => __( 'Progress saved at %s.', 'ap-template-library' ),
		'draftCreated'          => __( 'Draft created.', 'ap-template-library' ),
		/* translators: %s: title of the new draft. */
		'draftCreatedBody'      => __( '"%s" is saved as a draft. Nothing was published.', 'ap-template-library' ),
		'preview'               => __( 'Preview', 'ap-template-library' ),
		'openEditor'            => __( 'Open in editor', 'ap-template-library' ),
		'failed'                => __( 'Something went wrong. Nothing was saved.', 'ap-template-library' ),
		'titleRequired'         => __( 'Give the new page a title (Page settings).', 'ap-template-library' ),
		'fixJson'               => __( 'A structured (JSON) field is not valid. Fix it before sending.', 'ap-template-library' ),
		/* translators: %s: date and time. */
		'progressFound'         => __( 'You have unsent work on this template, saved %s.', 'ap-template-library' ),
		'referenceChanged'      => __( 'The reference page has changed since then; sections that no longer exist will be left out.', 'ap-template-library' ),
		'resume'                => __( 'Resume saved progress', 'ap-template-library' ),
		'discard'               => __( 'Discard it', 'ap-template-library' ),
		'discarded'             => __( 'Saved progress discarded.', 'ap-template-library' ),
		'restored'              => __( 'Saved progress restored.', 'ap-template-library' ),
		/* translators: %d: number of sections. */
		'restoredDropped'       => __( 'Saved progress restored; %d section(s) no longer exist on the reference and were left out.', 'ap-template-library' ),
		'progressUnreadable'    => __( 'The saved progress could not be read.', 'ap-template-library' ),
		/* translators: %s: post type label. */
		'pageSettings'          => __( '%s settings', 'ap-template-library' ),
		'pageTitle'             => __( 'Title', 'ap-template-library' ),
		'slug'                  => __( 'URL slug', 'ap-template-library' ),
		'slugAuto'              => __( 'Made from the title when published', 'ap-template-library' ),
		'parent'                => __( 'Parent page', 'ap-template-library' ),
		'noParent'              => __( '(no parent)', 'ap-template-library' ),
		'noTerms'               => __( 'None defined yet.', 'ap-template-library' ),
		'excerpt'               => __( 'Excerpt', 'ap-template-library' ),
		'featured'              => __( 'Featured image', 'ap-template-library' ),
		'featuredFromReference' => __( 'Copied from the reference. Replace it for the new page.', 'ap-template-library' ),
		'labels'                => array(
			'title'        => __( 'Title', 'ap-template-library' ),
			'title_accent' => __( 'Title accent (gradient words)', 'ap-template-library' ),
			'eyebrow'      => __( 'Eyebrow', 'ap-template-library' ),
			'lede'         => __( 'Lead paragraph', 'ap-template-library' ),
			'intro'        => __( 'Intro', 'ap-template-library' ),
			'body'         => __( 'Body', 'ap-template-library' ),
			'subtitle'     => __( 'Subtitle', 'ap-template-library' ),
			'cta_label'    => __( 'Button label', 'ap-template-library' ),
			'cta_url'      => __( 'Button link', 'ap-template-library' ),
			'link_label'   => __( 'Link label', 'ap-template-library' ),
			'link_url'     => __( 'Link URL', 'ap-template-library' ),
			'url'          => __( 'Link URL', 'ap-template-library' ),
			'image'        => __( 'Image', 'ap-template-library' ),
			'image_alt'    => __( 'Image alt text', 'ap-template-library' ),
			'alt'          => __( 'Alt text', 'ap-template-library' ),
			'icon'         => __( 'Icon', 'ap-template-library' ),
			'icon_svg'     => __( 'Icon (SVG markup)', 'ap-template-library' ),
			'logo'         => __( 'Logo', 'ap-template-library' ),
			'items'        => __( 'Items', 'ap-template-library' ),
			'badges'       => __( 'Badges', 'ap-template-library' ),
			'cards'        => __( 'Cards', 'ap-template-library' ),
			'label'        => __( 'Label', 'ap-template-library' ),
			'question'     => __( 'Question', 'ap-template-library' ),
			'answer'       => __( 'Answer', 'ap-template-library' ),
			'quote'        => __( 'Quote', 'ap-template-library' ),
			'author'       => __( 'Author', 'ap-template-library' ),
			'role'         => __( 'Role', 'ap-template-library' ),
			'kicker'       => __( 'Kicker', 'ap-template-library' ),
			'value'        => __( 'Value', 'ap-template-library' ),
			'description'  => __( 'Description', 'ap-template-library' ),
		),
	);
}

/** Library page callback: the library, or the builder when a template is open. */
function ap_tl_render_library_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	if ( '' !== ap_tl_requested_template() ) {
		ap_tl_render_builder();
		return;
	}
	try {
		ap_tl_render_library();
	} catch ( Throwable $e ) {
		error_log( '[ap-template-library] library: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'The Template Library could not be shown. Nothing was changed.', 'ap-template-library' ) . '</p></div></div>';
	}
}

/** The builder shell (builder.js fills it). */
function ap_tl_render_builder() {
	$error = isset( $GLOBALS['ap_tl_builder_error'] ) ? (string) $GLOBALS['ap_tl_builder_error'] : '';
	echo '<div class="wrap ap-tl ap-tl--builder">';
	if ( '' !== $error ) {
		echo '<h1>' . esc_html__( 'Template Library', 'ap-template-library' ) . '</h1>';
		echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		echo '<p><a class="button" href="' . esc_url( ap_tl_library_url() ) . '">' . esc_html__( 'Back to Template Library', 'ap-template-library' ) . '</a></p>';
		echo '</div>';
		return;
	}
	echo '<div id="ap-tl-builder" class="ap-tl-builder"><p class="ap-tl-loading"><span class="spinner is-active"></span>' . esc_html__( 'Loading the builder…', 'ap-template-library' ) . '</p></div>';
	echo '<noscript><div class="notice notice-error"><p>' . esc_html__( 'The builder needs JavaScript.', 'ap-template-library' ) . '</p></div></noscript>';
	echo '</div>';
}

/** The template cards. */
function ap_tl_render_library() {
	$templates = ap_tl_templates();
	$groups    = ap_tl_groups();
	$progress  = ap_tl_get_progress( get_current_user_id() );
	$counts    = array();
	$cards     = array();

	foreach ( $templates as $template ) {
		$context                        = ap_tl_template_context( $template );
		$counts[ $template['group'] ] = ( $counts[ $template['group'] ] ?? 0 ) + 1;
		$cards[]                        = array( $template, $context );
	}

	echo '<div class="wrap ap-tl ap-tl-library">';
	echo '<h1 class="wp-heading-inline">' . esc_html__( 'Template Library', 'ap-template-library' ) . '</h1>';
	if ( current_user_can( 'manage_options' ) ) {
		echo ' <a class="page-title-action" href="' . esc_url( ap_tl_settings_url() ) . '">' . esc_html__( 'Manage templates', 'ap-template-library' ) . '</a>';
	}
	echo '<hr class="wp-header-end">';
	echo '<p class="ap-tl-lead">' . esc_html__( 'Pick a template to start a new page from it. Every template is a real page on this site: you get its sections as boxes to fill in, then "Send to Draft" creates a draft you can preview and publish from WordPress.', 'ap-template-library' ) . '</p>';

	echo '<div class="ap-tl-filters">';
	echo '<div class="ap-tl-tabs" role="tablist" aria-label="' . esc_attr__( 'Template groups', 'ap-template-library' ) . '">';
	printf(
		'<button type="button" role="tab" class="ap-tl-tab is-active" data-group="all" aria-selected="true">%s <span class="count">%d</span></button>',
		esc_html__( 'All', 'ap-template-library' ),
		count( $cards )
	);
	foreach ( $groups as $group => $label ) {
		if ( empty( $counts[ $group ] ) ) {
			continue;
		}
		printf(
			'<button type="button" role="tab" class="ap-tl-tab" data-group="%1$s" aria-selected="false" tabindex="-1">%2$s <span class="count">%3$d</span></button>',
			esc_attr( $group ),
			esc_html( $label ),
			(int) $counts[ $group ]
		);
	}
	echo '</div>';
	echo '<p class="ap-tl-search-wrap"><label class="screen-reader-text" for="ap-tl-search">' . esc_html__( 'Search templates', 'ap-template-library' ) . '</label>';
	echo '<input type="search" id="ap-tl-search" class="ap-tl-search" placeholder="' . esc_attr__( 'Search templates…', 'ap-template-library' ) . '"></p>';
	echo '</div>';
	echo '<p class="screen-reader-text" aria-live="polite"><span class="ap-tl-visible-count">' . (int) count( $cards ) . '</span> ' . esc_html__( 'templates shown', 'ap-template-library' ) . '</p>';

	if ( ! $cards ) {
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No templates yet.', 'ap-template-library' ) . '</p></div>';
	}

	echo '<div class="ap-tl-grid">';
	foreach ( $cards as $card ) {
		ap_tl_render_card( $card[0], $card[1], $progress );
	}
	echo '</div>';
	echo '<div class="notice notice-info inline ap-tl-empty" hidden><p>' . esc_html__( 'No templates match.', 'ap-template-library' ) . '</p></div>';
	echo '</div>';
}

/**
 * One template card.
 *
 * @param array $template Template.
 * @param array $context  Template context.
 * @param array $progress The user's saved progress.
 */
function ap_tl_render_card( array $template, array $context, array $progress ) {
	$post     = $context['post'];
	$status   = $context['status'];
	$can      = 'ok' === $status && ap_tl_user_can_create( $context['post_type'] );
	$thumb    = ap_tl_thumbnail_url( $template, $post );
	$sections = $post ? ap_tl_section_titles( $post ) : array();
	$object   = get_post_type_object( $context['post_type'] );
	$search   = strtolower( $template['label'] . ' ' . $template['group_label'] . ' ' . $template['description'] . ' ' . ( $post ? $post->post_title : '' ) );

	printf(
		'<article class="ap-tl-card%1$s" data-group="%2$s" data-template="%3$s" data-search="%4$s">',
		'ok' === $status ? '' : ' is-unavailable',
		esc_attr( $template['group'] ),
		esc_attr( $template['key'] ),
		esc_attr( $search )
	);

	echo '<div class="ap-tl-card__thumb">';
	if ( '' !== $thumb ) {
		echo '<img src="' . esc_url( $thumb ) . '" alt="" loading="lazy" decoding="async">';
	} else {
		echo '<div class="ap-tl-ph" aria-hidden="true">';
		if ( $sections ) {
			foreach ( array_slice( $sections, 0, 7 ) as $i => $section ) {
				printf( '<span class="ap-tl-ph__bar ap-tl-ph__bar--%1$d">%2$s</span>', (int) $i, esc_html( $section ) );
			}
			if ( count( $sections ) > 7 ) {
				/* translators: %d: number of further sections. */
				echo '<span class="ap-tl-ph__more">' . esc_html( sprintf( __( '+ %d more', 'ap-template-library' ), count( $sections ) - 7 ) ) . '</span>';
			}
		} else {
			echo '<span class="ap-tl-ph__missing">' . esc_html( 'missing' === $status ? __( 'Reference missing', 'ap-template-library' ) : __( 'No sections', 'ap-template-library' ) ) . '</span>';
		}
		echo '</div>';
	}
	echo '</div>';

	echo '<div class="ap-tl-card__body">';
	echo '<p class="ap-tl-card__group">' . esc_html( $template['group_label'] ) . '</p>';
	echo '<h2 class="ap-tl-card__title">' . esc_html( $template['label'] ) . '</h2>';
	if ( '' !== $template['description'] ) {
		echo '<p class="ap-tl-card__desc">' . esc_html( $template['description'] ) . '</p>';
	}
	echo '<p class="ap-tl-card__meta">';
	if ( $post ) {
		echo esc_html__( 'Based on:', 'ap-template-library' ) . ' <a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $post ) ) . '</a>';
		echo '<br>';
		$count = count( ap_tl_builder_count( $post ) );
		/* translators: 1: number of sections, 2: post type label (Page, Resource…). */
		echo esc_html( sprintf( _n( '%1$d section · creates a %2$s', '%1$d sections · creates a %2$s', $count, 'ap-template-library' ), $count, $object ? $object->labels->singular_name : $context['post_type'] ) );
		if ( '' !== $context['resource_type'] && 'resource' === $context['post_type'] ) {
			echo ' (' . esc_html( $context['resource_type'] ) . ')';
		}
	}
	echo '</p>';
	if ( 'missing' === $status ) {
		echo '<p class="ap-tl-status ap-tl-status--warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html__( 'Reference missing: the page this template is built from is not published here.', 'ap-template-library' ) . '</p>';
	} elseif ( 'no_post_type' === $status ) {
		echo '<p class="ap-tl-status ap-tl-status--warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html__( 'The content type this template creates is not registered.', 'ap-template-library' ) . '</p>';
	} elseif ( ! $can ) {
		echo '<p class="ap-tl-status ap-tl-status--warn">' . esc_html__( 'You cannot create this kind of content.', 'ap-template-library' ) . '</p>';
	}
	if ( isset( $progress[ $template['key'] ]['saved'] ) ) {
		/* translators: %s: date and time. */
		echo '<p class="ap-tl-status ap-tl-status--info"><span class="dashicons dashicons-backup" aria-hidden="true"></span> ' . esc_html( sprintf( __( 'Unsent work saved %s', 'ap-template-library' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $progress[ $template['key'] ]['saved'] ) ) ) . '</p>';
	}
	echo '</div>';

	echo '<div class="ap-tl-card__actions">';
	if ( $can ) {
		/* translators: %s: template name. */
		echo '<a class="button button-primary ap-tl-use" href="' . esc_url( ap_tl_library_url( $template['key'] ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Use template: %s', 'ap-template-library' ), $template['label'] ) ) . '">' . esc_html__( 'Use template', 'ap-template-library' ) . '</a>';
	} else {
		echo '<button type="button" class="button button-primary" disabled>' . esc_html__( 'Use template', 'ap-template-library' ) . '</button>';
	}
	if ( $post ) {
		/* translators: %s: template name. */
		echo ' <a class="button ap-tl-preview-ref" href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( sprintf( __( 'Preview reference of %s (opens in a new tab)', 'ap-template-library' ), $template['label'] ) ) . '">' . esc_html__( 'Preview reference', 'ap-template-library' ) . '</a>';
	}
	if ( current_user_can( 'manage_options' ) ) {
		echo ' <a class="ap-tl-card__thumb-link" href="' . esc_url( ap_tl_settings_url() . '#ap-tl-row-' . $template['key'] ) . '">' . esc_html__( 'Set thumbnail', 'ap-template-library' ) . '</a>';
	}
	echo '</div>';
	echo '</article>';
}

/**
 * Top-level, non-whitespace blocks of a post (what the builder shows as boxes).
 *
 * @param WP_Post $post Post.
 * @return array
 */
function ap_tl_builder_count( WP_Post $post ) {
	static $cache = array();
	if ( ! isset( $cache[ $post->ID ] ) ) {
		$cache[ $post->ID ] = array_values(
			array_filter(
				parse_blocks( (string) $post->post_content ),
				static function ( $block ) {
					return ! ap_tl_is_whitespace_block( $block );
				}
			)
		);
	}
	return $cache[ $post->ID ];
}

/* -------------------------------------------------------------------------- */
/* Settings                                                                   */
/* -------------------------------------------------------------------------- */

/** load-* hook of the settings page. */
function ap_tl_load_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage the Template Library.', 'ap-template-library' ), 403 );
	}
	wp_enqueue_style( 'ap-tl-admin', ap_tl_url( 'assets/admin.css' ), array( 'dashicons' ), ap_tl_asset_ver( 'assets/admin.css' ) );
	wp_enqueue_media();
	wp_enqueue_script( 'ap-tl-settings', ap_tl_url( 'assets/settings.js' ), array( 'media-editor' ), ap_tl_asset_ver( 'assets/settings.js' ), true );
	wp_localize_script(
		'ap-tl-settings',
		'AP_TL_SETTINGS',
		array(
			'title'   => __( 'Choose a thumbnail for this template', 'ap-template-library' ),
			'button'  => __( 'Use as thumbnail', 'ap-template-library' ),
			'noMedia' => __( 'The media library is not available.', 'ap-template-library' ),
		)
	);
}

/** Notices shown after a settings action (fixed messages; nothing from the URL is echoed). */
function ap_tl_settings_notices() {
	return array(
		'added'      => array( 'success', __( 'Template added. It is now in the library.', 'ap-template-library' ) ),
		'removed'    => array( 'success', __( 'Template removed from the library.', 'ap-template-library' ) ),
		'hidden'     => array( 'success', __( 'Template hidden from the library.', 'ap-template-library' ) ),
		'shown'      => array( 'success', __( 'Template shown in the library again.', 'ap-template-library' ) ),
		'thumb'      => array( 'success', __( 'Thumbnail saved.', 'ap-template-library' ) ),
		'thumb_off'  => array( 'success', __( 'Thumbnail removed; the card uses the reference\'s featured image or a placeholder.', 'ap-template-library' ) ),
		'bad_ref'    => array( 'error', __( 'That reference was not found. Use the ID, URL or path of a published page or resource.', 'ap-template-library' ) ),
		'bad_label'  => array( 'error', __( 'Give the template a name.', 'ap-template-library' ) ),
		'bad_thumb'  => array( 'error', __( 'That file is not an image.', 'ap-template-library' ) ),
		'bad_key'    => array( 'error', __( 'That template was not found.', 'ap-template-library' ) ),
	);
}

/** The settings screen. */
function ap_tl_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$templates = ap_tl_all_templates();
	$groups    = ap_tl_groups();

	echo '<div class="wrap ap-tl ap-tl-settings">';
	echo '<h1 class="wp-heading-inline">' . esc_html__( 'Template Library settings', 'ap-template-library' ) . '</h1>';
	echo ' <a class="page-title-action" href="' . esc_url( ap_tl_library_url() ) . '">' . esc_html__( 'Open the library', 'ap-template-library' ) . '</a>';
	echo '<hr class="wp-header-end">';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only, mapped to fixed strings.
	$notice  = isset( $_GET['ap_tl_notice'] ) ? sanitize_key( wp_unslash( $_GET['ap_tl_notice'] ) ) : '';
	$notices = ap_tl_settings_notices();
	if ( isset( $notices[ $notice ] ) ) {
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notices[ $notice ][0] ), esc_html( $notices[ $notice ][1] ) );
	}

	echo '<p>' . esc_html__( 'Templates read their reference page every time they are used, so they always match the live design. Hide a default template you do not need, add your own from any published page or resource, and set the picture each card shows.', 'ap-template-library' ) . '</p>';

	echo '<h2>' . esc_html__( 'Templates', 'ap-template-library' ) . '</h2>';
	echo '<table class="widefat striped ap-tl-table"><thead><tr>';
	echo '<th scope="col">' . esc_html__( 'Thumbnail', 'ap-template-library' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Template', 'ap-template-library' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Reference', 'ap-template-library' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Creates', 'ap-template-library' ) . '</th>';
	echo '<th scope="col">' . esc_html__( 'Actions', 'ap-template-library' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $templates as $template ) {
		$context = ap_tl_template_context( $template );
		$post    = $context['post'];
		$thumb   = ap_tl_thumbnail_url( $template, $post );
		$object  = get_post_type_object( $context['post_type'] );

		echo '<tr id="ap-tl-row-' . esc_attr( $template['key'] ) . '"' . ( $template['hidden'] ? ' class="ap-tl-row--hidden"' : '' ) . '>';

		echo '<td class="ap-tl-table__thumb">';
		echo '<div class="ap-tl-thumb-mini">' . ( '' !== $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '<span>' . esc_html__( 'Placeholder', 'ap-template-library' ) . '</span>' ) . '</div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'ap_tl_settings' );
		echo '<input type="hidden" name="action" value="ap_tl_thumb"><input type="hidden" name="key" value="' . esc_attr( $template['key'] ) . '"><input type="hidden" name="thumb_id" value="0">';
		/* translators: %s: template name. */
		echo '<button type="button" class="button button-small ap-tl-pick-thumb" aria-label="' . esc_attr( sprintf( __( 'Choose thumbnail for %s', 'ap-template-library' ), $template['label'] ) ) . '">' . esc_html__( 'Choose thumbnail', 'ap-template-library' ) . '</button>';
		if ( ! empty( $template['thumbnail_id'] ) ) {
			echo ' <button type="submit" name="remove" value="1" class="button-link ap-tl-remove-thumb">' . esc_html__( 'Remove', 'ap-template-library' ) . '</button>';
		}
		echo '</form></td>';

		echo '<td><strong>' . esc_html( $template['label'] ) . '</strong><br><span class="description">' . esc_html( $template['group_label'] ) . ' · <code>' . esc_html( $template['key'] ) . '</code> · ';
		$origins = array(
			'default' => __( 'default', 'ap-template-library' ),
			'custom'  => __( 'added here', 'ap-template-library' ),
			'code'    => __( 'added in code (ap_tl_templates filter)', 'ap-template-library' ),
		);
		echo esc_html( $origins[ $template['origin'] ] ?? $template['origin'] );
		if ( $template['hidden'] ) {
			echo ' · <strong>' . esc_html__( 'hidden', 'ap-template-library' ) . '</strong>';
		}
		echo '</span></td>';

		echo '<td>';
		if ( $post ) {
			echo '<a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $post ) ) . '</a><br><code>' . esc_html( (string) wp_make_link_relative( get_permalink( $post ) ) ) . '</code> · #' . (int) $post->ID;
		} else {
			echo '<span class="ap-tl-status--warn">' . esc_html__( 'Reference missing', 'ap-template-library' ) . '</span><br><span class="description">' . esc_html( ap_tl_describe_source( $template['source'] ) ) . '</span>';
		}
		echo '</td>';

		echo '<td>' . esc_html( $object ? $object->labels->singular_name : $context['post_type'] );
		if ( '' !== $context['resource_type'] && 'resource' === $context['post_type'] ) {
			echo '<br><span class="description">' . esc_html( $context['resource_type'] ) . '</span>';
		}
		echo '</td>';

		echo '<td>';
		if ( 'default' === $template['origin'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'ap_tl_settings' );
			echo '<input type="hidden" name="action" value="ap_tl_toggle"><input type="hidden" name="key" value="' . esc_attr( $template['key'] ) . '">';
			echo '<button type="submit" class="button button-small">' . esc_html( $template['hidden'] ? __( 'Show in library', 'ap-template-library' ) : __( 'Hide from library', 'ap-template-library' ) ) . '</button>';
			echo '</form>';
		} elseif ( 'custom' === $template['origin'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'ap_tl_settings' );
			echo '<input type="hidden" name="action" value="ap_tl_remove"><input type="hidden" name="key" value="' . esc_attr( $template['key'] ) . '">';
			echo '<button type="submit" class="button button-small button-link-delete">' . esc_html__( 'Remove', 'ap-template-library' ) . '</button>';
			echo '</form>';
		} else {
			echo '<span class="description">' . esc_html__( 'Change it in code.', 'ap-template-library' ) . '</span>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';

	ap_tl_render_add_form( $groups );
	echo '</div>';
}

/**
 * Human description of a template source (for a missing reference).
 *
 * @param array $source Source.
 * @return string
 */
function ap_tl_describe_source( $source ) {
	if ( ! empty( $source['id'] ) ) {
		/* translators: %d: post ID. */
		return sprintf( __( 'Looks for post #%d', 'ap-template-library' ), (int) $source['id'] );
	}
	if ( ! empty( $source['path'] ) ) {
		/* translators: %s: page path. */
		return sprintf( __( 'Looks for the published page /%s/', 'ap-template-library' ), trim( (string) $source['path'], '/' ) );
	}
	if ( ! empty( $source['slug'] ) ) {
		/* translators: 1: slug, 2: resource type. */
		return sprintf( __( 'Looks for the published resource "%1$s" (else the newest %2$s)', 'ap-template-library' ), (string) $source['slug'], (string) ( $source['resource_type'] ?? '' ) );
	}
	return '';
}

/**
 * The "Add a template" form.
 *
 * @param array $groups Groups.
 */
function ap_tl_render_add_form( array $groups ) {
	$candidates = get_posts(
		array(
			'post_type'        => ap_tl_source_post_types(),
			'post_status'      => 'publish',
			'numberposts'      => 600,
			'orderby'          => array(
				'post_type' => 'ASC',
				'title'     => 'ASC',
			),
			'suppress_filters' => true,
		)
	);

	echo '<h2 id="ap-tl-add">' . esc_html__( 'Add a template', 'ap-template-library' ) . '</h2>';
	echo '<p>' . esc_html__( 'Any published page or resource can be a template. Its blocks become the template\'s sections.', 'ap-template-library' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ap-tl-add-form">';
	wp_nonce_field( 'ap_tl_settings' );
	echo '<input type="hidden" name="action" value="ap_tl_add">';
	echo '<table class="form-table" role="presentation"><tbody>';

	echo '<tr><th scope="row"><label for="ap-tl-ref">' . esc_html__( 'Reference page', 'ap-template-library' ) . '</label></th><td>';
	echo '<input type="text" id="ap-tl-ref" name="reference" class="regular-text" list="ap-tl-ref-list" required autocomplete="off" aria-describedby="ap-tl-ref-help">';
	echo '<datalist id="ap-tl-ref-list">';
	foreach ( $candidates as $candidate ) {
		$object = get_post_type_object( $candidate->post_type );
		printf(
			'<option value="%1$d">%2$s</option>',
			(int) $candidate->ID,
			esc_html( get_the_title( $candidate ) . ' — ' . wp_make_link_relative( get_permalink( $candidate ) ) . ' (' . ( $object ? $object->labels->singular_name : $candidate->post_type ) . ')' )
		);
	}
	echo '</datalist>';
	echo '<p class="description" id="ap-tl-ref-help">' . esc_html__( 'Start typing a title, or paste the page\'s ID, URL or path (for example /solutions/education/).', 'ap-template-library' ) . '</p></td></tr>';

	echo '<tr><th scope="row"><label for="ap-tl-label">' . esc_html__( 'Template name', 'ap-template-library' ) . '</label></th><td><input type="text" id="ap-tl-label" name="label" class="regular-text" required></td></tr>';

	echo '<tr><th scope="row"><label for="ap-tl-group">' . esc_html__( 'Group', 'ap-template-library' ) . '</label></th><td><select id="ap-tl-group" name="group">';
	foreach ( $groups as $group => $label ) {
		echo '<option value="' . esc_attr( $group ) . '"' . selected( 'other', $group, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></td></tr>';

	echo '<tr><th scope="row"><label for="ap-tl-desc">' . esc_html__( 'Description', 'ap-template-library' ) . '</label></th><td><textarea id="ap-tl-desc" name="description" rows="2" class="large-text"></textarea></td></tr>';

	echo '<tr><th scope="row"><label for="ap-tl-type">' . esc_html__( 'Creates', 'ap-template-library' ) . '</label></th><td><select id="ap-tl-type" name="post_type">';
	echo '<option value="">' . esc_html__( 'Same type as the reference', 'ap-template-library' ) . '</option>';
	foreach ( ap_tl_source_post_types() as $type ) {
		$object = get_post_type_object( $type );
		echo '<option value="' . esc_attr( $type ) . '">' . esc_html( $object ? $object->labels->singular_name : $type ) . '</option>';
	}
	echo '</select></td></tr>';

	if ( taxonomy_exists( 'resource-types' ) ) {
		$types = get_terms(
			array(
				'taxonomy'   => 'resource-types',
				'hide_empty' => false,
			)
		);
		echo '<tr><th scope="row"><label for="ap-tl-rtype">' . esc_html__( 'Resource type', 'ap-template-library' ) . '</label></th><td><select id="ap-tl-rtype" name="resource_type">';
		echo '<option value="">' . esc_html__( 'Same as the reference', 'ap-template-library' ) . '</option>';
		foreach ( is_wp_error( $types ) ? array() : $types as $term ) {
			echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Only used when the template creates resources.', 'ap-template-library' ) . '</p></td></tr>';
	}

	echo '</tbody></table>';
	submit_button( __( 'Add template', 'ap-template-library' ) );
	echo '</form>';
}

/**
 * Finds a published reference from what an admin typed: an ID, a URL, or a path.
 *
 * @param string $raw Input.
 * @return WP_Post|null
 */
function ap_tl_reference_from_input( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return null;
	}
	$id = 0;
	if ( preg_match( '/^\d+$/', $raw ) ) {
		$id = (int) $raw;
	} else {
		$url = preg_match( '#^https?://#i', $raw ) ? $raw : home_url( '/' . ltrim( $raw, '/' ) );
		$id  = url_to_postid( $url );
		if ( ! $id ) {
			$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
			if ( '' !== $home && 0 === strpos( $path, $home ) ) {
				$path = trim( substr( $path, strlen( $home ) ), '/' );
			}
			$page = '' !== $path ? get_page_by_path( $path, OBJECT, ap_tl_source_post_types() ) : null;
			if ( ! $page && '' !== $path ) {
				// Resources live at /resource-center/<type>/<slug>/: try the last segment as a slug.
				$parts = explode( '/', $path );
				$found = get_posts(
					array(
						'name'             => sanitize_title( end( $parts ) ),
						'post_type'        => ap_tl_source_post_types(),
						'post_status'      => 'publish',
						'numberposts'      => 1,
						'suppress_filters' => true,
					)
				);
				$page  = $found ? $found[0] : null;
			}
			$id = $page ? (int) $page->ID : 0;
		}
	}
	$post = $id ? get_post( $id ) : null;
	if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, ap_tl_source_post_types(), true ) ) {
		return null;
	}
	return $post;
}

/** Shared guard for the settings handlers. */
function ap_tl_settings_guard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage the Template Library.', 'ap-template-library' ), 403 );
	}
	check_admin_referer( 'ap_tl_settings' );
}

/**
 * Redirect back to the settings screen with a notice.
 *
 * @param string $notice Notice key.
 * @param string $anchor Optional row anchor.
 */
function ap_tl_settings_redirect( $notice, $anchor = '' ) {
	wp_safe_redirect( ap_tl_settings_url( array( 'ap_tl_notice' => $notice ) ) . ( '' !== $anchor ? '#' . $anchor : '' ) );
	exit;
}

/** admin-post: add a template. */
function ap_tl_handle_add() {
	ap_tl_settings_guard();
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in ap_tl_settings_guard().
	$post  = ap_tl_reference_from_input( isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '' );
	$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
	$group = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : 'other';
	$desc  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
	$type  = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$rtype = isset( $_POST['resource_type'] ) ? sanitize_title( wp_unslash( $_POST['resource_type'] ) ) : '';
	// phpcs:enable
	if ( ! $post ) {
		ap_tl_settings_redirect( 'bad_ref', 'ap-tl-add' );
	}
	if ( '' === $label ) {
		ap_tl_settings_redirect( 'bad_label', 'ap-tl-add' );
	}
	if ( ! isset( ap_tl_groups()[ $group ] ) ) {
		$group = 'other';
	}
	if ( '' !== $type && ! in_array( $type, ap_tl_source_post_types(), true ) ) {
		$type = '';
	}
	if ( '' !== $rtype && ( ! taxonomy_exists( 'resource-types' ) || ! term_exists( $rtype, 'resource-types' ) ) ) {
		$rtype = '';
	}

	$settings = ap_tl_settings();
	$existing = array_merge( array_keys( ap_tl_default_templates() ), array_keys( ap_tl_all_templates() ) );
	$base     = 'custom-' . ( sanitize_key( sanitize_title( $label ) ) ? sanitize_key( sanitize_title( $label ) ) : (string) $post->ID );
	$key      = substr( $base, 0, 60 );
	$n        = 2;
	while ( in_array( $key, $existing, true ) ) {
		$key = substr( $base, 0, 56 ) . '-' . $n;
		++$n;
	}
	$settings['custom'][] = array(
		'key'           => $key,
		'label'         => $label,
		'group'         => $group,
		'description'   => $desc,
		'source_id'     => (int) $post->ID,
		'post_type'     => $type,
		'resource_type' => $rtype,
	);
	ap_tl_save_settings( $settings );
	ap_tl_settings_redirect( 'added', 'ap-tl-row-' . $key );
}

/** admin-post: remove an admin-added template. */
function ap_tl_handle_remove() {
	ap_tl_settings_guard();
	$key      = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$settings = ap_tl_settings();
	$before   = count( $settings['custom'] );
	$settings['custom'] = array_values(
		array_filter(
			$settings['custom'],
			static function ( $custom ) use ( $key ) {
				return ! is_array( $custom ) || ( $custom['key'] ?? '' ) !== $key;
			}
		)
	);
	if ( count( $settings['custom'] ) === $before ) {
		ap_tl_settings_redirect( 'bad_key' );
	}
	unset( $settings['thumbs'][ $key ] );
	ap_tl_save_settings( $settings );
	ap_tl_settings_redirect( 'removed' );
}

/** admin-post: hide or show a default template. */
function ap_tl_handle_toggle() {
	ap_tl_settings_guard();
	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! isset( ap_tl_default_templates()[ $key ] ) ) {
		ap_tl_settings_redirect( 'bad_key' );
	}
	$settings = ap_tl_settings();
	if ( in_array( $key, $settings['hidden'], true ) ) {
		$settings['hidden'] = array_values( array_diff( $settings['hidden'], array( $key ) ) );
		$notice             = 'shown';
	} else {
		$settings['hidden'][] = $key;
		$notice               = 'hidden';
	}
	ap_tl_save_settings( $settings );
	ap_tl_settings_redirect( $notice, 'ap-tl-row-' . $key );
}

/** admin-post: set or remove a card thumbnail. */
function ap_tl_handle_thumb() {
	ap_tl_settings_guard();
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in ap_tl_settings_guard().
	$key    = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$id     = isset( $_POST['thumb_id'] ) ? absint( $_POST['thumb_id'] ) : 0;
	$remove = ! empty( $_POST['remove'] );
	// phpcs:enable
	if ( ! isset( ap_tl_all_templates()[ $key ] ) ) {
		ap_tl_settings_redirect( 'bad_key' );
	}
	$settings = ap_tl_settings();
	if ( $remove || ! $id ) {
		unset( $settings['thumbs'][ $key ] );
		ap_tl_save_settings( $settings );
		ap_tl_settings_redirect( 'thumb_off', 'ap-tl-row-' . $key );
	}
	if ( ! wp_attachment_is_image( $id ) ) {
		ap_tl_settings_redirect( 'bad_thumb', 'ap-tl-row-' . $key );
	}
	$settings['thumbs'][ $key ] = $id;
	ap_tl_save_settings( $settings );
	ap_tl_settings_redirect( 'thumb', 'ap-tl-row-' . $key );
}
