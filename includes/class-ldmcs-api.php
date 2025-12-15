<?php
/**
 * REST API handler for master-client communication.
 *
 * @package LearnDash_Master_Client_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API class.
 */
class LDMCS_API {

	/**
	 * Instance of this class.
	 *
	 * @var LDMCS_API
	 */
	private static $instance = null;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'ldmcs/v1';

	/**
	 * Get instance.
	 *
	 * @return LDMCS_API
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Get content endpoint.
		register_rest_route(
			$this->namespace,
			'/content/(?P<type>[a-z]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_content' ),
				'permission_callback' => array( $this, 'check_api_key' ),
				'args'                => array(
					'type' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_content_type' ),
					),
					'page' => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Get single content item endpoint.
		register_rest_route(
			$this->namespace,
			'/content/(?P<type>[a-z]+)/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_single_content' ),
				'permission_callback' => array( $this, 'check_api_key' ),
				'args'                => array(
					'type' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_content_type' ),
					),
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Verify connection endpoint.
		register_rest_route(
			$this->namespace,
			'/verify',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'verify_connection' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		// Receive pushed content endpoint (for client sites).
		register_rest_route(
			$this->namespace,
			'/receive',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'receive_pushed_content' ),
				'permission_callback' => array( $this, 'check_master_api_key' ),
			)
		);
	}

	/**
	 * Validate content type.
	 *
	 * @param string $type Content type.
	 * @return bool
	 */
	public function validate_content_type( $type ) {
		$valid_types = array( 'courses', 'lessons', 'topics', 'quizzes', 'questions' );
		return in_array( $type, $valid_types, true );
	}

	/**
	 * Check API key permission.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_api_key( $request ) {
		$api_key = $request->get_header( 'X-LDMCS-API-Key' );

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'ldmcs_missing_api_key',
				__( 'API key is missing.', 'learndash-master-client-sync' ),
				array( 'status' => 401 )
			);
		}

		$stored_api_key = get_option( 'ldmcs_api_key' );

		if ( $api_key !== $stored_api_key ) {
			return new WP_Error(
				'ldmcs_invalid_api_key',
				__( 'Invalid API key.', 'learndash-master-client-sync' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check master API key permission for receiving pushed content.
	 * On client sites, this validates that the push request is coming from the configured master site.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_master_api_key( $request ) {
		$api_key = $request->get_header( 'X-LDMCS-API-Key' );

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'ldmcs_missing_api_key',
				__( 'API key is missing.', 'learndash-master-client-sync' ),
				array( 'status' => 401 )
			);
		}

		// On client sites, check against the master's API key that was configured
		$master_api_key = get_option( 'ldmcs_master_api_key' );

		if ( empty( $master_api_key ) ) {
			return new WP_Error(
				'ldmcs_not_configured',
				__( 'Master API key is not configured.', 'learndash-master-client-sync' ),
				array( 'status' => 403 )
			);
		}

		if ( $api_key !== $master_api_key ) {
			return new WP_Error(
				'ldmcs_invalid_master_api_key',
				__( 'Invalid master API key. The API key does not match the configured master site API key.', 'learndash-master-client-sync' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get content callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_content( $request ) {
		$type     = $request->get_param( 'type' );
		$page     = $request->get_param( 'page' );
		$per_page = min( $request->get_param( 'per_page' ), 50 ); // Max 50 items per page.

		$post_type = $this->get_post_type_from_content_type( $type );

		if ( ! $post_type ) {
			return new WP_Error(
				'ldmcs_invalid_content_type',
				__( 'Invalid content type.', 'learndash-master-client-sync' ),
				array( 'status' => 400 )
			);
		}

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $args );

		$items = array();
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$items[] = $this->prepare_content_item( $post, $type );
			}
		}

		$response = new WP_REST_Response(
			array(
				'items'       => $items,
				'total'       => $query->found_posts,
				'total_pages' => $query->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);

		return $response;
	}

	/**
	 * Get single content item callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_single_content( $request ) {
		$type = $request->get_param( 'type' );
		$id   = $request->get_param( 'id' );

		$post = get_post( $id );

		if ( ! $post ) {
			return new WP_Error(
				'ldmcs_content_not_found',
				__( 'Content not found.', 'learndash-master-client-sync' ),
				array( 'status' => 404 )
			);
		}

		$post_type = $this->get_post_type_from_content_type( $type );

		if ( $post->post_type !== $post_type ) {
			return new WP_Error(
				'ldmcs_content_type_mismatch',
				__( 'Content type mismatch.', 'learndash-master-client-sync' ),
				array( 'status' => 400 )
			);
		}

		$item = $this->prepare_content_item( $post, $type );

		return new WP_REST_Response( $item );
	}

	/**
	 * Verify connection callback.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function verify_connection( $request ) {
		$response_data = array(
			'success'   => true,
			'message'   => __( 'Connection verified successfully.', 'learndash-master-client-sync' ),
			'site_url'  => get_site_url(),
			'site_name' => get_bloginfo( 'name' ),
			'version'   => LDMCS_VERSION,
		);

		// Track the client site connection.
		$client_site_url = $request->get_header( 'X-LDMCS-Client-URL' );
		if ( $client_site_url ) {
			// Get client site name from request header or use URL as fallback.
			$client_site_name = $request->get_header( 'X-LDMCS-Client-Name' );
			if ( empty( $client_site_name ) ) {
				$client_site_name = $client_site_url;
			}

			$master = LDMCS_Master::get_instance();
			$master->register_client_site( $client_site_url, $client_site_name );
		}

		return new WP_REST_Response( $response_data );
	}

	/**
	 * Get post type from content type.
	 *
	 * @param string $content_type Content type.
	 * @return string|false
	 */
	private function get_post_type_from_content_type( $content_type ) {
		$mapping = array(
			'courses'   => 'sfwd-courses',
			'lessons'   => 'sfwd-lessons',
			'topics'    => 'sfwd-topic',
			'quizzes'   => 'sfwd-quiz',
			'questions' => 'sfwd-question',
		);

		return isset( $mapping[ $content_type ] ) ? $mapping[ $content_type ] : false;
	}

