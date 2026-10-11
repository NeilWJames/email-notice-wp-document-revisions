<?php
/**
 * External List address AJAX handlers (security fix #1).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * The wpdr_en_add_address and wpdr_en_del_address actions: only users who can edit the List,
 * whatever user ID the browser sends.
 */
class Test_WPDR_EN_ZZ_Ajax_Address extends WPDR_EN_Ajax_UnitTestCase {

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
	 * A list with one address.
	 *
	 * @return int List ID.
	 */
	private function make_list() {
		return self::create_list(
			array(
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
				),
			)
		);
	}

	/**
	 * An editor (edit_doc_ext_lists) can add an address.
	 */
	public function test_editor_can_add() {
		$list_id = $this->make_list();

		$response = $this->ajax(
			'wpdr_en_add_address',
			self::$users['editor'],
			array(
				'post_id'   => $list_id,
				'user_name' => 'Bob',
				'email'     => 'bob@example.org',
				'pause'     => 0,
			)
		);

		$this->assertFalse( $response['error'], 'no error: ' . wp_json_encode( $response ) );
		$this->assertSame( array( 'ann@example.org', 'bob@example.org' ), wp_list_pluck( self::list_addresses( $list_id ), 'email' ) );
	}

	/**
	 * Sending an existing email updates that entry rather than adding a duplicate.
	 */
	public function test_existing_email_is_updated() {
		$list_id = $this->make_list();

		$this->ajax(
			'wpdr_en_add_address',
			self::$users['editor'],
			array(
				'post_id'   => $list_id,
				'user_name' => 'Ann Other',
				'email'     => 'ann@example.org',
				'pause'     => 1,
			)
		);

		$addresses = self::list_addresses( $list_id );
		$this->assertCount( 1, $addresses );
		$this->assertSame( 'Ann Other', $addresses[0]['user_name'] );
		$this->assertSame( 1, $addresses[0]['pause'] );
	}

	/**
	 * A contributor cannot add an address, even claiming to be the administrator.
	 */
	public function test_contributor_cannot_add_even_with_userid() {
		$list_id = $this->make_list();

		$response = $this->ajax(
			'wpdr_en_add_address',
			self::$users['contributor'],
			array(
				'post_id'   => $list_id,
				'user_name' => 'Mallory',
				'email'     => 'mallory@example.org',
				'pause'     => 0,
				'userid'    => self::$users['administrator'],
			)
		);

		$this->assertSame( 'F-12', $response['error_code'] );
		$this->assertSame( array( 'ann@example.org' ), wp_list_pluck( self::list_addresses( $list_id ), 'email' ) );
		$this->assertSame( self::$users['contributor'], get_current_user_id(), 'current user not switched' );
	}

	/**
	 * Addresses can only be written to Document External Lists.
	 */
	public function test_non_list_is_rejected() {
		$doc = self::create_document();

		$response = $this->ajax(
			'wpdr_en_add_address',
			self::$users['administrator'],
			array(
				'post_id'   => $doc,
				'user_name' => 'Bob',
				'email'     => 'bob@example.org',
				'pause'     => 0,
			)
		);

		$this->assertSame( 'F-13', $response['error_code'] );
		$this->assertSame( '', get_post_meta( $doc, 'wpdr_en_addressees', true ) );
	}

	/**
	 * An invalid email address is rejected with its own message.
	 */
	public function test_invalid_email_is_rejected() {
		$list_id = $this->make_list();

		$response = $this->ajax(
			'wpdr_en_add_address',
			self::$users['editor'],
			array(
				'post_id'   => $list_id,
				'user_name' => 'Bob',
				'email'     => 'not an email',
				'pause'     => 0,
			)
		);

		$this->assertSame( 'F-17', $response['error_code'] );
		$this->assertSame( 'Invalid email address', $response['error_msg'] );
		$this->assertCount( 1, self::list_addresses( $list_id ) );
	}

	/**
	 * An editor can delete an address.
	 */
	public function test_editor_can_delete() {
		$list_id = $this->make_list();

		$response = $this->ajax(
			'wpdr_en_del_address',
			self::$users['editor'],
			array(
				'post_id' => $list_id,
				'del_rec' => 0,
			)
		);

		$this->assertFalse( $response['error'], 'no error: ' . wp_json_encode( $response ) );
		$this->assertCount( 0, self::list_addresses( $list_id ) );
	}

	/**
	 * A contributor cannot delete an address, even claiming to be the administrator.
	 */
	public function test_contributor_cannot_delete_even_with_userid() {
		$list_id = $this->make_list();

		$response = $this->ajax(
			'wpdr_en_del_address',
			self::$users['contributor'],
			array(
				'post_id' => $list_id,
				'del_rec' => 0,
				'userid'  => self::$users['administrator'],
			)
		);

		$this->assertSame( 'F-12', $response['error_code'] );
		$this->assertCount( 1, self::list_addresses( $list_id ) );
	}
}
