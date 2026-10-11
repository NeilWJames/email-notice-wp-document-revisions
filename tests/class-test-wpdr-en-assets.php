<?php
/**
 * Script and style files (src/ and build/).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Every source has a built copy, the built JS keeps the global functions the HTML calls,
 * and the plugin loads the files from the right place.
 */
class Test_WPDR_EN_Assets extends WPDR_EN_UnitTestCase {

	/**
	 * Plugin folder.
	 *
	 * @return string
	 */
	private static function dir() {
		return dirname( __DIR__ );
	}

	/**
	 * Base names of the files in a folder with a given ending.
	 *
	 * @param string $folder Folder relative to the plugin.
	 * @param string $ending File ending, e.g. '.js'.
	 * @return string[]
	 */
	private static function names( $folder, $ending ) {
		$names = array();
		foreach ( (array) glob( self::dir() . '/' . $folder . '/*' . $ending ) as $file ) {
			$names[] = basename( $file, $ending );
		}
		sort( $names );
		return $names;
	}

	/**
	 * The two asset types.
	 *
	 * @return array[]
	 */
	public static function data_types() {
		return array(
			'js'  => array( 'js' ),
			'css' => array( 'css' ),
		);
	}

	/**
	 * Each src/ file has a build/ file and there are no stray built files.
	 *
	 * @dataProvider data_types
	 * @param string $type 'js' or 'css'.
	 */
	public function test_every_source_is_built( $type ) {
		$sources = self::names( 'src/' . $type, '.' . $type );
		$this->assertNotEmpty( $sources, 'sources found' );
		$this->assertSame( $sources, self::names( 'build/' . $type, '.' . $type ), 'same names in build/ (an old .min file would show here)' );
	}

	/**
	 * The minified JS keeps the global functions called from onclick attributes and inline scripts.
	 */
	public function test_built_js_keeps_global_functions() {
		$expected = array(
			'wpdr-en-address' => array( 'wpdr_en_insert', 'wpdr_en_delete', 'wpdr_en_edit', 'wpdr_en_clear', 'check_address' ),
			'wpdr-en-log'     => array( 'wpdr_en_extra' ),
		);
		foreach ( $expected as $name => $functions ) {
			$code = file_get_contents( self::dir() . '/build/js/' . $name . '.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			foreach ( $functions as $function ) {
				$this->assertStringContainsString( 'function ' . $function . '(', $code, "build/js/$name.js defines $function()" );
			}
		}
	}

	/**
	 * With WP_DEBUG on (as in the test config) the source file is loaded, with its time as version.
	 */
	public function test_asset_uses_source_in_debug() {
		$this->assertTrue( WP_DEBUG, 'tests run with WP_DEBUG' );

		$asset = WPDR_Email_Notice::asset( 'js', 'wpdr-en-log' );

		$this->assertStringEndsWith( '/src/js/wpdr-en-log.js', $asset['url'] );
		$this->assertSame( (string) filemtime( self::dir() . '/src/js/wpdr-en-log.js' ), $asset['version'] );
	}

	/**
	 * The document screen loads the document script and style.
	 */
	public function test_document_screen_enqueues_assets() {
		global $post;
		wp_set_current_user( self::$users['editor'] );
		$post = get_post( self::create_document() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->reset_js_loaded();
		WPDR_Email_Notice::load_js_methods();

		$this->assertTrue( wp_script_is( 'wpdr_en_script', 'enqueued' ) );
		$this->assertStringEndsWith( '/src/js/wpdr-email-notice.js', wp_scripts()->registered['wpdr_en_script']->src );
		$this->assertTrue( wp_style_is( 'wpdr_en_css', 'enqueued' ) );
		$this->assertStringEndsWith( '/src/css/wpdr-en-notice.css', wp_styles()->registered['wpdr_en_css']->src );
	}

	/**
	 * The List edit screen loads the address script and the list style; other screens load nothing.
	 */
	public function test_list_screen_enqueues_assets() {
		global $post;
		wp_set_current_user( self::$users['editor'] );
		$post = get_post( self::create_list() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->reset_js_loaded();
		WPDR_Email_Notice::load_js_methods();

		$this->assertStringEndsWith( '/src/js/wpdr-en-address.js', wp_scripts()->registered['wpdr_en_script']->src );
		$this->assertStringEndsWith( '/src/css/wpdr-en-mail.css', wp_styles()->registered['wpdr_en_css']->src );

		$post = get_post( self::factory()->post->create() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->reset_js_loaded();
		WPDR_Email_Notice::load_js_methods();
		$this->assertFalse( wp_script_is( 'wpdr_en_script', 'registered' ) );
		$post = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Allow load_js_methods() to run again in this request.
	 *
	 * @return void
	 */
	private function reset_js_loaded() {
		$prop = new ReflectionProperty( 'WPDR_Email_Notice', 'js_loaded' );
		if ( PHP_VERSION_ID < 80100 ) {
			$prop->setAccessible( true ); // Needed before PHP 8.1; deprecated in 8.5.
		}
		$prop->setValue( null, false );
		wp_dequeue_script( 'wpdr_en_script' );
		wp_deregister_script( 'wpdr_en_script' );
		wp_dequeue_style( 'wpdr_en_css' );
		wp_deregister_style( 'wpdr_en_css' );
	}

	/**
	 * Undo the enqueueing.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->reset_js_loaded();
		parent::tear_down();
	}

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
}
