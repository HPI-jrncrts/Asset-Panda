<?php
/**
 * TEST ONLY: a stand-in for Yoast SEO's WPSEO_Options with the behaviour the
 * plugin relies on, modelled on Yoast 28.7 (inc/options/class-wpseo-options.php
 * and the option classes):
 * - get() reads a per-request cache primed from the stored option groups merged
 *   over their defaults; clear_cache() empties it;
 * - set() finds the group holding the key (or a key pattern such as title- /
 *   noindex-), merges, validates and update_option()s the whole group, and
 *   returns whether the stored value equals the requested one; an unknown key
 *   only lands in the in-memory cache and returns null;
 * - validation keeps only known keys (per-post-type and per-taxonomy keys exist
 *   only for registered public types), casts booleans, strips @ / URL from
 *   twitter_site and sanitises text and URLs.
 *
 * @package AssetPandaSite
 */

if ( class_exists( 'WPSEO_Options' ) ) {
	return;
}

if ( ! defined( 'WPSEO_VERSION' ) ) {
	define( 'WPSEO_VERSION', '28.7-test-stub' );
}

// phpcs:disable
class WPSEO_Options {

	public static $options = array(
		'wpseo'        => true,
		'wpseo_titles' => true,
		'wpseo_social' => true,
	);

	protected static $option_values = null;

	public static function defaults( $group ) {
		switch ( $group ) {
			case 'wpseo':
				return array(
					'googleverify'       => '',
					'msverify'           => '',
					'enable_llms_txt'    => false,
					'enable_xml_sitemap' => true,
				);
			case 'wpseo_social':
				return array(
					'facebook_site'     => '',
					'twitter_site'      => '',
					'other_social_urls' => array(),
					'opengraph'         => true,
					'twitter'           => true,
					'og_default_image'  => '',
				);
			case 'wpseo_titles':
				$d = array(
					'separator'          => 'sc-dash',
					'company_or_person'  => 'company',
					'company_name'       => '',
					'company_logo'       => '',
					'company_logo_id'    => 0,
					'company_logo_meta'  => false,
					'disable-author'     => false,
					'title-search-wpseo' => '',
				);
				foreach ( get_post_types( array( 'public' => true ) ) as $pt ) {
					$d[ 'title-' . $pt ]   = '%%title%% %%page%% %%sep%% %%sitename%%';
					$d[ 'noindex-' . $pt ] = false;
				}
				foreach ( get_taxonomies( array( 'public' => true ) ) as $tax ) {
					$d[ 'title-tax-' . $tax ]   = '%%term_title%% Archives %%page%% %%sep%% %%sitename%%';
					$d[ 'noindex-tax-' . $tax ] = false;
				}
				return $d;
		}
		return array();
	}

	public static function get_option( $group ) {
		$stored = get_option( $group );
		return array_merge( self::defaults( $group ), is_array( $stored ) ? array_intersect_key( $stored, self::defaults( $group ) ) : array() );
	}

	public static function get_all() {
		$all = array();
		foreach ( array_keys( self::$options ) as $group ) {
			$all = array_merge( $all, self::get_option( $group ) );
		}
		return $all;
	}

	public static function get( $key, $default_value = null ) {
		if ( ! isset( self::$option_values[ $key ] ) ) {
			self::$option_values = self::get_all();
		}
		return isset( self::$option_values[ $key ] ) ? self::$option_values[ $key ] : $default_value;
	}

	public static function clear_cache() {
		self::$option_values = null;
	}

	public static function set( $key, $value ) {
		foreach ( array_keys( self::$options ) as $group ) {
			if ( array_key_exists( $key, self::get_option( $group ) ) ) {
				return self::save_option( $group, $key, $value );
			}
		}
		foreach ( array( 'title-', 'noindex-', 'metadesc-' ) as $pattern ) {
			if ( 0 === strpos( $key, $pattern ) ) {
				return self::save_option( 'wpseo_titles', $key, $value );
			}
		}
		self::$option_values[ $key ] = $value;
		return null;
	}

	public static function save_option( $group, $key, $value ) {
		$options         = self::get_option( $group );
		$options[ $key ] = $value;
		update_option( $group, self::validate( $group, $options ) );
		$saved = self::get_option( $group );
		self::clear_cache();
		return isset( $saved[ $key ] ) && $saved[ $key ] === $options[ $key ];
	}

	protected static function validate( $group, array $dirty ) {
		$clean = array();
		foreach ( self::defaults( $group ) as $k => $default ) {
			if ( ! array_key_exists( $k, $dirty ) ) {
				$clean[ $k ] = $default;
				continue;
			}
			$v = $dirty[ $k ];
			if ( 'company_logo_meta' === $k ) {
				// Yoast: "Only ever set programmatically, so no reason for intense validation."
				$v = is_array( $v ) ? $v : false;
			} elseif ( is_bool( $default ) ) {
				$v = (bool) filter_var( $v, FILTER_VALIDATE_BOOLEAN );
			} elseif ( 'twitter_site' === $k ) {
				$v = preg_replace( '#^(?:https?://(?:www\.)?(?:twitter|x)\.com/|@)#i', '', trim( (string) $v ) );
			} elseif ( 'other_social_urls' === $k ) {
				$v = array_values( array_filter( array_map( 'esc_url_raw', (array) $v ) ) );
			} elseif ( 'company_logo_id' === $k ) {
				$v = (int) $v;
			} elseif ( 'company_logo' === $k || 'facebook_site' === $k ) {
				$v = esc_url_raw( (string) $v );
			} elseif ( is_string( $default ) ) {
				$v = sanitize_text_field( (string) $v );
			}
			$clean[ $k ] = $v;
		}
		return $clean;
	}
}
// phpcs:enable
