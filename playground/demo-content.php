<?php
/**
 * Demo content for the Email Notice for WP Document Revisions playground.
 *
 * Loaded by playground/blueprint.json (and blueprint-local.json) once WP Document Revisions and this
 * plugin are active. It sets up a small document centre:
 *
 * - a Departments taxonomy on Documents (playground/demo-mu-plugin.php, which also records emails as
 *   sent without sending them, as a playground has no mail server);
 * - users of each role (password "password"), some subscribed to notifications;
 * - Documents in several departments and states, some with a file to attach;
 * - Document External Lists that match on Departments (any or all terms, parent terms, a paused List
 *   and a paused address, a List whose log entries are deleted after 90 days);
 * - notifications already sent, so both email logs have entries (including a repeat and extra text).
 *
 * The code must run as in the admin (the plugin only loads there), so the blueprint step defines
 * WP_ADMIN before loading WordPress.
 *
 * @package Email Notice WP Document Revisions
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create a tree of terms.
 *
 * @param string $taxonomy Taxonomy name.
 * @param array  $tree     Term name => child tree (array) for each term.
 * @param int    $parent_id Parent term ID.
 * @return void
 */
function wpdr_en_demo_terms( $taxonomy, $tree, $parent_id = 0 ) {
	foreach ( $tree as $name => $children ) {
		$term = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent_id ) );
		if ( is_array( $term ) && ! empty( $children ) ) {
			wpdr_en_demo_terms( $taxonomy, $children, (int) $term['term_id'] );
		}
	}
}

/**
 * Term IDs from names.
 *
 * @param string   $taxonomy Taxonomy name.
 * @param string[] $names    Term names.
 * @return int[]
 */
function wpdr_en_demo_term_ids( $taxonomy, $names ) {
	$ids = array();
	foreach ( $names as $name ) {
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( $term ) {
			$ids[] = (int) $term->term_id;
		}
	}
	return $ids;
}

/**
 * Create a user.
 *
 * @param string $login        Login (password "password").
 * @param string $display_name Display name.
 * @param string $role         Role.
 * @param int    $notify       1 to receive notifications.
 * @param int    $attach       1 to receive the document as an attachment.
 * @return int User ID.
 */
function wpdr_en_demo_user( $login, $display_name, $role, $notify = 0, $attach = 0 ) {
	$user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => 'password',
			'user_email'   => $login . '@example.com',
			'display_name' => $display_name,
			'first_name'   => strtok( $display_name, ' ' ),
			'role'         => $role,
		)
	);
	if ( is_wp_error( $user_id ) ) {
		return 0;
	}
	update_user_meta( $user_id, 'wpdr_en_user_notification', $notify );
	update_user_meta( $user_id, 'wpdr_en_user_attachment', $attach );
	return (int) $user_id;
}

/**
 * Create a Document, optionally with a small text file as its document file.
 *
 * @param string   $title       Title.
 * @param string   $status      Post status.
 * @param int      $author      Author user ID.
 * @param string[] $departments Department names.
 * @param string   $state       Workflow State name ('' for none).
 * @param bool     $file        Whether to add a document file.
 * @return int Document ID.
 */
function wpdr_en_demo_document( $title, $status, $author, $departments, $state, $file = true ) {
	$doc_id = wp_insert_post(
		array(
			'post_type'    => 'document',
			'post_status'  => $status,
			'post_title'   => $title,
			'post_author'  => $author,
			'post_excerpt' => 'A sample document for the Email Notice demo.',
			'post_content' => '',
		)
	);
	wp_set_object_terms( $doc_id, wpdr_en_demo_term_ids( 'department', $departments ), 'department' );
	if ( '' !== $state ) {
		wp_set_object_terms( $doc_id, wpdr_en_demo_term_ids( 'workflow_state', array( $state ) ), 'workflow_state' );
	}

	if ( $file ) {
		$upload = wp_upload_bits(
			sanitize_file_name( $title ) . '.txt',
			null,
			$title . "\n\nA sample document file for the Email Notice for WP Document Revisions demo.\n"
		);
		if ( empty( $upload['error'] ) ) {
			$attach_id = wp_insert_attachment(
				array(
					'post_mime_type' => 'text/plain',
					'post_title'     => $title,
					'post_status'    => 'inherit',
				),
				$upload['file'],
				$doc_id
			);
			// WP Document Revisions finds the document file from the content (and, in recent versions, this meta).
			update_post_meta( $doc_id, '_document_attachment_id', $attach_id );
			wp_update_post(
				array(
					'ID'           => $doc_id,
					'post_content' => '<!-- WPDR ' . $attach_id . ' -->',
				)
			);
		}
	}
	return (int) $doc_id;
}

