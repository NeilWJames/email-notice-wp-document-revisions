<?php
/**
 * Email Notice WP Document Revisions List Table Functionality
 *
 * @author  Neil W. James <neil@familyjames.com>
 * @package Email Notice WP Document Revisions
 */

// No direct access allowed to plugin php file.
if ( ! defined( 'ABSPATH' ) ) {
	die( esc_html__( 'You are not allowed to call this file directly.', 'wpdr-email-notice' ) );
}

// Load WP_List_Table if not loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
	require_once ABSPATH . 'wp-admin/includes/screen.php';
}

/**
 * Main WP_Document_Revisions_Email_Notice_List_Table class.
 */
class WPDR_EN_User_Log_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 1.0
	 * @param mixed[] $args  Arguments to List_Table.
	 * @return void
	 */
	public function __construct( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'singular' => __( 'Notification email sent', 'wpdr-email-notice' ),    // singular name of the listed records.
				'plural'   => __( 'Notification emails sent', 'wpdr-email-notice' ),   // plural name of the listed records.
				'ajax'     => false,
				'screen'   => null,
			)
		);
		parent::__construct( $args );
	}

	/**
	 * Define columns for table.
	 *
	 * @since 1.0
	 * @return string[]
	 */
	public function get_columns() {
		$columns = array(
			'id'                => '#',
			'post_title'        => __( 'Post Title', 'wpdr-email-notice' ),
			'time_mail_sent'    => __( 'Email sent', 'wpdr-email-notice' ),
			'user_display_name' => __( 'User Name', 'wpdr-email-notice' ),
			'user_email'        => __( 'User Email', 'wpdr-email-notice' ),
			'status'            => __( 'Status', 'wpdr-email-notice' ),
		);
		return $columns;
	}

	/**
	 * Define sortable columns for table.
	 *
	 * @since 1.0
	 * @return string[]
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'post_title'        => array( 'post_title', false ),
			'time_mail_sent'    => array( 'time_mail_sent', false ),
			'user_display_name' => array( 'user_display_name', false ),
			'user_email'        => array( 'user_email', false ),
			'status'            => array( 'status', false ),
		);
		return $sortable_columns;
	}

	/**
	 * Define defaults columns for table.
	 *
	 * @since 1.0
	 * @param mixed[] $item        row in List Table.
	 * @param string  $column_name column name.
	 * @return string[]
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'post_title':
				if ( current_user_can( 'edit_document', $item['post_id'] ) ) {
					return '<a href="' . get_edit_post_link( $item['post_id'] ) . '">' . $item[ $column_name ] . '</a>';
				} else {
					return '<a href="' . get_permalink( $item['post_id'] ) . '">' . $item[ $column_name ] . '</a>';
				}
			case 'user_display_name':
				if ( current_user_can( 'list_users' ) ) {
					return '<a href="' . get_edit_user_link( $item['user_id'] ) . '">' . ucwords( $item[ $column_name ] ) . '</a>';
				} else {
					return ucwords( $item[ $column_name ] );
				}
			case 'time_mail_sent':
			case 'user_email':
			case 'status':
				return $item[ $column_name ];
		}
	}

	/**
	 * Define actions for table to display extra text..
	 *
	 * @since 3.1
	 * @param mixed[] $item row in List Table.
	 * @return string[]
	 */
	public function column_id( $item ) {
		if ( empty( $item['extra_text'] ) ) {
			return $item['id'];
		}
		$actions = array(
			'view' => '<button onclick="wpdr_en_extra( ' . $item['id'] . ' )">' . __( 'Extra Text', 'wpdr-email-notice' ) . '</button>&nbsp;&nbsp;' .
					'<textarea id="extra_' . $item['id'] . '" onclick="wpdr_en_extra( ' . $item['id'] . ' )" rows="3" cols="40" style="display: none">' .
					$item['extra_text'] . '</textarea>',
		);
		return sprintf( '%1$s %2$s', $item['id'], $this->row_actions( $actions, true ) );
	}

	/**
	 * Define data to populate table.
	 *
	 * @since 1.0
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	public function prepare_items() {
		$per_page              = 20;
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		global $wpdb;
		$log_items = null;
		// sorting.
		$orderby = 'time_mail_sent';
		$order   = 'desc';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['s'] ) ) {
			$parm = '%' . sanitize_text_field( wp_unslash( $_GET['s'] ) ) . '%';
		} else {
			$parm = '%%';
		}

		if ( ! empty( $_GET['orderby'] ) ) {
			$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
			switch ( $orderby ) {
				case 'post_title':
				case 'user_display_name':
				case 'user_email':
				case 'status':
					break;
				default:
					$orderby = 'time_mail_sent';
					break;
			}
		}
		if ( ! empty( $_GET['order'] ) ) {
			$order = sanitize_text_field( wp_unslash( $_GET['order'] ) );
			switch ( $order ) {
				case 'asc':
				case 'desc':
					break;
				default:
					$order = 'desc';
					break;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		// SQL prepared for user-entered data. Ordering data constrained to specific values and camnot be hijacked.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$log_items    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 	l.id as id,
				 l.post_id as post_id,
				 p.post_title as post_title,
				 l.user_id as user_id,
				 u.display_name as user_display_name,						
				 l.time_mail_sent as time_mail_sent,
				 l.user_email as user_email,
				 l.status,
				 (SELECT e.extra_text FROM {$wpdb->prefix}wpdr_en_extra_text e
				  WHERE e.id = l.extra_text_id) as extra_text
				 FROM {$wpdb->prefix}wpdr_notification_log l
				 INNER JOIN {$wpdb->prefix}posts p
				 ON l.post_id = p.ID
				 INNER JOIN {$wpdb->base_prefix}users u
				 ON l.user_id = u.id
				 WHERE ( p.post_title LIKE %s OR u.display_name LIKE %s )",
				$parm,
				$parm
			) . " ORDER BY {$orderby} {$order}",
			ARRAY_A // ARRAY_A will ensure that we get associated array instead of stdClass.
		);
		$current_page = $this->get_pagenum();
		$total_items  = count( $log_items );
		$log_items    = array_slice( $log_items, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->items = $log_items;
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,                      // WE have to calculate the total number of items.
				'per_page'    => $per_page,                         // WE have to determine how many items to show on a page.
				'total_pages' => ceil( $total_items / $per_page ),  // WE have to calculate the total number of pages.
			)
		);
	}

	/**
	 * Define message if no items for table.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No emails sent out, yet.', 'wpdr-email-notice' );
	}

	/**
	 * Define bulk actions for table.
	 *
	 * Should be get_block_actions.
	 *
	 * @since 1.0
	 * @param string $which not yet defined.
	 * @return void
	 */
	public function bulk_actions( $which = '' ) {
		// Nothing here yet.
	}
}
