<?php
/**
 * Admin screens: settings fields, log pages, metaboxes, help, messages, notices and log clean-up.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Smoke tests: each screen renders without PHP notices (PHPUnit turns them into errors) and
 * escapes what it shows; plus the log clean-up when a document or user is deleted.
 */
class Test_WPDR_EN_Admin_Pages extends WPDR_EN_UnitTestCase {

	/**
	 * A value that must never reach the page as markup.
	 */
	const HOSTILE = '"><script>alert(1)</script>';

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
		self::$tag = $factory->tag->create( array( 'name' => 'EN Pages' ) );
	}

	/**
	 * Act as an editor (who can read and edit lists).
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$users['editor'] );
		// On a site the List has the Document taxonomies; tags stand in for them here.
		register_taxonomy_for_object_type( 'post_tag', 'doc_ext_list' );
	}

	/**
	 * Restore the screen, request and globals.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $post;
		$post = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		unset( $GLOBALS['hook_suffix'], $_GET['page'] );
		set_current_screen( 'dashboard' );
		wp_dequeue_script( 'wpdr_en_log' );
		delete_option( 'wpdr_en_set_content' );
		delete_option( 'wpdr_en_set_exttext' );
		unregister_taxonomy_for_object_type( 'post_tag', 'doc_ext_list' );
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
	 * Make a post the current post, as on its edit screen.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function edit_screen( $post_id ) {
		global $post;
		$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		set_current_screen( $post->post_type );
	}

	/**
	 * A matching list (with one address) and a public document.
	 *
	 * @return int[] Document ID, List ID.
	 */
	private static function document_and_list() {
		$terms = array( 'post_tag' => self::$tag );
		$list  = self::create_list(
			array(
				'title'     => 'Pages List',
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
				),
			)
		);
		return array( self::create_document( array( 'terms' => $terms ) ), $list );
	}

	/**
	 * Settings field callbacks and the option each shows.
	 *
	 * @return array[]
	 */
	public static function data_settings_fields() {
		return array(
			'general intro'   => array( 'general_settings', '' ),
			'from name'       => array( 'set_email_from', 'wpdr_en_set_email_from' ),
			'from address'    => array( 'set_email_from_address', 'wpdr_en_set_email_from_address' ),
			'writing intro'   => array( 'writing_settings', '' ),
			'mode'            => array( 'set_notification_mode', 'wpdr_en_set_notification_mode' ),
			'notify about'    => array( 'set_notification_about', 'wpdr_en_set_notification_about' ),
			'subject'         => array( 'set_subject', 'wpdr_en_set_subject' ),
			'internal text'   => array( 'set_content', 'wpdr_en_set_content' ),
			'external text'   => array( 'set_exttext', 'wpdr_en_set_exttext' ),
			'external attach' => array( 'set_ext_attach', 'wpdr_en_set_ext_attach' ),
			'repeat text'     => array( 'set_repeat', 'wpdr_en_set_repeat' ),
			'log days'        => array( 'set_log_days', 'wpdr_en_set_log_days' ),
		);
	}

	/**
	 * Each settings field renders, and a stored value cannot add markup.
	 *
	 * @dataProvider data_settings_fields
	 * @param string $method Field callback.
	 * @param string $option Option it shows ('' for none).
	 */
	public function test_settings_field_renders( $method, $option ) {
		global $wpdr_en;
		if ( '' !== $option ) {
			// Bypasses the sanitizing, as a value stored by an older version or another plugin would.
			add_filter(
				'pre_option_' . $option,
				static function () {
					return self::HOSTILE;
				}
			);
		}

		$html = self::output( array( $wpdr_en, $method ) );

		$this->assertNotSame( '', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * The settings, sections and fields are registered on the General and Writing screens.
	 */
	public function test_settings_registered() {
		global $wp_registered_settings, $wp_settings_fields;

		foreach ( array( 'email_from', 'email_from_address', 'notification_mode', 'notification_about', 'subject', 'content', 'exttext', 'ext_attach', 'repeat', 'log_days' ) as $name ) {
			$this->assertArrayHasKey( 'wpdr_en_set_' . $name, $wp_registered_settings );
		}
		$this->assertCount( 2, $wp_settings_fields['general']['wpdr_en_general_settings'] );
		$this->assertCount( 8, $wp_settings_fields['writing']['wpdr_en_writing_settings'] );
	}

	/**
	 * Both log pages render their table, search box and script.
	 */
	public function test_log_pages_render() {
		global $wpdr_en;
		list( $doc ) = self::document_and_list();
		self::subscribe( self::$users['subscriber'] );
		$wpdr_en->notify( $doc, '' );
		$wpdr_en->ext_notice( $doc, array(), '' );

		$pages = array(
			'notification_log_list' => array( 'wpdr_en_notification_log', 'subscriber@example.com' ),
			'ext_notice_log_list'   => array( 'wpdr_en_ext_notice_log', 'ann@example.org' ),
		);
		foreach ( $pages as $method => $expect ) {
			$GLOBALS['hook_suffix'] = 'doc_ext_list_page_' . $expect[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			set_current_screen( $GLOBALS['hook_suffix'] );

			$html = self::output( array( $wpdr_en, $method ) );

			$this->assertStringContainsString( 'name="page" value="' . $expect[0] . '"', $html, $method );
			$this->assertStringContainsString( 'id="search_id-search-input"', $html, $method );
			$this->assertStringContainsString( 'wp-list-table', $html, $method );
			$this->assertStringContainsString( $expect[1], $html, $method );
		}
		$this->assertTrue( wp_script_is( 'wpdr_en_log', 'enqueued' ) );
	}

	/**
	 * The log column style is only added on the log pages.
	 */
	public function test_admin_head_style() {
		global $wpdr_en;
		$this->assertSame( '', self::output( array( $wpdr_en, 'admin_head' ) ) );

		$_GET['page'] = 'wpdr_en_ext_notice_log';
		$this->assertStringContainsString( '.column-id', self::output( array( $wpdr_en, 'admin_head' ) ) );
	}

	/**
	 * Document metabox: buttons follow the document and what has been sent.
	 */
	public function test_document_metabox_states() {
		global $wpdr_en;
		list( $doc ) = self::document_and_list();
		self::subscribe( self::$users['subscriber'] );

		self::edit_screen( $doc );
		$html = self::output( array( $wpdr_en, 'add_metabox' ) );
		$this->assertMatchesRegularExpression( '/id="wpdr-en-notify"[^>]*value="Send notification email\(s\)"\/>/', $html, 'send enabled' );
		$this->assertStringContainsString( 'value="Send external list email(s)"/>', $html );
		$this->assertStringContainsString( 'name="wpdr-en-extra"', $html, 'extra text box (default templates have %extra%)' );

		update_post_meta( $doc, 'wpdr_en_notification_sent', '1' );
		update_post_meta( $doc, 'wpdr_en_ext_notice_sent', '1' );
		$html = self::output( array( $wpdr_en, 'add_metabox' ) );
		$this->assertStringContainsString( 'value="Re-send notification email(s)" />', $html );
		$this->assertStringContainsString( 'value="Re-send external list email(s)" />', $html );

		$draft = self::create_document( array( 'post_status' => 'draft' ) );
		self::edit_screen( $draft );
		$html = self::output( array( $wpdr_en, 'add_metabox' ) );
		$this->assertStringContainsString( 'value="Send notification email(s)" disabled/>', $html );
		$this->assertStringContainsString( 'value="Send external list email(s)" disabled/>', $html );
		wp_reset_postdata();
	}

	/**
	 * Without %extra% in either template there is no extra text box.
	 */
	public function test_document_metabox_without_extra() {
		global $wpdr_en;
		update_option( 'wpdr_en_set_content', 'Hello %title%' );
		update_option( 'wpdr_en_set_exttext', 'Hi %title%' );

		self::edit_screen( self::create_document() );
		$html = self::output( array( $wpdr_en, 'add_metabox' ) );
		wp_reset_postdata();

		$this->assertStringContainsString( 'Optional Extra text not configured in templates', $html );
		$this->assertStringNotContainsString( 'name="wpdr-en-extra"', $html );
	}

	/**
	 * The metabox is added to Documents (but not to PublishPress Revisions copies).
	 */
	public function test_document_metabox_added() {
		global $wpdr_en, $wp_meta_boxes;
		$doc = self::create_document();

		self::edit_screen( $doc );
		$wpdr_en->add_metabox_head();
		$this->assertArrayHasKey( 'wpdr_en_sectionid', $wp_meta_boxes['document']['side']['high'] );

		unset( $wp_meta_boxes['document'] );
		update_post_meta( $doc, '_rvy_base_post_id', 1 );
		$wpdr_en->add_metabox_head();
		wp_reset_postdata();
		$this->assertArrayNotHasKey( 'document', (array) $wp_meta_boxes );
	}

	/**
	 * List edit screen: the two metaboxes are added and render the settings and addresses.
	 */
	public function test_list_metaboxes() {
		global $wpdr_en, $wp_meta_boxes;
		list( , $list ) = self::document_and_list();
		wp_update_post(
			array(
				'ID'           => $list,
				'post_excerpt' => '</textarea>' . self::HOSTILE,
			)
		);

		self::edit_screen( $list );
		$wpdr_en->meta_box_cb();
		$this->assertArrayHasKey( 'del_parms', $wp_meta_boxes['doc_ext_list']['normal']['high'] );
		$this->assertArrayHasKey( 'doc_ext_list', $wp_meta_boxes['doc_ext_list']['normal']['high'] );

		$attrs = self::output( array( $wpdr_en, 'del_attrs_meta_box' ) );
		$this->assertStringContainsString( 'name="tm_rule"', $attrs );
		$this->assertSame( 1, substr_count( $attrs, '</textarea>' ), 'notes cannot close the textarea' );
		$this->assertStringNotContainsString( '<script>', $attrs );
		$this->assertStringContainsString( 'name="tax_save[post_tag][]" value="' . self::$tag . '"', $attrs, 'saved terms kept for comparison' );

		$users = self::output( array( $wpdr_en, 'doc_ext_list_metabox' ) );
		wp_reset_postdata();
		$this->assertStringContainsString( 'id="wpdr-en-email"', $users );
		$this->assertStringContainsString( 'ann@example.org', $users );
	}

	/**
	 * The List metaboxes refuse users who cannot edit lists.
	 *
	 * @dataProvider data_list_metaboxes
	 * @param string $method Metabox callback.
	 */
	public function test_list_metaboxes_need_edit_capability( $method ) {
		global $wpdr_en;
		self::edit_screen( self::create_list() );
		wp_set_current_user( self::$users['author'] );

		$this->expectException( 'WPDieException' );
		$wpdr_en->$method();
	}

	/**
	 * The List metaboxes.
	 *
	 * @return array[]
	 */
	public static function data_list_metaboxes() {
		return array(
			'attributes' => array( 'del_attrs_meta_box' ),
			'addresses'  => array( 'doc_ext_list_metabox' ),
		);
	}

	/**
	 * Help tabs on the List, List table and Document screens only; the text can be filtered.
	 */
	public function test_help_tabs() {
		global $wpdr_en;
		$counts = array(
			'doc_ext_list'      => 6,
			'edit-doc_ext_list' => 1,
			'document'          => 2,
			'post'              => 0,
		);
		foreach ( $counts as $id => $count ) {
			set_current_screen( $id );
			get_current_screen()->remove_help_tabs();
			$wpdr_en->add_help_tab();
			$this->assertCount( $count, get_current_screen()->get_help_tabs(), $id );
		}

		add_filter(
			'wpdr_en_help_array',
			static function () {
				return array( 'Only' => '<p>One</p>' );
			}
		);
		set_current_screen( 'document' );
		$this->assertSame( array( 'Only' => '<p>One</p>' ), $wpdr_en->get_help_text() );
	}

	/**
	 * List update messages.
	 */
	public function test_update_messages() {
		global $wpdr_en, $post;
		$post = get_post( self::create_list() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$messages = $wpdr_en->update_messages( array( 'post' => array() ) );

		$this->assertArrayHasKey( 'post', $messages );
		$this->assertCount( 10, $messages['doc_ext_list'] );
		$this->assertSame( 'Document External List published.', $messages['doc_ext_list'][6] );
		$this->assertFalse( $messages['doc_ext_list'][5], 'no revision' );
	}

	/**
	 * The List error notice: shown for a list with no terms or addresses, hidden for a complete one.
	 */
	public function test_list_error_notice() {
		global $wpdr_en;
		self::edit_screen( self::create_list( array( 'status' => 'draft' ) ) );
		$html = self::output( array( $wpdr_en, 'check_error_state' ) );
		$this->assertStringContainsString( 'notice-warning', $html, 'draft: warning' );
		$this->assertStringContainsString( 'no terms defined', $html );
		$this->assertStringContainsString( 'no addressees defined', $html );
		$this->assertStringNotContainsString( 'display:none', $html );

		list( , $list ) = self::document_and_list();
		self::edit_screen( $list );
		$html = self::output( array( $wpdr_en, 'check_error_state' ) );
		wp_reset_postdata();
		$this->assertStringContainsString( 'style="display:none"', $html, 'complete list: hidden' );

		set_current_screen( 'document' );
		$this->assertSame( '', self::output( array( $wpdr_en, 'check_error_state' ) ), 'other screens' );
	}

	/**
	 * Deleting a document removes its log rows; deleting a user removes theirs.
	 */
	public function test_log_rows_removed_on_delete() {
		global $wpdr_en, $wpdb;
		list( $doc ) = self::document_and_list();
		$user        = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::subscribe( $user );
		$wpdr_en->notify( $doc, '' );
		$wpdr_en->ext_notice( $doc, array(), '' );
		$doc2 = self::create_document();
		$wpdr_en->notify( $doc2, '' );

		$count = static function ( $table, $column, $id ) use ( $wpdb ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE {$column} = %d", $id ) );
		};
		$this->assertSame( 1, $count( 'wpdr_notification_log', 'post_id', $doc ) );
		$this->assertSame( 1, $count( 'wpdr_ext_notice_log', 'post_id', $doc ) );

		wp_delete_post( $doc, true );
		$this->assertSame( 0, $count( 'wpdr_notification_log', 'post_id', $doc ) );
		$this->assertSame( 0, $count( 'wpdr_ext_notice_log', 'post_id', $doc ) );
		$this->assertSame( 1, $count( 'wpdr_notification_log', 'user_id', $user ), 'other document kept' );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user );
		$this->assertSame( 0, $count( 'wpdr_notification_log', 'user_id', $user ) );
	}
}
