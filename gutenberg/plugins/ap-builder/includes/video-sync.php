<?php
/**
 * Video Library → Settings: YouTube sources that fill the library by
 * themselves, and the one-time import of the videos already on the site.
 *
 * Sync (hourly, WP-Cron, and "Sync now"): for each source — a channel
 * (youtube.com/channel/UC…, youtube.com/@handle) or a playlist (…list=PL…) —
 * new uploads are added to the library. Videos published after the source was
 * added go straight to the source's placement (e.g. the Video Tutorials page);
 * older ones are added without a placement, so a first sync never floods a
 * page. A video someone deletes from the library is not added again.
 *
 * Feed: YouTube's public RSS (no key; the latest 15 per source). It has been
 * unreliable (404/500 in 2026-11), so with a YouTube Data API key set here (or
 * AP_YOUTUBE_API_KEY in wp-config.php) the official API is used instead (50
 * per source per run, 1 quota unit each, of 10,000 free a day).
 *
 * @package APBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_VIDEO_SYNC_OPTION = 'ap_video_sync';
const AP_VIDEO_SYNC_EVENT  = 'ap_video_sync_event';

/** Settings, with defaults: the Asset Panda channel, new uploads to the tutorials. */
function ap_video_sync_settings() {
	$s = get_option( AP_VIDEO_SYNC_OPTION );
	if ( ! is_array( $s ) ) {
		// Saved at once, so "since" is the day the library started, not the moment of each read.
		$s = array(
			'sources' => array(
				array(
					'url'   => 'https://www.youtube.com/channel/UCmz_q5p4O_NOShFjawIg4Fw',
					'place' => 'tutorials',
					'since' => gmdate( 'c' ),
				),
			),
			'api_key' => '',
		);
		add_option( AP_VIDEO_SYNC_OPTION, $s, '', false );
	}
	$s['sources'] = isset( $s['sources'] ) && is_array( $s['sources'] ) ? $s['sources'] : array();
	return $s;
}

/** The API key: wp-config.php wins over the settings field. */
function ap_video_api_key() {
	if ( defined( 'AP_YOUTUBE_API_KEY' ) && AP_YOUTUBE_API_KEY ) {
		return (string) AP_YOUTUBE_API_KEY;
	}
	$s = ap_video_sync_settings();
	return isset( $s['api_key'] ) ? (string) $s['api_key'] : '';
}

/**
 * What a source link points at.
 *
 * @param string $url Channel or playlist link.
 * @return array { kind: channel|playlist|'', id }
 */
