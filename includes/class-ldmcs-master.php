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
			'sfwd-courses'  => 'course',
			'sfwd-lessons'  => 'lesson',
			'sfwd-topic'    => 'topic',
			'sfwd-quiz'     => 'quiz',
			'sfwd-question' => 'question',
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
}
