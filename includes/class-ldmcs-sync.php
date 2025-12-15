<?php
/**
 * Sync handler for content synchronization.
 *
 * @package LearnDash_Master_Client_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync class.
 */
class LDMCS_Sync {

	/**
	 * Sync content from master to client.
	 *
	 * @param array $content_types Content types to sync.
	 * @return array Sync results.
	 */
	public static function sync_from_master( $content_types = array() ) {
		$master_url = get_option( 'ldmcs_master_url' );
		$api_key    = get_option( 'ldmcs_master_api_key' );

		if ( empty( $master_url ) || empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Master site URL or API key is not configured.', 'learndash-master-client-sync' ),
			);
		}

		$results = array(
			'success'    => true,
			'synced'     => 0,
			'skipped'    => 0,
			'errors'     => 0,
			'details'    => array(),
		);

		// Default to all content types if none specified.
		if ( empty( $content_types ) ) {
			$content_types = array();
			if ( get_option( 'ldmcs_sync_courses', true ) ) {
				$content_types[] = 'courses';
			}
			if ( get_option( 'ldmcs_sync_lessons', true ) ) {
				$content_types[] = 'lessons';
			}
			if ( get_option( 'ldmcs_sync_topics', true ) ) {
				$content_types[] = 'topics';
			}
			if ( get_option( 'ldmcs_sync_quizzes', true ) ) {
				$content_types[] = 'quizzes';
			}
			if ( get_option( 'ldmcs_sync_questions', true ) ) {
				$content_types[] = 'questions';
			}
		}

		foreach ( $content_types as $content_type ) {
			$type_result = self::sync_content_type( $master_url, $api_key, $content_type );
			$results['synced']  += $type_result['synced'];
			$results['skipped'] += $type_result['skipped'];
			$results['errors']  += $type_result['errors'];
			$results['details'][ $content_type ] = $type_result;
		}

