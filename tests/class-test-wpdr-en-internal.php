<?php
/**
 * Internal (WordPress user) notifications.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Who receives internal notifications, exercised through WPDR_Email_Notice::notify().
 */
class Test_WPDR_EN_Internal extends WPDR_EN_UnitTestCase {

	// phpcs:disable
	/**
	 * Users shared by the tests.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// phpcs:enable
		self::create_users( $factory );
	}

	/**
	 * Send internal notifications for a document.
	 *
	 * @param int    $doc_id Document ID.
	 * @param string $extra  Extra text.
	 * @return array Result from notify().
	 */
	private function send( $doc_id, $extra = '' ) {
		global $wpdr_en;
		return $wpdr_en->notify( $doc_id, $extra );
	}

	/**
	 * Only opted-in users are mailed about a public document.
	 */
	public function test_public_document_mails_opted_in_users() {
		self::subscribe( self::$users['subscriber'] );
		self::subscribe( self::$users['author'] );
		self::subscribe( self::$users['contributor'], false );
		$doc = self::create_document();

		$result = $this->send( $doc );

		$this->assertFalse( $result['error'], 'no error' );
		$this->assertSame( 2, $result['sent_count'] );
		$this->assertSame( array( 'author@example.com', 'subscriber@example.com' ), $this->mailed_addresses() );
		$this->assertSame( '1', get_post_meta( $doc, 'wpdr_en_notification_sent', true ) );
	}

	/**
	 * A private document is only mailed to opted-in users who can read it.
	 */
	public function test_private_document_needs_read_access() {
		self::subscribe( self::$users['subscriber'] );
		self::subscribe( self::$users['administrator'] );
		$doc = self::create_document( array( 'post_status' => 'private' ) );

		$this->send( $doc );

		$this->assertSame( array( 'administrator@example.com' ), $this->mailed_addresses() );
	}

	/**
	 * Unpublished documents are not mailed.
	 */
	public function test_draft_document_is_not_mailed() {
		self::subscribe( self::$users['subscriber'] );
		$doc = self::create_document( array( 'post_status' => 'draft' ) );

		$result = $this->send( $doc );

		$this->assertTrue( $result['error'] );
		$this->assertSame( 'F-03', $result['error_code'] );
		$this->assertCount( 0, $this->mails );
	}

	/**
	 * Each internal mail is logged against the user and document.
	 */
	public function test_mail_is_logged() {
		global $wpdb;
		self::subscribe( self::$users['subscriber'] );
		$doc = self::create_document();

		$result = $this->send( $doc );

		$this->assertSame( 1, $result['logged_count'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpdr_notification_log WHERE post_id = %d", $doc ), ARRAY_A );
		$this->assertNotNull( $row, 'log row written' );
		$this->assertSame( (string) self::$users['subscriber'], (string) $row['user_id'] );
		$this->assertSame( 'subscriber@example.com', $row['user_email'] );
	}

	/**
	 * Mail is sent as HTML.
	 */
	public function test_mail_is_html() {
		self::subscribe( self::$users['subscriber'] );
		$doc = self::create_document();

		$this->send( $doc );

		$this->assertCount( 1, $this->mails );
		$this->assertContains( 'Content-type: text/html', (array) $this->mails[0]['headers'] );
		$this->assertStringStartsWith( '<html><body>', $this->mails[0]['message'] );
	}
}
