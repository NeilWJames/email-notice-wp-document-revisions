<?php
/**
 * Plugin Name: Email Notice demo helpers
 * Description: For the Email Notice for WP Document Revisions playground only: a Departments taxonomy on Documents, emails recorded as sent without sending them (unless the playground captures them), and no review request.
 *
 * Copied to wp-content/mu-plugins by playground/demo-content.php.
 *
 * @package Email Notice WP Document Revisions
 */

defined( 'ABSPATH' ) || exit;

/**
 * Departments: a hierarchical taxonomy on Documents, so that Document External Lists can match on it
 * (the Lists use the Document taxonomies, including WP Document Revisions' Workflow States).
 *
 * @return void
 */
function wpdr_en_demo_register_taxonomy() {
	register_taxonomy(
		'department',
		array( 'document' ),
		array(
			'hierarchical'      => true,
			'labels'            => array(
				'name'          => 'Departments',
				'singular_name' => 'Department',
				'menu_name'     => 'Departments',
				'all_items'     => 'All Departments',
				'add_new_item'  => 'Add New Department',
				'edit_item'     => 'Edit Department',
			),
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'wpdr_en_demo_register_taxonomy', 5 );

/**
 * A playground has no mail server: treat every email as sent, so the logs show what would go out.
 *
 * @param null|bool $short Short-circuit value.
 * @return bool
 */
function wpdr_en_demo_no_mail( $short ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return true;
}

/*
 * The playground on playground.wordpress.net (and the WordPress.org Live Preview) captures what PHP
 * hands to sendmail and shows it in Dev Tools > Email, so there the emails really go through wp_mail()
 * (with their attachments) and can be looked at. Their blueprints define WPDR_EN_DEMO_MAIL_CAPTURE.
 * The Playground CLI (npm run playground, the e2e tests) and wp-env capture nothing, and mail() fails
 * there, so they keep the short-circuit.
 */
if ( ! defined( 'WPDR_EN_DEMO_MAIL_CAPTURE' ) || ! WPDR_EN_DEMO_MAIL_CAPTURE ) {
	add_filter( 'pre_wp_mail', 'wpdr_en_demo_no_mail' );
}

// No pause between emails.
add_filter( 'wpdr_en_mail_delay', '__return_zero' );

// No WordPress.org review request from WP Document Revisions in the demo (its filter, since WP Document Revisions 5.6.0).
add_filter( 'document_show_review_prompt', '__return_false' );