function ap_video_source_target( $url ) {
	$url = trim( (string) $url );
	if ( preg_match( '/[?&]list=([A-Za-z0-9_-]{10,})/', $url, $m ) ) {
		return array( 'kind' => 'playlist', 'id' => $m[1] );
	}
	if ( preg_match( '~youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~', $url, $m ) ) {
		return array( 'kind' => 'channel', 'id' => $m[1] );
	}
	if ( preg_match( '~youtube\.com/(@[A-Za-z0-9._-]+|c/[^/?#]+|user/[^/?#]+)~', $url, $m ) ) {
		$key = 'ap_video_ch_' . md5( $m[1] );
		$id  = get_transient( $key );
		if ( ! $id ) {
			$res = wp_remote_get( 'https://www.youtube.com/' . $m[1], array( 'timeout' => 12 ) );
			$id  = preg_match( '/"(?:channelId|externalId)":"(UC[A-Za-z0-9_-]{22})"/', (string) wp_remote_retrieve_body( $res ), $c ) ? $c[1] : '';
			set_transient( $key, $id, $id ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
		}
		return array( 'kind' => $id ? 'channel' : '', 'id' => (string) $id );
	}
	return array( 'kind' => '', 'id' => '' );
}

/**
 * The newest videos of a source.
 *
 * @param array $target { kind, id }.
 * @return array|WP_Error [ { id, title, description, published } ]
 */
function ap_video_source_videos( array $target ) {
	$key = ap_video_api_key();
	if ( '' !== $key ) {
		$playlist = 'channel' === $target['kind'] ? 'UU' . substr( $target['id'], 2 ) : $target['id'];
		$res      = wp_remote_get(
			add_query_arg(
				array(
					'part'       => 'snippet',
					'maxResults' => 50,
					'playlistId' => $playlist,
					'key'        => $key,
				),
				'https://www.googleapis.com/youtube/v3/playlistItems'
			),
			array( 'timeout' => 15 )
		);
		$data     = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $res ) || ! is_array( $data ) ) {
			$msg = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'HTTP ' . wp_remote_retrieve_response_code( $res );
			return new WP_Error( 'ap_video_api', 'YouTube API: ' . wp_strip_all_tags( $msg ) );
		}
		$out = array();
		foreach ( (array) ( isset( $data['items'] ) ? $data['items'] : array() ) as $item ) {
			$sn = isset( $item['snippet'] ) ? $item['snippet'] : array();
			$id = isset( $sn['resourceId']['videoId'] ) ? (string) $sn['resourceId']['videoId'] : '';
			if ( '' === $id || in_array( isset( $sn['title'] ) ? $sn['title'] : '', array( 'Private video', 'Deleted video' ), true ) ) {
				continue;
			}
			$out[] = array(
				'id'          => $id,
				'title'       => isset( $sn['title'] ) ? (string) $sn['title'] : '',
				'description' => isset( $sn['description'] ) ? (string) $sn['description'] : '',
				'published'   => isset( $sn['publishedAt'] ) ? (string) $sn['publishedAt'] : '',
			);
		}
		return $out;
	}
	$feed = 'https://www.youtube.com/feeds/videos.xml?' . ( 'channel' === $target['kind'] ? 'channel_id=' : 'playlist_id=' ) . rawurlencode( $target['id'] );
	$res  = wp_remote_get( $feed, array( 'timeout' => 15 ) );
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = (string) wp_remote_retrieve_body( $res );
	if ( 200 !== $code || false === strpos( $body, '<entry>' ) ) {
		return new WP_Error( 'ap_video_feed', sprintf( 'YouTube’s public feed answered %s. Add a YouTube Data API key below to sync reliably.', $code ? 'HTTP ' . $code : 'nothing' ) );
	}
	$out = array();
	if ( preg_match_all( '#<entry>(.*?)</entry>#s', $body, $entries ) ) {
		foreach ( $entries[1] as $entry ) {
			$get   = static function ( $pattern ) use ( $entry ) {
				return preg_match( $pattern, $entry, $m ) ? html_entity_decode( $m[1], ENT_QUOTES | ENT_XML1, 'UTF-8' ) : '';
			};
			$out[] = array(
				'id'          => $get( '#<yt:videoId>([^<]+)</yt:videoId>#' ),
				'title'       => $get( '#<title>([^<]*)</title>#' ),
				'description' => $get( '#<media:description>(.*?)</media:description>#s' ),
				'published'   => $get( '#<published>([^<]+)</published>#' ),
			);
		}
	}
	return array_values(
		array_filter(
			$out,
			static function ( $v ) {
				return '' !== $v['id'];
			}
		)
	);
}

/** A YouTube description as the library's: the first paragraph, without links and hashtags, at most 400 characters. */
function ap_video_clean_description( $text ) {
	$text  = preg_replace( '~https?://\S+~', '', (string) $text );
	$text  = preg_replace( '/(^|\s)#\w+/u', '$1', (string) $text );
	$parts = preg_split( "/\n\s*\n/", trim( (string) $text ) );
	return wp_html_excerpt( trim( preg_replace( '/[ \t]+/', ' ', (string) ( $parts ? $parts[0] : '' ) ) ), 400, '…' );
}

/**
 * One sync run over every source.
 *
 * @return array { added, errors[], at }
 */