		return $results;
	}

	/**
	 * Sync a specific content type.
	 *
	 * @param string $master_url   Master site URL.
	 * @param string $api_key      API key.
	 * @param string $content_type Content type.
	 * @return array Sync results.
	 */
	private static function sync_content_type( $master_url, $api_key, $content_type ) {
		$results = array(
			'synced'  => 0,
			'skipped' => 0,
			'errors'  => 0,
			'items'   => array(),
		);

		$page          = 1;
		$per_page      = get_option( 'ldmcs_batch_size', 10 );
		$has_more      = true;

		while ( $has_more ) {
			$response = self::fetch_content_from_master( $master_url, $api_key, $content_type, $page, $per_page );

			if ( is_wp_error( $response ) ) {
				$results['errors']++;
				LDMCS_Logger::log( 'client_pull', $content_type, 0, 'error', $response->get_error_message() );
				break;
			}

			$items = isset( $response['items'] ) ? $response['items'] : array();

			foreach ( $items as $item ) {
				$sync_result = self::sync_single_item( $item, $content_type );

				if ( 'success' === $sync_result['status'] ) {
					$results['synced']++;
				} elseif ( 'skipped' === $sync_result['status'] ) {
					$results['skipped']++;
				} else {
					$results['errors']++;
				}

				$results['items'][] = $sync_result;
			}

			// Check if there are more pages.
			$total_pages = isset( $response['total_pages'] ) ? $response['total_pages'] : 1;
			$has_more    = $page < $total_pages;
			$page++;
		}

		return $results;
	}

	/**
	 * Fetch content from master site.
	 *
	 * @param string $master_url   Master site URL.
	 * @param string $api_key      API key.
	 * @param string $content_type Content type.
	 * @param int    $page         Page number.
	 * @param int    $per_page     Items per page.
	 * @return array|WP_Error Response data or error.
	 */
	private static function fetch_content_from_master( $master_url, $api_key, $content_type, $page = 1, $per_page = 10 ) {
		$url = trailingslashit( $master_url ) . 'wp-json/ldmcs/v1/content/' . $content_type;
		$url = add_query_arg(
			array(
				'page'     => $page,
				'per_page' => $per_page,
			),
			$url
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'X-LDMCS-API-Key' => $api_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			$message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'learndash-master-client-sync' );
			return new WP_Error( 'ldmcs_api_error', $message );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data;
	}

	/**
	 * Sync a single content item.
	 *
	 * @param array  $item         Content item data.
	 * @param string $content_type Content type.
	 * @return array Sync result.
	 */
	private static function sync_single_item( $item, $content_type ) {
		$post_type = self::get_post_type_from_content_type( $content_type );

		// Check if content already exists.
		$existing_post = self::find_existing_post( $item['id'], $item['slug'], $post_type );

		if ( $existing_post ) {
			$conflict_resolution = get_option( 'ldmcs_conflict_resolution', 'skip' );

			if ( 'skip' === $conflict_resolution ) {
				LDMCS_Logger::log( 'client_pull', $content_type, $existing_post->ID, 'skipped', 'Content already exists' );
				return array(
					'status'  => 'skipped',
					'item_id' => $item['id'],
					'message' => __( 'Content already exists', 'learndash-master-client-sync' ),
				);
			}

			// Overwrite existing content.
			$result = self::update_content( $existing_post->ID, $item, $post_type );
			$status = $result ? 'success' : 'error';
			$message = $result ? __( 'Content updated', 'learndash-master-client-sync' ) : __( 'Failed to update content', 'learndash-master-client-sync' );

			LDMCS_Logger::log( 'client_pull', $content_type, $existing_post->ID, $status, $message );

			return array(
				'status'  => $status,
				'item_id' => $item['id'],
				'post_id' => $existing_post->ID,
				'message' => $message,
			);
		}

		// Create new content.
		$post_id = self::create_content( $item, $post_type );

		if ( is_wp_error( $post_id ) ) {
			LDMCS_Logger::log( 'client_pull', $content_type, 0, 'error', $post_id->get_error_message() );
			return array(
				'status'  => 'error',
				'item_id' => $item['id'],
				'message' => $post_id->get_error_message(),
			);
		}

		LDMCS_Logger::log( 'client_pull', $content_type, $post_id, 'success', 'Content created' );

		return array(
			'status'  => 'success',
			'item_id' => $item['id'],
			'post_id' => $post_id,
			'message' => __( 'Content created', 'learndash-master-client-sync' ),
		);
	}

	/**
	 * Find existing post by master ID or slug.
	 *
	 * @param int    $master_id Master post ID.
	 * @param string $slug      Post slug.
	 * @param string $post_type Post type.
	 * @return WP_Post|null
	 */
	private static function find_existing_post( $master_id, $slug, $post_type ) {
		// Try to find by master ID stored in meta.
		$posts = get_posts(
			array(
				'post_type'   => $post_type,
				'meta_key'    => '_ldmcs_master_id',
				'meta_value'  => $master_id,
				'numberposts' => 1,
			)
		);

		if ( ! empty( $posts ) ) {
			return $posts[0];
		}

		// Try to find by slug.
		$post = get_page_by_path( $slug, OBJECT, $post_type );

		return $post;
	}

	/**
	 * Create new content.
	 *
	 * @param array  $item      Content item data.
	 * @param string $post_type Post type.
	 * @return int|WP_Error Post ID or error.
	 */
	private static function create_content( $item, $post_type ) {
		$post_data = array(
			'post_type'    => $post_type,
			'post_title'   => sanitize_text_field( $item['title'] ),
			'post_content' => wp_kses_post( $item['content'] ),
			'post_excerpt' => sanitize_textarea_field( $item['excerpt'] ),
			'post_status'  => 'publish',
			'post_name'    => sanitize_title( $item['slug'] ),
			'menu_order'   => isset( $item['menu_order'] ) ? absint( $item['menu_order'] ) : 0,
		);

		// Remove filter to avoid recursion.
		remove_action( 'save_post', array( 'LDMCS_Master', 'on_content_save' ), 10, 3 );

		$post_id = wp_insert_post( $post_data, true );

		// Re-add filter.
		add_action( 'save_post', array( 'LDMCS_Master', 'on_content_save' ), 10, 3 );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Store master ID.
		update_post_meta( $post_id, '_ldmcs_master_id', $item['id'] );
		update_post_meta( $post_id, '_ldmcs_last_sync', current_time( 'mysql' ) );

		// Update meta data.
		if ( isset( $item['meta'] ) && is_array( $item['meta'] ) ) {
			foreach ( $item['meta'] as $meta_key => $meta_value ) {
				update_post_meta( $post_id, $meta_key, $meta_value );
			}
		}

		// Update taxonomies.
		if ( isset( $item['taxonomies'] ) && is_array( $item['taxonomies'] ) ) {
			foreach ( $item['taxonomies'] as $taxonomy => $terms ) {
				wp_set_object_terms( $post_id, $terms, $taxonomy );
			}
		}

		return $post_id;
	}

	/**
	 * Update existing content.
	 *
	 * @param int    $post_id   Post ID.
	 * @param array  $item      Content item data.
	 * @param string $post_type Post type.
	 * @return bool Success status.
	 */
	private static function update_content( $post_id, $item, $post_type ) {
		$post_data = array(
			'ID'           => $post_id,
			'post_title'   => sanitize_text_field( $item['title'] ),
			'post_content' => wp_kses_post( $item['content'] ),
			'post_excerpt' => sanitize_textarea_field( $item['excerpt'] ),
			'post_name'    => sanitize_title( $item['slug'] ),
			'menu_order'   => isset( $item['menu_order'] ) ? absint( $item['menu_order'] ) : 0,
		);

		// Remove filter to avoid recursion.
		remove_action( 'save_post', array( 'LDMCS_Master', 'on_content_save' ), 10, 3 );

		$result = wp_update_post( $post_data, true );

		// Re-add filter.
		add_action( 'save_post', array( 'LDMCS_Master', 'on_content_save' ), 10, 3 );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Update master ID and sync time.
		update_post_meta( $post_id, '_ldmcs_master_id', $item['id'] );
		update_post_meta( $post_id, '_ldmcs_last_sync', current_time( 'mysql' ) );

		// Update meta data.
		if ( isset( $item['meta'] ) && is_array( $item['meta'] ) ) {
			foreach ( $item['meta'] as $meta_key => $meta_value ) {
				update_post_meta( $post_id, $meta_key, $meta_value );
			}
		}

		// Update taxonomies.
		if ( isset( $item['taxonomies'] ) && is_array( $item['taxonomies'] ) ) {
			foreach ( $item['taxonomies'] as $taxonomy => $terms ) {
				wp_set_object_terms( $post_id, $terms, $taxonomy );
			}
		}

		return true;
	}

	/**
	 * Get post type from content type.
	 *
	 * @param string $content_type Content type.
	 * @return string
	 */
	private static function get_post_type_from_content_type( $content_type ) {
		$mapping = array(
			'courses'   => 'sfwd-courses',
			'lessons'   => 'sfwd-lessons',
			'topics'    => 'sfwd-topic',
			'quizzes'   => 'sfwd-quiz',
			'questions' => 'sfwd-question',
		);

		return isset( $mapping[ $content_type ] ) ? $mapping[ $content_type ] : '';
	}

	/**
	 * Verify connection to master site.
	 *
	 * @param string $master_url Master site URL.
	 * @param string $api_key    API key.
	 * @return array|WP_Error Response or error.
	 */
	public static function verify_master_connection( $master_url, $api_key ) {
		$url = trailingslashit( $master_url ) . 'wp-json/ldmcs/v1/verify';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'X-LDMCS-API-Key' => $api_key,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			$message = isset( $data['message'] ) ? $data['message'] : __( 'Connection failed', 'learndash-master-client-sync' );
			return new WP_Error( 'ldmcs_connection_error', $message );
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}
}
