<?php
/**
 * The read_doc_ext_lists capability (4.0.0).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Default capabilities, the 4.0 upgrade, and what read_doc_ext_lists controls.
 */
class Test_WPDR_EN_Capabilities extends WPDR_EN_UnitTestCase {

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
		self::$tag = $factory->tag->create( array( 'name' => 'EN Caps' ) );
	}

	/**
	 * Role changes are rolled back with the test's transaction; reload the role objects to match.
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();
		wp_cache_flush();
		wp_roles()->for_site();
	}

	/**
	 * Whether a user (by role key in self::$users) has a capability, freshly loaded.
	 *
	 * @param string $who Role key.
	 * @param string $cap Capability.
	 * @return bool
	 */
	private static function user_can_cap( $who, $cap ) {
		clean_user_cache( self::$users[ $who ] );
		return ( new WP_User( self::$users[ $who ] ) )->has_cap( $cap );
	}

	/**
	 * New installs give read_doc_ext_lists to administrators and editors only.
	 */
	public function test_default_roles() {
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'read_doc_ext_lists' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'read_doc_ext_lists' ) );
		$this->assertFalse( get_role( 'author' )->has_cap( 'read_doc_ext_lists' ) );
		$this->assertFalse( get_role( 'contributor' )->has_cap( 'read_doc_ext_lists' ) );
		$this->assertFalse( get_role( 'subscriber' )->has_cap( 'read_doc_ext_lists' ) );
	}

	/**
	 * Upgrading from 3.x gives read_doc_ext_lists to every role and user with edit_doc_ext_lists,
	 * whoever loads the page.
	 */
	public function test_upgrade_gives_read_to_list_editors() {
		global $wpdr_en;
		get_role( 'administrator' )->remove_cap( 'read_doc_ext_lists' );
		get_role( 'editor' )->remove_cap( 'read_doc_ext_lists' );
		add_role(
			'en_list_manager',
			'List Manager',
			array(
				'read'               => true,
				'edit_doc_ext_lists' => true,
			)
		);
		( new WP_User( self::$users['author'] ) )->add_cap( 'edit_doc_ext_lists' );
		update_option( 'wpdr_en_db_version', '3.1' );

		wp_set_current_user( self::$users['subscriber'] );
		$wpdr_en->db_version_check();

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'read_doc_ext_lists' ), 'administrator' );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'read_doc_ext_lists' ), 'editor' );
		$this->assertTrue( get_role( 'en_list_manager' )->has_cap( 'read_doc_ext_lists' ), 'custom role' );
		$this->assertFalse( get_role( 'author' )->has_cap( 'read_doc_ext_lists' ), 'author role unchanged' );
		$this->assertTrue( self::user_can_cap( 'author', 'read_doc_ext_lists' ), 'user with edit_doc_ext_lists' );
		$this->assertFalse( self::user_can_cap( 'contributor', 'read_doc_ext_lists' ), 'contributor' );
	}

	/**
	 * A role where read_doc_ext_lists was explicitly denied stays denied.
	 */
	public function test_upgrade_keeps_explicit_denial() {
		global $wpdr_en;
		get_role( 'editor' )->add_cap( 'read_doc_ext_lists', false );
		update_option( 'wpdr_en_db_version', '3.1' );

		$wpdr_en->db_version_check();

		$this->assertFalse( get_role( 'editor' )->has_cap( 'read_doc_ext_lists' ) );
	}

	/**
	 * When no role has read_doc_ext_lists (e.g. the version option was lost), the install check adds
	 * the defaults to administrator and editor, whoever loads the page.
	 */
	public function test_install_adds_missing_read_to_defaults() {
		global $wpdr_en;
		get_role( 'administrator' )->remove_cap( 'read_doc_ext_lists' );
		get_role( 'editor' )->remove_cap( 'read_doc_ext_lists' );
		delete_option( 'wpdr_en_db_version' );

		wp_set_current_user( self::$users['subscriber'] );
		$wpdr_en->db_version_check();
		wp_roles()->for_site();

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'read_doc_ext_lists' ), 'administrator' );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'read_doc_ext_lists' ), 'editor' );
		$this->assertFalse( get_role( 'author' )->has_cap( 'read_doc_ext_lists' ), 'author' );
	}

	/**
	 * The tables are only checked when the site's version option is missing or different
	 * (on a network this ran on every page load).
	 */
	public function test_table_check_only_when_version_changes() {
		global $wpdr_en;
		$checks = 0;
		add_filter(
			'dbdelta_queries',
			static function ( $queries ) use ( &$checks ) {
				++$checks;
				return $queries;
			}
		);

		update_option( 'wpdr_en_db_version', WPDR_Email_Notice::$version );
		$wpdr_en->db_version_check();
		$this->assertSame( 0, $checks, 'current version' );

		delete_option( 'wpdr_en_db_version' );
		$wpdr_en->db_version_check();
		$this->assertSame( 1, $checks, 'missing version' );
		$this->assertSame( WPDR_Email_Notice::$version, get_option( 'wpdr_en_db_version' ) );

		$wpdr_en->db_version_check();
		$this->assertSame( 1, $checks, 'once' );
	}

	/**
	 * The upgrade only runs when coming from a version before 4.0.
	 */
	public function test_upgrade_runs_once() {
		global $wpdr_en;
		get_role( 'editor' )->remove_cap( 'read_doc_ext_lists' );
		update_option( 'wpdr_en_db_version', '4.0' );

		$wpdr_en->db_version_check();

		$this->assertFalse( get_role( 'editor' )->has_cap( 'read_doc_ext_lists' ) );
	}

	/**
	 * Reading a Document External List maps to read_doc_ext_lists.
	 */
	public function test_list_post_type_read_capability() {
		$this->assertSame( 'read_doc_ext_lists', get_post_type_object( 'doc_ext_list' )->cap->read );
	}

	/**
	 * The two log pages.
	 *
	 * @return array[]
	 */
	public static function data_log_pages() {
		return array(
			'internal log' => array( 'notification_log_list' ),
			'external log' => array( 'ext_notice_log_list' ),
		);
	}

	/**
	 * The log pages refuse users without read_doc_ext_lists.
	 *
	 * @dataProvider data_log_pages
	 * @param string $method Page callback.
	 */
	public function test_log_pages_need_read_capability( $method ) {
		global $wpdr_en;
		wp_set_current_user( self::$users['author'] );

		$this->expectException( 'WPDieException' );
		$wpdr_en->$method();
	}

	/**
	 * The log menu items are only added for users with read_doc_ext_lists.
	 */
	public function test_log_menu_items_need_read_capability() {
		global $wpdr_en, $submenu;
		$saved  = $submenu;
		$parent = 'edit.php?post_type=doc_ext_list';

		wp_set_current_user( self::$users['author'] );
		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdr_en->notification_log_menu();
		$author_items = isset( $submenu[ $parent ] ) ? $submenu[ $parent ] : array();

		wp_set_current_user( self::$users['editor'] );
		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdr_en->notification_log_menu();
		$editor_items = isset( $submenu[ $parent ] ) ? $submenu[ $parent ] : array();

		$submenu = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertCount( 0, $author_items, 'author: no log menus' );
		$this->assertSame( array( 'wpdr_en_ext_notice_log', 'wpdr_en_notification_log' ), wp_list_pluck( $editor_items, 2 ), 'editor: both log menus' );
		$this->assertSame( array( 'read_doc_ext_lists', 'read_doc_ext_lists' ), wp_list_pluck( $editor_items, 1 ) );
	}

	/**
	 * Render the Document metabox for a document as a user.
	 *
	 * @param int $doc     Document ID.
	 * @param int $user_id User ID.
	 * @return string HTML.
	 */
	private function metabox( $doc, $user_id ) {
		global $wpdr_en, $post;
		wp_set_current_user( $user_id );
		$post = get_post( $doc ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		ob_start();
		$wpdr_en->add_metabox();
		$html = ob_get_clean();
		wp_reset_postdata();
		return $html;
	}

	/**
	 * A matching list and a public document by the given author.
	 *
	 * @param string $author Role key of the document author.
	 * @return int Document ID.
	 */
	private function document_with_list( $author ) {
		$terms = array( 'post_tag' => self::$tag );
		self::create_list(
			array(
				'title'     => 'Visible List',
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
				),
			)
		);
		return self::create_document(
			array(
				'terms'       => $terms,
				'post_author' => self::$users[ $author ],
			)
		);
	}

	/**
	 * Without read_doc_ext_lists the metabox has the internal part only.
	 */
	public function test_metabox_hides_lists_without_read_capability() {
		$doc = $this->document_with_list( 'author' );

		$html = $this->metabox( $doc, self::$users['author'] );

		$this->assertStringContainsString( 'id="wpdr-en-notify"', $html, 'internal send shown' );
		$this->assertStringNotContainsString( 'wpdr-en-ext-note', $html, 'external send hidden' );
		$this->assertStringNotContainsString( 'Visible List', $html, 'lists hidden' );
	}

	/**
	 * A list editor sees the lists and can untick them.
	 */
	public function test_metabox_shows_lists_to_list_editor() {
		$doc = $this->document_with_list( 'editor' );

		$html = $this->metabox( $doc, self::$users['editor'] );

		$this->assertStringContainsString( 'id="wpdr-en-ext-note"', $html );
		$this->assertStringContainsString( 'Visible List', $html );
		$this->assertMatchesRegularExpression( '/name="wpdr-en-ext-list"[^>]*checked="checked">/', $html, 'list checkbox enabled' );
	}

	/**
	 * A user who can read but not edit lists sees them, but cannot untick them.
	 */
	public function test_metabox_lists_fixed_for_read_only_user() {
		( new WP_User( self::$users['author'] ) )->add_cap( 'read_doc_ext_lists' );
		$doc = $this->document_with_list( 'author' );

		$html = $this->metabox( $doc, self::$users['author'] );

		$this->assertStringContainsString( 'id="wpdr-en-ext-note"', $html );
		$this->assertStringContainsString( 'Visible List', $html );
		$this->assertMatchesRegularExpression( '/name="wpdr-en-ext-list"[^>]*checked="checked" disabled>/', $html, 'list checkbox disabled' );
	}
}
