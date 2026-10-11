<?php
/**
 * Template tags in the subject and content.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Resolution of %title%, %recipient_name%, %words_n%, %extra% and friends,
 * checked on the mail actually sent by notify() and ext_notice().
 */
class Test_WPDR_EN_Tags extends WPDR_EN_UnitTestCase {

	/**
	 * Tag term used to match the External List.
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
		self::$tag = $factory->tag->create( array( 'name' => 'EN Tags' ) );
	}

	/**
	 * Clear the template options after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( 'wpdr_en_set_subject' );
		delete_option( 'wpdr_en_set_content' );
		delete_option( 'wpdr_en_set_exttext' );
		parent::tear_down();
	}

	/**
	 * Send one internal mail to the subscriber and return it.
	 *
	 * @param int    $doc   Document ID.
	 * @param string $extra Extra text.
	 * @return array wp_mail() arguments.
	 */
	private function internal_mail( $doc, $extra = '' ) {
		global $wpdr_en;
		self::subscribe( self::$users['subscriber'] );
		$wpdr_en->notify( $doc, $extra );
		$this->assertCount( 1, $this->mails, 'one mail sent' );
		return $this->mails[0];
	}

	/**
	 * Without a subject template the subject is "New document: <title>".
	 */
	public function test_default_subject() {
		$doc = self::create_document( array( 'post_title' => 'Annual Report' ) );

		$mail = $this->internal_mail( $doc );

		$this->assertSame( 'New document: Annual Report', $mail['subject'] );
	}

	/**
	 * A subject ending in a digit gets a full stop (spam checkers dislike trailing numbers).
	 */
	public function test_subject_trailing_number() {
		$doc = self::create_document( array( 'post_title' => 'Policy 7' ) );

		$mail = $this->internal_mail( $doc );

		$this->assertSame( 'New document: Policy 7.', $mail['subject'] );
	}

	/**
	 * The wpdr_en_subject_trailing_number filter can turn that off.
	 */
	public function test_subject_trailing_number_filter() {
		add_filter( 'wpdr_en_subject_trailing_number', '__return_false' );
		$doc = self::create_document( array( 'post_title' => 'Policy 7' ) );

		$mail = $this->internal_mail( $doc );

		$this->assertSame( 'New document: Policy 7', $mail['subject'] );
	}

	/**
	 * Subject template tags are resolved and HTML is stripped.
	 */
	public function test_subject_template() {
		update_option( 'wpdr_en_set_subject', 'Hi %recipient_name%: %title_with_permalink%' );
		$doc = self::create_document( array( 'post_title' => 'Minutes' ) );

		$mail = $this->internal_mail( $doc );

		$this->assertSame( 'Hi Subscriber User: Minutes', $mail['subject'] );
	}

	/**
	 * Content template tags are resolved.
	 */
	public function test_content_template() {
		update_option( 'wpdr_en_set_content', 'Dear %recipient_name%, see %title% at %permalink% by %author_name%.' );
		$doc = self::create_document( array( 'post_title' => 'Minutes' ) );

		$mail = $this->internal_mail( $doc );

		$link = get_permalink( $doc );
		$this->assertStringContainsString( 'Dear Subscriber User, see Minutes at <a href="' . $link . '">' . $link . '</a> by Editor User.', $mail['message'] );
	}

	/**
	 * %words_n% gives the first n words, with an ellipsis when there are more.
	 */
	public function test_words_tag() {
		update_option( 'wpdr_en_set_content', '%words_3%|%words_9%' );
		$doc = self::create_document( array( 'post_content' => 'one two three four five' ) );

		$mail = $this->internal_mail( $doc );

		$this->assertStringContainsString( '<blockquote><em>one two three...</em></blockquote>|', $mail['message'] );
		$this->assertStringContainsString( '|<blockquote><em>one two three four five</em></blockquote>', $mail['message'] );
	}

	/**
	 * %extra% is replaced by the text given when sending.
	 */
	public function test_extra_tag() {
		update_option( 'wpdr_en_set_content', 'Start %extra% End' );
		$doc = self::create_document();

		$mail = $this->internal_mail( $doc, 'Please review by Friday' );

		$this->assertStringContainsString( 'Start Please review by Friday End', $mail['message'] );
	}

	/**
	 * %excerpt% is only filled in when the sender can edit the document.
	 */
	public function test_excerpt_needs_edit_rights() {
		update_option( 'wpdr_en_set_content', '[%excerpt%]' );
		$doc = self::create_document( array( 'post_excerpt' => 'Short summary' ) );

		wp_set_current_user( self::$users['editor'] );
		$mail = $this->internal_mail( $doc );
		$this->assertStringContainsString( '[Short summary]', $mail['message'], 'editor sees excerpt' );

		$this->mails = array();
		wp_set_current_user( self::$users['subscriber'] );
		$mail = $this->internal_mail( $doc );
		$this->assertStringContainsString( '[]', $mail['message'], 'subscriber does not' );
	}

	/**
	 * External mail uses the external template and the list's name for %recipient_name%.
	 */
	public function test_external_template() {
		global $wpdr_en;
		update_option( 'wpdr_en_set_exttext', 'Hello %recipient_name% (%title%)' );
		$terms = array( 'post_tag' => self::$tag );
		self::create_list(
			array(
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'jo smith',
						'email'     => 'jo@example.org',
					),
				),
			)
		);
		$doc = self::create_document(
			array(
				'terms'      => $terms,
				'post_title' => 'Agenda',
			)
		);

		$wpdr_en->ext_notice( $doc, array(), '' );

		$this->assertCount( 1, $this->mails );
		$this->assertStringContainsString( 'Hello Jo Smith (Agenda)', $this->mails[0]['message'] );
		$this->assertSame( 'jo smith <jo@example.org>', $this->mails[0]['to'] );
	}
}
