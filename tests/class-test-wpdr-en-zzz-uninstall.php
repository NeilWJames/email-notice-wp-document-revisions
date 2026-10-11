<?php
/**
 * Uninstall (uninstall.php).
 *
 * Named zzz so it runs last: uninstall.php declares a function, so it can only be loaded once.
 * The test's transaction rolls back what it deletes.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Deleting the plugin removes its settings, Lists, flags, user settings, capabilities and tables.
 */
class Test_WPDR_EN_ZZZ_Uninstall extends WPDR_EN_UnitTestCase {

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
	 * Reload the role objects after the rollback.
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();
		wp_cache_flush();
		wp_roles()->for_site();
	}

	/**
	 * Everything the plugin stored is removed.
	 */
	public function test_uninstall_removes_everything() {
		global $wpdr_en;
		$list = self::create_list(
			array(
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
				),
			)
		);
		$doc  = self::create_document();
		update_post_meta( $doc, 'wpdr_en_notification_sent', '1' );
		update_post_meta( $doc, 'wpdr_en_ext_notice_sent', '1' );
		update_option( 'wpdr_en_set_subject', 'Subject' );
		update_option( 'wpdr_en_set_log_days', 90 );
		wp_schedule_event( time(), 'daily', 'wpdr_en_purge_logs' );
		self::subscribe( self::$users['subscriber'] );
		$author = new WP_User( self::$users['author'] );
		$author->add_cap( 'read_doc_ext_lists' );
		$wpdr_en->notify( $doc, '' );

		$drops = array();
		add_filter(
			'query',
			static function ( $query ) use ( &$drops ) {
				if ( 0 === stripos( ltrim( $query ), 'DROP' ) ) {
					$drops[] = $query;
				}
				return $query;
			}
		);

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'email-notice-wp-document-revisions/wpdr-email-notice.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertFalse( get_option( 'wpdr_en_set_subject' ), 'settings' );
		$this->assertFalse( get_option( 'wpdr_en_db_version' ), 'version' );
		$this->assertFalse( get_option( 'wpdr_en_set_log_days' ), 'log days' );
		$this->assertFalse( wp_next_scheduled( 'wpdr_en_purge_logs' ), 'daily clean-up' );
		$this->assertNull( get_post( $list ), 'lists' );
		$this->assertSame( '', get_post_meta( $doc, 'wpdr_en_notification_sent', true ), 'internal flag' );
		$this->assertSame( '', get_post_meta( $doc, 'wpdr_en_ext_notice_sent', true ), 'external flag' );
		$this->assertSame( '', get_user_meta( self::$users['subscriber'], 'wpdr_en_user_notification', true ), 'user settings' );
		$this->assertSame( '', get_user_meta( self::$users['subscriber'], 'wpdr_en_user_attachment', true ) );

		wp_roles()->for_site();
		foreach ( array( 'administrator', 'editor' ) as $role ) {
			foreach ( array( 'read_doc_ext_lists', 'edit_doc_ext_lists', 'delete_doc_ext_lists' ) as $cap ) {
				$this->assertFalse( get_role( $role )->has_cap( $cap ), "$role $cap" );
			}
		}
		clean_user_cache( self::$users['author'] );
		$this->assertArrayNotHasKey( 'read_doc_ext_lists', ( new WP_User( self::$users['author'] ) )->caps, 'user capability' );

		$dropped = implode( ' ', $drops );
		foreach ( array( 'wpdr_notification_log', 'wpdr_ext_notice_log', 'wpdr_en_extra_text' ) as $table ) {
			$this->assertStringContainsString( $table, $dropped, $table );
		}
	}
}
