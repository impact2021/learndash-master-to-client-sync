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
			$base_message = sprintf(
				/* translators: %s: course title */
				__( 'Failed to push course "%s" to any client sites.', 'learndash-master-client-sync' ),
				esc_html( $course->post_title )
			);
			
			$error_message = $this->build_push_error_message( $base_message, $results );
			
			wp_send_json_error(
				array(
					'message' => $error_message,
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

		// Get post from content ID.
		$post = get_post( $content_id );

		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Content not found.', 'learndash-master-client-sync' ) ) );
		}

		// Verify the post type matches the content type.
		$actual_content_type = LDMCS_Sync::get_content_type_from_post_type( $post->post_type );
		if ( $actual_content_type !== $content_type ) {
			wp_send_json_error( array( 'message' => __( 'Content type mismatch.', 'learndash-master-client-sync' ) ) );
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
			$base_message = sprintf(
				/* translators: %s: content title */
				__( 'Failed to push "%s" to any client sites.', 'learndash-master-client-sync' ),
				esc_html( $post->post_title )
			);
			
			$error_message = $this->build_push_error_message( $base_message, $results );
			
			wp_send_json_error(
				array(
					'message' => $error_message,
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
			$related_content = $this->get_related_course_content( $post_id );
			$items = array_merge( $items, $related_content );
			
			// Get UUID for logging
			$uuid = get_post_meta( $post_id, LDMCS_Sync::UUID_META_KEY, true );
			$log_id = ! empty( $uuid ) ? $uuid : $post_id;
			
			// Log how many related items were found.
			LDMCS_Logger::log(
				'master_push',
				$content_type,
				$log_id,
				'info',
				sprintf( 'Found %d related content items for course "%s"', count( $related_content ), $post->post_title )
			);
		}

		// Get UUID for logging
		$uuid = get_post_meta( $post_id, LDMCS_Sync::UUID_META_KEY, true );
		$log_id = ! empty( $uuid ) ? $uuid : $post_id;

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
					$log_id,
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

				// Log success with sync statistics
				$sync_stats = '';
				if ( isset( $push_result['synced'] ) || isset( $push_result['skipped'] ) || isset( $push_result['errors'] ) ) {
					$sync_stats = sprintf(
						' | Synced: %d | Skipped: %d | Errors: %d',
						isset( $push_result['synced'] ) ? $push_result['synced'] : 0,
						isset( $push_result['skipped'] ) ? $push_result['skipped'] : 0,
						isset( $push_result['errors'] ) ? $push_result['errors'] : 0
					);
				}

				LDMCS_Logger::log(
					'master_push',
					$content_type,
					$log_id,
					'success',
					sprintf( 'Successfully pushed to %s%s', $site_url, $sync_stats )
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

		// Log initial state for debugging.
		LDMCS_Logger::log(
			'master_push',
			'courses',
			$course_id,
			'debug',
			sprintf( 'Starting get_related_course_content for course ID %d', $course_id )
		);

		// Use LearnDash's core function to get all course steps if available (LearnDash 3.0+).
		if ( function_exists( 'learndash_course_get_steps' ) ) {
			// Get all steps (lessons and topics) for the course.
			$course_steps = learndash_course_get_steps( $course_id );
			
			// Log what we found.
			LDMCS_Logger::log(
				'master_push',
				'courses',
				$course_id,
				'debug',
				sprintf( 'learndash_course_get_steps returned %d steps', is_array( $course_steps ) ? count( $course_steps ) : 0 )
			);
			
			// If learndash_course_get_steps returns empty, try reading directly from metadata.
			// This is important because learndash_course_get_steps only returns published posts.
			if ( empty( $course_steps ) ) {
				LDMCS_Logger::log(
					'master_push',
					'courses',
					$course_id,
					'debug',
					'learndash_course_get_steps returned empty, trying direct metadata read'
				);
				
				$course_steps = $this->get_course_steps_from_meta( $course_id );
				
				LDMCS_Logger::log(
					'master_push',
					'courses',
					$course_id,
					'debug',
					sprintf( 'Direct metadata read returned %d steps', count( $course_steps ) )
				);
			}
			
			if ( ! empty( $course_steps ) ) {
				foreach ( $course_steps as $step_id ) {
					$step_post = get_post( $step_id );
					if ( ! $step_post ) {
						continue;
					}

					// Determine the content type based on post type.
					$content_type = LDMCS_Sync::get_content_type_from_post_type( $step_post->post_type );
					
					if ( $content_type ) {
						$items[] = array(
							'type' => $content_type,
							'data' => $this->prepare_push_item( $step_post, $content_type ),
						);
						
						// Get quizzes attached to this lesson or topic.
						if ( 'lessons' === $content_type || 'topics' === $content_type ) {
							$step_quizzes = $this->get_step_quizzes( $step_id, $step_post->post_type );
							if ( ! empty( $step_quizzes ) ) {
								foreach ( $step_quizzes as $quiz_id ) {
									$quiz_post = get_post( $quiz_id );
									if ( $quiz_post && 'sfwd-quiz' === $quiz_post->post_type ) {
										// Check if not already added to avoid duplicates.
										if ( ! $this->is_quiz_already_added( $quiz_id, $items ) ) {
											$items[] = array(
												'type' => 'quizzes',
												'data' => $this->prepare_push_item( $quiz_post, 'quizzes' ),
											);
											
											// Get questions for this quiz.
											$items = array_merge( $items, $this->get_quiz_questions( $quiz_id ) );
										}
									}
								}
							}
						}
					}
				}
			}

			// Get course-level quizzes using LearnDash 3.0+ method.
			$course_quizzes = learndash_course_get_children( $course_id, 'sfwd-quiz' );
			
			// Log what we found for quizzes.
			LDMCS_Logger::log(
				'master_push',
				'courses',
				$course_id,
				'debug',
				sprintf( 'learndash_course_get_children (quizzes) returned %d quizzes', is_array( $course_quizzes ) ? count( $course_quizzes ) : 0 )
			);
			
			// If learndash_course_get_children returns empty, try reading from metadata.
			if ( empty( $course_quizzes ) ) {
				LDMCS_Logger::log(
					'master_push',
					'courses',
					$course_id,
					'debug',
					'learndash_course_get_children returned empty, trying metadata read for quizzes'
				);
				
				$course_quizzes = $this->get_course_quizzes_meta( $course_id );
				
				LDMCS_Logger::log(
					'master_push',
					'courses',
					$course_id,
					'debug',
					sprintf( 'Metadata read for quizzes returned %d quizzes', count( $course_quizzes ) )
				);
			}
			
			if ( ! empty( $course_quizzes ) && is_array( $course_quizzes ) ) {
				foreach ( $course_quizzes as $quiz_id ) {
					$quiz_post = get_post( $quiz_id );
					if ( $quiz_post ) {
						// Check if not already added to avoid duplicates.
						if ( ! $this->is_quiz_already_added( $quiz_id, $items ) ) {
							$items[] = array(
								'type' => 'quizzes',
								'data' => $this->prepare_push_item( $quiz_post, 'quizzes' ),
							);

							// Get questions for this quiz.
							$items = array_merge( $items, $this->get_quiz_questions( $quiz_id ) );
						}
					}
				}
			}
		} else {
			// Fallback for older LearnDash versions or alternative approach.
			LDMCS_Logger::log(
				'master_push',
				'courses',
				$course_id,
				'debug',
				'learndash_course_get_steps function not available, using legacy method'
			);
			
			$items = $this->get_related_course_content_legacy( $course_id );
			
			LDMCS_Logger::log(
				'master_push',
				'courses',
				$course_id,
				'debug',
				sprintf( 'Legacy method returned %d items', count( $items ) )
			);
		}

		LDMCS_Logger::log(
			'master_push',
			'courses',
			$course_id,
			'debug',
			sprintf( 'Total items collected: %d', count( $items ) )
		);

		return $items;
	}

	/**
	 * Get related course content using legacy methods (fallback).
	 *
	 * @param int $course_id Course ID.
	 * @return array Related content items.
	 */
	private function get_related_course_content_legacy( $course_id ) {
		$items = array();

		// Get lessons using meta query as fallback.
		$lesson_ids = $this->get_course_lessons_meta( $course_id );
		if ( ! empty( $lesson_ids ) ) {
			foreach ( $lesson_ids as $lesson_id ) {
				$lesson_post = get_post( $lesson_id );
				if ( $lesson_post && 'sfwd-lessons' === $lesson_post->post_type ) {
					$items[] = array(
						'type' => 'lessons',
						'data' => $this->prepare_push_item( $lesson_post, 'lessons' ),
					);

					// Get topics for this lesson.
					$topic_ids = $this->get_lesson_topics_meta( $lesson_id, $course_id );
					if ( ! empty( $topic_ids ) ) {
						foreach ( $topic_ids as $topic_id ) {
							$topic_post = get_post( $topic_id );
							if ( $topic_post && 'sfwd-topic' === $topic_post->post_type ) {
								$items[] = array(
									'type' => 'topics',
									'data' => $this->prepare_push_item( $topic_post, 'topics' ),
								);
							}
						}
					}

					// Get quizzes for this lesson.
					$lesson_quiz_ids = $this->get_lesson_quizzes_meta( $lesson_id );
					if ( ! empty( $lesson_quiz_ids ) ) {
						foreach ( $lesson_quiz_ids as $quiz_id ) {
							$quiz_post = get_post( $quiz_id );
							if ( $quiz_post && 'sfwd-quiz' === $quiz_post->post_type ) {
								$items[] = array(
									'type' => 'quizzes',
									'data' => $this->prepare_push_item( $quiz_post, 'quizzes' ),
								);

								// Get questions for this quiz.
								$items = array_merge( $items, $this->get_quiz_questions( $quiz_id ) );
							}
						}
					}
				}
			}
		}

		// Get course-level quizzes.
		$course_quiz_ids = $this->get_course_quizzes_meta( $course_id );
		if ( ! empty( $course_quiz_ids ) ) {
			foreach ( $course_quiz_ids as $quiz_id ) {
				$quiz_post = get_post( $quiz_id );
				if ( $quiz_post && 'sfwd-quiz' === $quiz_post->post_type ) {
					// Check if already added from lesson processing.
					if ( ! $this->is_quiz_already_added( $quiz_id, $items ) ) {
						$items[] = array(
							'type' => 'quizzes',
							'data' => $this->prepare_push_item( $quiz_post, 'quizzes' ),
						);

						// Get questions for this quiz.
						$items = array_merge( $items, $this->get_quiz_questions( $quiz_id ) );
					}
				}
			}
		}

		return $items;
	}

	/**
	 * Get quiz questions.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array Question items.
	 */
	private function get_quiz_questions( $quiz_id ) {
		$items = array();

		// Try different methods to get quiz questions.
		$question_ids = array();

		// Method 1: Use LearnDash function if available.
		if ( function_exists( 'learndash_get_quiz_questions' ) ) {
			$questions = learndash_get_quiz_questions( $quiz_id );
			if ( is_array( $questions ) ) {
				foreach ( $questions as $question ) {
					$question_id = $this->extract_question_id( $question );
					if ( $question_id ) {
						$question_ids[] = $question_id;
					}
				}
			}
		}

		// Method 2: Get from post meta as fallback.
		if ( empty( $question_ids ) ) {
			$question_ids = $this->get_quiz_questions_meta( $quiz_id );
		}

		// Process question IDs.
		if ( ! empty( $question_ids ) ) {
			foreach ( $question_ids as $question_id ) {
				$question_post = get_post( $question_id );
				if ( $question_post && 'sfwd-question' === $question_post->post_type ) {
					$items[] = array(
						'type' => 'questions',
						'data' => $this->prepare_push_item( $question_post, 'questions' ),
					);
				}
			}
		}

		return $items;
	}

	/**
	 * Get course lessons from meta.
	 *
	 * @param int $course_id Course ID.
	 * @return array Lesson IDs.
	 */
	private function get_course_lessons_meta( $course_id ) {
		$lessons = get_post_meta( $course_id, 'ld_course_steps', true );
		if ( ! empty( $lessons ) && is_array( $lessons ) && isset( $lessons['sfwd-lessons'] ) ) {
			return array_keys( $lessons['sfwd-lessons'] );
		}
		
		// Alternative: Get from course_lessons meta key.
		$lesson_ids = get_post_meta( $course_id, 'course_lessons', true );
		if ( ! empty( $lesson_ids ) && is_array( $lesson_ids ) ) {
			return $lesson_ids;
		}

		return array();
	}

	/**
	 * Get lesson topics from meta.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $course_id Course ID.
	 * @return array Topic IDs.
	 */
	private function get_lesson_topics_meta( $lesson_id, $course_id ) {
		// Try ld_course_steps meta first.
		$steps = get_post_meta( $course_id, 'ld_course_steps', true );
		// LearnDash uses 'h' prefix for hierarchical lesson keys in the steps array.
		// Format: $steps['sfwd-lessons']['h123'] where 123 is the lesson ID.
		if ( ! empty( $steps ) && is_array( $steps ) && isset( $steps['sfwd-lessons'][ 'h' . $lesson_id ] ) ) {
			return array_keys( $steps['sfwd-lessons'][ 'h' . $lesson_id ] );
		}

		// Alternative: Get topics by lesson parent.
		$topic_query = new WP_Query(
			array(
				'post_type'      => 'sfwd-topic',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'   => 'lesson_id',
						'value' => $lesson_id,
					),
					array(
						'key'   => 'course_id',
						'value' => $course_id,
					),
				),
				'fields'         => 'ids',
			)
		);

		return $topic_query->posts;
	}

	/**
	 * Get course quizzes from meta.
	 *
	 * @param int $course_id Course ID.
	 * @return array Quiz IDs.
	 */
	private function get_course_quizzes_meta( $course_id ) {
		$quizzes = get_post_meta( $course_id, 'ld_course_steps', true );
		if ( ! empty( $quizzes ) && is_array( $quizzes ) && isset( $quizzes['sfwd-quiz'] ) ) {
			return array_keys( $quizzes['sfwd-quiz'] );
		}

		// Alternative: Get from course_quiz meta key.
		$quiz_ids = get_post_meta( $course_id, 'course_quiz', true );
		if ( ! empty( $quiz_ids ) && is_array( $quiz_ids ) ) {
			return $quiz_ids;
		}

		return array();
	}

	/**
	 * Get lesson quizzes from meta.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array Quiz IDs.
	 */
	private function get_lesson_quizzes_meta( $lesson_id ) {
		$quiz_ids = get_post_meta( $lesson_id, 'lesson_quiz', true );
		if ( ! empty( $quiz_ids ) && is_array( $quiz_ids ) ) {
			return $quiz_ids;
		}

		return array();
	}

	/**
	 * Get quizzes for a lesson or topic using modern LearnDash API.
	 *
	 * @param int    $step_id   Lesson or Topic ID.
	 * @param string $post_type Post type ('sfwd-lessons' or 'sfwd-topic').
	 * @return array Quiz IDs.
	 */
	private function get_step_quizzes( $step_id, $post_type ) {
		$quiz_ids = array();
		
		// For lessons, use the lesson_quiz meta key or LearnDash function.
		if ( 'sfwd-lessons' === $post_type ) {
			// Try LearnDash 3.0+ function first.
			if ( function_exists( 'learndash_get_lesson_quiz_list' ) ) {
				$quizzes = learndash_get_lesson_quiz_list( $step_id );
				if ( ! empty( $quizzes ) && is_array( $quizzes ) ) {
					$quiz_ids = array_keys( $quizzes );
				}
			}
			
			// Fallback to meta.
			if ( empty( $quiz_ids ) ) {
				$quiz_ids = $this->get_lesson_quizzes_meta( $step_id );
			}
		}
		
		// For topics, check if there's a topic_quiz meta or use LearnDash function.
		if ( 'sfwd-topic' === $post_type ) {
			// Try LearnDash 3.0+ function first.
			if ( function_exists( 'learndash_get_topic_quiz_list' ) ) {
				$quizzes = learndash_get_topic_quiz_list( $step_id );
				if ( ! empty( $quizzes ) && is_array( $quizzes ) ) {
					$quiz_ids = array_keys( $quizzes );
				}
			}
			
			// Fallback to meta.
			if ( empty( $quiz_ids ) ) {
				$topic_quiz_ids = get_post_meta( $step_id, 'topic_quiz', true );
				if ( ! empty( $topic_quiz_ids ) && is_array( $topic_quiz_ids ) ) {
					$quiz_ids = $topic_quiz_ids;
				}
			}
		}
		
		return $quiz_ids;
	}

	/**
	 * Get quiz questions from meta.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array Question IDs.
	 */
	private function get_quiz_questions_meta( $quiz_id ) {
		// Get questions from quiz meta.
		$questions = get_post_meta( $quiz_id, 'ld_quiz_questions', true );
		if ( ! empty( $questions ) && is_array( $questions ) ) {
			return $questions;
		}

		// Alternative: Get from quiz_question_list.
		$question_ids = get_post_meta( $quiz_id, 'quiz_question_list', true );
		if ( ! empty( $question_ids ) && is_array( $question_ids ) ) {
			return $question_ids;
		}

		return array();
	}

	/**
	 * Get course steps directly from metadata (doesn't filter by post status).
	 * 
	 * This method reads the ld_course_steps metadata directly, which contains
	 * all steps regardless of their post status. This is useful when 
	 * learndash_course_get_steps returns empty because it only returns published posts.
	 *
	 * @param int $course_id Course ID.
	 * @return array Array of step post IDs.
	 */
	private function get_course_steps_from_meta( $course_id ) {
		$step_ids = array();
		
		// Get the ld_course_steps metadata.
		$course_steps = get_post_meta( $course_id, 'ld_course_steps', true );
		
		if ( empty( $course_steps ) || ! is_array( $course_steps ) ) {
			return $step_ids;
		}
		
		// Process lessons (which may have nested topics).
		if ( isset( $course_steps['sfwd-lessons'] ) && is_array( $course_steps['sfwd-lessons'] ) ) {
			foreach ( $course_steps['sfwd-lessons'] as $lesson_key => $topics ) {
				// Lesson keys are prefixed with 'h' (e.g., 'h123').
				$lesson_id = intval( str_replace( 'h', '', $lesson_key ) );
				if ( $lesson_id > 0 ) {
					$step_ids[] = $lesson_id;
					
					// Add topics nested under this lesson.
					if ( is_array( $topics ) ) {
						foreach ( array_keys( $topics ) as $topic_id ) {
							$topic_id = intval( $topic_id );
							if ( $topic_id > 0 ) {
								$step_ids[] = $topic_id;
							}
						}
					}
				}
			}
		}
		
		// Note: We don't include quizzes here as they are handled separately.
		// The ld_course_steps structure includes quizzes in the 'sfwd-quiz' key,
		// but we handle those separately to avoid duplication.
		
		return $step_ids;
	}

	/**
	 * Check if a quiz is already in the items array.
	 *
	 * @param int   $quiz_id Quiz ID to check.
	 * @param array $items   Array of items to search.
	 * @return bool True if quiz is already added, false otherwise.
	 */
	private function is_quiz_already_added( $quiz_id, $items ) {
		$quiz_uuid = get_post_meta( $quiz_id, LDMCS_Sync::UUID_META_KEY, true );
		
		foreach ( $items as $item ) {
			if ( 'quizzes' !== $item['type'] || ! isset( $item['data']['id'] ) ) {
				continue;
			}
			
			$item_uuid = $item['data']['id'];
			
			// Match by UUID if both have UUIDs, otherwise match by post ID.
			if ( ! empty( $quiz_uuid ) && $item_uuid === $quiz_uuid ) {
				return true;
			}
			
			if ( empty( $quiz_uuid ) && $item['data']['id'] === $quiz_id ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Extract question ID from question data (handles array or object format).
	 *
	 * @param array|object $question Question data from LearnDash.
	 * @return int|null Question ID or null if not found.
	 */
	private function extract_question_id( $question ) {
		if ( is_array( $question ) && isset( $question['id'] ) ) {
			return $question['id'];
		}
		
		if ( is_object( $question ) && isset( $question->ID ) ) {
			return $question->ID;
		}
		
		return null;
	}

	/**
	 * Build detailed error message with per-site failure reasons.
	 *
	 * @param string $base_message The base error message.
	 * @param array  $results      The push results array with details.
	 * @return string Complete error message with details.
	 */
	private function build_push_error_message( $base_message, $results ) {
		$error_details = array();
		
		if ( isset( $results['details'] ) && ! empty( $results['details'] ) ) {
			foreach ( $results['details'] as $site_url => $detail ) {
				if ( ! $detail['success'] ) {
					$error_details[] = sprintf(
						'%s: %s',
						esc_html( $site_url ),
						esc_html( $detail['message'] )
					);
				}
			}
		}
		
		if ( ! empty( $error_details ) ) {
			$base_message .= ' ' . __( 'Reasons:', 'learndash-master-client-sync' ) . ' ' . implode( '; ', $error_details );
		}
		
		return $base_message;
	}
}
