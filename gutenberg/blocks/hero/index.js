/**
 * AP Hero block — editor registration (adapter, buildless).
 *
 * The block is registered server-side from block.json (name, attributes,
 * render). This script attaches the editor experience: an inspector panel for
 * the attributes and a live preview via ServerSideRender — so the editor shows
 * the REAL kit output (ui/components/hero/render.php), never a hand-built copy.
 *
 * No markup lives here. save() returns null because the block is dynamic; the
 * front end is produced by ap_render_component() in render.php.
 *
 * Buildless on purpose: uses the global wp.* packages (enqueued with explicit
 * deps in inc/asset-panda-ui.php), so there is no wp-scripts/JSX build step.
 */
( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType( 'assetpanda/hero', {
		edit: function ( props ) {
			var a = props.attributes;

			function set( key ) {
				return function ( value ) {
					var patch = {};
					patch[ key ] = value;
					props.setAttributes( patch );
				};
			}

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Hero', 'assetpanda' ), initialOpen: true },
						el( TextControl, { label: __( 'Eyebrow', 'assetpanda' ), value: a.eyebrow, onChange: set( 'eyebrow' ) } ),
						el( TextControl, { label: __( 'Title', 'assetpanda' ), value: a.title, onChange: set( 'title' ) } ),
						el( TextareaControl, { label: __( 'Subtitle', 'assetpanda' ), value: a.subtitle, onChange: set( 'subtitle' ) } ),
						el( TextControl, { label: __( 'CTA label', 'assetpanda' ), value: a.ctaLabel, onChange: set( 'ctaLabel' ) } ),
						el( TextControl, { label: __( 'CTA URL', 'assetpanda' ), value: a.ctaUrl, onChange: set( 'ctaUrl' ) } ),
						el( SelectControl, {
							label: __( 'Alignment', 'assetpanda' ),
							value: a.alignment,
							options: [
								{ label: __( 'Center', 'assetpanda' ), value: 'center' },
								{ label: __( 'Left', 'assetpanda' ), value: 'left' }
							],
							onChange: set( 'alignment' )
						} ),
						el( SelectControl, {
							label: __( 'Theme', 'assetpanda' ),
							value: a.theme,
							options: [
								{ label: __( 'Light', 'assetpanda' ), value: 'light' },
								{ label: __( 'Dark', 'assetpanda' ), value: 'dark' },
								{ label: __( 'Brand', 'assetpanda' ), value: 'brand' }
							],
							onChange: set( 'theme' )
						} )
					)
				),
				el(
					'div',
					useBlockProps(),
					el( ServerSideRender, { block: 'assetpanda/hero', attributes: a } )
				)
			);
		},

		save: function () {
			return null;
		}
	} );
} )( window.wp );
