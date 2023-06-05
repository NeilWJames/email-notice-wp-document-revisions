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
	 * @var string $attach_file
	 */
	public static $version = '1.0.0';

	/**
	 * Temporary file name
	 *
	 * @since 1.0.0
	 *
	 * @var string $attach_file
	 */
	public static $attach_file;

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
		// Install table when plugin activated.
		register_activation_hook( __FILE__, array( $this, 'install_notification_log' ) );

		// Initialize settings.
		add_action( 'admin_init', array( $this, 'admin_init' ), 20 );
		// Add the notification log option.
		add_action( 'admin_menu', array( $this, 'notification_log_menu' ), 30 );
	}


	/**
	 * Install log table for storing notification log.
	 *
	 * @since 1.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @global WP_Role[] $wp_roles WP_Roles array object.
	 *
	 * @return void
	 */
	public function install_notification_log() {
		global $wpdb;
		$sql = array();

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'wpdr_notification_log';

		// Related post, user, e-mail sent (Time stamp), e-mail address, Status (successful/failed).
		$sql[] = "CREATE TABLE IF NOT EXISTS $table_name (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,	  
		  user_id bigint(20) NOT NULL,
		  post_id bigint(20) NOT NULL,
		  time_mail_sent datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		  user_email varchar(100) NOT NULL,
		  status varchar(100) NOT NULL,
		  PRIMARY KEY  (id),
		  UNIQUE KEY id (id),
		  INDEX (user_id),
		  INDEX (post_id)  
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wpdr_en_db_version', '1.0' );
	}

	/* Settings */

	/**
	 * Make sure we update database in case of a manual plugin download as well.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function db_version_check() {
		$current_db_ver = get_option( 'wpdr_en_db_version' );
		if ( '1.0' !== $current_db_ver || is_multisite() ) {
			$this->install_notification_log();
		}
	}

	/**
	 * Set up Settings on admin_init.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function admin_init() {
		// support languages.
		load_plugin_textdomain( 'wpdr-email-notice', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Adding settings to Settings->General.
		add_settings_section( 'wpdr_en_general_settings', 'Document Email Settings', array( $this, 'general_settings' ), 'general' );
		add_settings_field( 'wpdr_en_set_email_from', __( 'Email From', 'wpdr-email-notice' ), array( $this, 'set_email_from' ), 'general', 'wpdr_en_general_settings' );
		add_settings_field( 'wpdr_en_set_email_from_address', __( 'Email Address', 'wpdr-email-notice' ), array( $this, 'set_email_from_address' ), 'general', 'wpdr_en_general_settings' );
		register_setting( 'general', 'wpdr_en_set_email_from' );
		register_setting( 'general', 'wpdr_en_set_email_from_address' );
		// Adding settings to Settings->Writing.
		add_settings_section( 'wpdr_en_writing_settings', 'Document Email Settings - ' . __( 'Notifications', 'wpdr-email-notice' ), array( $this, 'writing_settings' ), 'writing' );
		add_settings_field( 'wpdr_en_set_notification_mode', __( 'Notification mode', 'wpdr-email-notice' ), array( $this, 'set_notification_mode' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_notification_about', __( 'Notify users about', 'wpdr-email-notice' ), array( $this, 'set_notification_about' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_subject', __( 'Notification e-mail subject', 'wpdr-email-notice' ), array( $this, 'set_subject' ), 'writing', 'wpdr_en_writing_settings' );
		add_settings_field( 'wpdr_en_set_content', __( 'Notification e-mail content', 'wpdr-email-notice' ), array( $this, 'set_content' ), 'writing', 'wpdr_en_writing_settings' );
		// phpcs:disable
		// #TODO: purge.
		// add_settings_field( 'wpdr_en_set_notification_log', 'Logging', array( $this, 'set_notification_log' ), 'writing', 'wpdr_en_writing_settings' );
		// phpcs:enable
		register_setting( 'writing', 'wpdr_en_set_notification_mode' );
		register_setting( 'writing', 'wpdr_en_set_notification_about' );
		register_setting( 'writing', 'wpdr_en_set_subject' );
		register_setting( 'writing', 'wpdr_en_set_content' );
		// phpcs:disable
		// #TODO: purge.
		// register_setting( 'writing', 'wpdr_en_set_notification_log' );.
		// phpcs:enable

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

		add_action( 'add_meta_boxes', array( $this, 'add_metabox_head' ) );

		// Overwrite default e-mail address only if user set new value.
		if ( get_option( 'wpdr_en_set_email_from_address' ) ) {
			add_filter( 'wp_mail_from', array( $this, 'wp_mail_from' ) );
		}
		// Overwrite default e-mail from text only if user set new value.
		if ( get_option( 'wpdr_en_set_email_from' ) ) {
			add_filter( 'wp_mail_from_name', array( $this, 'wp_mail_from_name' ) );
		}

		// Send options.
		add_action( 'wp_ajax_wpdr_en_send_notification_manual', array( $this, 'send_notification_manual' ) );
		add_action( 'save_post_document', array( $this, 'send_notification_auto' ), 20, 3 );
		add_action( 'admin_notices', array( $this, 'admin_notice_auto_notification' ) );

		// help.
		add_action( 'admin_head', array( $this, 'add_help_tab' ) );

		// Delete related log entries when a post is being deleted.
		add_action( 'delete_post', array( $this, 'delete_log_entry_on_post_delete' ), 10 );

		// Delete related log entries when a user is being deleted.
		add_action( 'deleted_user', array( $this, 'delete_log_entry_on_user_delete' ), 10, 3 );

		// enqueue script on document page.
		add_action( 'admin_enqueue_scripts', array( $this, 'load_js_methods' ) );

	}

	/**
	 * Initialize js methods.
	 *
	 * @since 1.0
	 * @global WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function load_js_methods() {
		global $post;
		if ( is_null( $post ) || 'document' !== $post->post_type ) {
			return;
		}
		$suffix = ( WP_DEBUG ) ? '' : '.min';
		wp_register_script(
			'wpdr_en_script',
			plugin_dir_url( __DIR__ ) . 'js/wpdr-email-notice' . $suffix . '.js',
			array( 'jquery' ),
			'1.0',
			true
		);
		// Improve security.
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
		wp_localize_script( 'wpdr_en_script', 'wpdr_en_obj', $nonce_array );
		wp_enqueue_script( 'wpdr_en_script' );
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
		echo '<br/><br/>';
	}

	/**
	 * Settings field to set Notification mode: Auto/Manual.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_notification_mode() {
		echo '<input type="radio" name="wpdr_en_set_notification_mode" value="Auto" ' . checked( 'Auto', esc_html( get_option( 'wpdr_en_set_notification_mode' ) ), false ) . '>' . esc_html__( 'Auto', 'wpdr-email-notice' ) . '</input> ' . esc_html__( '(send e-mails automatically when you publish a post)', 'wpdr-email-notice' ) . '<br/>';
		echo '<input type="radio" name="wpdr_en_set_notification_mode" value="Manual" ' . checked( 'Manual', esc_html( get_option( 'wpdr_en_set_notification_mode' ) ), false ) . '>' . esc_html__( 'Manual', 'wpdr-email-notice' ) . '</input> ' . esc_html__( '(you need to press a button to send notification)', 'wpdr-email-notice' );
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
		echo '<br />' . esc_html__( 'Notifications are sent only to those who can read the document', 'wpdr-email-notice' );
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
		echo '<textarea id="wpdr_en_set_content" name="wpdr_en_set_content" cols="80" rows="10" 
		placeholder = "Dear %recipient_name%, A new document is published. Check it out! %title_with_permalink% %words_50% In case you do not want to receive this kind of notification you can turn it off in your profile.">' . esc_html( get_option( 'wpdr_en_set_content' ) ) . '</textarea>';
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
	 * Add subscription to notifications to User's Profile screen.
	 *
	 * @since 1.0
	 * @param WP_User $user User record.
	 * @return void
	 */
	public function user_profile( $user ) {
		// Wrapper.
		echo '<div class="wpdr-en-wrapper">';

		// Header.
		echo '<div class="wpdr_en-header">';
		echo '<h3>Document Email Settings</h3>';
		echo '</div>'; // wpdr_en-header end.

		$wpdr_en_user_notification = null;
		$wpdr_en_user_attachment   = null;
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
	 * @param string $empty       Initial content of cell.
	 * @param string $column_name Cell column name.
	 * @param string $user_id     Cell row name.
	 * @return string
	 */
	public function all_users_column_rows( $empty, $column_name, $user_id ) {
		if ( 'wpdr_en_notification' !== $column_name ) {
			return $empty;
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

	/* Adds a Document Email Settings box to Edit Post screen */

	/**
	 * Adds a Document Email Settings metabox to Edit Document screen.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function add_metabox_head() {
		add_meta_box( 'wpdr_en_sectionid', __( 'Document Email Settings', 'wpdr-email-notice' ), array( $this, 'add_metabox' ), 'document', 'side', 'high' );
	}

	/**
	 * Builds the Document Email Settings metabox.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function add_metabox() {
		// Add an nonce field so we can check for it later.
		$document_id = get_the_ID();
		wp_nonce_field( 'wpdr_en_meta_box', 'wpdr_en_meta_box_nonce' );
		$notification_sent = (string) get_post_meta( $document_id, 'wpdr_en_notification_sent', true );
		$pstatus           = $this->post_status( $document_id );
		$recipients        = $this->prepare_mail_recipients( $document_id );
		$hasrecipient      = ! empty( $recipients );
		if ( ( ! empty( $notification_sent ) || '1' === $notification_sent ) && in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) && $hasrecipient ) {
			echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_html__( 'Re-send notification email(s)', 'wpdr-email-notice' ) . '" />';
		} else {
			if ( in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) && ( empty( $notification_sent ) || '0' === $notification_sent ) && $hasrecipient ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_html__( 'Send notification email(s)', 'wpdr-email-notice' ) . '"/>';
			} elseif ( empty( $notification_sent ) || '0' === $notification_sent ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_html__( 'Send notification email(s)', 'wpdr-email-notice' ) . '" disabled/>';
			} elseif ( ! empty( $notification_sent ) || '1' === $notification_sent ) {
				echo '<input type="button" id="wpdr-en-notify" class="button-secondary" value="' . esc_html__( 'Re-send notification email(s)', 'wpdr-email-notice' ) . '" disabled/>';
			}
		}
		// Pass document id to jQuery.
		echo '<div id="wraphidden">';
		echo '<input id="wpdr-en-notification-postid" type="hidden" value="' . esc_attr( $document_id ) . '">';
		echo '<span id="dProgress" style="display:none; margin-top: 5px;" >';
		echo '<img id="spnSendNotifications" src="' . esc_url( admin_url() ) . '/images/wpspin_light.gif">';
		echo '</span>';
		echo '<span id="wpdr-en-message" style="display: none; margin-left: 5px;">' . esc_html__( 'Sending email notifications...', 'wpdr-email-notice' ) . '</span>';
		echo '</div>';

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
		$plugin_page = add_submenu_page( 'edit.php?post_type=document', __( 'Document Notification Email Log', 'wpdr-email-notice' ), __( 'Document Email Log', 'wpdr-email-notice' ), 'edit_documents', 'wpdr_en_notification_log', array( $this, 'notification_log_list' ) );
		add_action( 'admin_head-' . $plugin_page, array( $this, 'admin_head' ) );
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
		if ( 'wpdr_en_notification_log' !== $page ) {
			return;
		}
		echo '<style>';
		// phpcs:ignore
		// echo 'table.wp-list-table { table-layout: auto; }';.
		echo '.wp-list-table .column-id { width: 5%; }';
		echo '</style>';
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

		require_once __DIR__ . '/class-wpdr-en-user-log-table.php';

		// #TODO: add filter/search.
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Document Email Notification Log', 'wpdr-email-notice' ) . '</h1>';
		$wpdr_en_notification_log_table         = new WPDR_EN_User_Log_Table();
		$wpdr_en_notification_log_table->screen = convert_to_screen( null );
		$wpdr_en_notification_log_table->prepare_items();
		echo '<form method="get">';
		echo '<input type="hidden" name="post_type" value="document" />';
		echo '<input type="hidden" name="page" value="wpdr_en_notification_log" />';
		$wpdr_en_notification_log_table->search_box( esc_html__( 'Search', 'wpdr-email-notice' ), 'search_id' );
		echo '</form>';
		$wpdr_en_notification_log_table->display();
		echo '</div>';
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
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$sitename = strtolower( wp_strip_all_tags( stripslashes( filter_var( $_SERVER['SERVER_NAME'], FILTER_VALIDATE_URL ) ) ) );
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
	public function post_status( $post_id ) {
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
	public function substring_index( $subject, $delim, $count ) {
		if ( $count < 0 ) {
			return implode( $delim, array_slice( explode( $delim, $subject ), $count ) );
		} else {
			return implode( $delim, array_slice( explode( $delim, $subject ), 0, $count ) );
		}
	}

	/**
	 * It gets the necessary number of words from the post content.
	 *
	 * @since 1.0
	 * @param int $document_id     Post ID.
	 * @param int $number_of_words Word count.
	 * @return string
	 */
	public function get_words_from_post( $document_id, $number_of_words ) {
		$content = get_post_field( 'post_content', $document_id );
		if ( is_numeric( $content ) ) {
			return '';
		}
		// remove document attachment comment.
		$content = preg_replace( '/<!-- WPDR ([0-9]+) -->/', '', $content );
		$content = preg_replace( '/\[(.*)/', '', preg_replace( '/\[(.*)\]/', '', preg_replace( '/\](.*)\[/', '][', wp_strip_all_tags( $content ) ) ) );
		$content = $this->substring_index( $content, ' ', $number_of_words );
		return $content;
	}

	/**
	 * Number of words in a post.
	 *
	 * @since 1.0
	 * @param int $document_id Document ID.
	 * @return int
	 */
	public function get_post_word_count( $document_id ) {
		$content = get_post_field( 'post_content', $document_id );
		if ( is_numeric( $content ) ) {
			return 0;
		}
		// remove document attachment comment.
		$content = preg_replace( '/<!-- WPDR ([0-9]+) -->/', '', $content );
		$content = preg_replace( '/\[(.*)/', '', preg_replace( '/\[(.*)\]/', '', preg_replace( '/\](.*)\[/', '][', wp_strip_all_tags( $content ) ) ) );
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
	*/

	/**
	 * Resolve tags.
	 *
	 * @since 1.0
	 * @param string $text    Template Text.
	 * @param int    $post_id Post ID.
	 * @param int    $user_id User ID.
	 * @return string
	 */
	public function resolve_tags( $text, $post_id, $user_id ) {
		$text = nl2br( $text );     // to keep line breaks.
		$text = str_replace( '%title%', get_the_title( $post_id ), $text );
		$text = str_replace( '%permalink%', '<a href="' . get_permalink( $post_id ) . '">' . get_permalink( $post_id ) . '</a>', $text );
		$text = str_replace( '%title_with_permalink%', '<a href="' . get_permalink( $post_id ) . '">' . get_the_title( $post_id ) . '</a>', $text );
		$text = str_replace( '%author_name%', ucwords( get_userdata( get_post_field( 'post_author', $post_id ) )->display_name ), $text );
		$text = str_replace( '%excerpt%', ( current_user_can( 'edit_document', $post_id ) ? get_post_field( 'post_excerpt', $post_id ) : '' ), $text );
		$text = str_replace( '%recipient_name%', ucwords( get_userdata( $user_id )->display_name ), $text );
		// %words_n%
		$nom = preg_match_all( '/\%words_\d+\%/', $text, $matches, PREG_OFFSET_CAPTURE );
		if ( $nom && count( (array) $nom ) > 0 ) {
			foreach ( $matches[0] as $key => $value ) {
				$tag = $value[0];
				$now = intval( substr( $tag, 7, strlen( $tag ) - 8 ) ); // number of words needed.
				if ( $now > 0 ) {
					// Check if content is longer than number of words needed and if so, add three dots.
					if ( $this->get_post_word_count( $post_id ) <= $now ) {
						$text = str_replace( $tag, '<blockquote><em>' . $this->get_words_from_post( $post_id, $now ) . '</em></blockquote>', $text );
					} else {
						$text = str_replace( $tag, '<blockquote><em>' . $this->get_words_from_post( $post_id, $now ) . '...</em></blockquote>', $text );
					}
				}
			}
		}
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
	public function prepare_mail_recipients( $post_id ) {
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
	 * It puts together the mail subject
	 *
	 * @since 1.0
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function prepare_mail_subject( $post_id, $user_id ) {
		$template = get_option( 'wpdr_en_set_subject' );
		// Avoid characters like dashes replaced with html numeric character references.
		remove_filter( 'the_title', 'wptexturize' );
		if ( empty( $template ) ) {
			$template = __( 'New document: ', 'wpdr-email-notice' ) . get_the_title( $post_id );
		}
		$subject = wp_strip_all_tags( $this->resolve_tags( $template, $post_id, $user_id ) );
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
		};

		// Revert to default mode.
		add_filter( 'the_title', 'wptexturize' );
		return $subject;
	}

	/**
	 * It puts together the mail content
	 *
	 * @since 1.0
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function prepare_mail_content( $post_id, $user_id ) {
		$template = get_option( 'wpdr_en_set_content' );
		// Apply default template if no content setting present.
		if ( empty( $template ) ) {
			$template  = 'Dear %recipient_name%,<br/><br/> A new document is published. Check it out!<br/><br/><strong>%title_with_permalink%</strong><br/>%words_50%<br><br><small>In case you do not want to receive this kind of notification you can turn it off in your <a href="' . admin_url( 'profile.php' ) . '">profile</a>.</small>';
			$template .= '<small><br/>Also go there if you wish to change whether you will receive the document as an attachment.</small>';
		}
		$content = '<html><body>' . $this->resolve_tags( $template, $post_id, $user_id ) . '</body></html>';
		return $content;
	}

	/**
	 * Send out emails.
	 *
	 * @since 1.0
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public function send_mail( $post_id ) {
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
			return $result;}

		// post considered no protected if field is empty.
		$no_password = empty( get_post_field( 'post_password', $post_id ) );
		foreach ( $recipients as $value ) {
			// Make sure accented characters sent out correctly as well.
			$mail_subject = mb_encode_mimeheader( $this->prepare_mail_subject( $post_id, $value->user_id ), 'UTF-8' );
			$mail_content = $this->prepare_mail_content( $post_id, $value->user_id );
			// if there is no subject or content then do not send mail.
			if ( empty( $mail_subject ) || empty( $mail_content ) ) {
				if ( $this->log_mail_sent( $value->user_id, $post_id, $value->user_email, __( 'Empty mail subject and/or content.', 'wpdr-email-notice' ) ) ) {
					$sending_error_count++;
					$logged_count++;
				} else {
					$sending_error_count++;
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
				// Set mail type.
				$headers = array( 'Content-type: text/html' );

				// Send mail.
				$mail_status = wp_mail( $value->display_name . '<' . $value->user_email . '>', $mail_subject, $mail_content, $headers, $attachments );

				// Add log entry.
				if ( $mail_status ) {
					$status_text = ( empty( $attachments ) ? __( 'Successful', 'wpdr-email-notice' ) : __( 'Success Attachment', 'wpdr-email-notice' ) );
					$sent_count++;
				} else {
					$status_text = __( 'Failed', 'wpdr-email-notice' );
					$sending_error_count++;
				}
				// Add a small delay to stop flooding mail system.
				/**
				 * Filters the delay time introduced to avoid flooding the mail system.
				 *
				 * @param int 50000  default delay time (0.05 sec).
				 */
				usleep( apply_filters( 'wpdr_en_mail_delay', 50000 ) );
				if ( $this->log_mail_sent( $value->user_id, $post_id, $value->user_email, $status_text ) ) {
					$logged_count++;
				}
			}
		}
		// did we create a temporary attachment.
		if ( ! empty( self::$attach_file ) ) {
			unlink( self::$attach_file[0] );
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
	 * Add log entry.
	 *
	 * @since 1.0
	 * @param int    $user_id    User ID.
	 * @param int    $post_id    Post ID.
	 * @param string $user_email User email address.
	 * @param string $status     Status text.
	 * @return string
	 */
	public function log_mail_sent( $user_id, $post_id, $user_email, $status ) {
		$result = false;
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'wpdr_notification_log',
			array(
				'user_id'        => $user_id,
				'post_id'        => $post_id,
				'time_mail_sent' => current_time( 'mysql' ),
				'user_email'     => $user_email,
				'status'         => $status,
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $result;  // if false then insert failed.
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
	public function delete_log_entry_on_user_delete( $user_id, $reassign, $user ) {
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
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public function notify( $post_id ) {
		$result              = array();
		$mails               = $this->send_mail( $post_id );
		$logged_count        = intval( $mails['logged_count'] );
		$sent_count          = intval( $mails['sent_count'] );
		$sending_error_count = intval( $mails['sending_error_count'] );
		$log_page_url        = admin_url( 'edit.php?post_type=document&page=wpdr_en_notification_log' );
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
			$result  = $this->notify( $post_id );
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
			unlink( self::$attach_file[0] );
			self::$attach_file = '';
		}

		if ( empty( $post_id ) ) {
			return;
		}

		if ( get_option( 'wpdr_en_set_notification_mode' ) !== 'Auto' ) {
			return;
		} else {
			$notification_sent = get_post_meta( $post_id, 'wpdr_en_notification_sent', true );
			$pstatus           = $this->post_status( $post_id );
			$recipients        = $this->prepare_mail_recipients( $post_id );
			$hasrecipient      = ! empty( $recipients );
			if ( in_array( $pstatus, array( 'Public', 'Password protected', 'Private' ), true ) && ( empty( $notification_sent ) || 0 === $notification_sent ) && $hasrecipient ) {
				$message = null;
				$result  = array();
				add_post_meta( $post_id, 'wpdr_en_notification_sent', '0', true );
				$result = $this->notify( $post_id );

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
			} else {
				delete_transient( 'auto_notification_message' );
				delete_transient( 'auto_notification_result' );
			}
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
			$attach_id = $wpdr->extract_document_id( $content );
			if ( ! is_null( $attach_id ) ) {
				$afile = get_attached_file( $attach_id );

				// flip slashes for WAMP settups to prevent 404ing on the next line.
				/**
				 * Filters the file name for WAMP settings (filter routine provided by plugin).
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
					@chmod( $nfile, 0664 );
					$file[] = $nfile;
				}
				// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
			}
			self::$attach_file = $file;
		}

		return self::$attach_file;
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
		if ( 'document' !== $screen->post_type ) {
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
			'document' => array(
				__( 'Document Email Settings', 'wpdr-email-notice' ) =>
				'<p>' . __( 'Notification emails can be sent (or re-sent) for published documents to internal users  by clicking on "Send notification emails".', 'wpdr-email-notice' ) . '</p><p>' .
				__( 'Internal users are those with user-ids for the site. They can decide whether they wish to receive these notifications or not and whether the mail should include a copy of the document.', 'wpdr-email-notice' ) . '</p>',
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

}

// #TODO: add export log functionality
// #TODO: add purge functionality: uncomment related code and configuration options; implement scheduler to truncate logs regularly + manually on view log page
