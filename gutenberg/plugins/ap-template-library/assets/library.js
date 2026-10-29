/**
 * AP Template Library — library screen: group tabs and search over the template cards.
 * Without JS every card stays visible.
 */
( function () {
	'use strict';

	var lib = document.querySelector( '.ap-tl-library' );
	if ( ! lib ) {
		return;
	}
	var tabs = Array.prototype.slice.call( lib.querySelectorAll( '.ap-tl-tab' ) );
	var cards = Array.prototype.slice.call( lib.querySelectorAll( '.ap-tl-card' ) );
	var search = lib.querySelector( '.ap-tl-search' );
	var empty = lib.querySelector( '.ap-tl-empty' );
	var count = lib.querySelector( '.ap-tl-visible-count' );
	var current = 'all';

	function apply() {
		var q = search ? search.value.trim().toLowerCase() : '';
		var shown = 0;
		cards.forEach( function ( card ) {
			var inGroup = current === 'all' || card.getAttribute( 'data-group' ) === current;
			var matches = ! q || ( card.getAttribute( 'data-search' ) || '' ).indexOf( q ) !== -1;
			card.hidden = ! ( inGroup && matches );
			if ( ! card.hidden ) {
				shown += 1;
			}
		} );
		if ( empty ) {
			empty.hidden = shown !== 0;
		}
		if ( count ) {
			count.textContent = String( shown );
		}
	}

	function select( group, focus ) {
		current = group;
		tabs.forEach( function ( tab ) {
			var on = tab.getAttribute( 'data-group' ) === group;
			tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			tab.tabIndex = on ? 0 : -1;
			tab.classList.toggle( 'is-active', on );
			if ( on && focus ) {
				tab.focus();
			}
		} );
		try {
			window.sessionStorage.setItem( 'ap-tl-group', group );
		} catch ( e ) {}
		apply();
	}

	tabs.forEach( function ( tab, i ) {
		tab.addEventListener( 'click', function () {
			select( tab.getAttribute( 'data-group' ) );
		} );
		tab.addEventListener( 'keydown', function ( e ) {
			var next = null;
			if ( e.key === 'ArrowRight' ) {
				next = tabs[ ( i + 1 ) % tabs.length ];
			} else if ( e.key === 'ArrowLeft' ) {
				next = tabs[ ( i - 1 + tabs.length ) % tabs.length ];
			} else if ( e.key === 'Home' ) {
				next = tabs[ 0 ];
			} else if ( e.key === 'End' ) {
				next = tabs[ tabs.length - 1 ];
			}
			if ( next ) {
				e.preventDefault();
				select( next.getAttribute( 'data-group' ), true );
			}
		} );
	} );

	if ( search ) {
		search.addEventListener( 'input', apply );
	}

	var remembered = 'all';
	try {
		remembered = window.sessionStorage.getItem( 'ap-tl-group' ) || 'all';
	} catch ( e ) {}
	var known = tabs.some( function ( t ) {
		return t.getAttribute( 'data-group' ) === remembered;
	} );
	select( known ? remembered : 'all' );
	lib.classList.add( 'is-ready' );
} )();
