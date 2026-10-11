<?php
/**
 * External List address table output (security fix #3).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * The address table must never put names or emails into JavaScript.
 */
class Test_WPDR_EN_Address_Table extends WPDR_EN_UnitTestCase {

	/**
	 * Load the table class.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		require_once dirname( __DIR__ ) . '/includes/class-wpdr-en-ext-user-table.php';
	}

	/**
	 * Clear request data.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset( $_GET['post'], $_POST['post'] );
		parent::tear_down();
	}

	/**
	 * Build a table as if on the edit screen of the given post value.
	 *
	 * @param string $post_param Raw value of the post query argument.
	 * @return WPDR_EN_Ext_User_Table
	 */
	private function table( $post_param ) {
		$_GET['post'] = $post_param;
		return new WPDR_EN_Ext_User_Table();
	}

	/**
	 * Quotes in a name or email cannot break out of the Edit button.
	 */
	public function test_edit_button_escapes_values() {
		$html = $this->table( '12' )->column_rec_num(
			array(
				'rec_num'   => 3,
				'user_name' => 'x", alert(1), "',
				'email'     => "o'brien@example.org",
				'pause'     => 1,
			)
		);

		$this->assertStringContainsString( 'onclick="wpdr_en_edit( this )"', $html );
		$this->assertStringContainsString( 'data-user-name="x&quot;, alert(1), &quot;"', $html );
		$this->assertStringContainsString( 'data-email="o&#039;brien@example.org"', $html );
		$this->assertStringContainsString( 'data-pause="1"', $html );
		$this->assertStringNotContainsString( 'x", alert(1)', $html );
	}

	/**
	 * The post ID in the Delete button is forced to an integer.
	 */
	public function test_delete_button_post_id_is_integer() {
		$html = $this->table( '12);alert(1)//' )->column_rec_num(
			array(
				'rec_num'   => 3,
				'user_name' => 'Ann',
				'email'     => 'ann@example.org',
				'pause'     => 0,
			)
		);

		$this->assertStringContainsString( 'onclick="wpdr_en_delete( 12, 3 )"', $html );
		$this->assertStringNotContainsString( 'alert', $html );
	}

	/**
	 * Name and email cells are HTML-escaped.
	 */
	public function test_cells_are_escaped() {
		$table = $this->table( '12' );
		$item  = array(
			'rec_num'   => 0,
			'user_name' => 'A <b>bold</b> & name',
			'email'     => 'ann@example.org',
			'pause'     => 0,
		);

		$this->assertSame( 'A &lt;b&gt;bold&lt;/b&gt; &amp; name', $table->column_default( $item, 'user_name' ) );
		$this->assertSame( 'ann@example.org', $table->column_default( $item, 'email' ) );
	}
}