function ap_video_sync_run() {
	$s      = ap_video_sync_settings();
	$seen   = (array) get_option( 'ap_video_seen', array() );
	$added  = 0;
	$posters = 0;
	$errors = array();
	foreach ( $s['sources'] as $source ) {
		$target = ap_video_source_target( isset( $source['url'] ) ? $source['url'] : '' );
		if ( '' === $target['kind'] ) {
			$errors[] = ( isset( $source['url'] ) ? $source['url'] : '?' ) . ': not a YouTube channel or playlist link.';
			continue;
		}
		$videos = ap_video_source_videos( $target );
		if ( is_wp_error( $videos ) ) {
			$errors[] = $videos->get_error_message();
			continue;
		}
		$since = isset( $source['since'] ) ? strtotime( (string) $source['since'] ) : 0;
		foreach ( $videos as $v ) {
			if ( in_array( $v['id'], $seen, true ) || ap_video_find( $v['id'] ) ) {
				$seen[] = $v['id'];
				continue;
			}
			$new   = $since && strtotime( $v['published'] ) >= $since;
			$place = isset( $source['place'] ) ? sanitize_key( (string) $source['place'] ) : '';
			$id    = ap_video_add(
				'https://www.youtube.com/watch?v=' . $v['id'],
				array(
					'title'       => $v['title'],
					'excerpt'     => ap_video_clean_description( $v['description'] ),
					'places'      => $new && '' !== $place ? array( $place ) : array(),
					'source'      => 'youtube ' . $target['kind'],
					'published'   => $v['published'],
					'skip_poster' => $posters >= 6, // A few pictures per run keeps the run short; pages use YouTube's picture until then.
				)
			);
			if ( ! is_wp_error( $id ) ) {
				++$added;
				++$posters;
				$seen[] = $v['id'];
			}
		}
	}
	// Videos still without a picture: a few more each run.
	foreach ( get_posts( array( 'post_type' => AP_VIDEO_TYPE, 'post_status' => 'publish', 'numberposts' => 6, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ), 'suppress_filters' => true ) ) as $pid ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		ap_video_fetch_poster( $pid, (string) get_post_meta( $pid, '_ap_video_id', true ) );
	}
	update_option( 'ap_video_seen', array_values( array_unique( $seen ) ), false );
	$status = array(
		'added'  => $added,
		'errors' => $errors,
		'at'     => time(),
	);
	update_option( 'ap_video_sync_status', $status, false );
	return $status;
}

add_action( AP_VIDEO_SYNC_EVENT, 'ap_video_sync_run' );

add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( AP_VIDEO_SYNC_EVENT ) ) {
			wp_schedule_event( time() + 300, 'hourly', AP_VIDEO_SYNC_EVENT );
		}
	}
);

register_deactivation_hook(
	AP_BUILDER_FILE,
	static function () {
		wp_clear_scheduled_hook( AP_VIDEO_SYNC_EVENT );
	}
);

/**
 * One-time import: the YouTube videos already on the site's pages become
 * library videos. The /tutorials/ gallery's keep their order, poster and
 * caption and are placed on the Video Tutorials page; the rest go in
 * without a placement.
 *
 * @return array { added, found }
 */
