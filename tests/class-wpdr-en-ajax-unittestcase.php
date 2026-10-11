<?php
/**
 * Base test case for the Email Notice AJAX handlers.
 *
 * WP_Ajax_UnitTestCase defines DOING_AJAX for the rest of the PHPUnit run, so AJAX test
 * files are named class-test-wpdr-en-zz-* to run after the others.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * WP_Ajax_UnitTestCase with the plugin fixtures and mail capture.
 */
abstract class WPDR_EN_Ajax_UnitTestCase extends WP_Ajax_UnitTestCase {

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

	/**
	 * Call an AJAX action as a user and return the decoded JSON response.
	 *
	 * The nonce is created for that user unless $post already contains one.
	 *
	 * @param string $action  AJAX action name (without wp_ajax_).
	 * @param int    $user_id User to act as.
	 * @param array  $post    POST fields.
	 * @return array Decoded response.
	 */
	protected function ajax( $action, $user_id, $post ) {
		wp_set_current_user( $user_id );
		if ( ! array_key_exists( 'wpdr_en_nonce', $post ) ) {
			$post['wpdr_en_nonce'] = wp_create_nonce( 'wpdr_en_nonce' );
		}
		$_POST                = $post;
		$this->_last_response = '';
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'AJAX response is JSON: ' . $this->_last_response );
		return $response;
	}
}
