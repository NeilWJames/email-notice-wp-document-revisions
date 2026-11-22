<?php
/**
 * Accessibility review (November 2026): labels, grouping, descriptions, status regions,
 * names for repeated buttons and read-only boxes, and markup that browsers and screen
 * readers can rely on.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Markup checks for each screen changed by the accessibility review.
 */
class Test_WPDR_EN_Accessibility extends WPDR_EN_UnitTestCase {

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
		self::$tag = $factory->tag->create( array( 'name' => 'EN Access' ) );
	}

	/**
	 * Act as an editor (who can read and edit lists); load the table classes.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$users['editor'] );
		register_taxonomy_for_object_type( 'post_tag', 'doc_ext_list' );
		require_once dirname( __DIR__ ) . '/includes/class-wpdr-en-ext-user-table.php';
		require_once dirname( __DIR__ ) . '/includes/class-wpdr-en-user-log-table.php';
		require_once dirname( __DIR__ ) . '/includes/class-wpdr-en-ext-log-table.php';
	}

	/**
	 * Restore the screen, request and globals.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $post;
		$post = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		unset( $_GET['post'] );
		set_current_screen( 'dashboard' );
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
				'title'     => 'Access List',
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
	 * Assert that every aria-describedby in the HTML points at an element that is there.
	 *
	 * @param string $html     HTML.
	 * @param int    $expected Number of aria-describedby attributes expected (at least).
	 * @return void
	 */
	private function assert_descriptions_exist( $html, $expected ) {
		preg_match_all( '/aria-describedby="([^"]+)"/', $html, $matches );
		$this->assertGreaterThanOrEqual( $expected, count( $matches[1] ), 'descriptions used' );
		foreach ( $matches[1] as $ids ) {
			foreach ( explode( ' ', $ids ) as $id ) {
				$this->assertStringContainsString( 'id="' . $id . '"', $html, "description $id exists" );
			}
		}
	}

	/**
	 * Assert that each element opened in the HTML is also closed.
	 *
	 * @param string   $html HTML.
	 * @param string[] $tags Element names.
	 * @return void
	 */
	private function assert_balanced( $html, $tags ) {
		foreach ( $tags as $tag ) {
			$this->assertSame( preg_match_all( '/<' . $tag . '[\s>]/', $html ), substr_count( $html, '</' . $tag . '>' ), "<$tag> balanced" );
		}
	}

	/**
	 * Address entry: each field has a label, the help is tied to the fields, results go to a
	 * status region and the table heading can take focus.
	 */
	public function test_address_entry_form() {
		global $wpdr_en;
		list( , $list ) = self::document_and_list();
		self::edit_screen( $list );

		$html = self::output( array( $wpdr_en, 'doc_ext_list_metabox' ) );
		wp_reset_postdata();

		foreach ( array( 'wpdr-en-user-name', 'wpdr-en-email', 'wpdr_pause' ) as $id ) {
			$this->assertStringContainsString( '<label for="' . $id . '">', $html, $id );
		}
		$this->assertStringNotContainsString( '<td>User Name</td>', $html );
		$this->assertStringNotContainsString( 'onblur', $html, 'Add is checked as the user types' );
		$this->assertStringContainsString( 'id="wpdr-en-message" role="status" aria-live="polite"', $html );
		$this->assertStringContainsString( 'id="wpdr-en-list-heading" tabindex="-1"', $html );
		$this->assertStringNotContainsString( 'name="clear_address"', $html, 'no name attribute on a div' );
		$this->assert_descriptions_exist( $html, 2 );
	}

	/**
	 * Document metabox: labelled extra text, a status region, a decorative progress icon,
	 * a named group of lists and well-formed markup.
	 */
	public function test_document_metabox_markup() {
		global $wpdr_en;
		list( $doc ) = self::document_and_list();
		self::subscribe( self::$users['subscriber'] );
		self::edit_screen( $doc );

		$html = self::output( array( $wpdr_en, 'add_metabox' ) );
		wp_reset_postdata();

		$this->assertStringContainsString( '<label for="wpdr-en-extra">Optional Extra text for this specific mailing</label>', $html );
		$this->assertStringContainsString( 'id="wpdr-en-message" role="status" aria-live="polite"', $html );
		$this->assertStringNotContainsString( '<img', $html, 'core spinner, not an image without alt text' );
		$this->assertStringContainsString( 'class="spinner is-active" style="display:none;" aria-hidden="true"', $html );
		$this->assertStringContainsString( '<fieldset class="wpdr-en-lists"><legend class="screen-reader-text">Document External Lists to send to</legend>', $html );
		$this->assertMatchesRegularExpression( '#<label><input name="wpdr-en-ext-list"[^>]*> Access List</label>#', $html );
		$this->assertStringNotContainsString( '&nbsp;', $html );
		$this->assertStringNotContainsString( 'style=" 5px', $html );
		$this->assert_balanced( $html, array( 'div', 'span', 'fieldset', 'label' ) );
		$this->assert_descriptions_exist( $html, 1 );
	}

	/**
	 * Disabled send buttons say why they can't be used.
	 */
	public function test_document_metabox_disabled_reasons() {
		global $wpdr_en;

		self::edit_screen( self::create_document( array( 'post_status' => 'draft' ) ) );
		$html = self::output( array( $wpdr_en, 'add_metabox' ) );
		$this->assertStringContainsString( 'Publish the Document to send notifications.', $html );
		$this->assertStringContainsString( 'External list emails are only sent for public Documents.', $html );

		// Published, but nobody subscribed and no matching List.
		self::edit_screen( self::create_document() );
		$html = self::output( array( $wpdr_en, 'add_metabox' ) );
		wp_reset_postdata();
		$this->assertStringContainsString( 'No users are set to receive notifications for this Document.', $html );
		$this->assertStringContainsString( 'No Document External List matches this Document.', $html );
		$this->assert_balanced( $html, array( 'div', 'span' ) );
	}

	/**
	 * List Attributes: each setting is a fieldset with a legend, no drag-handle class, and
	 * each explanation is tied to its field.
	 */
	public function test_list_attributes_grouped() {
		global $wpdr_en;
		list( , $list ) = self::document_and_list();
		self::edit_screen( $list );

		$html = self::output( array( $wpdr_en, 'del_attrs_meta_box' ) );
		wp_reset_postdata();

		$this->assertSame( 4, substr_count( $html, '<fieldset class="wpdr-en-attr"' ) );
		$this->assertSame( 4, substr_count( $html, '<legend class="wpdr-en-legend">' ) );
		$this->assertStringContainsString( '<label for="excerpt" class="wpdr-en-legend">', $html );
		$this->assertStringNotContainsString( 'hndle', $html );
		$this->assertStringNotContainsString( 'role="radiogroup"', $html );
		$this->assert_balanced( $html, array( 'fieldset', 'label' ) );
		$this->assert_descriptions_exist( $html, 3 );
	}

	/**
	 * Quick Edit has no ids (WordPress copies it into each row); Bulk Edit has no repeated id
	 * and no spacing with non-breaking spaces.
	 */
	public function test_quick_and_bulk_edit_without_ids() {
		global $wpdr_en;
		foreach ( array( 'tm_rule', 'wpdr_en_attach', 'wpdr_en_pause' ) as $column ) {
			$html = self::output( array( $wpdr_en, 'del_qe_box' ), array( $column, 'doc_ext_list', '' ) );
			$this->assertDoesNotMatchRegularExpression( '/\sid="/', $html, $column );
			$this->assertStringNotContainsString( '&nbsp;', $html, $column );

			$bulk = self::output( array( $wpdr_en, 'del_be_box' ), array( $column, 'doc_ext_list' ) );
			$this->assertDoesNotMatchRegularExpression( '/\sid="/', $bulk, $column );
			$this->assertStringNotContainsString( '&nbsp;', $bulk, $column );
		}
		$html = self::output( array( $wpdr_en, 'del_qe_box' ), array( 'tm_rule', 'doc_ext_list', '' ) );
		$this->assertStringContainsString( '<legend class="screen-reader-text">Match Rule</legend>', $html );
		$this->assertStringNotContainsString( 'aria-labelledby', $html );
	}

	/**
	 * The read-only boxes in the Lists table are named for screen readers.
	 */
	public function test_list_column_boxes_named() {
		global $wpdr_en;
		$list = self::create_list(
			array(
				'attach' => 1,
				'pause'  => 1,
			)
		);

		$this->assertStringContainsString( 'disabled aria-label="Document attached to emails">', self::output( array( $wpdr_en, 'del_column_data' ), array( 'wpdr_en_attach', $list ) ) );
		$this->assertStringContainsString( 'disabled aria-label="List paused">', self::output( array( $wpdr_en, 'del_column_data' ), array( 'wpdr_en_pause', $list ) ) );
	}

	/**
	 * Address table: Edit and Delete name their address and don't submit the List form;
	 * the pause box is named and well formed.
	 */
	public function test_address_table_buttons_and_box() {
		$_GET['post'] = '12';
		$table        = new WPDR_EN_Ext_User_Table();
		$item         = array(
			'rec_num'   => 3,
			'user_name' => 'Ann',
			'email'     => 'ann@example.org',
			'pause'     => 1,
		);

		$html = $table->column_rec_num( $item );
		$this->assertStringContainsString( '<button type="button" onclick="wpdr_en_edit( this )"', $html );
		$this->assertStringContainsString( '<button type="button" onclick="wpdr_en_delete( 12, 3 )">Delete<span class="screen-reader-text"> ann@example.org</span></button>', $html );
		$this->assertSame( 2, substr_count( $html, '<span class="screen-reader-text"> ann@example.org</span>' ) );

		$box = $table->column_default( $item, 'pause' );
		$this->assertSame( '<input type="checkbox" checked="checked" disabled aria-label="Mail paused">', $box );
		$item['pause'] = 0;
		$this->assertSame( '<input type="checkbox" disabled aria-label="Mail paused">', $table->column_default( $item, 'pause' ) );
	}

	/**
	 * Log tables: the Extra Text button is a named disclosure button and the text box is
	 * labelled, read-only and no longer hides itself when clicked.
	 *
	 * @dataProvider data_log_tables
	 * @param string $table_class Table class.
	 * @param string $screen      Screen ID.
	 */
	public function test_log_extra_text_toggle( $table_class, $screen ) {
		$table = new $table_class( array( 'screen' => $screen ) );

		$html = $table->column_id(
			array(
				'id'         => 4,
				'extra_text' => 'Please review',
			)
		);

		$this->assertStringContainsString( '<button type="button" aria-expanded="false" aria-controls="extra_4" aria-label="Extra Text for log entry 4" onclick="wpdr_en_extra( 4, this )">Extra Text</button>', $html );
		$this->assertStringContainsString( '<textarea id="extra_4" rows="3" cols="40" readonly aria-label="Extra text sent with log entry 4" style="display: none">Please review</textarea>', $html );
		$this->assertDoesNotMatchRegularExpression( '/<textarea[^>]*onclick/', $html );
		$this->assertStringNotContainsString( '&nbsp;', $html );
	}

	/**
	 * Log table classes and their screens.
	 *
	 * @return array[]
	 */
	public static function data_log_tables() {
		return array(
			'internal' => array( 'WPDR_EN_User_Log_Table', 'wpdr_en_notification_log' ),
			'external' => array( 'WPDR_EN_Ext_Log_Table', 'wpdr_en_ext_notice_log' ),
		);
	}

	/**
	 * Settings: grouped choices have a legend, hints are tied to their fields, no </input>.
	 */
	public function test_settings_groups_and_descriptions() {
		global $wpdr_en;
		$this->assertStringContainsString( '<fieldset><legend class="screen-reader-text"><span>Internal Notice mode</span></legend>', self::output( array( $wpdr_en, 'set_notification_mode' ) ) );
		$this->assertStringContainsString( '<fieldset><legend class="screen-reader-text"><span>Notify internal users about</span></legend>', self::output( array( $wpdr_en, 'set_notification_about' ) ) );

		$fields = array(
			'set_subject'    => 'wpdr_en_set_subject',
			'set_content'    => 'wpdr_en_set_content',
			'set_exttext'    => 'wpdr_en_set_exttext',
			'set_repeat'     => 'wpdr_en_set_repeat',
			'set_ext_attach' => 'wpdr_en_set_ext_attach',
			'set_log_days'   => 'wpdr_en_set_log_days',
		);
		foreach ( $fields as $method => $id ) {
			$html = self::output( array( $wpdr_en, $method ) );
			$this->assertStringContainsString( 'aria-describedby="' . $id . '-description"', $html, $method );
			$this->assertStringContainsString( '<p class="description" id="' . $id . '-description">', $html, $method );
		}
		foreach ( array( 'set_email_from', 'set_email_from_address', 'set_subject' ) as $method ) {
			$this->assertStringNotContainsString( '</input>', self::output( array( $wpdr_en, $method ) ), $method );
		}
	}

	/**
	 * The template tags are a description list.
	 */
	public function test_tag_reference_is_a_list() {
		global $wpdr_en;
		$html = self::output( array( $wpdr_en, 'writing_settings' ) );

		$this->assertSame( 3, substr_count( $html, '<dl class="wpdr-en-tags">' ) );
		$this->assertStringContainsString( '<dt><code>%title%</code></dt><dd>means title of the post</dd>', $html );
		$this->assertStringContainsString( '<dt><code>%extra%</code></dt>', $html );
		$this->assertStringNotContainsString( '<br/>', $html );
	}

	/**
	 * The profile section has an h2 like the other sections, and its boxes are a named group.
	 */
	public function test_profile_section_structure() {
		global $wpdr_en;
		wp_set_current_user( 1 );
		$html = self::output( array( $wpdr_en, 'user_profile' ), array( get_user_by( 'id', 1 ) ) );

		$this->assertStringContainsString( '<h2>Document Email Settings</h2>', $html );
		$this->assertStringNotContainsString( '<h3>', $html );
		$this->assertStringContainsString( '<th scope="row">Document notifications</th>', $html );
		$this->assertStringContainsString( '<fieldset><legend class="screen-reader-text"><span>Document notifications</span></legend>', $html );
		$this->assert_balanced( $html, array( 'div', 'table', 'fieldset', 'label' ) );
	}
}
