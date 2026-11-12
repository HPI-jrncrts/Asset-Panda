<?php
/**
 * Vendor logos (1.3.22; 1.3.24: SVG logos, and every logo shown at a size
 * matched to the built-in ones whatever its shape): the logos drawn under each company's heading in
 * "Best …" articles (the ap26x ranked-list design). The design matches a
 * company to a logo by name; until now that list lived in code, so a company
 * without a logo showed its initials. Resources → Vendor logos lets a writer
 * upload a logo once, type the company's name, and every article that ranks
 * that company shows it — no code, no HTML.
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_CONTENT_LOGOS_OPTION = 'ap_content_vendor_logos';
const AP_CONTENT_LOGOS_PAGE   = 'ap-vendor-logos';

/**
 * A company name as the design compares it: lower case, no tags, no trailing
 * "(…)" ("ManagerPlus (Eptura Asset)" → "managerplus").
 *
 * @param string $name Name.
 * @return string
 */
function ap_content_logo_norm( $name ) {
	$n = html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' );
	$n = preg_replace( '/\s*\([^)]*\)\s*$/u', '', $n );
	$n = preg_replace( '/\s+/u', ' ', $n );
	return strtolower( trim( $n ) );
}

/**
 * The logos writers added: [ id => attachment id, names => [normalised names] ].
 *
 * @return array[]
 */
function ap_content_vendor_logos() {
	$raw = get_option( AP_CONTENT_LOGOS_OPTION, array() );
	$out = array();
	foreach ( is_array( $raw ) ? $raw : array() as $row ) {
		$id    = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$names = isset( $row['names'] ) && is_array( $row['names'] ) ? array_values( array_filter( array_map( 'ap_content_logo_norm', $row['names'] ) ) ) : array();
		if ( $id && $names ) {
			$out[] = array(
				'id'    => $id,
				'names' => $names,
			);
		}
	}
	return $out;
}

/**
 * Whether an attachment is an image a logo can use (JPG, PNG, WebP, GIF or SVG).
 * WordPress's own wp_attachment_is_image() says no to SVG.
 *
 * @param int $id Attachment id.
 * @return bool
 */
function ap_content_logo_is_image( $id ) {
	return 'attachment' === get_post_type( $id ) && ( 0 === strpos( (string) get_post_mime_type( $id ), 'image/' ) || ap_content_logo_is_svg( $id ) );
}

/**
 * Whether an attachment is an SVG (some on www were stored as
 * application/octet-stream, so the file name counts too).
 *
 * @param int $id Attachment id.
 * @return bool
 */
function ap_content_logo_is_svg( $id ) {
	if ( 'image/svg+xml' === get_post_mime_type( $id ) ) {
		return true;
	}
	$file = (string) get_attached_file( $id );
	return '' !== $file && 'svg' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
}

/**
 * An attachment's natural width and height: from its metadata for photos,
 * from the viewBox (or width/height) of the file for an SVG.
 *
 * @param int $id Attachment id.
 * @return float[] [ width, height ], [ 0, 0 ] when unknown.
 */
