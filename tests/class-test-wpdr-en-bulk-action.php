<?php
/**
 * Bulk subscribe and unsubscribe on the Users screen.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * WPDR_EN_All_Users_Bulk_Action. The edit_user check (#12) is in class-test-wpdr-en-security.php.
 */
class Test_WPDR_EN_Bulk_Action extends WPDR_EN_UnitTestCase {

	/**
	 * Roles that may choose to receive mail, as set up by admin_init().
	 *
	 * @var string[]
	 */
	private $roles;

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
	 * Act as user 1 (on multisite only a network administrator can edit other users).
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->roles = WPDR_Email_Notice::$internal_roles;
		wp_set_current_user( 1 );
	}

	/**
	 * Restore the roles and the screen.
	 *
	 * @return void
	 */
	public function tear_down() {
		WPDR_Email_Notice::$internal_roles = $this->roles;
		set_current_screen( 'dashboard' );
		parent::tear_down();
	}

	/**
	 * Run a bulk action as the current user.
	 *
	 * @param string $action   Action.
	 * @param int[]  $user_ids Users.
	 * @return string Redirect URL.
	 */
	private static function bulk( $action, $user_ids ) {
		$bulk = new WPDR_EN_All_Users_Bulk_Action();
		return $bulk->custom_bulk_action_handler( 'users.php?paged=2', $action, $user_ids );
	}

	/**
	 * The actions are added to the Users screen's Bulk actions menu.
	 */
	public function test_actions_are_offered() {
		// The filters are only added in the admin.
		set_current_screen( 'users' );
		$bulk    = new WPDR_EN_All_Users_Bulk_Action();
		$actions = $bulk->custom_bulk_action_option( array( 'delete' => 'Delete' ) );

		$this->assertSame( array( 'delete', 'wpdr_subscribe', 'wpdr_unsubscribe' ), array_keys( $actions ) );
		$this->assertNotFalse( has_filter( 'bulk_actions-users', array( $bulk, 'custom_bulk_action_option' ) ) );
		$this->assertNotFalse( has_filter( 'handle_bulk_actions-users', array( $bulk, 'custom_bulk_action_handler' ) ) );
	}

	/**
	 * Subscribe several users; the redirect is kept.
	 */
	public function test_subscribe() {
		$ids = array( self::$users['subscriber'], self::$users['author'] );

		$this->assertSame( 'users.php?paged=2', self::bulk( 'wpdr_subscribe', $ids ) );

		foreach ( $ids as $id ) {
			$this->assertTrue( (bool) get_user_meta( $id, 'wpdr_en_user_notification', true ) );
		}
	}

	/**
	 * Unsubscribing also switches off the attachment.
	 */
	public function test_unsubscribe() {
		$id = self::$users['subscriber'];
		self::subscribe( $id );
		update_user_meta( $id, 'wpdr_en_user_attachment', 1 );

		self::bulk( 'wpdr_unsubscribe', array( (string) $id ) );

		$this->assertFalse( (bool) get_user_meta( $id, 'wpdr_en_user_notification', true ) );
		$this->assertFalse( (bool) get_user_meta( $id, 'wpdr_en_user_attachment', true ) );
	}

	/**
	 * Other actions and an empty selection change nothing.
	 */
	public function test_other_actions_ignored() {
		$id = self::$users['subscriber'];
		self::subscribe( $id, false );

		$this->assertSame( 'users.php?paged=2', self::bulk( 'delete', array( $id ) ) );
		$this->assertSame( 'users.php?paged=2', self::bulk( 'wpdr_subscribe', array() ) );

		$this->assertFalse( (bool) get_user_meta( $id, 'wpdr_en_user_notification', true ) );
	}

	/**
	 * A user whose role cannot receive mail is refused, and any old settings removed.
	 *
	 * @dataProvider data_actions
	 * @param string $action Bulk action.
	 */
	public function test_role_without_choice_is_refused( $action ) {
		$id = self::$users['subscriber'];
		self::subscribe( $id );
		WPDR_Email_Notice::$internal_roles = array( 'administrator' );

		try {
			self::bulk( $action, array( $id ) );
			$this->fail( 'Expected wp_die()' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'Error', $e->getMessage() );
		}

		$this->assertSame( '', get_user_meta( $id, 'wpdr_en_user_notification', true ) );
		$this->assertSame( '', get_user_meta( $id, 'wpdr_en_user_attachment', true ) );
	}

	/**
	 * Both actions.
	 *
	 * @return array[]
	 */
	public static function data_actions() {
		return array(
			'subscribe'   => array( 'wpdr_subscribe' ),
			'unsubscribe' => array( 'wpdr_unsubscribe' ),
		);
	}
}
