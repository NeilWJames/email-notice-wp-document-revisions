<?php
/**
 * Email Notice WP Document Revisions Main Functionality
 *
 * @author  Neil W. James <neil@familyjames.com>
 * @package Email Notice WP Document Revisions
 */

// No direct access allowed to plugin php file.
if ( ! defined( 'ABSPATH' ) ) {
	die( esc_html__( 'You are not allowed to call this file directly.', 'email-notice-wp-document-revisions' ) );
}

/**
 * Email Notice for WP Document Revisions: settings, metaboxes, Document External Lists and sending.
 */
class WPDR_Email_Notice {

	/* Initialisation */

	/**
	 * File version.
	 *
	 * @var string $version
	 */
	public static $version = '4.0';

	/**
	 * JS has been loaded.
	 *
	 * @var boolean $js_loaded
	 */
	private static $js_loaded = false;

	/**
	 * Temporary file name
	 *
	 * @var array|string $attach_file
	 */
	public static $attach_file = null;

	/**
	 * Default email content
	 *
	 * @var string $default_content
	 */
	private static $default_content;

	/**
	 * Default email content for external users
	 *
	 * @var string $default_exttext
	 */
	private static $default_exttext;

	/**
	 * Default email repeat
	 *
	 * @var string $default_repeat
	 */
	private static $default_repeat;

	/**
	 * Roles that can set user option to mail document information.
	 *
	 * @var string[] $internal_roles
	 */
	public static $internal_roles;

	/**
	 * Flag to determine whether to provide the internal list capability.
	 *
	 * @var bool $internal_list_needed
	 */
	private static $internal_list_needed;

	/**
	 * "Notify internal users about" setting keys, with the value saved and the document status they cover.
	 *
	 * @var string[][] $notify_about_keys
	 */
	private static $notify_about_keys = array(
		'Chkbx_Public'   => array( 'Public', 'Public' ),
		'Chkbx_Password' => array( 'Password', 'Password protected' ),
		'Chkbx_Private'  => array( 'Private', 'Private' ),
	);

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		// Ensure log tables and capabilities defined.
		add_action( 'plugins_loaded', array( $this, 'db_version_check' ) );

		add_action( 'init', array( $this, 'init' ), 3000 );

		// Initialize settings.
		add_action( 'admin_init', array( $this, 'admin_init' ), 200 );
		// Add the notification log options.
		add_action( 'admin_menu', array( $this, 'notification_log_menu' ), 30 );

		// Daily removal of old log entries (WP-Cron; the plugin is also loaded for cron requests).
		add_action( 'wpdr_en_purge_logs', array( $this, 'purge_logs' ) );
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

