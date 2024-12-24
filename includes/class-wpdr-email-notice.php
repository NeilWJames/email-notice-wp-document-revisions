<?php
/**
 * Email Notice WP Document Revisions Main Functionality
 *
 * @author  Neil W. James <neil@familyjames.com>
 * @package Email Notice WP Document Revisions
 */

// No direct access allowed to plugin php file.
if ( ! defined( 'ABSPATH' ) ) {
	die( esc_html__( 'You are not allowed to call this file directly.', 'wpdr-email-notice' ) );
}

/**
 * Email Notice WP Document Revisions class.
 */
class WPDR_Email_Notice {

	/* Initialisation */

	/**
	 * File version.
	 *
	 * @since 1.0.0
	 *
	 * @var string $version
	 */
	public static $version = '3.1';

	/**
	 * JS has been loaded.
	 *
	 * @since 2.0.0
	 *
	 * @var boolean $js_loaded
	 */
	private static $js_loaded = false;

	/**
	 * Temporary file name
	 *
	 * @since 1.0.0
	 *
	 * @var array|string $attach_file
	 */
	public static $attach_file = null;

	/**
	 * Default e-mail content
	 *
	 * @since 2.0.0
	 *
	 * @var string $default_content
	 */
	private static $default_content;

	/**
	 * Default e-mail content for external users
	 *
	 * @since 2.0.0
	 *
	 * @var string $default_exttext
	 */
	private static $default_exttext;

	/**
	 * Default e-mail repeat
	 *
	 * @since 2.0.0
	 *
	 * @var string $default_repeat
	 */
	private static $default_repeat;

	/**
	 * Roles that can set user option to mail document information.
	 *
	 * @since 2.0.0
	 *
	 * @var string[] $internal_roles
	 */
	public static $internal_roles;

	/**
	 * Flag to determine whether to provide the internal list capability.
	 *
	 * @since 2.0.0
	 *
	 * @var bool $internal_list_needed
	 */
	private static $internal_list_needed;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		// Ensure log table defined.
		$this->db_version_check();

		add_action( 'init', array( $this, 'init' ), 3000 );

