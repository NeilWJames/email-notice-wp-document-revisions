<?php
/**
 * WP Document Revisions Email Notice Log Table Functionality
 *
 * @author  Neil W. James <neil@familyjames.com>
 * @package WP Document Revisions Email Notice
 */

// Load WP_List_Table if not loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
	require_once ABSPATH . 'wp-admin/includes/screen.php';
}

/**
 * Main WP_Document_Revisions_Email_Notice_Log_Table class.
 */
class WPDR_EN_Ext_Log_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 2.0
	 * @param mixed[] $args  Arguments to List_Table.
	 * @return void
	 */
	public function __construct( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'singular' => __( 'External Notification email sent', 'email-notice-wp-document-revisions' ),    // singular name of the listed records.
				'plural'   => __( 'External Notification emails sent', 'email-notice-wp-document-revisions' ),   // plural name of the listed records.
				'ajax'     => false,
				'screen'   => null,
			)
		);
		parent::__construct( $args );
	}

	/**
	 * Define columns for table.
	 *
	 * @since 2.0
	 * @return string[]
	 */
	public function get_columns() {
		$columns = array(
			'id'             => '#',
			'post_title'     => __( 'Post Title', 'email-notice-wp-document-revisions' ),
			'list_title'     => __( 'List Title', 'email-notice-wp-document-revisions' ),
			'time_mail_sent' => __( 'Email sent', 'email-notice-wp-document-revisions' ),
			'user_name'      => __( 'User Name', 'email-notice-wp-document-revisions' ),
			'user_email'     => __( 'User Email', 'email-notice-wp-document-revisions' ),
			'status'         => __( 'Status', 'email-notice-wp-document-revisions' ),
		);
		return $columns;
	}

	/**
	 * Define sortable columns for table.
	 *
	 * @since 2.0
	 * @return string[]
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'post_title'     => array( 'post_title', true ),
			'list_title'     => array( 'list_title', true ),
			'time_mail_sent' => array( 'time_mail_sent', true ),
			'user_name'      => array( 'user_display_name', true ),
			'user_email'     => array( 'user_email', true ),
			'status'         => array( 'status', false ),
		);
		return $sortable_columns;
	}

	/**
	 * Define defaults columns for table.
	 *
	 * @since 2.0
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
			case 'list_title':
				if ( current_user_can( 'edit_document', $item['list_id'] ) ) {
					return '<a href="' . get_edit_post_link( $item['list_id'] ) . '">' . $item[ $column_name ] . '</a>';
				} else {
					return '<a href="' . get_permalink( $item['list_id'] ) . '">' . $item[ $column_name ] . '</a>';
				}
			case 'user_name':
				return ucwords( $item[ $column_name ] );
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
			'view' => '<button onclick="wpdr_en_extra( ' . $item['id'] . ' )">' . __( 'Extra Text', 'email-notice-wp-document-revisions' ) . '</button>&nbsp;&nbsp;' .
					'<textarea id="extra_' . $item['id'] . '" onclick="wpdr_en_extra( ' . $item['id'] . ' )" rows="3" cols="40" style="display: none">' .
					$item['extra_text'] . '</textarea>',
		);
		return sprintf( '%1$s %2$s', $item['id'], $this->row_actions( $actions, true ) );
	}

	/**
	 * Define data to populate table.
	 *
	 * @since 2.0
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
		$search = '';
		if ( ! empty( $_GET['s'] ) ) {
			$s      = esc_attr( sanitize_text_field( wp_unslash( $_GET['s'] ) ) );
			$search = "AND (p.post_title LIKE '%{$s}%' OR l.user_name LIKE '%{$s}%' OR l.user_email LIKE '%{$s}%' )";
		}

		$orderby = 'time_mail_sent';
		if ( ! empty( $_GET['orderby'] ) ) {
			$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
			switch ( $orderby ) {
				case 'post_title':
				case 'user_name':
				case 'user_email':
				case 'status':
				case 'time_mail_sent':
					break;
				default:
					$orderby = 'time_mail_sent';
					break;
			}
		}
		$order = 'desc';
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
		$sql = "SELECT 	l.id as id,
				l.post_id as post_id,
				p.post_title as post_title,
				l.doc_ext_list_id as list_id,
				t.post_title as list_title,
				l.user_name as user_name,						
				l.time_mail_sent as time_mail_sent,
				l.user_email as user_email,
				l.status,
				(SELECT e.extra_text FROM {$wpdb->prefix}wpdr_en_extra_text e
				 WHERE e.id = l.extra_text_id) as extra_text
				FROM {$wpdb->prefix}wpdr_ext_notice_log l
				INNER JOIN {$wpdb->prefix}posts p
				ON l.post_id = p.ID
				INNER JOIN {$wpdb->prefix}posts t
				ON l.doc_ext_list_id = t.ID
				WHERE 1=1
				{$search}
				ORDER BY {$orderby} {$order}, l.id {$order}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$log_items    = $wpdb->get_results( $sql, ARRAY_A ); // ARRAY_A will ensure that we get associated array instead of stdClass.
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
	 * @since 2.0
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No emails sent out, yet.', 'email-notice-wp-document-revisions' );
	}

	/**
	 * Define bulk actions for table.
	 *
	 * Should be get_block_actions.
	 *
	 * @since 2.0
	 * @param string $which not yet defined.
	 * @return void
	 */
	public function bulk_actions( $which = '' ) {
		// Nothing here yet.
	}
}
