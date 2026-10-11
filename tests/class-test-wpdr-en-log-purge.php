<?php
/**
 * Deleting old log entries (4.0.0).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Log entries are deleted a set number of days after their Document was last modified:
 * internal entries by the Settings > Writing value, external ones by each List's value.
 */
class Test_WPDR_EN_Log_Purge extends WPDR_EN_UnitTestCase {

	/**
	 * Tag used to match the External Lists.
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
		self::$tag = $factory->tag->create( array( 'name' => 'EN Purge' ) );
	}

	/**
	 * Clear the setting and the request.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( 'wpdr_en_set_log_days' );
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Set a document's last-modified time to some days ago.
	 *
	 * @param int $doc  Document ID.
	 * @param int $days Days ago.
	 * @return void
	 */
	private static function modified_days_ago( $doc, $days ) {
		global $wpdb;
		$time = time() - $days * DAY_IN_SECONDS;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $time ) ),
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
			),
			array( 'ID' => $doc )
		);
		clean_post_cache( $doc );
	}

	/**
	 * Log rows for a document.
	 *
	 * @param string $table 'wpdr_notification_log' or 'wpdr_ext_notice_log'.
	 * @param int    $doc   Document ID.
	 * @return int
	 */
	private static function rows( $table, $doc ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE post_id = %d", $doc ) );
	}

	/**
	 * A matching List with one address.
	 *
	 * @param int|string $days Log days meta ('' for none).
	 * @return int List ID.
	 */
	private static function make_list( $days ) {
		$list = self::create_list(
			array(
				'terms'     => array( 'post_tag' => self::$tag ),
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann' . wp_rand() . '@example.org',
					),
				),
			)
		);
		if ( '' !== $days ) {
			update_post_meta( $list, 'wpdr_en_log_days', $days );
		}
		return $list;
	}

	/**
	 * Internal entries go after the set days; recent Documents and a 0 setting keep them.
	 */
	public function test_internal_entries() {
		global $wpdr_en;
		self::subscribe( self::$users['subscriber'] );
		$old    = self::create_document();
		$recent = self::create_document();
		$wpdr_en->notify( $old, '' );
		$wpdr_en->notify( $recent, '' );
		self::modified_days_ago( $old, 100 );
		self::modified_days_ago( $recent, 10 );

		$this->assertSame( 0, $wpdr_en->purge_logs()['internal'], 'no setting' );

		update_option( 'wpdr_en_set_log_days', 90 );
		$this->assertSame( 1, $wpdr_en->purge_logs()['internal'] );
		$this->assertSame( 0, self::rows( 'wpdr_notification_log', $old ) );
		$this->assertSame( 1, self::rows( 'wpdr_notification_log', $recent ) );
	}

	/**
	 * External entries follow each List's own days: 90 deletes, 0 or none keeps.
	 */
	public function test_external_entries_per_list() {
		global $wpdr_en;
		$monthly = self::make_list( 90 );
		$zero    = self::make_list( 0 );
		$none    = self::make_list( '' );
		$doc     = self::create_document( array( 'terms' => array( 'post_tag' => self::$tag ) ) );
		$wpdr_en->ext_notice( $doc, array(), '' );
		$this->assertSame( 3, self::rows( 'wpdr_ext_notice_log', $doc ) );

		self::modified_days_ago( $doc, 89 );
		$this->assertSame( 0, $wpdr_en->purge_logs()['external'], 'not yet' );

		self::modified_days_ago( $doc, 91 );
		$this->assertSame( 1, $wpdr_en->purge_logs()['external'] );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$lists = $wpdb->get_col( $wpdb->prepare( "SELECT doc_ext_list_id FROM {$wpdb->prefix}wpdr_ext_notice_log WHERE post_id = %d ORDER BY doc_ext_list_id", $doc ) );
		$this->assertEquals( array( $zero, $none ), $lists );
		$this->assertNotContains( (string) $monthly, $lists );
	}

	/**
	 * Extra text is deleted when no log entry uses it any more.
	 */
	public function test_unused_extra_text_deleted() {
		global $wpdr_en, $wpdb;
		self::subscribe( self::$users['subscriber'] );
		$old    = self::create_document();
		$recent = self::create_document();
		$wpdr_en->notify( $old, 'Old note' );
		$wpdr_en->notify( $recent, 'New note' );
		self::modified_days_ago( $old, 30 );
		update_option( 'wpdr_en_set_log_days', 7 );

		$this->assertSame( 1, $wpdr_en->purge_logs()['extra_text'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$texts = $wpdb->get_col( "SELECT extra_text FROM {$wpdb->prefix}wpdr_en_extra_text" );
		$this->assertSame( array( 'New note' ), $texts );
	}

	/**
	 * The daily job is scheduled on admin_init and runs purge_logs().
	 */
	public function test_daily_job() {
		global $wpdr_en;
		wp_clear_scheduled_hook( 'wpdr_en_purge_logs' );

		$wpdr_en->admin_init();

		$this->assertNotFalse( wp_next_scheduled( 'wpdr_en_purge_logs' ) );
		$this->assertSame( 'daily', wp_get_schedule( 'wpdr_en_purge_logs' ) );
		$this->assertNotFalse( has_action( 'wpdr_en_purge_logs', array( $wpdr_en, 'purge_logs' ) ) );

		wpdr_en_deactivate();
		$this->assertFalse( wp_next_scheduled( 'wpdr_en_purge_logs' ), 'cleared on deactivation' );
	}

	/**
	 * The List's days are saved from the edit form only by users who can delete Lists.
	 */
	public function test_list_days_saved_with_delete_capability() {
		global $wpdr_en;
		$list = self::make_list( '' );
		$save = static function () use ( $list, $wpdr_en ) {
			$_POST = array(
				'_wpnonce'         => wp_create_nonce( 'update-post_' . $list ),
				'wpdr_en_log_days' => '90',
			);
			$wpdr_en->save_doc_ext_list( $list, get_post( $list ), true );
		};

		wp_set_current_user( self::$users['editor'] );
		$save();
		$this->assertSame( '', get_post_meta( $list, 'wpdr_en_log_days', true ), 'editor cannot delete Lists' );

		wp_set_current_user( self::$users['administrator'] );
		$save();
		$this->assertSame( '90', get_post_meta( $list, 'wpdr_en_log_days', true ) );

		$_POST = array(
			'_wpnonce'         => wp_create_nonce( 'update-post_' . $list ),
			'wpdr_en_log_days' => '-5',
		);
		$wpdr_en->save_doc_ext_list( $list, get_post( $list ), true );
		$this->assertSame( '5', get_post_meta( $list, 'wpdr_en_log_days', true ), 'whole positive number' );
	}

	/**
	 * The List attributes box shows the days; for users who cannot delete Lists it is read-only, with the reason.
	 */
	public function test_list_days_field() {
		global $wpdr_en, $post;
		$post = get_post( self::make_list( 30 ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		wp_set_current_user( self::$users['administrator'] );
		ob_start();
		$wpdr_en->del_attrs_meta_box();
		$admin = ob_get_clean();

		wp_set_current_user( self::$users['editor'] );
		ob_start();
		$wpdr_en->del_attrs_meta_box();
		$editor = ob_get_clean();
		$post   = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertMatchesRegularExpression( '/name="wpdr_en_log_days"[^>]*value="30">/', $admin );
		$this->assertMatchesRegularExpression( '/name="wpdr_en_log_days"[^>]*value="30" disabled/', $editor );
		$this->assertStringNotContainsString( 'Only users who can delete Lists', $admin, 'no explanation when it can be changed' );
		$this->assertStringContainsString( 'Only users who can delete Lists can change this.', $editor, 'why it is greyed out' );
	}

	/**
	 * The internal setting is a whole number.
	 */
	public function test_internal_setting_sanitized() {
		update_option( 'wpdr_en_set_log_days', '-30 days' );

		$this->assertSame( 30, get_option( 'wpdr_en_set_log_days' ) );
	}
}
