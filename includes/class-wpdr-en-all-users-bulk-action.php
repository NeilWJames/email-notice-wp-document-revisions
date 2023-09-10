<?php
/**
 * Email Notice WP Document Revisions Bulk Action Functionality
 *
 * @author  Neil W. James <neil@familyjames.com>
 * @package Email Notice WP Document Revisions
 */

// No direct access allowed to plugin php file.
if ( ! defined( 'ABSPATH' ) ) {
	die( esc_html__( 'You are not allowed to call this file directly.', 'wpdr-email-notice' ) );
}

/**
 * Main WP_Document_Revisions_Email_Notice_Bulk_Action class.
 */
class WPDR_EN_All_Users_Bulk_Action {

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		if ( is_admin() ) {
			// admin actions.
			add_filter( 'bulk_actions-users', array( &$this, 'custom_bulk_action_option' ) );
			add_filter( 'handle_bulk_actions-users', array( &$this, 'custom_bulk_action_handler' ), 10, 3 );
		}
	}

	/**
	 * Add subscribe/unsubscribe actions to the select menus.
	 *
	 * @since 1.0
	 * @param array $bulk_actions List of Bulk Actions.
	 * @return string[]
	 */
	public function custom_bulk_action_option( $bulk_actions ) {
		$bulk_actions['wpdr_subscribe']   = __( 'Subscribe to Document Email notifications', 'wpdr-email-notice' );
		$bulk_actions['wpdr_unsubscribe'] = __( 'Unsubscribe from Document Email notifications', 'wpdr-email-notice' );
		return $bulk_actions;
	}

	/**
	 * Handle the bulk actions, based on https://www.skyverge.com/blog/add-custom-bulk-action/.
	 *
	 * @since 1.0
	 * @param string $redirect_to Where next.
	 * @param string $doaction    Action requested.
	 * @param int[]  $user_ids    List of user ids.
	 * @return string
	 */
	public function custom_bulk_action_handler( $redirect_to, $doaction, $user_ids ) {
		if ( 'wpdr_subscribe' !== $doaction && 'wpdr_unsubscribe' !== $doaction ) {
			return $redirect_to;
		}

		if ( empty( $user_ids ) ) {
			return $redirect_to;
		}

		switch ( $doaction ) {
			case 'wpdr_subscribe':
				$subscribed = 0;
				foreach ( $user_ids as $user_id ) {
					if ( ! $this->perform_subscribe( $user_id ) ) {
						wp_die( esc_html__( 'Error subscribing users.', 'wpdr-email-notice' ) );
					}
					++$subscribed;
				}
				break;
			case 'wpdr_unsubscribe':
				$subscribed = 0;
				foreach ( $user_ids as $user_id ) {
					if ( ! $this->perform_unsubscribe( $user_id ) ) {
						wp_die( esc_html__( 'Error unsubscribing users.', 'wpdr-email-notice' ) );
					}
					++$subscribed;
				}
				break;
		}
		// make sure we redirect user to the same page. May have to get from POST data.
		return $redirect_to;
	}

	/**
	 * Subscribe the given user from mailing.
	 *
	 * @since 1.0
	 * @global $wpdr_en;
	 * @param int $user_id User id.
	 * @return boolean
	 */
	private function perform_subscribe( $user_id ) {
		global $wpdr_en;
		// Does the user have the choice.
		$user = get_user_by( 'ID', $user_id );
		if ( ! (bool) array_intersect( $user->roles, $wpdr_en::$internal_roles ) ) {
			// remove if was present due to change of roles.
			delete_user_meta( $user_id, 'wpdr_en_user_notification' );
			delete_user_meta( $user_id, 'wpdr_en_user_attachment' );
			return false;
		}

		update_user_meta( $user_id, 'wpdr_en_user_notification', true );
		if ( (bool) get_user_meta( $user_id, 'wpdr_en_user_notification', true ) !== true ) {
			return false;
		}
		return true;
	}

	/**
	 * Unsubscribe the given user from mailing.
	 *
	 * @since 1.0
	 * @global $wpdr_en;
	 * @param int $user_id User id.
	 * @return boolean
	 */
	private function perform_unsubscribe( $user_id ) {
		global $wpdr_en;
		// Does the user have the choice.
		$user = get_user_by( 'ID', $user_id );
		if ( ! (bool) array_intersect( $user->roles, $wpdr_en::$internal_roles ) ) {
			// remove if was present due to change of roles.
			delete_user_meta( $user_id, 'wpdr_en_user_notification' );
			delete_user_meta( $user_id, 'wpdr_en_user_attachment' );
			return false;
		}

		update_user_meta( $user_id, 'wpdr_en_user_notification', false );
		// switch off attachment.
		update_user_meta( $user_id, 'wpdr_en_user_attachment', false );
		if ( (bool) get_user_meta( $user_id, 'wpdr_en_user_notification', true ) !== false ) {
			return false;
		}
		return true;
	}
}
