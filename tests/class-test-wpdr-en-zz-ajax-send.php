<?php
/**
 * Manual send AJAX handlers (security fix #2).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * The wpdr_en_send_notification_manual and wpdr_en_send_ext_notice_manual actions:
 * the sender must be able to edit the Document being mailed.
 */
class Test_WPDR_EN_ZZ_Ajax_Send extends WPDR_EN_Ajax_UnitTestCase {

	/**
	 * Tag used to match the External List.
	 *
	 * @var int
	 */
	private static $tag;

	/**
	 * Published document by the editor, matching one External List.
	 *
	 * @var int
	 */
	private static $doc;

	// phpcs:disable
	/**
	 * Users, a subscriber who wants mail, a document and a matching list.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// phpcs:enable
		self::create_users( $factory );
		self::subscribe( self::$users['subscriber'] );
		self::$tag = $factory->tag->create( array( 'name' => 'EN Ajax Send' ) );
		$terms     = array( 'post_tag' => self::$tag );
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
		self::$doc = self::create_document( array( 'terms' => $terms ) );
	}

	/**
	 * Both send actions.
	 *
	 * @return array[]
	 */
	public static function data_actions() {
		return array(
			'internal' => array( 'wpdr_en_send_notification_manual' ),
			'external' => array( 'wpdr_en_send_ext_notice_manual' ),
		);
	}

	/**
	 * An editor of the document can send.
	 *
	 * @dataProvider data_actions
	 * @param string $action AJAX action.
	 */
	public function test_editor_can_send( $action ) {
		$response = $this->ajax(
			$action,
			self::$users['editor'],
			array(
				'post_id' => self::$doc,
				'extra'   => '',
			)
		);

		$this->assertFalse( $response['error'], 'no error: ' . wp_json_encode( $response ) );
		$this->assertSame( 1, $response['sent_count'] );
		$this->assertCount( 1, $this->mails );
	}

	/**
	 * A contributor cannot send for a document they cannot edit.
	 *
	 * @dataProvider data_actions
	 * @param string $action AJAX action.
	 */
	public function test_contributor_cannot_send( $action ) {
		$response = $this->ajax(
			$action,
			self::$users['contributor'],
			array(
				'post_id' => self::$doc,
				'extra'   => '',
			)
		);

		$this->assertTrue( $response['error'] );
		$this->assertSame( 'F-08', $response['error_code'] );
		$this->assertCount( 0, $this->mails );
	}

	/**
	 * An author cannot send for another user's document.
	 *
	 * @dataProvider data_actions
	 * @param string $action AJAX action.
	 */
	public function test_author_cannot_send_others_document( $action ) {
		$response = $this->ajax(
			$action,
			self::$users['author'],
			array(
				'post_id' => self::$doc,
				'extra'   => '',
			)
		);

		$this->assertSame( 'F-08', $response['error_code'] );
		$this->assertCount( 0, $this->mails );
	}

	/**
	 * Only documents can be mailed.
	 *
	 * @dataProvider data_actions
	 * @param string $action AJAX action.
	 */
	public function test_non_document_is_rejected( $action ) {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$users['editor'] ) );

		$response = $this->ajax(
			$action,
			self::$users['administrator'],
			array(
				'post_id' => $post_id,
				'extra'   => '',
			)
		);

		$this->assertSame( 'F-07', $response['error_code'] );
		$this->assertCount( 0, $this->mails );
	}

	/**
	 * A missing or wrong nonce is rejected.
	 *
	 * @dataProvider data_actions
	 * @param string $action AJAX action.
	 */
	public function test_bad_nonce_is_rejected( $action ) {
		$response = $this->ajax(
			$action,
			self::$users['editor'],
			array(
				'post_id'       => self::$doc,
				'extra'         => '',
				'wpdr_en_nonce' => 'not-a-nonce',
			)
		);

		$this->assertSame( 'F-02', $response['error_code'] );
		$this->assertCount( 0, $this->mails );
	}

	/**
	 * An author can send internal notifications for their own document.
	 */
	public function test_author_can_send_internal_for_own_document() {
		$doc = self::create_document( array( 'post_author' => self::$users['author'] ) );

		$response = $this->ajax(
			'wpdr_en_send_notification_manual',
			self::$users['author'],
			array(
				'post_id' => $doc,
				'extra'   => '',
			)
		);

		$this->assertFalse( $response['error'], 'no error: ' . wp_json_encode( $response ) );
		$this->assertSame( array( 'subscriber@example.com' ), $this->mailed_addresses() );
	}

	/**
	 * Sending to External Lists needs read_doc_ext_lists, even for the document's author.
	 */
	public function test_external_needs_read_capability() {
		$doc = self::create_document(
			array(
				'post_author' => self::$users['author'],
				'terms'       => array( 'post_tag' => self::$tag ),
			)
		);

		$response = $this->ajax(
			'wpdr_en_send_ext_notice_manual',
			self::$users['author'],
			array(
				'post_id' => $doc,
				'extra'   => '',
			)
		);

		$this->assertSame( 'F-09', $response['error_code'] );
		$this->assertCount( 0, $this->mails );
	}

	/**
	 * A user who can read but not edit lists always sends to every matched list.
	 */
	public function test_list_choice_ignored_without_edit_capability() {
		$terms   = array( 'post_tag' => self::$tag );
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		( new WP_User( $user_id ) )->add_cap( 'read_doc_ext_lists' );
		$second = self::create_list(
			array(
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'Bea',
						'email'     => 'bea@example.org',
					),
				),
			)
		);
		$doc    = self::create_document(
			array(
				'post_author' => $user_id,
				'terms'       => $terms,
			)
		);

		$response = $this->ajax(
			'wpdr_en_send_ext_notice_manual',
			$user_id,
			array(
				'post_id' => $doc,
				'lists'   => array( $second ),
				'extra'   => '',
			)
		);

		$this->assertFalse( $response['error'], 'no error: ' . wp_json_encode( $response ) );
		$this->assertSame( array( 'ann@example.org', 'bea@example.org' ), $this->mailed_addresses() );
	}

	/**
	 * A list editor can choose which matched lists to send to.
	 */
	public function test_list_editor_can_choose_lists() {
		$terms  = array( 'post_tag' => self::$tag );
		$second = self::create_list(
			array(
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'Bea',
						'email'     => 'bea@example.org',
					),
				),
			)
		);

		$response = $this->ajax(
			'wpdr_en_send_ext_notice_manual',
			self::$users['editor'],
			array(
				'post_id' => self::$doc,
				'lists'   => array( $second ),
				'extra'   => '',
			)
		);

		$this->assertFalse( $response['error'], 'no error: ' . wp_json_encode( $response ) );
		$this->assertSame( array( 'bea@example.org' ), $this->mailed_addresses() );
	}
}
