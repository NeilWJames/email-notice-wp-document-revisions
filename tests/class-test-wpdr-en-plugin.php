<?php
/**
 * Plugin loading: the WP Document Revisions check and translations.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Functions in wpdr-email-notice.php and the translations folder.
 */
class Test_WPDR_EN_Plugin extends WPDR_EN_UnitTestCase {

	/**
	 * WP Document Revisions must be active on the site or, on a network, for all sites.
	 */
	public function test_wpdr_active_check() {
		$this->assertTrue( wpdr_en_wpdr_active(), 'active (as set up by the bootstrap)' );

		// Without the bootstrap's filter, the test site has no active plugins.
		remove_all_filters( 'active_plugins' );
		update_option( 'active_plugins', array() );
		$this->assertFalse( wpdr_en_wpdr_active(), 'not active' );

		update_option( 'active_plugins', array( 'wp-document-revisions/wp-document-revisions.php' ) );
		$this->assertTrue( wpdr_en_wpdr_active(), 'active on the site' );

		if ( is_multisite() ) {
			update_option( 'active_plugins', array() );
			update_site_option( 'active_sitewide_plugins', array( 'wp-document-revisions/wp-document-revisions.php' => time() ) );
			$this->assertTrue( wpdr_en_wpdr_active(), 'network active' );
		}
	}

	/**
	 * The notice when WP Document Revisions is missing is a normal admin notice.
	 */
	public function test_missing_wpdr_notice() {
		ob_start();
		wpdr_en_missing_wpdr_notice();
		$html = ob_get_clean();

		$this->assertStringStartsWith( '<div class="notice notice-warning is-dismissible">', $html );
		$this->assertStringContainsString( 'its required plugin WP Document Revisions is not', $html );
	}

	/**
	 * Translations are looked for in the plugin's languages folder (not includes/languages).
	 */
	public function test_translations_folder() {
		global $wp_textdomain_registry;
		$paths = new ReflectionProperty( $wp_textdomain_registry, 'custom_paths' );
		if ( PHP_VERSION_ID < 80100 ) {
			$paths->setAccessible( true );
		}

		$path = $paths->getValue( $wp_textdomain_registry )['email-notice-wp-document-revisions'];

		$this->assertStringEndsWith( '/' . basename( dirname( __DIR__ ) ) . '/languages', untrailingslashit( $path ) );
	}
}
