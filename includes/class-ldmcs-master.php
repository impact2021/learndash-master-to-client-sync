<?php
/**
 * Master site functionality.
 *
 * @package LearnDash_Master_Client_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Master site class.
 */
class LDMCS_Master {

	/**
	 * Instance of this class.
	 *
	 * @var LDMCS_Master
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return LDMCS_Master
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Only initialize if mode is master.
		if ( 'master' === get_option( 'ldmcs_mode', 'client' ) ) {
			$this->init_hooks();
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Hook to notify on content updates (for future webhook implementation).
		add_action( 'save_post', array( $this, 'on_content_save' ), 10, 3 );

		// Add AJAX handlers for push operations.
		add_action( 'wp_ajax_ldmcs_push_course', array( $this, 'handle_push_course' ) );
		add_action( 'wp_ajax_ldmcs_push_content', array( $this, 'handle_push_content' ) );
		add_action( 'wp_ajax_ldmcs_generate_uuids', array( $this, 'handle_generate_uuids' ) );
	}

	/**
	 * Handle content save event.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 */
	public function on_content_save( $post_id, $post, $update ) {
		// Skip autosaves and revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check if this is a LearnDash post type.
		$learndash_post_types = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-question' );
		if ( ! in_array( $post->post_type, $learndash_post_types, true ) ) {
			return;
		}

		// Generate UUID if not present.
		$this->ensure_uuid( $post_id );

		// Log the update.
		LDMCS_Logger::log(
			'master_update',
			$this->get_content_type_from_post_type( $post->post_type ),
			$post_id,
			'updated',
			sprintf( 'Content updated: %s', $post->post_title )
		);

		// Future: Trigger webhook to notify client sites.
		do_action( 'ldmcs_content_updated', $post_id, $post );
	}

	/**
	 * Get content type from post type.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private function get_content_type_from_post_type( $post_type ) {
		$mapping = array(
			'sfwd-courses'  => 'courses',
			'sfwd-lessons'  => 'lessons',
			'sfwd-topic'    => 'topics',
			'sfwd-quiz'     => 'quizzes',
			'sfwd-question' => 'questions',
		);

		return isset( $mapping[ $post_type ] ) ? $mapping[ $post_type ] : 'unknown';
	}

	/**
	 * Ensure post has a UUID.
	 *
	 * @param int $post_id Post ID.
	 * @return string UUID.
	 */
	private function ensure_uuid( $post_id ) {
		$uuid = get_post_meta( $post_id, LDMCS_Sync::UUID_META_KEY, true );
		
		if ( empty( $uuid ) ) {
			$uuid = wp_generate_uuid4();
			update_post_meta( $post_id, LDMCS_Sync::UUID_META_KEY, $uuid );
		}
		
		return $uuid;
	}

	/**
	 * Generate UUIDs for all existing LearnDash content.
	 *
	 * @return array Results with counts per content type.
	 */
	public function generate_all_uuids() {
		$learndash_post_types = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-question' );
		$results = array(
			'total'   => 0,
			'updated' => 0,
			'skipped' => 0,
			'details' => array(),
		);

		foreach ( $learndash_post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				)
			);

			$type_updated = 0;
			$type_skipped = 0;

			foreach ( $posts as $post ) {
				$existing_uuid = get_post_meta( $post->ID, LDMCS_Sync::UUID_META_KEY, true );
				
				if ( empty( $existing_uuid ) ) {
					$this->ensure_uuid( $post->ID );
					$type_updated++;
					$results['updated']++;
				} else {
					$type_skipped++;
					$results['skipped']++;
				}
				
				$results['total']++;
			}

