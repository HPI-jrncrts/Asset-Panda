/**
 * AP Duplicate Check — "Send my changes to the live page".
 *
 * A draft linked to a live page (a Duplicate copy, or a draft the writer
 * linked from the duplicate warning) shows a "Live page" panel in the editor
 * sidebar. "Choose what to send…" opens a window listing the draft's sections
 * against the live page's: replace, add or remove each one, optionally the
 * title, SEO fields and featured image. Preview shows the live page with those
 * changes (an autosave — nothing visible changes); Push updates it; Undo puts
 * it back.
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.components || ! wp.data || ! wp.apiFetch ) {
		return;
	}
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var C = wp.components;
	var data = wp.data;
	var apiFetch = wp.apiFetch;
	var CFG = window.AP_DUPCHECK || {};
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	// The notice (editor.js) opens the window through this, and asks which live
	// page the draft is linked to: once linked, it offers "Send my changes…" for
	// that page only, never for another page that merely looks similar.
	var listeners = [];
	var linkedId = CFG.target && CFG.target.id ? CFG.target.id : 0;
	var linkWatchers = [];
	function setLinked( id ) {
		id = id || 0;
		if ( id !== linkedId ) {
			linkedId = id;
			linkWatchers.forEach( function ( fn ) {
				fn( id );
			} );
		}
	}
	window.apDupcheckPush = {
		open: function ( match ) {
			listeners.forEach( function ( fn ) {
				fn( match );
			} );
		},
		linkedId: function () {
			return linkedId;
		},
		onLinkChange: function ( fn ) {
			linkWatchers.push( fn );
		},
	};

	function editor() {
		return data.select( 'core/editor' );
	}

	function draftState() {
		var ed = editor();
		var seo = null;
		try {
			var y = data.select( 'yoast-seo/editor' );
			if ( y && y.getSnippetEditorTitle && y.getSnippetEditorDescription ) {
				// Yoast reports its site-wide default ("%%title%% %%page%% %%sep%% %%sitename%%")
				// when the writer set nothing: that is not a custom value to carry over.
				var title = String( y.getSnippetEditorTitle() || '' ).trim();
				if ( /^(%%[a-z_]+%%\s*)+$/.test( title ) ) {
					title = '';
				}
				seo = {
					_yoast_wpseo_title: title,
					_yoast_wpseo_metadesc: String( y.getSnippetEditorDescription() || '' ).trim(),
					_yoast_wpseo_focuskw: String( ( y.getFocusKeyphrase && y.getFocusKeyphrase() ) || '' ).trim(),
				};
				if ( ! seo._yoast_wpseo_title && ! seo._yoast_wpseo_metadesc && ! seo._yoast_wpseo_focuskw ) {
					seo = null; // Nothing custom in this draft.
				}
			}
		} catch ( e ) {}
		return {
			id: ed.getCurrentPostId(),
			title: ed.getEditedPostAttribute( 'title' ) || '',
			content: ed.getEditedPostContent ? ed.getEditedPostContent() : '',
			image: ed.getEditedPostAttribute( 'featured_media' ) || 0,
			seo: seo,
		};
	}

	function errorText( err ) {
		return ( err && err.message ) || 'Something went wrong. Please try again.';
	}

	function rowLabel( r ) {
		if ( r.type === 'order' ) {
			return r.label;
		}
		if ( r.type === 'replace' ) {
			return 'Replace “' + r.label + '”' + ( r.new_label ? ' with “' + r.new_label + '”' : '' );
		}
		if ( r.type === 'add' ) {
			return 'Add new section “' + r.label + '”';
		}
		return 'Remove “' + r.label + '” from the live page';
	}

	function PushModal( props ) {
		var target = props.target;
		var s = useState( null );
		var plan = s[ 0 ];
		var setPlan = s[ 1 ];
		var sel = useState( {} );
		var e = useState( '' );
		var busy = useState( '' );
		var done = useState( null );
		var previewed = useState( false );
		var opts = useState( { title: false, seo: false, image: false, trash: false } );
		var draft = useState( draftState )[ 0 ]; // Read once, when the window opens.

		useEffect( function () {
			apiFetch( {
				path: '/ap-dupcheck/v1/plan',
				method: 'POST',
				data: { draft_id: draft.id, live_id: target.id, title: draft.title, content: draft.content },
			} ).then( function ( res ) {
				var initial = {};
				// A rewrite (most of the live page's sections are not in the draft):
				// start with "replace the whole page" ticked.
				var count = function ( type ) {
					return res.rows.filter( function ( r ) {
						return r.type === type;
					} ).length;
				};
				var removes = count( 'remove' );
				// Most of the page is new or gone (a rewrite from a template), not a few edits.
				res.whole = removes > 0 && count( 'add' ) + removes > count( 'replace' ) + res.same.length;
				res.rows.forEach( function ( r ) {
					initial[ r.id ] = res.whole ? true : !! r.checked;
				} );
				sel[ 1 ]( initial );
				setPlan( res );
			} ).catch( function ( err ) {
				e[ 1 ]( errorText( err ) );
			} );
		}, [] );

		function selected() {
			return Object.keys( sel[ 0 ] ).filter( function ( k ) {
				return sel[ 0 ][ k ];
			} );
		}

		function request( path, extra ) {
			return apiFetch( {
				path: path,
				method: 'POST',
				data: Object.assign( {
					draft_id: draft.id,
					live_id: target.id,
					title: draft.title,
					content: draft.content,
					selected: selected(),
					with_title: opts[ 0 ].title,
				}, extra || {} ),
			} );
		}

		function close() {
			if ( previewed[ 0 ] && ! done[ 0 ] ) {
				request( '/ap-dupcheck/v1/preview', { discard: true } ).catch( function () {} );
			}
			props.onClose( done[ 0 ] );
		}

		function preview() {
			var win = window.open( 'about:blank', '_blank' );
			busy[ 1 ]( 'preview' );
			e[ 1 ]( '' );
			request( '/ap-dupcheck/v1/preview' ).then( function ( res ) {
				previewed[ 1 ]( true );
				busy[ 1 ]( '' );
				if ( win ) {
					win.location = res.url;
				} else {
					window.open( res.url, '_blank' );
				}
			} ).catch( function ( err ) {
				busy[ 1 ]( '' );
				if ( win ) {
					win.close();
				}
				e[ 1 ]( errorText( err ) );
			} );
		}

		function push() {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( 'Update the live page “' + target.name + '” now? Visitors see the change straight away. You can undo it right after.' ) ) {
				return;
			}
			busy[ 1 ]( 'push' );
			e[ 1 ]( '' );
			var o = opts[ 0 ];
			request( '/ap-dupcheck/v1/push', {
				live_modified: plan.live_modified,
				seo: o.seo && draft.seo ? draft.seo : null,
				image_id: o.image ? draft.image : 0,
				trash_draft: o.trash,
			} ).then( function ( res ) {
				busy[ 1 ]( '' );
				done[ 1 ]( { target: res.target, trashed: o.trash } );
			} ).catch( function ( err ) {
				busy[ 1 ]( '' );
				e[ 1 ]( errorText( err ) );
			} );
		}

		function undo() {
			busy[ 1 ]( 'undo' );
			e[ 1 ]( '' );
			request( '/ap-dupcheck/v1/undo' ).then( function ( res ) {
				busy[ 1 ]( '' );
				done[ 1 ]( { target: res.target, undone: true } );
			} ).catch( function ( err ) {
				busy[ 1 ]( '' );
				e[ 1 ]( errorText( err ) );
			} );
		}

		function setOpt( key, value ) {
			var next = Object.assign( {}, opts[ 0 ] );
			next[ key ] = value;
			opts[ 1 ]( next );
		}

		var liveLink = el( 'a', { href: target.url, target: '_blank', rel: 'noopener noreferrer' }, '“' + target.name + '”' );
		var body;

		if ( done[ 0 ] ) {
			body = el( 'div', { className: 'ap-dupcheck-push__done' },
				el( C.Notice, { status: 'success', isDismissible: false },
					done[ 0 ].undone ? 'The live page is back the way it was.' : 'The live page is updated.' ),
				el( 'p', null, 'Live page: ', liveLink ),
				done[ 0 ].trashed && ! done[ 0 ].undone ? el( 'p', null, 'This draft was moved to the Trash.' ) : null,
				el( 'div', { className: 'ap-dupcheck-push__buttons' },
					el( C.Button, { variant: 'primary', href: target.url, target: '_blank' }, 'View the live page' ),
					! done[ 0 ].undone && done[ 0 ].target && done[ 0 ].target.undo
						? el( C.Button, { variant: 'secondary', isBusy: busy[ 0 ] === 'undo', disabled: !! busy[ 0 ], onClick: undo }, 'Undo' )
						: null,
					target.edit_url ? el( C.Button, { variant: 'tertiary', href: target.edit_url }, 'Open the live page in the editor' ) : null
				)
			);
		} else if ( ! plan ) {
			body = e[ 0 ] ? null : el( 'p', null, el( C.Spinner ), ' Comparing this draft with the live page…' );
		} else {
			var draftTitleDiffers = draft.title && draft.title !== plan.live_title;
			var seoDiffers = draft.seo && Object.keys( draft.seo ).some( function ( k ) {
				return ( draft.seo[ k ] || '' ) !== ( plan.live_seo[ k ] || '' );
			} );
			var imageDiffers = draft.image && draft.image !== plan.live_image;
			var nothing = ! plan.rows.length && ! draftTitleDiffers && ! seoDiffers && ! imageDiffers;
			body = el( 'div', null,
				el( 'p', null, 'Choose what to send to the live page ', liveLink, ' (', target.kind, ' · last updated ', target.updated, '). Nothing changes on the live page until you press Push.' ),
				nothing ? el( C.Notice, { status: 'info', isDismissible: false }, 'This draft already matches the live page.' ) : null,
				plan.rows.length ? el( 'h3', { className: 'ap-dupcheck-push__h' }, 'Sections' ) : null,
				plan.rows.length > 1 ? el( 'div', { className: 'ap-dupcheck-push__row ap-dupcheck-push__row--whole' },
					el( C.CheckboxControl, {
						label: 'Replace the whole live page with this draft',
						help: 'Ticks every row below: the live page becomes exactly this draft (same address). Untick to choose section by section.',
						checked: plan.rows.every( function ( r ) {
							return !! sel[ 0 ][ r.id ];
						} ),
						onChange: function ( v ) {
							var next = {};
							plan.rows.forEach( function ( r ) {
								next[ r.id ] = v ? true : !! r.checked;
							} );
							sel[ 1 ]( next );
						},
					} )
				) : null,
				el( 'div', { className: 'ap-dupcheck-push__rows' },
					plan.rows.map( function ( r ) {
						return el( 'div', { key: r.id, className: 'ap-dupcheck-push__row ap-dupcheck-push__row--' + r.type },
							el( C.CheckboxControl, {
								label: rowLabel( r ),
								help: r.excerpt,
								checked: !! sel[ 0 ][ r.id ],
								onChange: function ( v ) {
									var next = Object.assign( {}, sel[ 0 ] );
									next[ r.id ] = v;
									sel[ 1 ]( next );
								},
							} )
						);
					} )
				),
				plan.same.length ? el( 'p', { className: 'ap-dupcheck-push__same' }, plan.same.length + ' section' + ( plan.same.length === 1 ? '' : 's' ) + ' already match the live page: ' + plan.same.slice( 0, 6 ).join( ', ' ) + ( plan.same.length > 6 ? '…' : '' ) + '.' ) : null,
				draftTitleDiffers || seoDiffers || imageDiffers ? el( 'h3', { className: 'ap-dupcheck-push__h' }, 'Page details' ) : null,
				draftTitleDiffers ? el( C.CheckboxControl, { label: 'Use this draft’s title: “' + draft.title + '”', help: 'Live title: “' + plan.live_title + '”. The address stays the same.', checked: opts[ 0 ].title, onChange: function ( v ) {
					setOpt( 'title', v );
				} } ) : null,
				seoDiffers ? el( C.CheckboxControl, { label: 'Use this draft’s SEO title, meta description and focus keyphrase (Yoast)', checked: opts[ 0 ].seo, onChange: function ( v ) {
					setOpt( 'seo', v );
				} } ) : null,
				imageDiffers ? el( C.CheckboxControl, { label: 'Use this draft’s featured image', checked: opts[ 0 ].image, onChange: function ( v ) {
					setOpt( 'image', v );
				} } ) : null,
				el( 'h3', { className: 'ap-dupcheck-push__h' }, 'After pushing' ),
				el( C.CheckboxControl, { label: 'Move this draft to the Trash', help: 'Leave it unticked to keep editing this draft and push again later.', checked: opts[ 0 ].trash, onChange: function ( v ) {
					setOpt( 'trash', v );
				} } ),
				el( 'div', { className: 'ap-dupcheck-push__buttons' },
					el( C.Button, { variant: 'secondary', isBusy: busy[ 0 ] === 'preview', disabled: !! busy[ 0 ] || nothing, onClick: preview }, 'Preview the live page with these changes' ),
					el( C.Button, { variant: 'primary', isBusy: busy[ 0 ] === 'push', disabled: !! busy[ 0 ] || nothing, onClick: push }, 'Push to live page' )
				)
			);
		}

		return el( C.Modal, { title: 'Send changes to the live page', onRequestClose: close, className: 'ap-dupcheck-push', size: 'medium' },
			e[ 0 ] ? el( C.Notice, { status: 'error', isDismissible: false }, e[ 0 ] ) : null,
			body
		);
	}

	function LivePagePanel() {
		var t = useState( CFG.target || null );
		var target = t[ 0 ];
		var setTarget = t[ 1 ];
		var open = useState( false );
		var err = useState( '' );
		var status = data.useSelect ? data.useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'status' );
		}, [] ) : '';

		useEffect( function () {
			setLinked( target ? target.id : 0 );
		}, [ target ? target.id : 0 ] );

		useEffect( function () {
			function onOpen( match ) {
				err[ 1 ]( '' );
				apiFetch( {
					path: '/ap-dupcheck/v1/link',
					method: 'POST',
					data: { draft_id: editor().getCurrentPostId(), live_id: match.id },
				} ).then( function ( res ) {
					setTarget( res.target );
					open[ 1 ]( true );
				} ).catch( function ( e ) {
					err[ 1 ]( errorText( e ) );
				} );
			}
			listeners.push( onOpen );
			return function () {
				listeners = listeners.filter( function ( fn ) {
					return fn !== onOpen;
				} );
			};
		}, [] );

		if ( status === 'publish' || status === 'private' || ! Panel ) {
			return null;
		}

		function unlink() {
			apiFetch( { path: '/ap-dupcheck/v1/unlink', method: 'POST', data: { draft_id: editor().getCurrentPostId() } } ).then( function () {
				setTarget( null );
			} );
		}

		var panel = target ? el( Panel, { name: 'ap-dupcheck-live-page', title: 'Live page', className: 'ap-dupcheck-panel' },
			el( 'p', null, 'This draft updates ', el( 'a', { href: target.url, target: '_blank', rel: 'noopener noreferrer' }, target.name ), ' (', target.kind, ').' ),
			target.can_push ? el( C.Button, { variant: 'primary', onClick: function () {
				open[ 1 ]( true );
			} }, 'Choose what to send…' ) : el( 'p', null, 'You cannot edit that page.' ),
			target.undo ? el( 'p', { className: 'ap-dupcheck-panel__undo' }, 'Last push: ' + target.undo.time + '. You can undo it from the window.' ) : null,
			el( C.Button, { variant: 'link', onClick: unlink }, 'This is a separate page (unlink)' ),
			err[ 0 ] ? el( C.Notice, { status: 'error', isDismissible: false }, err[ 0 ] ) : null
		) : ( err[ 0 ] ? el( Panel, { name: 'ap-dupcheck-live-page', title: 'Live page' }, el( C.Notice, { status: 'error', isDismissible: false }, err[ 0 ] ) ) : null );

		return el( wp.element.Fragment, null,
			panel,
			open[ 0 ] && target ? el( PushModal, {
				target: target,
				onClose: function ( result ) {
					open[ 1 ]( false );
					if ( result && result.target ) {
						setTarget( result.target );
					}
				},
			} ) : null
		);
	}

	wp.plugins.registerPlugin( 'ap-duplicate-check-push', { render: LivePagePanel } );
}( window.wp ) );
