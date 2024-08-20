<?php
/**
 * Plugin Name:       Email Notice for WP Document Revisions
 * Plugin URI:        http://github.com/NeilWJames/email-notice-wp-document-revisions
 * Description:       Add-on plugin to WP Document Revisions to notify users about new documents published.
 * Version:           3.0
 * Author:            Neil James
 * Author URI:        http://github.com/NeilWJames
 * License:           GPLv3 or later
 * Requires at least: 4.9
 * Requires PHP:      7.4
 * Text Domain:       wpdr-email-notice
 * Domain Path:       /languages
 *
 * @package Email Notice WP Document Revisions
 */

// No direct access allowed to plugin php file.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this file directly.' );
}

// Check that WP Document Revisions is active.
if ( ! in_array( 'wp-document-revisions/wp-document-revisions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	if ( is_admin() ) {
		echo wp_kses_post( '<div class="notice notice-warning is-dismissible"><p>' );
		// translators: Do not translate Email Notice WP Document Revisions or WP Document Revisions.
		esc_html_e( 'Plugin Email Notice WP Document Revisions is activated but its required plugin WP Document Revisions is not.', 'wpdr-email-notice' );
		echo wp_kses_post( '</p><p>' );
		// translators: Do not translate Email Notice WP Document Revisions.
		esc_html_e( 'Plugin Email Notice WP Document Revisions will not activate its functionality.', 'wpdr-email-notice' );
		echo wp_kses_post( '</p></div>' );
	}
	return;
}

/**
 * Email Notice WP Document Revisions.
 */

// Only use when Admin (Load when needed).
if ( is_admin() ) {
	global $wpdr_en;
	require_once __DIR__ . '/includes/class-wpdr-email-notice.php';
	$wpdr_en = new WPDR_Email_Notice();

	// Install table when plugin activated.
	register_activation_hook( __FILE__, array( $wpdr_en, 'install_notification_log' ) );
	register_activation_hook( __FILE__, array( $wpdr_en, 'install_capabilities' ) );

	// Bulk subscribe/unsubscribe users.
	if ( ! class_exists( 'WPDR_EN_All_Users_Bulk_Action' ) ) {
		include_once __DIR__ . '/includes/class-wpdr-en-all-users-bulk-action.php';
		new WPDR_EN_All_Users_Bulk_Action();
	}
}