		// Initialize settings.
		add_action( 'admin_init', array( $this, 'admin_init' ), 200 );
		// Add the notification log options.
		add_action( 'admin_menu', array( $this, 'notification_log_menu' ), 30 );
	}

	/**
	 * Install log table for storing notification log.
	 *
	 * @since 1.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	private function install_plugin_tables() {
		global $wpdb;
		$sql = array();

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'wpdr_notification_log';

		// Related post, user, e-mail sent (Time stamp), e-mail address, Status (successful/failed).
		$sql[] = "CREATE TABLE $table_name (
		  id bigint(20) NOT NULL AUTO_INCREMENT,	  
		  user_id bigint(20) NOT NULL,
		  post_id bigint(20) NOT NULL,
		  time_mail_sent datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		  user_email varchar(100) NOT NULL,
		  status varchar(100) NOT NULL,
		  extra_text_id bigint(20) DEFAULT NULL,
		  PRIMARY KEY  (id),
		  INDEX user_id1 (user_id),
		  INDEX post_id1 (post_id, user_id)  
		) $charset_collate;";

		$table_name = $wpdb->prefix . 'wpdr_ext_notice_log';

		// Related post, user, e-mail sent (Time stamp), e-mail address, Status (successful/failed).
		$sql[] = "CREATE TABLE $table_name (
		  id bigint(20) NOT NULL AUTO_INCREMENT,	  
		  post_id bigint(20) NOT NULL,
		  doc_ext_list_id bigint(20) NOT NULL,
		  time_mail_sent datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		  user_name varchar(150) NOT NULL,
		  user_email varchar(100) NOT NULL,
		  status varchar(100) NOT NULL,
		  extra_text_id bigint(20) DEFAULT NULL,
		  PRIMARY KEY  (id),
		  INDEX post_id1 (post_id, user_email),
		  INDEX del_id1 (doc_ext_list_id)
		) $charset_collate;";

		$table_name = $wpdb->prefix . 'wpdr_en_extra_text';

		// Related post, added text.
		$sql[] = "CREATE TABLE $table_name (
		  id bigint(20) NOT NULL AUTO_INCREMENT,	  
		  post_id bigint(20) NOT NULL,
		  extra_text varchar(250),
		  PRIMARY KEY  (id),
		  INDEX post_id1 (post_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wpdr_en_db_version', self::$version );
	}

	/**
	 * Install capabilities on activation.
	 *
	 * @since 2.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @global WP_Role[] $wp_roles WP_Roles array object.
	 *
	 * @return void
	 */
	private function install_capabilities() {

		// create/enter capabilities.
		global $wp_roles;
		if ( ! is_object( $wp_roles ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_roles = new WP_Roles();
		}

		// default roles that should have the edit_and/or delete doc_ext_lists capability.
		// can be overridden by 3rd party plugins.
		// deliberately very limited.
		$defaults = array(
			'administrator' =>
			array(
				'edit_doc_ext_lists',
				'delete_doc_ext_lists',
			),
			'editor'        =>
			array(
				'edit_doc_ext_lists',
			),
		);

		/**
		 * Filter the default roles that will be allowed to manage the lists.
		 *
		 * @since 2.0
		 *
		 * @param string $defaults the default roles that will be allowed to manage the lists.
		 */
		$defaults = apply_filters( 'wpdr_en_doc_ext_list_roles', $defaults );

		foreach ( $defaults as $role => $caps ) {

			$role_caps = $wp_roles->roles[ $role ]['capabilities'];

			// loop  through capacities for role.
			foreach ( $caps as $cap ) {
				// add only missing capabilities.
				if ( ! array_key_exists( $cap, $role_caps ) ) {
					$wp_roles->add_cap( $role, $cap, true );
				}
			}
		}
	}

	/* Settings */

	/**
	 * Make sure we update database in case of a manual plugin download as well.
	 *
	 * @since 1.0
	 * @global WP_Role[] $wp_roles WP_Roles array object.
	 * @return void
	 */
	public function db_version_check() {
		$current_db_ver = get_option( 'wpdr_en_db_version' );
		if ( self::$version !== $current_db_ver ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				global $wp_roles;
				if ( ! is_object( $wp_roles ) ) {
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$wp_roles = new WP_Roles();
				}
				// check that a role has been allocated with edit and/or delete. if not set default.
				$found_edit   = false;
				$found_delete = false;
				foreach ( (array) $wp_roles->roles as $role => $data ) {
					if ( array_key_exists( 'edit_doc_ext_lists', $data['capabilities'] ) ) {
						// found. no more to do.
						$found_edit = true;
					}
					if ( array_key_exists( 'delete_doc_ext_lists', $data['capabilities'] ) ) {
						// found. no more to do.
						$found_delete = true;
					}
				}
				if ( ! $found_edit || ! $found_delete ) {
					$this->install_capabilities();
				}
			}
		}
		if ( ( self::$version !== $current_db_ver ) || is_multisite() ) {
			$this->install_plugin_tables();
		}
	}

	/**
	 * Register CPT on init.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function init() {
		// retrieve document taxonomies.
		$taxonomies = get_object_taxonomies( 'document', 'names' );
		/**
		 * Filter to select subset of document taxonomies used for the lists.
		 *
		 * @since 2.0
		 * @param string[] $taxonomies List of taxonomy slugs on documents.
		 * @return string[]
		 */
		$taxonomies = apply_filters( 'wpdr_en_taxonomies', $taxonomies );

		// create custom post type.
		$labels = array(
			'name'               => _x( 'Document External Lists', 'post type general name', 'wpdr-email-notice' ),
			'singular_name'      => _x( 'Document External List', 'post type singular name', 'wpdr-email-notice' ),
			'add_new'            => __( 'Add Document External List', 'wpdr-email-notice' ),
			'add_new_item'       => __( 'Add New Document External List', 'wpdr-email-notice' ),
			'edit_item'          => __( 'Edit Document External List', 'wpdr-email-notice' ),
			'new_item'           => __( 'New Document External List', 'wpdr-email-notice' ),
			'view_item'          => __( 'View Document External List', 'wpdr-email-notice' ),
			'view_items'         => __( 'View Document External Lists', 'wpdr-email-notice' ),
			'search_items'       => __( 'Search Document External Lists', 'wpdr-email-notice' ),
			'not_found'          => __( 'No Document External List found', 'wpdr-email-notice' ),
			'not_found_in_trash' => __( 'No Document External Lists found in Trash', 'wpdr-email-notice' ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'Document Emails', 'wpdr-email-notice' ),
			'all_items'          => __( 'All Document External Lists', 'wpdr-email-notice' ),
		);

		$args = array(
			'labels'               => $labels,
			'publicly_queryable'   => false,
			'public'               => true,
			'show_ui'              => true,
			'show_in_menu'         => true,
			'show_in_admin_bar'    => false,
			'show_in_nav_menus'    => true,
			'query_var'            => false,
			'rewrite'              => false,
			'has_archive'          => false,
			'hierarchical'         => false,
			'menu_position'        => 43,
			'menu_icon'            => 'dashicons-email-alt',
			'capability_type'      => array( 'doc_ext_list', 'doc_ext_lists' ),
			'map_meta_cap'         => true,
			'capabilities'         => array(
				// Meta Capabilities.
				'edit_post'              => 'edit_post',
				'read_post'              => 'read_post',
				'delete_post'            => 'delete_post',
				// Primitive Capabilities.
				'edit_posts'             => 'edit_doc_ext_lists',
				'edit_private_posts'     => 'edit_doc_ext_lists',
				'edit_published_posts'   => 'edit_doc_ext_lists',
				'edit_others_posts'      => 'edit_doc_ext_lists',
				'publish_posts'          => 'edit_doc_ext_lists',
				'read'                   => 'edit_documents',
				'read_private_posts'     => 'edit_doc_ext_lists',
				'delete_posts'           => 'edit_doc_ext_lists',
				'delete_private_posts'   => 'delete_doc_ext_lists',
				'delete_published_posts' => 'delete_doc_ext_lists',
				'delete_others_posts'    => 'delete_doc_ext_lists',
			),
			'register_meta_box_cb' => array( $this, 'meta_box_cb' ),
			'supports'             => array( 'title', 'excerpt' ),
			'taxonomies'           => $taxonomies,
		);
		/**
		 * Filters the delivered document external list type definition prior to registering it.
		 *
		 * @since 2.0
		 *
		 * @param mixed[] $args delivered document external list type definition.
		 */
		register_post_type( 'doc_ext_list', apply_filters( 'wpdr_en_register_del', $args ) );
	}

	/**
	 * Set up Settings on admin_init.
	 *
	 * @since 1.0
	 * @global WP_Role[] $wp_roles WP_Roles array object.
	 * @return void
	 */
	public function admin_init() {
		global $wp_roles;
		if ( ! is_object( $wp_roles ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_roles = new WP_Roles();
		}

		// create the list of roles that can choose to receive mails.
		$internal_roles = array_keys( $wp_roles->roles );
		/**
		 * Filter all roles to determine those who can choose to receive mails.
		 *
		 * @param string[] $internal_roles List of user roles.
		 */
		self::$internal_roles = apply_filters( 'wpdr_en_roles_email', $internal_roles );

		// if the resulting set of roles is empty, then the functionality has been switched off.
		self::$internal_list_needed = ! empty( self::$internal_roles );

		// support languages.
		load_plugin_textdomain( 'wpdr-email-notice', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Adding settings to Settings->General.
		add_settings_section( 'wpdr_en_general_settings', __( 'Document Email Settings', 'wpdr-email-notice' ), array( $this, 'general_settings' ), 'general' );
		add_settings_field( 'wpdr_en_set_email_from', __( 'Email From', 'wpdr-email-notice' ), array( $this, 'set_email_from' ), 'general', 'wpdr_en_general_settings' );
		add_settings_field( 'wpdr_en_set_email_from_address', __( 'Email Address', 'wpdr-email-notice' ), array( $this, 'set_email_from_address' ), 'general', 'wpdr_en_general_settings' );
		register_setting( 'general', 'wpdr_en_set_email_from' );
		register_setting( 'general', 'wpdr_en_set_email_from_address' );
		// Adding settings to Settings->Writing.
		add_settings_section( 'wpdr_en_writing_settings', __( 'Document Email Settings - ', 'wpdr-email-notice' ) . __( 'Notifications', 'wpdr-email-notice' ), array( $this, 'writing_settings' ), 'writing' );
		add_settings_field( 'wpdr_en_set_notification_mode', __( 'Internal Notice mode', 'wpdr-email-notice' ), array( $this, 'set_notification_mode' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_notification_about', __( 'Notify internal users about', 'wpdr-email-notice' ), array( $this, 'set_notification_about' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_subject', __( 'Notification e-mail subject', 'wpdr-email-notice' ), array( $this, 'set_subject' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_content', __( 'Internal Notice e-mail content', 'wpdr-email-notice' ), array( $this, 'set_content' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_exttext', __( 'External Notice e-mail content', 'wpdr-email-notice' ), array( $this, 'set_exttext' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_ext_attach', '', array( $this, 'set_ext_attach' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_repeat', __( 'Notification e-mail repeat', 'wpdr-email-notice' ), array( $this, 'set_repeat' ), 'writing', 'wpdr_en_writing_settings' );
		// phpcs:disable
		// #TODO: purge.
		// add_settings_field( 'wpdr_en_set_notification_log', __( 'Logging', 'wpdr-email-notice' ), array( $this, 'set_notification_log' ), 'writing', 'wpdr_en_writing_settings' );
		// phpcs:enable
		register_setting( 'writing', 'wpdr_en_set_notification_mode' );
		register_setting( 'writing', 'wpdr_en_set_notification_about' );
		register_setting( 'writing', 'wpdr_en_set_subject' );
		register_setting( 'writing', 'wpdr_en_set_content' );
		register_setting( 'writing', 'wpdr_en_set_exttext' );
		register_setting( 'writing', 'wpdr_en_set_ext_attach' );
		register_setting( 'writing', 'wpdr_en_set_repeat' );
		// phpcs:disable
		// #TODO: purge.
		// register_setting( 'writing', 'wpdr_en_set_notification_log' );.
		// phpcs:enable

		if ( self::$internal_list_needed ) {
			// Profile values.
			add_action( 'show_user_profile', array( $this, 'user_profile' ) );
			add_action( 'edit_user_profile', array( $this, 'user_profile' ) );
			add_action( 'personal_options_update', array( $this, 'save_user_profile' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_user_profile' ) );

			/* Add subscription to notifications to Add New User screen */
			add_action( 'user_new_form', array( $this, 'user_profile' ) );
			add_action( 'user_register', array( $this, 'save_user_profile' ) );

			add_filter( 'manage_users_columns', array( $this, 'all_users_column_register' ) );
			add_filter( 'manage_users_custom_column', array( $this, 'all_users_column_rows' ), 10, 3 );

			// Bulk subscribe/unsubscribe users.
			if ( ! class_exists( 'WPDR_EN_All_Users_Bulk_Action' ) ) {
				include_once __DIR__ . '/includes/class-wpdr-en-all-users-bulk-action.php';
				new WPDR_EN_All_Users_Bulk_Action();
			}

			// Send options.
			add_action( 'wp_ajax_wpdr_en_send_notification_manual', array( $this, 'send_notification_manual' ) );
			add_action( 'wp_ajax_wpdr_en_send_ext_notice_manual', array( $this, 'send_ext_notice_manual' ) );
			add_action( 'save_post_document', array( $this, 'send_notification_auto' ), 20, 3 );
			add_action( 'admin_notices', array( $this, 'admin_notice_auto_notification' ) );
		} else {
			// not wanted, but menu set earlier in process, so add and remove here.
			remove_submenu_page( 'edit.php?post_type=doc_ext_list', 'wpdr_en_notification_log' );
		}

		// remove taxonomy terms (since are on Document menu).
		/**
		 * Filters whether to remove the taxonomy menu items from the list menu.
		 *
		 * @since 2.0
		 *
		 * @param bool true taxonomy items will be removed.
		 */
		if ( apply_filters( 'wpdr_en_remove_taxonomy_menu_items', true ) ) {
			global $submenu;
			if ( isset( $submenu['edit.php?post_type=doc_ext_list'] ) ) {
				foreach ( $submenu['edit.php?post_type=doc_ext_list'] as $k => $items ) {
					if ( 'edit-tags.php?' === substr( $items[2], 0, 14 ) ) {
						remove_submenu_page( 'edit.php?post_type=doc_ext_list', $items[2] );
					}
				}
			}
		}

		add_action( 'add_meta_boxes', array( $this, 'add_metabox_head' ) );

		// help and messages.
		add_filter( 'post_updated_messages', array( $this, 'update_messages' ) );
		add_action( 'admin_head', array( $this, 'add_help_tab' ) );
		add_action( 'admin_notices', array( $this, 'check_error_state' ) );

		// Overwrite default e-mail address only if user set new value.
		if ( get_option( 'wpdr_en_set_email_from_address' ) ) {
			add_filter( 'wp_mail_from', array( $this, 'wp_mail_from' ) );
		}
		// Overwrite default e-mail from text only if user set new value.
		if ( get_option( 'wpdr_en_set_email_from' ) ) {
			add_filter( 'wp_mail_from_name', array( $this, 'wp_mail_from_name' ) );
		}

		// manage taxonomy match rule and attach option..
		add_filter( 'manage_doc_ext_list_posts_columns', array( $this, 'add_meta_columns' ) );
		add_action( 'manage_doc_ext_list_posts_custom_column', array( $this, 'del_column_data' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'del_qe_box' ), 10, 3 );
		add_action( 'bulk_edit_custom_box', array( $this, 'del_be_box' ), 10, 2 );
		// save ext list meta data.
		add_action( 'save_post_doc_ext_list', array( $this, 'save_doc_ext_list' ), 10, 3 );

		// make sure that there is at least one taxonomy set on publish.
		add_filter( 'wp_insert_post_empty_content', array( $this, 'check_taxonomy_value_set' ), 10, 2 );

		// Delete related log entries when a post is being deleted.
		add_action( 'delete_post', array( $this, 'delete_log_entry_on_post_delete' ), 10 );

		// Delete related log entries when a user is being deleted.
		add_action( 'deleted_user', array( $this, 'delete_log_entry_on_user_delete' ), 10, 3 );

		// enqueue script on document page.
		add_action( 'admin_enqueue_scripts', array( $this, 'load_js_methods' ) );

		// for the user list interface.
		add_action( 'wp_ajax_wpdr_en_add_address', array( $this, 'edit_address' ) );
		add_action( 'wp_ajax_wpdr_en_del_address', array( $this, 'delete_address' ) );
		add_action( 'wp_ajax_wpdr_en_search_list', array( $this, 'search_list' ) );

		// search box needs post id added (Mis-use of filter to process in correct place).
		add_action( 'use_block_editor_for_post', array( $this, 'ensure_post_id' ), 10, 2 );

		// set default texts.
		// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
		// translators: For this group, text surroundedby % symbols (like %recipient_name%) should not be translated.
		self::$default_content = __( 'Dear %recipient_name%,<br/><br/>', 'wpdr-email-notice' ) .
			__( 'A new document is published. Check it out!<br/><br/><strong>%title_with_permalink%</strong><br/>%words_50%%extra%%repeat%<br/>', 'wpdr-email-notice' ) .
			// translators: %s is the link address of the user's profile. Do not translate.
			sprintf( __( '<small>In case you do not want to receive this kind of notification you can turn it off in your <a href="%s">profile</a>.</small>', 'wpdr-email-notice' ), admin_url( 'profile.php' ) ) .
			__( '<small><br/>Also go there if you wish to change whether you will receive the document as an attachment.</small>', 'wpdr-email-notice' );
		self::$default_exttext = __( 'Dear %recipient_name%,<br/><br/>', 'wpdr-email-notice' ) .
			__( 'A new document is published. Check it out!<br/><br/><strong>%title_with_permalink%</strong><br/>%words_50%%extra%%repeat%<br/>', 'wpdr-email-notice' ) .
			__( '<small>In case you do not want to receive this kind of notification you can reply with the message "Unsubscribe".</small>', 'wpdr-email-notice' );
		self::$default_repeat  = __( '<p>This document has previously been sent to you %num% time(s), with the latest sent on %last_date%.</p>', 'wpdr-email-notice' );
		// phpcs:enable WordPress.Security.EscapeOutput, WordPress.WP.I18n
		self::$default_content = wp_kses_post( self::$default_content );
		self::$default_exttext = wp_kses_post( self::$default_exttext );
		self::$default_repeat  = wp_kses_post( self::$default_repeat );
	}

	/**
	 * Initialize js methods.
	 *
	 * @since 1.0
	 * @global WP_Post $post Post object.
	 *
	 * @return void
	 */
	public static function load_js_methods() {
		global $post;
		if ( is_null( $post ) || self::$js_loaded ) {
			return;
		}
		if ( 'document' === $post->post_type ) {
			$script      = 'wpdr-email-notice';
			$css         = 'wpdr-en-notice';
			$nonce_array = array(
				'wpdr_en_nonce'    => wp_create_nonce( 'wpdr_en_nonce' ),
				'ajaxurl'          => admin_url( 'admin-ajax.php' ),
				'sending_mails'    => __( 'Sending email notifications...', 'wpdr-email-notice' ),
				'error_sending'    => __( 'Error sending emails.', 'wpdr-email-notice' ),
				'resend'           => __( 'Re-send notification email(s)', 'wpdr-email-notice' ),
				'email_out_of'     => __( 'email(s) out of', 'wpdr-email-notice' ),
				'notif_sent_check' => __( 'notification(s) sent. Check', 'wpdr-email-notice' ),
				'sent_with'        => __( 'sent with', 'wpdr-email-notice' ),
				'log_issues'       => __( 'log issues. Check', 'wpdr-email-notice' ),
				'log'              => __( 'log', 'wpdr-email-notice' ),
				'for_details'      => __( 'for details.', 'wpdr-email-notice' ),
			);
		} elseif ( 'doc_ext_list' === $post->post_type ) {
			$css         = 'wpdr-en-mail';
			$script      = 'wpdr-en-address';
			$nonce_array = array(
				'wpdr_en_nonce' => wp_create_nonce( 'wpdr_en_nonce' ),
				'ajaxurl'       => admin_url( 'admin-ajax.php' ),
				'user'          => get_current_user_id(),
				'add_address'   => __( 'Add Address to List', 'wpdr-email-notice' ),
				'edit_address'  => __( 'Edit existing Address', 'wpdr-email-notice' ),
			);
		} else {
			return;
		}

		// process script.
		$suffix = ( WP_DEBUG ) ? '' : '.min';
		$path   = 'js/' . $script . $suffix . '.js';
		$versn  = ( WP_DEBUG ) ? filemtime( plugin_dir_path( __DIR__ ) . $path ) : self::$version;
		wp_register_script(
			'wpdr_en_script',
			plugin_dir_url( __DIR__ ) . $path,
			array( 'jquery' ),
			$versn,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		// Improve security.
		wp_localize_script( 'wpdr_en_script', 'wpdr_en_obj', $nonce_array );
		wp_enqueue_script( 'wpdr_en_script' );

		// load css as well.
		$suffix = ( WP_DEBUG ) ? '' : '.min';
		$path   = 'css/' . $css . $suffix . '.css';
		wp_enqueue_style(
			'wpdr_en_css',
			plugin_dir_url( __DIR__ ) . $path,
			array(),
			WP_DEBUG ? filemtime( plugin_dir_path( __DIR__ ) . $path ) : self::$version,
		);
		// note loaded.
		self::$js_loaded = true;
	}

	/* Settings->General */

	/**
	 * Document Email Settings section intro text.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function general_settings() {
		// Get the site domain and get rid of www.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.WP.DeprecatedFunctions
		$sitename = strtolower( sanitize_url( $_SERVER['SERVER_NAME'] ) );
		if ( substr( $sitename, 0, 4 ) === 'www.' ) {
			$sitename = substr( $sitename, 4 );
		}
		echo esc_html__( 'By default all notification e-mails received from "WordPress" < wordpress@', 'wpdr-email-notice' ) . esc_url( $sitename ) . ' >. ' . esc_html__( 'You can change these below.', 'wpdr-email-notice' );
	}

	/**
	 * Settings field to set Email From text.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_email_from() {
		echo '<input class="regular-text ltr" type="text" id="wpdr_en_set_email_from" name="wpdr_en_set_email_from" placeholder="Wordpress" value="' . esc_html( get_option( 'wpdr_en_set_email_from' ) ) . '"></input>';
	}

	/**
	 * Settings field to set Email from email Address.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_email_from_address() {
		// Get the site domain and get rid of www.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.WP.DeprecatedFunctions
		$sitename = strtolower( sanitize_url( $_SERVER['SERVER_NAME'] ) );
		if ( 'www.' === substr( $sitename, 0, 4 ) ) {
			$sitename = substr( $sitename, 4 );
		}
		echo '<input class="regular-text ltr" type="email" id="wpdr_en_set_email_from_address" name="wpdr_en_set_email_from_address" placeholder="wordpress@' . esc_url( $sitename ) . '" value="' . esc_html( get_option( 'wpdr_en_set_email_from_address' ) ) . '"></input>';
	}

	/* Settings->Writing */

	/**
	 * Notifications section intro text.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function writing_settings() {
		esc_html_e( 'Tags available to make e-mail subject and/or content dynamic:', 'wpdr-email-notice' );
		echo '<br/>';
		echo '<strong>%title%</strong> ' . esc_html__( 'means title of the post', 'wpdr-email-notice' ) . '<br/>';
		echo '<strong>%permalink%</strong> ' . esc_html__( 'means URL of the post', 'wpdr-email-notice' ) . '<br/>';
		echo '<strong>%title_with_permalink%</strong> ' . esc_html__( 'means URL with title of the post', 'wpdr-email-notice' ) . '<br/>';
		echo '<strong>%author_name%</strong> ' . esc_html__( 'means the name of the post author', 'wpdr-email-notice' ) . '<br/>';
		echo '<strong>%excerpt%</strong> ' . esc_html__( 'means excerpt of the post', 'wpdr-email-notice' ) . ' ' . esc_html__( 'Only available to those who can edit the document', 'wpdr-email-notice' ) . '<br/>';
		echo '<strong>%words_n%</strong> ' . esc_html__( 'means the first n (must be an integer number) number of word(s) extracted from the post', 'wpdr-email-notice' ) . '<br/>';
		echo '<strong>%recipient_name%</strong> ' . esc_html__( 'means display name of the user who receives the e-mail', 'wpdr-email-notice' ) . '<br/>';
		echo '<strong>%repeat%</strong> ' . esc_html__( 'means output the phrase if the document has been previously e-mailed.', 'wpdr-email-notice' ) . '<br/>';
		echo '<br/>';
		echo esc_html__( 'Tags available within the tag', 'wpdr-email-notice' ) . ' <strong>%repeat%</strong>';
		echo '<br/>';
		echo '<strong>%num%</strong> ' . esc_html__( 'means the number of times the document has been previously e-mailed.', 'wpdr-email-notice' ) . '<br/>';
		echo '<strong>%last_date%</strong> ' . esc_html__( 'means the last date that the document was e-mailed.', 'wpdr-email-notice' ) . '<br/>';
		echo '<strong>%last_time%</strong> ' . esc_html__( 'means the last date incuding time that the document was e-mailed.', 'wpdr-email-notice' ) . '<br/>';
		echo '<br/>';
		echo '<strong>%extra%</strong> ' . esc_html__( 'means output an optional extra phrase entered at the time of mailing.', 'wpdr-email-notice' ) . '<br/>';
	}

	/**
	 * Settings field to set Notification mode: Auto/Manual.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_notification_mode() {
		$opt = get_option( 'wpdr_en_set_notification_mode' );
		if ( ! isset( $opt ) ) {
			$opt = 'Manual';
		}
		echo '<input type="radio" name="wpdr_en_set_notification_mode" value="Auto" ' . checked( 'Auto', $opt, false ) . '>' . esc_html__( 'Auto', 'wpdr-email-notice' ) . '</input> ' . esc_html__( '(send e-mails automatically when you publish a post)', 'wpdr-email-notice' ) . '<br/>';
		echo '<input type="radio" name="wpdr_en_set_notification_mode" value="Manual" ' . checked( 'Manual', $opt, false ) . '>' . esc_html__( 'Manual', 'wpdr-email-notice' ) . '</input> ' . esc_html__( '(you need to press a button to send notification)', 'wpdr-email-notice' );
	}

	/**
	 * Settings field to set Notify users about public/private/password protected posts.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_notification_about() {
		$options          = get_option( 'wpdr_en_set_notification_about' );
		$public_checked   = '';
		$password_checked = '';
		$private_checked  = '';
		if ( ! empty( $options ) ) {
			if ( array_key_exists( 'Chkbx_Public', $options ) ) {
				$public_checked = 'checked="checked"';
			}
			if ( array_key_exists( 'Chkbx_Password', $options ) ) {
				$password_checked = ' checked="checked" ';
			}
			if ( array_key_exists( 'Chkbx_Private', $options ) ) {
				$private_checked = ' checked="checked" ';
			}
		}
		echo '<input type="checkbox" id="Chkbx_Public" name="wpdr_en_set_notification_about[Chkbx_Public]" value="Public" ' . esc_attr( $public_checked ) . '>' . esc_html__( 'Public posts', 'wpdr-email-notice' ) . '</input><br/>';
		echo '<input type="checkbox" id="Chkbx_Password" name="wpdr_en_set_notification_about[Chkbx_Password]" value="Password"' . esc_attr( $password_checked ) . '>' . esc_html__( 'Password', 'wpdr-email-notice' ) . '</input> ' . esc_html__( 'protected posts (password will', 'wpdr-email-notice' ) . ' <strong>' . esc_html__( 'NOT', 'wpdr-email-notice' ) . '</strong> ' . esc_html__( 'be included in notification e-mail)', 'wpdr-email-notice' ) . '<br/>';
		echo '<input type="checkbox" id="Chkbx_Private" name="wpdr_en_set_notification_about[Chkbx_Private]" value="Private"' . esc_attr( $private_checked ) . '>' . esc_html__( 'Private posts', 'wpdr-email-notice' ) . '</input> ';
		echo '<br />' . esc_html__( 'Notifications are sent only to those Internal users who can read the document', 'wpdr-email-notice' );
		echo '<br />' . esc_html__( 'Notifications can only be sent to External users for Public documents.', 'wpdr-email-notice' );
	}

	/**
	 * Settings field to set notification email subject.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_subject() {
		echo '<input class="regular-text ltr" type="text" id="wpdr_en_set_subject" name="wpdr_en_set_subject" placeholder="New document: %title%" value="' . esc_html( get_option( 'wpdr_en_set_subject' ) ) . '"></input>';
		echo '<br/><br/>' . esc_html__( 'Hint: HTML tags are not allowed here, e.g.: %title_with_permalink% will revert to %title%.', 'wpdr-email-notice' );
	}

	/**
	 * Settings field to set notification email content.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_content() {
		$text  = wp_kses_post( get_option( 'wpdr_en_set_content' ) );
		$place = str_replace( '"', '&quot;', self::$default_content );
		if ( $text === self::$default_content ) {
			$text = $place;
		}
		// phpcs:disable WordPress.Security.EscapeOutput
		echo '<textarea id="wpdr_en_set_content" name="wpdr_en_set_content" cols="80" rows="8" 
		placeholder = "' . $place . '">' . $text . '</textarea>';
		// phpcs:enable WordPress.Security.EscapeOutput
		echo '<br/><br/>' . esc_html__( 'Hint: HTML tags are welcome here to make your notification e-mails more personalized.', 'wpdr-email-notice' );
	}

	/**
	 * Settings field to set external notification email content.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function set_exttext() {
		$text  = wp_kses_post( get_option( 'wpdr_en_set_exttext' ) );
		$place = str_replace( '"', '&quot;', self::$default_exttext );
		if ( $text === self::$default_exttext ) {
			$text = $place;
		}
		// phpcs:disable WordPress.Security.EscapeOutput
		echo '<textarea id="wpdr_en_set_content" name="wpdr_en_set_exttext" cols="80" rows="7" 
		placeholder = "' . $place . '">' . $text . '</textarea>';
		// phpcs:enable WordPress.Security.EscapeOutput
		echo '<br/><br/>' . esc_html__( 'Hint: HTML tags are welcome here to make your notification e-mails more personalized.', 'wpdr-email-notice' );
	}

	/**
	 * Settings field to set external email document attachment.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function set_ext_attach() {
		?>
		<label for="wpdr_en_set_ext_attach">
		<input name="wpdr_en_set_ext_attach" type="checkbox" id="wpdr_en_set_ext_attach" value="1" <?php checked( '1', get_option( 'wpdr_en_set_ext_attach' ) ); ?> />
		<?php esc_html_e( 'Attach the document in emails to external users.', 'wpdr-email-notice' ); ?></label><br />
		<?php
		echo '<br/>' . esc_html__( 'Remember that Public documents may not always be accessible, so these may need to be attached.', 'wpdr-email-notice' );
	}

	/**
	 * Settings field to set notification email repeat text.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function set_repeat() {
		// phpcs:disable WordPress.Security.EscapeOutput
		echo '<textarea id="wpdr_en_set_repeat" name="wpdr_en_set_repeat" cols="80" rows="5" 
		placeholder = "' . self::$default_repeat . '">' . wp_kses_post( get_option( 'wpdr_en_set_repeat' ) ) . '</textarea>';
		// phpcs:enable WordPress.Security.EscapeOutput
		echo '<br/><br/>' . esc_html__( 'Hint: HTML tags are welcome here to make your notification e-mails more personalized.', 'wpdr-email-notice' );
	}

	// #TODO: purge.

	/**
	 * Settings field to set purge interval.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function purge_notification_log() {
		echo 'Purge log every <input type="number" id="wpdr_en_set_notification_log" name="wpdr_en_set_notification_log" value="' . esc_html( get_option( 'wpdr_en_set_notification_log' ) ) . '" min="0" max="999" size="3"></input> day(s). Zero or empty value means no purge needed.<br>';
	}

	/**
	 * Retrieve the text for a mailing.
	 *
	 * @since 3.1
	 * @param int $type Text to retrieve (-1 = internal, 0 = External, n = List Id).
	 * @return string
	 */
	private function get_content( $type ) {
		if ( -1 === $type ) {
			$text = get_option( 'wpdr_en_set_content' );
			if ( empty( $text ) ) {
				$text = self::$default_content;
			}
		} elseif ( 0 === $type ) {
			$text = get_option( 'wpdr_en_set_exttext' );
			if ( empty( $text ) ) {
				$text = self::$default_exttext;
			}
		} else {
			$text = 'Not yet supported';
		}
		return $text;
	}

	/**
	 * Add subscription to notifications to User's Profile screen.
	 *
	 * @since 1.0
	 * @param WP_User $user User record.
	 * @return void
	 */
	public function user_profile( $user ) {
		// user_new_form does not have a WP_User (but no roles yet) and then see if User has the choice.
		if ( ! $user instanceof WP_User || ! (bool) array_intersect( $user->roles, self::$internal_roles ) ) {
			return;
		}
		// Wrapper.
		echo '<div class="wpdr-en-wrapper">';

		// Header.
		echo '<div class="wpdr_en-header">';
		echo '<h3>' . esc_html__( 'Document Email Settings', 'wpdr-email-notice' ) . '</h3>';
		echo '</div>'; // wpdr_en-header end.

		$wpdr_en_user_notification = '';
		$wpdr_en_user_attachment   = '';
		if ( ! empty( $user->ID ) ) {
			$wpdr_en_user_notification = checked( 1, (int) get_user_meta( $user->ID, 'wpdr_en_user_notification', true ), false );
			$wpdr_en_user_attachment   = checked( 1, (int) get_user_meta( $user->ID, 'wpdr_en_user_attachment', true ), false );
		}

		echo '<div class="wpdr_en-content">';
		echo '<input type="checkbox" name="wpdr_en_user_notification" value="1" ' . esc_attr( $wpdr_en_user_notification ) . '>' . esc_html__( 'Notify me by e-mail when a new document is published', 'wpdr-email-notice' ) . '</input><br/>';

		echo '<input type="checkbox" name="wpdr_en_user_attachment" value="1" ' . esc_attr( $wpdr_en_user_attachment ) . '>' . esc_html__( 'Also send me the document as an attachment by e-mail when a new document is published', 'wpdr-email-notice' ) . '</input><br/>';
		echo '</div>'; // wpdr_en-content end.
		echo '</div>'; // wpdr_en-wrapper end.
	}

	/**
	 * Save Profile settings.
	 *
	 * @since 1.0
	 * @param int $user_id User id.
	 * @return void
	 */
	public function save_user_profile( $user_id ) {
		// Can be called from add_user or update_user.
		if ( ! ( check_admin_referer( 'update-user_' . $user_id ) || check_admin_referer( 'create-user', '_wpnonce_create-user' ) ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// Does the user have the choice.
		$user = get_user_by( 'ID', $user_id );
		if ( ! (bool) array_intersect( $user->roles, self::$internal_roles ) ) {
			// remove if was present due to change of roles.
			delete_user_meta( $user_id, 'wpdr_en_user_notification' );
			delete_user_meta( $user_id, 'wpdr_en_user_attachment' );
			return;
		}

		$notify = false;
		if ( isset( $_POST['wpdr_en_user_notification'] ) ) {
			$notify = (bool) sanitize_text_field( wp_unslash( $_POST['wpdr_en_user_notification'] ) );
		}
		$attach = false;
		if ( isset( $_POST['wpdr_en_user_attachment'] ) ) {
			$attach = (bool) sanitize_text_field( wp_unslash( $_POST['wpdr_en_user_attachment'] ) );
		}
		update_user_meta( $user_id, 'wpdr_en_user_notification', (int) $notify );
		update_user_meta( $user_id, 'wpdr_en_user_attachment', (int) $attach );

		// Check if values saved successfully.
		if ( (bool) get_user_meta( $user_id, 'wpdr_en_user_notification', true ) !== $notify ) {
			wp_die( 'Something went wrong.<br>[Error: F-01] ' );
		}
		if ( (bool) get_user_meta( $user_id, 'wpdr_en_user_attachment', true ) !== $attach ) {
			wp_die( 'Something went wrong.<br>[Error: F-01] ' );
		}
	}

	/**
	 * Add subscription to notifications. Add column.
	 *
	 * @since 1.0
	 * @param mixed[] $columns Columns of user detail.
	 * @return mixed[]
	 */
	public function all_users_column_register( $columns ) {
		$columns['wpdr_en_notification'] = 'Document Email notifications';
		return $columns;
	}

	/**
	 * Add subscription to notifications. Add row information.
	 *
	 * @since 1.0
	 * @param string $content     Initial content of cell.
	 * @param string $column_name Cell column name.
	 * @param string $user_id     Cell row name.
	 * @return string
	 */
	public function all_users_column_rows( $content, $column_name, $user_id ) {
		if ( 'wpdr_en_notification' !== $column_name ) {
			return $content;
		}

		// Does the user have the choice.
		$user = get_user_by( 'ID', $user_id );
		if ( ! (bool) array_intersect( $user->roles, self::$internal_roles ) ) {
			return $content;
		}

		$oput                   = '';
		$wpdr_user_notification = (bool) get_user_meta( $user_id, 'wpdr_en_user_notification', true );
		if ( empty( $wpdr_user_notification ) || ! $wpdr_user_notification ) {
			$oput = '<input type="checkbox" disabled></>&nbsp;';
		} else {
			$oput = '<input type="checkbox" checked="checked" disabled></>&nbsp;';
		}
		$oput                .= '&nbsp;';
		$wpdr_user_attachment = (bool) get_user_meta( $user_id, 'wpdr_en_user_attachment', true );
		if ( empty( $wpdr_user_attachment ) || ! $wpdr_user_attachment ) {
			return $oput . '<input type="checkbox" disabled></>';
		} else {
			return $oput . '<input type="checkbox" checked="checked" disabled></>';
		}
	}

	/* Adds a Document Email Notifications box to Edit Post screen */

	/**
	 * Adds a Document Email Notifications metabox to Edit Document screen.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function add_metabox_head() {
		add_meta_box( 'wpdr_en_sectionid', __( 'Document Email Notifications', 'wpdr-email-notice' ), array( $this, 'add_metabox' ), 'document', 'side', 'high' );
	}

	/**
	 * Builds the Document Email Notifications metabox.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function add_metabox() {
		// Add an nonce field so we can check for it later.
		$document_id = get_the_ID();
		wp_nonce_field( 'wpdr_en_meta_box', 'wpdr_en_meta_box_nonce' );
		$pstatus = $this->post_status( $document_id );
		// Internal user lists box.
		if ( self::$internal_list_needed ) {
			$notification_sent = (string) get_post_meta( $document_id, 'wpdr_en_notification_sent', true );
			$recipients        = $this->prepare_mail_recipients( $document_id );
			$hasrecipient      = ! empty( $recipients );
			echo '<div class="wpdr-en-add-meta"><div>';
			echo '<div class="wpdr-en-add-text"><div>';
			if ( ( ! empty( $notification_sent ) || '1' === $notification_sent ) && in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) && $hasrecipient ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_html__( 'Re-send notification email(s)', 'wpdr-email-notice' ) . '" />';
			} elseif ( in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) && ( empty( $notification_sent ) || '0' === $notification_sent ) && $hasrecipient ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_html__( 'Send notification email(s)', 'wpdr-email-notice' ) . '"/>';
			} elseif ( empty( $notification_sent ) || '0' === $notification_sent ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_html__( 'Send notification email(s)', 'wpdr-email-notice' ) . '" disabled/>';
			} elseif ( ! empty( $notification_sent ) || '1' === $notification_sent ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_html__( 'Re-send notification email(s)', 'wpdr-email-notice' ) . '" disabled/>';
			}
			echo '</div><div style="margin-left: 5px;"><label>';
			// Is extra text available for internal.
			if ( str_contains( $this->get_content( -1 ), '%extra%' ) ) {
				echo '<input type="checkbox" id="wpdr-en-int-extra" value="0" disabled />';
				$extra = true;
			} else {
				echo '<input type="checkbox" id="wpdr-en-int-extra" value="0" class="wpdr_en_not_use" disabled />';
				$extra = false;
			}
			echo esc_html__( 'Add Extra text', 'wpdr-email-notice' ) . '</label></div></div>';
		}
		// Pass document id to jQuery.
		echo '<div id="wraphidden">';
		echo '<span id="dProgress" style="display:none; margin-top: 5px;" >';
		echo '<img id="spnSendNotifications" src="' . esc_url( admin_url() ) . '/images/wpspin_light.gif">';
		echo '</span>';

		// phpcs:disable
		/* To debug uncomment the following.
		if ( in_array( $pstatus, array( 'Public','Password protected','Private' ), true ) ) {
			_e( 'Can send mail', 'wpdr-email-notice' );
		} else {
			_e( 'Should not send mail', 'wpdr-email-notice' );
		}
		echo '<br/>';
		if ( empty($notification_sent ) || '0' === $notification_sent ) {
			_e( 'Not yet sent', 'wpdr-email-notice' );
		} else {
			_e( 'Already sent', 'wpdr-email-notice' );
		}
		echo '<br/>';
		if ( $hasrecipient ) {
			_e( 'Recipient exists', 'wpdr-email-notice' );
		} else {
			_e( 'No recipient', 'wpdr-email-notice' );
		}
		*/
		// phpcs:enable
		echo '<span id="wpdr-en-message" style="display: none; margin-left: 5px;">' . esc_html__( 'Sending email notifications...', 'wpdr-email-notice' ) . '</span>';
		echo '</span></div>';
		// external users.
		echo '<br />';
		$ext_notice_sent = (string) get_post_meta( $document_id, 'wpdr_en_ext_notice_sent', true );
		// only need to know if there are recipients so do a shortcut version. Not selected lsts yet.
		$ext_lists       = $this->prepare_mail_ext_users( $document_id, array(), false );
		$hasextrecipient = ! empty( $ext_lists );
		$read            = ( current_user_can( 'edit_doc_ext_lists' ) ? '' : ' readonly' );
		if ( ( ! empty( $ext_notice_sent ) || '1' === $ext_notice_sent ) && 'Public' === $pstatus && $hasextrecipient ) {
			echo '<div class="wpdr-en-add-text"><div>';
			echo '<input type="button" id="wpdr-en-ext-note" class="button-secondary" value="' . esc_html__( 'Re-send external list email(s)', 'wpdr-email-notice' ) . '" />';
			// Is extra text available for external.
			echo '</div><div style="margin-left: 5px; margin-right: 5px;"><label>';
			if ( str_contains( $this->get_content( 0 ), '%extra%' ) ) {
				echo '<input type="checkbox" id="wpdr-en-ext-extra" value="0" disabled />';
				$extra = true;
			} else {
				echo '<input type="checkbox" id="wpdr-en-ext-extra" value="0" class="wpdr_en_not_use" disabled />';
			}
			echo esc_html__( 'Add Extra text', 'wpdr-email-notice' ) . '</label></div></div>';
			// output the list(s) available (text box readonly if cannot edit the lists).
			foreach ( $ext_lists as $list ) {
				echo '<br /><label>&nbsp;&nbsp;';
				echo '<input name="wpdr-en-ext-list" type="checkbox" value="' . esc_attr( $list['list_id'] ) . esc_attr( $read ) . '" checked="checked">  ' . esc_attr( $list['list_title'] );
				echo '</label>';
			}
		} elseif ( 'Public' === $pstatus && ( empty( $ext_notice_sent ) || '0' === $ext_notice_sent ) && $hasextrecipient ) {
			echo '<div class="wpdr-en-add-text"><div>';
			echo '<input type="button" id="wpdr-en-ext-note" class="button-secondary" value="' . esc_html__( 'Send external list email(s)', 'wpdr-email-notice' ) . '"/>';
			// Is extra text available for external.
			echo '</div><div style=" 5px; margin-right: 5px;"><label>';
			if ( str_contains( $this->get_content( 0 ), '%extra%' ) ) {
				echo '<input type="checkbox" id="wpdr-en-ext-extra" value="0" disabled />';
				$extra = true;
			} else {
				echo '<input type="checkbox" id="wpdr-en-ext-extra" value="0" class="wpdr_en_not_use" disabled />';
			}
			echo esc_html__( 'Add Extra text', 'wpdr-email-notice' ) . '</label></div>';
			// output the list(s) available (text box readonly if cannot edit the lists).
			foreach ( $ext_lists as $list ) {
				echo '<br /><label>&nbsp;&nbsp;';
				echo '<input name="wpdr-en-ext-list" type="checkbox" value="' . esc_attr( $list['list_id'] ) . esc_attr( $read ) . '" checked="checked">  ' . esc_attr( $list['list_title'] );
				echo '</label>';
			}
		} elseif ( empty( $ext_notice_sent ) || '0' === $ext_notice_sent ) {
			echo '<input type="button" id="wpdr-en-ext-note" class="button-secondary" value="' . esc_html__( 'Send external list email(s)', 'wpdr-email-notice' ) . '" disabled/>';
		} elseif ( ! empty( $ext_notice_sent ) || '1' === $ext_notice_sent ) {
			echo '<input type="button" id="wpdr-en-ext-note" class="button-secondary" value="' . esc_html__( 'Re-send external list email(s)', 'wpdr-email-notice' ) . '" disabled/>';
		}
		echo '</div><div><p>' . esc_html__( 'Optional Extra text', 'wpdr-email-notice' ) . '</p><fieldset>';
		echo '<textarea rows="4" cols="30" name="wpdr-en-extra" id="wpdr-en-extra"' . ( $extra ? '' : ' disabled' ) . '></textarea><br />';
		echo '<label class="screen-reader-text" for="wpdr-en-extra">' . esc_html__( 'Optional Extra text', 'wpdr-email-notice' ) . '</label>';
		esc_html_e( 'Enter any extra text for this specific mailing and click Add Extra text.', 'wpdr-email-notice' );
		echo '</fieldset></div></div>';
	}

	/* Posts ->  WPDR EN User Notification log */

	/**
	 * Create the menu items.
	 *
	 * @since 1.0
	 * @global WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function notification_log_menu() {
		add_submenu_page( 'edit.php?post_type=doc_ext_list', __( 'External User Email Log', 'wpdr-email-notice' ), __( 'External User Email Log', 'wpdr-email-notice' ), 'edit_documents', 'wpdr_en_ext_notice_log', array( $this, 'ext_notice_log_list' ) );
		// internal list menu item may be removed later.
		add_submenu_page( 'edit.php?post_type=doc_ext_list', __( 'Internal User Email Log', 'wpdr-email-notice' ), __( 'Internal User Email Log', 'wpdr-email-notice' ), 'edit_documents', 'wpdr_en_notification_log', array( $this, 'notification_log_list' ) );
	}

	/**
	 * Edit address in post.
	 *
	 * @since 2.0
	 *
	 * @global WP_Post $post Post object.
	 */
	public static function edit_address() {
		// Avoid being easily hacked.
		if ( ! isset( $_POST['wpdr_en_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdr_en_nonce'] ) ), 'wpdr_en_nonce' ) ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'wpdr-email-notice' ),
				'error_code' => 'F-11',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}

		// set user.
		if ( ! isset( $_POST['userid'] ) || ! wp_set_current_user( sanitize_text_field( wp_unslash( $_POST['userid'] ) ) ) instanceof WP_User ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'wpdr-email-notice' ),
				'error_code' => 'F-12',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}

		// set post_id.
		if ( ! isset( $_POST['post_id'] ) || ! get_post( sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) ) instanceof WP_Post ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'wpdr-email-notice' ),
				'error_code' => 'F-13',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}
		$id = sanitize_text_field( wp_unslash( $_POST['post_id'] ) );
		global $post;

		$user_name = '';
		$email     = '';
		$pause     = 0;
		if ( isset( $_POST['user_name'] ) ) {
			$user_name = sanitize_text_field( wp_unslash( $_POST['user_name'] ) );
		}
		if ( isset( $_POST['email'] ) ) {
			$email = sanitize_text_field( wp_unslash( $_POST['email'] ) );
		}
		if ( isset( $_POST['pause'] ) ) {
			$pause = (int) sanitize_text_field( wp_unslash( $_POST['pause'] ) );
		}
		if ( empty( $user_name ) || empty( $email ) ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'wpdr-email-notice' ),
				'error_code' => 'F-14',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}

		$users_rec = get_post_meta( $id, 'wpdr_en_addressees', true );
		if ( false === $users_rec || empty( $users_rec ) ) {
			$rec_num = 0;
			$users   = array();
		} else {
			$users_rec = json_decode( $users_rec, true );
			$rec_num   = $users_rec['rec_num'];
			$users     = $users_rec['users'];
		}

		// make sure email is not duplicated.
		$insert = true;
		foreach ( $users as $key => $user ) {
			if ( $email === $user['email'] ) {
				$users[ $key ]['user_name'] = $user_name;
				$users[ $key ]['pause']     = $pause;
				$insert                     = false;
			}
		}
		if ( $insert ) {
			$user = array(
				'rec_num'   => $rec_num,
				'user_name' => $user_name,
				'email'     => $email,
				'pause'     => $pause,
			);
			++$rec_num;
			$users[] = $user;
		}
		$users_rec = array(
			'rec_num' => $rec_num,
			'users'   => $users,
		);

		$users_rec = wp_json_encode( $users_rec );
		update_post_meta( $id, 'wpdr_en_addressees', $users_rec );

		// generate new report.
		ob_start();
		self::ext_user_list( $id );
		$rpt = ob_get_clean();
		// json encode it.
		// phpcs:ignore
		$rpt = json_encode( $rpt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );

		// return success.
		$result = array(
			'error'      => false,
			'error_msg'  => $rpt,
			'error_code' => '',
		);
		header( 'Content-Type: application/json' );
		die( wp_json_encode( $result ) );
	}

	/**
	 * Delete address in post.
	 *
	 * @since 2.0
	 */
	public static function delete_address() {
		// Avoid being easily hacked.
		if ( ! isset( $_POST['wpdr_en_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdr_en_nonce'] ) ), 'wpdr_en_nonce' ) ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'wpdr-email-notice' ),
				'error_code' => 'F-11',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}

		// set user.
		if ( ! isset( $_POST['userid'] ) || ! wp_set_current_user( sanitize_text_field( wp_unslash( $_POST['userid'] ) ) ) instanceof WP_User ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'wpdr-email-notice' ),
				'error_code' => 'F-12',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}

		// set post_id.
		if ( ! isset( $_POST['post_id'] ) || ! get_post( sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) ) instanceof WP_Post ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'wpdr-email-notice' ),
				'error_code' => 'F-13',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}
		$id = sanitize_text_field( wp_unslash( $_POST['post_id'] ) );
		global $post;

		$del_rec = null;
		if ( isset( $_POST['del_rec'] ) ) {
			$del_rec = (int) sanitize_text_field( wp_unslash( $_POST['del_rec'] ) );
		}
		if ( is_null( $del_rec ) ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'wpdr-email-notice' ),
				'error_code' => 'F-14',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}

		$users_rec = get_post_meta( $id, 'wpdr_en_addressees', true );
		if ( false === $users_rec || empty( $users_rec ) ) {
			// list should exist.
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'wpdr-email-notice' ),
				'error_code' => 'F-15',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}

		$users_rec = json_decode( $users_rec, true );
		$rec_num   = $users_rec['rec_num'];
		$users     = $users_rec['users'];

		// remove entry.
		$removed = false;
		foreach ( $users as $key => $user ) {
			if ( $del_rec === $user['rec_num'] ) {
				unset( $users[ $key ] );
				$removed = true;
			}
		}
		// nothing removed.
		if ( ! $removed ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'wpdr-email-notice' ),
				'error_code' => 'F-16',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}

		// repack and update.
		$users_rec = array(
			'rec_num' => $rec_num,
			'users'   => $users,
		);
		$users_rec = wp_json_encode( $users_rec );
		update_post_meta( $id, 'wpdr_en_addressees', $users_rec );

		// generate new report.
		ob_start();
		self::ext_user_list( $id );
		$rpt = ob_get_clean();
		// json encode it.
		// phpcs:ignore
		$rpt = json_encode( $rpt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );

		// return success.
		$result = array(
			'error'      => false,
			'error_msg'  => $rpt,
			'error_code' => '',
		);
		header( 'Content-Type: application/json' );
		die( wp_json_encode( $result ) );
	}

	/**
	 * Hack for search box.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function search_list() {
		// Avoid being easily hacked.
		if ( ! isset( $_GET['wpdr_en_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wpdr_en_nonce'] ) ), 'wpdr_en_nonce' ) ) {
			$result = array(
				'error'               => true,
				'error_msg'           => __( 'Something went wrong', 'wpdr-email-notice' ),
				'logged_count'        => 0,
				'sent_count'          => 0,
				'sending_error_count' => 0,
				'error_code'          => 'F-21',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}
		$result = array();
		if ( isset( $_GET['post_id'] ) ) {
			$post_id = sanitize_text_field( wp_unslash( $_GET['post_id'] ) );
			// generate new report.
			ob_start();
			self::ext_user_list( $post_id );
			$rpt = ob_get_clean();
			// json encode it.
			// phpcs:ignore
			$rpt = json_encode( $rpt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );

			// return success.
			$result = array(
				'error'      => false,
				'error_msg'  => $rpt,
				'error_code' => '',
			);
		} else {
			// Post id was not available.
			$result = array(
				'error'      => true,
				'error_msg'  => 'Something went wrong',
				'error_code' => 'F-22',
			);
		}
		header( 'Content-Type: application/json' );
		die( wp_json_encode( $result ) );
	}

	/**
	 * Output the log css.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function admin_head() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = ( isset( $_GET['page'] ) ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) : false;
		if ( 'wpdr_en_notification_log' !== $page && 'wpdr_en_ext_notice_log' !== $page ) {
			return;
		}
		echo '<style>';
		echo '.wp-list-table .column-id { width: 5%; }';
		echo '</style>';
	}

	/**
	 * Output the log js.
	 *
	 * @since 3.1
	 * @return void
	 */
	private function enqueue_log_js() {
		// process log script.
		$suffix = ( WP_DEBUG ) ? '' : '.min';
		$path   = 'js/wpdr-en-log' . $suffix . '.js';
		$versn  = ( WP_DEBUG ) ? filemtime( plugin_dir_path( __DIR__ ) . $path ) : self::$version;
		wp_register_script(
			'wpdr_en_log',
			plugin_dir_url( __DIR__ ) . $path,
			array( 'jquery' ),
			$versn,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		// Improve security.
		wp_enqueue_script( 'wpdr_en_log' );
	}

	/**
	 * Output the log itself.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function notification_log_list() {
		if ( ! current_user_can( 'edit_documents' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpdr-email-notice' ) );
		}

		$this->enqueue_log_js();
		require_once __DIR__ . '/class-wpdr-en-user-log-table.php';

		// #TODO: add filter/search.
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Document Email Notification Log', 'wpdr-email-notice' ) . '</h1>';
		$wpdr_en_notification_log_table         = new WPDR_EN_User_Log_Table();
		$wpdr_en_notification_log_table->screen = convert_to_screen( null );
		$wpdr_en_notification_log_table->prepare_items();
		echo '<form method="get">';
		echo '<input type="hidden" name="post_type" value="doc_ext_list" />';
		echo '<input type="hidden" name="page" value="wpdr_en_notification_log" />';
		$wpdr_en_notification_log_table->search_box( esc_html__( 'Search', 'wpdr-email-notice' ), 'search_id' );
		echo '</form>';
		$wpdr_en_notification_log_table->display();
		echo '<p>' . esc_html__( 'Note: Success denotes that the mail was successfully received by your e-mail system.', 'wpdr-email-notice' ) . '</p>';
		echo '<p>' . esc_html__( 'The mail delivery process to the recipient is managed within your e-mail system.', 'wpdr-email-notice' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Output the external notice log itself.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function ext_notice_log_list() {
		if ( ! current_user_can( 'edit_documents' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpdr-email-notice' ) );
		}

		$this->enqueue_log_js();
		require_once __DIR__ . '/class-wpdr-en-ext-log-table.php';

		// #TODO: add filter/search.
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Document Email External Notice Log', 'wpdr-email-notice' ) . '</h1>';
		$wpdr_en_ext_notice_log_table         = new WPDR_EN_Ext_Log_Table();
		$wpdr_en_ext_notice_log_table->screen = convert_to_screen( null );
		$wpdr_en_ext_notice_log_table->prepare_items();
		echo '<form method="get">';
		echo '<input type="hidden" name="post_type" value="doc_ext_list" />';
		echo '<input type="hidden" name="page" value="wpdr_en_ext_notice_log" />';
		$wpdr_en_ext_notice_log_table->search_box( esc_html__( 'Search', 'wpdr-email-notice' ), 'search_id' );
		echo '</form>';
		$wpdr_en_ext_notice_log_table->display();
		echo '<p>' . esc_html__( 'Note: Success denotes that the mail was successfully received by your e-mail system.', 'wpdr-email-notice' ) . '</p>';
		echo '<p>' . esc_html__( 'The delivery process to the recipient is managed within your e-mail system.', 'wpdr-email-notice' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Output the external users for a list.
	 *
	 * @since 2.0
	 * @global WP_Post $post Post object.
	 *
	 * @param int $post_id ID of doc_ext_list element being updated.
	 * @return void
	 */
	private static function ext_user_list( $post_id = null ) {
		if ( ! current_user_can( 'edit_doc_ext_lists' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpdr-email-notice' ) );
		}

		if ( ! is_null( $post_id ) ) {
			global $post;
			if ( is_null( $post ) ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride
				$post = get_post( $post_id );
			}
		}

		require_once __DIR__ . '/class-wpdr-en-ext-user-table.php';
		$_SERVER['REQUEST_URI'] = add_query_arg( 'post', $post_id );

		// can be errors if built after ajax call.
		add_filter( 'set_url_scheme', array( __CLASS__, 'set_url_scheme' ), 10, 3 );
		echo '<div class="wrap" id="user-list">';
		echo '<h2 class="wp-heading-inline">' . esc_html__( 'User Addressee List', 'wpdr-email-notice' ) . '</h2>';
		$wpdr_en_ext_user_table = new WPDR_EN_Ext_User_Table();
		$wpdr_en_ext_user_table->prepare_items();
		$wpdr_en_ext_user_table->search_box( esc_html__( 'Search', 'wpdr-email-notice' ), 'search_id' );
		$wpdr_en_ext_user_table->display();
		echo '<input id="empty_addr" type="hidden" value="' . esc_attr( $wpdr_en_ext_user_table::$no_addr ) . '">';
		echo '</div>';
		remove_filter( 'set_url_scheme', array( __CLASS__, 'set_url_scheme' ), 10, 3 );
	}

	/**
	 * Filters the resulting URL after setting the scheme.
	 *
	 * @since 2.0
	 *
	 * @param string      $url         The complete URL including scheme and path.
	 * @param string      $scheme      Scheme applied to the URL. One of 'http', 'https', or 'relative'.
	 * @param string|null $orig_scheme Scheme requested for the URL. One of 'http', 'https', 'login',
	 *                                 'login_post', 'admin', 'relative', 'rest', 'rpc', or null.
	 */
	public static function set_url_scheme( $url, $scheme, $orig_scheme ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// did we come via ajax.
		if ( isset( $_SERVER['REQUEST_URI'] ) && '/wp-admin/admin-ajax.php' === $_SERVER['REQUEST_URI'] && isset( $_SERVER['HTTP_REFERER'] ) ) {
			// if so revert to post page.
			$url = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ), $scheme );
		}
		return $url;
	}

	/**
	 * Checks the resulting URL contains the post id if from search.
	 *
	 * Misuse of filter - but acts in a place where the Request URI can be set.
	 * May be no nonce, so don't look for it.
	 *
	 * @since 2.0
	 *
	 * @param bool    $use_block_editor Whether the post can be edited or not.
	 * @param WP_Post $post             The post being checked.
	 */
	public static function ensure_post_id( $use_block_editor, $post ) {
		global $action;
		if ( is_null( $post ) || 'doc_ext_list' !== $post->post_type || 'edit' !== $action ) {
			return $use_block_editor;
		}

		// Need to ensure post is is on the location.
		// phpcs:disable WordPress.Security.NonceVerification
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( isset( $_SERVER['REQUEST_URI'] ) && ! strpos( $_SERVER['REQUEST_URI'], 'action=edit' ) ) {
			// if so revert to post page.
			$_SERVER['REQUEST_URI'] = add_query_arg( 'post', $post->ID );
			$_SERVER['REQUEST_URI'] = add_query_arg( 'action', 'edit' );
			if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
				$_SERVER['REQUEST_URI'] = add_query_arg( 's', sanitize_text_field( wp_unslash( $_POST['s'] ) ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification
		return $use_block_editor;
	}

	/**
	 * Callback for metaboxes,.
	 *
	 * @since 2.0
	 * @global array $wp_meta_boxes
	 */
	public function meta_box_cb() {
		// replace standard excerpt metabox with adapted one.
		remove_meta_box( 'postexcerpt', 'doc_ext_list', 'normal' );
		add_meta_box( 'del_parms', __( 'List Attributes', 'wpdr-email-notice' ), array( &$this, 'del_attrs_meta_box' ), 'doc_ext_list', 'normal', 'high' );

		add_meta_box( 'doc_ext_list', __( 'Document External User List', 'wpdr-email-notice' ), array( &$this, 'doc_ext_list_metabox' ), 'doc_ext_list', 'normal', 'high' );
	}

	/**
	 * Metabox for user data,.
	 *
	 * @since 2.0
	 * @global WP_Post $post Post object.
	 */
	public function doc_ext_list_metabox() {
		if ( ! current_user_can( 'edit_doc_ext_lists' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpdr-email-notice' ) );
		}

		global $post;
		?>
		<div class="metabox-holder inside">
		<table id="user-entry" class="form-table" style="clear:none;">
			<tbody>
			<tr>
			<td>User Name</td>
			<td><input type="text" id="wpdr-en-user-name" name="user-name" value="" onblur="check_address()" /></td>
			</tr>
			<tr>
			<td>Email Address</td>
			<td><input type="text" id="wpdr-en-email" name="email" value="" onblur="check_address()" /></td>
			</tr>
			<tr>
			<td>Pause Mail</td>
			<td><input type="checkbox" id="wpdr_pause" name="pause" /></td>
			</tr>
			<tr>
			<td><div id="clear_address" name="clear_address" style="display:none;" >
			<input type="submit" class="button button-large" value="<?php echo esc_html_e( 'Clear values', 'wpdr-email-notice' ); ?>" onclick="wpdr_en_clear()"/>
			</div></td>
			<td><input type="submit" id="add_address" name="add_address" class="button button-large" value="<?php echo esc_html_e( 'Add Address to List', 'wpdr-email-notice' ); ?>" onclick="wpdr_en_insert()" disabled /></td>
			</tr>
			<tr>
			<td></td>
			<td><p id="wpdr-en-message"></p></td>
			</tr>
			</tbody>
		</table>
		<div id="current-list">
		<?php self::ext_user_list( $post->ID ); ?>
		</div>
		</div>
		<?php
	}

	/**
	 * Metabox for document list.
	 *
	 * Restyled excerpt metabox.
	 *
	 * @since 3.0
	 * @global WP_Post $post Post object.
	 */
	public function del_attrs_meta_box() {
		if ( ! current_user_can( 'edit_doc_ext_lists' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpdr-email-notice' ) );
		}

		global $post;
		$tm_rule = (int) get_post_meta( $post->ID, 'wpdr_en_tm', true );
		// find whether to attach doument - use site option as initial default.
		$meta   = get_post_meta( $post->ID, 'wpdr_en_attach', false );
		$attach = ( is_array( $meta ) && ! empty( $meta ) ? (int) $meta[0] : (int) get_option( 'wpdr_en_set_ext_attach' ) );
		$pause  = (int) get_post_meta( $post->ID, 'wpdr_en_pause', true );
		?>
		<h3 id="tm_label" class="hndle" style="padding-left: 0;"><?php esc_html_e( 'Match Rule', 'wpdr-email-notice' ); ?></h3>
		<div id="tm_descr"><?php esc_html_e( 'Choose whether any or all of this lists taxonomy elements must match those on the document to be considered a match.', 'wpdr-email-notice' ); ?></div>
		<div id="tm_ruled" role="radiogroup" aria-labelledby="tm_label" aria-describedby="tm_descr">
		<fieldset>
		<label><input type="radio" id="tm_any" name="tm_rule" <?php checked( 0, $tm_rule, true ); ?> value="0"><?php esc_html_e( 'Any taxonomy element', 'wpdr-email-notice' ); ?></label><br />
		<label><input type="radio" id="tm_all" name="tm_rule" <?php checked( 1, $tm_rule, true ); ?> value="1"><?php esc_html_e( 'All taxonomy elements', 'wpdr-email-notice' ); ?></label>
		</fieldset>
		</div>
		<h3 id="attach_label" class="hndle" style="padding-left: 0;"><?php esc_html_e( 'Document Attach', 'wpdr-email-notice' ); ?></h3>
		<fieldset>
		<input type="checkbox" id="wpdr_en_attach" name="wpdr_en_attach" value="<?php echo esc_attr( $attach ) . '" ' . checked( 1, $attach, false ); ?>>
		<label for="wpdr_en_attach"><?php esc_html_e( 'Attach Document to Notification E-mails', 'wpdr-email-notice' ); ?></label>
		</fieldset>
		<h3 id="pause_label" class="hndle" style="padding-left: 0;"><?php esc_html_e( 'Pause Mail', 'wpdr-email-notice' ); ?></h3>
		<fieldset>
		<input type="checkbox" id="wpdr_en_pause" name="wpdr_en_pause" value="<?php echo esc_attr( $pause ) . '" ' . checked( 1, $pause, false ); ?>>
		<label for="wpdr_en_pause"><?php esc_html_e( 'Pause this List', 'wpdr-email-notice' ); ?></label>
		</fieldset>
		<h3 id="note_label" class="hndle" style="padding-left: 0;"><?php esc_html_e( 'Document List Notes', 'wpdr-email-notice' ); ?></h3>
		<fieldset>
		<textarea rows="4" cols="40" name="excerpt" id="excerpt"><?php echo $post->post_excerpt; // phpcs:ignore. ?></textarea>
		<label class="screen-reader-text" for="excerpt"><?php esc_html_e( 'Document List Notes', 'wpdr-email-notice' ); ?></label>
		<?php esc_html_e( 'You can use this to hold any notes for this list entry.', 'wpdr-email-notice' ); ?>
		</fieldset>
		<?php
		// add existing taxonomy values (to identify differences).
		$taxs = get_object_taxonomies( 'doc_ext_list' );
		foreach ( $taxs as $tax ) {
			$tax_terms = get_the_terms( $post->ID, $tax );
			if ( is_array( $tax_terms ) && ! empty( $tax_terms ) ) {
				foreach ( $tax_terms as $term ) {
					echo '<input type="hidden" name="tax_save[' . esc_attr( $tax ) . '][]" value="' . esc_attr( $term->term_id ) . '">' . "\n";
				}
			}
		}
	}

	/* Functionality: Email from configuration */

	/**
	 * Replace default <wordpress@yourdomain.com> e-mail address.
	 *
	 * @since 1.0
	 * @param string $email Default From email address.
	 * @return string
	 */
	public function wp_mail_from( $email ) {
		// Get the site domain and get rid of www.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.WP.DeprecatedFunctions
		$sitename = strtolower( sanitize_url( $_SERVER['SERVER_NAME'] ) );
		if ( 'www.' === substr( $sitename, 0, 4 ) ) {
			$sitename = substr( $sitename, 4 );
		}
		// Override only default email address - provides compatibility with other plugins.
		if ( 'wordpress@' . $sitename === $email ) {
			return get_option( 'wpdr_en_set_email_from_address' );
		} else {
			return $email;
		}
	}

	/**
	 * Replace default e-mail from "WordPress".
	 *
	 * @since 1.0
	 * @param string $from_name From email address.
	 * @return string
	 */
	public function wp_mail_from_name( $from_name ) {
		// Override only default email from - provides compatibility with other plugins.
		if ( 'WordPress' === $from_name ) {
			return get_option( 'wpdr_en_set_email_from' );
		} else {
			return $from_name;
		}
	}

	/* Functionality: Notifications */

	/**
	 * Retrieve post status.
	 *
	 * @since 1.0
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function post_status( $post_id ) {
		$pstatus = get_post_status( $post_id );
		if ( 'publish' === $pstatus ) {
			if ( post_password_required( $post_id ) ) {
				return 'Password protected';
			} else {
				return 'Public';
			}
		} elseif ( 'private' === $pstatus ) {
			return 'Private';
		} else {
			return 'Do not notify';
		}
	}

	/**
	 * It extracts from $subject string a sub string marked by $delim character(s) $count times.
	 *
	 * @since 1.0
	 * @param string $subject Subject string.
	 * @param string $delim   Delimiter string.
	 * @param int    $count   Count.
	 * @return string
	 */
	private function substring_index( $subject, $delim, $count ) {
		if ( $count < 0 ) {
			return implode( $delim, array_slice( explode( $delim, $subject ), $count ) );
		} else {
			return implode( $delim, array_slice( explode( $delim, $subject ), 0, $count ) );
		}
	}
	/**
	 * Gets the document content as straight words (tags stripped).
	 *
	 * @since 3.1
	 * @param int $document_id     Post ID.
	 * @return string
	 */
	private function get_doc_words( $document_id ) {
		$content = get_post_field( 'post_content', $document_id );
		if ( is_numeric( $content ) ) {
			return '';
		}
		// remove document attachment comment.
		$content = preg_replace( '/<!-- WPDR \s*(\d+) -->/', '', $content );
		$content = preg_replace( '/\[(.*)/', '', preg_replace( '/\[(.*)\]/', '', preg_replace( '/\](.*)\[/', '][', wp_strip_all_tags( $content ) ) ) );
		return $content;
	}

	/**
	 * Number of words in a post.
	 *
	 * @since 3.1
	 * @param string $content Document content (stripped tags).
	 * @return int
	 */
	private function get_doc_word_count( $content ) {
		return count( explode( ' ', $content ) );
	}

	/*
	Tags available
	%title% means title of the post
	%permalink% means URL of the post
	%title_with_permalink% means URL with title of the post
	%author_name% means the name of the post author
	%excerpt% means excerpt of the post
	%words_n% means the first n (must be an integer number) number of word(s) extracted from the post
	%recipient_name% means display name of the user who receives the email
	%repeat% means output a sentence if the document has been previously e-mailed
	%extra% means output a mailing specific set of extra text.
	*/

	/**
	 * Resolve tags.
	 *
	 * @since 1.0
	 * @param string     $text       Template Text.
	 * @param int        $post_id    Post ID.
	 * @param int|string $user_id    User ID.or external user display name.
	 * @param string     $extra      Optional text to replace %extra%.
	 * @param string     $user_email User_mail address (only on external mails).
	 * @return string
	 */
	private function resolve_tags( $text, $post_id, $user_id, $extra, $user_email = null ) {
		$text = nl2br( $text );     // to keep line breaks.
		$text = str_replace( '%extra%', $extra, $text );
		$text = str_replace( '%title%', get_the_title( $post_id ), $text );
		$text = str_replace( '%permalink%', '<a href="' . get_permalink( $post_id ) . '">' . get_permalink( $post_id ) . '</a>', $text );
		$text = str_replace( '%title_with_permalink%', '<a href="' . get_permalink( $post_id ) . '">' . get_the_title( $post_id ) . '</a>', $text );
		$text = str_replace( '%author_name%', ucwords( get_userdata( get_post_field( 'post_author', $post_id ) )->display_name ), $text );
		$text = str_replace( '%excerpt%', ( current_user_can( 'edit_document', $post_id ) ? get_post_field( 'post_excerpt', $post_id ) : '' ), $text );
		$text = str_replace( '%recipient_name%', ucwords( ( is_null( $user_email ) ? get_userdata( $user_id )->display_name : $user_id ) ), $text );
		// %words_n%
		$nom = preg_match_all( '/\%words_\d+\%/', $text, $matches, PREG_OFFSET_CAPTURE );
		if ( $nom && count( (array) $nom ) > 0 ) {
			foreach ( $matches[0] as $key => $value ) {
				$tag = $value[0];
				$now = intval( substr( $tag, 7, strlen( $tag ) - 8 ) ); // number of words needed.
				if ( $now > 0 ) {
					$content = $this->get_doc_words( $post_id );
					if ( empty( $content ) ) {
						// Nothing to insert, just remove tag.
						$text = str_replace( $tag, '', $text );
					} elseif ( $this->get_doc_word_count( $content ) <= $now ) {
						// Check if content is longer than number of words needed and if so, add three dots.
						$text = str_replace( $tag, '<blockquote><em>' . $content . '</em></blockquote>', $text );
					} else {
						$text = str_replace( $tag, '<blockquote><em>' . $this->substring_index( $content, ' ', $now ) . '...</em></blockquote>', $text );
					}
				}
			}
		}
		$text = str_replace( '%repeat%', $this->resolve_repeat( $user_id, $post_id, $user_email ), $text );
		return $text;
	}

	/**
	 * Find the mail recipients.
	 *
	 * @since 1.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $post_id Document ID.
	 * @return object[]array
	 */
	private function prepare_mail_recipients( $post_id ) {
		$result = array();
		global $wpdb;
		$pstatus = $this->post_status( $post_id );

		if ( in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) ) {
			// Public & Password protected post users.
			if ( in_array( $pstatus, array( 'Public', 'Password protected' ), true ) ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$result = $wpdb->get_results(
					"SELECT u.id AS user_id,
						u.display_name,
						u.user_email as user_email
					 FROM {$wpdb->base_prefix}users u, 
						{$wpdb->base_prefix}usermeta um
					 WHERE u.id=um.user_id
					 AND um.meta_key='wpdr_en_user_notification'
					 AND um.meta_value=1"
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
			// Private post users.
			if ( in_array( $pstatus, array( 'Private' ), true ) ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$all_users_who_want_notifications = $wpdb->get_results(
					"SELECT u.id AS user_id, 
						u.display_name,
						u.user_email as user_email
					 FROM {$wpdb->base_prefix}users u, 
						{$wpdb->base_prefix}usermeta um
					 WHERE u.id=um.user_id
					 AND um.meta_key='wpdr_en_user_notification'
					 AND um.meta_value=1"
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				foreach ( $all_users_who_want_notifications as $value ) {
					if ( user_can( $value->user_id, 'read_document', $post_id ) ) {
						$user_data               = new StdClass();
						$user_data->user_id      = $value->user_id;
						$user_data->display_name = $value->display_name;
						$user_data->user_email   = $value->user_email;
						$result[]                = $user_data;
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Find the external mail recipients.
	 *
	 * @since 2.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int   $post_id Document ID.
	 * @param int[] $lists   Array of Ext_lists to process. (Empty means all matches).
	 * @param bool  $all     Full or simply to know of there are users.
	 * @return object[]array
	 */
	private function prepare_mail_ext_users( $post_id, $lists, $all ) {
		$result  = array();
		$pstatus = $this->post_status( $post_id );

		// Public post only.
		if ( 'Public' !== $pstatus ) {
			return $result;
		}

		// Can non-signed on user read it?
		/**
		 * Filter to force notification for external users for a not publically readable document.
		 *
		 * Special case for normally private site but where certain non-users may be sent documents..
		 *
		 * @since 2.0
		 * @param bool false    Expect normal permissions to determine accessibility.
		 * @param int  $post_id Post ID.
		 * @return boolean
		 */
		if ( apply_filters( 'wpdr_en_ext_force_notice', false, $post_id ) && ! user_can( 0, 'read_document', $post_id ) ) {
			return $result;
		}

		global $wpdb;
		// get the direct term taxonomy matches.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tt_ids = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_taxonomy_id
				 FROM {$wpdb->base_prefix}term_relationships tt
				 WHERE tt.object_id = %d",
				$post_id
			),
			ARRAY_A
		);
		// return if no terms.
		if ( empty( $tt_ids ) ) {
			return $result;
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// go up the hierarchical taxonomies until none returned (as parent terms could match).
		$tot_ids = $tt_ids;
		do {
			// get the parents.
			$list = implode( ',', wp_list_pluck( $tt_ids, 'term_taxonomy_id' ) );
			// Don't Use placeholders as prepare can get confused by comma-separated list.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$pp_ids = $wpdb->get_results(
				"SELECT p.term_taxonomy_id
				 FROM {$wpdb->base_prefix}term_taxonomy t
				 INNER JOIN {$wpdb->base_prefix}term_taxonomy p
				 ON  p.term_id = t.parent
				 AND p.taxonomy = t.taxonomy
				 WHERE t.term_taxonomy_id IN ( " . $list . ' )
				 AND   p.term_taxonomy_id NOT IN ( ' . $list . ' )
				 GROUP BY p.term_taxonomy_id',
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( ! empty( $pp_ids ) ) {
				$tt_ids  = $pp_ids;
				$tot_ids = array_merge( $tot_ids, $pp_ids );
			}
		} while ( ! empty( $pp_ids ) );

		// find the matching lists.
		$tlist = implode( ',', wp_list_pluck( $tot_ids, 'term_taxonomy_id' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$olists = $wpdb->get_results(
			"SELECT up.ID AS list_id,
					up.post_title as list_title,
					(SELECT meta_value
					 FROM {$wpdb->base_prefix}postmeta
					 WHERE post_id = up.ID
					 AND meta_key = 'wpdr_en_addressees') as users_rec,
					(SELECT meta_value
					 FROM {$wpdb->base_prefix}postmeta
					 WHERE post_id = up.ID
					 AND meta_key = 'wpdr_en_tm') as tm_rule,
					 COALESCE( (SELECT meta_value
					 FROM {$wpdb->base_prefix}postmeta
					 WHERE post_id = up.ID
					 AND meta_key = 'wpdr_en_attach'),
					 (SELECT option_value
					 FROM {$wpdb->base_prefix}options
					 WHERE option_name = 'wpdr_en_set_ext_attach'),
					 0 ) as attach,
					 COALESCE( (SELECT meta_value
					 FROM {$wpdb->base_prefix}postmeta
					 WHERE post_id = up.ID
					 AND meta_key = 'wpdr_en_pause'),
					 0 ) as pause,
					COUNT(1) as matches,
					(SELECT COUNT(1)
					 FROM {$wpdb->base_prefix}term_relationships tr
					 WHERE object_id = up.ID) as tot_terms
			 FROM {$wpdb->base_prefix}posts up
			 INNER JOIN {$wpdb->base_prefix}term_relationships ut
			 ON ut.object_id = up.ID
			 WHERE up.post_type = 'doc_ext_list'
			 AND up.post_status = 'publish'
			 AND ut.term_taxonomy_id IN ( " . $tlist . ' )
			 GROUP BY up.ID
			 HAVING (tm_rule = 0 OR matches = tot_terms) AND pause = 0',
			ARRAY_A
		);

		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// for putting in metabox, we just need to know if there are entries.
		// Note. earliest point where we know there is a result.
		if ( ! $all ) {
			return $olists;
		}

		// now build the recipient list.
		foreach ( $olists as $list ) {
			// skip over missing lists for results.
			if ( ! empty( $lists ) && ! in_array( (int) $list['list_id'], $lists, true ) ) {
				continue;
			}
			$users_rec = json_decode( $list['users_rec'], true );
			$users     = $users_rec['users'];
			foreach ( $users as $user ) {
				// ignore if paused.
				if ( ! array_key_exists( 'pause', $user ) || 0 === $user['pause'] ) {
					$result[] = (object) array(
						'list_id'    => $list['list_id'],
						'attach'     => $list['attach'],
						'rec_num'    => $user['rec_num'],
						'user_name'  => $user['user_name'],
						'user_email' => $user['email'],
					);
				}
			}
		}
		return $result;
	}

	/**
	 * It puts together the mail subject
	 *
	 * @since 1.0
	 * @param int    $post_id    Post ID.
	 * @param int    $user_id    User ID.
	 * @param string $user_email User_mail address (only on external mails).
	 * @return string
	 */
	private function prepare_mail_subject( $post_id, $user_id, $user_email = null ) {
		$template = get_option( 'wpdr_en_set_subject' );
		// Avoid characters like dashes replaced with html numeric character references.
		remove_filter( 'the_title', 'wptexturize' );
		if ( empty( $template ) ) {
			$template = __( 'New document: ', 'wpdr-email-notice' ) . get_the_title( $post_id );
		}
		// %extra% is not supported herev as makes no sense.
		$subject = wp_strip_all_tags( $this->resolve_tags( $template, $post_id, $user_id, '', $user_email ) );
		/**
		 * Filter to ensure that the mail subject does not end in a number.
		 *
		 * Some spam checkers assign a penalty if the subject ends with a number, so by default add a period.
		 *
		 * @since 1.0
		 * @param bool true     Default is to add a period if subject ends with a number.
		 * @param int  $post_id Post ID.
		 * @return boolean
		 */
		if ( apply_filters( 'wpdr_en_subject_trailing_number', true, $post_id ) && preg_match( '/.*[0-9]$/', $subject ) ) {
			$subject .= '.';
		}

		// Revert to default mode.
		add_filter( 'the_title', 'wptexturize' );
		return $subject;
	}

	/**
	 * It puts together the mail content
	 *
	 * @since 1.0
	 * @param int    $post_id    Post ID.
	 * @param int    $user_id    User ID.
	 * @param string $extra      Text string to be matched with %extra%.
	 * @param string $user_email User_mail address (only on external mails).
	 * @return string
	 */
	private function prepare_mail_content( $post_id, $user_id, $extra, $user_email = null ) {
		$template = $this->get_content( ( is_null( $user_email ) || empty( $user_email ) ? -1 : 0 ) );
		$content  = '<html><body>' . $this->resolve_tags( $template, $post_id, $user_id, $extra, $user_email ) . '</body></html>';

		return $content;
	}

	/**
	 * Insert extra .
	 *
	 * @since 1.0
	 * @param int    $post_id Post ID.
	 * @param string $extra   Text string to be matched with %extra%.
	 * @return int||null
	 */
	private function insert_extra( $post_id, $extra = null ) {
		if ( empty( $extra ) ) {
			return null;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			"{$wpdb->prefix}wpdr_en_extra_text",
			array(
				'post_id'    => $post_id,
				'extra_text' => $extra,
			),
			array(
				'%d',
				'%s',
			),
		);
		return $wpdb->insert_id;
	}

	/**
	 * Send out emails.
	 *
	 * @since 1.0
	 * @param int    $post_id Post ID.
	 * @param string $extra   Text string to be matched with %extra%.
	 * @return int[]
	 */
	private function send_mail( $post_id, $extra = null ) {
		$logged_count        = 0;
		$sent_count          = 0;
		$sending_error_count = 0;
		$result              = array(
			'logged_count'        => $logged_count,
			'sent_count'          => $sent_count,
			'sending_error_count' => $sending_error_count,
		);

		$recipients = $this->prepare_mail_recipients( $post_id );
		if ( empty( $recipients ) ) {
			return $result;
		}

		// insert extra into its table.
		$extra_id = $this->insert_extra( $extra );

		// post considered no protected if field is empty.
		$no_password = empty( get_post_field( 'post_password', $post_id ) );
		foreach ( $recipients as $value ) {
			// Make sure accented characters sent out correctly as well.
			$mail_subject = mb_encode_mimeheader( $this->prepare_mail_subject( $post_id, $value->user_id ), 'UTF-8' );
			$mail_content = $this->prepare_mail_content( $post_id, $value->user_id, $extra, null );
			// if there is no subject or content then do not send mail.
			if ( empty( $mail_subject ) || empty( $mail_content ) ) {
				if ( $this->log_mail_sent( $value->user_id, $post_id, $value->user_email, __( 'Empty mail subject and/or content.', 'wpdr-email-notice' ), $extra_id ) ) {
					++$sending_error_count;
					++$logged_count;
				} else {
					++$sending_error_count;
				}
			} else {
				// Is email attachment wanted.
				$attachments = array();
				if ( (bool) get_user_meta( $value->user_id, 'wpdr_en_user_attachment', true ) ) {
					// no password protection and user can read it.
					if ( $no_password && user_can( $value->user_id, 'read_document', $post_id ) ) {
						$attachments = $this->get_attachment( $post_id );
					}
					if ( empty( $attachments ) ) {
						$mail_content .= '<p>' . __( 'Document not attached.', 'wpdr-email-notice' ) . '</p>';
						/**
						 * Filters whether to attach the file depending on its size and type of user.
						 *
						 * @param bool true      default is to send the file.
						 * @param int  $filesize attachment file size.
						 * @param bool $internal indicates whether the notification is being sent to internal users..
						 */
					} elseif ( apply_filters( 'wpdr_en_filesize', true, filesize( $attachments[0] ), true ) ) {
						$mail_content .= '<p>' . __( 'Document attached.', 'wpdr-email-notice' ) . '</p>';
					} else {
						$mail_content .= '<p>' . __( 'Document too large to be attached.', 'wpdr-email-notice' ) . '</p>';
						$attachments   = array();
					}
				}
				/**
				 * Filters whether to actually send the email - useful for setup testing.
				 *
				 * There will be a log entry so this can be used for testing.
				 *
				 * @param bool false default is to send the file.
				 */
				if ( apply_filters( 'wpdr_en_no_send_email', false ) ) {
					// test sending process.
					$status_text = __( 'Test', 'wpdr-email-notice' ) . ' ' . ( empty( $attachments ) ? __( 'Successful', 'wpdr-email-notice' ) : __( 'Success Attachment', 'wpdr-email-notice' ) );
					++$sent_count;
				} else {
					// Set mail type.
					$headers = array( 'Content-type: text/html' );

					// Send mail.
					$mail_status = wp_mail( $value->display_name . '<' . $value->user_email . '>', $mail_subject, $mail_content, $headers, $attachments );

					// set status message.
					if ( $mail_status ) {
						$status_text = ( empty( $attachments ) ? __( 'Successful', 'wpdr-email-notice' ) : __( 'Success Attachment', 'wpdr-email-notice' ) );
						++$sent_count;
					} else {
						$status_text = __( 'Failed', 'wpdr-email-notice' );
						++$sending_error_count;
					}
					$this->mail_delay();
				}
				// Add log entry.
				if ( $this->log_mail_sent( $value->user_id, $post_id, $value->user_email, $status_text, $extra_id ) ) {
					++$logged_count;
				}
			}
		}
		// did we create a temporary attachment.
		if ( ! empty( self::$attach_file ) ) {
			wp_delete_file( self::$attach_file[0] );
			self::$attach_file = '';
		}

		$result = array(
			'logged_count'        => $logged_count,
			'sent_count'          => $sent_count,
			'sending_error_count' => $sending_error_count,
		);
		return $result;
	}

	/**
	 * Send out external emails.
	 *
	 * @since 2.0
	 * @param int    $post_id Post ID.
	 * @param int[]  $lists   Array of Ext_lists to process. (Empty means all matches).
	 * @param string $extra   Text string to be matched with %extra%.
	 * @return int[]
	 */
	private function send_ext_mail( $post_id, $lists, $extra ) {
		$logged_count        = 0;
		$sent_count          = 0;
		$sending_error_count = 0;
		$result              = array(
			'logged_count'        => $logged_count,
			'sent_count'          => $sent_count,
			'sending_error_count' => $sending_error_count,
		);

		$recipients = $this->prepare_mail_ext_users( $post_id, $lists, true );

		if ( empty( $recipients ) ) {
			return $result;
		}

		// insert extra into its table.
		$extra_id = $this->insert_extra( $post_id, $extra );

		// post considered no protected if field is empty.
		$no_password = empty( get_post_field( 'post_password', $post_id ) );
		foreach ( $recipients as $value ) {
			// Make sure accented characters sent out correctly as well.
			$mail_subject = mb_encode_mimeheader( $this->prepare_mail_subject( $post_id, $value->user_name, $value->user_email ), 'UTF-8' );
			$mail_content = $this->prepare_mail_content( $post_id, $value->user_name, $extra, $value->user_email );
			// if there is no subject or content then do not send mail.
			if ( empty( $mail_subject ) || empty( $mail_content ) ) {
				$status = __( 'Empty mail subject and/or content.', 'wpdr-email-notice' );
				if ( $this->log_mail_sent( $value->user_name, $post_id, $value->user_email, $status, $extra_id, $value->list_id ) ) {
					++$sending_error_count;
					++$logged_count;
				} else {
					++$sending_error_count;
				}
			} else {
				// Is email attachment wanted.
				$attachments = array();
				if ( (bool) $value->attach ) {
					$attachments = $this->get_attachment( $post_id );
					if ( empty( $attachments ) ) {
						$mail_content .= '<p>' . __( 'Document not attached.', 'wpdr-email-notice' ) . '</p>';
						/**
						 * Filters whether to attach the file depending on its size and type of user.
						 *
						 * @param bool true      default is to send the file.
						 * @param int  $filesize attachment file size.
						 * @param bool $internal indicates whether the notification is being sent to internal users..
						 */
					} elseif ( apply_filters( 'wpdr_en_filesize', true, filesize( $attachments[0] ), false ) ) {
						$mail_content .= '<p>' . __( 'Document attached.', 'wpdr-email-notice' ) . '</p>';
					} else {
						$mail_content .= '<p>' . __( 'Document too large to be attached.', 'wpdr-email-notice' ) . '</p>';
						$attachments   = array();
					}
				}

				/**
				 * Filters whether to actually send the email.
				 *
				 * There will be a log entry so this can be used for testing.
				 *
				 * @param bool false default is to send the file.
				 */
				if ( apply_filters( 'wpdr_en_no_send_email', false ) ) {
					// test sending process.
					$status_text = __( 'Test', 'wpdr-email-notice' ) . ' ' . ( empty( $attachments ) ? __( 'Successful', 'wpdr-email-notice' ) : __( 'Success Attachment', 'wpdr-email-notice' ) );
					++$sent_count;
				} else {
					// Set mail type and how sent.
					$headers = array(
						'Content-type: text/html',
						'X-wpdr-en: ' . $post_id . '-' . $value->list_id . '-' . $value->rec_num,
					);

					// Send mail.
					$mail_status = wp_mail( $value->user_name . '<' . $value->user_email . '>', $mail_subject, $mail_content, $headers, $attachments );

					// set status message.
					if ( $mail_status ) {
						$status_text = ( empty( $attachments ) ? __( 'Successful', 'wpdr-email-notice' ) : __( 'Success Attachment', 'wpdr-email-notice' ) );
						++$sent_count;
					} else {
						$status_text = __( 'Failed', 'wpdr-email-notice' );
						++$sending_error_count;
					}
					$this->mail_delay();
				}
				// Add log entry.
				if ( $this->log_mail_sent( $value->user_name, $post_id, $value->user_email, $status_text, $extra_id, $value->list_id ) ) {
					++$logged_count;
				}
			}
		}

		// did we create a temporary attachment.
		if ( ! empty( self::$attach_file ) ) {
			wp_delete_file( self::$attach_file[0] );
			self::$attach_file = '';
		}

		$result = array(
			'logged_count'        => $logged_count,
			'sent_count'          => $sent_count,
			'sending_error_count' => $sending_error_count,
		);
		return $result;
	}

	/**
	 * Add small delay after mail sent to not flood system.
	 *
	 * @since 2.0
	 */
	private function mail_delay() {
		/**
		 * Filters the delay time introduced to avoid flooding the mail system.
		 *
		 * @since 1.0
		 * @param int 50000  default delay time (0.05 sec).
		 */
		usleep( apply_filters( 'wpdr_en_mail_delay', 50000 ) );
	}

	/**
	 * Add log entry.
	 *
	 * @since 1.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int|string $user_id    User ID.
	 * @param int        $post_id    Post ID.
	 * @param string     $user_email User email address.
	 * @param string     $status     Status text.
	 * @param int||null  $extra_id   Id of extra text string in DB (or nulll).
	 * @param int        $list_id    post_id of list for external mail.
	 * @return string
	 */
	private function log_mail_sent( $user_id, $post_id, $user_email, $status, $extra_id, $list_id = null ) {
		$result = false;
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( is_null( $list_id ) ) {
			$result = $wpdb->insert(
				$wpdb->prefix . 'wpdr_notification_log',
				array(
					'user_id'        => $user_id,
					'post_id'        => $post_id,
					'time_mail_sent' => current_time( 'mysql' ),
					'user_email'     => $user_email,
					'extra_text_id'  => $extra_id,
				)
			);
		} else {
			$result = $wpdb->insert(
				$wpdb->prefix . 'wpdr_ext_notice_log',
				array(
					'post_id'         => $post_id,
					'doc_ext_list_id' => $list_id,
					'time_mail_sent'  => current_time( 'mysql' ),
					'user_name'       => $user_id,
					'user_email'      => $user_email,
					'status'          => $status,
					'extra_text_id'   => $extra_id,
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $result;  // if false then insert failed.
	}

	/**
	 * Determine if post has been previously sent..
	 *
	 * @since 2.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int    $user_id    User ID.
	 * @param int    $post_id    Post ID.
	 * @param string $user_email User_mail address (only on external mails).
	 * @return mixed[]|false
	 */
	private function resolve_repeat( $user_id, $post_id, $user_email = null ) {
		global $wpdb;
		// Ignore test mails in log.
		$test = __( 'Test', 'wpdr-email-notice' ) . ' %';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		if ( is_null( $user_email ) ) {
			$prev = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(1), MAX(time_mail_sent) FROM {$wpdb->prefix}wpdr_notification_log" .
					' WHERE user_id = %d AND post_id = %d AND status NOT LIKE %s GROUP BY user_id, post_id',
					$user_id,
					$post_id,
					$test
				),
				ARRAY_N
			);
		} else {
			$prev = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(1), MAX(time_mail_sent) FROM {$wpdb->prefix}wpdr_ext_notice_log" .
					' WHERE user_email = %d AND post_id = %d AND status NOT LIKE %s GROUP BY user_email, post_id',
					$user_email,
					$post_id,
					$test
				),
				ARRAY_N
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( is_array( $prev ) ) {
			$repeat = get_option( 'wpdr_en_set_repeat' );
			// Apply default template if no repeat setting present.
			if ( empty( $repeat ) ) {
				$repeat = self::$default_repeat;
			}
			$repeat = str_replace( '%num%', $prev[0], $repeat );
			$repeat = str_replace( '%last_date%', substr( $prev[1], 0, 10 ), $repeat );
			$repeat = str_replace( '%last_time%', $prev[1], $repeat );
			return $repeat;
		} else {
			return '';
		}
	}

	/**
	 * Delete related log entries when post is deleted.
	 *
	 * WordPress sometimes does re-use post IDs and without deleting the log entires this could cause confusion.
	 *
	 * @since 1.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_log_entry_on_post_delete( $post_id ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		global $wpdb;
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . $wpdb->prefix . 'wpdr_notification_log WHERE post_id = %d', $post_id ) ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'wpdr_notification_log WHERE post_id = %d', $post_id ) );
		}
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . $wpdb->prefix . 'wpdr_ext_notice_log WHERE post_id = %d', $post_id ) ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'wpdr_ext_notice_log WHERE post_id = %d', $post_id ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
	/**
	 * Delete related log entries when user is deleted.
	 *
	 * WordPress sometimes does re-use post IDs and without deleting the log entires this could cause confusion.
	 *
	 * @since 1.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int     $user_id  Deleted user ID.
	 * @param int     $reassign Reassign user ID.
	 * @param WP_User $user     Deleted user object.
	 * @return void
	 */
	public function delete_log_entry_on_user_delete( $user_id, $reassign, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		global $wpdb;
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ' . $wpdb->prefix . 'wpdr_notification_log WHERE user_id = %d', $user_id ) ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'wpdr_notification_log WHERE user_id = %d', $user_id ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Create Notification result set.
	 *
	 * @since 1.0
	 * @param int    $post_id Post ID.
	 * @param string $extra   Text string to be matched with %extra%.
	 * @return string[]
	 */
	public function notify( $post_id, $extra ) {
		$result              = array();
		$mails               = $this->send_mail( $post_id, $extra );
		$logged_count        = intval( $mails['logged_count'] );
		$sent_count          = intval( $mails['sent_count'] );
		$sending_error_count = intval( $mails['sending_error_count'] );
		$log_page_url        = admin_url( 'edit.php?post_type=doc_ext_list&page=wpdr_en_notification_log' );
		// to debug uncomment the following.

		// phpcs:disable
		/*
		//Test data
		$logged_count=<num_value>;
		$sent_count=<num_value>;
		$sending_error_count=<num_value>;
		*/
		// phpcs:enable

		// Not logged or sent out successfully none.
		if ( 0 === $logged_count || 0 === $sent_count ) {
			$result = array(
				'error'               => true,
				'error_msg'           => __( 'Not logged and/or sent out successfully no mails.', 'wpdr-email-notice' ),
				'logged_count'        => $logged_count,
				'sent_count'          => $sent_count,
				'sending_error_count' => $sending_error_count,
				'error_code'          => 'F-03',
				'log_page_url'        => $log_page_url,
			);
		} else {
			if ( $logged_count === $sent_count && 0 === $sending_error_count ) {
				$result = array(
					'error'               => false,
					'logged_count'        => $logged_count,
					'sent_count'          => $sent_count,
					'sending_error_count' => $sending_error_count,
					'log_page_url'        => $log_page_url,
				);
			} else {
				$result = array(
					'error'               => true,
					'error_msg'           => __( 'Something went wrong', 'wpdr-email-notice' ),
					'logged_count'        => $logged_count,
					'sent_count'          => $sent_count,
					'sending_error_count' => $sending_error_count,
					'error_code'          => 'F-04',
					'log_page_url'        => $log_page_url,
				);
			}

			// Note that post notification sent.
			$current_status = (string) get_post_meta( $post_id, 'wpdr_en_notification_sent', true );
			if ( '0' === $current_status || empty( $current_status ) ) {
				$updated = update_post_meta( $post_id, 'wpdr_en_notification_sent', '1' );
				if ( ! $updated ) {
					$result = array(
						'error'               => true,
						'error_msg'           => __( 'Something went wrong', 'wpdr-email-notice' ),
						'logged_count'        => $logged_count,
						'sent_count'          => $sent_count,
						'sending_error_count' => $sending_error_count,
						'error_code'          => 'F-05',
						'log_page_url'        => $log_page_url,
					);
				}
			}
		}

		// if a temporary file was created then delete it.
		if ( ! empty( self::$attach_file ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_delete_file( self::$attach_file[0] );
			self::$attach_file = null;
		}

		return $result;
	}

	/**
	 * Create External Notice result set.
	 *
	 * @since 2.0
	 * @param int    $post_id Post ID.
	 * @param int[]  $lists   Array of Ext_lists to process. (Empty means all matches).
	 * @param string $extra   Text string to be matched with %extra%.
	 * @return string[]
	 */
	public function ext_notice( $post_id, $lists, $extra ) {
		$result              = array();
		$mails               = $this->send_ext_mail( $post_id, $lists, $extra );
		$logged_count        = intval( $mails['logged_count'] );
		$sent_count          = intval( $mails['sent_count'] );
		$sending_error_count = intval( $mails['sending_error_count'] );
		$log_page_url        = admin_url( 'edit.php?post_type=doc_ext_list&page=wpdr_en_ext_notice_log' );
		// to debug uncomment the following.

		// phpcs:disable
		/*
		//Test data
		$logged_count=<num_value>;
		$sent_count=<num_value>;
		$sending_error_count=<num_value>;
		*/
		// phpcs:enable

		// Not logged or sent out successfully any.
		if ( 0 === $logged_count || 0 === $sent_count ) {
			$result = array(
				'error'               => true,
				'error_msg'           => __( 'Not logged and/or sent out successfully any mails.', 'wpdr-email-notice' ),
				'logged_count'        => $logged_count,
				'sent_count'          => $sent_count,
				'sending_error_count' => $sending_error_count,
				'error_code'          => 'F-03',
				'log_page_url'        => $log_page_url,
			);
		} else {
			if ( $logged_count === $sent_count && 0 === $sending_error_count ) {
				$result = array(
					'error'               => false,
					'logged_count'        => $logged_count,
					'sent_count'          => $sent_count,
					'sending_error_count' => $sending_error_count,
					'log_page_url'        => $log_page_url,
				);
			} else {
				$result = array(
					'error'               => true,
					'error_msg'           => __( 'Something went wrong', 'wpdr-email-notice' ),
					'logged_count'        => $logged_count,
					'sent_count'          => $sent_count,
					'sending_error_count' => $sending_error_count,
					'error_code'          => 'F-04',
					'log_page_url'        => $log_page_url,
				);
			}

			// Note that post notification sent.
			$current_status = (string) get_post_meta( $post_id, 'wpdr_en_ext_notice_sent', true );
			if ( '0' === $current_status || empty( $current_status ) ) {
				$updated = update_post_meta( $post_id, 'wpdr_en_ext_notice_sent', '1' );
				if ( ! $updated ) {
					$result = array(
						'error'               => true,
						'error_msg'           => __( 'Something went wrong', 'wpdr-email-notice' ),
						'logged_count'        => $logged_count,
						'sent_count'          => $sent_count,
						'sending_error_count' => $sending_error_count,
						'error_code'          => 'F-05',
						'log_page_url'        => $log_page_url,
					);
				}
			}
		}

		// if a temporary file was created then delete it.
		if ( ! empty( self::$attach_file ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_delete_file( self::$attach_file[0] );
			self::$attach_file = null;
		}

		return $result;
	}

	/**
	 * Sending manual notifications
	 *
	 * @since 1.0
	 * @return void
	 */
	public function send_notification_manual() {
		// Avoid being easily hacked.
		if ( ! isset( $_POST['wpdr_en_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdr_en_nonce'] ) ), 'wpdr_en_nonce' ) ) {
			$result = array(
				'error'               => true,
				'error_msg'           => __( 'Something went wrong', 'wpdr-email-notice' ),
				'logged_count'        => 0,
				'sent_count'          => 0,
				'sending_error_count' => 0,
				'error_code'          => 'F-02',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}
		$result = array();
		if ( isset( $_POST['post_id'] ) ) {
			$post_id = sanitize_text_field( wp_unslash( $_POST['post_id'] ) );
			$extra   = sanitize_text_field( wp_unslash( $_POST['extra'] ) ); // phpcs:ignore
			$extra   = wp_kses_post( $extra );
			$result  = $this->notify( $post_id, $extra );
		} else {
			// Post id was not available.
			$result = array(
				'error'      => true,
				'error_msg'  => 'Something went wrong',
				'error_code' => 'F-06',
			);
		}
		header( 'Content-Type: application/json' );
		die( wp_json_encode( $result ) );
	}

	/**
	 * Sending manual notifications to external users.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function send_ext_notice_manual() {
		// Avoid being easily hacked.
		if ( ! isset( $_POST['wpdr_en_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdr_en_nonce'] ) ), 'wpdr_en_nonce' ) ) {
			$result = array(
				'error'               => true,
				'error_msg'           => __( 'Something went wrong', 'wpdr-email-notice' ),
				'logged_count'        => 0,
				'sent_count'          => 0,
				'sending_error_count' => 0,
				'error_code'          => 'F-02',
			);
			header( 'Content-Type: application/json' );
			die( wp_json_encode( $result ) );
		}

		// input validated.
		$result = array();
		if ( isset( $_POST['post_id'] ) ) {
			$post_id = sanitize_text_field( wp_unslash( $_POST['post_id'] ) );
			// if all lists wanted then no lists array found.
			if ( isset( $_POST['lists'] ) ) {
				$lists = wp_parse_id_list( wp_unslash( $_POST['lists'] ) );
			} else {
				$lists = array();
			}
			// Get the extra text (if exists).
			$extra = ( isset( $_POST['extra'] ) ? wp_kses_post( wp_unslash( $_POST['extra'] ) ) : '' );

			$result = $this->ext_notice( $post_id, $lists, $extra );
		} else {
			// Post id was not available.
			$result = array(
				'error'      => true,
				'error_msg'  => 'Something went wrong',
				'error_code' => 'F-06',
			);
		}
		header( 'Content-Type: application/json' );
		die( wp_json_encode( $result ) );
	}

	/**
	 * Sending automatic notifications and display admin notice
	 *
	 * @since 1.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function send_notification_auto( $post_id ) {
		// ignore whilst doing autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// remove attachment file if set.
		if ( ! empty( self::$attach_file ) ) {
			wp_delete_file( self::$attach_file[0] );
			self::$attach_file = '';
		}

		if ( empty( $post_id ) ) {
			return;
		}

		if ( get_option( 'wpdr_en_set_notification_mode' ) !== 'Auto' ) {
			return;
		}
		$notification_sent = get_post_meta( $post_id, 'wpdr_en_notification_sent', true );
		$pstatus           = $this->post_status( $post_id );
		$recipients        = $this->prepare_mail_recipients( $post_id );
		$hasrecipient      = ! empty( $recipients );
		if ( in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) && ( empty( $notification_sent ) || 0 === $notification_sent ) && $hasrecipient ) {
			$message = null;
			$result  = array();
			add_post_meta( $post_id, 'wpdr_en_notification_sent', '0', true );
			// No extra text possible here.
			$result = $this->notify( $post_id, '' );

			if ( $result['error'] ) {
				// Error.
				if ( 0 === $result['logged_count'] || 0 === $result['sent_count'] ) {
					$message = '<br>' . __( 'Error sending notifications.', 'wpdr-email-notice' ) . '<a href="' . $result['log_page_url'] . '">' . __( 'Check log', 'wpdr-email-notice' ) . '</a> ' . __( 'for details.', 'wpdr-email-notice' );
				} else {
					// Warning.
					set_transient( 'auto_notification_result', 'update-nag' );
					$message = '<br>' . $result['sent_count'] . ' ' . __( 'email(s) out of', 'wpdr-email-notice' ) . ' ' . ( $result['sent_count'] + $result['sending_error_count'] ) . ' ' . __( 'sent with', 'wpdr-email-notice' ) . ' ' . ( $result['sent_count'] + $result['sending_error_count'] - $result['logged_count'] ) . ' ' . __( 'log issues.', 'wpdr-email-notice' ) . '<a href="' . $result['log_page_url'] . '">' . __( 'Check log', 'wpdr-email-notice' ) . '</a> ' . __( 'for details.', 'wpdr-email-notice' );
				}
			} else {
				// Emails sent successfully.
				set_transient( 'auto_notification_result', 'updated' );
				$message = '<br>' . $result['sent_count'] . ' ' . __( 'notification(s) sent.', 'wpdr-email-notice' ) . ' <a href="' . $result['log_page_url'] . '">' . __( 'Check log', 'wpdr-email-notice' ) . '</a> ' . __( 'for details.', 'wpdr-email-notice' );
			}
			set_transient( 'auto_notification_message', $message );
			remove_action( 'transition_post_status', array( $this, 'send_notification_auto' ) );

			// remove attachment file if set.
			if ( ! empty( self::$attach_file ) ) {
				wp_delete_file( self::$attach_file[0] );
				self::$attach_file = '';
			}
		} else {
			delete_transient( 'auto_notification_message' );
			delete_transient( 'auto_notification_result' );
		}
	}

	/**
	 * Display automatic notifications on admin notice.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function admin_notice_auto_notification() {
		$message = get_transient( 'auto_notification_message' );
		if ( ! empty( $message ) ) {
			echo '<div class="' . esc_attr( get_transient( 'auto_notification_result' ) ) . '" id="wpdr_en_notification_div">';
			echo '<p>' . esc_html( $message ) . '</p>';
			echo '</div>';
			delete_transient( 'auto_notification_message' );
			delete_transient( 'auto_notification_result' );
		}
	}

	/**
	 * Find the attachment details (and hold in cache).
	 *
	 * @since 1.0
	 * @param int $post_id  Post ID.
	 * @return string | string[]
	 */
	private function get_attachment( $post_id ) {
		if ( empty( self::$attach_file ) ) {
			$file      = array();
			$content   = get_post_field( 'post_content', $post_id );
			$attach_id = null;

			/* Load of code copied directly from WPDR */

			global $wpdr;
			if ( ! $wpdr && class_exists( 'WP_Document_Revisions' ) ) {
				$wpdr = new WP_Document_Revisions();
			}
			$attach_id = $wpdr->extract_document_id( $content );
			if ( ! is_null( $attach_id ) ) {
				$afile = get_attached_file( $attach_id );

				// flip slashes for WAMP settups to prevent 404ing on the next line.
				/**
				 * Filters the file name for WAMP settings (filter routine provided by WPDR plugin).
				 *
				 * @param string $afile attached file name.
				 */
				$afile = apply_filters( 'document_path', $afile );

				// We need to change the file name back to a readable one, so copy it.
				$file_name = get_post_field( 'post_name', $post_id );
				$file_ext  = pathinfo( $afile, PATHINFO_EXTENSION );
				$nfile     = get_temp_dir() . $file_name . '.' . $file_ext;
				// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
				if ( @copy( $afile, $nfile ) ) {
					$file[] = $nfile;
				}
				// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
			}
			self::$attach_file = $file;
		}

		return self::$attach_file;
	}

	/**
	 * Callback to unlink previously loaded document (should do nothing).
	 *
	 * @since 2.0
	 * @param int $doc_id the ID of the post being edited.
	 */
	public function save_document( $doc_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// ignore whilst doing autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! empty( self::$attach_file ) ) {
			wp_delete_file( self::$attach_file[0] );
			self::$attach_file = '';
		}
	}

	/**
	 * Output the match rule, attach and pause column titles.
	 *
	 * @since 2.0
	 * @param array $defaults the default column labels.
	 * @returns array the modified column labels
	 */
	public function add_meta_columns( $defaults ) {
		$output = array_slice( $defaults, 0, 2 );
		// splice in columns..
		$output['tm_rule']        = __( 'Match Rule', 'wpdr-email-notice' );
		$output['wpdr_en_attach'] = __( 'Document Attach', 'wpdr-email-notice' );
		$output['wpdr_en_pause']  = __( 'List Paused', 'wpdr-email-notice' );

		// get the rest of the columns.
		$output = array_merge( $output, array_slice( $defaults, 2 ) );

		return $output;
	}

	/**
	 * Output the match rule, attach and pause column post values.
	 *
	 * @since 2.0
	 * @param string $column_name the name of the column being propagated.
	 * @param int    $post_id the ID of the post being displayed.
	 */
	public function del_column_data( $column_name, $post_id ) {
		if ( 'tm_rule' === $column_name ) {
			$tm_rule = (int) get_post_meta( $post_id, 'wpdr_en_tm', true );
			echo '<input type="hidden" id="tm-rule-' . esc_attr( $post_id ) . '" value ="' . esc_attr( $tm_rule ) . '" >';
			( 0 === $tm_rule ? esc_html_e( 'Any taxonomy element', 'wpdr-email-notice' ) : esc_html_e( 'All taxonomy elements', 'wpdr-email-notice' ) );
		}
		if ( 'wpdr_en_attach' === $column_name ) {
			$meta   = get_post_meta( $post_id, 'wpdr_en_attach', false );
			$attach = ( is_array( $meta ) && ! empty( $meta ) ? (int) $meta[0] : (int) get_option( 'wpdr_en_set_ext_attach' ) );
			echo '<input type="checkbox" id="attach-' . esc_attr( $post_id ) . '" value ="' . esc_attr( $attach ) . '" ' . checked( 1, $attach, false ) . ' disabled >';
		}
		if ( 'wpdr_en_pause' === $column_name ) {
			$pause = (int) get_post_meta( $post_id, 'wpdr_en_pause', true );
			echo '<input type="checkbox" id="pause-' . esc_attr( $post_id ) . '" value ="' . esc_attr( $pause ) . '" ' . checked( 1, $pause, false ) . ' disabled >';
		}
	}

	/**
	 * Callback to manage meta data for list.
	 *
	 * @since 2.0
	 * @global WP_Post $post Post object.
	 *
	 * @param string $column_name Name of the column to edit.
	 * @param string $post_type   The post type slug, or current screen name if this is a taxonomy list table.
	 * @param string $taxonomy    The taxonomy name, if any.
	 */
	public function del_qe_box( $column_name, $post_type, $taxonomy ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 'doc_ext_list' !== $post_type ) {
			return;
		}

		// Note we don't know which post we are building the QE box for here, so don't try to set values correctly, just the shape.
		if ( 'tm_rule' === $column_name ) {
			?>
			<fieldset class="inline-edit-col-left">
			<div id="tm_ruled" class="inline-edit-group wp-clearfix column-tm_rule" role="radiogroup" aria-labelledby="tm_label" aria-describedby="tm_descr">
			<label id="tm_label" class="alignleft"><?php esc_html_e( 'Match Rule :', 'wpdr-email-notice' ); ?></label>
			<label class="alignleft"><input type="radio" id="tm_any" name="tm_rule" value="0"><?php esc_html_e( 'Any taxonomy element', 'wpdr-email-notice' ); ?>&nbsp;&nbsp;</label>
			<label class="alignleft"><input type="radio" id="tm_all" name="tm_rule" value="1"><?php esc_html_e( 'All taxonomy elements', 'wpdr-email-notice' ); ?></label>
			</div>
			<p id="tm_descr" class="howto"><?php esc_html_e( 'Choose whether Any or All of these taxonomy elements must match for the list to be used to send e-mails.', 'wpdr-email-notice' ); ?></p>
			</fieldset>
			<?php
		}
		if ( 'wpdr_en_attach' === $column_name ) {
			?>
			<fieldset class="inline-edit-col-left">
			<div class="inline-edit-group wp-clearfix column-attach">
			<input type="checkbox" id="wpdr_en_attach" name="wpdr_en_attach" value= "">
			<label class="alignleft" for="wpdr_en_attach"><?php esc_html_e( 'Document Attach :', 'wpdr-email-notice' ); ?>&nbsp;</label>
			</div>
			</fieldset>
			<?php
		}
		if ( 'wpdr_en_pause' === $column_name ) {
			?>
			<fieldset class="inline-edit-col-left">
			<div class="inline-edit-group wp-clearfix column-pause">
			<input type="checkbox" id="wpdr_en_pause" name="wpdr_en_pause" value= "">
			<label class="alignleft" for="wpdr_en_pause"><?php esc_html_e( 'Pause Mail :', 'wpdr-email-notice' ); ?>&nbsp;</label>
			</div>
			</fieldset>
			<?php
		}
	}

	/**
	 * Callback to manage meta data for bulk edit list.
	 *
	 * @since 2.0
	 * @global WP_Post $post Post object.
	 *
	 * @param string $column_name Name of the column to edit.
	 * @param string $post_type   The post type slug, or current screen name if this is a taxonomy list table.
	 */
	public function del_be_box( $column_name, $post_type ) {
		if ( 'doc_ext_list' !== $post_type ) {
			return;
		}

		if ( 'tm_rule' === $column_name ) {
			?>
			<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
			<div class="inline-edit-group wp-clearfix">
			<label class="inline-edit-tm-rule alignleft">
			<span class="title"><?php esc_html_e( 'Match Rule :', 'wpdr-email-notice' ); ?>&nbsp;</span>
			<select name="tm_ruleb">
			<option value="-1"><?php esc_html_e( '-- No Change --', 'wpdr-email-notice' ); ?></option>
			<option value="0"><?php esc_html_e( 'Any taxonomy element', 'wpdr-email-notice' ); ?></option>
			<option value="1"><?php esc_html_e( 'All taxonomy elements-', 'wpdr-email-notice' ); ?></option>
			</select>
			</label>
			</div>	
			<p id="tm_descr" class="howto"><?php esc_html_e( 'Choose whether Any or All of these taxonomy elements must match for the lists to be used to send e-mails.', 'wpdr-email-notice' ); ?></p>
			</div>
			</fieldset>
			<?php
		}
		if ( 'wpdr_en_attach' === $column_name ) {
			?>
			<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
			<div class="inline-edit-group wp-clearfix">
			<label class="inline-edit-attach alignleft">
			<span class="title" style="width: auto;"><?php esc_html_e( 'Document Attach :', 'wpdr-email-notice' ); ?>&nbsp;</span>
			<select name="attachb">
			<option value="-1"><?php esc_html_e( '-- No Change --', 'wpdr-email-notice' ); ?></option>
			<option value="0"><?php esc_html_e( 'Do not Attach Document', 'wpdr-email-notice' ); ?></option>
			<option value="1"><?php esc_html_e( 'Attach Document', 'wpdr-email-notice' ); ?></option>
			</select>
			</label>
			</div>	
			</div>
			</fieldset>
			<?php
		}
		if ( 'wpdr_en_pause' === $column_name ) {
			?>
			<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
			<div class="inline-edit-group wp-clearfix">
			<label class="inline-edit-pause alignleft">
			<span class="title" style="width: auto;"><?php esc_html_e( 'Pause Mail :', 'wpdr-email-notice' ); ?>&nbsp;</span>
			<select name="list_pauseb">
			<option value="-1"><?php esc_html_e( '-- No Change --', 'wpdr-email-notice' ); ?></option>
			<option value="0"><?php esc_html_e( 'Do not Pause Mail', 'wpdr-email-notice' ); ?></option>
			<option value="1"><?php esc_html_e( 'Pause Mail', 'wpdr-email-notice' ); ?></option>
			</select>
			</label>
			</div>	
			</div>
			</fieldset>
			<?php
		}
	}

	/**
	 * Save the Matching rule.
	 *
	 * @since 2.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	public function save_doc_ext_list( $post_id, $post, $update ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// ignore whilst doing autosave.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// ignore for auto-draft.
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( isset( $_GET['bulk_edit'] ) && 'update' === strtolower( sanitize_text_field( wp_unslash( $_GET['bulk_edit'] ) ) ) ) {
			check_admin_referer( 'bulk-posts' );
			if ( isset( $_GET['tm_ruleb'] ) ) {
				$tm_rule = (int) $_GET['tm_ruleb'];
				if ( -1 !== $tm_rule ) {
					update_post_meta( $post_id, 'wpdr_en_tm', $tm_rule );
				}
			}
			if ( isset( $_GET['attachb'] ) ) {
				$attach = (int) $_GET['attachb'];
				if ( -1 !== $attach ) {
					update_post_meta( $post_id, 'wpdr_en_attach', $attach );
				}
			}
			if ( isset( $_GET['list_pauseb'] ) ) {
				$pause = (int) $_GET['list_pauseb'];
				if ( -1 !== $pause ) {
					update_post_meta( $post_id, 'wpdr_en_pause', $pause );
				}
			}
		} else {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['_inline_edit'] ) ) {
				// Are we doing Quick Edit.
				if ( ! wp_verify_nonce( wp_unslash( sanitize_key( $_POST['_inline_edit'] ) ), 'inlineeditnonce' ) ) {
					return;
				}

				// specific post ID is held in post_ID for QE.
				if ( isset( $_POST['post_ID'] ) ) {
					$post_id = wp_unslash( (int) $_POST['post_ID'] );
				}
			} else {
				// normal update.
				check_admin_referer( 'update-post_' . $post_id );

			}

			if ( isset( $_POST['tm_rule'] ) ) {
				$tm_rule = (int) $_POST['tm_rule'];
				update_post_meta( $post_id, 'wpdr_en_tm', $tm_rule );
			}

			$attach = ( isset( $_POST['wpdr_en_attach'] ) ? (int) $_POST['wpdr_en_attach'] : 0 );
			update_post_meta( $post_id, 'wpdr_en_attach', $attach );

			$pause = ( isset( $_POST['wpdr_en_pause'] ) ? (int) $_POST['wpdr_en_pause'] : 0 );
			update_post_meta( $post_id, 'wpdr_en_pause', $pause );
		}
	}

	/**
	 * Identifies the unsaved taxonomy entries.
	 *
	 * @since 2.0
	 * @param array $posttax Array of post taxonomy data.
	 * @param array $savetax Array of post taxonomy data (that was saved on the post).
	 */
	private function unsaved_taxonomy_values( $posttax, $savetax ) {
		$unsaved = array();
		foreach ( $posttax as $tax => $terms ) {
			$tsave = ( isset( $savetax[ $tax ] ) ? $savetax[ $tax ] : array() );
			$test  = array();
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				// remove the saved terms.
				$test = array_diff( $terms, $tsave );
				if ( 0 === $test[0] ) {
					unset( $test[0] );
				}
			}
			// if $tsave is not empty, then there were terms. The diff tells us which are being removed.
			if ( ! empty( $tsave ) ) {
				$tsave = array_diff( $tsave, $terms );
				foreach ( $tsave as $no_term ) {
					if ( 0 !== $no_term ) {
						$test[] = -$no_term;
					}
				}
			}
			if ( ! empty( $test ) ) {
				$unsaved[ $tax ] = $test;
			}
		}
		// does not deal with the case that there is no entry for the taxonomy being removed.

		return $unsaved;
	}

	/**
	 * Checks the taxonomy entries.
	 *
	 * @since 2.0
	 * @param bool  $maybe_empty Whether the post should be considered "empty".
	 * @param array $postarr     Array of post data.
	 */
	public function check_taxonomy_value_set( $maybe_empty, $postarr ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $maybe_empty;
		}
		if ( ! isset( $postarr['post_type'] ) || 'doc_ext_list' !== $postarr['post_type'] ) {
			return $maybe_empty;
		}
		if ( isset( $postarr['post_status'] ) && 'publish' !== $postarr['post_status'] ) {
			return $maybe_empty;
		}

		// Sanitize the value of the $_POST collection for the Coding Standards.
		$url = sanitize_text_field( wp_unslash( $postarr['_wp_http_referer'] ) );
		if ( '//' !== $url && isset( $postarr['tax_input'] ) && is_array( $postarr['tax_input'] ) ) {
			// make sure parent-child terms are not entered.
			foreach ( $postarr['tax_input'] as $tax => $terms ) {
				if ( is_array( $terms ) && ! empty( $terms ) ) {
					if ( get_taxonomy( $tax )->hierarchical ) {
						foreach ( $terms as $id => $term ) {
							if ( 0 !== (int) $term ) {
								$children = get_term_children( $term, $tax );
								if ( is_array( $children ) && ! empty( $children ) ) {
									foreach ( $children as $key => $child ) {
										if ( in_array( $child, $terms, true ) ) {
											// find unsaved terms from post.
											$tsave = ( isset( $postarr['tax_save'] ) ? $postarr['tax_save'] : array() );
											$trans = array(
												$tax,
												$term,
												$child,
												$this->unsaved_taxonomy_values( $postarr['tax_input'], $tsave ),
											);
											set_transient(
												'del_' . $postarr['ID'],
												$trans,
												120
											);
											wp_safe_redirect( urldecode( $url ) );
											exit;
										}
									}
								}
							}
						}
					}
				}
			}
			// check that there is at least one set.
			foreach ( $postarr['tax_input'] as $tax => $terms ) {
				if ( is_array( $terms ) && ! empty( $terms ) ) {
					foreach ( $terms as $id => $term ) {
						if ( 0 !== (int) $term ) {
							return $maybe_empty;
						}
					}
				}
			}
		}

		// no taxonomies set.
		set_transient(
			'del_' . $postarr['ID'],
			array( 0 ),
			120
		);
		wp_safe_redirect( urldecode( $url ) );
		exit;
	}

	/**
	 * Registers update messages
	 *
	 * @since 2.0
	 * @global WP_Post $post Post object.
	 *
	 * @param array $messages messages array.
	 * @returns array messages array with doc_ext_list messages
	 */
	public function update_messages( $messages ) {
		global $post;

		$messages['doc_ext_list'] = array(
			1  => __( 'Document External List updated.', 'wpdr-email-notice' ),
			2  => __( 'Custom field updated.', 'wpdr-email-notice' ),
			3  => __( 'Custom field deleted.', 'wpdr-email-notice' ),
			4  => __( 'Document External List updated.', 'wpdr-email-notice' ),
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			// translators: %s is the revision ID.
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Document External List restored to revision from %s', 'wpdr-email-notice' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			6  => __( 'Document External List published.', 'wpdr-email-notice' ),
			7  => __( 'Document External List saved.', 'wpdr-email-notice' ),
			8  => __( 'Document External List submitted.', 'wpdr-email-notice' ),
			// translators: %1$s is the date, %2$s is the preview link.
			9  => sprintf( __( 'Document External List scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview document list</a>', 'wpdr-email-notice' ), date_i18n( sprintf( _x( '%1$s @ %2$s', '%1$s: date; %2$s: time', 'wpdr-email-notice' ), get_option( 'date_format' ), get_option( 'time_format' ) ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post->ID ) ) ),
			10 => __( 'Document External List draft updated.', 'wpdr-email-notice' ),
		);

		return $messages;
	}

	/**
	 * Adds help tabs to help tab API.
	 *
	 * @since 1.0
	 * @uses get_help_text()
	 * @return void
	 */
	public function add_help_tab() {
		$screen = get_current_screen();

		// only interested in document post_types.
		if ( 'doc_ext_list' !== $screen->post_type && 'document' !== $screen->post_type ) {
			return;
		}

		// loop through each tab in the help array and add.
		foreach ( $this->get_help_text( $screen ) as $title => $content ) {
			$screen->add_help_tab(
				array(
					'title'   => $title,
					'id'      => str_replace( ' ', '_', $title ),
					'content' => $content,
				)
			);
		}
	}

	/**
	 * Helper function to provide help text as an array.
	 *
	 * @since 1.0
	 * @param WP_Screen $screen (optional) the current screen.
	 * @returns array the help text
	 */
	public function get_help_text( $screen = null ) {
		if ( is_null( $screen ) ) {
			$screen = get_current_screen();
		}

		// parent key is the id of the current screen
		// child key is the title of the tab
		// value is the help text (as HTML).
		$help = array(
			'doc_ext_list'      => array(
				__( 'Usage', 'wpdr-email-notice' )       =>
				'<p>' . __( 'This screen allows a user to define a list of email users that will receive an email (generally with the document attached) if any taxonomy value of the list matches that on the document requested.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'The List is available for this processing once the List is published.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'When editing is completed, simply click <code>Update</code> or <code>Publish</code> to save your changes.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'There are three main areas associated with the List:', 'wpdr-email-notice' ) . '</p><ul><li>' .
				__( 'List Attributes', 'wpdr-email-notice' ) . '</li><li>' .
				__( 'Document External User List', 'wpdr-email-notice' ) . '</li><li>' .
				__( 'Taxonomies', 'wpdr-email-notice' ) . '</li></ul>',
				__( 'List Attributes', 'wpdr-email-notice' ) =>
				'<p><strong>' . __( 'Taxonomy Match Rule', 'wpdr-email-notice' ) . '</strong></p><p>' .
				__( 'See the section <strong>Document Taxonomies</strong> for the process to determine whether an individual External List term matches that on the Document.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'This flag indicates whether all the terms on the External List or at least one must match those on the Document for the External List to be considered matching the Document.', 'wpdr-email-notice' ) . '</p><p><strong>' .
				__( 'Document Attach', 'wpdr-email-notice' ) . '</strong></p><p>' .
				__( 'This is a text area where optional comment or information can be made about the External List.', 'wpdr-email-notice' ) . '</p><p><strong><p><strong>' .
				__( 'Pause Mail', 'wpdr-email-notice' ) . '</strong></p><p>' .
				__( 'This allows the External List to be paused from matching Documents.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'It is provided so that an External List can be withdrawn from processing temporarily without simply changing its status.', 'wpdr-email-notice' ) . '</p><p><strong><p><strong>' .
				__( 'List Notes', 'wpdr-email-notice' ) . '</strong></p><p>' .
				'<p>' . __( 'This is a text area where optional comment or information can be held about the External List.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Use is made of the excerpt field as it intended to be a simple aide-memoire to administrators of the External Lists.', 'wpdr-email-notice' ) . '</p>',
				__( 'Document External User List', 'wpdr-email-notice' ) =>
				'<p>' . __( 'This panel consists of two parts. First are the elements to create or edit an individual user record into the table of email addresses, followed by a searchable list of the email addresses associated with the list.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Note that changes made to this User List are stored immediately. There is no separate process to update the data permanently or to cancel the edits made so far.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'This holds a list of individual users that will be sent an email when the External List is selected.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Each user record will contain the user name and email address and an optional pause attribute that pauses the sending of emails to the user.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'This pause attribute allows the user details to be retained in the User List but notification emails will not be sent to the specific user.', 'wpdr-email-notice' ) . '</p>',
				__( 'Document Taxonomies', 'wpdr-email-notice' ) =>
				'<p>' . __( 'These taxonomies are generally shown on the right part of the screen. They are the taxonomies that can be applied to Documents.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'At least one taxonomy term is required to be entered, although several can be.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'If there are multiple terms on the External List then the matching rule will used to determine whether just one term is needed for the External List to be selected or all terms present must match for notification.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'For hierarchical taxonomies, a term on the External List is considered matched if the term on the Document is either the same as that on the External List or is a child of the External List term.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Because of this, it is not permitted to publish an External List when the terms entered contains a term and its parent since the term matching process means that the child term would be redundant.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'A Document can have additional terms present, i.e. it is not necessary that all Document terms must match.', 'wpdr-email-notice' ) . '</p>',
				__( 'Publish', 'wpdr-email-notice' )     =>
				'<p>' . __( 'By default, Documents are only accessible to logged in users. Documents can be published, thus making them accessible to the world, by toggling their visibility in the "Publish" box in the top right corner. Any document marked as published will be accessible to anyone with the proper URL.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Similarly these user lists need to be published for the list to be eligible to be selected for sending emails to its users.', 'wpdr-email-notice' ) . '</p>',
				__( 'Permissions', 'wpdr-email-notice' ) =>
				'<p>' . __( 'It is expected that the emailing administration process will be centralised with only a handful of External Lists being created. Therefore the full WordPress set of access permissions is not required.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'The permission <strong>edit_doc_ext_lists</strong> is supplied that allows users to create, update, and publish any External List.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'The permission <strong>delete_doc_ext_lists</strong> is supplied that allows users to delete any External List.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Users with <strong>edit_documents</strong> permission can read any External List since its use is from within the Document editing screen.', 'wpdr-email-notice' ) . '</p>',
			),
			'edit-doc_ext_list' => array(
				__( 'Document External Lists', 'wpdr-email-notice' ) =>
				'<p>' . __( 'Below is a list of all Document External Lists used to send email notifications to external users on an update to a Document. Click the list title to edit the document list.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'To add a new Document External List, click <strong>Add Document External List</strong> at the top of the screen.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Note that whilst this functionality is primarily intended for people that are not users of this site, any email address can be used.', 'wpdr-email-notice' ) . '</p>',
			),
			'document'          => array(
				__( 'Document Email Notifications', 'wpdr-email-notice' ) =>
				'<p>' . __( 'Notification emails can be sent (or re-sent) for published Documents by clicking on the button "Send notification emails" (to internal users) or "Send external emails" (to  external users).', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Internal users are those with user-ids on the site. They can decide whether they wish to receive these notifications or not and whether a copy of the document should be attached the mail.', 'wpdr-email-notice' ) . '&nbsp;' .
				__( 'This data can also be updated by Administrators (also using Bulk Editing functions).', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'External users normally do not have a sign-on. Notifications are based on the concept of External Lists. A list will contain one or more taxonomy terms that will be matched again the document terms.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Every published External List (except those marked as Paused) will be tested to see whether its terms match those on the Document.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Those that match are listed below the button. If the user can maintain the External List, then they can choose to not send the mail to a List by unchecking the List before sending the Notification.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'A term on the list matches with one on the Document if they are same or, for hierarchical taxonomies, the List term is a parent of the Document term.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'If there are several terms on the List, it can be set so that the List is matched if either any term matches or all terms must match.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Potentially several Lists may match the Document. Emails will be sent to every user (except those individually paused) on each list that is matched.', 'wpdr-email-notice' ) . '</p>',
				__( 'Document Email Extra Text', 'wpdr-email-notice' ) =>
				'<p>' . __( 'You can include some message-specific additional text with the notification emails.', 'wpdr-email-notice' ) . '</p><p>' .
				// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
				__( 'The template used (Internal or External) has to contain the tag "%extra%" in it to use this capability.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'A checkbox which is initially disabled with label "Add Extra Text" is placed next to each Send button.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'On entering some text in the text field, these checkboxes will become active if the template allows it.', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'The extra text will only be included if the corresponding checkbox is checked when the send button is clicked. It can contains html tags to better format the output.', 'wpdr-email-notice' ) . '</p>',
			),
		);

		// if we don't have any help text for this screen, just kick.
		if ( ! isset( $help[ $screen->id ] ) ) {
			return array();
		}

		/**
		 * Filters the default help text for current screen.
		 *
		 * @param string[]  $help   default help text for current screen.
		 * @param WP_Screen $screen current screen name.
		 */
		return apply_filters( 'wpdr_en_help_array', $help[ $screen->id ], $screen );
	}

	/**
	 * Function to output error message (or placeholder) box for whether an External List post can be saved.
	 *
	 * @since 2.0
	 * @returns void
	 */
	public function check_error_state() {
		global $current_screen, $post;
		if ( 'doc_ext_list' !== $current_screen->post_type || 'post' !== $current_screen->base ) {
			return;
		}
		if ( is_null( $post ) ) {
			return;
		}
		// only wanted for the actual edit post screen.
		$messages   = '';
		$err_exists = true;
		// Are there any taxonomies set.
		$taxes = get_object_taxonomies( $post );
		foreach ( $taxes as $tax ) {
			if ( get_the_terms( $post, $tax ) ) {
				$err_exists = false;
				break;
			}
		}
		if ( $err_exists ) {
			$messages = '<p>' . esc_html__( 'There are no terms defined for matching with this list. Please add at least one.', 'wpdr-email-notice' ) . '</p>';
		}
		// Are there users.
		$users_rec = get_post_meta( $post->ID, 'wpdr_en_addressees', true );
		if ( false === $users_rec || empty( $users_rec ) ) {
			$rec_num = 0;
			$users   = array();
		} else {
			$users_rec = json_decode( $users_rec, true );
			$rec_num   = $users_rec['rec_num'];
			$users     = $users_rec['users'];
		}
		if ( 0 === count( $users ) ) {
			$messages  .= '<p>' . esc_html__( 'There are no addressees defined for matching with this list. Please add at least one.', 'wpdr-email-notice' ) . '</p>';
			$err_exists = true;
		}

		// if status is draft or similar, we can save, but not publish.
		if ( in_array( $post->post_status, array( 'auto-draft', 'draft', 'pending', 'trash' ), true ) ) {
			$oclass = 'notice-warning';
		} else {
			$oclass = 'notice-error';
		}

		// was an error found in transient.
		$err = get_transient( 'del_' . $post->ID );
		if ( $err ) {
			delete_transient( 'del_' . $post->ID );
			$err_exists = true;
			$oclass     = 'notice-error';
			if ( 0 === $err[0] ) {
				$messages .= '<p>' . esc_html__( 'There are no terms defined for matching with this list. Please add at least one.', 'wpdr-email-notice' ) . '</p>';
			} else {
				$tax_l  = get_taxonomy( $err[0] )->label;
				$parent = esc_attr( get_term( $err[1], $err[0] )->name );
				$child  = esc_attr( get_term( $err[2], $err[0] )->name );
				// translators: %1$s is the taxonomy name, %2$s is the parent term, %2$s is the child term.
				$messages .= '<p>' . sprintf( __( 'Taxonomy: %1$s. Cannot have both parent (%2$s) and child terms (%3$s). Remove child term as parent term will match.', 'wpdr-email-notice' ), $tax_l, $parent, $child ) . '</p>';
				// make sure js methods have been loaded in case we add something after.
				if ( ! self::$js_loaded ) {
					self::load_js_methods();
				}
				// create update script to act on load.
				$add_script = false;
				$script     = 'document.addEventListener("DOMContentLoaded", function(evt) {' . "\n";
				foreach ( $err[3] as $tax => $terms ) {
					$tax_o = get_taxonomy( $tax );
					if ( $tax_o->hierarchical ) {
						$add_script = true;
						foreach ( $terms as $term ) {
							if ( $term > 0 ) {
								// term added, must be checked.
								$script .= " revert_unsaved_hier('" . $tax . "', " . $term . ', true );' . "\n";
							} else {
								// term removed, must be unchecked.
								$script .= " revert_unsaved_hier('" . $tax . "', " . $term . ', false );' . "\n";
							}
						}
					} else {
						// translators: %s is the taxonomy name.
						$messages .= '<p>' . sprintf( __( 'Taxonomy: %s. You must redo your non-hierarchical changes manually.', 'wpdr-email-notice' ), $tax_o->label );
						foreach ( $terms as $term ) {
							if ( $term > 0 ) {
								// term added, must be checked.
								// translators: %1$s is the taxonomy term name.
								$messages .= '<br />&nbsp;' . sprintf( __( 'Add Term: %1$s ', 'wpdr-email-notice' ), get_term( $term, $tax )->name );
							} else {
								// term removed, must be unchecked.
								// translators: %1$s is the taxonomy term name.
								$messages .= '<br />&nbsp;' . sprintf( __( 'Remove Term: %1$s ', 'wpdr-email-notice' ), get_term( -$term, $tax )->name );
							}
						}
						$messages .= '</p>';
					}
				}
				if ( $add_script ) {
					$script .= '})' . "\n";
					// add after the script.
					wp_add_inline_script( 'wpdr_en_script', $script, 'after' );
				}
			}
		}
		?>
		<div id="wpdr_en_message" class="notice <?php echo esc_attr( $oclass ); ?>" <?php echo ( $err_exists ? '' : 'style="display:none"' ); ?>>
		<?php echo $messages; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
	}
}

// #TODO: add export log functionality
// #TODO: add purge functionality: uncomment related code and configuration options; implement scheduler to truncate logs regularly + manually on view log page
