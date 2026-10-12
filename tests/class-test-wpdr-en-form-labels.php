<?php
/**
 * Form markup fixed after the end-to-end tests (October 2026): every choice has a label,
 * and the address table has one "Show more details" button per row.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Labels on the settings, profile and Quick/Bulk Edit fields; the address table rows.
 */
class Test_WPDR_EN_Form_Labels extends WPDR_EN_UnitTestCase {

	/**
	 * Clear request data.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset( $_GET['post'] );
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
	 * Assert that each checkbox or radio in the HTML is inside a label with text, and no
	 * (invalid) closing input tag is left.
	 *
	 * @param string $html     HTML.
	 * @param int    $expected Number of checkboxes and radios expected.
	 * @return void
	 */
	private function assert_choices_labelled( $html, $expected ) {
		$this->assertSame( $expected, preg_match_all( '/<input type="(checkbox|radio)"/', $html ) );
		$this->assertSame( $expected, preg_match_all( '#<label[^>]*><input type="(checkbox|radio)"[^>]*>[^<]*\w#', $html ) );
		$this->assertStringNotContainsString( '</input>', $html );
	}

	/**
	 * Notification mode radios and "Notify internal users about" boxes are labelled.
	 */
	public function test_writing_settings_choices_have_labels() {
		global $wpdr_en;
		$this->assert_choices_labelled( self::output( array( $wpdr_en, 'set_notification_mode' ) ), 2 );
		$this->assert_choices_labelled( self::output( array( $wpdr_en, 'set_notification_about' ) ), 3 );
	}

	/**
	 * The text and textarea settings are labelled by their row heading.
	 */
	public function test_text_settings_have_label_for() {
		global $wp_settings_fields, $wpdr_en;
		$wpdr_en->admin_init();
		$fields = array(
			'general' => array( 'wpdr_en_set_email_from', 'wpdr_en_set_email_from_address' ),
			'writing' => array( 'wpdr_en_set_subject', 'wpdr_en_set_content', 'wpdr_en_set_exttext', 'wpdr_en_set_repeat' ),
		);
		foreach ( $fields as $page => $ids ) {
			foreach ( $ids as $id ) {
				$field = $wp_settings_fields[ $page ][ 'wpdr_en_' . $page . '_settings' ][ $id ];
				$this->assertSame( $id, $field['args']['label_for'], $id );
			}
		}
	}

	/**
	 * The profile boxes are labelled.
	 */
	public function test_profile_choices_have_labels() {
		global $wpdr_en;
		wp_set_current_user( 1 );
		$this->assert_choices_labelled( self::output( array( $wpdr_en, 'user_profile' ), array( get_user_by( 'id', 1 ) ) ), 2 );
	}

	/**
	 * Quick Edit boxes are labelled by wrapping, not by an id that WordPress copies into each
	 * Quick Edit row (so the label pointed at the hidden template).
	 */
	public function test_quick_edit_boxes_wrapped_in_labels() {
		global $wpdr_en;
		foreach ( array( 'wpdr_en_attach', 'wpdr_en_pause' ) as $column ) {
			$html = self::output( array( $wpdr_en, 'del_qe_box' ), array( $column, 'doc_ext_list', '' ) );
			$this->assert_choices_labelled( $html, 1 );
			$this->assertStringNotContainsString( 'id="' . $column . '"', $html );
			$this->assertStringContainsString( 'name="' . $column . '"', $html );
		}
	}

	/**
	 * The Bulk Edit match rule choice has no stray hyphen.
	 */
	public function test_bulk_edit_match_rule_text() {
		global $wpdr_en;
		$html = self::output( array( $wpdr_en, 'del_be_box' ), array( 'tm_rule', 'doc_ext_list' ) );
		$this->assertStringContainsString( '<option value="1">All taxonomy elements</option>', $html );
	}

	/**
	 * The Users screen column boxes are named for screen readers, with no stray closing tags.
	 */
	public function test_users_column_boxes_named() {
		global $wpdr_en;
		update_user_meta( 1, 'wpdr_en_user_notification', 1 );
		$html = $wpdr_en->all_users_column_rows( '', 'wpdr_en_notification', 1 );

		$this->assertMatchesRegularExpression( '/<input type="checkbox" checked="checked" disabled aria-label="Notified by email">/', $html );
		$this->assertMatchesRegularExpression( '/<input type="checkbox" disabled aria-label="Document sent as attachment">/', $html );
		$this->assertStringNotContainsString( '</>', $html );
	}

	/**
	 * Each address row has one "Show more details" button (row_actions() adds it already).
	 */
	public function test_address_rows_have_one_toggle() {
		require_once dirname( __DIR__ ) . '/includes/class-wpdr-en-ext-user-table.php';
		$list_id      = self::create_list(
			array(
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
					array(
						'user_name' => 'Bob',
						'email'     => 'bob@example.org',
					),
				),
			)
		);
		$_GET['post'] = (string) $list_id;
		set_current_screen( 'doc_ext_list' );
		$table = new WPDR_EN_Ext_User_Table();
		$table->prepare_items();

		$html = self::output( array( $table, 'display_rows' ) );
		$this->assertSame( 2, substr_count( $html, '<tr' ) );
		$this->assertSame( 2, substr_count( $html, 'class="toggle-row"' ) );
	}
}
