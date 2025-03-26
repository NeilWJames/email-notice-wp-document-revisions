<?php
/**
 * WP Document Revisions Email User List Table Functionality
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
class WPDR_EN_Ext_User_Table extends WP_List_Table {


	/**
	 * File version.
	 *
	 * @since 2.0.0
	 *
	 * @var string $version file version
	 */
	public static $version = '2.0.0';

	/**
	 * Post id being output.
	 *
	 * @since 2.0
	 *
	 * @var int $post_id post_id being displayd.
	 */
	private static $post_id = null;

	/**
	 * Whether the list of addresses is empty.
	 *
	 * @since 2.0
	 *
	 * @var int $no_addr Whether there are no users in the list..
	 */
	public static $no_addr;

	/**
	 * Constructor
	 *
	 * @since 2.0
	 * @global WP_Post $post Post object.
	 *
	 * @param mixed[] $args  Arguments to List_Table.
	 * @return void
	 */
	public function __construct( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'singular' => __( 'External User Address', 'wpdr-email-notice' ),    // singular name of the listed records.
				'plural'   => __( 'External User Addresses', 'wpdr-email-notice' ),   // plural name of the listed records.
				'ajax'     => false,
				'screen'   => 'doc_ext_list',
			)
		);
		parent::__construct( $args );

		// set the post.
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['post'] ) ) {
			self::$post_id = sanitize_text_field( wp_unslash( $_GET['post'] ) );
		} elseif ( isset( $_POST['post'] ) ) {
			self::$post_id = sanitize_text_field( wp_unslash( $_POST['post'] ) );
		} else {
			global $post;
			if ( ! is_null( $post ) ) {
				self::$post_id = $post->ID;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification

		// sometimes the URI used within table orderings) loses these fields.
		$args = array(
			'post'   => self::$post_id,
			'action' => 'edit',
		);
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.WP.DeprecatedFunctions
			$_SERVER['REQUEST_URI'] = add_query_arg( $args, sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}
	}

	/**
	 * Define columns for table.
	 *
	 * @since 2.0
	 * @return string[]
	 */
	public function get_columns() {
		$columns = array(
			'rec_num'   => '#',
			'user_name' => __( 'User Name', 'wpdr-email-notice' ),
			'email'     => __( 'Email Address', 'wpdr-email-notice' ),
			'pause'     => __( 'Pause Mail', 'wpdr-email-notice' ),
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
			'user_name' => array( 'user_name', true ),
			'email'     => array( 'email', true ),
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
			case 'rec_num':
			case 'user_name':
			case 'email':
				return $item[ $column_name ];
			case 'pause':
				return '<input type="checkbox"' . ( 0 === $item[ $column_name ] ? '' : '  checked="checked"' ) . ' disabled></>&nbsp;';
		}
	}

	/**
	 * Define actions for table.
	 *
	 * @since 2.0
	 * @param mixed[] $item row in List Table.
	 * @return string[]
	 */
	public function column_rec_num( $item ) {
		$actions = array(
			'edit'   => '<button onclick=\'wpdr_en_edit( "' . $item['user_name'] . '", "' . $item['email'] . '", "' . $item['pause'] . '" )\'>' . __( 'Edit', 'wpdr-email-notice' ) . '</button>',
			'delete' => sprintf( '<button onclick="wpdr_en_delete(%s, %s)">' . __( 'Delete', 'wpdr-email-notice' ) . '</button>', self::$post_id, $item['rec_num'] ),
		);

		return sprintf( '%1$s %2$s', $item['rec_num'], $this->row_actions( $actions ) );
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
		$orderby = 'rec_num';
		$order   = 'asc';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = '';
		if ( ! empty( $_REQUEST['s'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			switch ( $_REQUEST['orderby'] ) {
				case 'rec_num':
				case 'user_name':
				case 'email':
					$orderby = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) );
					break;
				default:
					$orderby = 'rec_num';
					break;
			}
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			switch ( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) {
				case 'asc':
				case 'desc':
					$order = sanitize_text_field( wp_unslash( $_REQUEST['order'] ) );
					break;
				default:
					$order = 'asc';
					break;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$users_rec = get_post_meta( self::$post_id, 'wpdr_en_addressees', true );
		if ( false === $users_rec || empty( $users_rec ) ) {
			$rec_num = 0;
			$users   = array();
		} else {
			$users_rec = json_decode( $users_rec, true );
			$rec_num   = $users_rec['rec_num'];
			$users     = $users_rec['users'];
			// make sure users have a pause column.
			foreach ( $users as $key => $user ) {
				if ( ! array_key_exists( 'pause', $user ) ) {
					$users[ $key ]['pause'] = 0;
				}
			}
			// sort the array.
			$keys = array_column( $users, $orderby );
			array_multisort( $keys, ( 'asc' === $order ? SORT_ASC : SORT_DESC ), $users );
		}
		// store whether there are entries in the user table.
		self::$no_addr = (int) empty( $users );

		if ( ! empty( $search ) ) {
			$rows = array();
			foreach ( $users as $user ) {
				if ( strpos( $user['user_name'], $search ) !== false || strpos( $user['email'], $search ) !== false ) {
					$rows[] = $user;
				}
			}
			$users = $rows;
		}
		$current_page = $this->get_pagenum();
		$total_items  = count( $users );
		$users        = array_slice( $users, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->items = $users;
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
		if ( self::$no_addr ) {
			esc_html_e( 'No addressees created yet.', 'wpdr-email-notice' );
		} else {
			esc_html_e( 'No matching addressees found.', 'wpdr-email-notice' );
		}
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

	/**
	 * Generates the table navigation above or below the table
	 *
	 * Copy of parent class but without nonce generation
	 *
	 * @since 3.1.0
	 * @param string $which was it above or below.
	 */
	protected function display_tablenav( $which ) {
		?>
	<div class="tablenav <?php echo esc_attr( $which ); ?>">

		<?php if ( $this->has_items() ) : ?>
		<div class="alignleft actions bulkactions">
			<?php $this->bulk_actions( $which ); ?>
		</div>
			<?php
		endif;
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		?>

		<br class="clear" />
	</div>
		<?php
	}
}
