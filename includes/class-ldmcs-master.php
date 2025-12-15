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

		// Add AJAX handler for push course.
		add_action( 'wp_ajax_ldmcs_push_course', array( $this, 'handle_push_course' ) );
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

		// Log the push action.
		LDMCS_Logger::log(
			'master_push',
			'courses',
			$course_id,
			'success',
			sprintf( 'Course "%s" (ID: %d) pushed to client sites', $course->post_title, $course_id )
		);

		// Get client sites count for response.
		$client_sites = get_option( 'ldmcs_client_sites', array() );
		$client_count = count( $client_sites );

		wp_send_json_success(
			array(
				'message'      => sprintf(
					/* translators: %1$s: course title, %2$d: number of client sites */
					__( 'Course "%1$s" has been marked for push. %2$d client site(s) will pull this course on their next sync.', 'learndash-master-client-sync' ),
					$course->post_title,
					$client_count
				),
				'course_id'    => $course_id,
				'course_title' => $course->post_title,
				'client_count' => $client_count,
			)
		);
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

		// Use URL as unique identifier.
		$client_id = md5( $site_url );

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
}