/**
 * Create a Document External List.
 *
 * @param string   $title       Title.
 * @param string[] $departments Department names to match.
 * @param array    $settings    'tm' (1: all terms must match), 'attach', 'pause', 'log_days', 'notes'.
 * @param array[]  $addresses   Each: name, email, pause (0|1).
 * @return int List ID.
 */
function wpdr_en_demo_list( $title, $departments, $settings, $addresses ) {
	$settings = wp_parse_args(
		$settings,
		array(
			'tm'       => 0,
			'attach'   => 0,
			'pause'    => 0,
			'log_days' => 0,
			'notes'    => '',
		)
	);
	$list_id  = wp_insert_post(
		array(
			'post_type'    => 'doc_ext_list',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_excerpt' => $settings['notes'],
		)
	);
	wp_set_object_terms( $list_id, wpdr_en_demo_term_ids( 'department', $departments ), 'department' );
	update_post_meta( $list_id, 'wpdr_en_tm', (int) $settings['tm'] );
	update_post_meta( $list_id, 'wpdr_en_attach', (int) $settings['attach'] );
	update_post_meta( $list_id, 'wpdr_en_pause', (int) $settings['pause'] );
	update_post_meta( $list_id, 'wpdr_en_log_days', (int) $settings['log_days'] );

	$users = array();
	foreach ( array_values( $addresses ) as $num => $address ) {
		$users[] = array(
			'rec_num'   => $num,
			'user_name' => $address[0],
			'email'     => $address[1],
			'pause'     => empty( $address[2] ) ? 0 : 1,
		);
	}
	update_post_meta(
		$list_id,
		'wpdr_en_addressees',
		wp_json_encode(
			array(
				'rec_num' => count( $users ),
				'users'   => $users,
			)
		)
	);
	return (int) $list_id;
}

/**
 * Load the demo: helpers, settings, users, terms, Documents, Lists and some notifications sent.
 *
 * @param bool $install_mu_plugin Copy the helper mu-plugin into wp-content/mu-plugins (the tests don't).
 * @return void
 */
