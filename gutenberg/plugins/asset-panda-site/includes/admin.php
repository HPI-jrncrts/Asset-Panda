<?php
/**
 * Settings → Asset Panda Site: where this host stands, each feature's state,
 * the settings, a preview of the exact snippets, and the Apply buttons.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_init',
	static function () {
		register_setting(
			'ap_site',
			AP_SITE_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'ap_site_sanitize_settings',
				'show_in_rest'      => false,
			)
		);
	}
);

add_action(
	'admin_menu',
	static function () {
		add_options_page( 'Asset Panda Site', 'Asset Panda Site', 'manage_options', 'asset-panda-site', 'ap_site_render_admin_page' );
	}
);

/**
 * Reports a settings problem on the settings page (when running there).
 *
 * @param string $message Text.
 */
function ap_site_settings_error( $message ) {
	if ( function_exists( 'add_settings_error' ) ) {
		add_settings_error( AP_SITE_OPTION, 'ap_site_' . md5( $message ), $message, 'error' );
	}
}

/**
 * Splits a textarea or list into trimmed lines.
 *
 * @param mixed $value Text or array.
 * @return string[]
 */
function ap_site_lines( $value ) {
	if ( is_array( $value ) ) {
		return ap_site_string_list( $value );
	}
	return ap_site_string_list( preg_split( '/[\r\n,]+/', (string) $value ) );
}

/**
 * Validates settings from the form (marked with _form) or from code.
 * Invalid values keep the previous value and show an error.
 *
 * @param mixed $input Submitted value.
 * @return array
 */
function ap_site_sanitize_settings( $input ) {
	$current = ap_site_settings();
	if ( ! is_array( $input ) ) {
		return $current;
	}
	$form = ! empty( $input['_form'] );
	$out  = $current;

	foreach ( ap_site_bool_keys() as $key ) {
		if ( array_key_exists( $key, $input ) ) {
			$out[ $key ] = (bool) $input[ $key ];
		} elseif ( $form ) {
			$out[ $key ] = false; // Unticked checkbox.
		}
	}

	if ( array_key_exists( 'production_hosts', $input ) ) {
		$hosts = array();
		foreach ( ap_site_lines( $input['production_hosts'] ) as $host ) {
			$norm = ap_site_normalize_host( $host );
			if ( '' === $norm || ! ap_site_valid_hostname( $norm ) ) {
				ap_site_settings_error( 'Ignored "' . $host . '": not a host name.' );
			} elseif ( ap_site_is_blocked_host( $norm ) ) {
				ap_site_settings_error( 'Ignored "' . $host . '": staging and local hosts can never be production.' );
			} else {
				$hosts[] = $norm;
			}
		}
		$out['production_hosts'] = array_values( array_unique( $hosts ) );
	}

	if ( isset( $input['gtm_id'] ) ) {
		$id = strtoupper( trim( (string) $input['gtm_id'] ) );
		if ( ap_site_valid_gtm_id( $id ) ) {
			$out['gtm_id'] = $id;
		} else {
			ap_site_settings_error( 'Container ID must look like GTM-XXXXXX; kept ' . $current['gtm_id'] . '.' );
		}
	}
	if ( isset( $input['loader'] ) && in_array( $input['loader'], array( 'gtm', 'stape' ), true ) ) {
		$out['loader'] = $input['loader'];
	}
	if ( isset( $input['stape_domain'] ) ) {
		$host = strtolower( trim( (string) $input['stape_domain'] ) );
		if ( ap_site_valid_hostname( $host ) ) {
			$out['stape_domain'] = $host;
		} else {
			ap_site_settings_error( 'Stape domain is not a host name; kept ' . $current['stape_domain'] . '.' );
		}
	}
	if ( isset( $input['stape_loader'] ) ) {
		$file = trim( (string) $input['stape_loader'] );
		if ( preg_match( '/^[A-Za-z0-9_-]+\.js$/', $file ) ) {
			$out['stape_loader'] = $file;
		} else {
			ap_site_settings_error( 'Stape loader file must look like xabcdef.js; kept ' . $current['stape_loader'] . '.' );
		}
	}
	if ( isset( $input['stape_query'] ) ) {
		$query = trim( (string) $input['stape_query'] );
		if ( preg_match( '/^[A-Za-z0-9_]+=[A-Za-z0-9%._~-]+$/', $query ) ) {
			$out['stape_query'] = $query;
		} else {
			ap_site_settings_error( 'Stape loader parameter must look like a7a=…; kept the previous one.' );
		}
	}
	if ( isset( $input['cookiebot_id'] ) ) {
		$cbid = strtolower( trim( (string) $input['cookiebot_id'] ) );
		if ( ap_site_valid_cookiebot_id( $cbid ) ) {
			$out['cookiebot_id'] = $cbid;
		} else {
			ap_site_settings_error( 'Cookiebot ID must be a UUID; kept ' . $current['cookiebot_id'] . '.' );
		}
	}
	if ( isset( $input['ursa_demo_url'] ) ) {
		$url = esc_url_raw( trim( (string) $input['ursa_demo_url'] ), array( 'https' ) );
		if ( 0 === strpos( $url, 'https://' ) ) {
			$out['ursa_demo_url'] = $url;
		} else {
			ap_site_settings_error( 'Ursa demo URL must start with https://; kept the previous one.' );
		}
	}
	foreach ( ap_site_pattern_keys() as $key ) {
		if ( ! array_key_exists( $key, $input ) ) {
			continue;
		}
		$list = array();
		foreach ( ap_site_lines( $input[ $key ] ) as $pattern ) {
			if ( preg_match( '#^[/*][A-Za-z0-9/_.*%~-]*$#', $pattern ) ) {
				$list[] = $pattern;
			} else {
				ap_site_settings_error( 'Ignored path pattern "' . $pattern . '": use a path like /login-splash* .' );
			}
		}
		$out[ $key ] = array_values( array_unique( $list ) );
	}

	return array_intersect_key( $out, ap_site_defaults() );
}

