<?php
/**
 * Plugin Name:       AP Duplicate Check
 * Description:       Warns at the top of the editor when a page, post, resource or press release that is not live yet covers the same topic as one that already is (a rewrite of an old post, the same comparison guide under a new title), with a link to the live page. Matches on topic, not word for word: the page's distinctive words, its title and headings, its address and its Yoast focus keyphrase. Copies made with a Duplicate action are not flagged against the page they were copied from. A draft linked to its live page (a Duplicate copy, or one linked from the warning) can send the sections you pick to that page — replace, add or remove each, plus title, SEO fields and featured image — with a preview first and Undo after. Editor only: nothing loads on a visitor's page.
 * Version:           1.1.2
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Asset Panda
 * License:           GPL-2.0-or-later
 * Text Domain:       ap-duplicate-check
 *
 * @package APDuplicateCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AP_DUPCHECK_VERSION', '1.1.2' );
define( 'AP_DUPCHECK_FILE', __FILE__ );
define( 'AP_DUPCHECK_DIR', __DIR__ );
/** Bump when ap_dupcheck_terms() changes so stored term records are rebuilt. */
define( 'AP_DUPCHECK_ALGO', 2 );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

foreach ( array( 'text', 'index', 'copies', 'rest', 'push', 'editor' ) as $ap_dupcheck_include ) {
	require_once AP_DUPCHECK_DIR . '/includes/' . $ap_dupcheck_include . '.php';
}
unset( $ap_dupcheck_include );

register_activation_hook( __FILE__, 'ap_dupcheck_schedule_backfill' );
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'ap_dupcheck_backfill' );
	}
);
