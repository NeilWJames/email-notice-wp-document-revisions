<?php
/**
 * The internal notification log table.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * WPDR_EN_User_Log_Table: rows, search, sorting, paging and cells.
 */
class Test_WPDR_EN_User_Log extends WPDR_EN_UnitTestCase {

	/**
	 * Documents: 'alpha' and 'beta'.
	 *
	 * @var int[]
	 */
	private static $docs = array();

	// phpcs:disable
	/**
	 * Users and two documents shared by the tests.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// phpcs:enable
		require_once dirname( __DIR__ ) . '/includes/class-wpdr-en-user-log-table.php';
		self::create_users( $factory );
		self::$docs['alpha'] = self::create_document( array( 'post_title' => 'Alpha Report' ) );
		self::$docs['beta']  = self::create_document( array( 'post_title' => 'Beta 100% Plan' ) );
	}

	/**
	 * Clear the request.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset( $_GET['s'], $_GET['orderby'], $_GET['order'], $_REQUEST['paged'] );
		parent::tear_down();
	}

	/**
	 * Add a log row.
	 *
	 * @param string   $doc    Document key in self::$docs.
	 * @param string   $who    User key in self::$users.
	 * @param string   $time   Time sent (Y-m-d H:i:s).
	 * @param string   $status Status text.
	 * @param int|null $extra  Extra text ID.
	 * @return void
	 */
	private static function log( $doc, $who, $time, $status = 'Successful', $extra = null ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'wpdr_notification_log',
			array(
				'user_id'        => self::$users[ $who ],
				'post_id'        => self::$docs[ $doc ],
				'time_mail_sent' => $time,
				'user_email'     => $who . '@example.com',
				'status'         => $status,
				'extra_text_id'  => $extra,
			)
		);
	}

	/**
	 * Three rows: alpha to subscriber (oldest), beta to author, alpha to author (newest).
	 *
	 * @return void
	 */
	private static function three_rows() {
		self::log( 'alpha', 'subscriber', '2026-01-01 10:00:00', 'Failed' );
		self::log( 'beta', 'author', '2026-01-02 10:00:00' );
		self::log( 'alpha', 'author', '2026-01-03 10:00:00', 'Success Attachment' );
	}

	/**
	 * Build the table from the current request.
	 *
	 * @return WPDR_EN_User_Log_Table
	 */
	private static function table() {
		$table = new WPDR_EN_User_Log_Table( array( 'screen' => 'wpdr_en_notification_log' ) );
		$table->prepare_items();
		return $table;
	}

	/**
	 * A column of the table's rows, in order.
	 *
	 * @param WPDR_EN_User_Log_Table $table  Table.
	 * @param string                 $column Column name.
	 * @return array
	 */
	private static function column( $table, $column ) {
		return array_column( $table->items, $column );
	}

	/**
	 * Rows join the document title and user name; newest first by default.
	 */
	public function test_rows_newest_first() {
		self::three_rows();

		$table = self::table();

		$this->assertSame( array( 'Alpha Report', 'Beta 100% Plan', 'Alpha Report' ), self::column( $table, 'post_title' ) );
		$this->assertSame( array( 'author user', 'author user', 'subscriber user' ), self::column( $table, 'user_display_name' ) );
		$this->assertSame( array( 'Success Attachment', 'Successful', 'Failed' ), self::column( $table, 'status' ) );
	}

	/**
	 * The search matches document titles and user names.
	 */
	public function test_search_title_and_name() {
		self::three_rows();

		$_GET['s'] = 'beta';
		$this->assertSame( array( 'Beta 100% Plan' ), self::column( self::table(), 'post_title' ) );

		$_GET['s'] = 'subscriber';
		$this->assertSame( array( 'subscriber user' ), self::column( self::table(), 'user_display_name' ) );
	}

	/**
	 * A % in the search is a literal character, not a wildcard.
	 */
	public function test_search_percent_is_literal() {
		self::three_rows();

		$_GET['s'] = '100%';
		$this->assertCount( 1, self::table()->items );

		$_GET['s'] = '%';
		$this->assertCount( 1, self::table()->items, 'only the title containing %' );
	}

	/**
	 * Sorting by a column, in either direction.
	 */
	public function test_sort_by_column() {
		self::three_rows();

		$_GET['orderby'] = 'user_display_name';
		$_GET['order']   = 'asc';
		$this->assertSame( array( 'author user', 'author user', 'subscriber user' ), self::column( self::table(), 'user_display_name' ) );

		$_GET['orderby'] = 'post_title';
		$_GET['order']   = 'desc';
		$this->assertSame( 'Beta 100% Plan', self::column( self::table(), 'post_title' )[0] );

		$_GET['orderby'] = 'status';
		$_GET['order']   = 'asc';
		$this->assertSame( array( 'Failed', 'Success Attachment', 'Successful' ), self::column( self::table(), 'status' ) );

		$_GET['orderby'] = 'user_email';
		$this->assertSame( 'author@example.com', self::column( self::table(), 'user_email' )[0] );
	}

	/**
	 * Unknown sort columns and directions fall back to newest first, without reaching the SQL.
	 */
	public function test_unknown_sort_falls_back() {
		global $wpdb;
		self::three_rows();

		$_GET['orderby'] = 'post_title, (SELECT 1)';
		$_GET['order']   = 'sideways';
		$table           = self::table();

		$this->assertSame( '', $wpdb->last_error );
		$this->assertSame( array( 'Success Attachment', 'Successful', 'Failed' ), self::column( $table, 'status' ) );
	}

	/**
	 * Twenty rows per page.
	 */
	public function test_paging() {
		for ( $i = 0; $i < 21; $i++ ) {
			self::log( 'alpha', 'subscriber', sprintf( '2026-02-01 10:%02d:00', $i ) );
		}

		$table = self::table();
		$this->assertCount( 20, $table->items );
		$this->assertSame( 21, $table->get_pagination_arg( 'total_items' ) );
		$this->assertEquals( 2, $table->get_pagination_arg( 'total_pages' ) );

		$_REQUEST['paged'] = 2;
		$this->assertCount( 1, self::table()->items );
	}

	/**
	 * Columns, the sortable ones, and the message with no rows.
	 */
	public function test_columns_and_empty_message() {
		$table = self::table();

		$this->assertSame( array( 'id', 'post_title', 'time_mail_sent', 'user_display_name', 'user_email', 'status' ), array_keys( $table->get_columns() ) );
		$this->assertArrayNotHasKey( 'id', $table->get_sortable_columns() );
		$this->assertSame( array(), $table->items );

		ob_start();
		$table->no_items();
		$this->assertSame( 'No emails sent out, yet.', ob_get_clean() );

		ob_start();
		$table->bulk_actions();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Links depend on what the viewer can edit; other columns are text.
	 */
	public function test_cell_links_follow_capabilities() {
		$table = new WPDR_EN_User_Log_Table( array( 'screen' => 'wpdr_en_notification_log' ) );
		$item  = array(
			'id'                => 1,
			'post_id'           => self::$docs['alpha'],
			'post_title'        => 'Alpha Report',
			'user_id'           => self::$users['subscriber'],
			'user_display_name' => 'subscriber user',
			'time_mail_sent'    => '2026-01-01 10:00:00',
			'user_email'        => 'subscriber@example.com',
			'status'            => 'Successful',
			'extra_text'        => null,
		);

		// User 1: on multisite only a network administrator can edit other users.
		wp_set_current_user( 1 );
		$this->assertStringContainsString( 'post.php?post=' . self::$docs['alpha'], $table->column_default( $item, 'post_title' ) );
		$this->assertStringContainsString( 'user-edit.php', $table->column_default( $item, 'user_display_name' ) );
		$this->assertSame( 'Subscriber User', wp_strip_all_tags( $table->column_default( $item, 'user_display_name' ) ), 'name capitalised' );

		wp_set_current_user( self::$users['contributor'] );
		$this->assertStringNotContainsString( 'post.php', $table->column_default( $item, 'post_title' ) );
		$this->assertStringContainsString( esc_url( get_permalink( self::$docs['alpha'] ) ), $table->column_default( $item, 'post_title' ) );
		$this->assertSame( 'Subscriber User', $table->column_default( $item, 'user_display_name' ) );

		$this->assertSame( '2026-01-01 10:00:00', $table->column_default( $item, 'time_mail_sent' ) );
		$this->assertSame( '', $table->column_default( $item, 'unknown' ) );
		$this->assertSame( '1', $table->column_id( $item ), 'no Extra Text button without extra text' );
	}

	/**
	 * Extra text sent with a mail is shown in the log.
	 */
	public function test_extra_text_from_sending() {
		global $wpdr_en;
		self::subscribe( self::$users['subscriber'] );

		$wpdr_en->notify( self::$docs['beta'], 'Please review' );
		$items = self::table()->items;

		$this->assertCount( 1, $items );
		$this->assertSame( 'Please review', $items[0]['extra_text'] );
		$this->assertSame( 'Successful', $items[0]['status'] );
	}
}
