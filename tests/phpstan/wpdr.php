<?php
/**
 * PHPStan: where WP Document Revisions is, as this plugin uses its classes and functions.
 *
 * Found as tests/bootstrap.php finds it: the WPDR_PLUGIN_DIR environment variable, otherwise the
 * sibling folder ../wp-document-revisions. PHPStan only reads that code; it is not analysed.
 *
 * @package Email Notice WP Document Revisions
 */

$wpdr_en_wpdr_dir = getenv( 'WPDR_PLUGIN_DIR' );
if ( ! $wpdr_en_wpdr_dir ) {
	$wpdr_en_wpdr_dir = dirname( __DIR__, 3 ) . '/wp-document-revisions';
}

return array(
	'parameters' => array(
		'scanFiles'       => array( $wpdr_en_wpdr_dir . '/wp-document-revisions.php' ),
		'scanDirectories' => array( $wpdr_en_wpdr_dir . '/includes' ),
	),
);
