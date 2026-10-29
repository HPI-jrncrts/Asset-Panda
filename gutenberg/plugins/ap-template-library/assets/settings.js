/**
 * AP Template Library — settings screen: choose a card thumbnail from the media library.
 * The choice is saved by submitting the row's own form (admin-post, nonce-checked).
 */
( function () {
	'use strict';

	var strings = window.AP_TL_SETTINGS || {};

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.ap-tl-pick-thumb' ) : null;
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		if ( ! window.wp || ! window.wp.media ) {
			window.alert( strings.noMedia || 'The media library is not available.' );
			return;
		}
		var form = btn.closest( 'form' );
		var frame = window.wp.media( {
			title: strings.title || 'Choose a thumbnail',
			button: { text: strings.button || 'Use as thumbnail' },
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			form.querySelector( 'input[name="thumb_id"]' ).value = String( att.id || 0 );
			form.submit();
		} );
		frame.open();
	} );
} )();
