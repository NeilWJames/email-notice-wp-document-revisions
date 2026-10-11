<?php
/**
 * Shared fixtures and helpers for the Email Notice tests.
 *
 * @package Email Notice WP Document Revisions
 */

/**
 * Users, documents, Document External Lists and mail capture.
 *
 * Document and List terms use the built-in `category` (hierarchical) and `post_tag` (flat)
 * taxonomies: the List matching is done in SQL on term relationships, so any taxonomy works,
 * and the built-in ones are always registered.
 */
trait WPDR_EN_Fixtures {

	/**
	 * User IDs by role: administrator, editor, author, contributor, subscriber.
	 *
	 * @var int[]
	 */
	protected static $users = array();

	/**
	 * Mail captured by pre_wp_mail in the current test.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $mails = array();

	/**
	 * Create one user per role. Call from wpSetUpBeforeClass().
	 *
	 * Each user has email <role>@example.com and display name "<role> user".
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 * @return void
	 */
	protected static function create_users( $factory ) {
		foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			self::$users[ $role ] = $factory->user->create(
				array(
					'role'         => $role,
					'user_login'   => 'en_' . $role,
					'user_email'   => $role . '@example.com',
					'display_name' => $role . ' user',
				)
			);
		}
	}

	/**
	 * Opt a user in (or out) of internal notifications.
	 *
	 * @param int  $user_id User ID.
	 * @param bool $notify  Whether to receive notifications.
	 * @return void
	 */
	protected static function subscribe( $user_id, $notify = true ) {
		update_user_meta( $user_id, 'wpdr_en_user_notification', (int) $notify );
		update_user_meta( $user_id, 'wpdr_en_user_attachment', 0 );
	}

	/**
	 * Create a document.
	 *
	 * @param array $args Overrides for wp_insert_post() plus 'terms' => array( taxonomy => term IDs ).
	 * @return int Document ID.
	 */
	protected static function create_document( $args = array() ) {
		$terms = isset( $args['terms'] ) ? $args['terms'] : array();
		unset( $args['terms'] );

		$post_id = wp_insert_post(
			array_merge(
				array(
					'post_type'    => 'document',
					'post_status'  => 'publish',
					'post_title'   => 'Test Document',
					'post_content' => 'one two three four five',
					'post_author'  => self::$users['editor'],
				),
				$args
			)
		);
		foreach ( $terms as $taxonomy => $term_ids ) {
			wp_set_object_terms( $post_id, array_map( 'intval', (array) $term_ids ), $taxonomy );
		}
		return (int) $post_id;
	}

	/**
	 * Create a Document External List.
	 *
	 * The plugin's save hooks expect the admin edit form (nonce, referer, tax_input),
	 * so they are unhooked while the fixture is inserted and the meta is written directly.
	 *
	 * @param array $args Optional settings: 'title'; 'status' (default publish); 'terms' (taxonomy => term IDs);
	 *                    'addresses' (each with 'user_name', 'email', optional 'pause' 0|1); 'tm_rule' (0 = any
	 *                    term matches, 1 = all must); 'pause' (1 = list paused); 'attach' (1 = attach the document).
	 * @return int List ID.
	 */
	protected static function create_list( $args = array() ) {
		global $wpdr_en;
		$args = wp_parse_args(
			$args,
			array(
				'title'     => 'Test List',
				'status'    => 'publish',
				'terms'     => array(),
				'addresses' => array(),
				'tm_rule'   => 0,
				'pause'     => 0,
				'attach'    => 0,
			)
		);

		remove_filter( 'wp_insert_post_empty_content', array( $wpdr_en, 'check_taxonomy_value_set' ), 10 );
		remove_action( 'save_post_doc_ext_list', array( $wpdr_en, 'save_doc_ext_list' ), 10 );
		$list_id = (int) wp_insert_post(
			array(
				'post_type'   => 'doc_ext_list',
				'post_status' => $args['status'],
				'post_title'  => $args['title'],
			)
		);
		add_filter( 'wp_insert_post_empty_content', array( $wpdr_en, 'check_taxonomy_value_set' ), 10, 2 );
		add_action( 'save_post_doc_ext_list', array( $wpdr_en, 'save_doc_ext_list' ), 10, 3 );

		foreach ( $args['terms'] as $taxonomy => $term_ids ) {
			wp_set_object_terms( $list_id, array_map( 'intval', (array) $term_ids ), $taxonomy );
		}
		update_post_meta( $list_id, 'wpdr_en_tm', (int) $args['tm_rule'] );
		update_post_meta( $list_id, 'wpdr_en_pause', (int) $args['pause'] );
		update_post_meta( $list_id, 'wpdr_en_attach', (int) $args['attach'] );

		$users = array();
		foreach ( array_values( $args['addresses'] ) as $num => $address ) {
			$users[] = array(
				'rec_num'   => $num,
				'user_name' => $address['user_name'],
				'email'     => $address['email'],
				'pause'     => isset( $address['pause'] ) ? (int) $address['pause'] : 0,
			);
		}
		if ( ! empty( $users ) ) {
			update_post_meta(
				$list_id,
				'wpdr_en_addressees',
				wp_json_encode(
					array(
						'rec_num' => count( $users ),
						'users'   => $users,
					)
				)
			);
		}
		return $list_id;
	}

	/**
	 * Read a list's stored addresses.
	 *
	 * @param int $list_id List ID.
	 * @return array[] Address records.
	 */
	protected static function list_addresses( $list_id ) {
		$rec = get_post_meta( $list_id, 'wpdr_en_addressees', true );
		if ( empty( $rec ) ) {
			return array();
		}
		$rec = json_decode( $rec, true );
		return $rec['users'];
	}

	/**
	 * Start capturing mail and remove the per-mail delay. Call from set_up().
	 *
	 * @return void
	 */
	protected function start_mail_capture() {
		$this->mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
		add_filter( 'wpdr_en_mail_delay', '__return_zero' );
	}

	/**
	 * Short-circuit wp_mail() and record its arguments.
	 *
	 * @param null|bool $short Short-circuit value.
	 * @param array     $atts  wp_mail() arguments (to, subject, message, headers, attachments).
	 * @return bool True: mail treated as sent.
	 */
	public function capture_mail( $short, $atts ) {
		$this->mails[] = $atts;
		return true;
	}

	/**
	 * Email addresses that mail was sent to, in order.
	 *
	 * Recipients are sent as "Name<email>", so the address is taken from between the brackets.
	 *
	 * @return string[]
	 */
	protected function mailed_addresses() {
		$addresses = array();
		foreach ( $this->mails as $mail ) {
			$to = is_array( $mail['to'] ) ? implode( ',', $mail['to'] ) : (string) $mail['to'];
			if ( preg_match( '/<([^>]+)>/', $to, $match ) ) {
				$addresses[] = $match[1];
			} else {
				$addresses[] = trim( $to );
			}
		}
		sort( $addresses );
		return $addresses;
	}
}
