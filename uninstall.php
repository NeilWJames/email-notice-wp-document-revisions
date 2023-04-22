<?php
/**
 * Remove settings on plugin delete.
 *
 * WPDR Custom Email Settings Uninstaller
 *
 * @version 1.0
 *
 * @package Email Notice WP Document Revisions
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

global $wpdb;

/**
 * Remove individual settings on plugin delete.
 *
 * @version 1.0
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
	// phpcs:ignore Squiz.PHP.CommentedOutCode
	// #TODO: purge
	// delete_option('wpdr_en_set_notification_log');

	// delete wpdr_en_notification_sent flags.
	delete_post_meta_by_key( 'wpdr_en_notification_sent' );

	// drop notifications log.
	$table_name = $wpdb->prefix . 'wpdr_notification_log';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			'DROP TABLE IF EXISTS %s',
			$table_name
		)
	);
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

if ( ! is_multisite() ) {
	wpdr_en_del_options();
} else {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
	foreach ( $blog_ids as $_blog_id ) {
		switch_to_blog( $_blog_id );
		wpdr_en_del_options();
		restore_current_blog();
	}
}

// Delete notification settings for users.
// phpcs:disable WordPress.DB.SlowDBQuery
$all_user_ids = get_users(
	array(
		'meta_key' => 'wpdr_en_user_notification',
		'fields'   => 'ID',
	)
);
foreach ( $all_user_ids as $user ) {
	delete_user_option( $user, 'wpdr_en_user_notification', true );
}


// Delete notification settings for users.
$all_user_ids = get_users(
	array(
		'meta_key' => 'wpdr_en_user_attachment',
		'fields'   => 'ID',
	)
);
foreach ( $all_user_ids as $user ) {
	delete_user_option( $user, 'wpdr_en_user_attachment', true );
}
// phpcs:enable WordPress.DB.SlowDBQuery



