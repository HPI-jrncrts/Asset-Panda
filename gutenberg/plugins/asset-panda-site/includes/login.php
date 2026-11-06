<?php
/**
 * Sign-in protection for wp-login.php (1.3.0, go-live prep 2026-10-30: "Let's make sure the wp-admin login is
 * bruteforce resistant and exploit resistant ... Add maybe a v3 recaptcha on wp-admin? No interaction needed").
 *
 * - Lockout (any host, setting "login_guard"): after `login_limit` failed sign-ins from one address within
 *   `login_window` minutes, that address cannot sign in (even with the right password) until the window has
 *   passed. A successful sign-in clears its count.
 * - One message for every wrong username, email or password, so the form never tells which accounts exist.
 * - XML-RPC off (its system.multicall is the classic way to try hundreds of passwords in one request).
 * - Google reCAPTCHA v3 (setting "recaptcha"): invisible. The sign-in and lost-password forms ask Google for a
 *   token on submit; WordPress checks it with Google and refuses the attempt when Google is not confident a person
 *   sent it (score under `recaptcha_threshold`). Runs only when both keys are saved, and only on the production
 *   hosts plus any host listed in `recaptcha_hosts` (the key's domain list must include each host, or Google
 *   refuses its tokens). If WordPress cannot reach Google at all, the attempt is let through and logged, so an
 *   outage at Google never locks the team out; the lockout above still applies.
 *
 * Escape hatches if a sign-in problem ever locks everyone out: define( 'AP_SITE_DISABLE', true ) in wp-config.php
 * (stops this plugin's front-end features and these checks), or deactivate the plugin from the WP Engine portal.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True when the lockout, generic errors and XML-RPC switch apply. */
function ap_site_login_guard_on() {
	if ( defined( 'AP_SITE_DISABLE' ) && AP_SITE_DISABLE ) {
		return false;
	}
	return (bool) ap_site_setting( 'login_guard' ) && (bool) apply_filters( 'ap_site_feature_login_guard', true );
}

/** True when reCAPTCHA is checked on this request: setting on, both keys saved, an allowed host. */
function ap_site_recaptcha_on() {
	if ( defined( 'AP_SITE_DISABLE' ) && AP_SITE_DISABLE ) {
		return false;
	}
	$s = ap_site_settings();
	if ( empty( $s['recaptcha'] ) || '' === $s['recaptcha_site_key'] || '' === $s['recaptcha_secret'] ) {
		return false;
	}
	$host = ap_site_request_host();
	$ok   = ap_site_is_production() || in_array( $host, (array) $s['recaptcha_hosts'], true );
	return $ok && (bool) apply_filters( 'ap_site_feature_recaptcha', true );
}

/** The visitor's address as WP Engine passes it (it sets REMOTE_ADDR to the client behind its proxy). */
function ap_site_login_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/** Transient key holding an address's failed sign-in count. */
function ap_site_login_key( $ip ) {
	return 'ap_site_lf_' . md5( $ip );
}

/** Minutes of the counting and lockout window. */
function ap_site_login_window() {
	return max( 1, min( 1440, (int) ap_site_setting( 'login_window' ) ) );
}

/** Failed sign-ins allowed per window. */
function ap_site_login_limit() {
	return max( 3, min( 50, (int) ap_site_setting( 'login_limit' ) ) );
}

/** True when the address has used up its attempts. */
function ap_site_login_locked( $ip ) {
	return (int) get_transient( ap_site_login_key( $ip ) ) >= ap_site_login_limit();
}

add_action(
	'wp_login_failed',
	function () {
		ap_site_guard(
			function () {
				if ( ! ap_site_login_guard_on() ) {
					return;
				}
				$key = ap_site_login_key( ap_site_login_ip() );
				set_transient( $key, (int) get_transient( $key ) + 1, ap_site_login_window() * MINUTE_IN_SECONDS );
			}
		);
	}
);

add_action(
	'wp_login',
	function () {
		ap_site_guard(
			function () {
				delete_transient( ap_site_login_key( ap_site_login_ip() ) );
			}
		);
	}
);

/**
 * Last word on every sign-in attempt (after WordPress has checked the password): a locked-out address or a failed
 * reCAPTCHA check refuses the attempt even with the right password.
 */
add_filter(
	'authenticate',
	function ( $user, $username ) {
		return ap_site_guard(
			function () use ( $user, $username ) {
				if ( '' === (string) $username && ! ( $user instanceof WP_User ) ) {
					return $user; // The empty form on first load: nothing was attempted.
				}
				if ( ap_site_login_guard_on() && ap_site_login_locked( ap_site_login_ip() ) ) {
					return new WP_Error( 'ap_site_locked', sprintf( 'Too many failed sign-in attempts. Please try again in %d minutes.', ap_site_login_window() ) );
				}
				if ( ap_site_recaptcha_on() ) {
					$check = ap_site_recaptcha_verify( 'login' );
					if ( is_wp_error( $check ) ) {
						return $check;
					}
				}
				return $user;
			},
			$user
		);
	},
	99,
	2
);

