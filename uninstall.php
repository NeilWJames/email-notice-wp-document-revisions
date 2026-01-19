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

	// delete wpdr_en_notification_sent flags.
	delete_post_meta_by_key( 'wpdr_en_notification_sent' );

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

global $wp_roles;
if ( ! is_object( $wp_roles ) ) {
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$wp_roles = new WP_Roles();
}

// $wrole is each role.
foreach ( $wp_roles->role_names as $wrole => $label ) {
	$role_caps = $wp_roles->roles[ $wrole ]['capabilities'];
	if ( array_key_exists( 'edit_doc_ext_lists', $role_caps ) ) {
		$wp_roles->remove_cap( $wrole, 'edit_doc_ext_lists' );
	}
	if ( array_key_exists( 'delete_doc_ext_lists', $role_caps ) ) {
		$wp_roles->remove_cap( $wrole, 'delete_doc_ext_lists' );
	}
}

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