/* ── Apply buttons ────────────────────────────────────────────────────── */

add_action(
	'admin_post_ap_site_apply',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sorry, you are not allowed to do that.', 403 );
		}
		check_admin_referer( 'ap_site_apply' );
		$which = isset( $_POST['which'] ) ? sanitize_key( wp_unslash( $_POST['which'] ) ) : '';
		$dry   = ! empty( $_POST['dry_run'] );
		$force = ! empty( $_POST['force_icon'] );
		$llms  = ! empty( $_POST['refresh_llms_txt'] );
		$result = ap_site_guard(
			static function () use ( $which, $dry, $force, $llms ) {
				if ( 'seo' === $which ) {
					return ap_site_apply_seo( $dry, $llms );
				}
				if ( 'identity' === $which ) {
					return ap_site_apply_identity( $dry, $force );
				}
				return new WP_Error( 'ap_site_unknown', 'Unknown action.' );
			},
			new WP_Error( 'ap_site_error', 'The action failed; see the PHP error log.' )
		);
		if ( is_wp_error( $result ) ) {
			$result = array(
				'ok'      => false,
				'summary' => $result->get_error_message(),
			);
		}
		set_transient( 'ap_site_apply_notice_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=asset-panda-site&applied=1' ) );
		exit;
	}
);

/* ── Page ─────────────────────────────────────────────────────────────── */

/**
 * Yes/no badge.
 *
 * @param bool $on State.
 * @return string
 */
function ap_site_badge( $on ) {
	return $on
		? '<span style="display:inline-block;padding:1px 8px;border-radius:9px;background:#d1fae5;color:#065f46;font-weight:600">yes</span>'
		: '<span style="display:inline-block;padding:1px 8px;border-radius:9px;background:#f3f4f6;color:#374151;font-weight:600">no</span>';
}

