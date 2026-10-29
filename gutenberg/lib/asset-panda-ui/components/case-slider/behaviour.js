/*
 * case-slider — moves between customer stories.
 *
 * Live's inline slider script, made per-instance and idempotent: builds one dot
 * per slide, wires the arrows, and slides the progress segment along the line.
 * Only classes and the progress transform change; nothing is measured.
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.hasAttribute( 'data-ap-case-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-ap-case-ready', '' );

		var slides = root.querySelectorAll( '.apg-case-slide' );
		var dots = root.querySelector( '.apg-case-dots' );
		var prev = root.querySelector( '.apg-arrow-prev' );
		var next = root.querySelector( '.apg-arrow-next' );
		var progress = root.querySelector( '.apg-case-progress' );
		var cur = 0;

		if ( ! slides.length ) {
			return;
		}

		function go( n ) {
			cur = ( n + slides.length ) % slides.length;
			for ( var k = 0; k < slides.length; k++ ) {
				slides[ k ].classList.toggle( 'is-active', k === cur );
				if ( dots && dots.children[ k ] ) {
					dots.children[ k ].classList.toggle( 'is-active', k === cur );
				}
			}
			if ( progress ) {
				progress.style.width = ( 100 / slides.length ) + '%';
				progress.style.transform = 'translateX(' + ( 100 * cur ) + '%)';
			}
		}

		if ( dots ) {
			for ( var j = 0; j < slides.length; j++ ) {
				( function ( idx ) {
					var d = document.createElement( 'button' );
					d.type = 'button';
					d.className = 'apg-case-dot' + ( 0 === idx ? ' is-active' : '' );
					d.setAttribute( 'aria-label', 'Go to case study ' + ( idx + 1 ) );
					d.addEventListener( 'click', function () {
						go( idx );
					} );
					dots.appendChild( d );
				} )( j );
			}
		}

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				go( cur - 1 );
			} );
		}
		if ( next ) {
			next.addEventListener( 'click', function () {
				go( cur + 1 );
			} );
		}
		go( 0 );
	}

	function initAll() {
		var nodes = document.querySelectorAll( '[data-ap-case-slider]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			init( nodes[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