	/**
	 * Prepare content item for response.
	 *
	 * @param WP_Post $post         Post object.
	 * @param string  $content_type Content type.
	 * @return array
	 */
	private function prepare_content_item( $post, $content_type ) {
		// Use UUID as the ID if available, otherwise fall back to post ID.
		$uuid = get_post_meta( $post->ID, LDMCS_Sync::UUID_META_KEY, true );
		$master_id = ! empty( $uuid ) ? $uuid : $post->ID;

		$item = array(
			'id'              => $master_id,
			'title'           => $post->post_title,
			'content'         => $post->post_content,
			'excerpt'         => $post->post_excerpt,
			'status'          => $post->post_status,
			'slug'            => $post->post_name,
			'date'            => $post->post_date,
			'modified'        => $post->post_modified,
			'parent'          => $post->post_parent,
			'menu_order'      => $post->menu_order,
			'meta'            => $this->get_learndash_meta( $post->ID, $content_type ),
			'featured_image'  => get_post_thumbnail_id( $post->ID ),
			'taxonomies'      => $this->get_post_taxonomies( $post->ID ),
		);

		return $item;
	}

	/**
	 * Get LearnDash specific metadata.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $content_type Content type.
	 * @return array
	 */
	private function get_learndash_meta( $post_id, $content_type ) {
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
	 * Receive pushed content from master site.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function receive_pushed_content( $request ) {
		// Only process this on client sites.
		if ( 'client' !== get_option( 'ldmcs_mode', 'client' ) ) {
			return new WP_Error(
				'ldmcs_invalid_mode',
				__( 'This endpoint is only available on client sites.', 'learndash-master-client-sync' ),
				array( 'status' => 400 )
			);
		}

		$content = $request->get_json_params();

		if ( empty( $content ) || ! isset( $content['items'] ) ) {
			return new WP_Error(
				'ldmcs_invalid_data',
				__( 'Invalid content data.', 'learndash-master-client-sync' ),
				array( 'status' => 400 )
			);
		}

		$results = array(
			'success' => true,
			'synced'  => 0,
			'skipped' => 0,
			'errors'  => 0,
			'details' => array(),
		);

		// Track UUID to client post ID mapping for rebuilding course structure.
		$uuid_to_post_id_map = array();
		$course_ids = array();

		// Process each content item.
		foreach ( $content['items'] as $item ) {
			if ( ! isset( $item['type'] ) || ! isset( $item['data'] ) ) {
				$results['errors']++;
				continue;
			}

			// Pass 'master_push' as sync type since this content was pushed from master
			$sync_result = LDMCS_Sync::sync_single_item( $item['data'], $item['type'], 'master_push' );

			if ( 'success' === $sync_result['status'] ) {
				$results['synced']++;
				
				// Track the mapping for structure rebuild.
				if ( isset( $sync_result['post_id'] ) && isset( $item['data']['id'] ) ) {
					$uuid_to_post_id_map[ $item['data']['id'] ] = $sync_result['post_id'];
					
					// Track course IDs for structure rebuild.
					if ( 'courses' === $item['type'] ) {
						$course_ids[] = $sync_result['post_id'];
					}
				}
			} elseif ( 'skipped' === $sync_result['status'] ) {
				$results['skipped']++;
				
				// Even if skipped, track the existing post for mapping.
				if ( isset( $sync_result['post_id'] ) && isset( $item['data']['id'] ) ) {
					$uuid_to_post_id_map[ $item['data']['id'] ] = $sync_result['post_id'];
					
					if ( 'courses' === $item['type'] ) {
						$course_ids[] = $sync_result['post_id'];
					}
				}
			} else {
				$results['errors']++;
			}

			$results['details'][] = $sync_result;
		}

		// Rebuild course structure metadata with client post IDs.
		if ( ! empty( $course_ids ) && ! empty( $uuid_to_post_id_map ) ) {
			foreach ( $course_ids as $course_id ) {
				LDMCS_Sync::rebuild_course_structure( $course_id, $uuid_to_post_id_map );
			}
		}

		return new WP_REST_Response( $results );
	}
}
