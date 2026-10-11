<?php
/**
 * Security review findings #4 and #7–#12 (4.0.0).
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Regression tests for the remaining security findings. Multisite (#6) is in
 * class-test-wpdr-en-multisite.php.
 */
class Test_WPDR_EN_Security extends WPDR_EN_UnitTestCase {

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
		self::$tag = $factory->tag->create( array( 'name' => 'EN Security' ) );
	}

	/**
	 * Undo screen and script changes.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->reset_js();
		set_current_screen( 'dashboard' );
		unset( $_GET['s'] );
		delete_option( 'wpdr_en_set_content' );
		delete_option( 'wpdr_en_set_email_from_address' );
		parent::tear_down();
	}

	/**
	 * Call a private or protected method.
	 *
	 * @param object|string $target Object, or class name for a static method.
	 * @param string        $method Method name.
	 * @param array         $args   Arguments.
	 * @return mixed
	 */
	private static function call( $target, $method, $args = array() ) {
		$ref = new ReflectionMethod( $target, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true );
		}
		return $ref->invokeArgs( is_object( $target ) ? $target : null, $args );
	}

	/**
	 * Allow load_js_methods() to run again and drop the plugin's script.
	 *
	 * @return void
	 */
	private function reset_js() {
		$prop = new ReflectionProperty( 'WPDR_Email_Notice', 'js_loaded' );
		if ( PHP_VERSION_ID < 80100 ) {
			$prop->setAccessible( true );
		}
		$prop->setValue( null, false );
		wp_deregister_script( 'wpdr_en_script' );
		wp_deregister_style( 'wpdr_en_css' );
	}

	/**
	 * #4: unsaved term IDs from the form are reduced to integers, hierarchical taxonomies only.
	 */
	public function test_unsaved_terms_are_integers() {
		global $wpdr_en;
		$unsaved = self::call(
			$wpdr_en,
			'unsaved_taxonomy_values',
			array(
				array(
					'category'  => array( '0', '5', '9);alert(1)//' ),
					'post_tag'  => 'red,blue',
					'not_a_tax' => array( '3' ),
					'<script>'  => array( '4' ),
				),
				array( 'category' => array( '7' ) ),
			)
		);

		$this->assertSame( array( 'category' => array( 5, 9, -7 ) ), $unsaved );
	}

	/**
	 * #4: the parent/child error message is escaped and the revert script holds only safe values.
	 */
	public function test_error_state_output_is_safe() {
		global $wpdr_en, $post;
		$parent = self::factory()->category->create( array( 'name' => 'Parent' ) );
		$child  = self::factory()->category->create(
			array(
				'name'   => 'Child',
				'parent' => $parent,
			)
		);
		$list   = self::create_list( array( 'terms' => array( 'category' => $parent ) ) );
		set_transient(
			'del_' . $list,
			array(
				'category',
				$parent,
				$child,
				array(
					'category'      => array( $child, '5);alert(1)//', -7 ),
					"x');alert(1);" => array( 1 ),
				),
			),
			120
		);
		// Term names are stripped of tags when saved, so give one some HTML on the way out.
		add_filter(
			'get_term',
			static function ( $term ) use ( $parent ) {
				if ( $term instanceof WP_Term && $parent === $term->term_id ) {
					$term->name = 'Parent <b>bold</b>';
				}
				return $term;
			}
		);
		wp_set_current_user( self::$users['editor'] );
		set_current_screen( 'doc_ext_list' );
		$post = get_post( $list ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->reset_js();

		ob_start();
		$wpdr_en->check_error_state();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Parent &lt;b&gt;bold&lt;/b&gt;', $html, 'term name escaped' );
		$this->assertStringNotContainsString( '<b>bold</b>', $html );

		$script = implode( "\n", (array) wp_scripts()->get_data( 'wpdr_en_script', 'after' ) );
		$this->assertStringContainsString( 'revert_unsaved_hier( "category", ' . $child . ', true );', $script );
		$this->assertStringContainsString( 'revert_unsaved_hier( "category", 5, true );', $script );
		$this->assertStringContainsString( 'revert_unsaved_hier( "category", 7, false );', $script );
		$this->assertStringNotContainsString( 'alert', $script );
		$this->assertFalse( get_transient( 'del_' . $list ), 'transient used once' );
	}

	/**
	 * #4: the function the revert script calls exists in the List screen script.
	 */
	public function test_revert_function_is_defined() {
		$dir = dirname( __DIR__ );
		foreach ( array( '/src/js/wpdr-en-address.js', '/build/js/wpdr-en-address.js' ) as $file ) {
			$this->assertStringContainsString( 'function revert_unsaved_hier(', file_get_contents( $dir . $file ), $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
	}

	/**
	 * #4 (related): a List saved from code (no edit form) is not checked, redirected or stopped.
	 */
	public function test_programmatic_publish_is_not_checked() {
		global $wpdr_en;
		$postarr = array(
			'ID'          => 0,
			'post_type'   => 'doc_ext_list',
			'post_status' => 'publish',
		);

		$this->assertFalse( $wpdr_en->check_taxonomy_value_set( false, $postarr ) );
	}

	/**
	 * #7: Lists are not public, searchable or offered in menus.
	 */
	public function test_lists_are_not_public() {
		$type = get_post_type_object( 'doc_ext_list' );

		$this->assertFalse( $type->public );
		$this->assertTrue( $type->exclude_from_search );
		$this->assertFalse( $type->show_in_nav_menus );
		$this->assertFalse( $type->publicly_queryable );
		$this->assertFalse( $type->show_in_rest );
		$this->assertTrue( $type->show_ui, 'still managed in the admin' );
	}

	/**
	 * #7: a site search does not return Lists.
	 */
	public function test_search_does_not_find_lists() {
		self::create_list( array( 'title' => 'Zebracorn subscribers' ) );

		$query = new WP_Query(
			array(
				's'         => 'Zebracorn',
				'post_type' => 'any',
			)
		);

		$this->assertSame( 0, $query->found_posts );
	}

	/**
	 * #8: the External log search finds names with quotes (it is a prepared LIKE now).
	 */
	public function test_external_log_search_handles_quotes() {
		global $wpdb;
		require_once dirname( __DIR__ ) . '/includes/class-wpdr-en-ext-log-table.php';
		$doc  = self::create_document( array( 'post_title' => 'Search Doc' ) );
		$list = self::create_list( array( 'title' => 'Search List' ) );
		foreach ( array( "Pat O'Brien", 'Sam Smith' ) as $name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'wpdr_ext_notice_log',
				array(
					'post_id'         => $doc,
					'doc_ext_list_id' => $list,
					'time_mail_sent'  => current_time( 'mysql' ),
					'user_name'       => $name,
					'user_email'      => 'x@example.org',
					'status'          => 'Successful',
				)
			);
		}
		$_GET['s'] = "O'Brien";

		$table = new WPDR_EN_Ext_Log_Table( array( 'screen' => 'wpdr_en_ext_notice_log' ) );
		$table->prepare_items();

		$this->assertSame( array( "Pat O'Brien" ), wp_list_pluck( $table->items, 'user_name' ) );
	}

	/**
	 * #9: a comma or angle brackets in a name cannot add recipients.
	 */
	public function test_recipient_string() {
		$this->assertSame( 'jo smith <jo@example.org>', WPDR_Email_Notice::recipient( 'jo smith', 'jo@example.org' ) );
		$this->assertSame( 'Smith Bob x@evil.example <b@example.org>', WPDR_Email_Notice::recipient( 'Smith, Bob <x@evil.example>', 'b@example.org' ) );
		$this->assertSame( 'b@example.org', WPDR_Email_Notice::recipient( ' , ', 'b@example.org' ) );
	}

	/**
	 * #9: an External List name with a comma sends one mail to one address.
	 */
	public function test_comma_in_name_sends_one_mail() {
		global $wpdr_en;
		$terms = array( 'post_tag' => self::$tag );
		self::create_list(
			array(
				'terms'     => $terms,
				'addresses' => array(
					array(
						'user_name' => 'Smith, x@evil.example',
						'email'     => 'smith@example.org',
					),
				),
			)
		);
		$doc = self::create_document( array( 'terms' => $terms ) );

		$wpdr_en->ext_notice( $doc, array(), '' );

		$this->assertCount( 1, $this->mails );
		$this->assertSame( 'Smith x@evil.example <smith@example.org>', $this->mails[0]['to'] );
	}

	/**
	 * #10: the attached copy is in a private, unpredictable folder and removed after sending.
	 */
	public function test_attachment_temporary_copy() {
		global $wpdr_en;
		$terms = array( 'post_tag' => self::$tag );
		self::create_list(
			array(
				'terms'     => $terms,
				'attach'    => 1,
				'addresses' => array(
					array(
						'user_name' => 'Ann',
						'email'     => 'ann@example.org',
					),
				),
			)
		);
		$doc    = self::create_document(
			array(
				'terms'     => $terms,
				'post_name' => 'annual-report',
			)
		);
		$attach = self::factory()->attachment->create_object(
			array(
				'file'           => 'en-source.txt',
				'post_parent'    => $doc,
				'post_mime_type' => 'text/plain',
			)
		);
		update_attached_file( $attach, 'en-source.txt' );
		$source = apply_filters( 'document_path', get_attached_file( $attach ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		wp_mkdir_p( dirname( $source ) );
		file_put_contents( $source, 'document body' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		wp_update_post(
			array(
				'ID'           => $doc,
				'post_content' => '<!-- WPDR ' . $attach . ' -->',
			)
		);

		// Look at the copy while the mail is being "sent".
		$seen = array();
		add_filter(
			'pre_wp_mail',
			static function ( $short, $atts ) use ( &$seen ) {
				$file   = $atts['attachments'][0];
				$seen[] = array(
					'file'   => $file,
					'exists' => file_exists( $file ),
					'body'   => file_exists( $file ) ? file_get_contents( $file ) : '', // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					'perms'  => fileperms( dirname( $file ) ) & 0777,
				);
				return $short;
			},
			5,
			2
		);

		$wpdr_en->ext_notice( $doc, array(), '' );
		wp_delete_file( $source );

		$this->assertCount( 1, $seen, 'one mail with an attachment' );
		$this->assertTrue( $seen[0]['exists'] );
		$this->assertSame( 'document body', $seen[0]['body'] );
		$this->assertSame( 'annual-report.txt', basename( $seen[0]['file'] ), 'readable name' );
		$this->assertStringStartsWith( 'wpdr-en-', basename( dirname( $seen[0]['file'] ) ), 'own folder' );
		$this->assertNotSame( rtrim( get_temp_dir(), '/' ), dirname( $seen[0]['file'] ), 'not directly in the temp folder' );
		if ( '\\' !== DIRECTORY_SEPARATOR ) {
			$this->assertSame( 0700, $seen[0]['perms'], 'folder private to the web server user' );
		}
		$this->assertFileDoesNotExist( $seen[0]['file'], 'copy removed' );
		$this->assertDirectoryDoesNotExist( dirname( $seen[0]['file'] ), 'folder removed' );
	}

	/**
	 * #11: the External log escapes what it shows.
	 */
	public function test_external_log_escapes_output() {
		require_once dirname( __DIR__ ) . '/includes/class-wpdr-en-ext-log-table.php';
		wp_set_current_user( self::$users['author'] );
		$table = new WPDR_EN_Ext_Log_Table( array( 'screen' => 'wpdr_en_ext_notice_log' ) );
		$item  = array(
			'id'         => 3,
			'post_id'    => self::create_document(),
			'post_title' => '<script>a</script>',
			'list_id'    => self::create_list(),
			'list_title' => '<img src=x onerror=b>',
			'user_name'  => '<i>n</i>',
			'user_email' => '<u>e</u>',
			'status'     => '<s>s</s>',
			'extra_text' => '</textarea><script>c</script>',
		);

		$html = '';
		foreach ( array( 'post_title', 'list_title', 'user_name', 'user_email', 'status' ) as $column ) {
			$html .= $table->column_default( $item, $column );
		}
		$html .= $table->column_id( $item );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<i>', $html );
		$this->assertSame( 1, substr_count( $html, '</textarea>' ), 'extra text cannot close the textarea' );
		$this->assertStringContainsString( '&lt;img src=x onerror=b&gt;', $html, 'list title as text for non-editors' );
	}

	/**
	 * #11: the internal log escapes what it shows.
	 */
	public function test_internal_log_escapes_output() {
		require_once dirname( __DIR__ ) . '/includes/class-wpdr-en-user-log-table.php';
		$table = new WPDR_EN_User_Log_Table( array( 'screen' => 'wpdr_en_notification_log' ) );
		$item  = array(
			'id'                => 4,
			'post_id'           => self::create_document(),
			'post_title'        => '<script>a</script>',
			'user_id'           => self::$users['subscriber'],
			'user_display_name' => '<b>d</b>',
			'user_email'        => '<u>e</u>',
			'status'            => '<s>s</s>',
			'extra_text'        => '</textarea><script>c</script>',
		);

		$html = '';
		foreach ( array( 'post_title', 'user_display_name', 'user_email', 'status' ) as $column ) {
			$html .= $table->column_default( $item, $column );
		}
		$html .= $table->column_id( $item );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertSame( 1, substr_count( $html, '</textarea>' ) );
	}

	/**
	 * #11: the settings textareas show the stored HTML as text.
	 */
	public function test_settings_textarea_escaped() {
		global $wpdr_en;
		update_option( 'wpdr_en_set_content', 'Hi </textarea><b>there</b>' );

		ob_start();
		$wpdr_en->set_content();
		$html = ob_get_clean();

		$this->assertSame( 1, substr_count( $html, '</textarea>' ) );
		$this->assertStringContainsString( '&lt;b&gt;there&lt;/b&gt;', $html );
	}

	/**
	 * #11 (related): the From address replaces WordPress's default, which uses the site's domain.
	 */
	public function test_from_address_replaces_default() {
		global $wpdr_en;
		update_option( 'wpdr_en_set_email_from_address', 'docs@example.net' );
		$host = wp_parse_url( network_home_url(), PHP_URL_HOST );

		$this->assertSame( 'docs@example.net', $wpdr_en->wp_mail_from( 'wordpress@' . preg_replace( '/^www\./', '', $host ) ) );
		$this->assertSame( 'other@example.org', $wpdr_en->wp_mail_from( 'other@example.org' ), 'other plugins\' addresses kept' );
	}

	/**
	 * #12: bulk subscribe only changes users the current user can edit.
	 */
	public function test_bulk_subscribe_needs_edit_user() {
		add_role(
			'en_lister',
			'Lister',
			array(
				'read'       => true,
				'list_users' => true,
			)
		);
		$lister = self::factory()->user->create( array( 'role' => 'en_lister' ) );
		$target = self::$users['subscriber'];
		delete_user_meta( $target, 'wpdr_en_user_notification' );
		$bulk = new WPDR_EN_All_Users_Bulk_Action();

		wp_set_current_user( $lister );
		$bulk->custom_bulk_action_handler( 'users.php', 'wpdr_subscribe', array( $target ) );
		$this->assertSame( '', get_user_meta( $target, 'wpdr_en_user_notification', true ), 'lister cannot subscribe others' );

		// User 1: on multisite only a network administrator can edit other users.
		wp_set_current_user( 1 );
		$bulk->custom_bulk_action_handler( 'users.php', 'wpdr_subscribe', array( $target ) );
		$this->assertSame( '1', get_user_meta( $target, 'wpdr_en_user_notification', true ), 'administrator can' );

		remove_role( 'en_lister' );
	}
}
