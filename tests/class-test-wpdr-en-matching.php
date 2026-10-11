<?php
/**
 * External List matching and sending.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Which Document External Lists match a document, and who gets the mail.
 *
 * Exercised through WPDR_Email_Notice::ext_notice(), the public entry point used by the
 * "Send external list email(s)" button.
 */
class Test_WPDR_EN_Matching extends WPDR_EN_UnitTestCase {

	/**
	 * Term IDs: parent and child category, two tags.
	 *
	 * @var int[]
	 */
	private static $t = array();

	// phpcs:disable
	/**
	 * Users and terms shared by the tests.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// phpcs:enable
		self::create_users( $factory );
		self::$t['parent'] = $factory->category->create( array( 'name' => 'EN Parent' ) );
		self::$t['child']  = $factory->category->create(
			array(
				'name'   => 'EN Child',
				'parent' => self::$t['parent'],
			)
		);
		self::$t['red']    = $factory->tag->create( array( 'name' => 'EN Red' ) );
		self::$t['blue']   = $factory->tag->create( array( 'name' => 'EN Blue' ) );
	}

	/**
	 * Send external notices for a document to all matching lists.
	 *
	 * @param int   $doc_id Document ID.
	 * @param int[] $lists  Lists to restrict to (empty = all matching).
	 * @return array Result from ext_notice().
	 */
	private function send( $doc_id, $lists = array() ) {
		global $wpdr_en;
		return $wpdr_en->ext_notice( $doc_id, $lists, '' );
	}

	/**
	 * A list with the "any" rule matches when one of its terms is on the document.
	 */
	public function test_any_rule_matches_one_term() {
		self::create_list(
			array(
				'terms'     => array( 'post_tag' => array( self::$t['red'], self::$t['blue'] ) ),
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
				),
			)
		);
		$doc = self::create_document( array( 'terms' => array( 'post_tag' => self::$t['red'] ) ) );

		$result = $this->send( $doc );