function ap_video_import_site() {
	$tutorials = get_page_by_path( 'tutorials' );
	$pages     = get_posts(
		array(
			'post_type'        => array( 'page', 'resource' ),
			'post_status'      => 'publish',
			'numberposts'      => 600,
			'suppress_filters' => true,
		)
	);
	$found = 0;
	$added = 0;
	$take  = static function ( $url, array $args ) use ( &$found, &$added ) {
		if ( '' === ap_video_youtube_id( $url ) ) {
			return;
		}
		++$found;
		$before = ap_video_find( ap_video_youtube_id( $url ) );
		$id     = ap_video_add( $url, $args + array( 'source' => 'site import', 'skip_poster' => $added >= 12 ) );
		if ( ! $before && ! is_wp_error( $id ) ) {
			++$added;
		}
	};
	foreach ( $pages as $post ) {
		if ( false === strpos( $post->post_content, 'youtu' ) ) {
			continue;
		}
		$is_tutorials = $tutorials && (int) $tutorials->ID === (int) $post->ID;
		foreach ( parse_blocks( $post->post_content ) as $block ) {
			$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$attrs = isset( $block['attrs'] ) ? (array) $block['attrs'] : array();
			if ( 'assetpanda/page-videos' === $name ) {
				foreach ( array_values( (array) ( isset( $attrs['items'] ) ? $attrs['items'] : array() ) ) as $i => $item ) {
					$poster = isset( $item['poster'] ) && function_exists( 'attachment_url_to_postid' ) ? attachment_url_to_postid( (string) $item['poster'] ) : 0;
					$take(
						isset( $item['video'] ) ? (string) $item['video'] : '',
						array(
							'title'      => isset( $item['title'] ) ? (string) $item['title'] : '',
							'excerpt'    => isset( $item['body'] ) ? (string) $item['body'] : '',
							'places'     => $is_tutorials ? array( 'tutorials' ) : array(),
							'menu_order' => $is_tutorials ? $i + 1 : 0,
							'poster_id'  => $poster,
						)
					);
				}
			} elseif ( 'assetpanda/media-band' === $name && ! empty( $attrs['video'] ) ) {
				// No title: the band's video_title is a button label ("Watch the …"), YouTube's own title is better.
				$take( (string) $attrs['video'], array( 'excerpt' => isset( $attrs['body'] ) ? (string) $attrs['body'] : '' ) );
			} elseif ( 'assetpanda/story-slider' === $name ) {
				foreach ( (array) ( isset( $attrs['items'] ) ? $attrs['items'] : array() ) as $item ) {
					if ( ! empty( $item['video_id'] ) ) {
						$take( (string) $item['video_id'], array() );
					}
				}
			} elseif ( 'assetpanda/integration-detail' === $name && ! empty( $attrs['overview']['video_url'] ) ) {
				$id = ap_video_youtube_id( (string) $attrs['overview']['video_url'] );
				if ( '' !== $id ) {
					$take( (string) $attrs['overview']['video_url'], array( 'places' => array( 'integrations' ) ) );
					$vid = ap_video_find( $id );
					if ( $vid && ! get_post_meta( $vid, '_ap_video_integration', true ) ) {
						update_post_meta( $vid, '_ap_video_integration', (int) $post->ID );
					}
				}
			}
		}
	}
	ap_video_refresh_titles();
	return array(
		'found' => $found,
		'added' => $added,
	);
}

/**
 * Videos named after a button label by an earlier import ("Watch the webinar: …")
 * take YouTube's own title.
 *
 * @return array[] One row per video looked at: id, old, new (or '' when YouTube gave none).
 */
function ap_video_refresh_titles() {
	$report = array();
	$videos = get_posts(
		array(
			'post_type'        => AP_VIDEO_TYPE,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts'      => 300,
			'suppress_filters' => true,
		)
	);
	foreach ( $videos as $video ) {
		if ( 0 !== stripos( (string) $video->post_title, 'watch ' ) ) {
			continue;
		}
		$oe  = ap_video_oembed( (string) get_post_meta( $video->ID, '_ap_video_id', true ) );
		$new = isset( $oe['title'] ) ? (string) $oe['title'] : '';
		if ( '' !== $new ) {
			wp_update_post(
				array(
					'ID'         => $video->ID,
					'post_title' => $new,
				)
			);
		}
		$report[] = array(
			'id'  => (int) $video->ID,
			'old' => (string) $video->post_title,
			'new' => $new,
		);
	}
	return $report;
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			AP_BUILDER_REST_NS,
			'/videos/refresh-titles',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
				'callback'            => static function () {
					return rest_ensure_response( ap_video_refresh_titles() );
				},
			)
		);
	}
);