			$results['details'][ $post_type ] = array(
				'updated' => $type_updated,
				'skipped' => $type_skipped,
				'total'   => count( $posts ),
			);
		}

		return $results;
	}

	/**
	 * Handle generate UUIDs AJAX request.
	 */
	public function handle_generate_uuids() {
		check_ajax_referer( 'ldmcs_generate_uuids', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learndash-master-client-sync' ) ) );
		}

		$results = $this->generate_all_uuids();

		LDMCS_Logger::log(
			'master_uuid_generation',
			'all',
			0,
			'success',
			sprintf( 'Generated UUIDs: %d updated, %d skipped, %d total', $results['updated'], $results['skipped'], $results['total'] )
		);

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %1$d: updated count, %2$d: skipped count, %3$d: total count */
					__( 'UUID generation complete! Updated: %1$d, Already had UUIDs: %2$d, Total: %3$d', 'learndash-master-client-sync' ),
					$results['updated'],
					$results['skipped'],
					$results['total']
				),
				'results' => $results,
			)
		);
	}

	/**
	 * Get API key for client authentication.
	 *
	 * @return string
	 */
	public function get_api_key() {
		return get_option( 'ldmcs_api_key', '' );
	}

	/**
	 * Regenerate API key.
	 *
	 * @return string New API key.
	 */
	public function regenerate_api_key() {
		$new_key = wp_generate_password( 32, false );
		update_option( 'ldmcs_api_key', $new_key );
		return $new_key;
	}

	/**
	 * Handle push course AJAX request.
	 */
	public function handle_push_course() {
		check_ajax_referer( 'ldmcs_push_course', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learndash-master-client-sync' ) ) );
		}

		$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;

		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course ID.', 'learndash-master-client-sync' ) ) );
		}

		$course = get_post( $course_id );

		if ( ! $course || 'sfwd-courses' !== $course->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Course not found.', 'learndash-master-client-sync' ) ) );
		}

		// Get client sites count.
		$client_sites = get_option( 'ldmcs_client_sites', array() );
		$client_count = count( $client_sites );

		if ( 0 === $client_count ) {
			wp_send_json_error( array( 'message' => __( 'No client sites connected. Client sites must verify their connection first.', 'learndash-master-client-sync' ) ) );
		}

		// Push the course and all related content to client sites.
		$results = $this->push_to_clients( $course_id, 'courses' );

		if ( $results['success'] > 0 ) {
			$message = sprintf(
				/* translators: %1$s: course title, %2$d: successful pushes, %3$d: failed pushes */
				__( 'Course "%1$s" pushed to %2$d client site(s) successfully. %3$d failed.', 'learndash-master-client-sync' ),
				$course->post_title,
				$results['success'],
				$results['failed']
			);

			wp_send_json_success(
				array(
					'message'      => $message,
					'course_id'    => $course_id,
					'course_title' => $course->post_title,
					'results'      => $results,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: course title */
						__( 'Failed to push course "%s" to any client sites.', 'learndash-master-client-sync' ),
						$course->post_title
					),
					'results' => $results,
				)
			);
		}
	}

	/**
	 * Handle push content AJAX request (generic for all content types).
	 */
	public function handle_push_content() {
		check_ajax_referer( 'ldmcs_push_content', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learndash-master-client-sync' ) ) );
		}

		$content_id   = isset( $_POST['content_id'] ) ? absint( $_POST['content_id'] ) : 0;
		$content_type = isset( $_POST['content_type'] ) ? sanitize_text_field( $_POST['content_type'] ) : '';

		if ( ! $content_id || ! $content_type ) {
			wp_send_json_error( array( 'message' => __( 'Invalid content ID or type.', 'learndash-master-client-sync' ) ) );
		}

		// Validate content type.
		$valid_types = array( 'courses', 'lessons', 'topics', 'quizzes', 'questions' );
		if ( ! in_array( $content_type, $valid_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid content type.', 'learndash-master-client-sync' ) ) );
		}

		// Get post type from content type.
		$post_type_map = array(
			'courses'   => 'sfwd-courses',
			'lessons'   => 'sfwd-lessons',
			'topics'    => 'sfwd-topic',
			'quizzes'   => 'sfwd-quiz',
			'questions' => 'sfwd-question',
		);

		$post = get_post( $content_id );

		if ( ! $post || $post->post_type !== $post_type_map[ $content_type ] ) {
			wp_send_json_error( array( 'message' => __( 'Content not found.', 'learndash-master-client-sync' ) ) );
		}

		// Get client sites count.
		$client_sites = get_option( 'ldmcs_client_sites', array() );
		$client_count = count( $client_sites );

		if ( 0 === $client_count ) {
			wp_send_json_error( array( 'message' => __( 'No client sites connected. Client sites must verify their connection first.', 'learndash-master-client-sync' ) ) );
		}

		// Push the content to client sites.
		$results = $this->push_to_clients( $content_id, $content_type );

		if ( $results['success'] > 0 ) {
			$message = sprintf(
				/* translators: %1$s: content title, %2$d: successful pushes, %3$d: failed pushes */
				__( '"%1$s" pushed to %2$d client site(s) successfully. %3$d failed.', 'learndash-master-client-sync' ),
				$post->post_title,
				$results['success'],
				$results['failed']
			);

			wp_send_json_success(
				array(
					'message'      => $message,
					'content_id'   => $content_id,
					'content_type' => $content_type,
					'content_title' => $post->post_title,
					'results'      => $results,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: content title */
						__( 'Failed to push "%s" to any client sites.', 'learndash-master-client-sync' ),
						$post->post_title
					),
					'results' => $results,
				)
			);
		}
	}

	/**
	 * Register a client site connection.
	 *
	 * @param string $site_url  Client site URL.
	 * @param string $site_name Client site name.
	 * @return bool Success status.
	 */
	public function register_client_site( $site_url, $site_name = '' ) {
		$client_sites = get_option( 'ldmcs_client_sites', array() );

		// Use URL as unique identifier with secure hash.
		$client_id = hash( 'sha256', $site_url );

		// If site name is empty, use URL.
		if ( empty( $site_name ) ) {
			$site_name = $site_url;
		}

		$current_time = current_time( 'mysql' );

		if ( isset( $client_sites[ $client_id ] ) ) {
			// Update existing client site.
			$client_sites[ $client_id ]['last_connected'] = $current_time;
			$client_sites[ $client_id ]['site_name']      = $site_name;
		} else {
			// Add new client site.
			$client_sites[ $client_id ] = array(
				'site_url'        => $site_url,
				'site_name'       => $site_name,
				'first_connected' => $current_time,
				'last_connected'  => $current_time,
			);
		}

		return update_option( 'ldmcs_client_sites', $client_sites );
	}

	/**
	 * Push content to client sites.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $content_type Content type.
	 * @return array Push results.
	 */
	public function push_to_clients( $post_id, $content_type ) {
		$client_sites = get_option( 'ldmcs_client_sites', array() );
		$results      = array(
			'success' => 0,
			'failed'  => 0,
			'details' => array(),
		);

		if ( empty( $client_sites ) ) {
			return $results;
		}

		// Get the post data.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $results;
		}

		// Prepare the content item using the same format as the API.
		$api       = LDMCS_API::get_instance();
		$item_data = $this->prepare_push_item( $post, $content_type );

		if ( ! $item_data ) {
			return $results;
		}

		// Get related content for courses (lessons, topics, quizzes, questions).
		$items = array(
			array(
				'type' => $content_type,
				'data' => $item_data,
			),
		);

		if ( 'courses' === $content_type ) {
			$items = array_merge( $items, $this->get_related_course_content( $post_id ) );
		}

		// Push to each client site.
		foreach ( $client_sites as $client_id => $client_data ) {
			$site_url = $client_data['site_url'];

			$push_result = $this->push_to_single_client( $site_url, $items );

			if ( is_wp_error( $push_result ) ) {
				$results['failed']++;
				$results['details'][ $site_url ] = array(
					'success' => false,
					'message' => $push_result->get_error_message(),
				);

				LDMCS_Logger::log(
					'master_push',
					$content_type,
					$post_id,
					'error',
					sprintf( 'Failed to push to %s: %s', $site_url, $push_result->get_error_message() )
				);
			} else {
				$results['success']++;
				$results['details'][ $site_url ] = array(
					'success' => true,
					'message' => 'Content pushed successfully',
					'data'    => $push_result,
				);

				LDMCS_Logger::log(
					'master_push',
					$content_type,
					$post_id,
					'success',
					sprintf( 'Successfully pushed to %s', $site_url )
				);
			}
		}

		return $results;
	}

	/**
	 * Push content to a single client site.
	 *
	 * @param string $site_url Client site URL.
	 * @param array  $items    Content items to push.
	 * @return array|WP_Error Response or error.
	 */
	private function push_to_single_client( $site_url, $items ) {
		$url = trailingslashit( $site_url ) . 'wp-json/ldmcs/v1/receive';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'    => 'application/json',
					'X-LDMCS-API-Key' => $this->get_api_key(),
				),
				'body'    => wp_json_encode( array( 'items' => $items ) ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$body    = wp_remote_retrieve_body( $response );
			$data    = json_decode( $body, true );
			$message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'learndash-master-client-sync' );
			return new WP_Error( 'ldmcs_push_error', $message );
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}

	/**
	 * Prepare a content item for pushing.
	 *
	 * @param WP_Post $post         Post object.
	 * @param string  $content_type Content type.
	 * @return array|null Item data or null on failure.
	 */
	private function prepare_push_item( $post, $content_type ) {
		// Ensure UUID exists.
		$uuid = $this->ensure_uuid( $post->ID );

		$item = array(
			'id'              => $uuid,
			'title'           => $post->post_title,
			'content'         => $post->post_content,
			'excerpt'         => $post->post_excerpt,
			'status'          => $post->post_status,
			'slug'            => $post->post_name,
			'date'            => $post->post_date,
			'modified'        => $post->post_modified,
			'parent'          => $post->post_parent,
			'menu_order'      => $post->menu_order,
			'meta'            => $this->get_learndash_meta( $post->ID ),
			'featured_image'  => get_post_thumbnail_id( $post->ID ),
			'taxonomies'      => $this->get_post_taxonomies( $post->ID ),
		);

		return $item;
	}

	/**
	 * Get LearnDash specific metadata.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_learndash_meta( $post_id ) {
		$meta = array();

		// Get all post meta.
		$all_meta = get_post_meta( $post_id );

		// Use shared unsafe patterns from sync class.
		$excluded_patterns = LDMCS_Sync::get_unsafe_meta_patterns();

		// Filter LearnDash specific meta keys but exclude user data.
		foreach ( $all_meta as $key => $value ) {
			// Skip if matches excluded patterns.
			$should_exclude = false;
			foreach ( $excluded_patterns as $pattern ) {
				if ( strpos( $key, $pattern ) !== false ) {
					$should_exclude = true;
					break;
				}
			}

			if ( $should_exclude ) {
				continue;
			}

			// Include LearnDash configuration meta only (not user data).
			if ( strpos( $key, '_' ) === 0 || strpos( $key, 'ld_' ) === 0 || strpos( $key, 'course_' ) === 0 ) {
				$meta[ $key ] = maybe_unserialize( $value[0] );
			}
		}

		return $meta;
	}

	/**
	 * Get post taxonomies.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_post_taxonomies( $post_id ) {
		$taxonomies = array();
		$tax_names  = get_object_taxonomies( get_post_type( $post_id ) );

		foreach ( $tax_names as $tax_name ) {
			$terms = get_the_terms( $post_id, $tax_name );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$taxonomies[ $tax_name ] = wp_list_pluck( $terms, 'slug' );
			}
		}

		return $taxonomies;
	}

	/**
	 * Get related course content (lessons, topics, quizzes, questions).
	 *
	 * @param int $course_id Course ID.
	 * @return array Related content items.
	 */
	private function get_related_course_content( $course_id ) {
		$items = array();

		// Get lessons associated with this course.
		$lessons = learndash_get_course_lessons_list( $course_id );
		if ( ! empty( $lessons ) ) {
			foreach ( $lessons as $lesson ) {
				$lesson_post = get_post( $lesson['id'] );
				if ( $lesson_post ) {
					$items[] = array(
						'type' => 'lessons',
						'data' => $this->prepare_push_item( $lesson_post, 'lessons' ),
					);

					// Get topics for this lesson.
					$topics = learndash_get_topic_list( $lesson['id'], $course_id );
					if ( ! empty( $topics ) ) {
						foreach ( $topics as $topic ) {
							$topic_post = get_post( $topic->ID );
							if ( $topic_post ) {
								$items[] = array(
									'type' => 'topics',
									'data' => $this->prepare_push_item( $topic_post, 'topics' ),
								);
							}
						}
					}
				}
			}
		}

		// Get quizzes associated with this course.
		$quizzes = learndash_get_course_quiz_list( $course_id );
		if ( ! empty( $quizzes ) ) {
			foreach ( $quizzes as $quiz ) {
				$quiz_post = get_post( $quiz['id'] );
				if ( $quiz_post ) {
					$items[] = array(
						'type' => 'quizzes',
						'data' => $this->prepare_push_item( $quiz_post, 'quizzes' ),
					);

					// Get questions for this quiz.
					$questions = learndash_get_quiz_questions( $quiz['id'] );
					if ( ! empty( $questions ) ) {
						foreach ( $questions as $question ) {
							$question_post = get_post( $question['id'] );
							if ( $question_post ) {
								$items[] = array(
									'type' => 'questions',
									'data' => $this->prepare_push_item( $question_post, 'questions' ),
								);
							}
						}
					}
				}
			}
		}

		return $items;
	}
}
