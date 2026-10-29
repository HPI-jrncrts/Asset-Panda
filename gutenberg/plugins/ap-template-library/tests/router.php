<?php
/**
 * TEST ONLY — router for `php -S`: static files as they are, everything else
 * through WordPress's index.php.
 *
 * @package APTemplateLibrary
 */

$ap_root = $_SERVER['DOCUMENT_ROOT'];
$ap_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( '/' !== $ap_path && is_file( $ap_root . $ap_path ) && ! preg_match( '/\.php$/', $ap_path ) ) {
	return false;
}
if ( preg_match( '#^/(wp-admin|wp-login\.php|wp-cron\.php)#', (string) $ap_path ) && is_file( $ap_root . $ap_path ) ) {
	require $ap_root . $ap_path;
	return true;
}
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $ap_root . '/index.php';
chdir( $ap_root );
require $ap_root . '/index.php';
