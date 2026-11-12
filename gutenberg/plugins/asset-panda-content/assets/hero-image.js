/**
 * Asset Panda Content — the header image as a picture in the editor (1.3.25).
 *
 * Every article opens with WordPress's Featured Image block right under the
 * title, where the page shows the header image: click it → Replace, or Upload
 * when there is none. It is added when the article has none, without marking
 * the post as changed (the page itself draws the image in its header; the
 * block prints nothing there).
 */
( function ( wp ) {
	if ( ! wp || ! wp.data || ! wp.blocks ) {
		return;
	}
	var data = wp.data;
	var CFG = window.AP_HERO_IMAGE || {};
	var CLASS = CFG.className || 'ap-hero-image';
	var done = {};

	function ready( ed ) {
		if ( typeof ed.__unstableIsEditorReady === 'function' ) {
			return ed.__unstableIsEditorReady();
		}
		return true;
	}

	function ensure() {
		var ed = data.select( 'core/editor' );
		var be = data.select( 'core/block-editor' );
		if ( ! ed || ! be || ! ed.getCurrentPostId ) {
			return;
		}
		var id = ed.getCurrentPostId();
		if ( ! id || done[ id ] || ! ready( ed ) ) {
			return;
		}
		var blocks = be.getBlocks();
		if ( ! blocks.length && ! ( ed.isCleanNewPost && ed.isCleanNewPost() ) ) {
			return; // The post's blocks are not in yet.
		}
		done[ id ] = true;
		if ( ! wp.blocks.getBlockType( 'core/post-featured-image' ) ) {
			return;
		}
		for ( var i = 0; i < blocks.length; i++ ) {
			if ( blocks[ i ].name === 'core/post-featured-image' ) {
				return;
			}
		}
		var act = data.dispatch( 'core/block-editor' );
		if ( typeof act.__unstableMarkNextChangeAsNotPersistent === 'function' ) {
			act.__unstableMarkNextChangeAsNotPersistent();
		}
		act.insertBlock(
			wp.blocks.createBlock( 'core/post-featured-image', { aspectRatio: '16/10', scale: 'cover', className: CLASS } ),
			0,
			undefined,
			false
		);
	}

	data.subscribe( ensure );
}( window.wp ) );