/* ---- The settings screen ---------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=' . AP_VIDEO_TYPE,
			__( 'Video Library settings', 'ap-builder' ),
			__( 'Settings', 'ap-builder' ),
			'edit_pages',
			'ap-video-settings',
			'ap_video_settings_page'
		);
	}
);

/** Saves the settings form and runs the buttons. */
add_action(
	'admin_post_ap_video_settings',
	static function () {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ap-builder' ) );
		}
		check_admin_referer( 'ap_video_settings' );
		$do     = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'save';
		$notice = 'saved';
		if ( 'sync' === $do ) {
			$st     = ap_video_sync_run();
			$notice = 'sync-' . (int) $st['added'] . ( $st['errors'] ? '-errors' : '' );
		} elseif ( 'import' === $do ) {
			$st     = ap_video_import_site();
			$notice = 'import-' . (int) $st['added'] . '-' . (int) $st['found'];
		} else {
			$old     = ap_video_sync_settings();
			$known   = array();
			foreach ( $old['sources'] as $src ) {
				$known[ isset( $src['url'] ) ? $src['url'] : '' ] = isset( $src['since'] ) ? $src['since'] : gmdate( 'c' );
			}
			$urls    = isset( $_POST['src_url'] ) ? array_map( 'trim', (array) wp_unslash( $_POST['src_url'] ) ) : array();
			$places  = isset( $_POST['src_place'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['src_place'] ) ) : array();
			$sources = array();
			$skipped = 0;
			foreach ( $urls as $i => $url ) {
				if ( '' === $url ) {
					continue;
				}
				// Only YouTube links: a browser or password manager can autofill an email into an empty row.
				if ( ! preg_match( '~^https?://(?:www\.|m\.)?(?:youtube\.com|youtu\.be)/~i', $url ) ) {
					++$skipped;
					continue;
				}
				$url       = esc_url_raw( $url );
				$sources[] = array(
					'url'   => $url,
					'place' => isset( $places[ $i ] ) ? $places[ $i ] : '',
					'since' => isset( $known[ $url ] ) ? $known[ $url ] : gmdate( 'c' ),
				);
			}
			$old_key = isset( $old['api_key'] ) ? (string) $old['api_key'] : '';
			$key     = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : $old_key;
			if ( '••••' === substr( $key, 0, 4 ) ) {
				$key = $old_key; // The masked value: unchanged.
			} elseif ( '' !== $key && ! preg_match( '/^AIza[0-9A-Za-z_-]{35}$/', $key ) ) {
				// Not a Google API key (they are "AIza" + 35 characters): never store what a password manager typed in.
				$key = $old_key;
				++$skipped;
			}
			$notice = $skipped ? 'saved-skipped-' . $skipped : 'saved';
			update_option(
				AP_VIDEO_SYNC_OPTION,
				array(
					'sources' => $sources,
					'api_key' => current_user_can( 'manage_options' ) ? $key : ( isset( $old['api_key'] ) ? $old['api_key'] : '' ),
				),
				false
			);
		}
		wp_safe_redirect( add_query_arg( 'ap_notice', $notice, admin_url( 'edit.php?post_type=' . AP_VIDEO_TYPE . '&page=ap-video-settings' ) ) );
		exit;
	}
);

