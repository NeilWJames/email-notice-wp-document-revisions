<?php
/**
 * Known bugs, written as the behaviour we want.
 *
 * Excluded from normal runs (phpunit.xml.dist). Run with: phpunit --group known-bug
 * When a bug is fixed, remove its @group known-bug tag so it joins the normal run.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Bugs found in the 2026-10 review (see work/current.md).
 *
 * @group known-bug
 */
class Test_WPDR_EN_Known_Bugs extends WPDR_EN_UnitTestCase {

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
		self::$tag = $factory->tag->create( array( 'name' => 'EN Bugs' ) );
	}

	/**
	 * Clear options set by the tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( 'wpdr_en_set_exttext' );
		delete_option( 'wpdr_en_set_notification_mode' );
		delete_option( 'wpdr_en_set_notification_about' );
		parent::tear_down();
	}

	/**
	 * Saving "Auto" mode keeps the value (it is sanitized with intval, so becomes 0).
	 */
	public function test_notification_mode_auto_is_saved() {
		update_option( 'wpdr_en_set_notification_mode', 'Auto' );

		$this->assertSame( 'Auto', get_option( 'wpdr_en_set_notification_mode' ) );
	}

	/**
	 * The "Notify internal users about" checkboxes are saved (array sanitized as text, so lost).
	 */
	public function test_notification_about_is_saved() {
		$value = array(
			'Chkbx_Public'  => 'Public',
			'Chkbx_Private' => 'Private',
		);
		update_option( 'wpdr_en_set_notification_about', $value );

		$this->assertSame( $value, get_option( 'wpdr_en_set_notification_about' ) );
	}

	/**
	 * The internal log records the send status (the insert has no status column).
	 */
	public function test_internal_log_has_status() {
		global $wpdr_en, $wpdb;
		self::subscribe( self::$users['subscriber'] );
		$doc = self::create_document();

		$wpdr_en->notify( $doc, '' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}wpdr_notification_log WHERE post_id = %d", $doc ) );
		$this->assertSame( 'Successful', $status );
	}

	/**
	 * Extra text sent to internal users is stored for the log (insert_extra() is called without the post ID).
	 */
	public function test_internal_extra_text_is_stored() {
		global $wpdr_en, $wpdb;
		self::subscribe( self::$users['subscriber'] );
		$doc = self::create_document();

		$wpdr_en->notify( $doc, 'Please review' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$text = $wpdb->get_var( $wpdb->prepare( "SELECT extra_text FROM {$wpdb->prefix}wpdr_en_extra_text WHERE post_id = %d", $doc ) );
		$this->assertSame( 'Please review', $text );
	}

	/**
	 * A repeat external send includes the %repeat% text (the lookup compares the email with %d).
	 */
	public function test_external_repeat_text() {
		global $wpdr_en;
		update_option( 'wpdr_en_set_exttext', 'Body %repeat%' );
		$terms = array( 'post_tag' => self::$tag );
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
		$doc = self::create_document( array( 'terms' => $terms ) );

		$wpdr_en->ext_notice( $doc, array(), '' );
		$wpdr_en->ext_notice( $doc, array(), '' );

		$this->assertCount( 2, $this->mails );
		$this->assertStringContainsString( 'previously been sent to you 1 time', $this->mails[1]['message'] );
	}

	/**
	 * Creating a user outside the profile form does not die (save_user_profile() uses check_admin_referer()).
	 */
	public function test_user_register_does_not_die() {
		global $wpdr_en;
		add_action( 'user_register', array( $wpdr_en, 'save_user_profile' ) );

		$user_id = wp_insert_user(
			array(
				'user_login' => 'en_new_user',
				'user_pass'  => wp_generate_password(),
				'user_email' => 'new@example.com',
			)
		);

		$this->assertIsInt( $user_id );
	}
}