function ap_content_logo_natural( $id ) {
	if ( ap_content_logo_is_svg( $id ) ) {
		$file = get_attached_file( $id );
		$svg  = ( $file && is_readable( $file ) ) ? (string) file_get_contents( $file, false, null, 0, 16384 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( preg_match( '/<svg\b[^>]*>/i', $svg, $tag ) ) {
			if ( preg_match( '/\bviewBox\s*=\s*["\']\s*-?[\d.]+[\s,]+-?[\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $tag[0], $m ) && (float) $m[1] > 0 && (float) $m[2] > 0 ) {
				return array( (float) $m[1], (float) $m[2] );
			}
			$w = preg_match( '/\swidth\s*=\s*["\']\s*([\d.]+)\s*(px)?\s*["\']/i', $tag[0], $m ) ? (float) $m[1] : 0;
			$h = preg_match( '/\sheight\s*=\s*["\']\s*([\d.]+)\s*(px)?\s*["\']/i', $tag[0], $m ) ? (float) $m[1] : 0;
			if ( $w > 0 && $h > 0 ) {
				return array( $w, $h );
			}
		}
	}
	$meta = wp_get_attachment_metadata( $id );
	if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
		return array( (float) $meta['width'], (float) $meta['height'] );
	}
	return array( 0, 0 );
}

/**
 * The size a logo is shown at, so every logo looks about as big as the
 * built-in ones whatever its shape: about the area of a 240×75 box, never
 * wider than 370 or taller than 96 (a wide word mark ≈ 335×56, a square icon
 * 96×96).
 *
 * @param float $w Natural width.
 * @param float $h Natural height.
 * @return int[] [ width, height ]
 */
function ap_content_logo_fit( $w, $h ) {
	if ( $w <= 0 || $h <= 0 ) {
		return array( 240, 75 );
	}
	$s = sqrt( 18000 / ( $w * $h ) );
	$s = min( $s, 370 / $w, 96 / $h );
	return array( max( 1, (int) round( $w * $s ) ), max( 1, (int) round( $h * $s ) ) );
}

/**
 * A writer-added logo in the shape the design uses (url, w, h, aliases).
 *
 * @param array $row Stored row.
 * @return array|null
 */
function ap_content_logo_asset( array $row ) {
	$id = (int) $row['id'];
	if ( ! ap_content_logo_is_image( $id ) ) {
		return null;
	}
	list( $nw, $nh ) = ap_content_logo_natural( $id );
	list( $w, $h )   = ap_content_logo_fit( $nw, $nh );
	$url             = '';
	if ( ! ap_content_logo_is_svg( $id ) ) {
		// A copy at least twice the shown width, so it stays sharp on retina screens.
		foreach ( array( 'medium_large', 'large', 'full' ) as $size ) {
			$src = wp_get_attachment_image_src( $id, $size );
			if ( $src && ! empty( $src[0] ) ) {
				$url = $src[0];
				if ( (int) $src[1] >= 2 * $w ) {
					break;
				}
			}
		}
	}
	if ( '' === $url ) {
		$url = (string) wp_get_attachment_url( $id );
	}
	if ( '' === $url ) {
		return null;
	}
	return array(
		'url'     => $url,
		'w'       => $w,
		'h'       => $h,
		'aliases' => $row['names'],
		'custom'  => $id,
	);
}

// Writer-added logos first, so they win over the built-in list for the same name.
add_filter(
	'ap26x_assets',
	function ( $assets ) {
		if ( ! is_array( $assets ) || ! isset( $assets['logos'] ) || ! is_array( $assets['logos'] ) ) {
			return $assets;
		}
		$mine = array();
		foreach ( ap_content_vendor_logos() as $row ) {
			$asset = ap_content_logo_asset( $row );
			if ( $asset ) {
				$mine[] = $asset;
			}
		}
		$assets['logos'] = array_merge( $mine, $assets['logos'] );
		return $assets;
	},
	20
);

/**
 * Every name the design already has a logo for, writer-added ones included.
 * The design's own list lives in a front-end snippet; when it is not loaded
 * (wp-admin), its names are listed here.
 *
 * @return string[]
 */
function ap_content_logo_known_names() {
	$names = array();
	if ( function_exists( 'ap26x_assets' ) ) {
		$assets = ap26x_assets();
		foreach ( (array) ( $assets['logos'] ?? array() ) as $logo ) {
			$names = array_merge( $names, (array) ( $logo['aliases'] ?? array() ) );
		}
		return array_values( array_unique( $names ) );
	}
	$snippet = array( 'asset panda', 'safetyculture', 'safety culture', 'aveva', 'aveva eam', 'aveva enterprise asset management', 'invgate', 'invgate assets', 'sage', 'sage fixed assets', 'sap', 'sap asset manager', 'ibm', 'ibm maximo', 'maximo', 'manageengine', 'assetexplorer', 'manageengine assetexplorer', 'upkeep', 'maintainx', 'limble', 'limble cmms', 'hexagon', 'hxgn eam', 'hexagon eam', 'accruent' );
	foreach ( ap_content_listicle_logos() as $logo ) {
		$snippet = array_merge( $snippet, (array) $logo[3] );
	}
	foreach ( ap_content_vendor_logos() as $row ) {
		$snippet = array_merge( $snippet, $row['names'] );
	}
	return array_values( array_unique( $snippet ) );
}

/**
 * Whether the design finds a logo for a company name (same rule as the design:
 * an exact name, or a known name followed by more words).
 *
 * @param string   $name  Company name.
 * @param string[] $known Known names.
 * @return bool
 */
function ap_content_logo_known( $name, array $known ) {
	$n = ap_content_logo_norm( $name );
	if ( '' === $n || in_array( $n, $known, true ) ) {
		return '' !== $n;
	}
	foreach ( $known as $alias ) {
		if ( 0 === strpos( $n . ' ', $alias . ' ' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Companies ranked in published "Best …" articles that have no logo yet.
 *
 * @return array name => [ 'count' => int, 'posts' => [titles] ]
 */
function ap_content_logos_missing() {
	$known = ap_content_logo_known_names();
	$ids   = get_posts(
		array(
			'post_type'      => array( 'resource', 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 400,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$missing = array();
	foreach ( $ids as $id ) {
		$title = get_the_title( $id );
		if ( ! preg_match( '/\b(best|top|alternatives?|vs\.?|compared|comparison)\b/i', $title ) || preg_match( '/best practices?/i', $title ) ) {
			continue;
		}
		$blocks = parse_blocks( (string) get_post_field( 'post_content', $id ) );
		foreach ( $blocks as $bi => $b ) {
			if ( 'core/heading' !== ( $b['blockName'] ?? '' ) || 2 !== (int) ( $b['attrs']['level'] ?? 2 ) ) {
				continue;
			}
			// A filled logo box under the heading (logo-boxes.php) is this article's logo.
			if ( function_exists( 'ap_content_next_block' ) && '' !== ap_content_block_image_src( ap_content_next_block( $blocks, $bi ) ) ) {
				continue;
			}
			$plain = trim( html_entity_decode( wp_strip_all_tags( (string) $b['innerHTML'] ), ENT_QUOTES, 'UTF-8' ) );
			if ( ! preg_match( '/^#?\s*\d{1,2}\s*[:.)\x{2013}\x{2014}-]\s*(.+)$/u', $plain, $m ) ) {
				continue;
			}
			$parts = preg_split( '/\s+[\x{2013}\x{2014}-]\s+|:\s+/u', trim( $m[1] ), 2 );
			$name  = trim( $parts[0] );
			if ( '' === $name || mb_strlen( $name ) > 60 || ap_content_logo_known( $name, $known ) ) {
				continue;
			}
			$key = ap_content_logo_norm( $name );
			if ( ! isset( $missing[ $key ] ) ) {
				$missing[ $key ] = array(
					'name'  => $name,
					'count' => 0,
					'posts' => array(),
				);
			}
			++$missing[ $key ]['count'];
			if ( count( $missing[ $key ]['posts'] ) < 3 && ! in_array( $title, $missing[ $key ]['posts'], true ) ) {
				$missing[ $key ]['posts'][] = html_entity_decode( $title, ENT_QUOTES, 'UTF-8' );
			}
		}
	}
	uasort(
		$missing,
		static function ( $a, $b ) {
			return $b['count'] <=> $a['count'] ?: strcasecmp( $a['name'], $b['name'] );
		}
	);
	return $missing;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=resource',
			__( 'Vendor logos', 'asset-panda-content' ),
			__( 'Vendor logos', 'asset-panda-content' ),
			'edit_posts',
			AP_CONTENT_LOGOS_PAGE,
			'ap_content_render_logos_page'
		);
	},
	20
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( false !== strpos( (string) $hook, AP_CONTENT_LOGOS_PAGE ) ) {
			wp_enqueue_media();
		}
	}
);

/** The Vendor logos screen. */
function ap_content_render_logos_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$mine    = ap_content_vendor_logos();
	$missing = ap_content_logos_missing();
	$notice  = isset( $_GET['ap_logo'] ) ? sanitize_key( wp_unslash( $_GET['ap_logo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$prefill = isset( $_GET['name'] ) ? sanitize_text_field( wp_unslash( $_GET['name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$action  = admin_url( 'admin-post.php' );
	?>
	<div class="wrap ap-logos">
		<h1><?php esc_html_e( 'Vendor logos', 'asset-panda-content' ); ?></h1>
		<p class="ap-logos__lead"><?php esc_html_e( 'In “Best …” articles, every ranked company shows its logo under its heading. Add a logo here once and every article that lists that company shows it — on the live page and in Preview (the editor itself shows the plain heading).', 'asset-panda-content' ); ?></p>
		<?php if ( 'added' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Logo saved. Articles that list this company show it now.', 'asset-panda-content' ); ?></p></div>
		<?php elseif ( 'removed' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Logo removed.', 'asset-panda-content' ); ?></p></div>
		<?php elseif ( 'missing' === $notice ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Choose a logo image and type the company name, then save.', 'asset-panda-content' ); ?></p></div>
		<?php endif; ?>

		<div class="ap-logos__grid">
			<div class="ap-logos__card" id="ap-logos-add">
				<h2><?php esc_html_e( 'Add a logo', 'asset-panda-content' ); ?></h2>
				<form method="post" action="<?php echo esc_url( $action ); ?>">
					<input type="hidden" name="action" value="ap_content_logo_save">
					<?php wp_nonce_field( 'ap_content_logo_save' ); ?>
					<input type="hidden" name="logo_id" id="ap-logo-id" value="">
					<p class="ap-logos__pick">
						<span class="ap-logos__preview" id="ap-logo-preview"><?php esc_html_e( 'No image chosen', 'asset-panda-content' ); ?></span>
						<button type="button" class="button" id="ap-logo-choose"><?php esc_html_e( 'Choose or upload logo', 'asset-panda-content' ); ?></button>
					</p>
					<p>
						<label for="ap-logo-name"><strong><?php esc_html_e( 'Company name', 'asset-panda-content' ); ?></strong></label><br>
						<input type="text" class="regular-text" id="ap-logo-name" name="logo_name" value="<?php echo esc_attr( $prefill ); ?>" placeholder="<?php esc_attr_e( 'e.g. Freshservice', 'asset-panda-content' ); ?>" required autocomplete="off">
						<br><span class="description"><?php esc_html_e( 'As it appears in the article heading, after the number: “3. Freshservice — …” → Freshservice.', 'asset-panda-content' ); ?></span>
					</p>
					<p>
						<label for="ap-logo-aka"><strong><?php esc_html_e( 'Other names (optional)', 'asset-panda-content' ); ?></strong></label><br>
						<input type="text" class="regular-text" id="ap-logo-aka" name="logo_aka" value="" placeholder="<?php esc_attr_e( 'e.g. Freshworks Freshservice, Fresh Service', 'asset-panda-content' ); ?>" autocomplete="off">
						<br><span class="description"><?php esc_html_e( 'Other spellings used in headings, separated by commas.', 'asset-panda-content' ); ?></span>
					</p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save logo', 'asset-panda-content' ); ?></button></p>
				</form>
			</div>

			<div class="ap-logos__card">
				<h2><?php esc_html_e( 'Companies still without a logo', 'asset-panda-content' ); ?></h2>
				<?php if ( ! $missing ) : ?>
					<p><?php esc_html_e( 'Every ranked company in a published “Best …” article has a logo.', 'asset-panda-content' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'These show the company’s initials instead of a logo today.', 'asset-panda-content' ); ?></p>
					<table class="widefat striped ap-logos__missing">
						<tbody>
						<?php foreach ( array_slice( $missing, 0, 60 ) as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['name'] ); ?></strong><br><span class="description"><?php echo esc_html( implode( ' · ', $row['posts'] ) ); ?></span></td>
								<td class="ap-logos__act"><a class="button button-small ap-logo-add-for" href="#ap-logos-add" data-name="<?php echo esc_attr( $row['name'] ); ?>"><?php esc_html_e( 'Add logo', 'asset-panda-content' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<h2><?php esc_html_e( 'Logos added here', 'asset-panda-content' ); ?></h2>
		<?php if ( ! $mine ) : ?>
			<p><?php esc_html_e( 'None yet. The built-in logos (Asset Panda, SafetyCulture, AVEVA, InvGate, Sage, SAP, IBM Maximo, UpKeep and others) keep working.', 'asset-panda-content' ); ?></p>
		<?php else : ?>
			<table class="widefat striped ap-logos__mine">
				<thead><tr><th><?php esc_html_e( 'Logo', 'asset-panda-content' ); ?></th><th><?php esc_html_e( 'Shown for', 'asset-panda-content' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $mine as $i => $row ) : ?>
					<tr>
						<?php $shown = ap_content_logo_asset( $row ); ?>
						<td class="ap-logos__img"><?php if ( $shown ) : ?><img src="<?php echo esc_url( $shown['url'] ); ?>" width="<?php echo (int) round( $shown['w'] / 2 ); ?>" height="<?php echo (int) round( $shown['h'] / 2 ); ?>" alt=""><?php endif; ?></td>
						<td><?php echo esc_html( implode( ', ', $row['names'] ) ); ?></td>
						<td class="ap-logos__act">
							<form method="post" action="<?php echo esc_url( $action ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this logo? Articles go back to showing the initials.', 'asset-panda-content' ) ); ?>');">
								<input type="hidden" name="action" value="ap_content_logo_remove">
								<input type="hidden" name="index" value="<?php echo (int) $i; ?>">
								<?php wp_nonce_field( 'ap_content_logo_remove' ); ?>
								<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Remove', 'asset-panda-content' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<style>
		.ap-logos__lead { font-size: 14px; max-width: 760px; }
		.ap-logos__grid { display: grid; grid-template-columns: minmax(320px, 1fr) minmax(320px, 1fr); gap: 20px; margin: 16px 0 28px; }
		@media (max-width: 960px) { .ap-logos__grid { grid-template-columns: 1fr; } }
		.ap-logos__card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 4px 20px 12px; }
		.ap-logos__pick { display: flex; align-items: center; gap: 14px; }
		.ap-logos__preview { display: inline-flex; align-items: center; justify-content: center; min-width: 160px; height: 64px; padding: 6px 10px; border: 1px dashed #c3c4c7; border-radius: 6px; color: #646970; background: #f6f7f7; }
		.ap-logos__preview img { max-height: 52px; max-width: 200px; }
		.ap-logos__act { text-align: right; white-space: nowrap; width: 1%; }
		.ap-logos__img img { display: block; }
	</style>
	<script>
	( function () {
		var frame;
		var idField = document.getElementById( 'ap-logo-id' );
		var preview = document.getElementById( 'ap-logo-preview' );
		var name = document.getElementById( 'ap-logo-name' );
		document.getElementById( 'ap-logo-choose' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( ! window.wp || ! wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = wp.media( { title: <?php echo wp_json_encode( __( 'Choose a logo', 'asset-panda-content' ) ); ?>, button: { text: <?php echo wp_json_encode( __( 'Use this logo', 'asset-panda-content' ) ); ?> }, library: { type: 'image' }, multiple: false } );
				frame.on( 'select', function () {
					var a = frame.state().get( 'selection' ).first().toJSON();
					var url = ( a.sizes && ( a.sizes.medium || a.sizes.full ) ) ? ( a.sizes.medium || a.sizes.full ).url : a.url;
					idField.value = a.id;
					preview.innerHTML = '';
					var img = document.createElement( 'img' );
					img.src = url;
					img.alt = '';
					preview.appendChild( img );
				} );
			}
			frame.open();
		} );
		document.querySelectorAll( '.ap-logo-add-for' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				name.value = b.getAttribute( 'data-name' );
				setTimeout( function () { name.focus(); }, 50 );
			} );
		} );
	}() );
	</script>
	<?php
}

add_action(
	'admin_post_ap_content_logo_save',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'asset-panda-content' ), 403 );
		}
		check_admin_referer( 'ap_content_logo_save' );
		$id    = isset( $_POST['logo_id'] ) ? absint( $_POST['logo_id'] ) : 0;
		$names = array();
		foreach ( array( 'logo_name', 'logo_aka' ) as $field ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			foreach ( explode( ',', $value ) as $part ) {
				$n = ap_content_logo_norm( $part );
				if ( '' !== $n && strlen( $n ) <= 80 ) {
					$names[] = $n;
				}
			}
		}
		$url = add_query_arg( array( 'post_type' => 'resource', 'page' => AP_CONTENT_LOGOS_PAGE ), admin_url( 'edit.php' ) );
		if ( ! $id || ! ap_content_logo_is_image( $id ) || ! $names ) {
			wp_safe_redirect( add_query_arg( 'ap_logo', 'missing', $url ) );
			exit;
		}
		$names = array_values( array_unique( $names ) );
		$rows  = array();
		// A name moves to the new logo: drop it from older rows (and rows left empty).
		foreach ( ap_content_vendor_logos() as $row ) {
			$row['names'] = array_values( array_diff( $row['names'], $names ) );
			if ( $row['names'] ) {
				$rows[] = $row;
			}
		}
		$rows[] = array(
			'id'    => $id,
			'names' => $names,
		);
		update_option( AP_CONTENT_LOGOS_OPTION, $rows, false );
		wp_safe_redirect( add_query_arg( 'ap_logo', 'added', $url ) );
		exit;
	}
);

add_action(
	'admin_post_ap_content_logo_remove',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'asset-panda-content' ), 403 );
		}
		check_admin_referer( 'ap_content_logo_remove' );
		$index = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
		$rows  = ap_content_vendor_logos();
		if ( isset( $rows[ $index ] ) ) {
			array_splice( $rows, $index, 1 );
			update_option( AP_CONTENT_LOGOS_OPTION, $rows, false );
		}
		wp_safe_redirect( add_query_arg( array( 'post_type' => 'resource', 'page' => AP_CONTENT_LOGOS_PAGE, 'ap_logo' => 'removed' ), admin_url( 'edit.php' ) ) );
		exit;
	}
);
