<?php
/**
 * Plugin Name:       Email Notice for WP Document Revisions
 * Plugin URI:        http://github.com/NeilWJames/email-notice-wp-document-revisions
 * Description:       Add-on plugin to WP Document Revisions to notify users about new documents published.
 * Version:           4.0.0
 * Author:            Neil James
 * Author URI:        http://github.com/NeilWJames
 * License:           GPLv3 or later
 * Requires at least: 5.9
 * Requires PHP:      8.0
 * Text Domain:       email-notice-wp-document-revisions
 * Domain Path:       /languages
 *
 * @package Email Notice WP Document Revisions
 */

// No direct access allowed to plugin php file.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this file directly.' );
}

/**
 * Whether WP Document Revisions is active on this site or for the whole network.
 *
 * Plugins load in name order, so this one loads before WP Document Revisions and its class
 * cannot be checked for yet.
 *
 * @since 4.0.0
 * @return bool
 */
function wpdr_en_wpdr_active() {
	$wpdr = 'wp-document-revisions/wp-document-revisions.php';
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	if ( in_array( $wpdr, (array) apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
		return true;
	}
	return is_multisite() && array_key_exists( $wpdr, (array) get_site_option( 'active_sitewide_plugins', array() ) );
}

/**
 * Admin notice shown when WP Document Revisions is not active.
 *
 * @since 4.0.0
 * @return void
 */
function wpdr_en_missing_wpdr_notice() {
	echo '<div class="notice notice-warning is-dismissible"><p>';
	// translators: Do not translate Email Notice WP Document Revisions or WP Document Revisions.
	esc_html_e( 'Plugin Email Notice WP Document Revisions is activated but its required plugin WP Document Revisions is not.', 'email-notice-wp-document-revisions' );
	echo '</p><p>';
	// translators: Do not translate Email Notice WP Document Revisions.
	esc_html_e( 'Plugin Email Notice WP Document Revisions will not activate its functionality.', 'email-notice-wp-document-revisions' );
	echo '</p></div>';
}

/**
 * Deactivation: stop the daily log clean-up.
 *
 * @since 4.0.0
 * @return void
 */
function wpdr_en_deactivate() {
	wp_clear_scheduled_hook( 'wpdr_en_purge_logs' );
}

// Check that WP Document Revisions is active (the notice is shown with the other admin notices).
if ( ! wpdr_en_wpdr_active() ) {
	if ( is_admin() ) {
		add_action( 'admin_notices', 'wpdr_en_missing_wpdr_notice' );
	}
	return;
}

/**
 * Email Notice WP Document Revisions.
 */

// Only use when Admin (Load when needed), or for WP-Cron (daily log clean-up).
if ( is_admin() || wp_doing_cron() ) {
	global $wpdr_en;
	require_once __DIR__ . '/includes/class-wpdr-email-notice.php';
	$wpdr_en = new WPDR_Email_Notice();

	// Install tables and capabilities when plugin activated.
	register_activation_hook( __FILE__, array( $wpdr_en, 'db_version_check' ) );

	// Stop the daily log clean-up when the plugin is deactivated (admin_init schedules it again).
	register_deactivation_hook( __FILE__, 'wpdr_en_deactivate' );

	// Bulk subscribe/unsubscribe users.
	if ( ! class_exists( 'WPDR_EN_All_Users_Bulk_Action' ) ) {
		include_once __DIR__ . '/includes/class-wpdr-en-all-users-bulk-action.php';
		new WPDR_EN_All_Users_Bulk_Action();
	}
}
