<?php
/**
 * Document External List settings: list columns, Quick Edit, Bulk Edit and saving.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Match rule, attach and pause settings of a list.
 */
class Test_WPDR_EN_List_Edit extends WPDR_EN_UnitTestCase {

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
	 * Act as an editor (who can edit lists).
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$users['editor'] );
	}

	/**
	 * Clear the request.
	 *
	 * @return void
	 */
	public function tear_down() {
		$_GET  = array();
		$_POST = array();
		remove_filter( 'wp_doing_ajax', '__return_true' );
		delete_option( 'wpdr_en_set_ext_attach' );
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
	 * A list's settings: match rule, attach, pause.
	 *
	 * @param int $list_id List ID.
	 * @return string[]
	 */
	private static function settings( $list_id ) {
		return array(
			get_post_meta( $list_id, 'wpdr_en_tm', true ),
			get_post_meta( $list_id, 'wpdr_en_attach', true ),
			get_post_meta( $list_id, 'wpdr_en_pause', true ),
		);
	}

	/**
	 * Call the save hook as WordPress does.
	 *
	 * @param int $list_id List ID.
	 * @return void
	 */
	private static function save( $list_id ) {
		global $wpdr_en;
		$wpdr_en->save_doc_ext_list( $list_id, get_post( $list_id ), true );
	}

	/**
	 * The three columns follow the checkbox and title.
	 */
	public function test_columns_added() {
		global $wpdr_en;
		$columns = $wpdr_en->add_meta_columns(
			array(
				'cb'    => '',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertSame( array( 'cb', 'title', 'tm_rule', 'wpdr_en_attach', 'wpdr_en_pause', 'date' ), array_keys( $columns ) );
	}

	/**
	 * Column cells show the settings; attach falls back to the site setting.
	 */
	public function test_column_values() {
		global $wpdr_en;
		$all  = self::create_list(
			array(
				'tm_rule' => 1,
				'pause'   => 1,
			)
		);
		$html = self::output( array( $wpdr_en, 'del_column_data' ), array( 'tm_rule', $all ) );
		$this->assertStringContainsString( 'id="tm-rule-' . $all . '" value ="1"', $html );
		$this->assertStringContainsString( 'All taxonomy elements', $html );
		$this->assertStringContainsString( "checked='checked'", self::output( array( $wpdr_en, 'del_column_data' ), array( 'wpdr_en_pause', $all ) ) );
		$this->assertStringNotContainsString( 'checked', self::output( array( $wpdr_en, 'del_column_data' ), array( 'wpdr_en_attach', $all ) ) );

		$any = self::create_list();
		delete_post_meta( $any, 'wpdr_en_attach' );
		update_option( 'wpdr_en_set_ext_attach', 1 );
		$this->assertStringContainsString( 'Any taxonomy element', self::output( array( $wpdr_en, 'del_column_data' ), array( 'tm_rule', $any ) ) );
		$this->assertStringContainsString( "checked='checked'", self::output( array( $wpdr_en, 'del_column_data' ), array( 'wpdr_en_attach', $any ) ), 'site setting' );
		$this->assertSame( '', self::output( array( $wpdr_en, 'del_column_data' ), array( 'title', $any ) ) );
	}

	/**
	 * Quick Edit and Bulk Edit fields, for lists only.
	 */
	public function test_edit_boxes() {
		global $wpdr_en;
		$fields = array(
			'tm_rule'        => array( 'name="tm_rule"', 'name="tm_ruleb"' ),
			'wpdr_en_attach' => array( 'name="wpdr_en_attach"', 'name="attachb"' ),
			'wpdr_en_pause'  => array( 'name="wpdr_en_pause"', 'name="list_pauseb"' ),
		);
		foreach ( $fields as $column => $names ) {
			$this->assertStringContainsString( $names[0], self::output( array( $wpdr_en, 'del_qe_box' ), array( $column, 'doc_ext_list', '' ) ) );
			$bulk = self::output( array( $wpdr_en, 'del_be_box' ), array( $column, 'doc_ext_list' ) );
			$this->assertStringContainsString( $names[1], $bulk );
			$this->assertStringContainsString( 'value="-1"', $bulk, 'No Change' );
		}

		$this->assertSame( '', self::output( array( $wpdr_en, 'del_qe_box' ), array( 'tm_rule', 'document', '' ) ) );
		$this->assertSame( '', self::output( array( $wpdr_en, 'del_be_box' ), array( 'tm_rule', 'post' ) ) );
	}

	/**
	 * The edit form saves the settings; a ticked box is on whatever its value.
	 */
	public function test_edit_form_save() {
		$list = self::create_list();

		$_POST = array(
			'_wpnonce'       => wp_create_nonce( 'update-post_' . $list ),
			'tm_rule'        => '1',
			// The edit form's boxes hold the value when the page was loaded.
			'wpdr_en_attach' => '0',
			'wpdr_en_pause'  => '0',
		);
		self::save( $list );
		$this->assertSame( array( '1', '1', '1' ), self::settings( $list ) );

		// Unticked boxes are not sent.
		$_POST = array(
			'_wpnonce' => wp_create_nonce( 'update-post_' . $list ),
			'tm_rule'  => '0',
		);
		self::save( $list );
		$this->assertSame( array( '0', '0', '0' ), self::settings( $list ) );
	}

	/**
	 * Quick Edit saves the settings.
	 */
	public function test_quick_edit_save() {
		$list = self::create_list();
		add_filter( 'wp_doing_ajax', '__return_true' );

		$_POST = array(
			'_inline_edit'   => wp_create_nonce( 'inlineeditnonce' ),
			'tm_rule'        => '1',
			'wpdr_en_attach' => '1',
		);
		self::save( $list );
		$this->assertSame( array( '1', '1', '0' ), self::settings( $list ) );

		$_POST['_inline_edit'] = 'bad';
		$_POST['tm_rule']      = '0';
		self::save( $list );
		$this->assertSame( '1', get_post_meta( $list, 'wpdr_en_tm', true ), 'bad nonce' );
	}

	/**
	 * Bulk Edit changes only the fields not left at "No Change", whatever the button's (translated) label.
	 */
	public function test_bulk_edit_save() {
		$list = self::create_list(
			array(
				'tm_rule' => 1,
				'attach'  => 1,
			)
		);

		$_GET = array(
			'bulk_edit'   => 'Mettre à jour',
			'_wpnonce'    => wp_create_nonce( 'bulk-posts' ),
			'tm_ruleb'    => '0',
			'attachb'     => '-1',
			'list_pauseb' => '1',
		);
		self::save( $list );
		$this->assertSame( array( '0', '1', '1' ), self::settings( $list ) );

		$_GET['_wpnonce'] = 'bad';
		$_GET['attachb']  = '0';
		self::save( $list );
		$this->assertSame( '1', get_post_meta( $list, 'wpdr_en_attach', true ), 'bad nonce' );

		$_GET['_wpnonce'] = wp_create_nonce( 'bulk-posts' );
		$_GET['attachb']  = '7';
		self::save( $list );
		$this->assertSame( '1', get_post_meta( $list, 'wpdr_en_attach', true ), 'only 0 or 1' );
	}

	/**
	 * A save from code (no form) leaves the settings alone and does not die.
	 */
	public function test_programmatic_save() {
		$list = self::create_list(
			array(
				'tm_rule' => 1,
				'pause'   => 1,
			)
		);

		wp_update_post(
			array(
				'ID'         => $list,
				'post_title' => 'Renamed',
			)
		);

		$this->assertSame( 'Renamed', get_the_title( $list ) );
		$this->assertSame( array( '1', '0', '1' ), self::settings( $list ) );
	}

	/**
	 * A user who cannot edit the list changes nothing, even with a valid nonce.
	 */
	public function test_save_needs_edit_capability() {
		$list = self::create_list();
		wp_set_current_user( self::$users['contributor'] );

		$_POST = array(
			'_wpnonce'      => wp_create_nonce( 'update-post_' . $list ),
			'tm_rule'       => '1',
			'wpdr_en_pause' => '1',
		);
		self::save( $list );

		$this->assertSame( array( '0', '0', '0' ), self::settings( $list ) );
	}
}
