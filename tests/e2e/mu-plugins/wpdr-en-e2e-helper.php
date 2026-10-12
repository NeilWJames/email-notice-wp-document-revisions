<?php
/**
 * Plugin Name: Email Notice e2e test helper
 * Description: Used only by the Playwright tests: the playground's Departments taxonomy and "no mail server" filter, a record of the emails, and REST routes to reset the site and load the demo content. Mounted as a must-use plugin; never shipped.
 *
 * @package Email Notice WP Document Revisions
 */

defined( 'ABSPATH' ) || exit;

/*
 * The plugin only loads in the admin (and for WP-Cron). The helper routes create Documents, Lists and
 * notifications with the plugin's own code, so a request for them runs as an admin request, as the
 * playground blueprint's runPHP step does.
 */
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only compared with a fixed string.
$wpdr_en_e2e_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
if ( str_contains( rawurldecode( $wpdr_en_e2e_uri ), 'wpdr-en-e2e/v1' ) && ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}
unset( $wpdr_en_e2e_uri );

// The playground's Departments taxonomy, no mail server and no pause between emails.
$wpdr_en_e2e_demo = WP_PLUGIN_DIR . '/email-notice-wp-document-revisions/playground/demo-mu-plugin.php';
if ( is_readable( $wpdr_en_e2e_demo ) ) {
	require_once $wpdr_en_e2e_demo;
}
unset( $wpdr_en_e2e_demo );

/**
 * Record each email (before the demo filter treats it as sent), so the tests can check what went out.
 *
 * @param null|bool $short Short-circuit value.
 * @param array     $atts  wp_mail() arguments.
 * @return null|bool
 */
function wpdr_en_e2e_record_mail( $short, $atts ) {
	$mails   = get_option( 'wpdr_en_e2e_mails', array() );
	$mails[] = array(
		'to'          => implode( ',', (array) $atts['to'] ),
		'subject'     => (string) $atts['subject'],
		'message'     => (string) $atts['message'],
		'attachments' => array_map( 'basename', (array) $atts['attachments'] ),
	);
	update_option( 'wpdr_en_e2e_mails', $mails, false );
	return $short;
}
add_filter( 'pre_wp_mail', 'wpdr_en_e2e_record_mail', 5, 2 );

add_action( 'rest_api_init', 'wpdr_en_e2e_register_routes' );

/**
 * Register the helper routes (namespace wpdr-en-e2e/v1). Administrators only.
 *
 * POST /reset  Delete the Documents, Lists, log entries, demo users and Departments, and the plugin
 *              settings; with { "demo": true } then load the playground demo content.
 * GET  /mails  The emails recorded since the last reset.
 * POST /option Set a plugin setting: { "name": "wpdr_en_set_...", "value": ... }.
 *
 * @return void
 */
function wpdr_en_e2e_register_routes() {
	$permission = static function () {
		return current_user_can( 'manage_options' );
	};

	register_rest_route(
		'wpdr-en-e2e/v1',
		'/reset',
		array(
			'methods'             => 'POST',
			'callback'            => 'wpdr_en_e2e_reset',
			'permission_callback' => $permission,
			'args'                => array(
				'demo' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		)
	);

	register_rest_route(
		'wpdr-en-e2e/v1',
		'/mails',
		array(
			'methods'             => 'GET',
			'callback'            => static function () {
				return rest_ensure_response( get_option( 'wpdr_en_e2e_mails', array() ) );
			},
			'permission_callback' => $permission,
		)
	);

	register_rest_route(
		'wpdr-en-e2e/v1',
		'/option',
		array(
			'methods'             => 'POST',
			'callback'            => static function ( $request ) {
				update_option( $request['name'], $request['value'] );
				return rest_ensure_response( array( $request['name'] => get_option( $request['name'] ) ) );
			},
			'permission_callback' => $permission,
			'args'                => array(
				'name'  => array(
					'type'     => 'string',
					'required' => true,
					'pattern'  => '^wpdr_en_set_[a-z_]+$',
				),
				'value' => array(
					'required' => true,
				),
			),
		)
	);
}

/**
 * Reset the site to WordPress, WP Document Revisions and this plugin with no content, then
 * optionally load the playground demo (playground/demo-content.php).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function wpdr_en_e2e_reset( $request ) {
	global $wpdb, $wpdr_en;

	if ( ! $wpdr_en instanceof WPDR_Email_Notice ) {
		return new WP_Error( 'wpdr_en_e2e_not_loaded', 'Email Notice is not loaded.', array( 'status' => 500 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';

	// Documents (with their files and revisions) and Lists.
	$posts = get_posts(
		array(
			'post_type'   => array( 'document', 'doc_ext_list' ),
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $posts as $post_id ) {
		foreach ( get_children(
			array(
				'post_parent' => $post_id,
				'post_type'   => 'attachment',
				'fields'      => 'ids',
			)
		) as $attach_id ) {
			wp_delete_attachment( $attach_id, true );
		}
		wp_delete_post( $post_id, true );
	}

	// Log entries. DELETE rather than TRUNCATE: Playground uses SQLite.
	foreach ( array( 'wpdr_notification_log', 'wpdr_ext_notice_log', 'wpdr_en_extra_text' ) as $table ) {
		$wpdb->query( "DELETE FROM {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	// Demo users and any user a test created (logins starting e2e-).
	foreach ( get_users( array( 'fields' => array( 'ID', 'user_login' ) ) ) as $user ) {
		if ( in_array( $user->user_login, array( 'editor', 'author', 'contributor', 'subscriber', 'subscriber2' ), true ) || str_starts_with( $user->user_login, 'e2e-' ) ) {
			wp_delete_user( (int) $user->ID, 1 );
		}
	}
	delete_user_meta( 1, 'wpdr_en_user_notification' );
	delete_user_meta( 1, 'wpdr_en_user_attachment' );

	// Departments.
	if ( taxonomy_exists( 'department' ) ) {
		foreach ( get_terms(
			array(
				'taxonomy'   => 'department',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		) as $term_id ) {
			wp_delete_term( (int) $term_id, 'department' );
		}
	}

	// Settings (not the database version), notices and recorded emails.
	foreach ( wp_load_alloptions() as $name => $value ) {
		if ( str_starts_with( $name, 'wpdr_en_set_' ) || str_starts_with( $name, '_transient_wpdr_en_auto_notice_' ) ) {
			delete_option( $name );
		}
	}
	delete_option( 'wpdr_en_e2e_mails' );

	if ( $request['demo'] ) {
		require_once WP_PLUGIN_DIR . '/email-notice-wp-document-revisions/playground/demo-content.php';
		wpdr_en_demo_load( false );
		// The demo's own notifications are in the logs; record only the tests' emails.
		delete_option( 'wpdr_en_e2e_mails' );
	} else {
		delete_transient( 'wpdr_activation_issue' );
	}

	$ids = array();
	foreach ( get_posts(
		array(
			'post_type'   => array( 'document', 'doc_ext_list' ),
			'post_status' => 'any',
			'numberposts' => -1,
		)
	) as $post ) {
		$ids[ $post->post_title ] = $post->ID;
	}
	return rest_ensure_response( array( 'posts' => $ids ) );
}
