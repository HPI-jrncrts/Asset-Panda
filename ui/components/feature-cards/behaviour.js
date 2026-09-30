/*
 * feature-cards — on phones the checklist becomes an accordion (Figma Home
 * Mobile 4333:40892): one item open at a time, the first open to start.
 *
 * Progressive enhancement. The markup is a plain list with every answer
 * visible, which is also what desktop shows. Below 768px this wraps each item
 * title in a disclosure button; from 768px up it restores the plain headings,
 * so a resize or rotation never leaves the desktop list collapsed.
 * Delegated and idempotent: safe with any number of instances.
 */
( function () {
	'use strict';

	var mq = window.matchMedia ? window.matchMedia( '(max-width: 767.98px)' ) : null;

	function open( root, item ) {
		var items = root.querySelectorAll( '.ap-fcards__item' );
		for ( var i = 0; i < items.length; i++ ) {
			var on = items[ i ] === item;
			items[ i ].classList.toggle( 'is-open', on );
			var btn = items[ i ].querySelector( '.ap-fcards__toggle' );
			if ( btn ) {
				btn.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
			}
		}
	}

	function enhance( root ) {
		if ( root.hasAttribute( 'data-ap-fcards-accordion' ) ) {
			return;
		}
		root.setAttribute( 'data-ap-fcards-accordion', '' );

		var items = root.querySelectorAll( '.ap-fcards__item' );
		for ( var i = 0; i < items.length; i++ ) {
			var title = items[ i ].querySelector( '.ap-fcards__item-title' );
			var body = items[ i ].querySelector( '.ap-fcards__item-body' );
			if ( ! title || ! body ) {
				continue;
			}
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'ap-fcards__toggle';
			btn.setAttribute( 'aria-controls', body.id );
			while ( title.firstChild ) {
				btn.appendChild( title.firstChild );
			}
			var chevron = document.createElement( 'span' );
			chevron.className = 'ap-fcards__chevron';
			chevron.setAttribute( 'aria-hidden', 'true' );
			btn.appendChild( chevron );
			title.appendChild( btn );
		}

		open( root, items[ 0 ] );
	}

	function restore( root ) {
		if ( ! root.hasAttribute( 'data-ap-fcards-accordion' ) ) {
			return;
		}
		root.removeAttribute( 'data-ap-fcards-accordion' );

		var btns = root.querySelectorAll( '.ap-fcards__toggle' );
		for ( var i = 0; i < btns.length; i++ ) {
			var title = btns[ i ].parentNode;
			var chevron = btns[ i ].querySelector( '.ap-fcards__chevron' );
			if ( chevron ) {
				btns[ i ].removeChild( chevron );
			}
			while ( btns[ i ].firstChild ) {
				title.insertBefore( btns[ i ].firstChild, btns[ i ] );
			}
			title.removeChild( btns[ i ] );
		}

		var items = root.querySelectorAll( '.ap-fcards__item' );
		for ( var j = 0; j < items.length; j++ ) {
			items[ j ].classList.remove( 'is-open' );
		}
	}

	function sync() {
		var roots = document.querySelectorAll( '[data-ap-fcards]' );
		for ( var i = 0; i < roots.length; i++ ) {
			if ( mq && mq.matches ) {
				enhance( roots[ i ] );
			} else {
				restore( roots[ i ] );
			}
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target && e.target.closest ? e.target.closest( '.ap-fcards__toggle' ) : null;
		if ( ! btn ) {
			return;
		}
		var item = btn.closest( '.ap-fcards__item' );
		var root = btn.closest( '[data-ap-fcards]' );
		if ( item && root ) {
			open( root, item.classList.contains( 'is-open' ) ? null : item );
		}
	} );

	if ( mq ) {
		if ( mq.addEventListener ) {
			mq.addEventListener( 'change', sync );
		} else if ( mq.addListener ) {
			mq.addListener( sync );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', sync );
	} else {
		sync();
	}
} )();
