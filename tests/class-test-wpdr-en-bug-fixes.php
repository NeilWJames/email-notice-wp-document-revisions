<?php
/**
 * Bugs fixed in 4.0.0 (found in the 2026-10 review; until then in the known-bug group).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Settings, automatic sending, logging, repeat text and the user profile.
 */
class Test_WPDR_EN_Bug_Fixes extends WPDR_EN_UnitTestCase {

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
		self::$tag = $factory->tag->create( array( 'name' => 'EN Fixes' ) );
	}

	/**
	 * Clear options and notices set by the tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( 'wpdr_en_set_exttext' );
		delete_option( 'wpdr_en_set_notification_mode' );
		delete_option( 'wpdr_en_set_notification_about' );
		foreach ( self::$users as $user_id ) {
			delete_transient( 'wpdr_en_auto_notice_' . $user_id );
		}
		parent::tear_down();
	}

	/**
	 * Output of a callback.
	 *
	 * @param callable $callback Callback.
	 * @param array    $args     Arguments.
	 * @return string
	 */
	private static function output( $callback, $args = array() ) {
		ob_start();
		call_user_func_array( $callback, $args );
		return ob_get_clean();
	}

	/**
	 * Store an option value as older versions did, without the 4.0.0 sanitizing.
	 *
	 * @param string $option Option name.
	 * @param string $value  Raw value.
	 * @return void
	 */
	private static function raw_option( $option, $value ) {
		global $wpdb;
		delete_option( $option );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => $option,
				'option_value' => $value,
				'autoload'     => 'yes',
			)
		);
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * Saving "Auto" mode keeps the value (it was sanitized with intval, so became 0); anything else is Manual.
	 */
	public function test_notification_mode_auto_is_saved() {
		update_option( 'wpdr_en_set_notification_mode', 'Auto' );
		$this->assertSame( 'Auto', get_option( 'wpdr_en_set_notification_mode' ) );

		update_option( 'wpdr_en_set_notification_mode', 'Sometimes' );
		$this->assertSame( 'Manual', get_option( 'wpdr_en_set_notification_mode' ) );
	}

	/**
	 * The mode radio shows the saved value; the 0 saved by older versions shows as Manual.
	 */
	public function test_notification_mode_field() {
		global $wpdr_en;
		update_option( 'wpdr_en_set_notification_mode', 'Auto' );
		$this->assertMatchesRegularExpression( '/value="Auto"\s+checked/', self::output( array( $wpdr_en, 'set_notification_mode' ) ) );

		// As saved by 3.2 to 3.3.
		self::raw_option( 'wpdr_en_set_notification_mode', '0' );
		$this->assertMatchesRegularExpression( '/value="Manual"\s+checked/', self::output( array( $wpdr_en, 'set_notification_mode' ) ) );
	}

	/**
	 * The "Notify internal users about" checkboxes are saved (the array was sanitized as text, so lost).
	 */
	public function test_notification_about_is_saved() {
		$value = array(
			'Chkbx_Public'  => 'Public',
			'Chkbx_Private' => 'Private',
		);
		update_option( 'wpdr_en_set_notification_about', $value );
		$this->assertSame( $value, get_option( 'wpdr_en_set_notification_about' ) );

		update_option(
			'wpdr_en_set_notification_about',
			array(
				'Chkbx_Password' => 'x',
				'Other'          => 'y',
			)
		);
		$this->assertSame( array( 'Chkbx_Password' => 'Password' ), get_option( 'wpdr_en_set_notification_about' ), 'only known keys' );

		// No box ticked submits nothing.
		update_option( 'wpdr_en_set_notification_about', null );
		$this->assertSame( array(), get_option( 'wpdr_en_set_notification_about' ) );
	}

	/**
	 * Until saved as a list, every box shows ticked (that is what was sent); then the saved ones.
	 */
	public function test_notification_about_field() {
		global $wpdr_en;
		$html = self::output( array( $wpdr_en, 'set_notification_about' ) );
		$this->assertSame( 3, substr_count( $html, "checked='checked'" ) );

		update_option( 'wpdr_en_set_notification_about', array( 'Chkbx_Private' => 'Private' ) );
		$html = self::output( array( $wpdr_en, 'set_notification_about' ) );
		$this->assertSame( 1, substr_count( $html, "checked='checked'" ) );
		$this->assertMatchesRegularExpression( '/value="Private"\s+checked/', $html );
	}

	/**
	 * Internal users are only told about the kinds of documents chosen.
	 */
	public function test_notification_about_is_applied() {
		global $wpdr_en;
		self::subscribe( self::$users['editor'] );
		$public  = self::create_document();
		$private = self::create_document( array( 'post_status' => 'private' ) );

		update_option( 'wpdr_en_set_notification_about', array( 'Chkbx_Private' => 'Private' ) );
		$wpdr_en->notify( $public, '' );
		$this->assertCount( 0, $this->mails, 'public not chosen' );
		$wpdr_en->notify( $private, '' );
		$this->assertCount( 1, $this->mails, 'private chosen' );

		// The '' saved by older versions means all.
		self::raw_option( 'wpdr_en_set_notification_about', '' );
		$wpdr_en->notify( $public, '' );
		$this->assertCount( 2, $this->mails );
	}

	/**
	 * Auto mode sends when a document is published, once, and leaves a notice for the publisher only.
	 */
	public function test_auto_mode_sends_once_on_publish() {
		global $wpdr_en;
		update_option( 'wpdr_en_set_notification_mode', 'Auto' );
		self::subscribe( self::$users['subscriber'] );
		wp_set_current_user( self::$users['editor'] );

		$doc = self::create_document();
		$this->assertSame( array( 'subscriber@example.com' ), $this->mailed_addresses() );
		$this->assertSame( '1', get_post_meta( $doc, 'wpdr_en_notification_sent', true ) );

		wp_update_post(
			array(
				'ID'         => $doc,
				'post_title' => 'Changed',
			)
		);
		$this->assertCount( 1, $this->mails, 'not sent again' );

		$html = self::output( array( $wpdr_en, 'admin_notice_auto_notification' ) );
		$this->assertStringContainsString( 'notice-success', $html );
		$this->assertStringContainsString( '1 notification(s) sent.', $html );
		$this->assertStringContainsString( '<a href="', $html, 'log link is a link' );
		$this->assertSame( '', self::output( array( $wpdr_en, 'admin_notice_auto_notification' ) ), 'shown once' );

		wp_set_current_user( self::$users['author'] );
		self::create_document();
		wp_set_current_user( self::$users['editor'] );
		$this->assertSame( '', self::output( array( $wpdr_en, 'admin_notice_auto_notification' ) ), 'another user\'s notice' );
	}

	/**
	 * Manual mode and drafts send nothing automatically.
	 */
	public function test_auto_mode_needs_auto_and_published() {
		self::subscribe( self::$users['subscriber'] );

		self::create_document();
		update_option( 'wpdr_en_set_notification_mode', 'Auto' );
		self::create_document( array( 'post_status' => 'draft' ) );

		$this->assertCount( 0, $this->mails );
	}

	/**
	 * The internal log records the send status (the insert had no status column).
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
	 * Extra text sent to internal users is stored for the log (insert_extra() was called without the post ID).
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
	 * A repeat internal send includes the %repeat% text.
	 */
	public function test_internal_repeat_text() {
		global $wpdr_en;
		self::subscribe( self::$users['subscriber'] );
		$doc = self::create_document();

		$wpdr_en->notify( $doc, '' );
		$wpdr_en->notify( $doc, '' );

		$this->assertStringNotContainsString( 'previously been sent', $this->mails[0]['message'] );
		$this->assertStringContainsString( 'previously been sent to you 1 time', $this->mails[1]['message'] );
	}

	/**
	 * A repeat external send includes the %repeat% text (the lookup compared the email with %d).
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
	 * Creating a user outside the profile form does not die (save_user_profile() used check_admin_referer()).
	 */
	public function test_user_register_does_not_die() {
		global $wpdr_en;
		$this->assertNotFalse( has_action( 'user_register', array( $wpdr_en, 'save_user_profile' ) ) );
		wp_set_current_user( 1 );

		$user_id = wp_insert_user(
			array(
				'user_login' => 'en_new_user',
				'user_pass'  => wp_generate_password(),
				'user_email' => 'new@example.com',
			)
		);

		$this->assertIsInt( $user_id );
		$this->assertSame( '', get_user_meta( $user_id, 'wpdr_en_user_notification', true ), 'nothing saved' );
	}

	/**
	 * The profile form saves both settings; without its nonce nothing changes.
	 */
	public function test_profile_save() {
		global $wpdr_en;
		$id = self::$users['subscriber'];
		self::subscribe( $id, false );
		wp_set_current_user( 1 );

		$_POST = array(
			'wpdr_en_user_notification' => '1',
			'wpdr_en_user_attachment'   => '1',
		);
		$wpdr_en->save_user_profile( $id );
		$this->assertSame( '0', get_user_meta( $id, 'wpdr_en_user_notification', true ), 'no nonce' );

		$_POST['_wpnonce'] = wp_create_nonce( 'update-user_' . $id );
		$wpdr_en->save_user_profile( $id );
		$this->assertSame( '1', get_user_meta( $id, 'wpdr_en_user_notification', true ) );
		$this->assertSame( '1', get_user_meta( $id, 'wpdr_en_user_attachment', true ) );

		// Unticked boxes are not sent.
		$_POST = array( '_wpnonce_create-user' => wp_create_nonce( 'create-user' ) );
		$wpdr_en->save_user_profile( $id );
		$this->assertSame( '0', get_user_meta( $id, 'wpdr_en_user_notification', true ) );
		$this->assertSame( '0', get_user_meta( $id, 'wpdr_en_user_attachment', true ) );
	}

	/**
	 * Users cannot change another user's settings; roles without the choice lose them.
	 */
	public function test_profile_save_limits() {
		global $wpdr_en;
		$id = self::$users['subscriber'];
		self::subscribe( $id );

		wp_set_current_user( self::$users['author'] );
		$_POST = array( '_wpnonce' => wp_create_nonce( 'update-user_' . $id ) );
		$wpdr_en->save_user_profile( $id );
		$this->assertSame( '1', get_user_meta( $id, 'wpdr_en_user_notification', true ), 'author cannot edit the subscriber' );

		$roles                             = WPDR_Email_Notice::$internal_roles;
		WPDR_Email_Notice::$internal_roles = array( 'administrator' );
		wp_set_current_user( 1 );
		$_POST = array( '_wpnonce' => wp_create_nonce( 'update-user_' . $id ) );
		$wpdr_en->save_user_profile( $id );
		WPDR_Email_Notice::$internal_roles = $roles;
		$this->assertSame( '', get_user_meta( $id, 'wpdr_en_user_notification', true ), 'removed' );
	}

	/**
	 * The profile shows the user's settings; Add New User shows them unticked; nothing for adding an
	 * existing user to a network site or for roles without the choice.
	 */
	public function test_profile_fields() {
		global $wpdr_en;
		$user = get_user_by( 'id', self::$users['subscriber'] );
		self::subscribe( $user->ID );

		$html = self::output( array( $wpdr_en, 'user_profile' ), array( $user ) );
		$this->assertStringContainsString( 'name="wpdr_en_user_notification"', $html );
		$this->assertSame( 1, substr_count( $html, "checked='checked'" ), 'notify ticked, attach not' );
		$this->assertStringNotContainsString( '&#039;', $html, 'checked attribute not escaped' );

		$html = self::output( array( $wpdr_en, 'user_profile' ), array( 'add-new-user' ) );
		$this->assertStringContainsString( 'name="wpdr_en_user_attachment"', $html );
		$this->assertStringNotContainsString( 'checked', $html );
		$this->assertSame( '', self::output( array( $wpdr_en, 'user_profile' ), array( 'add-existing-user' ) ) );

		$roles                             = WPDR_Email_Notice::$internal_roles;
		WPDR_Email_Notice::$internal_roles = array( 'administrator' );
		$html                              = self::output( array( $wpdr_en, 'user_profile' ), array( $user ) );
		WPDR_Email_Notice::$internal_roles = $roles;
		$this->assertSame( '', $html );
	}

	/**
	 * The labels say "me" on the user's own profile and "this user" when adding or editing someone else.
	 */
	public function test_profile_labels() {
		global $wpdr_en;
		$user = get_user_by( 'id', self::$users['subscriber'] );

		wp_set_current_user( $user->ID );
		$own = self::output( array( $wpdr_en, 'user_profile' ), array( $user ) );
		$this->assertStringContainsString( 'Notify me by email', $own );
		$this->assertStringContainsString( 'Also send me the document', $own );

		wp_set_current_user( 1 );
		$other = self::output( array( $wpdr_en, 'user_profile' ), array( $user ) );
		$this->assertStringContainsString( 'Notify this user by email', $other );
		$this->assertStringContainsString( 'Also send this user the document', $other );

		$this->assertStringContainsString( 'Notify this user by email', self::output( array( $wpdr_en, 'user_profile' ), array( 'add-new-user' ) ) );
	}

	/**
	 * A user added on the Add New User screen gets the settings ticked there.
	 */
	public function test_add_new_user_saves_settings() {
		wp_set_current_user( 1 );
		$_POST = array(
			'_wpnonce_create-user'      => wp_create_nonce( 'create-user' ),
			'wpdr_en_user_notification' => '1',
		);

		$user_id = wp_insert_user(
			array(
				'user_login' => 'en_added_user',
				'user_pass'  => wp_generate_password(),
				'user_email' => 'added@example.com',
				'role'       => 'author',
			)
		);

		$this->assertSame( '1', get_user_meta( $user_id, 'wpdr_en_user_notification', true ) );
		$this->assertSame( '0', get_user_meta( $user_id, 'wpdr_en_user_attachment', true ) );
	}

	/**
	 * The Users screen column shows both settings as disabled checkboxes.
	 */
	public function test_users_column() {
		global $wpdr_en;
		$id = self::$users['subscriber'];
		self::subscribe( $id );

		$this->assertArrayHasKey( 'wpdr_en_notification', $wpdr_en->all_users_column_register( array() ) );
		$this->assertSame( 'kept', $wpdr_en->all_users_column_rows( 'kept', 'other', $id ) );

		$html = $wpdr_en->all_users_column_rows( '', 'wpdr_en_notification', $id );
		$this->assertSame( 2, substr_count( $html, 'disabled' ) );
		$this->assertSame( 1, substr_count( $html, 'checked="checked"' ) );
	}
}