		// Related post, user, email sent (Time stamp), email address, Status (successful/failed).
		$sql[] = "CREATE TABLE $table_name (
		  id bigint(20) NOT NULL AUTO_INCREMENT,	  
		  user_id bigint(20) NOT NULL,
		  post_id bigint(20) NOT NULL,
		  time_mail_sent datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		  user_email varchar(100) NOT NULL,
		  status varchar(100) NOT NULL,
		  extra_text_id bigint(20) DEFAULT NULL,
		  PRIMARY KEY  (id),
		  KEY user_id (user_id),
		  KEY post_id (post_id,user_id)
		) $charset_collate;";

		$table_name = $wpdb->prefix . 'wpdr_ext_notice_log';

		// Related post, user, email sent (Time stamp), email address, Status (successful/failed).
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
		  KEY post_id (post_id,user_email),
		  KEY doc_ext_list_id (doc_ext_list_id)
		) $charset_collate;";

		$table_name = $wpdb->prefix . 'wpdr_en_extra_text';

		// Related post, added text.
		$sql[] = "CREATE TABLE $table_name (
		  id bigint(20) NOT NULL AUTO_INCREMENT,	  
		  post_id bigint(20) NOT NULL,
		  extra_text varchar(250),
		  PRIMARY KEY  (id),
		  KEY post_id (post_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		// Indexes are named as MySQL named the original unnamed ones, so existing tables are left as they are.
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

		// default roles that should have the read, edit and/or delete doc_ext_lists capability.
		// can be overridden by 3rd party plugins.
		// deliberately very limited.
		$defaults = array(
			'administrator' =>
			array(
				'read_doc_ext_lists',
				'edit_doc_ext_lists',
				'delete_doc_ext_lists',
			),
			'editor'        =>
			array(
				'read_doc_ext_lists',
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

			if ( ! isset( $wp_roles->roles[ $role ] ) ) {
				continue;
			}
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

	/**
	 * Upgrade to 4.0: give read_doc_ext_lists to every role and user that has edit_doc_ext_lists.
	 *
	 * Before 4.0 anyone with edit_documents could see the External Lists, logs and metabox section;
	 * from 4.0 that needs read_doc_ext_lists. List editors keep their access; anyone else must be
	 * given the capability explicitly.
	 *
	 * Runs whatever the current user, as it is a one-off data change for the site.
	 *
	 * @since 4.0.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @global WP_Roles $wp_roles WP_Roles object.
	 * @return void
	 */
	private function upgrade_read_capability() {
		global $wpdb, $wp_roles;
		if ( ! is_object( $wp_roles ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_roles = new WP_Roles();
		}

		// Roles.
		foreach ( (array) $wp_roles->roles as $role => $data ) {
			if ( ! empty( $data['capabilities']['edit_doc_ext_lists'] ) && ! array_key_exists( 'read_doc_ext_lists', $data['capabilities'] ) ) {
				$wp_roles->add_cap( $role, 'read_doc_ext_lists', true );
			}
		}
		// Make the loaded role objects match what was saved.
		$wp_roles->for_site();

		// Users given edit_doc_ext_lists individually (not through a role).
		$users = get_users(
			array(
				'blog_id'      => get_current_blog_id(),
				'meta_key'     => $wpdb->get_blog_prefix() . 'capabilities', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'   => 'edit_doc_ext_lists', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare' => 'LIKE',
			)
		);
		foreach ( $users as $user ) {
			if ( ! empty( $user->caps['edit_doc_ext_lists'] ) && ! array_key_exists( 'read_doc_ext_lists', $user->caps ) ) {
				$user->add_cap( 'read_doc_ext_lists', true );
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
		// Existing site from before 4.0 (new installs get the capability with the defaults below).
		if ( ! empty( $current_db_ver ) && version_compare( $current_db_ver, '4.0', '<' ) ) {
			$this->upgrade_read_capability();
		}
		// Once per site (each site of a network has its own version option, tables and roles), whoever
		// loads the page: on a network the first visitor to a site's admin is rarely a network administrator.
		if ( self::$version === $current_db_ver ) {
			return;
		}
		global $wp_roles;
		if ( ! is_object( $wp_roles ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_roles = new WP_Roles();
		}
		// check that a role has been allocated with read, edit and delete. if not set default.
		$found_read   = false;
		$found_edit   = false;
		$found_delete = false;
		foreach ( (array) $wp_roles->roles as $role => $data ) {
			if ( array_key_exists( 'read_doc_ext_lists', $data['capabilities'] ) ) {
				$found_read = true;
			}
			if ( array_key_exists( 'edit_doc_ext_lists', $data['capabilities'] ) ) {
				$found_edit = true;
			}
			if ( array_key_exists( 'delete_doc_ext_lists', $data['capabilities'] ) ) {
				$found_delete = true;
			}
		}
		// install_capabilities() only adds what a default role is missing.
		if ( ! $found_read || ! $found_edit || ! $found_delete ) {
			$this->install_capabilities();
		}
		$this->install_plugin_tables();
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
			'name'               => _x( 'Document External Lists', 'post type general name', 'email-notice-wp-document-revisions' ),
			'singular_name'      => _x( 'Document External List', 'post type singular name', 'email-notice-wp-document-revisions' ),
			'add_new'            => __( 'Add Document External List', 'email-notice-wp-document-revisions' ),
			'add_new_item'       => __( 'Add New Document External List', 'email-notice-wp-document-revisions' ),
			'edit_item'          => __( 'Edit Document External List', 'email-notice-wp-document-revisions' ),
			'new_item'           => __( 'New Document External List', 'email-notice-wp-document-revisions' ),
			'view_item'          => __( 'View Document External List', 'email-notice-wp-document-revisions' ),
			'view_items'         => __( 'View Document External Lists', 'email-notice-wp-document-revisions' ),
			'search_items'       => __( 'Search Document External Lists', 'email-notice-wp-document-revisions' ),
			'not_found'          => __( 'No Document External List found', 'email-notice-wp-document-revisions' ),
			'not_found_in_trash' => __( 'No Document External Lists found in Trash', 'email-notice-wp-document-revisions' ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'Document Emails', 'email-notice-wp-document-revisions' ),
			'all_items'          => __( 'All Document External Lists', 'email-notice-wp-document-revisions' ),
		);

		$args = array(
			'labels'               => $labels,
			// Lists hold personal data: admin only, never on the front end, in search or in menus.
			'publicly_queryable'   => false,
			'public'               => false,
			'exclude_from_search'  => true,
			'show_ui'              => true,
			'show_in_menu'         => true,
			'show_in_admin_bar'    => false,
			'show_in_nav_menus'    => false,
			'show_in_rest'         => false,
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
				'read'                   => 'read_doc_ext_lists',
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
		// The plugin's languages folder (this file is in includes/).
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
		load_plugin_textdomain( 'email-notice-wp-document-revisions', false, dirname( plugin_basename( __DIR__ ) ) . '/languages' );

		// Adding settings to Settings->General.
		register_setting(
			'general',
			'wpdr_en_set_email_from',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
		register_setting(
			'general',
			'wpdr_en_set_email_from_address',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
			),
		);
		add_settings_section( 'wpdr_en_general_settings', __( 'Document Email Settings', 'email-notice-wp-document-revisions' ), array( $this, 'general_settings' ), 'general' );
		add_settings_field( 'wpdr_en_set_email_from', __( 'Email From', 'email-notice-wp-document-revisions' ), array( $this, 'set_email_from' ), 'general', 'wpdr_en_general_settings' );
		add_settings_field( 'wpdr_en_set_email_from_address', __( 'Email Address', 'email-notice-wp-document-revisions' ), array( $this, 'set_email_from_address' ), 'general', 'wpdr_en_general_settings' );

		// Adding settings to Settings->Writing.
		register_setting(
			'writing',
			'wpdr_en_set_notification_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_notification_mode' ),
			),
		);
		register_setting(
			'writing',
			'wpdr_en_set_notification_about',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_notification_about' ),
			),
		);
		register_setting(
			'writing',
			'wpdr_en_set_subject',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
		register_setting(
			'writing',
			'wpdr_en_set_content',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
		);
		register_setting(
			'writing',
			'wpdr_en_set_exttext',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
		);
		register_setting(
			'writing',
			'wpdr_en_set_ext_attach',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'intval',
			),
		);
		register_setting(
			'writing',
			'wpdr_en_set_repeat',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
		);
		add_settings_section( 'wpdr_en_writing_settings', __( 'Document Email Settings - ', 'email-notice-wp-document-revisions' ) . __( 'Notifications', 'email-notice-wp-document-revisions' ), array( $this, 'writing_settings' ), 'writing' );
		add_settings_field( 'wpdr_en_set_notification_mode', __( 'Internal Notice mode', 'email-notice-wp-document-revisions' ), array( $this, 'set_notification_mode' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_notification_about', __( 'Notify internal users about', 'email-notice-wp-document-revisions' ), array( $this, 'set_notification_about' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_subject', __( 'Notification email subject', 'email-notice-wp-document-revisions' ), array( $this, 'set_subject' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_content', __( 'Internal Notice email content', 'email-notice-wp-document-revisions' ), array( $this, 'set_content' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_exttext', __( 'External Notice email content', 'email-notice-wp-document-revisions' ), array( $this, 'set_exttext' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_ext_attach', '', array( $this, 'set_ext_attach' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_repeat', __( 'Notification email repeat', 'email-notice-wp-document-revisions' ), array( $this, 'set_repeat' ), 'writing', 'wpdr_en_writing_settings' );
		register_setting(
			'writing',
			'wpdr_en_set_log_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
		add_settings_field( 'wpdr_en_set_log_days', __( 'Internal email log', 'email-notice-wp-document-revisions' ), array( $this, 'set_log_days' ), 'writing', 'wpdr_en_writing_settings' );

		// Old log entries are removed once a day.
		if ( ! wp_next_scheduled( 'wpdr_en_purge_logs' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpdr_en_purge_logs' );
		}

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

		// Overwrite default email address only if user set new value.
		if ( get_option( 'wpdr_en_set_email_from_address' ) ) {
			add_filter( 'wp_mail_from', array( $this, 'wp_mail_from' ) );
		}
		// Overwrite default email from text only if user set new value.
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

		// Log export (CSV).
		add_action( 'admin_post_wpdr_en_export_log', array( $this, 'export_log' ) );

		// search box needs post id added (Mis-use of filter to process in correct place).
		add_action( 'use_block_editor_for_post', array( $this, 'ensure_post_id' ), 10, 2 );

		// set default texts.
		// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
		// translators: For this group, text surroundedby % symbols (like %recipient_name%) should not be translated.
		self::$default_content = __( 'Dear %recipient_name%,<br/><br/>', 'email-notice-wp-document-revisions' ) .
			__( 'A new document is published. Check it out!<br/><br/><strong>%title_with_permalink%</strong><br/>%words_50%%extra%%repeat%<br/>', 'email-notice-wp-document-revisions' ) .
			// translators: %s is the link address of the user's profile. Do not translate.
			sprintf( __( '<small>In case you do not want to receive this kind of notification you can turn it off in your <a href="%s">profile</a>.', 'email-notice-wp-document-revisions' ), admin_url( 'profile.php' ) ) .
			__( '<br/>Also go there if you wish to change whether you will receive the document as an attachment.</small>', 'email-notice-wp-document-revisions' );
		self::$default_exttext = __( 'Dear %recipient_name%,<br/><br/>', 'email-notice-wp-document-revisions' ) .
			__( 'A new document is published. Check it out!<br/><br/><strong>%title_with_permalink%</strong><br/>%words_50%%extra%%repeat%<br/>', 'email-notice-wp-document-revisions' ) .
			__( '<small>In case you do not want to receive this kind of notification you can reply with the message "Unsubscribe".</small>', 'email-notice-wp-document-revisions' );
		self::$default_repeat  = __( '<p>This document has previously been sent to you %num% time(s), with the latest sent on %last_date%.</p>', 'email-notice-wp-document-revisions' );
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
				'sending_mails'    => __( 'Sending email notifications...', 'email-notice-wp-document-revisions' ),
				'error_sending'    => __( 'Error sending emails.', 'email-notice-wp-document-revisions' ),
				'resend'           => __( 'Re-send notification email(s)', 'email-notice-wp-document-revisions' ),
				'email_out_of'     => __( 'email(s) out of', 'email-notice-wp-document-revisions' ),
				'notif_sent_check' => __( 'notification(s) sent. Check', 'email-notice-wp-document-revisions' ),
				'sent_with'        => __( 'sent with', 'email-notice-wp-document-revisions' ),
				'log_issues'       => __( 'log issues. Check', 'email-notice-wp-document-revisions' ),
				'log'              => __( 'log', 'email-notice-wp-document-revisions' ),
				'for_details'      => __( 'for details.', 'email-notice-wp-document-revisions' ),
			);
		} elseif ( 'doc_ext_list' === $post->post_type ) {
			$css         = 'wpdr-en-mail';
			$script      = 'wpdr-en-address';
			$nonce_array = array(
				'wpdr_en_nonce' => wp_create_nonce( 'wpdr_en_nonce' ),
				'ajaxurl'       => admin_url( 'admin-ajax.php' ),
				'add_address'   => __( 'Add Address to List', 'email-notice-wp-document-revisions' ),
				'edit_address'  => __( 'Edit existing Address', 'email-notice-wp-document-revisions' ),
			);
		} else {
			return;
		}

		// process script.
		$asset = self::asset( 'js', $script );
		wp_register_script(
			'wpdr_en_script',
			$asset['url'],
			array( 'jquery' ),
			$asset['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		// Improve security.
		wp_localize_script( 'wpdr_en_script', 'wpdr_en_obj', $nonce_array );
		wp_enqueue_script( 'wpdr_en_script' );

		// load css as well.
		$asset = self::asset( 'css', $css );
		wp_enqueue_style(
			'wpdr_en_css',
			$asset['url'],
			array(),
			$asset['version'],
		);
		// note loaded.
		self::$js_loaded = true;
	}

	/**
	 * URL and version of a script or style.
	 *
	 * Sources are in src/js and src/css; minified copies with the same names (built by `npm run build`)
	 * in build/js and build/css. The source is used when SCRIPT_DEBUG or WP_DEBUG is on. The version is the file's
	 * modification time, so browsers fetch a rebuilt file straight away.
	 *
	 * @since 4.0.0
	 * @param string $type 'js' or 'css'.
	 * @param string $name File name without extension, e.g. 'wpdr-en-log'.
	 * @return array{url: string, version: string|false}
	 */
	public static function asset( $type, $name ) {
		$debug = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		$path  = ( $debug ? 'src/' : 'build/' ) . $type . '/' . $name . '.' . $type;
		$file  = plugin_dir_path( __DIR__ ) . $path;
		return array(
			'url'     => plugin_dir_url( __DIR__ ) . $path,
			'version' => file_exists( $file ) ? (string) filemtime( $file ) : false,
		);
	}

	/**
	 * The domain WordPress uses for its default From address (wordpress@<domain>).
	 *
	 * Same rule as wp_mail(): the network home URL's host without "www.". (Previously the server
	 * name was passed through sanitize_url(), which adds "http://", so the default was never matched.)
	 *
	 * @since 4.0.0
	 * @return string
	 */
	private static function default_from_domain() {
		$sitename = strtolower( (string) wp_parse_url( network_home_url(), PHP_URL_HOST ) );
		if ( str_starts_with( $sitename, 'www.' ) ) {
			$sitename = substr( $sitename, 4 );
		}
		return $sitename;
	}

	/* Settings->General */

	/**
	 * Document Email Settings section intro text.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function general_settings() {
		$sitename = self::default_from_domain();
		echo esc_html__( 'By default all notification emails received from "WordPress" < wordpress@', 'email-notice-wp-document-revisions' ) . esc_html( $sitename ) . ' >. ' . esc_html__( 'You can change these below.', 'email-notice-wp-document-revisions' );
	}

	/**
	 * Settings field to set Email From text.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_email_from() {
		echo '<input class="regular-text ltr" type="text" id="wpdr_en_set_email_from" name="wpdr_en_set_email_from" placeholder="Wordpress" value="' . esc_attr( get_option( 'wpdr_en_set_email_from' ) ) . '"></input>';
	}

	/**
	 * Settings field to set Email from email Address.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_email_from_address() {
		$sitename = self::default_from_domain();
		echo '<input class="regular-text ltr" type="email" id="wpdr_en_set_email_from_address" name="wpdr_en_set_email_from_address" placeholder="wordpress@' . esc_attr( $sitename ) . '" value="' . esc_attr( get_option( 'wpdr_en_set_email_from_address' ) ) . '"></input>';
	}

	/* Settings->Writing */

	/**
	 * Notifications section intro text.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function writing_settings() {
		esc_html_e( 'Tags available to make email subject and/or content dynamic:', 'email-notice-wp-document-revisions' );
		echo '<br/>';
		echo '<strong>%title%</strong> ' . esc_html__( 'means title of the post', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<strong>%permalink%</strong> ' . esc_html__( 'means URL of the post', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<strong>%title_with_permalink%</strong> ' . esc_html__( 'means URL with title of the post', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<strong>%author_name%</strong> ' . esc_html__( 'means the name of the post author', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<strong>%excerpt%</strong> ' . esc_html__( 'means excerpt of the post', 'email-notice-wp-document-revisions' ) . ' ' . esc_html__( 'Only available to those who can edit the document', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<strong>%words_n%</strong> ' . esc_html__( 'means the first n (must be an integer number) number of word(s) extracted from the post', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<strong>%recipient_name%</strong> ' . esc_html__( 'means display name of the user who receives the email', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<strong>%repeat%</strong> ' . esc_html__( 'means output the phrase if the document has been previously emailed.', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<br/>';
		echo esc_html__( 'Tags available within the tag', 'email-notice-wp-document-revisions' ) . ' <strong>%repeat%</strong>';
		echo '<br/>';
		echo '<strong>%num%</strong> ' . esc_html__( 'means the number of times the document has been previously emailed.', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<strong>%last_date%</strong> ' . esc_html__( 'means the last date that the document was emailed.', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<strong>%last_time%</strong> ' . esc_html__( 'means the last date incuding time that the document was emailed.', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<br/>';
		echo '<strong>%extra%</strong> ' . esc_html__( 'means output an optional extra phrase entered at the time of mailing.', 'email-notice-wp-document-revisions' ) . '<br/>';
	}

	/**
	 * Settings field to set Notification mode: Auto/Manual.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_notification_mode() {
		// Anything but Auto is Manual (before 4.0.0 the setting was saved as 0).
		$opt = ( 'Auto' === get_option( 'wpdr_en_set_notification_mode' ) ? 'Auto' : 'Manual' );
		echo '<input type="radio" name="wpdr_en_set_notification_mode" value="Auto" ' . checked( 'Auto', $opt, false ) . '>' . esc_html__( 'Auto', 'email-notice-wp-document-revisions' ) . '</input> ' . esc_html__( '(send emails automatically when you publish a post)', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<input type="radio" name="wpdr_en_set_notification_mode" value="Manual" ' . checked( 'Manual', $opt, false ) . '>' . esc_html__( 'Manual', 'email-notice-wp-document-revisions' ) . '</input> ' . esc_html__( '(you need to press a button to send notification)', 'email-notice-wp-document-revisions' );
	}

	/**
	 * Settings field to set Notify users about public/private/password protected posts.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_notification_about() {
		echo '<input type="checkbox" id="Chkbx_Public" name="wpdr_en_set_notification_about[Chkbx_Public]" value="Public"' . checked( self::notify_about( 'Public' ), true, false ) . '>' . esc_html__( 'Public posts', 'email-notice-wp-document-revisions' ) . '</input><br/>';
		echo '<input type="checkbox" id="Chkbx_Password" name="wpdr_en_set_notification_about[Chkbx_Password]" value="Password"' . checked( self::notify_about( 'Password protected' ), true, false ) . '>' . esc_html__( 'Password', 'email-notice-wp-document-revisions' ) . '</input> ' . esc_html__( 'protected posts (password will', 'email-notice-wp-document-revisions' ) . ' <strong>' . esc_html__( 'NOT', 'email-notice-wp-document-revisions' ) . '</strong> ' . esc_html__( 'be included in notification email)', 'email-notice-wp-document-revisions' ) . '<br/>';
		echo '<input type="checkbox" id="Chkbx_Private" name="wpdr_en_set_notification_about[Chkbx_Private]" value="Private"' . checked( self::notify_about( 'Private' ), true, false ) . '>' . esc_html__( 'Private posts', 'email-notice-wp-document-revisions' ) . '</input> ';
		echo '<br />' . esc_html__( 'Notifications are sent only to those Internal users who can read the document', 'email-notice-wp-document-revisions' );
		echo '<br />' . esc_html__( 'Notifications can only be sent to External users for Public documents.', 'email-notice-wp-document-revisions' );
	}

	/**
	 * Sanitize the Internal Notice mode: Auto or Manual.
	 *
	 * @since 4.0.0
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_notification_mode( $value ) {
		return ( 'Auto' === $value ? 'Auto' : 'Manual' );
	}

	/**
	 * Sanitize the "Notify internal users about" checkboxes: keep only the known keys.
	 *
	 * @since 4.0.0
	 * @param mixed $value Submitted value (no box ticked submits nothing).
	 * @return string[]
	 */
	public static function sanitize_notification_about( $value ) {
		$clean = array();
		foreach ( self::$notify_about_keys as $key => $values ) {
			if ( is_array( $value ) && isset( $value[ $key ] ) ) {
				$clean[ $key ] = $values[0];
			}
		}
		return $clean;
	}

	/**
	 * Whether internal users are told about documents with this status.
	 *
	 * Until the setting is saved as a list (before 4.0.0 it could not be), they are told about all of them.
	 *
	 * @since 4.0.0
	 * @param string $pstatus Status from post_status(): Public, Password protected or Private.
	 * @return bool
	 */
	public static function notify_about( $pstatus ) {
		$about = get_option( 'wpdr_en_set_notification_about' );
		if ( ! is_array( $about ) ) {
			return true;
		}
		foreach ( self::$notify_about_keys as $key => $values ) {
			if ( $pstatus === $values[1] ) {
				return array_key_exists( $key, $about );
			}
		}
		return false;
	}

	/**
	 * Settings field to set notification email subject.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_subject() {
		echo '<input class="regular-text ltr" type="text" id="wpdr_en_set_subject" name="wpdr_en_set_subject" placeholder="New document: %title%" value="' . esc_attr( get_option( 'wpdr_en_set_subject' ) ) . '"></input>';
		echo '<br/><br/>' . esc_html__( 'Hint: HTML tags are not allowed here, e.g.: %title_with_permalink% will revert to %title%.', 'email-notice-wp-document-revisions' );
	}

	/**
	 * Settings field to set notification email content.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_content() {
		// The template is HTML, so it is shown as text in the textarea (and in the placeholder).
		echo '<textarea id="wpdr_en_set_content" name="wpdr_en_set_content" cols="80" rows="8" placeholder="' . esc_attr( self::$default_content ) . '">' . esc_textarea( wp_kses_post( (string) get_option( 'wpdr_en_set_content' ) ) ) . '</textarea>';
		echo '<br/><br/>' . esc_html__( 'Hint: HTML tags are welcome here to make your notification emails more personalized.', 'email-notice-wp-document-revisions' );
	}

	/**
	 * Settings field to set external notification email content.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function set_exttext() {
		echo '<textarea id="wpdr_en_set_exttext" name="wpdr_en_set_exttext" cols="80" rows="7" placeholder="' . esc_attr( self::$default_exttext ) . '">' . esc_textarea( wp_kses_post( (string) get_option( 'wpdr_en_set_exttext' ) ) ) . '</textarea>';
		echo '<br/><br/>' . esc_html__( 'Hint: HTML tags are welcome here to make your notification emails more personalized.', 'email-notice-wp-document-revisions' );
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
		<?php esc_html_e( 'Attach the Document in emails to external users.', 'email-notice-wp-document-revisions' ); ?></label><br />
		<?php
		echo '<br/>' . esc_html__( 'Public documents may not always be accessible to External users, so these may need to be attached.', 'email-notice-wp-document-revisions' );
	}

	/**
	 * Settings field to set notification email repeat text.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function set_repeat() {
		echo '<textarea id="wpdr_en_set_repeat" name="wpdr_en_set_repeat" cols="80" rows="5" placeholder="' . esc_attr( self::$default_repeat ) . '">' . esc_textarea( wp_kses_post( (string) get_option( 'wpdr_en_set_repeat' ) ) ) . '</textarea>';
		echo '<br/><br/>' . esc_html__( 'Hint: HTML tags are welcome here to make your notification emails more personalized.', 'email-notice-wp-document-revisions' );
	}

	/**
	 * Settings field: days to keep internal log entries after the Document was last modified.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public function set_log_days() {
		$days = absint( get_option( 'wpdr_en_set_log_days' ) );
		echo '<label for="wpdr_en_set_log_days">' . esc_html__( 'Delete entries for Documents not modified for', 'email-notice-wp-document-revisions' ) . ' ';
		echo '<input type="number" id="wpdr_en_set_log_days" name="wpdr_en_set_log_days" value="' . esc_attr( $days ? $days : '' ) . '" min="0" step="1" class="small-text" /> ';
		echo esc_html__( 'days', 'email-notice-wp-document-revisions' ) . '</label>';
		echo '<br/>' . esc_html__( 'Empty or 0 keeps them. Checked once a day. External log entries are kept as set on each Document External List.', 'email-notice-wp-document-revisions' );
	}

	/**
	 * Delete old log entries: those for Documents not modified for the number of days set.
	 *
	 * Internal log: the Settings > Writing value. External log: each List's own value.
	 * Extra text no longer used by any log entry is deleted too. Runs daily (WP-Cron).
	 *
	 * @since 4.0.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return int[] Rows deleted: 'internal', 'external', 'extra_text'.
	 */
	public function purge_logs() {
		global $wpdb;
		$now    = current_time( 'mysql', true );
		$result = array(
			'internal'   => 0,
			'external'   => 0,
			'extra_text' => 0,
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$days = absint( get_option( 'wpdr_en_set_log_days' ) );
		if ( $days > 0 ) {
			$result['internal'] = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE l FROM {$wpdb->prefix}wpdr_notification_log l
					 INNER JOIN {$wpdb->posts} p ON p.ID = l.post_id
					 WHERE p.post_modified_gmt < DATE_SUB( %s, INTERVAL %d DAY )",
					$now,
					$days
				)
			);
		}

		// Each List's days are in its wpdr_en_log_days meta (none or 0: keep).
		$result['external'] = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE e FROM {$wpdb->prefix}wpdr_ext_notice_log e
				 INNER JOIN {$wpdb->posts} p ON p.ID = e.post_id
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = e.doc_ext_list_id AND m.meta_key = 'wpdr_en_log_days'
				 WHERE CAST( m.meta_value AS UNSIGNED ) > 0
				 AND p.post_modified_gmt < DATE_SUB( %s, INTERVAL CAST( m.meta_value AS UNSIGNED ) DAY )",
				$now
			)
		);

		if ( $result['internal'] || $result['external'] ) {
			$result['extra_text'] = (int) $wpdb->query(
				"DELETE x FROM {$wpdb->prefix}wpdr_en_extra_text x
				 WHERE NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}wpdr_notification_log l WHERE l.extra_text_id = x.id )
				 AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}wpdr_ext_notice_log e WHERE e.extra_text_id = x.id )"
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $result;
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
	 * Add subscription to notifications to the User's Profile and Add New User screens.
	 *
	 * @since 1.0
	 * @param WP_User|string $user User record; on Add New User, the form's name ('add-new-user' or 'add-existing-user').
	 * @return void
	 */
	public function user_profile( $user ) {
		// Add New User has no user yet (the role is checked when it is saved); adding an existing user
		// to a network site keeps that user's own settings. Otherwise the user's role must have the choice.
		$new_user = ( 'add-new-user' === $user );
		if ( ! $new_user && ( ! $user instanceof WP_User || ! (bool) array_intersect( $user->roles, self::$internal_roles ) ) ) {
			return;
		}
		// Wrapper.
		echo '<div class="wpdr-en-wrapper">';

		// Header.
		echo '<div class="wpdr_en-header">';
		echo '<h3>' . esc_html__( 'Document Email Settings', 'email-notice-wp-document-revisions' ) . '</h3>';
		echo '</div>'; // wpdr_en-header end.

		$notify = 0;
		$attach = 0;
		if ( ! $new_user && ! empty( $user->ID ) ) {
			$notify = (int) get_user_meta( $user->ID, 'wpdr_en_user_notification', true );
			$attach = (int) get_user_meta( $user->ID, 'wpdr_en_user_attachment', true );
		}

		echo '<div class="wpdr_en-content">';
		// "me" on the user's own profile; "this user" when adding or editing someone else.
		if ( ! $new_user && get_current_user_id() === (int) $user->ID ) {
			$notify_label = __( 'Notify me by email when a new document is published', 'email-notice-wp-document-revisions' );
			$attach_label = __( 'Also send me the document as an attachment by email when a new document is published', 'email-notice-wp-document-revisions' );
		} else {
			$notify_label = __( 'Notify this user by email when a new document is published', 'email-notice-wp-document-revisions' );
			$attach_label = __( 'Also send this user the document as an attachment by email when a new document is published', 'email-notice-wp-document-revisions' );
		}
		echo '<input type="checkbox" name="wpdr_en_user_notification" value="1"' . checked( 1, $notify, false ) . '>' . esc_html( $notify_label ) . '</input><br/>';

		echo '<input type="checkbox" name="wpdr_en_user_attachment" value="1"' . checked( 1, $attach, false ) . '>' . esc_html( $attach_label ) . '</input><br/>';
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
		// Only from the profile and Add New User forms. A user created any other way (code, import,
		// registration) is left alone: check_admin_referer() would stop that request.
		$profile = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id );
		$create  = isset( $_POST['_wpnonce_create-user'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce_create-user'] ) ), 'create-user' );
		if ( ! ( $profile || $create ) ) {
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
		// Don't create the metabox if this is a PublishPress Revisions revision.
		if ( (int) get_post_meta( get_the_ID(), '_rvy_base_post_id', true ) ) {
			return;
		}
		add_meta_box( 'wpdr_en_sectionid', __( 'Document Email Notifications', 'email-notice-wp-document-revisions' ), array( $this, 'add_metabox' ), 'document', 'side', 'high' );
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

		// Is extra text possible?
		$extra_int = str_contains( $this->get_content( -1 ), '%extra%' );
		// External lists are only shown to users who can read them.
		$can_read_lists = current_user_can( 'read_doc_ext_lists' );
		$extra_ext      = $can_read_lists && str_contains( $this->get_content( 0 ), '%extra%' );
		$extra          = $extra_int || $extra_ext;

		echo '<div class="wpdr-en-add-meta"><div>';
		if ( $extra ) {
			echo '<div>' . esc_html__( 'Optional Extra text for this specific mailing', 'email-notice-wp-document-revisions' ) . '<fieldset>';
			echo '<textarea rows="4" cols="30" name="wpdr-en-extra" id="wpdr-en-extra"' . ( $extra ? '' : ' disabled' ) . '></textarea><br />';
			echo '<label class="screen-reader-text" for="wpdr-en-extra">' . esc_html__( 'Optional Extra text', 'email-notice-wp-document-revisions' ) . '</label>';
			esc_html_e( 'Enter any extra text and click Add Extra text before (re)sending.', 'email-notice-wp-document-revisions' );
			echo '</fieldset></div><br />';
		} else {
			echo '<div>' . esc_html__( 'Optional Extra text not configured in templates', 'email-notice-wp-document-revisions' ) . '</div><br />';
		}
		// Internal user lists box.
		if ( self::$internal_list_needed ) {
			$notification_sent = (string) get_post_meta( $document_id, 'wpdr_en_notification_sent', true );
			$recipients        = $this->prepare_mail_recipients( $document_id );
			$hasrecipient      = ! empty( $recipients );
			echo '<div class="wpdr-en-add-text"><div>';
			if ( ( ! empty( $notification_sent ) || '1' === $notification_sent ) && in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) && $hasrecipient ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_attr__( 'Re-send notification email(s)', 'email-notice-wp-document-revisions' ) . '" />';
			} elseif ( in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) && ( empty( $notification_sent ) || '0' === $notification_sent ) && $hasrecipient ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_attr__( 'Send notification email(s)', 'email-notice-wp-document-revisions' ) . '"/>';
			} elseif ( empty( $notification_sent ) || '0' === $notification_sent ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_attr__( 'Send notification email(s)', 'email-notice-wp-document-revisions' ) . '" disabled/>';
			} elseif ( ! empty( $notification_sent ) || '1' === $notification_sent ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_attr__( 'Re-send notification email(s)', 'email-notice-wp-document-revisions' ) . '" disabled/>';
			}
			echo '</div>';
			if ( $extra ) {
				echo '<div style="margin-left: 5px;"><label>';
				// Is extra text available for internal.
				if ( $extra_int ) {
					echo '<input type="checkbox" id="wpdr-en-int-extra" value="0" disabled />';
				} else {
					echo '<input type="checkbox" id="wpdr-en-int-extra" value="0" class="wpdr_en_not_use" disabled />';
				}
				echo esc_html__( 'Add Extra text', 'email-notice-wp-document-revisions' ) . '</label></div></div>';
			}
		}
		// Pass document id to jQuery.
		echo '<div id="wraphidden">';
		echo '<span id="dProgress" style="display:none; margin-top: 5px;" >';
		echo '<img id="spnSendNotifications" src="' . esc_url( admin_url() ) . '/images/wpspin_light.gif">';
		echo '</span>';

		// phpcs:disable
		/* To debug uncomment the following.
		if ( in_array( $pstatus, array( 'Public','Password protected','Private' ), true ) ) {
			_e( 'Can send mail', 'email-notice-wp-document-revisions' );
		} else {
			_e( 'Should not send mail', 'email-notice-wp-document-revisions' );
		}
		echo '<br/>';
		if ( empty($notification_sent ) || '0' === $notification_sent ) {
			_e( 'Not yet sent', 'email-notice-wp-document-revisions' );
		} else {
			_e( 'Already sent', 'email-notice-wp-document-revisions' );
		}
		echo '<br/>';
		if ( $hasrecipient ) {
			_e( 'Recipient exists', 'email-notice-wp-document-revisions' );
		} else {
			_e( 'No recipient', 'email-notice-wp-document-revisions' );
		}
		*/
		// phpcs:enable
		echo '<span id="wpdr-en-message" style="display: none; margin-left: 5px;">' . esc_html__( 'Sending email notifications...', 'email-notice-wp-document-revisions' ) . '</span>';
		echo '</span></div>';
		// external users (only for those who can read the lists).
		if ( $can_read_lists ) {
			echo '<br />';
			$ext_notice_sent = (string) get_post_meta( $document_id, 'wpdr_en_ext_notice_sent', true );
			// only need to know if there are recipients so do a shortcut version. Not selected lsts yet.
			$ext_lists       = $this->prepare_mail_ext_users( $document_id, array(), false );
			$hasextrecipient = ! empty( $ext_lists );
			// Only list editors may deselect lists (the server ignores the selection from anyone else).
			$read = ( current_user_can( 'edit_doc_ext_lists' ) ? '' : ' disabled' );
			if ( ( ! empty( $ext_notice_sent ) || '1' === $ext_notice_sent ) && 'Public' === $pstatus && $hasextrecipient ) {
				echo '<div class="wpdr-en-add-text"><div>';
				echo '<input type="button" id="wpdr-en-ext-note" class="button-secondary" value="' . esc_attr__( 'Re-send external list email(s)', 'email-notice-wp-document-revisions' ) . '" />';
				echo '</div>';
				// Is extra text available for external.
				if ( $extra ) {
					echo '<div style="margin-left: 5px; margin-right: 5px;"><label>';
					if ( $extra_ext ) {
						echo '<input type="checkbox" id="wpdr-en-ext-extra" value="0" disabled />';
					} else {
						echo '<input type="checkbox" id="wpdr-en-ext-extra" value="0" class="wpdr_en_not_use" disabled />';
					}
					echo esc_html__( 'Add Extra text', 'email-notice-wp-document-revisions' ) . '</label></div>';
				}
				echo '</div>';
				// output the list(s) available (disabled if cannot edit the lists).
				foreach ( $ext_lists as $list ) {
					echo '<br /><label>&nbsp;&nbsp;';
					echo '<input name="wpdr-en-ext-list" type="checkbox" value="' . esc_attr( $list['list_id'] ) . '" checked="checked"' . esc_attr( $read ) . '>  ' . esc_html( $list['list_title'] );
					echo '</label>';
				}
			} elseif ( 'Public' === $pstatus && ( empty( $ext_notice_sent ) || '0' === $ext_notice_sent ) && $hasextrecipient ) {
				echo '<div class="wpdr-en-add-text"><div>';
				echo '<input type="button" id="wpdr-en-ext-note" class="button-secondary" value="' . esc_attr__( 'Send external list email(s)', 'email-notice-wp-document-revisions' ) . '"/>';
				// Is extra text available for external.
				echo '</div><div style=" 5px; margin-right: 5px;"><label>';
				if ( str_contains( $this->get_content( 0 ), '%extra%' ) ) {
					echo '<input type="checkbox" id="wpdr-en-ext-extra" value="0" disabled />';
					$extra = true;
				} else {
					echo '<input type="checkbox" id="wpdr-en-ext-extra" value="0" class="wpdr_en_not_use" disabled />';
				}
				echo esc_html__( 'Add Extra text', 'email-notice-wp-document-revisions' ) . '</label></div>';
				// output the list(s) available (disabled if cannot edit the lists).
				foreach ( $ext_lists as $list ) {
					echo '<br /><label>&nbsp;&nbsp;';
					echo '<input name="wpdr-en-ext-list" type="checkbox" value="' . esc_attr( $list['list_id'] ) . '" checked="checked"' . esc_attr( $read ) . '>  ' . esc_html( $list['list_title'] );
					echo '</label>';
				}
			} elseif ( empty( $ext_notice_sent ) || '0' === $ext_notice_sent ) {
				echo '<input type="button" id="wpdr-en-ext-note" class="button-secondary" value="' . esc_attr__( 'Send external list email(s)', 'email-notice-wp-document-revisions' ) . '" disabled/>';
			} elseif ( ! empty( $ext_notice_sent ) || '1' === $ext_notice_sent ) {
				echo '<input type="button" id="wpdr-en-ext-note" class="button-secondary" value="' . esc_attr__( 'Re-send external list email(s)', 'email-notice-wp-document-revisions' ) . '" disabled/>';
			}
		}
		echo '</div>';
		echo '</div>';
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
		add_submenu_page( 'edit.php?post_type=doc_ext_list', __( 'External User Email Log', 'email-notice-wp-document-revisions' ), __( 'External User Email Log', 'email-notice-wp-document-revisions' ), 'read_doc_ext_lists', 'wpdr_en_ext_notice_log', array( $this, 'ext_notice_log_list' ) );
		// internal list menu item may be removed later.
		add_submenu_page( 'edit.php?post_type=doc_ext_list', __( 'Internal User Email Log', 'email-notice-wp-document-revisions' ), __( 'Internal User Email Log', 'email-notice-wp-document-revisions' ), 'read_doc_ext_lists', 'wpdr_en_notification_log', array( $this, 'notification_log_list' ) );
	}

	/**
	 * Send a JSON error response for an AJAX request and stop.
	 *
	 * @since 3.3.0
	 * @param string      $code Error code shown to the user.
	 * @param string|null $msg  Error message (default "Something went wrong").
	 * @return void
	 */
	private static function ajax_error( $code, $msg = null ) {
		$result = array(
			'error'               => true,
			'error_msg'           => ( is_null( $msg ) ? __( 'Something went wrong', 'email-notice-wp-document-revisions' ) : $msg ),
			'logged_count'        => 0,
			'sent_count'          => 0,
			'sending_error_count' => 0,
			'error_code'          => $code,
		);
		wp_send_json( $result );
	}

	/**
	 * Check that an address-list AJAX request is for a Document External List the current user can edit.
	 *
	 * Sends a JSON error response and stops if not.
	 *
	 * @since 3.3.0
	 * @param int $id Post ID from the request.
	 * @return int
	 */
	private static function check_list_post( $id ) {
		$list = get_post( $id );
		if ( ! $list instanceof WP_Post || 'doc_ext_list' !== $list->post_type ) {
			self::ajax_error( 'F-13' );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			self::ajax_error( 'F-12', __( 'You do not have permission to edit this list.', 'email-notice-wp-document-revisions' ) );
		}
		return $id;
	}

	/**
	 * Check that a send request is for a Document the current user can edit.
	 *
	 * Sends a JSON error response and stops if not.
	 *
	 * @since 3.3.0
	 * @param int $post_id Post ID from the request.
	 * @return int
	 */
	private static function check_send_post( $post_id ) {
		$document = get_post( $post_id );
		if ( ! $document instanceof WP_Post || 'document' !== $document->post_type ) {
			self::ajax_error( 'F-07' );
		}
		if ( ! current_user_can( 'edit_document', $post_id ) ) {
			self::ajax_error( 'F-08', __( 'You do not have permission to send notifications for this document.', 'email-notice-wp-document-revisions' ) );
		}
		return $post_id;
	}

	/**
	 * Edit address in post.
	 *
	 * @since 2.0
	 */
	public static function edit_address() {
		// Avoid being easily hacked.
		if ( ! isset( $_POST['wpdr_en_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdr_en_nonce'] ) ), 'wpdr_en_nonce' ) ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
				'error_code' => 'F-11',
			);
			wp_send_json( $result );
		}

		// The post must be a Document External List that the current user can edit.
		$id = self::check_list_post( isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0 );

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
		// Anything entered must be a valid address (sanitize_email() returns '' for rubbish, so check before relying on it).
		if ( ! empty( $email ) ) {
			$email = sanitize_email( $email );
			if ( ! is_email( $email ) ) {
				self::ajax_error( 'F-17', __( 'Invalid email address', 'email-notice-wp-document-revisions' ) );
			}
		}
		if ( empty( $user_name ) || empty( $email ) ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
				'error_code' => 'F-14',
			);
			wp_send_json( $result );
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
		wp_send_json( $result );
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
				'error_msg'  => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
				'error_code' => 'F-11',
			);
			wp_send_json( $result );
		}

		// The post must be a Document External List that the current user can edit.
		$id = self::check_list_post( isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0 );

		$del_rec = null;
		if ( isset( $_POST['del_rec'] ) ) {
			$del_rec = (int) sanitize_text_field( wp_unslash( $_POST['del_rec'] ) );
		}
		if ( is_null( $del_rec ) ) {
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
				'error_code' => 'F-14',
			);
			wp_send_json( $result );
		}

		$users_rec = get_post_meta( $id, 'wpdr_en_addressees', true );
		if ( false === $users_rec || empty( $users_rec ) ) {
			// list should exist.
			$result = array(
				'error'      => true,
				'error_msg'  => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
				'error_code' => 'F-15',
			);
			wp_send_json( $result );
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
				'error_msg'  => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
				'error_code' => 'F-16',
			);
			wp_send_json( $result );
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
		wp_send_json( $result );
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
				'error_msg'           => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
				'logged_count'        => 0,
				'sent_count'          => 0,
				'sending_error_count' => 0,
				'error_code'          => 'F-21',
			);
			wp_send_json( $result );
		}
		$result = array();
		if ( isset( $_GET['post_id'] ) ) {
			$post_id = absint( $_GET['post_id'] );
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
		wp_send_json( $result );
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
		$asset = self::asset( 'js', 'wpdr-en-log' );
		wp_register_script(
			'wpdr_en_log',
			$asset['url'],
			array( 'jquery' ),
			$asset['version'],
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
		if ( ! current_user_can( 'read_doc_ext_lists' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'email-notice-wp-document-revisions' ) );
		}

		$this->enqueue_log_js();
		require_once __DIR__ . '/class-wpdr-en-user-log-table.php';

		// #TODO: add filter/search.
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Document Email Notification Log', 'email-notice-wp-document-revisions' ) . '</h1>';
		echo ' <a href="' . esc_url( self::export_url( 'internal' ) ) . '" class="page-title-action">' . esc_html__( 'Export CSV', 'email-notice-wp-document-revisions' ) . '</a>';
		$wpdr_en_notification_log_table         = new WPDR_EN_User_Log_Table();
		$wpdr_en_notification_log_table->screen = convert_to_screen( null );
		$wpdr_en_notification_log_table->prepare_items();
		echo '<form method="get">';
		echo '<input type="hidden" name="post_type" value="doc_ext_list" />';
		echo '<input type="hidden" name="page" value="wpdr_en_notification_log" />';
		$wpdr_en_notification_log_table->search_box( esc_html__( 'Search', 'email-notice-wp-document-revisions' ), 'search_id' );
		echo '</form>';
		$wpdr_en_notification_log_table->display();
		echo '<p>' . esc_html__( 'Note: Success denotes that the email was successfully received by your email system.', 'email-notice-wp-document-revisions' ) . '</p>';
		echo '<p>' . esc_html__( 'The mail delivery process to the recipient is managed within your email system.', 'email-notice-wp-document-revisions' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Output the external notice log itself.
	 *
	 * @since 2.0
	 * @return void
	 */
	public function ext_notice_log_list() {
		if ( ! current_user_can( 'read_doc_ext_lists' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'email-notice-wp-document-revisions' ) );
		}

		$this->enqueue_log_js();
		require_once __DIR__ . '/class-wpdr-en-ext-log-table.php';

		// #TODO: add filter/search.
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Document Email External Notice Log', 'email-notice-wp-document-revisions' ) . '</h1>';
		echo ' <a href="' . esc_url( self::export_url( 'external' ) ) . '" class="page-title-action">' . esc_html__( 'Export CSV', 'email-notice-wp-document-revisions' ) . '</a>';
		$wpdr_en_ext_notice_log_table         = new WPDR_EN_Ext_Log_Table();
		$wpdr_en_ext_notice_log_table->screen = convert_to_screen( null );
		$wpdr_en_ext_notice_log_table->prepare_items();
		echo '<form method="get">';
		echo '<input type="hidden" name="post_type" value="doc_ext_list" />';
		echo '<input type="hidden" name="page" value="wpdr_en_ext_notice_log" />';
		$wpdr_en_ext_notice_log_table->search_box( esc_html__( 'Search', 'email-notice-wp-document-revisions' ), 'search_id' );
		echo '</form>';
		$wpdr_en_ext_notice_log_table->display();
		echo '<p>' . esc_html__( 'Note: Success denotes that the mail was successfully received by your email system.', 'email-notice-wp-document-revisions' ) . '</p>';
		echo '<p>' . esc_html__( 'The delivery process to the recipient is managed within your email system.', 'email-notice-wp-document-revisions' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Link to export a log as CSV, keeping the page's search and sort.
	 *
	 * @since 4.0.0
	 * @param string $log 'internal' or 'external'.
	 * @return string URL.
	 */
	public static function export_url( $log ) {
		$args = array(
			'action' => 'wpdr_en_export_log',
			'log'    => $log,
		);
		foreach ( array( 's', 'orderby', 'order' ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		return wp_nonce_url( add_query_arg( rawurlencode_deep( $args ), admin_url( 'admin-post.php' ) ), 'wpdr_en_export_log' );
	}

	/**
	 * The rows of a log export: a header row, then every log row matching the search, in the log's order.
	 *
	 * @since 4.0.0
	 * @param string $log 'internal' or 'external'.
	 * @return array[] Rows of cell values.
	 */
	public static function export_rows( $log ) {
		if ( 'external' === $log ) {
			require_once __DIR__ . '/class-wpdr-en-ext-log-table.php';
			$fields = array(
				'id'             => __( 'ID', 'email-notice-wp-document-revisions' ),
				'post_id'        => __( 'Document ID', 'email-notice-wp-document-revisions' ),
				'post_title'     => __( 'Document', 'email-notice-wp-document-revisions' ),
				'list_id'        => __( 'List ID', 'email-notice-wp-document-revisions' ),
				'list_title'     => __( 'List', 'email-notice-wp-document-revisions' ),
				'time_mail_sent' => __( 'Email sent', 'email-notice-wp-document-revisions' ),
				'user_name'      => __( 'Name', 'email-notice-wp-document-revisions' ),
				'user_email'     => __( 'Email', 'email-notice-wp-document-revisions' ),
				'status'         => __( 'Status', 'email-notice-wp-document-revisions' ),
				'extra_text'     => __( 'Extra Text', 'email-notice-wp-document-revisions' ),
			);
			$items  = WPDR_EN_Ext_Log_Table::get_log_items();
		} else {
			require_once __DIR__ . '/class-wpdr-en-user-log-table.php';
			$fields = array(
				'id'                => __( 'ID', 'email-notice-wp-document-revisions' ),
				'post_id'           => __( 'Document ID', 'email-notice-wp-document-revisions' ),
				'post_title'        => __( 'Document', 'email-notice-wp-document-revisions' ),
				'time_mail_sent'    => __( 'Email sent', 'email-notice-wp-document-revisions' ),
				'user_id'           => __( 'User ID', 'email-notice-wp-document-revisions' ),
				'user_display_name' => __( 'User Name', 'email-notice-wp-document-revisions' ),
				'user_email'        => __( 'User Email', 'email-notice-wp-document-revisions' ),
				'status'            => __( 'Status', 'email-notice-wp-document-revisions' ),
				'extra_text'        => __( 'Extra Text', 'email-notice-wp-document-revisions' ),
			);
			$items  = WPDR_EN_User_Log_Table::get_log_items();
		}

		$rows = array( array_values( $fields ) );
		foreach ( (array) $items as $item ) {
			$row = array();
			foreach ( array_keys( $fields ) as $key ) {
				$row[] = isset( $item[ $key ] ) ? (string) $item[ $key ] : '';
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * CSV text for rows: UTF-8 with a byte order mark (so Excel reads accents), comma separated.
	 *
	 * A cell starting with =, +, -, @, tab or carriage return gets a leading apostrophe so a
	 * spreadsheet shows it as text rather than running it as a formula.
	 *
	 * @since 4.0.0
	 * @param array[] $rows Rows of cell values.
	 * @return string
	 */
	public static function csv( $rows ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions
		$handle = fopen( 'php://temp', 'r+' );
		foreach ( $rows as $row ) {
			foreach ( $row as $i => $cell ) {
				$cell = (string) $cell;
				if ( '' !== $cell && false !== strpos( "=+-@\t\r", $cell[0] ) ) {
					$cell = "'" . $cell;
				}
				$row[ $i ] = $cell;
			}
			fputcsv( $handle, $row, ',', '"', '' );
		}
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );
		// phpcs:enable WordPress.WP.AlternativeFunctions
		return "\xEF\xBB\xBF" . $csv;
	}

	/**
	 * Send a log as a CSV download (admin-post.php?action=wpdr_en_export_log).
	 *
	 * Needs the same capability as viewing the logs, and the nonce from the page's Export link.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public function export_log() {
		check_admin_referer( 'wpdr_en_export_log' );
		if ( ! current_user_can( 'read_doc_ext_lists' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'email-notice-wp-document-revisions' ), 403 );
		}
		$log = ( isset( $_GET['log'] ) && 'external' === $_GET['log'] ? 'external' : 'internal' );

		$csv  = self::csv( self::export_rows( $log ) );
		$name = sanitize_file_name( ( 'external' === $log ? 'external-email-log-' : 'internal-email-log-' ) . wp_date( 'Y-m-d' ) . '.csv' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . strlen( $csv ) );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV file, not HTML.
		exit;
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'email-notice-wp-document-revisions' ) );
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
		echo '<h2 class="wp-heading-inline">' . esc_html__( 'User Addressee List', 'email-notice-wp-document-revisions' ) . '</h2>';
		$wpdr_en_ext_user_table = new WPDR_EN_Ext_User_Table();
		$wpdr_en_ext_user_table->prepare_items();
		$wpdr_en_ext_user_table->search_box( esc_html__( 'Search', 'email-notice-wp-document-revisions' ), 'search_id' );
		$wpdr_en_ext_user_table->display();
		echo '<input id="empty_addr" type="hidden" value="' . esc_attr( $wpdr_en_ext_user_table::$no_addr ) . '">';
		echo '</div>';
		remove_filter( 'set_url_scheme', array( __CLASS__, 'set_url_scheme' ), 10 );
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
		add_meta_box( 'del_parms', __( 'List Attributes', 'email-notice-wp-document-revisions' ), array( &$this, 'del_attrs_meta_box' ), 'doc_ext_list', 'normal', 'high' );

		add_meta_box( 'doc_ext_list', __( 'Document External User List', 'email-notice-wp-document-revisions' ), array( &$this, 'doc_ext_list_metabox' ), 'doc_ext_list', 'normal', 'high' );
	}

	/**
	 * Metabox for user data,.
	 *
	 * @since 2.0
	 * @global WP_Post $post Post object.
	 */
	public function doc_ext_list_metabox() {
		if ( ! current_user_can( 'edit_doc_ext_lists' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'email-notice-wp-document-revisions' ) );
		}

		global $post;
		?>
		<div class="metabox-holder inside">
		<table id="user-entry" class="form-table" style="clear:none;">
			<tbody>
			<tr>
			<td>User Name</td>
			<td><input type="text" id="wpdr-en-user-name" name="user-name" value="" onblur="check_address()" size=30 /></td>
			</tr>
			<tr>
			<td>Email Address</td>
			<td><input type="text" id="wpdr-en-email" name="email" value="" onblur="check_address()" size=30 /></td>
			</tr>
			<tr>
			<td>Pause Mail</td>
			<td><input type="checkbox" id="wpdr_pause" name="pause" /></td>
			</tr>
			<tr>
			<td><div id="clear_address" name="clear_address" style="display:none;" >
			<input type="submit" class="button button-large" value="<?php esc_attr_e( 'Clear values', 'email-notice-wp-document-revisions' ); ?>" onclick="wpdr_en_clear()"/>
			</div></td>
			<td><input type="submit" id="add_address" name="add_address" class="button button-large" value="<?php esc_attr_e( 'Add Address to List', 'email-notice-wp-document-revisions' ); ?>" onclick="wpdr_en_insert()" disabled /></td>
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'email-notice-wp-document-revisions' ) );
		}

		global $post;
		$tm_rule = (int) get_post_meta( $post->ID, 'wpdr_en_tm', true );
		// find whether to attach doument - use site option as initial default.
		$meta   = get_post_meta( $post->ID, 'wpdr_en_attach', false );
		$attach = ( is_array( $meta ) && ! empty( $meta ) ? (int) $meta[0] : (int) get_option( 'wpdr_en_set_ext_attach' ) );
		$pause  = (int) get_post_meta( $post->ID, 'wpdr_en_pause', true );
		?>
		<h3 id="tm_label" class="hndle" style="padding-left: 0;"><?php esc_html_e( 'Match Rule', 'email-notice-wp-document-revisions' ); ?></h3>
		<div id="tm_descr"><?php esc_html_e( 'Choose whether any or all of this lists taxonomy elements must match those on the document to be considered a match.', 'email-notice-wp-document-revisions' ); ?></div>
		<div id="tm_ruled" role="radiogroup" aria-labelledby="tm_label" aria-describedby="tm_descr">
		<fieldset>
		<label><input type="radio" id="tm_any" name="tm_rule" <?php checked( 0, $tm_rule, true ); ?> value="0"><?php esc_html_e( 'Any taxonomy element', 'email-notice-wp-document-revisions' ); ?></label><br />
		<label><input type="radio" id="tm_all" name="tm_rule" <?php checked( 1, $tm_rule, true ); ?> value="1"><?php esc_html_e( 'All taxonomy elements', 'email-notice-wp-document-revisions' ); ?></label>
		</fieldset>
		</div>
		<h3 id="attach_label" class="hndle" style="padding-left: 0;"><?php esc_html_e( 'Document Attach', 'email-notice-wp-document-revisions' ); ?></h3>
		<fieldset>
		<input type="checkbox" id="wpdr_en_attach" name="wpdr_en_attach" value="<?php echo esc_attr( $attach ) . '" ' . checked( 1, $attach, false ); ?>>
		<label for="wpdr_en_attach"><?php esc_html_e( 'Attach Document to Notification Emails', 'email-notice-wp-document-revisions' ); ?></label>
		</fieldset>
		<h3 id="pause_label" class="hndle" style="padding-left: 0;"><?php esc_html_e( 'Pause Mail', 'email-notice-wp-document-revisions' ); ?></h3>
		<fieldset>
		<input type="checkbox" id="wpdr_en_pause" name="wpdr_en_pause" value="<?php echo esc_attr( $pause ) . '" ' . checked( 1, $pause, false ); ?>>
		<label for="wpdr_en_pause"><?php esc_html_e( 'Pause this List', 'email-notice-wp-document-revisions' ); ?></label>
		</fieldset>
		<h3 id="log_days_label" class="hndle" style="padding-left: 0;"><?php esc_html_e( 'Email Log', 'email-notice-wp-document-revisions' ); ?></h3>
		<fieldset>
		<?php
		$log_days   = absint( get_post_meta( $post->ID, 'wpdr_en_log_days', true ) );
		$can_delete = current_user_can( 'delete_doc_ext_lists' );
		?>
		<label for="wpdr_en_log_days"><?php esc_html_e( 'Delete this List\'s log entries for Documents not modified for', 'email-notice-wp-document-revisions' ); ?>
		<input type="number" id="wpdr_en_log_days" name="wpdr_en_log_days" min="0" step="1" class="small-text" value="<?php echo esc_attr( $log_days ? $log_days : '' ); ?>"<?php disabled( ! $can_delete ); ?>>
		<?php esc_html_e( 'days', 'email-notice-wp-document-revisions' ); ?></label><br />
		<?php
		esc_html_e( 'Empty or 0 keeps them. Checked once a day.', 'email-notice-wp-document-revisions' );
		if ( ! $can_delete ) {
			// Explains why the box is greyed out.
			echo ' ' . esc_html__( 'Only users who can delete Lists can change this.', 'email-notice-wp-document-revisions' );
		}
		?>
		</fieldset>
		<h3 id="note_label" class="hndle" style="padding-left: 0;"><?php esc_html_e( 'Document List Notes', 'email-notice-wp-document-revisions' ); ?></h3>
		<fieldset>
		<textarea rows="4" cols="40" name="excerpt" id="excerpt"><?php echo esc_textarea( $post->post_excerpt ); ?></textarea>
		<label class="screen-reader-text" for="excerpt"><?php esc_html_e( 'Document List Notes', 'email-notice-wp-document-revisions' ); ?></label>
		<?php esc_html_e( 'You can use this to hold any notes for this list entry.', 'email-notice-wp-document-revisions' ); ?>
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
	 * Replace default <wordpress@yourdomain.com> email address.
	 *
	 * @since 1.0
	 * @param string $email Default From email address.
	 * @return string
	 */
	public function wp_mail_from( $email ) {
		$sitename = self::default_from_domain();
		// Override only default email address - provides compatibility with other plugins.
		if ( 'wordpress@' . $sitename === $email ) {
			return get_option( 'wpdr_en_set_email_from_address' );
		} else {
			return $email;
		}
	}

	/**
	 * Replace default email from "WordPress".
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
	%repeat% means output a sentence if the document has been previously emailed
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
	 * @return object[]
	 */
	private function prepare_mail_recipients( $post_id ) {
		$result = array();
		global $wpdb;
		$pstatus = $this->post_status( $post_id );

		if ( ! in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) || ! self::notify_about( $pstatus ) ) {
			return $result;
		}

		// Users who asked for notifications and belong to this site (users are shared across a network;
		// a site's members have its capabilities key).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wanted = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID AS user_id,
					u.display_name,
					u.user_email AS user_email
				 FROM {$wpdb->users} u
				 INNER JOIN {$wpdb->usermeta} um
				 ON u.ID = um.user_id
				 WHERE um.meta_key = 'wpdr_en_user_notification'
				 AND um.meta_value = '1'
				 AND EXISTS ( SELECT 1 FROM {$wpdb->usermeta} c WHERE c.user_id = u.ID AND c.meta_key = %s )",
				$wpdb->get_blog_prefix() . 'capabilities'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Public & Password protected posts go to all of them; private ones only to those who can read them.
		if ( 'Private' !== $pstatus ) {
			return $wanted;
		}
		foreach ( $wanted as $value ) {
			if ( user_can( $value->user_id, 'read_document', $post_id ) ) {
				$result[] = $value;
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
	 * @return mixed[]
	 */
	private function prepare_mail_ext_users( $post_id, $lists, $all ) {
		$result  = array();
		$pstatus = $this->post_status( $post_id );

		// Public post only.
		if ( 'Public' !== $pstatus ) {
			return $result;
		}

		global $wpdb;
		// get the direct term taxonomy matches.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tt_ids = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_taxonomy_id
				 FROM {$wpdb->term_relationships} tt
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
			$list = implode( ',', array_map( 'intval', wp_list_pluck( $tt_ids, 'term_taxonomy_id' ) ) );
			// Don't Use placeholders as prepare can get confused by comma-separated list.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$pp_ids = $wpdb->get_results(
				"SELECT p.term_taxonomy_id
				 FROM {$wpdb->term_taxonomy} t
				 INNER JOIN {$wpdb->term_taxonomy} p
				 ON  p.term_id = t.parent
				 AND p.taxonomy = t.taxonomy
				 WHERE t.term_taxonomy_id IN ( " . $list . ' )
				 AND   p.term_taxonomy_id NOT IN ( ' . $list . ' )
				 GROUP BY p.term_taxonomy_id',
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

			if ( ! empty( $pp_ids ) ) {
				$tt_ids  = $pp_ids;
				$tot_ids = array_merge( $tot_ids, $pp_ids );
			}
		} while ( ! empty( $pp_ids ) );

		// find the matching lists.
		$tlist = implode( ',', array_map( 'intval', wp_list_pluck( $tot_ids, 'term_taxonomy_id' ) ) );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$olists = $wpdb->get_results(
			"SELECT up.ID AS list_id,
					up.post_title as list_title,
					(SELECT meta_value
					 FROM {$wpdb->postmeta}
					 WHERE post_id = up.ID
					 AND meta_key = 'wpdr_en_addressees') as users_rec,
					(SELECT meta_value
					 FROM {$wpdb->postmeta}
					 WHERE post_id = up.ID
					 AND meta_key = 'wpdr_en_tm') as tm_rule,
					 COALESCE( (SELECT meta_value
					 FROM {$wpdb->postmeta}
					 WHERE post_id = up.ID
					 AND meta_key = 'wpdr_en_attach'),
					 (SELECT option_value
					 FROM {$wpdb->options}
					 WHERE option_name = 'wpdr_en_set_ext_attach'),
					 0 ) as attach,
					 COALESCE( (SELECT meta_value
					 FROM {$wpdb->postmeta}
					 WHERE post_id = up.ID
					 AND meta_key = 'wpdr_en_pause'),
					 0 ) as pause,
					COUNT(1) as matches,
					(SELECT COUNT(1)
					 FROM {$wpdb->term_relationships} tr
					 WHERE object_id = up.ID) as tot_terms
			 FROM {$wpdb->posts} up
			 INNER JOIN {$wpdb->term_relationships} ut
			 ON ut.object_id = up.ID
			 WHERE up.post_type = 'doc_ext_list'
			 AND up.post_status = 'publish'
			 AND ut.term_taxonomy_id IN ( " . $tlist . ' )
			 GROUP BY up.ID
			 HAVING (tm_rule = 0 OR matches = tot_terms) AND pause = 0',
			ARRAY_A
		);

		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
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
			$template = __( 'New document: ', 'email-notice-wp-document-revisions' ) . get_the_title( $post_id );
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
	 * @return int|null
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
		$extra_id = $this->insert_extra( $post_id, $extra );

		// post considered no protected if field is empty.
		$no_password = empty( get_post_field( 'post_password', $post_id ) );
		foreach ( $recipients as $value ) {
			// Make sure accented characters sent out correctly as well.
			$mail_subject = mb_encode_mimeheader( $this->prepare_mail_subject( $post_id, $value->user_id ), 'UTF-8' );
			$mail_content = $this->prepare_mail_content( $post_id, $value->user_id, $extra, null );
			// if there is no subject or content then do not send mail.
			if ( empty( $mail_subject ) || empty( $mail_content ) ) {
				if ( $this->log_mail_sent( $value->user_id, $post_id, $value->user_email, __( 'Empty mail subject and/or content.', 'email-notice-wp-document-revisions' ), $extra_id ) ) {
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
						$mail_content .= '<p>' . __( 'Document not attached.', 'email-notice-wp-document-revisions' ) . '</p>';
						/**
						 * Filters whether to attach the file depending on its size and type of user.
						 *
						 * @param bool true      default is to send the file.
						 * @param int  $filesize attachment file size.
						 * @param bool $internal indicates whether the notification is being sent to internal users..
						 */
					} elseif ( apply_filters( 'wpdr_en_filesize', true, filesize( $attachments[0] ), true ) ) {
						$mail_content .= '<p>' . __( 'Document attached.', 'email-notice-wp-document-revisions' ) . '</p>';
					} else {
						$mail_content .= '<p>' . __( 'Document too large to be attached.', 'email-notice-wp-document-revisions' ) . '</p>';
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
					// test sending process. Note Test is not translated.
					$status_text = 'Test ' . ( empty( $attachments ) ? __( 'Successful', 'email-notice-wp-document-revisions' ) : __( 'Success Attachment', 'email-notice-wp-document-revisions' ) );
					++$sent_count;
				} else {
					// Set mail type.
					$headers = array( 'Content-type: text/html' );

					// Send mail.
					$mail_status = wp_mail( self::recipient( $value->display_name, $value->user_email ), $mail_subject, $mail_content, $headers, $attachments );

					// set status message.
					if ( $mail_status ) {
						$status_text = ( empty( $attachments ) ? __( 'Successful', 'email-notice-wp-document-revisions' ) : __( 'Success Attachment', 'email-notice-wp-document-revisions' ) );
						++$sent_count;
					} else {
						$status_text = __( 'Failed', 'email-notice-wp-document-revisions' );
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
		self::remove_attachment();

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
				$status = __( 'Empty mail subject and/or content.', 'email-notice-wp-document-revisions' );
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
						$mail_content .= '<p>' . __( 'Document not attached.', 'email-notice-wp-document-revisions' ) . '</p>';
						/**
						 * Filters whether to attach the file depending on its size and type of user.
						 *
						 * @param bool true      default is to send the file.
						 * @param int  $filesize attachment file size.
						 * @param bool $internal indicates whether the notification is being sent to internal users..
						 */
					} elseif ( apply_filters( 'wpdr_en_filesize', true, filesize( $attachments[0] ), false ) ) {
						$mail_content .= '<p>' . __( 'Document attached.', 'email-notice-wp-document-revisions' ) . '</p>';
					} else {
						$mail_content .= '<p>' . __( 'Document too large to be attached.', 'email-notice-wp-document-revisions' ) . '</p>';
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
					// test sending process. Note Test is not translated.
					$status_text = 'Test ' . ( empty( $attachments ) ? __( 'Successful', 'email-notice-wp-document-revisions' ) : __( 'Success Attachment', 'email-notice-wp-document-revisions' ) );
					++$sent_count;
				} else {
					// Set mail type and how sent.
					$headers = array(
						'Content-type: text/html',
						'X-wpdr-en: ' . $post_id . '-' . $value->list_id . '-' . $value->rec_num,
					);

					// Send mail.
					$mail_status = wp_mail( self::recipient( $value->user_name, $value->user_email ), $mail_subject, $mail_content, $headers, $attachments );

					// set status message.
					if ( $mail_status ) {
						$status_text = ( empty( $attachments ) ? __( 'Successful', 'email-notice-wp-document-revisions' ) : __( 'Success Attachment', 'email-notice-wp-document-revisions' ) );
						++$sent_count;
					} else {
						$status_text = __( 'Failed', 'email-notice-wp-document-revisions' );
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
		self::remove_attachment();

		$result = array(
			'logged_count'        => $logged_count,
			'sent_count'          => $sent_count,
			'sending_error_count' => $sending_error_count,
		);
		return $result;
	}

	/**
	 * The "To" value for one recipient: "Name <email>".
	 *
	 * The wp_mail() function splits the To string on commas, so characters that would add a recipient or break
	 * the address part are removed from the name.
	 *
	 * @since 4.0.0
	 * @param string $name  Display name.
	 * @param string $email Email address.
	 * @return string
	 */
	public static function recipient( $name, $email ) {
		$name = trim( preg_replace( '/\s+/', ' ', str_replace( array( ',', ';', '<', '>', '"', "\r", "\n" ), ' ', (string) $name ) ) );
		return ( '' === $name ? $email : $name . ' <' . $email . '>' );
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
		 * @param int $delay default delay time (0.05 sec).
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
	 * @param int|null   $extra_id   Id of extra text string in DB (or nulll).
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
					'status'         => $status,
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
		// Ignore test mails in log. Note Test is not translated.
		$test = 'Test %';
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
					' WHERE user_email = %s AND post_id = %d AND status NOT LIKE %s GROUP BY user_email, post_id',
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
				'error_msg'           => __( 'Not logged and/or sent out successfully no mails.', 'email-notice-wp-document-revisions' ),
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
					'error_msg'           => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
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
						'error_msg'           => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
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
		self::remove_attachment();

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
				'error_msg'           => __( 'Not logged and/or sent out successfully any mails.', 'email-notice-wp-document-revisions' ),
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
					'error_msg'           => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
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
						'error_msg'           => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
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
		self::remove_attachment();

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
				'error_msg'           => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
				'logged_count'        => 0,
				'sent_count'          => 0,
				'sending_error_count' => 0,
				'error_code'          => 'F-02',
			);
			wp_send_json( $result );
		}
		$result = array();
		if ( isset( $_POST['post_id'] ) ) {
			// The post must be a Document that the current user can edit.
			$post_id = self::check_send_post( absint( $_POST['post_id'] ) );
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
		wp_send_json( $result );
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
				'error_msg'           => __( 'Something went wrong', 'email-notice-wp-document-revisions' ),
				'logged_count'        => 0,
				'sent_count'          => 0,
				'sending_error_count' => 0,
				'error_code'          => 'F-02',
			);
			wp_send_json( $result );
		}

		// input validated.
		$result = array();
		if ( isset( $_POST['post_id'] ) ) {
			// The post must be a Document that the current user can edit.
			$post_id = self::check_send_post( absint( $_POST['post_id'] ) );
			// External Lists are only available to users who can read them.
			if ( ! current_user_can( 'read_doc_ext_lists' ) ) {
				self::ajax_error( 'F-09', __( 'You do not have permission to send to Document External Lists.', 'email-notice-wp-document-revisions' ) );
			}
			// if all lists wanted then no lists array found. Only list editors may choose lists.
			if ( isset( $_POST['lists'] ) && current_user_can( 'edit_doc_ext_lists' ) ) {
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
		wp_send_json( $result );
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
		self::remove_attachment();

		if ( empty( $post_id ) ) {
			return;
		}

		if ( get_option( 'wpdr_en_set_notification_mode' ) !== 'Auto' ) {
			return;
		}
		// Sent once only: the flag is '1' after a successful send.
		$notification_sent = (string) get_post_meta( $post_id, 'wpdr_en_notification_sent', true );
		if ( '' !== $notification_sent && '0' !== $notification_sent ) {
			return;
		}
		$pstatus = $this->post_status( $post_id );
		if ( ! in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) || empty( $this->prepare_mail_recipients( $post_id ) ) ) {
			return;
		}

		add_post_meta( $post_id, 'wpdr_en_notification_sent', '0', true );
		// No extra text possible here.
		$result   = $this->notify( $post_id, '' );
		$log_link = ' <a href="' . esc_url( $result['log_page_url'] ) . '">' . esc_html__( 'Check log', 'email-notice-wp-document-revisions' ) . '</a> ' . esc_html__( 'for details.', 'email-notice-wp-document-revisions' );

		if ( ! $result['error'] ) {
			// Emails sent successfully.
			$class   = 'notice notice-success';
			$message = esc_html( $result['sent_count'] . ' ' . __( 'notification(s) sent.', 'email-notice-wp-document-revisions' ) );
		} elseif ( 0 === $result['logged_count'] || 0 === $result['sent_count'] ) {
			// Error.
			$class   = 'notice notice-error';
			$message = esc_html__( 'Error sending notifications.', 'email-notice-wp-document-revisions' );
		} else {
			// Warning.
			$class   = 'notice notice-warning';
			$message = esc_html( $result['sent_count'] . ' ' . __( 'email(s) out of', 'email-notice-wp-document-revisions' ) . ' ' . ( $result['sent_count'] + $result['sending_error_count'] ) . ' ' . __( 'sent with', 'email-notice-wp-document-revisions' ) . ' ' . ( $result['sent_count'] + $result['sending_error_count'] - $result['logged_count'] ) . ' ' . __( 'log issues.', 'email-notice-wp-document-revisions' ) );
		}

		// Shown on the next admin page of the user who published.
		set_transient(
			self::auto_notice_key(),
			array(
				'class'   => $class,
				'message' => $message . $log_link,
			),
			300
		);

		// remove attachment file if set.
		self::remove_attachment();
	}

	/**
	 * Transient name holding the result of an automatic send for the current user.
	 *
	 * @since 4.0.0
	 * @return string
	 */
	private static function auto_notice_key() {
		return 'wpdr_en_auto_notice_' . get_current_user_id();
	}

	/**
	 * Display automatic notifications on admin notice.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function admin_notice_auto_notification() {
		$notice = get_transient( self::auto_notice_key() );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( self::auto_notice_key() );
		echo '<div class="' . esc_attr( $notice['class'] ) . '" id="wpdr_en_notification_div">';
		echo '<p>' . wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) ) . '</p>';
		echo '</div>';
	}

	/**
	 * Delete the temporary copy of the document made for attaching, and its folder.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public static function remove_attachment() {
		if ( ! empty( self::$attach_file ) && is_array( self::$attach_file ) ) {
			$file = self::$attach_file[0];
			wp_delete_file( $file );
			$dir = dirname( $file );
			if ( str_starts_with( basename( $dir ), 'wpdr-en-' ) && is_dir( $dir ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
				@rmdir( $dir );
			}
		}
		self::$attach_file = null;
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
			// extract_document_id() returns false when there is no document file.
			if ( $attach_id ) {
				$afile = get_attached_file( $attach_id );

				// flip slashes for WAMP settups to prevent 404ing on the next line.
				/**
				 * Filters the file name for WAMP settings (filter routine provided by WPDR plugin).
				 *
				 * @param string $afile attached file name.
				 */
				$afile = apply_filters( 'document_path', $afile ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

				// Copy to a readable name in a private folder of its own: unpredictable, not readable by
				// other users of the server, and removed after sending (or at the end of the request).
				$file_name = sanitize_file_name( get_post_field( 'post_name', $post_id ) );
				$file_ext  = pathinfo( $afile, PATHINFO_EXTENSION );
				$dir       = trailingslashit( get_temp_dir() ) . 'wpdr-en-' . wp_generate_password( 16, false );
				if ( is_readable( $afile ) && wp_mkdir_p( $dir ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
					chmod( $dir, 0700 );
					$nfile = $dir . '/' . ( '' === $file_name ? 'document' : $file_name ) . ( '' === $file_ext ? '' : '.' . $file_ext );
					// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					if ( @copy( $afile, $nfile ) ) {
						$file[] = $nfile;
						add_action( 'shutdown', array( __CLASS__, 'remove_attachment' ) );
					} else {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
						rmdir( $dir );
					}
				}
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

		self::remove_attachment();
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
		$output['tm_rule']        = __( 'Match Rule', 'email-notice-wp-document-revisions' );
		$output['wpdr_en_attach'] = __( 'Document Attach', 'email-notice-wp-document-revisions' );
		$output['wpdr_en_pause']  = __( 'List Paused', 'email-notice-wp-document-revisions' );

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
			( 0 === $tm_rule ? esc_html_e( 'Any taxonomy element', 'email-notice-wp-document-revisions' ) : esc_html_e( 'All taxonomy elements', 'email-notice-wp-document-revisions' ) );
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
			<label id="tm_label" class="alignleft"><?php esc_html_e( 'Match Rule :', 'email-notice-wp-document-revisions' ); ?></label>
			<label class="alignleft"><input type="radio" id="tm_any" name="tm_rule" value="0"><?php esc_html_e( 'Any taxonomy element', 'email-notice-wp-document-revisions' ); ?>&nbsp;&nbsp;</label>
			<label class="alignleft"><input type="radio" id="tm_all" name="tm_rule" value="1"><?php esc_html_e( 'All taxonomy elements', 'email-notice-wp-document-revisions' ); ?></label>
			</div>
			<p id="tm_descr" class="howto"><?php esc_html_e( 'Choose whether Any or All of these taxonomy elements must match for the list to be used to send emails.', 'email-notice-wp-document-revisions' ); ?></p>
			</fieldset>
			<?php
		}
		if ( 'wpdr_en_attach' === $column_name ) {
			?>
			<fieldset class="inline-edit-col-left">
			<div class="inline-edit-group wp-clearfix column-attach">
			<input type="checkbox" id="wpdr_en_attach" name="wpdr_en_attach" value= "">
			<label class="alignleft" for="wpdr_en_attach"><?php esc_html_e( 'Document Attach :', 'email-notice-wp-document-revisions' ); ?>&nbsp;</label>
			</div>
			</fieldset>
			<?php
		}
		if ( 'wpdr_en_pause' === $column_name ) {
			?>
			<fieldset class="inline-edit-col-left">
			<div class="inline-edit-group wp-clearfix column-pause">
			<input type="checkbox" id="wpdr_en_pause" name="wpdr_en_pause" value= "">
			<label class="alignleft" for="wpdr_en_pause"><?php esc_html_e( 'Pause Mail :', 'email-notice-wp-document-revisions' ); ?>&nbsp;</label>
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
			<span class="title"><?php esc_html_e( 'Match Rule :', 'email-notice-wp-document-revisions' ); ?>&nbsp;</span>
			<select name="tm_ruleb">
			<option value="-1"><?php esc_html_e( '-- No Change --', 'email-notice-wp-document-revisions' ); ?></option>
			<option value="0"><?php esc_html_e( 'Any taxonomy element', 'email-notice-wp-document-revisions' ); ?></option>
			<option value="1"><?php esc_html_e( 'All taxonomy elements-', 'email-notice-wp-document-revisions' ); ?></option>
			</select>
			</label>
			</div>	
			<p id="tm_descr" class="howto"><?php esc_html_e( 'Choose whether Any or All of these taxonomy elements must match for the lists to be used to send emails.', 'email-notice-wp-document-revisions' ); ?></p>
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
			<span class="title" style="width: auto;"><?php esc_html_e( 'Document Attach :', 'email-notice-wp-document-revisions' ); ?>&nbsp;</span>
			<select name="attachb">
			<option value="-1"><?php esc_html_e( '-- No Change --', 'email-notice-wp-document-revisions' ); ?></option>
			<option value="0"><?php esc_html_e( 'Do not Attach Document', 'email-notice-wp-document-revisions' ); ?></option>
			<option value="1"><?php esc_html_e( 'Attach Document', 'email-notice-wp-document-revisions' ); ?></option>
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
			<span class="title" style="width: auto;"><?php esc_html_e( 'Pause Mail :', 'email-notice-wp-document-revisions' ); ?>&nbsp;</span>
			<select name="list_pauseb">
			<option value="-1"><?php esc_html_e( '-- No Change --', 'email-notice-wp-document-revisions' ); ?></option>
			<option value="0"><?php esc_html_e( 'Do not Pause Mail', 'email-notice-wp-document-revisions' ); ?></option>
			<option value="1"><?php esc_html_e( 'Pause Mail', 'email-notice-wp-document-revisions' ); ?></option>
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

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Only the list edit form, Quick Edit and Bulk Edit set these values; a save from code, REST or an
		// import leaves them alone (each form's nonce is checked rather than dying with check_admin_referer()).
		if ( isset( $_GET['bulk_edit'] ) ) {
			// Bulk Edit (the button value is translated, so only its presence is checked).
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'bulk-posts' ) ) {
				return;
			}
			$fields = array(
				'tm_ruleb'    => 'wpdr_en_tm',
				'attachb'     => 'wpdr_en_attach',
				'list_pauseb' => 'wpdr_en_pause',
			);
			foreach ( $fields as $field => $meta_key ) {
				// -1 is "No Change".
				$value = ( isset( $_GET[ $field ] ) ? (int) $_GET[ $field ] : -1 );
				if ( 0 === $value || 1 === $value ) {
					update_post_meta( $post_id, $meta_key, $value );
				}
			}
			return;
		}

		if ( wp_doing_ajax() && isset( $_POST['_inline_edit'] ) ) {
			// Quick Edit.
			if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_inline_edit'] ) ), 'inlineeditnonce' ) ) {
				return;
			}
		} elseif ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'update-post_' . $post_id ) ) {
			// Not the edit form.
			return;
		}

		if ( isset( $_POST['tm_rule'] ) ) {
			update_post_meta( $post_id, 'wpdr_en_tm', ( 1 === (int) $_POST['tm_rule'] ? 1 : 0 ) );
		}

		// A ticked box is sent and an unticked one is not. Its value is not used: on the edit form it holds
		// the value when the page was loaded, so ticking a box did not switch the setting on.
		update_post_meta( $post_id, 'wpdr_en_attach', ( isset( $_POST['wpdr_en_attach'] ) ? 1 : 0 ) );
		update_post_meta( $post_id, 'wpdr_en_pause', ( isset( $_POST['wpdr_en_pause'] ) ? 1 : 0 ) );

		// Log retention (edit form only): deleting log entries needs the right to delete Lists.
		if ( isset( $_POST['wpdr_en_log_days'] ) && current_user_can( 'delete_doc_ext_lists' ) ) {
			update_post_meta( $post_id, 'wpdr_en_log_days', absint( $_POST['wpdr_en_log_days'] ) );
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
		foreach ( (array) $posttax as $tax => $terms ) {
			// Only hierarchical taxonomies send term IDs (others send names), and only those can be reverted.
			$tax_o = is_string( $tax ) ? get_taxonomy( $tax ) : false;
			if ( ! $tax_o || ! $tax_o->hierarchical || ! is_array( $terms ) ) {
				continue;
			}
			// Term IDs come from the form: keep positive integers only.
			$terms = array_filter( array_map( 'intval', $terms ) );
			$tsave = ( isset( $savetax[ $tax ] ) && is_array( $savetax[ $tax ] ) ? array_filter( array_map( 'intval', $savetax[ $tax ] ) ) : array() );
			// Added terms are positive, removed terms negative.
			$test = array_values( array_diff( $terms, $tsave ) );
			foreach ( array_diff( $tsave, $terms ) as $no_term ) {
				$test[] = -$no_term;
			}
			if ( ! empty( $test ) ) {
				$unsaved[ $tax ] = $test;
			}
		}

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

		// Only the edit form (which sends the referer) is checked; saves from code are left alone.
		if ( ! isset( $postarr['_wp_http_referer'] ) ) {
			return $maybe_empty;
		}
		// Sanitize the value of the $_POST collection for the Coding Standards.
		$url = sanitize_text_field( wp_unslash( $postarr['_wp_http_referer'] ) );
		if ( '//' !== $url && isset( $postarr['tax_input'] ) && is_array( $postarr['tax_input'] ) ) {
			// make sure parent-child terms are not entered.
			foreach ( $postarr['tax_input'] as $tax => $terms ) {
				$tax_o = is_string( $tax ) ? get_taxonomy( $tax ) : false;
				if ( $tax_o && $tax_o->hierarchical && is_array( $terms ) && ! empty( $terms ) ) {
					// Term IDs arrive as strings.
					$terms = array_filter( array_map( 'intval', $terms ) );
					foreach ( $terms as $term ) {
						$children = get_term_children( $term, $tax );
						if ( is_array( $children ) && ! empty( $children ) ) {
							foreach ( $children as $child ) {
								if ( in_array( (int) $child, $terms, true ) ) {
									// find unsaved terms from post.
									$tsave = ( isset( $postarr['tax_save'] ) ? $postarr['tax_save'] : array() );
									$trans = array(
										$tax,
										$term,
										(int) $child,
										$this->unsaved_taxonomy_values( $postarr['tax_input'], $tsave ),
									);
									set_transient(
										'del_' . (int) $postarr['ID'],
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
			// check that there is at least one set (hierarchical: term IDs; others: comma-separated names).
			foreach ( $postarr['tax_input'] as $tax => $terms ) {
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						if ( 0 !== (int) $term || ( ! is_numeric( $term ) && '' !== trim( (string) $term ) ) ) {
							return $maybe_empty;
						}
					}
				} elseif ( is_string( $terms ) && '' !== trim( $terms, " ,\t\n\r" ) ) {
					return $maybe_empty;
				}
			}
		}

		// no taxonomies set.
		set_transient(
			'del_' . (int) $postarr['ID'],
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
			1  => __( 'Document External List updated.', 'email-notice-wp-document-revisions' ),
			2  => __( 'Custom field updated.', 'email-notice-wp-document-revisions' ),
			3  => __( 'Custom field deleted.', 'email-notice-wp-document-revisions' ),
			4  => __( 'Document External List updated.', 'email-notice-wp-document-revisions' ),
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			// translators: %s is the revision ID.
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Document External List restored to revision from %s', 'email-notice-wp-document-revisions' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			6  => __( 'Document External List published.', 'email-notice-wp-document-revisions' ),
			7  => __( 'Document External List saved.', 'email-notice-wp-document-revisions' ),
			8  => __( 'Document External List submitted.', 'email-notice-wp-document-revisions' ),
			// translators: %1$s is the date, %2$s is the preview link.
			9  => sprintf( __( 'Document External List scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview document list</a>', 'email-notice-wp-document-revisions' ), date_i18n( sprintf( _x( '%1$s @ %2$s', '%1$s: date; %2$s: time', 'email-notice-wp-document-revisions' ), get_option( 'date_format' ), get_option( 'time_format' ) ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post->ID ) ) ),
			10 => __( 'Document External List draft updated.', 'email-notice-wp-document-revisions' ),
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
				__( 'Usage', 'email-notice-wp-document-revisions' )       =>
				'<p>' . __( 'This screen allows a user to define a list of email users that will receive an email (generally with the document attached) if any taxonomy value of the list matches that on the document requested.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'The List is available for this processing once the List is published.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'When editing is completed, simply click <code>Update</code> or <code>Publish</code> to save your changes.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'There are three main areas associated with the List:', 'email-notice-wp-document-revisions' ) . '</p><ul><li>' .
				__( 'List Attributes', 'email-notice-wp-document-revisions' ) . '</li><li>' .
				__( 'Document External User List', 'email-notice-wp-document-revisions' ) . '</li><li>' .
				__( 'Taxonomies', 'email-notice-wp-document-revisions' ) . '</li></ul>',
				__( 'List Attributes', 'email-notice-wp-document-revisions' ) =>
				'<p><strong>' . __( 'Taxonomy Match Rule', 'email-notice-wp-document-revisions' ) . '</strong></p><p>' .
				__( 'See the section <strong>Document Taxonomies</strong> for the process to determine whether an individual External List term matches that on the Document.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'This flag indicates whether all the terms on the External List or at least one must match those on the Document for the External List to be considered matching the Document.', 'email-notice-wp-document-revisions' ) . '</p><p><strong>' .
				__( 'Document Attach', 'email-notice-wp-document-revisions' ) . '</strong></p><p>' .
				__( 'This is a text area where optional comment or information can be made about the External List.', 'email-notice-wp-document-revisions' ) . '</p><p><strong><p><strong>' .
				__( 'Pause Mail', 'email-notice-wp-document-revisions' ) . '</strong></p><p>' .
				__( 'This allows the External List to be paused from matching Documents.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'It is provided so that an External List can be withdrawn from processing temporarily without simply changing its status.', 'email-notice-wp-document-revisions' ) . '</p><p><strong><p><strong>' .
				__( 'Email Log', 'email-notice-wp-document-revisions' ) . '</strong></p><p>' .
				__( 'The number of days after a Document was last modified that this List\'s log entries for it are kept. They are deleted once a day; empty or 0 keeps them. Only users who can delete Lists can change it.', 'email-notice-wp-document-revisions' ) . '</p><p><strong>' .
				__( 'List Notes', 'email-notice-wp-document-revisions' ) . '</strong></p><p>' .
				'<p>' . __( 'This is a text area where optional comment or information can be held about the External List.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'Use is made of the excerpt field as it intended to be a simple aide-memoire to administrators of the External Lists.', 'email-notice-wp-document-revisions' ) . '</p>',
				__( 'Document External User List', 'email-notice-wp-document-revisions' ) =>
				'<p>' . __( 'This panel consists of two parts. First are the elements to create or edit an individual user record into the table of email addresses, followed by a searchable list of the email addresses associated with the list.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'Note that changes made to this User List are stored immediately. There is no separate process to update the data permanently or to cancel the edits made so far.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'This holds a list of individual users that will be sent an email when the External List is selected.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'Each user record will contain the user name and email address and an optional pause attribute that pauses the sending of emails to the user.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'This pause attribute allows the user details to be retained in the User List but notification emails will not be sent to the specific user.', 'email-notice-wp-document-revisions' ) . '</p>',
				__( 'Document Taxonomies', 'email-notice-wp-document-revisions' ) =>
				'<p>' . __( 'These taxonomies are generally shown on the right part of the screen. They are the taxonomies that can be applied to Documents.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'At least one taxonomy term is required to be entered, although several can be.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'If there are multiple terms on the External List then the matching rule will used to determine whether just one term is needed for the External List to be selected or all terms present must match for notification.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'For hierarchical taxonomies, a term on the External List is considered matched if the term on the Document is either the same as that on the External List or is a child of the External List term.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'Because of this, it is not permitted to publish an External List when the terms entered contains a term and its parent since the term matching process means that the child term would be redundant.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'A Document can have additional terms present, i.e. it is not necessary that all Document terms must match.', 'email-notice-wp-document-revisions' ) . '</p>',
				__( 'Publish', 'email-notice-wp-document-revisions' )     =>
				'<p>' . __( 'By default, Documents are only accessible to logged in users. Documents can be published, thus making them accessible to the world, by toggling their visibility in the "Publish" box in the top right corner. Any document marked as published will be accessible to anyone with the proper URL.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'Similarly these user lists need to be published for the list to be eligible to be selected for sending emails to its users.', 'email-notice-wp-document-revisions' ) . '</p>',
				__( 'Permissions', 'email-notice-wp-document-revisions' ) =>
				'<p>' . __( 'It is expected that the emailing administration process will be centralised with only a handful of External Lists being created. Therefore the full WordPress set of access permissions is not required.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'The permission <strong>edit_doc_ext_lists</strong> is supplied that allows users to create, update, and publish any External List.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'The permission <strong>delete_doc_ext_lists</strong> is supplied that allows users to delete any External List.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'The permission <strong>read_doc_ext_lists</strong> is supplied that allows users to see the External Lists, the email logs and the External List part of the Document screen, and to send External List emails for Documents they can edit.', 'email-notice-wp-document-revisions' ) . '</p>',
			),
			'edit-doc_ext_list' => array(
				__( 'Document External Lists', 'email-notice-wp-document-revisions' ) =>
				'<p>' . __( 'Below is a list of all Document External Lists used to send email notifications to external users on an update to a Document. Click the list title to edit the document list.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'To add a new Document External List, click <strong>Add Document External List</strong> at the top of the screen.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'Note that whilst this functionality is primarily intended for people that are not users of this site, any email address can be used.', 'email-notice-wp-document-revisions' ) . '</p>',
			),
			'document'          => array(
				__( 'Document Email Notifications', 'email-notice-wp-document-revisions' ) =>
				'<p>' . __( 'Notification emails can be sent (or re-sent) for published Documents by clicking on the button "Send notification emails" (to internal users) or "Send external emails" (to  external users).', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'Internal users are those with user-ids on the site. They can decide whether they wish to receive these notifications or not and whether a copy of the document should be attached the mail.', 'email-notice-wp-document-revisions' ) . '&nbsp;' .
				__( 'This data can also be updated by Administrators (also using Bulk Editing functions).', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'External users normally do not have a sign-on. Notifications are based on the concept of External Lists. A list will contain one or more taxonomy terms that will be matched again the document terms.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'Every published External List (except those marked as Paused) will be tested to see whether its terms match those on the Document.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'Those that match are listed below the button (only for users with the read_doc_ext_lists permission). If the user can maintain the External List, then they can choose to not send the mail to a List by unchecking the List before sending the Notification.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'A term on the List matches with one on the Document if they are same or, for hierarchical taxonomies, the List term is a parent of the Document term.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'When there are several terms on the List, a rule can be set so that the List is matched if either any term matches or all terms on the List must match.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'Potentially several Lists may match the Document. Emails will be sent to every user (except those individually paused) on each list that is matched.', 'email-notice-wp-document-revisions' ) . '</p>',
				__( 'Document Email Extra Text', 'email-notice-wp-document-revisions' ) =>
				'<p>' . __( 'You can include some message-specific additional text with the notification emails.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
				__( 'The template used (Internal or External) has to contain the tag "%extra%" in it to use this capability.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'If neither template contains the tag then the various options are not shown.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'A checkbox which is initially disabled with label "Add Extra Text" is placed next to each Send button.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'On entering some text in the text field, the checkboxes will become active if the template allows it.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'The extra text will only be included if the corresponding checkbox is checked when the send button is clicked.', 'email-notice-wp-document-revisions' ) . '</p><p>' .
				__( 'The Extra text can contains html tags to better format the output.', 'email-notice-wp-document-revisions' ) . '</p>',
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
			$messages = '<p>' . esc_html__( 'There are no terms defined for matching with this list. Please add at least one.', 'email-notice-wp-document-revisions' ) . '</p>';
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
			$messages  .= '<p>' . esc_html__( 'There are no addressees defined for matching with this list. Please add at least one.', 'email-notice-wp-document-revisions' ) . '</p>';
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
		if ( is_array( $err ) && ! empty( $err ) ) {
			delete_transient( 'del_' . $post->ID );
			$err_exists = true;
			$oclass     = 'notice-error';
			if ( 0 === $err[0] ) {
				$messages .= '<p>' . esc_html__( 'There are no terms defined for matching with this list. Please add at least one.', 'email-notice-wp-document-revisions' ) . '</p>';
			} else {
				$tax_o  = get_taxonomy( (string) $err[0] );
				$tax_l  = $tax_o ? $tax_o->label : '';
				$parent = get_term( (int) $err[1], (string) $err[0] );
				$child  = get_term( (int) $err[2], (string) $err[0] );
				// translators: %1$s is the taxonomy name, %2$s is the parent term, %3$s is the child term.
				$messages .= '<p>' . esc_html( sprintf( __( 'Taxonomy: %1$s. Cannot have both parent (%2$s) and child terms (%3$s). Remove child term as parent term will match.', 'email-notice-wp-document-revisions' ), $tax_l, ( $parent instanceof WP_Term ? $parent->name : '' ), ( $child instanceof WP_Term ? $child->name : '' ) ) ) . '</p>';
				// make sure js methods have been loaded in case we add something after.
				if ( ! self::$js_loaded ) {
					self::load_js_methods();
				}
				// create update script to act on load.
				$add_script = false;
				$script     = 'document.addEventListener("DOMContentLoaded", function(evt) {' . "\n";
				$unsaved    = ( isset( $err[3] ) && is_array( $err[3] ) ? $err[3] : array() );
				foreach ( $unsaved as $tax => $terms ) {
					$tax_o = get_taxonomy( (string) $tax );
					if ( ! $tax_o || ! is_array( $terms ) ) {
						continue;
					}
					if ( $tax_o->hierarchical ) {
						$add_script = true;
						foreach ( $terms as $term ) {
							// positive: term added, must be checked; negative: term removed, must be unchecked.
							$term    = (int) $term;
							$script .= sprintf( ' revert_unsaved_hier( %1$s, %2$d, %3$s );', wp_json_encode( $tax_o->name ), abs( $term ), ( $term > 0 ? 'true' : 'false' ) ) . "\n";
						}
					} else {
						// translators: %s is the taxonomy name.
						$messages .= '<p>' . esc_html( sprintf( __( 'Taxonomy: %s. You must redo your non-hierarchical changes manually.', 'email-notice-wp-document-revisions' ), $tax_o->label ) );
						foreach ( $terms as $term ) {
							$term     = (int) $term;
							$term_obj = get_term( abs( $term ), $tax_o->name );
							$name     = ( $term_obj instanceof WP_Term ? $term_obj->name : '' );
							if ( $term > 0 ) {
								// term added, must be checked.
								// translators: %1$s is the taxonomy term name.
								$messages .= '<br />&nbsp;' . esc_html( sprintf( __( 'Add Term: %1$s ', 'email-notice-wp-document-revisions' ), $name ) );
							} else {
								// term removed, must be unchecked.
								// translators: %1$s is the taxonomy term name.
								$messages .= '<br />&nbsp;' . esc_html( sprintf( __( 'Remove Term: %1$s ', 'email-notice-wp-document-revisions' ), $name ) );
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
		<?php echo $messages; // phpcs:ignore WordPress.Security.EscapeOutput -- built above from escaped parts. ?>
		</div>
		<?php
	}
}

