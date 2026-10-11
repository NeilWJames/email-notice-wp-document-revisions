<?php
/**
 * Multisite separation (security finding #6).
 *
 * Skipped on single site. Run with: WP_MULTISITE=1 bin/phpunit (CI does this in one job).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Each site only mails its own members and only uses its own External Lists.
 */
class Test_WPDR_EN_Multisite extends WPDR_EN_UnitTestCase {

	// phpcs:disable
	/**
	 * Users on the main site.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// phpcs:enable
		if ( is_multisite() ) {
			self::create_users( $factory );
		}
	}

	/**
	 * Multisite only.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only: run with WP_MULTISITE=1.' );
		}
	}

	/**
	 * A second site with the plugin's tables.
	 *
	 * @return int Blog ID.
	 */
	private function second_site() {
		global $wpdr_en;
		$blog = self::factory()->blog->create();
		switch_to_blog( $blog );
		$wpdr_en->db_version_check();
		restore_current_blog();
		return $blog;
	}

	/**
	 * Internal notifications go only to members of the document's site.
	 */
	public function test_internal_mail_only_to_site_members() {
		global $wpdr_en;
		$blog   = $this->second_site();
		$member = self::factory()->user->create( array( 'user_email' => 'member@example.com' ) );
		add_user_to_blog( $blog, $member, 'subscriber' );
		self::subscribe( $member );
		self::subscribe( self::$users['subscriber'] );

		switch_to_blog( $blog );
		$doc = self::create_document( array( 'post_author' => $member ) );
		$wpdr_en->notify( $doc, '' );
		restore_current_blog();

		$this->assertSame( array( 'member@example.com' ), $this->mailed_addresses() );
	}

	/**
	 * External notices only use the document's own site's Lists.
	 */
	public function test_lists_are_per_site() {
		global $wpdr_en;
		$main_tag = self::factory()->tag->create( array( 'name' => 'Shared' ) );
		self::create_list(
			array(
				'terms'     => array( 'post_tag' => $main_tag ),
				'addresses' => array(
					array(
						'user_name' => 'Main',
						'email'     => 'main@example.org',
					),
				),
			)
		);
		$blog = $this->second_site();

		switch_to_blog( $blog );
		$tag = self::factory()->tag->create( array( 'name' => 'Shared' ) );
		self::create_list(
			array(
				'terms'     => array( 'post_tag' => $tag ),
				'addresses' => array(
					array(
						'user_name' => 'Sub',
						'email'     => 'sub@example.org',
					),
				),
			)
		);
		$doc = self::create_document(
			array(
				'terms'       => array( 'post_tag' => $tag ),
				'post_author' => 1,
			)
		);
		$wpdr_en->ext_notice( $doc, array(), '' );
		restore_current_blog();

		$this->assertSame( array( 'sub@example.org' ), $this->mailed_addresses() );
	}
}
