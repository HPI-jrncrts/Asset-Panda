/**
 * Asset Panda Content — logo boxes in the editor (1.3.24).
 *
 * In a "Best …" article, each numbered company heading ("3. Freshservice — …")
 * has its logo as an image block right under it. When some company headings
 * have no such box, a notice offers "Add logo boxes": one image block under
 * each, holding the logo the page shows today (or empty, ready for an upload).
 * Writers then change a logo like any picture: click it → Replace → Upload.
 */
( function ( wp ) {
	if ( ! wp || ! wp.data || ! wp.blocks || ! wp.apiFetch ) {
		return;
	}
	var data = wp.data;
	var CFG = window.AP_LOGO_BOXES || {};
	var BOX = CFG.boxClass || 'ap-vendor-logo';
	var NOTICE = 'ap-content-logo-boxes';
	var timer = null;
	var lastBlocks = null;
	var lastTitle = null;
	var shown = '';

	function plain( html ) {
		var el = document.createElement( 'textarea' );
		el.innerHTML = String( html == null ? '' : html ).replace( /<[^>]*>/g, '' );
		return el.value.replace( /\s+/g, ' ' ).trim();
	}

	// Same rule as the design (ap26x_parse_rank): "3. Name — tagline" → "Name".
	function rankName( block ) {
		if ( ! block || block.name !== 'core/heading' || ( block.attributes.level || 2 ) !== 2 ) {
			return '';
		}
		var text = plain( block.attributes.content );
		var m = text.match( /^#\s*(\d{1,2})\s*[:.)–—-]?\s*(.+)$/ ) || text.match( /^(\d{1,2})\s*[:.)–—-]\s*(.+)$/ );
		if ( ! m ) {
			return '';
		}
		var rest = m[ 2 ].trim();
		var parts = rest.split( /\s+[–—-]\s+|:\s+/ );
		var name = parts.length > 1 && parts.slice( 1 ).join( ' ' ).trim() ? parts[ 0 ].trim() : rest;
		return name && name.length <= 60 ? name : '';
	}

	function isListicle( ranks, title ) {
		var titled = /\b(best|top|alternatives?|vs\.?|compared|comparison)\b/i.test( title ) && ! /\bbest practices?\b/i.test( title );
		return ( ranks >= 3 && ( ! title.trim() || titled ) ) || ( ranks >= 2 && titled );
	}

	function store( key, value ) {
		try {
			if ( value === undefined ) {
				return window.sessionStorage.getItem( key );
			}
			window.sessionStorage.setItem( key, value );
		} catch ( e ) {}
		return null;
	}

	function missingBoxes() {
		var blocks = data.select( 'core/block-editor' ).getBlocks();
		var ranks = 0;
		var missing = [];
		blocks.forEach( function ( b, i ) {
			var name = rankName( b );
			if ( ! name ) {
				return;
			}
			ranks++;
			// 1.3.25: Asset Panda gets a box too (its rating and award badges stay under it on the page).
			var next = blocks[ i + 1 ];
			if ( ! next || next.name !== 'core/image' ) {
				missing.push( { clientId: b.clientId, name: name } );
			}
		} );
		return { ranks: ranks, missing: missing };
	}

	function addBoxes() {
		var found = missingBoxes().missing;
		if ( ! found.length ) {
			return;
		}
		var names = found.map( function ( f ) {
			return f.name;
		} );
		wp.apiFetch( { path: '/ap-content/v1/logos', method: 'POST', data: { names: names } } ).catch( function () {
			return { logos: {} };
		} ).then( function ( res ) {
			var logos = ( res && res.logos ) || {};
			var be = data.select( 'core/block-editor' );
			var act = data.dispatch( 'core/block-editor' );
			var filled = 0;
			found.forEach( function ( f ) {
				var index = be.getBlockIndex( f.clientId );
				if ( index < 0 ) {
					return;
				}
				var url = logos[ f.name ] || '';
				var alt = /^asset\s*panda\b/i.test( f.name ) ? 'Asset Panda logo' : f.name + ' logo';
				var attrs = url ? { url: url, alt: alt, className: BOX } : { className: BOX };
				if ( url ) {
					filled++;
				}
				act.insertBlock( wp.blocks.createBlock( 'core/image', attrs ), index + 1, be.getBlockRootClientId( f.clientId ) || undefined, false );
			} );
			data.dispatch( 'core/notices' ).removeNotice( NOTICE );
			data.dispatch( 'core/notices' ).createNotice(
				'success',
				'Added ' + found.length + ' logo box' + ( found.length === 1 ? '' : 'es' ) + ( filled < found.length ? ' (' + ( found.length - filled ) + ' empty: click one and choose Upload)' : '' ) + '. To change a logo, click it and choose Replace.',
				{ type: 'snackbar' }
			);
		} );
	}

	function check() {
		var ed = data.select( 'core/editor' );
		if ( ! ed || ! ed.getCurrentPostId() ) {
			return;
		}
		var res = missingBoxes();
		var title = ed.getEditedPostAttribute( 'title' ) || '';
		var key = 'ap-logo-boxes:' + ed.getCurrentPostId();
		if ( ! res.missing.length || ! isListicle( res.ranks, title ) || store( key ) === 'dismissed' ) {
			if ( shown ) {
				data.dispatch( 'core/notices' ).removeNotice( NOTICE );
				shown = '';
			}
			return;
		}
		var sig = String( res.missing.length );
		if ( sig === shown ) {
			return;
		}
		shown = sig;
		var n = res.missing.length;
		data.dispatch( 'core/notices' ).createNotice(
			'info',
			n + ' compan' + ( n === 1 ? 'y in this list has' : 'ies in this list have' ) + ' no logo box. Add a box under each company heading to see its logo here and change it like any picture.',
			{
				id: NOTICE,
				isDismissible: true,
				actions: [ { label: 'Add logo boxes', onClick: addBoxes } ],
				onDismiss: function () {
					store( key, 'dismissed' );
					shown = '';
				},
			}
		);
	}

	data.subscribe( function () {
		var be = data.select( 'core/block-editor' );
		var ed = data.select( 'core/editor' );
		if ( ! be || ! ed || ! ed.getCurrentPostId ) {
			return;
		}
		var blocks = be.getBlocks();
		var title = ed.getEditedPostAttribute( 'title' );
		if ( blocks === lastBlocks && title === lastTitle ) {
			return;
		}
		var first = lastBlocks === null;
		lastBlocks = blocks;
		lastTitle = title;
		clearTimeout( timer );
		timer = setTimeout( check, first ? 1200 : 2000 );
	} );
}( window.wp ) );
