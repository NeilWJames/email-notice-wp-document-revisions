<?php
/**
 * Remove settings on plugin delete.
 *
 * WPDR Custom Email Settings Uninstaller
 *
 * @version 1.0
 * @global wpdb $wpdb WordPress database abstraction object.
 * @global WP_Role[] $wp_roles WP_Roles array object.
 *
 * @package Email Notice WP Document Revisions
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

global $wpdb;

/**
 * Remove individual settings on plugin delete.
 *
 * @version 2.0
 */
function wpdr_en_del_options() {
	global $wpdb;
	// wpdr_en_general_settings.
	delete_option( 'wpdr_en_db_version' );
	delete_option( 'wpdr_en_set_email_from' );
	delete_option( 'wpdr_en_set_email_from_address' );

	// wpdr_en_writing_settings.
	delete_option( 'wpdr_en_set_notification_mode' );
	delete_option( 'wpdr_en_set_notification_about' );
	delete_option( 'wpdr_en_set_subject' );
	delete_option( 'wpdr_en_set_content' );
	delete_option( 'wpdr_en_set_exttext' );
	delete_option( 'wpdr_en_set_ext_attach' );
	delete_option( 'wpdr_en_set_repeat' );
	delete_option( 'wpdr_en_set_log_days' );
	wp_clear_scheduled_hook( 'wpdr_en_purge_logs' );

	// Automatic send result notice used before 4.0.0 (since then per user and expiring after 5 minutes).
	delete_transient( 'auto_notification_message' );
	delete_transient( 'auto_notification_result' );

	// Remove DEL posts.
	$dels = get_posts(
		array(
			'post_type'   => 'doc_ext_list',
			'numberposts' => -1,
		)
	);
	foreach ( $dels as &$del ) {
		wp_delete_post( $del->ID, true );
	}

	// delete the sent flags on documents.
	delete_post_meta_by_key( 'wpdr_en_notification_sent' );
	delete_post_meta_by_key( 'wpdr_en_ext_notice_sent' );

	// Remove the capabilities from this site's roles and users (the 4.0 upgrade can give them to users).
	$caps = array( 'read_doc_ext_lists', 'edit_doc_ext_lists', 'delete_doc_ext_lists' );
	foreach ( wp_roles()->role_objects as $role ) {
		foreach ( $caps as $cap ) {
			if ( array_key_exists( $cap, $role->capabilities ) ) {
				$role->remove_cap( $cap );
			}
		}
	}
	foreach ( get_users( array( 'blog_id' => get_current_blog_id() ) ) as $user ) {
		foreach ( $caps as $cap ) {
			if ( array_key_exists( $cap, $user->caps ) ) {
				$user->remove_cap( $cap );
			}
		}
	}

	// drop notifications logs.
	$table_name = $wpdb->prefix . 'wpdr_notification_log';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
	// %1$s should be changed to %i when all WP supported >= 6.2.
	$wpdb->query(
		$wpdb->prepare(
			'DROP TABLE IF EXISTS %1$s',
			$table_name
		)
	);

	$table_name = $wpdb->prefix . 'wpdr_ext_notice_log';
	// %1$s should be changed to %i when all WP supported >= 6.2.
	$wpdb->query(
		$wpdb->prepare(
			'DROP TABLE IF EXISTS %1$s',
			$table_name
		)
	);

	$table_name = $wpdb->prefix . 'wpdr_en_extra_text';
	// %1$s should be changed to %i when all WP supported >= 6.2.
	$wpdb->query(
		$wpdb->prepare(
			'DROP TABLE IF EXISTS %1$s',
			$table_name
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
}

if ( ! is_multisite() ) {
	wpdr_en_del_options();
} else {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	foreach ( $blog_ids as $_blog_id ) {
		switch_to_blog( $_blog_id );
		wpdr_en_del_options();
		restore_current_blog();
	}
}

// Delete every user's notification settings (users are shared by all sites of a network).
delete_metadata( 'user', 0, 'wpdr_en_user_notification', '', true );
delete_metadata( 'user', 0, 'wpdr_en_user_attachment', '', true );

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
