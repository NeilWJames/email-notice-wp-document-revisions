<?php
/**
 * PHPUnit bootstrap for Email Notice for WP Document Revisions.
 *
 * Loads the WordPress test library, then WP Document Revisions (required parent plugin)
 * and this plugin, in an admin context because this plugin only runs in the admin.
 *
 * Environment variables:
 * - WP_TESTS_DIR     WordPress test library (default: <temp dir>/wordpress-tests-lib).
 * - WPDR_PLUGIN_DIR  WP Document Revisions plugin folder (default: sibling folder ../wp-document-revisions).
 *
 * @package Email Notice WP Document Revisions
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "WordPress test library not found in $_tests_dir. Run script/install-wp-tests first.\n" );
	exit( 1 );
}

$_wpdr_dir = getenv( 'WPDR_PLUGIN_DIR' );
if ( ! $_wpdr_dir ) {
	$_wpdr_dir = dirname( __DIR__, 2 ) . '/wp-document-revisions';
}
if ( ! file_exists( $_wpdr_dir . '/wp-document-revisions.php' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "WP Document Revisions not found in $_wpdr_dir. Set WPDR_PLUGIN_DIR.\n" );
	exit( 1 );
}
define( 'WPDR_EN_TESTS_WPDR_DIR', $_wpdr_dir );

// The WordPress test library looks here for the PHPUnit Polyfills.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load WP Document Revisions and this plugin as if in the admin.
 *
 * The plugin checks is_admin() and the active_plugins list when its main file is loaded,
 * so give it an admin screen and report WP Document Revisions as active.
 */
function wpdr_en_tests_load_plugins() {
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	$GLOBALS['current_screen'] = WP_Screen::get( 'dashboard' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	add_filter(
		'active_plugins',
		static function ( $plugins ) {
			$plugins   = (array) $plugins;
			$plugins[] = 'wp-document-revisions/wp-document-revisions.php';
			return $plugins;
		}
	);

	require WPDR_EN_TESTS_WPDR_DIR . '/wp-document-revisions.php';
	require dirname( __DIR__ ) . '/wpdr-email-notice.php';
}
tests_add_filter( 'muplugins_loaded', 'wpdr_en_tests_load_plugins' );

require $_tests_dir . '/includes/bootstrap.php';

// Admin functions (settings API, list tables) used by the plugin.
require_once ABSPATH . 'wp-admin/includes/admin.php';

/**
 * One-off setup that a real site gets from activation and admin_init.
 */
function wpdr_en_tests_setup() {
	global $wpdr, $wpdr_en;

	// WP Document Revisions role capabilities.
	$wpdr->add_caps();

	// Tables and the doc_ext_list capabilities (installed only for a user who can activate plugins).
	wp_set_current_user( 1 );
	delete_option( 'wpdr_en_db_version' );
	$wpdr_en->db_version_check();
	wp_set_current_user( 0 );

	// WP_Roles::add_cap() updates the stored roles but not the WP_Role objects already loaded
	// (a real site reloads them on the next request), so reload them before any test user is created.
	wp_roles()->for_site();

	// Settings, default templates and the AJAX/save hooks are set up on admin_init.
	$wpdr_en->admin_init();
}
wpdr_en_tests_setup();

require_once __DIR__ . '/trait-wpdr-en-fixtures.php';
require_once __DIR__ . '/class-wpdr-en-unittestcase.php';
require_once __DIR__ . '/class-wpdr-en-ajax-unittestcase.php';