/** The lost-password form gets the same reCAPTCHA check (it can be used to spam reset emails). */
add_action(
	'lostpassword_post',
	function ( $errors ) {
		ap_site_guard(
			function () use ( $errors ) {
				if ( ap_site_recaptcha_on() && $errors instanceof WP_Error ) {
					$check = ap_site_recaptcha_verify( 'lostpassword' );
					if ( is_wp_error( $check ) ) {
						$errors->add( $check->get_error_code(), $check->get_error_message() );
					}
				}
			}
		);
	}
);

/** One message for a wrong username, email or password. */
add_filter(
	'login_errors',
	function ( $message ) {
		return ap_site_guard(
			function () use ( $message ) {
				global $errors;
				if ( ! ap_site_login_guard_on() || ! ( $errors instanceof WP_Error ) ) {
					return $message;
				}
				$codes = $errors->get_error_codes();
				if ( array_intersect( $codes, array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
					return '<strong>Error:</strong> The username, email address or password is incorrect.';
				}
				return $message;
			},
			$message
		);
	}
);

add_filter(
	'xmlrpc_enabled',
	function ( $enabled ) {
		return ap_site_login_guard_on() ? false : $enabled;
	}
);

/**
 * Checks the token the form sent with Google.
 *
 * @param string $action The action the page asked the token for (login, lostpassword).
 * @return true|WP_Error
 */
function ap_site_recaptcha_verify( $action ) {
	$s     = ap_site_settings();
	$token = isset( $_POST['ap_recaptcha'] ) ? sanitize_text_field( wp_unslash( $_POST['ap_recaptcha'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the token is the check.
	$fail  = new WP_Error( 'ap_site_recaptcha', 'We could not confirm this sign-in came from a person. Please wait a moment and try again.' );
	if ( '' === $token ) {
		return $fail;
	}
	$response = wp_remote_post(
		'https://www.google.com/recaptcha/api/siteverify',
		array(
			'timeout' => 6,
			'body'    => array(
				'secret'   => $s['recaptcha_secret'],
				'response' => $token,
				'remoteip' => ap_site_login_ip(),
			),
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		ap_site_log( 'reCAPTCHA: Google could not be reached; the sign-in was let through. ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ) ) );
		return true;
	}
	return ap_site_recaptcha_judge( json_decode( wp_remote_retrieve_body( $response ), true ), $action, (float) $s['recaptcha_threshold'] ) ? true : $fail;
}

/**
 * Google's answer, judged: a valid token, for this action, with a score at or above the threshold.
 *
 * @param mixed  $result    Decoded siteverify response.
 * @param string $action    Expected action.
 * @param float  $threshold Lowest score accepted.
 * @return bool
 */
function ap_site_recaptcha_judge( $result, $action, $threshold ) {
	if ( ! is_array( $result ) || empty( $result['success'] ) ) {
		if ( is_array( $result ) && ! empty( $result['error-codes'] ) ) {
			ap_site_log( 'reCAPTCHA refused a token: ' . implode( ', ', (array) $result['error-codes'] ) );
		}
		return false;
	}
	if ( isset( $result['action'] ) && $result['action'] !== $action ) {
		return false;
	}
	return isset( $result['score'] ) && (float) $result['score'] >= $threshold;
}

/**
 * The sign-in page loads Google's script and asks for a token when a form is sent. If the script cannot load
 * (blocked, offline), the form is sent after a short wait without one and WordPress refuses it with a message.
 */
add_action(
	'login_enqueue_scripts',
	function () {
		ap_site_guard(
			function () {
				if ( ! ap_site_recaptcha_on() ) {
					return;
				}
				$key = ap_site_setting( 'recaptcha_site_key' );
				wp_enqueue_script( 'ap-site-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $key ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				$js = '(function(k){function hook(id,action){var f=document.getElementById(id);if(!f)return;var sent=false;f.addEventListener("submit",function(e){if(sent)return;e.preventDefault();var i=f.querySelector("input[name=ap_recaptcha]");if(!i){i=document.createElement("input");i.type="hidden";i.name="ap_recaptcha";f.appendChild(i);}var go=function(){if(sent)return;sent=true;f.submit();};var t=setTimeout(go,5000);try{grecaptcha.ready(function(){grecaptcha.execute(k,{action:action}).then(function(tok){i.value=tok;clearTimeout(t);go();},function(){clearTimeout(t);go();});});}catch(x){clearTimeout(t);go();}});}document.addEventListener("DOMContentLoaded",function(){hook("loginform","login");hook("lostpasswordform","lostpassword");});})(' . wp_json_encode( $key ) . ');';
				wp_add_inline_script( 'ap-site-recaptcha', $js );
			}
		);
	}
);