		$this->assertFalse( $result['error'], 'no error' );
		$this->assertSame( 1, $result['sent_count'] );
		$this->assertSame( array( 'ann@example.org' ), $this->mailed_addresses() );
	}

	/**
	 * A list whose terms are not on the document is not used.
	 */
	public function test_no_matching_term_sends_nothing() {
		self::create_list(
			array(
				'terms'     => array( 'post_tag' => self::$t['blue'] ),
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
				),
			)
		);
		$doc = self::create_document( array( 'terms' => array( 'post_tag' => self::$t['red'] ) ) );

		$result = $this->send( $doc );

		$this->assertTrue( $result['error'] );
		$this->assertSame( 'F-03', $result['error_code'] );
		$this->assertCount( 0, $this->mails );
	}

	/**
	 * With the "all" rule, every list term must be on the document.
	 */
	public function test_all_rule_needs_every_term() {
		self::create_list(
			array(
				'terms'     => array( 'post_tag' => array( self::$t['red'], self::$t['blue'] ) ),
				'tm_rule'   => 1,
				'addresses' => array(
					array(
						'user_name' => 'Bob',
						'email'     => 'bob@example.org',
					),
				),
			)
		);
		$one  = self::create_document( array( 'terms' => array( 'post_tag' => self::$t['red'] ) ) );
		$both = self::create_document( array( 'terms' => array( 'post_tag' => array( self::$t['red'], self::$t['blue'] ) ) ) );

		$this->send( $one );
		$this->assertCount( 0, $this->mails, 'one of two terms: no match' );

		$this->send( $both );
		$this->assertSame( array( 'bob@example.org' ), $this->mailed_addresses(), 'both terms: match' );
	}

	/**
	 * A parent term on the list matches a child term on the document.
	 */
	public function test_parent_term_matches_child() {
		self::create_list(
			array(
				'terms'     => array( 'category' => self::$t['parent'] ),
				'addresses' => array(
					array(
						'user_name' => 'Cat',
						'email'     => 'cat@example.org',
					),
				),
			)
		);
		$doc = self::create_document( array( 'terms' => array( 'category' => self::$t['child'] ) ) );

		$this->send( $doc );

		$this->assertSame( array( 'cat@example.org' ), $this->mailed_addresses() );
	}

	/**
	 * A child term on the list does not match a parent term on the document.
	 */
	public function test_child_term_does_not_match_parent() {
		self::create_list(
			array(
				'terms'     => array( 'category' => self::$t['child'] ),
				'addresses' => array(
					array(
						'user_name' => 'Cat',
						'email'     => 'cat@example.org',
					),
				),
			)
		);
		$doc = self::create_document( array( 'terms' => array( 'category' => self::$t['parent'] ) ) );

		$this->send( $doc );

		$this->assertCount( 0, $this->mails );
	}

	/**
	 * Paused lists, unpublished lists and paused addresses are skipped.
	 */
	public function test_paused_and_unpublished_are_skipped() {
		$terms = array( 'post_tag' => self::$t['red'] );
		self::create_list(
			array(
				'terms'     => $terms,
				'pause'     => 1,
				'addresses' => array(
					array(
						'user_name' => 'Paused List',
						'email'     => 'paused-list@example.org',
					),
				),
			)
		);
		self::create_list(
			array(
				'terms'     => $terms,
				'status'    => 'draft',
				'addresses' => array(
					array(
						'user_name' => 'Draft List',
						'email'     => 'draft-list@example.org',
					),
				),
			)
		);
		self::create_list(
			array(
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'Active',
						'email'     => 'active@example.org',
					),
					array(
						'user_name' => 'Paused Person',
						'email'     => 'paused-person@example.org',
						'pause'     => 1,
					),
				),
			)
		);
		$doc = self::create_document( array( 'terms' => $terms ) );

		$this->send( $doc );

		$this->assertSame( array( 'active@example.org' ), $this->mailed_addresses() );
	}

	/**
	 * Passing list IDs restricts sending to those lists.
	 */
	public function test_selected_lists_only() {
		$terms = array( 'post_tag' => self::$t['red'] );
		$first = self::create_list(
			array(
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'First',
						'email'     => 'first@example.org',
					),
				),
			)
		);
		self::create_list(
			array(
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'Second',
						'email'     => 'second@example.org',
					),
				),
			)
		);
		$doc = self::create_document( array( 'terms' => $terms ) );

		$this->send( $doc, array( $first ) );

		$this->assertSame( array( 'first@example.org' ), $this->mailed_addresses() );
	}

	/**
	 * External notices are only sent for public documents.
	 */
	public function test_only_public_documents() {
		$terms = array( 'post_tag' => self::$t['red'] );
		self::create_list(
			array(
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
				),
			)
		);
		$private  = self::create_document(
			array(
				'terms'       => $terms,
				'post_status' => 'private',
			)
		);
		$password = self::create_document(
			array(
				'terms'         => $terms,
				'post_password' => 'secret',
			)
		);
		$draft    = self::create_document(
			array(
				'terms'       => $terms,
				'post_status' => 'draft',
			)
		);

		foreach ( array( $private, $password, $draft ) as $doc ) {
			$this->send( $doc );
		}

		$this->assertCount( 0, $this->mails );
	}

	/**
	 * Each mail is logged and the document is flagged as sent.
	 */
	public function test_mail_is_logged() {
		global $wpdb;
		$terms   = array( 'post_tag' => self::$t['red'] );
		$list_id = self::create_list(
			array(
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
				),
			)
		);
		$doc     = self::create_document( array( 'terms' => $terms ) );

		$result = $this->send( $doc );

		$this->assertSame( 1, $result['logged_count'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpdr_ext_notice_log WHERE post_id = %d", $doc ), ARRAY_A );
		$this->assertNotNull( $row, 'log row written' );
		$this->assertSame( (string) $list_id, (string) $row['doc_ext_list_id'] );
		$this->assertSame( 'Ann', $row['user_name'] );
		$this->assertSame( 'ann@example.org', $row['user_email'] );
		$this->assertSame( 'Successful', $row['status'] );
		$this->assertSame( '1', get_post_meta( $doc, 'wpdr_en_ext_notice_sent', true ) );
	}
}
