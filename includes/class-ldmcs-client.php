<?php
/**
 * Client site functionality.
 *
 * @package LearnDash_Master_Client_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client site class.
 */
class LDMCS_Client {

	/**
	 * Instance of this class.
	 *
	 * @var LDMCS_Client
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return LDMCS_Client
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
		// Only initialize if mode is client.
		if ( 'client' === get_option( 'ldmcs_mode', 'client' ) ) {
			$this->init_hooks();
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Schedule automatic sync if enabled.
		add_action( 'ldmcs_sync_content', array( $this, 'run_scheduled_sync' ) );

		// Add AJAX handlers for manual sync.
		add_action( 'wp_ajax_ldmcs_manual_sync', array( $this, 'handle_manual_sync' ) );
		add_action( 'wp_ajax_ldmcs_verify_connection', array( $this, 'handle_verify_connection' ) );
	}

	/**
	 * Run scheduled sync.
	 */
	public function run_scheduled_sync() {
		// Check if auto sync is enabled.
		if ( ! get_option( 'ldmcs_auto_sync_enabled', false ) ) {
			return;
		}

		// Run sync in background to avoid impacting performance.
		$this->sync_content();
	}

	/**
	 * Sync content from master.
	 *
	 * @return array Sync results.
	 */
	public function sync_content() {
		return LDMCS_Sync::sync_from_master();
	}

	/**
	 * Handle manual sync AJAX request.
	 */
	public function handle_manual_sync() {
		check_ajax_referer( 'ldmcs_manual_sync', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learndash-master-client-sync' ) ) );
		}

		$content_types = isset( $_POST['content_types'] ) ? array_map( 'sanitize_text_field', $_POST['content_types'] ) : array();

		$results = LDMCS_Sync::sync_from_master( $content_types );

		if ( $results['success'] ) {
			wp_send_json_success( $results );
		} else {
			wp_send_json_error( $results );
		}
	}

	/**
	 * Handle verify connection AJAX request.
	 */
	public function handle_verify_connection() {
		check_ajax_referer( 'ldmcs_verify_connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learndash-master-client-sync' ) ) );
		}

		$master_url = isset( $_POST['master_url'] ) ? esc_url_raw( $_POST['master_url'] ) : get_option( 'ldmcs_master_url' );
		$api_key    = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : get_option( 'ldmcs_master_api_key' );

		$result = LDMCS_Sync::verify_master_connection( $master_url, $api_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}
}