/** Renders Settings → Asset Panda Site. */
function ap_site_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$status = ap_site_guard( 'ap_site_status', array() );
	$s      = ap_site_settings();
	$f      = isset( $status['features'] ) ? $status['features'] : array();
	$name   = AP_SITE_OPTION;
	$result = get_transient( 'ap_site_apply_notice_' . get_current_user_id() );
	if ( false !== $result ) {
		delete_transient( 'ap_site_apply_notice_' . get_current_user_id() );
	}
	$rows = array(
		array( 'Google Tag Manager', 'tracking', 'Loader: ' . ( 'stape' === ap_site_effective_loader( $s ) ? 'Stape custom loader (' . $s['stape_domain'] . ')' : 'standard (googletagmanager.com)' ) . ' · container ' . $s['gtm_id'] . ' · not on ' . implode( ', ', $s['tracking_exclude'] ) ),
		array( 'Cookiebot consent banner', 'cookiebot', 'cbid ' . $s['cookiebot_id'] . ( $s['cookiebot_exclude'] ? ' · not on ' . implode( ', ', $s['cookiebot_exclude'] ) : ' · every page, as live' ) ),
		array( 'Geotargetly', 'geotargetly', 'Old theme\'s Core/International switch; the 2026 header does not need it.' ),
		array( 'Security headers', 'headers', 'CSP frame-ancestors, Permissions-Policy, Referrer-Policy, X-Frame-Options (not on ' . implode( ', ', $s['xfo_omit'] ) . '), nosniff, X-XSS-Protection' ),
		array( 'CSP report-only header', 'csp_report_only', 'Live sends one with no report address; it only produces console messages.' ),
		array( 'Forced HTTPS (301)', 'https_redirect', 'GET/HEAD over plain HTTP to the same URL on https.' ),
		array( 'Homepage Ursa demo frame', 'ursa_demo', ap_site_ursa_url( $s ) ),
	);
	?>
	<div class="wrap">
		<h1>Asset Panda Site</h1>
		<?php if ( is_array( $result ) ) : ?>
			<div class="notice <?php echo ! empty( $result['ok'] ) ? 'notice-success' : 'notice-error'; ?>">
				<p><strong><?php echo esc_html( isset( $result['action'] ) ? ucfirst( $result['action'] ) . ' apply' : 'Apply' ); ?>:</strong> <?php echo esc_html( isset( $result['summary'] ) ? $result['summary'] : '' ); ?></p>
				<pre style="max-height:320px;overflow:auto;white-space:pre-wrap"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			</div>
		<?php endif; ?>

		<h2>This host</h2>
		<table class="widefat striped" style="max-width:960px">
			<tbody>
				<tr><th style="width:240px">Request host</th><td><code><?php echo esc_html( isset( $status['host'] ) ? $status['host'] : '' ); ?></code></td></tr>
				<tr><th>Production</th><td><?php echo ap_site_badge( ! empty( $status['production'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( isset( $status['reason'] ) ? $status['reason'] : '' ); ?></td></tr>
				<tr><th>Production hosts</th><td><code><?php echo esc_html( implode( ', ', ap_site_production_hosts() ) ); ?></code> <span class="description">(from <?php echo esc_html( isset( $status['hosts_from'] ) ? $status['hosts_from'] : 'settings' ); ?>)</span></td></tr>
				<tr><th>Yoast SEO</th><td><?php echo esc_html( ap_site_yoast_ready() ? 'active, ' . ap_site_yoast_version() : 'not active' ); ?></td></tr>
			</tbody>
		</table>

		<h2>Features</h2>
		<p class="description">Everything below runs only on a production host. On this host a feature is "active" only when the host is production and its setting is on.</p>
		<table class="widefat striped" style="max-width:960px">
			<thead><tr><th>Feature</th><th>Setting</th><th>Active on this host</th><th>Details</th></tr></thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$state  = isset( $f[ $row[1] ] ) ? $f[ $row[1] ] : array();
					$on_set = ! empty( $state['setting'] );
					$active = ! empty( $state['active'] );
					?>
					<tr>
						<td><?php echo esc_html( $row[0] ); ?></td>
						<td><?php echo ap_site_badge( $on_set ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td><?php echo ap_site_badge( $active ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td><?php echo esc_html( $row[2] ); ?></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<td>Yoast sitemap exclusions</td>
					<td><?php echo ap_site_badge( ! empty( $s['sitemap_exclude'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td><?php echo ap_site_badge( ! empty( $s['sitemap_exclude'] ) && ap_site_yoast_ready() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td>Any host. Switched on by "Apply SEO settings". Matches <?php echo esc_html( (string) count( ap_site_guard( 'ap_site_sitemap_excluded_pages', array() ) ) ); ?> published pages now.</td>
				</tr>
				<tr>
					<td>User enumeration guard</td>
					<td><?php echo ap_site_badge( ! empty( $s['user_enumeration'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td><?php echo ap_site_badge( ap_site_user_guard_on() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td>Any host. Visitors get 401 from /wp/v2/users (also at ?rest_route=), 404 from ?author=N and author archives, and no author name or URL in oEmbed. Logged-in users keep all three.</td>
				</tr>
				<tr>
					<td>Emoji script off</td>
					<td><?php echo ap_site_badge( ! empty( $s['no_emoji_scripts'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td><?php echo ap_site_badge( ap_site_no_emoji_on() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td>Any host. WordPress's emoji detection script, its styles and the s.w.org prefetch are not printed on the pages (its blob Worker stalls the main thread ~400 ms in PageSpeed runs). Browsers render emoji from the system font; admin screens unchanged.</td>
				</tr>
				<tr>
					<td>Strict-Transport-Security</td>
					<td colspan="3">Not set here. It comes from a WP Engine Web Rule (Header Rules, "When: All responses"): <code>max-age=63072000; includeSubDomains; preload</code>, unquoted. Recreate it if www moves to another install.</td>
				</tr>
			</tbody>
		</table>

		<h2>Apply live's SEO and site identity</h2>
		<p class="description">One-time setup, safe to repeat: each run reports what changed and leaves matching values alone. Also available as <code>POST /wp-json/ap-site/v1/apply-seo-settings</code> and <code>POST /wp-json/ap-site/v1/apply-identity</code>.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:24px">
			<?php wp_nonce_field( 'ap_site_apply' ); ?>
			<input type="hidden" name="action" value="ap_site_apply" />
			<input type="hidden" name="which" value="seo" />
			<label><input type="checkbox" name="dry_run" value="1" /> Dry run</label>
			<label style="margin-left:12px"><input type="checkbox" name="refresh_llms_txt" value="1" /> Regenerate llms.txt</label>
			<?php submit_button( 'Apply SEO settings', 'primary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
			<?php wp_nonce_field( 'ap_site_apply' ); ?>
			<input type="hidden" name="action" value="ap_site_apply" />
			<input type="hidden" name="which" value="identity" />
			<label><input type="checkbox" name="dry_run" value="1" /> Dry run</label>
			<label style="margin-left:12px"><input type="checkbox" name="force_icon" value="1" /> Replace an existing site icon</label>
			<?php submit_button( 'Apply site identity', 'secondary', 'submit', false ); ?>
		</form>
		<?php
		$last = get_option( AP_SITE_LAST_APPLY, array() );
		if ( is_array( $last ) && $last ) :
			?>
			<p>
			<?php foreach ( $last as $action => $row ) : ?>
				<?php if ( is_array( $row ) ) : ?>
					Last <?php echo esc_html( $action ); ?> apply: <?php echo esc_html( ( isset( $row['time'] ) ? $row['time'] : '' ) . ' by ' . ( isset( $row['user'] ) ? $row['user'] : '' ) . ': ' . ( isset( $row['summary'] ) ? $row['summary'] : '' ) ); ?><br />
				<?php endif; ?>
			<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<h2>Settings</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'ap_site' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[_form]" value="1" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ap-site-hosts">Production hosts</label></th>
					<td>
						<textarea id="ap-site-hosts" name="<?php echo esc_attr( $name ); ?>[production_hosts]" rows="3" cols="50" class="code"><?php echo esc_textarea( implode( "\n", $s['production_hosts'] ) ); ?></textarea>
						<p class="description">One per line. Staging, *.wpenginepowered.com, *.wpengine.com, localhost and IP addresses are always refused. AP_SITE_PRODUCTION_HOSTS in wp-config.php overrides this list.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Google Tag Manager</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[tracking]" value="1" <?php checked( $s['tracking'] ); ?> /> Load the container</label><br />
						<label>Container ID <input type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[gtm_id]" value="<?php echo esc_attr( $s['gtm_id'] ); ?>" /></label><br />
						<fieldset style="margin-top:8px">
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[loader]" value="gtm" <?php checked( 'gtm', $s['loader'] ); ?> /> Standard loader from www.googletagmanager.com (what live uses today)</label><br />
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[loader]" value="stape" <?php checked( 'stape', $s['loader'] ); ?> /> Stape custom loader from the first-party domain below (not blocked by ad blockers)</label>
						</fieldset>
						<p>
							<label>Stape domain <input type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[stape_domain]" value="<?php echo esc_attr( $s['stape_domain'] ); ?>" /></label><br />
							<label>Stape loader file <input type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[stape_loader]" value="<?php echo esc_attr( $s['stape_loader'] ); ?>" /></label><br />
							<label>Stape loader parameter <input type="text" class="large-text code" name="<?php echo esc_attr( $name ); ?>[stape_query]" value="<?php echo esc_attr( $s['stape_query'] ); ?>" /></label>
						</p>
						<p class="description">Copy the Stape values from Stape → Power-Ups → Custom Loader → Generate (platform "Other"); do not retype the token. After switching, purge the WP Engine cache and check the page source. AP_SITE_GTM_LOADER ('gtm' or 'stape') in wp-config.php overrides this choice.</p>
						<label>No container on these paths<br /><textarea name="<?php echo esc_attr( $name ); ?>[tracking_exclude]" rows="3" cols="50" class="code"><?php echo esc_textarea( implode( "\n", $s['tracking_exclude'] ) ); ?></textarea></label>
					</td>
				</tr>
				<tr>
					<th scope="row">Cookiebot</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[cookiebot]" value="1" <?php checked( $s['cookiebot'] ); ?> /> Load the consent banner</label><br />
						<label>Cookiebot ID <input type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[cookiebot_id]" value="<?php echo esc_attr( $s['cookiebot_id'] ); ?>" /></label><br />
						<label>No banner on these paths<br /><textarea name="<?php echo esc_attr( $name ); ?>[cookiebot_exclude]" rows="2" cols="50" class="code"><?php echo esc_textarea( implode( "\n", $s['cookiebot_exclude'] ) ); ?></textarea></label>
						<p class="description">Live loads Cookiebot on every page, the login splash pages included.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Geotargetly</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[geotargetly]" value="1" <?php checked( $s['geotargetly'] ); ?> /> Load Geotargetly (live loads it before consent for the old header; not needed by the 2026 header)</label></td>
				</tr>
				<tr>
					<th scope="row">Security headers</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[headers]" value="1" <?php checked( $s['headers'] ); ?> /> Send live's security headers</label><br />
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[csp_report_only]" value="1" <?php checked( $s['csp_report_only'] ); ?> /> Also send live's Content-Security-Policy-Report-Only header</label><br />
						<label>No X-Frame-Options on these paths<br /><textarea name="<?php echo esc_attr( $name ); ?>[xfo_omit]" rows="2" cols="50" class="code"><?php echo esc_textarea( implode( "\n", $s['xfo_omit'] ) ); ?></textarea></label>
						<p class="description">The login splash pages are framed by login/stage/prelive/admin.assetpanda.com. CSP frame-ancestors allows exactly those hosts on every path; X-Frame-Options is left off there so it can never block that frame. Empty this list to send X-Frame-Options everywhere, as live does.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">HTTPS</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[https_redirect]" value="1" <?php checked( $s['https_redirect'] ); ?> /> Redirect plain-HTTP page requests to https (301)</label></td>
				</tr>
				<tr>
					<th scope="row">Homepage demo</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[ursa_demo]" value="1" <?php checked( $s['ursa_demo'] ); ?> /> Frame the live Ursa demo in the homepage hero (instead of the posters)</label><br />
						<label>Demo URL <input type="url" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[ursa_demo_url]" value="<?php echo esc_attr( $s['ursa_demo_url'] ); ?>" /></label>
					</td>
				</tr>
				<tr>
					<th scope="row">User enumeration</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[user_enumeration]" value="1" <?php checked( $s['user_enumeration'] ); ?> /> Refuse account listings to visitors (REST users routes, ?author=N, oEmbed author fields)</label></td>
				</tr>
				<tr>
					<th scope="row">Emoji script</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[no_emoji_scripts]" value="1" <?php checked( $s['no_emoji_scripts'] ); ?> /> Keep WordPress's emoji detection script off the pages (PageSpeed: removes a ~400 ms main-thread stall; emoji still render from the system font)</label></td>
				</tr>
				<tr>
					<th scope="row">Yoast sitemap</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[sitemap_exclude]" value="1" <?php checked( $s['sitemap_exclude'] ); ?> /> Keep these pages out of the XML sitemap</label><br />
						<textarea name="<?php echo esc_attr( $name ); ?>[sitemap_exclude_patterns]" rows="8" cols="50" class="code"><?php echo esc_textarea( implode( "\n", $s['sitemap_exclude_patterns'] ) ); ?></textarea>
						<p class="description">Page paths; * matches anything; trailing slash optional. /products matches /products/ only.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2>Exact output on a production host</h2>
		<p class="description">What the page source of www.assetpanda.com carries with the settings above. Nothing of this is printed on this host unless it is production.</p>
		<h3>&lt;head&gt;</h3>
		<pre style="background:#f6f7f7;padding:12px;overflow:auto"><?php echo esc_html( ap_site_gtm_head_html( $s ) ); ?></pre>
		<h3>After &lt;body&gt;</h3>
		<pre style="background:#f6f7f7;padding:12px;overflow:auto"><?php echo esc_html( ap_site_gtm_noscript_html( $s ) ); ?></pre>
		<h3>Before &lt;/body&gt;</h3>
		<pre style="background:#f6f7f7;padding:12px;overflow:auto"><?php echo esc_html( ap_site_cookiebot_html( $s ) . ( $s['geotargetly'] ? ap_site_geotargetly_html() : '' ) ); ?></pre>
		<h3>Response headers (page /, then /login-splash-1/)</h3>
		<pre style="background:#f6f7f7;padding:12px;overflow:auto">
<?php
foreach ( array( '/', '/login-splash-1/' ) as $path ) {
	echo esc_html( $path ) . "\n";
	foreach ( ap_site_security_headers( $path, $s ) as $h => $v ) {
		echo esc_html( '  ' . $h . ': ' . $v ) . "\n";
	}
}
?>
</pre>
	</div>
	<?php
}
