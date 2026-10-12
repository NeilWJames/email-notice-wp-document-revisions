<?php
/**
 * The playground demo content (playground/demo-content.php).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * The demo loads against the current plugin code and gives the site it describes.
 */
class Test_WPDR_EN_Playground_Demo extends WPDR_EN_UnitTestCase {

	/**
	 * Remove the demo taxonomy and reload the roles (the demo adds users and terms).
	 *
	 * @return void
	 */
	public function tear_down() {
		unregister_taxonomy( 'department' );
		parent::tear_down();
		wp_roles()->for_site();
	}

	/**
	 * Load the demo (without installing its mu-plugin).
	 *
	 * @return void
	 */
	private static function load_demo() {
		require_once dirname( __DIR__ ) . '/playground/demo-content.php';
		wpdr_en_demo_load( false );
	}

	/**
	 * Count rows of a plugin table.
	 *
	 * @param string $table Table name without prefix.
	 * @param string $where SQL condition.
	 * @return int
	 */
	private static function count_rows( $table, $where = '1=1' ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE {$where}" );
	}

	/**
	 * Users, Documents, Lists and settings are created.
	 */
	public function test_site_content() {
		set_transient( 'wpdr_activation_issue', 1 );
		self::load_demo();

		foreach ( array( 'editor', 'author', 'contributor', 'subscriber', 'subscriber2' ) as $login ) {
			$this->assertInstanceOf( 'WP_User', get_user_by( 'login', $login ), $login );
		}
		$this->assertTrue( wp_check_password( 'password', get_user_by( 'login', 'editor' )->user_pass ) );
		$this->assertSame(
			7,
			count(
				get_posts(
					array(
						'post_type'   => 'document',
						'post_status' => 'any',
						'numberposts' => -1,
					)
				)
			)
		);
		$this->assertSame(
			4,
			count(
				get_posts(
					array(
						'post_type'   => 'doc_ext_list',
						'numberposts' => -1,
					)
				)
			)
		);
		$this->assertSame( 'Document Centre', get_option( 'wpdr_en_set_email_from' ) );
		$this->assertNotFalse( get_term_by( 'name', 'Payroll', 'department' ) );
		$this->assertNull( get_page_by_path( 'hello-world', OBJECT, 'post' ), 'sample post removed' );
		$this->assertFalse( get_transient( 'wpdr_activation_issue' ), 'no false WP Document Revisions warning' );
	}

	/**
	 * The notifications sent fill both logs as the demo describes.
	 */
	public function test_logs() {
		self::load_demo();

		// Internal: budget twice and payroll to admin, editor, Sam and Sue (4 each); the private handbook to admin and editor.
		$this->assertSame( 14, self::count_rows( 'wpdr_notification_log' ) );
		// The extra text, once for the internal and once for the external mailing.
		$this->assertSame( 2, self::count_rows( 'wpdr_en_extra_text', "extra_text LIKE '%Approved by the board%'" ) );

		// External: budget and payroll to Alice (Bob paused); the policy to Alice, Carol, Dan and Supplies Ltd. Newsletter paused.
		$this->assertSame( 6, self::count_rows( 'wpdr_ext_notice_log' ) );
		$this->assertSame( 0, self::count_rows( 'wpdr_ext_notice_log', "user_email IN ( 'bob@audit.example', 'news@club.example' )" ) );
		$this->assertSame( 3, self::count_rows( 'wpdr_ext_notice_log', "user_email = 'alice@audit.example'" ) );

		// Sam asked for attachments, and the documents have files.
		$this->assertSame( 3, self::count_rows( 'wpdr_notification_log', "user_email = 'subscriber@example.com' AND status = 'Success Attachment'" ) );

		// The second budget mail says it was sent before.
		$repeat = array_filter(
			$this->mails,
			static function ( $mail ) {
				return false !== strpos( $mail['message'], 'previously been sent to you' );
			}
		);
		$this->assertNotEmpty( $repeat );
	}
}
