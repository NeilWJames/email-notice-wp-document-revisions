<?php
/**
 * Exporting the email logs as CSV (4.0.0).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * The Export CSV link, the rows exported (search and sort as on the page, all pages) and the CSV text.
 */
class Test_WPDR_EN_Log_Export extends WPDR_EN_UnitTestCase {

	/**
	 * Tag used to match the External List.
	 *
	 * @var int
	 */
	private static $tag;

	// phpcs:disable
	/**
	 * Users and a tag shared by the tests.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// phpcs:enable
		self::create_users( $factory );
		self::$tag = $factory->tag->create( array( 'name' => 'EN Export' ) );
	}

	/**
	 * Act as an editor (who can read the logs).
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$users['editor'] );
	}

	/**
	 * Clear the request and screen.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset( $_GET['s'], $_GET['orderby'], $_GET['order'], $_GET['log'], $_GET['_wpnonce'], $_REQUEST['_wpnonce'], $GLOBALS['hook_suffix'] );
		set_current_screen( 'dashboard' );
		parent::tear_down();
	}

	/**
	 * The export link keeps the search and sort and carries a nonce.
	 */
	public function test_export_url() {
		$_GET['s']       = 'Report & co';
		$_GET['orderby'] = 'status';

		// wp_nonce_url() returns the URL HTML-escaped, ready for an attribute.
		$url = html_entity_decode( WPDR_Email_Notice::export_url( 'external' ) );
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$this->assertStringContainsString( 'admin-post.php', $url );
		$this->assertSame( 'wpdr_en_export_log', $query['action'] );
		$this->assertSame( 'external', $query['log'] );
		$this->assertSame( 'Report & co', $query['s'] );
		$this->assertSame( 'status', $query['orderby'] );
		$this->assertArrayNotHasKey( 'order', $query );
		$this->assertSame( 1, wp_verify_nonce( $query['_wpnonce'], 'wpdr_en_export_log' ) );
	}

	/**
	 * The internal export has a header row and every matching row, not just one page.
	 */
	public function test_internal_rows() {
		global $wpdr_en;
		self::subscribe( self::$users['subscriber'] );
		$docs = array();
		for ( $i = 0; $i < 22; $i++ ) {
			$docs[] = self::create_document( array( 'post_title' => 'Doc ' . $i ) );
		}
		$other = self::create_document( array( 'post_title' => 'Other' ) );
		foreach ( $docs as $doc ) {
			$wpdr_en->notify( $doc, '' );
		}
		$wpdr_en->notify( $other, 'Note' );

		$rows = WPDR_Email_Notice::export_rows( 'internal' );
		$this->assertSame( array( 'ID', 'Document ID', 'Document', 'Email sent', 'User ID', 'User Name', 'User Email', 'Status', 'Extra Text' ), $rows[0] );
		$this->assertCount( 24, $rows, 'header + 23 rows (more than one page)' );

		$_GET['s'] = 'Other';
		$rows      = WPDR_Email_Notice::export_rows( 'internal' );
		$this->assertCount( 2, $rows );
		$this->assertSame( (string) $other, $rows[1][1] );
		$this->assertSame( 'Other', $rows[1][2] );
		$this->assertSame( 'subscriber user', $rows[1][5] );
		$this->assertSame( 'subscriber@example.com', $rows[1][6] );
		$this->assertSame( 'Successful', $rows[1][7] );
		$this->assertSame( 'Note', $rows[1][8] );
	}

	/**
	 * The external export includes the List.
	 */
	public function test_external_rows() {
		global $wpdr_en;
		$terms = array( 'post_tag' => self::$tag );
		$list  = self::create_list(
			array(
				'title'     => 'Monthly',
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
				),
			)
		);
		$doc   = self::create_document(
			array(
				'post_title' => 'Report',
				'terms'      => $terms,
			)
		);
		$wpdr_en->ext_notice( $doc, array(), '' );

		$rows = WPDR_Email_Notice::export_rows( 'external' );

		$this->assertSame( array( 'ID', 'Document ID', 'Document', 'List ID', 'List', 'Email sent', 'Name', 'Email', 'Status', 'Extra Text' ), $rows[0] );
		$this->assertCount( 2, $rows );
		$this->assertSame( array( (string) $doc, 'Report', (string) $list, 'Monthly' ), array_slice( $rows[1], 1, 4 ) );
		$this->assertSame( array( 'Ann', 'ann@example.org', 'Successful', '' ), array_slice( $rows[1], 6 ) );
	}

	/**
	 * CSV text: byte order mark, quoting, and cells a spreadsheet would run as formulas made text.
	 */
	public function test_csv_text() {
		$csv = WPDR_Email_Notice::csv(
			array(
				array( 'Name', 'Note' ),
				array( 'Smith, "Jo"', "two\nlines" ),
				array( '=HYPERLINK("http://x")', '+1' ),
				array( '-2', '@SUM(A1)' ),
				array( 'Zoë', '' ),
			)
		);

		$this->assertStringStartsWith( "\xEF\xBB\xBF", $csv );
		$lines = substr( $csv, 3 );
		$this->assertStringContainsString( "\"Smith, \"\"Jo\"\"\",\"two\nlines\"\n", $lines );
		$this->assertStringContainsString( "\"'=HYPERLINK(\"\"http://x\"\")\",'+1\n", $lines );
		$this->assertStringContainsString( "'-2,'@SUM(A1)\n", $lines );
		$this->assertStringContainsString( "Zoë,\n", $lines );
	}

	/**
	 * The export needs the page's nonce and the capability to read the logs.
	 */
	public function test_export_needs_nonce_and_capability() {
		global $wpdr_en;

		try {
			$wpdr_en->export_log();
			$this->fail( 'no nonce' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		wp_set_current_user( self::$users['author'] );
		$nonce                = wp_create_nonce( 'wpdr_en_export_log' );
		$_GET['_wpnonce']     = $nonce;
		$_REQUEST['_wpnonce'] = $nonce;
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'sufficient permissions' );
		$wpdr_en->export_log();
	}

	/**
	 * Both log pages have the Export CSV link, and the handler is hooked.
	 */
	public function test_log_pages_link_to_export() {
		global $wpdr_en;
		$this->assertNotFalse( has_action( 'admin_post_wpdr_en_export_log', array( $wpdr_en, 'export_log' ) ) );

		foreach ( array(
			'notification_log_list' => 'wpdr_en_notification_log',
			'ext_notice_log_list'   => 'wpdr_en_ext_notice_log',
		) as $method => $page ) {
			$GLOBALS['hook_suffix'] = 'doc_ext_list_page_' . $page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			set_current_screen( $GLOBALS['hook_suffix'] );
			ob_start();
			$wpdr_en->$method();
			$html = ob_get_clean();

			$this->assertMatchesRegularExpression( '/<a href="[^"]*action=wpdr_en_export_log[^"]*" class="page-title-action">Export CSV<\/a>/', $html, $method );
		}
	}
}
