<?php
/**
 * Plugin Name: WP Document Revisions Email Notice
 * Plugin URI: http://github.com/NeilWJames/wp-document-revisions-email-notice
 * Description: Notify users about new documents published and customize your e-mail notification settings
 * Version: 1.0
 * Author: Neil James based on Janos Ver
 * Author URI: http://github.com/NeilWJames
 * License: GPLv3 or later
 *
 * @package WP Document Revisions Email Notice
 */

// No direct access allowed to plugin php file.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

// Check that WP Document Revisions is active.
if ( ! in_array( 'wp-document-revisions/wp-document-revisions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	if ( is_admin() ) {
		echo wp_kses_post( '<div class="notice notice-warning is-dismissible"><p>' );
		// translators: Do not translate WP Document Revisions Email Notice or WP Document Revisions.
		esc_html_e( 'Plugin WP Document Revisions Email Notice is activated but its required plugin WP Document Revisions is not.', 'wpdr-email-notice' );
		echo wp_kses_post( '</p><p>' );
		// translators: Do not translate WP Document Revisions Email Notice.
		esc_html_e( 'Plugin WP Document Revisions Email Notice will not activate its functionality.', 'wpdr-email-notice' );
		echo wp_kses_post( '</p></div>' );
	}
	return;
}

/**
 * WP Document Revisions Email Notice.
 */
add_action( 'plugins_loaded', 'init_wpdr_en' );

/**
 * Initialise classes.
 *
 * @since 1.0
 */
function init_wpdr_en() {
	// Admin (Load when needed).
	if ( is_admin() ) {
		require_once __DIR__ . '/includes/class-wpdr-email-notice.php';
		$wpdr_en = new WPDR_Email_Notice();

		// Bulk subscribe/unsubscribe users.
		if ( ! class_exists( 'WPDR_EN_All_Users_Bulk_Action' ) ) {
			include_once __DIR__ . '/includes/class-wpdr-en-all-users-bulk-action.php';
			new WPDR_EN_All_Users_Bulk_Action();
		}
	}
}