/** The settings page. */
function ap_video_settings_page() {
	$s      = ap_video_sync_settings();
	$status = get_option( 'ap_video_sync_status' );
	$places = get_terms(
		array(
			'taxonomy'   => AP_VIDEO_PLACE,
			'hide_empty' => false,
		)
	);
	$rows   = array_merge( $s['sources'], array( array( 'url' => '', 'place' => '' ) ) );
	$notice = isset( $_GET['ap_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['ap_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$key    = ap_video_api_key();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Video Library settings', 'ap-builder' ); ?></h1>
		<?php if ( 'saved' === $notice ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Saved.', 'ap-builder' ); ?></p></div>
		<?php elseif ( preg_match( '/^saved-skipped-(\d+)$/', $notice, $m ) ) : ?>
			<div class="notice notice-warning"><p><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Saved, but %d entries were left out: sources must be YouTube channel or playlist links, and the API key must be a Google API key (it starts with AIza).', 'ap-builder' ), (int) $m[1] ) ); ?></p></div>
		<?php elseif ( preg_match( '/^sync-(\d+)(-errors)?$/', $notice, $m ) ) : ?>
			<div class="notice notice-<?php echo empty( $m[2] ) ? 'success' : 'warning'; ?>"><p><?php echo esc_html( sprintf( /* translators: %d: count */ __( 'Sync done: %d new videos added.', 'ap-builder' ), (int) $m[1] ) ); ?></p></div>
		<?php elseif ( preg_match( '/^import-(\d+)-(\d+)$/', $notice, $m ) ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( sprintf( /* translators: 1: added, 2: found */ __( 'Import done: %1$d videos added to the library (%2$d found on the site’s pages; the others were already in it).', 'ap-builder' ), (int) $m[1], (int) $m[2] ) ); ?></p></div>
		<?php endif; ?>

		<?php
		/*
		 * "Sync now" and the import post this field-free form (their buttons
		 * name it with form=""), so they never send the source rows or the key
		 * and a half-filled row cannot block them. Autofill is off on every
		 * field, and the key is a text field (it only ever shows a masked
		 * value): with no password field, password managers do not take the
		 * form for a login and type an email and password into it.
		 */
		?>
		<form id="ap-video-actions" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ap_video_settings" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'ap_video_settings' ) ); ?>" />
			<?php wp_referer_field(); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
			<input type="hidden" name="action" value="ap_video_settings" />
			<?php wp_nonce_field( 'ap_video_settings' ); ?>

			<h2><?php esc_html_e( 'YouTube sources', 'ap-builder' ); ?></h2>
			<p><?php esc_html_e( 'New uploads to these channels and playlists are added to the library every hour. Videos published after a source is added go straight to its placement; older ones are added without one, so you choose where they show.', 'ap-builder' ); ?></p>
			<table class="widefat striped" style="max-width:960px">
				<thead><tr><th><?php esc_html_e( 'Channel or playlist link', 'ap-builder' ); ?></th><th><?php esc_html_e( 'New uploads show on', 'ap-builder' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><input type="url" name="src_url[]" value="<?php echo esc_attr( isset( $row['url'] ) ? $row['url'] : '' ); ?>" class="widefat" placeholder="https://www.youtube.com/@assetpanda  or  …playlist?list=PL…" autocomplete="off" data-1p-ignore data-lpignore="true" data-bwignore="true" data-form-type="other" /></td>
						<td>
							<select name="src_place[]">
								<option value=""><?php esc_html_e( 'Library only (place them yourself)', 'ap-builder' ); ?></option>
								<?php foreach ( is_wp_error( $places ) ? array() : $places as $term ) : ?>
									<option value="<?php echo esc_attr( $term->slug ); ?>"<?php selected( isset( $row['place'] ) ? $row['place'] : '', $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'YouTube Data API key (optional)', 'ap-builder' ); ?></h2>
			<p><?php esc_html_e( 'YouTube’s free public feed has been unreliable. With a YouTube Data API key (Google Cloud → APIs → YouTube Data API v3 → Credentials → API key) the sync uses the official API instead. Free; this uses a few units of the 10,000 a day.', 'ap-builder' ); ?></p>
			<?php if ( defined( 'AP_YOUTUBE_API_KEY' ) && AP_YOUTUBE_API_KEY ) : ?>
				<p><em><?php esc_html_e( 'Set in wp-config.php (AP_YOUTUBE_API_KEY).', 'ap-builder' ); ?></em></p>
			<?php elseif ( current_user_can( 'manage_options' ) ) : ?>
				<p><input type="text" name="api_key" value="<?php echo esc_attr( '' !== $key ? '••••' . substr( $key, -4 ) : '' ); ?>" class="regular-text code" placeholder="AIza…" autocomplete="off" spellcheck="false" data-1p-ignore data-lpignore="true" data-bwignore="true" data-form-type="other" /></p>
			<?php else : ?>
				<p><em><?php esc_html_e( 'An administrator can add it.', 'ap-builder' ); ?></em></p>
			<?php endif; ?>

			<p>
				<button class="button button-primary" name="do" value="save"><?php esc_html_e( 'Save', 'ap-builder' ); ?></button>
				<button class="button" form="ap-video-actions" name="do" value="sync"><?php esc_html_e( 'Sync now', 'ap-builder' ); ?></button>
			</p>
			<?php if ( is_array( $status ) ) : ?>
				<p class="description">
					<?php echo esc_html( sprintf( /* translators: 1: time ago, 2: count */ __( 'Last sync %1$s ago: %2$d added.', 'ap-builder' ), human_time_diff( (int) $status['at'] ), (int) $status['added'] ) ); ?>
					<?php foreach ( (array) $status['errors'] as $error ) : ?>
						<br /><span style="color:#b32d2e"><?php echo esc_html( $error ); ?></span>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Videos already on the site', 'ap-builder' ); ?></h2>
			<p><?php esc_html_e( 'Adds every YouTube video already used on the site’s pages to the library (the /tutorials/ gallery’s keep their order, picture and caption and are placed on the Video Tutorials page). Pages do not change. Safe to run again: videos already in the library are skipped.', 'ap-builder' ); ?></p>
			<p><button class="button" form="ap-video-actions" name="do" value="import"><?php esc_html_e( 'Import the videos already on the site', 'ap-builder' ); ?></button></p>
		</form>
	</div>
	<?php
}
