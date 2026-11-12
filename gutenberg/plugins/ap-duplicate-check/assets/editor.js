/**
 * AP Duplicate Check — editor side.
 *
 * While a page is not live yet, sends its title, content, address and Yoast
 * keyphrase to /ap-dupcheck/v1/check (a couple of seconds after the writer
 * stops typing) and shows the answer as a notice at the top of the editor,
 * linking the live page.
 */
( function ( wp ) {
	if ( ! wp || ! wp.data || ! wp.apiFetch ) {
		return;
	}
	var data = wp.data;
	var apiFetch = wp.apiFetch;
	var NOTICE_ID = 'ap-duplicate-check';
	var timer = null;
	var seq = 0;
	var lastKey = '';
	var lastBlocks = null;
	var lastTitle = null;
	var lastStatus = null;
	var shownKey = '';
	var lastShown = null;

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
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

	function countWords( s ) {
		var m = String( s || '' ).match( /[A-Za-z0-9]{2,}/g );
		return m ? m.length : 0;
	}

	function hash( s ) {
		var h = 5381;
		for ( var i = 0; i < s.length; i++ ) {
			h = ( ( h << 5 ) + h + s.charCodeAt( i ) ) | 0;
		}
		return h;
	}

	function editor() {
		return data.select( 'core/editor' );
	}

	function clear() {
		shownKey = '';
		data.dispatch( 'core/notices' ).removeNotice( NOTICE_ID );
	}

	function link( m ) {
		return '<a href="' + esc( m.url ) + '" target="_blank" rel="noopener noreferrer">“' + esc( m.name ) + '”</a>';
	}

	function label( m ) {
		return '(' + esc( m.kind ) + ' · last updated ' + esc( m.updated ) + ')';
	}

	function show( postId, matches ) {
		lastShown = { postId: postId, matches: matches };
		if ( ! matches.length ) {
			clear();
			return;
		}
		var same = matches.filter( function ( m ) {
			return m.verdict === 'same';
		} );
		var top = same[ 0 ] || matches[ 0 ];
		var rest = matches.filter( function ( m ) {
			return m !== top;
		} ).slice( 0, 3 );
		var key = 'ap-dupcheck:' + postId + ':' + matches.map( function ( m ) {
			return m.id;
		} ).join( ',' );
		// The live page this draft is linked to (0 = none). Once linked, the
		// draft is pushed there; another similar page is never offered as a target.
		var linked = window.apDupcheckPush && window.apDupcheckPush.linkedId ? window.apDupcheckPush.linkedId() : 0;
		if ( key + ':' + linked === shownKey ) {
			return;
		}
		if ( store( key ) === 'dismissed' ) {
			clear();
			return;
		}
		var html;
		var spoken;
		if ( same.length ) {
			html = '<strong>Already live?</strong> This looks like the same topic as ' + link( top ) + ' ' + label( top ) + '. ' +
				'Update that page instead of publishing a second one, or make sure this one covers something different.';
			spoken = 'Already live? This looks like the same topic as ' + top.name + '.';
		} else {
			html = '<strong>A similar page is already live:</strong> ' + link( top ) + ' ' + label( top ) + '. ' +
				'Check it before publishing so the two pages don’t compete.';
			spoken = 'A similar page is already live: ' + top.name + '.';
		}
		if ( rest.length ) {
			html += ' Also close: ' + rest.map( function ( m ) {
				return link( m );
			} ).join( ', ' ) + '.';
		}
		var actions = [
			{
				label: 'Open the live page',
				onClick: function () {
					window.open( top.url, '_blank', 'noopener' );
				},
			},
		];
		if ( top.edit_url ) {
			actions.push( {
				label: 'Edit the live page',
				onClick: function () {
					window.open( top.edit_url, '_blank', 'noopener' );
				},
			} );
			if ( window.apDupcheckPush && ( ! linked || linked === top.id ) ) {
				// push.js: link this draft to the live page and choose what to send to it.
				actions.push( {
					label: 'Send my changes to this live page…',
					onClick: function () {
						window.apDupcheckPush.open( top );
					},
				} );
			}
		}
		shownKey = key + ':' + linked;
		data.dispatch( 'core/notices' ).createNotice( same.length ? 'warning' : 'info', html, {
			id: NOTICE_ID,
			isDismissible: true,
			__unstableHTML: true,
			speak: true,
			spokenMessage: spoken,
			actions: actions,
			onDismiss: function () {
				store( key, 'dismissed' );
				shownKey = '';
			},
		} );
	}

	function run() {
		var ed = editor();
		if ( ! ed || ! ed.getCurrentPostId || ! ed.getCurrentPostId() ) {
			return;
		}
		var status = ed.getEditedPostAttribute( 'status' );
		if ( status === 'publish' || status === 'private' ) {
			clear();
			return;
		}
		var title = ed.getEditedPostAttribute( 'title' ) || '';
		var content = ed.getEditedPostContent ? ed.getEditedPostContent() : '';
		var text = content.replace( /<!--[\s\S]*?-->/g, ' ' ).replace( /<[^>]+>/g, ' ' );
		if ( countWords( title ) < 3 && countWords( text ) < 60 ) {
			clear();
			return;
		}
		var slug = ed.getEditedPostAttribute( 'slug' ) || '';
		var keyphrase = '';
		try {
			var yoast = data.select( 'yoast-seo/editor' );
			if ( yoast && yoast.getFocusKeyphrase ) {
				keyphrase = yoast.getFocusKeyphrase() || '';
			}
		} catch ( e ) {}
		var key = [ title, slug, keyphrase, content.length, hash( content ) ].join( '\u0001' );
		if ( key === lastKey ) {
			return;
		}
		lastKey = key;
		var mine = ++seq;
		var postId = ed.getCurrentPostId();
		apiFetch( {
			path: '/ap-dupcheck/v1/check',
			method: 'POST',
			data: { post_id: postId, post_type: ed.getCurrentPostType ? ed.getCurrentPostType() : '', title: title, content: content, slug: slug, keyphrase: keyphrase },
		} ).then( function ( res ) {
			if ( mine === seq ) {
				show( postId, ( res && res.matches ) || [] );
			}
		} ).catch( function () {
			lastKey = ''; // Try again on the next edit.
		} );
	}

	// Linking or unlinking the draft changes which pages count (the linked page
	// is left out) and which page "Send my changes…" may target: check again.
	if ( window.apDupcheckPush && window.apDupcheckPush.onLinkChange ) {
		window.apDupcheckPush.onLinkChange( function () {
			lastKey = '';
			if ( lastShown ) {
				show( lastShown.postId, lastShown.matches );
			}
			clearTimeout( timer );
			timer = setTimeout( run, 300 );
		} );
	}

	data.subscribe( function () {
		var ed = editor();
		if ( ! ed || ! ed.getCurrentPostId || ! ed.getCurrentPostId() ) {
			return;
		}
		var be = data.select( 'core/block-editor' );
		var blocks = be ? be.getBlocks() : null;
		var title = ed.getEditedPostAttribute( 'title' );
		var status = ed.getEditedPostAttribute( 'status' );
		if ( blocks === lastBlocks && title === lastTitle && status === lastStatus ) {
			return;
		}
		var first = lastStatus === null;
		lastBlocks = blocks;
		lastTitle = title;
		lastStatus = status;
		clearTimeout( timer );
		timer = setTimeout( run, first ? 1500 : 2500 );
	} );
}( window.wp ) );
