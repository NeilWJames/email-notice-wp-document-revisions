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
