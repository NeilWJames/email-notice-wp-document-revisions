<?php
/**
 * Base test case for the Email Notice tests.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * WP_UnitTestCase with the plugin fixtures and mail capture.
 */
abstract class WPDR_EN_UnitTestCase extends WP_UnitTestCase {

	use WPDR_EN_Fixtures;

	/**
	 * Capture mail for every test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->start_mail_capture();
	}
}