function wpdr_en_demo_load( $install_mu_plugin = true ) {
	global $wpdr_en;

	// The Departments taxonomy and the "no mail server" filter, for this request and every later one.
	if ( ! function_exists( 'wpdr_en_demo_register_taxonomy' ) ) {
		$source = __DIR__ . '/demo-mu-plugin.php';
		if ( $install_mu_plugin && wp_mkdir_p( WPMU_PLUGIN_DIR ) ) {
			copy( $source, WPMU_PLUGIN_DIR . '/wpdr-en-demo.php' );
		}
		require_once $source;
	}
	if ( ! taxonomy_exists( 'department' ) ) {
		wpdr_en_demo_register_taxonomy();
		// The Lists use the Document taxonomies, read when their post type was registered.
		register_taxonomy_for_object_type( 'department', 'doc_ext_list' );
	}

	// WP Document Revisions checks the activating user's capabilities in the same request that adds them,
	// before that user's capabilities are reloaded, so it always warns that edit_documents is missing.
	// The administrator does have it (the next request loads it), so drop the false warning.
	delete_transient( 'wpdr_activation_issue' );

	// Work as the administrator, with the plugin's admin set-up done.
	wp_set_current_user( 1 );
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	$wpdr_en->db_version_check();
	$wpdr_en->admin_init();

	// Settings.
	update_option( 'blogname', 'Email Notice Playground' );
	update_option( 'wpdr_en_set_email_from', 'Document Centre' );
	update_option( 'wpdr_en_set_notification_mode', 'Manual' );
	update_option(
		'wpdr_en_set_notification_about',
		array(
			'Chkbx_Public'   => 'Public',
			'Chkbx_Password' => 'Password',
			'Chkbx_Private'  => 'Private',
		)
	);
	update_option( 'wpdr_en_set_ext_attach', 1 );

	// Remove WordPress's sample post and page, so that the site only holds the demo content.
	foreach ( array(
		'post' => 'hello-world',
		'page' => 'sample-page',
	) as $post_type => $slug ) {
		$sample = get_page_by_path( $slug, OBJECT, $post_type );
		if ( $sample ) {
			wp_delete_post( $sample->ID, true );
		}
	}

	// Users: the administrator receives notifications too.
	update_user_meta( 1, 'wpdr_en_user_notification', 1 );
	$editor = wpdr_en_demo_user( 'editor', 'Elena Editor', 'editor', 1, 0 );
	$author = wpdr_en_demo_user( 'author', 'Arthur Author', 'author', 0, 0 );
	wpdr_en_demo_user( 'contributor', 'Connie Contributor', 'contributor', 0, 0 );
	wpdr_en_demo_user( 'subscriber', 'Sam Subscriber', 'subscriber', 1, 1 );
	wpdr_en_demo_user( 'subscriber2', 'Sue Subscriber', 'subscriber', 1, 0 );

	// Departments.
	wpdr_en_demo_terms(
		'department',
		array(
			'Finance'         => array(
				'Payroll'    => array(),
				'Purchasing' => array(),
			),
			'Operations'      => array(),
			'Human Resources' => array(),
		)
	);

	// Documents.
	$budget   = wpdr_en_demo_document( '2026 Budget', 'publish', $editor, array( 'Finance' ), 'Final' );
	$payroll  = wpdr_en_demo_document( 'Payroll calendar', 'publish', $editor, array( 'Payroll' ), 'Final' );
	$policy   = wpdr_en_demo_document( 'Purchasing policy', 'publish', $editor, array( 'Purchasing', 'Operations' ), 'Final' );
	$handbook = wpdr_en_demo_document( 'Staff handbook', 'private', $editor, array( 'Human Resources' ), 'Final' );
	wpdr_en_demo_document( 'Fire drill procedure', 'publish', $editor, array( 'Operations' ), 'Final' );
	wpdr_en_demo_document( 'Travel expenses form', 'publish', $author, array( 'Finance' ), 'In Progress' );
	wpdr_en_demo_document( 'Strategy 2027', 'draft', $editor, array( 'Finance', 'Operations' ), 'Initial Draft', false );

	// Document External Lists.
	wpdr_en_demo_list(
		'Auditors',
		array( 'Finance' ),
		array(
			'attach'   => 1,
			'log_days' => 90,
			'notes'    => 'Any Finance document, including Payroll and Purchasing. Log entries kept for 90 days after the document last changed.',
		),
		array(
			array( 'Alice Audit', 'alice@audit.example' ),
			array( 'Bob Checker', 'bob@audit.example', 1 ),
		)
	);
	wpdr_en_demo_list(
		'Board members',
		array( 'Finance', 'Operations' ),
		array(
			'tm'    => 1,
			'notes' => 'Only documents in both Finance (or below) and Operations.',
		),
		array(
			array( 'Carol Chair', 'carol@board.example' ),
			array( 'Dan Director', 'dan@board.example' ),
		)
	);
	wpdr_en_demo_list(
		'Suppliers',
		array( 'Purchasing' ),
		array( 'notes' => 'Purchasing documents only.' ),
		array(
			array( 'Supplies Ltd', 'orders@supplies.example' ),
		)
	);
	wpdr_en_demo_list(
		'Newsletter',
		array( 'Operations' ),
		array(
			'pause' => 1,
			'notes' => 'Paused: never matched while paused.',
		),
		array(
			array( 'Club news', 'news@club.example' ),
		)
	);

	// Notifications already sent, so that the logs have entries. The budget is sent twice (repeat text).
	$wpdr_en->notify( $budget, '' );
	$wpdr_en->notify( $budget, '<br/>Approved by the board on 1 October.' );
	$wpdr_en->ext_notice( $budget, array(), '<br/>Approved by the board on 1 October.' );
	$wpdr_en->notify( $payroll, '' );
	$wpdr_en->ext_notice( $payroll, array(), '' );
	$wpdr_en->ext_notice( $policy, array(), '' );
	$wpdr_en->notify( $handbook, '' );
}
