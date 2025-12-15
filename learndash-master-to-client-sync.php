<?php
/**
 * Plugin Name: LearnDash Master to Client Sync
 * Plugin URI: https://github.com/impact2021/learndash-master-to-client-sync
 * Description: Syncs LearnDash content from a master site to client sites without impacting users. Designed for IELTStestONLINE to affiliate sites synchronization.
 * Version: 2.1.0
 * Author: Impact Websites
 * Author URI: https://github.com/impact2021
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learndash-master-client-sync
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 *
 * @package LearnDash_Master_Client_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'LDMCS_VERSION', '2.1.0' );
define( 'LDMCS_PLUGIN_FILE', __FILE__ );
define( 'LDMCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LDMCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LDMCS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
class LearnDash_Master_Client_Sync {

	/**
	 * Plugin instance.
	 *
	 * @var LearnDash_Master_Client_Sync
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return LearnDash_Master_Client_Sync
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
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once LDMCS_PLUGIN_DIR . 'includes/class-ldmcs-master.php';
		require_once LDMCS_PLUGIN_DIR . 'includes/class-ldmcs-client.php';
		require_once LDMCS_PLUGIN_DIR . 'includes/class-ldmcs-admin.php';
		require_once LDMCS_PLUGIN_DIR . 'includes/class-ldmcs-api.php';
		require_once LDMCS_PLUGIN_DIR . 'includes/class-ldmcs-sync.php';
		require_once LDMCS_PLUGIN_DIR . 'includes/class-ldmcs-logger.php';
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'learndash-master-client-sync',
			false,
			dirname( LDMCS_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Check if LearnDash is active.
		if ( ! $this->is_learndash_active() ) {
			add_action( 'admin_notices', array( $this, 'learndash_missing_notice' ) );
			return;
		}

		// Initialize components.
		LDMCS_Master::get_instance();
		LDMCS_Client::get_instance();
		LDMCS_Admin::get_instance();
		LDMCS_API::get_instance();
	}

	/**
	 * Check if LearnDash is active.
	 *
	 * @return bool
	 */
	private function is_learndash_active() {
		return defined( 'LEARNDASH_VERSION' );
	}

	/**
	 * Show LearnDash missing notice.
	 */
	public function learndash_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: LearnDash plugin link */
						__( '<strong>LearnDash Master to Client Sync</strong> requires the LearnDash LMS plugin to be installed and activated. Please install %s.', 'learndash-master-client-sync' ),
						'<a href="https://www.learndash.com/" target="_blank">LearnDash LMS</a>'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Create necessary database tables and options.
		$this->create_tables();
		$this->set_default_options();

		// Schedule cron jobs for background sync.
		if ( ! wp_next_scheduled( 'ldmcs_sync_content' ) ) {
			wp_schedule_event( time(), 'hourly', 'ldmcs_sync_content' );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Clear scheduled cron jobs.
		$timestamp = wp_next_scheduled( 'ldmcs_sync_content' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ldmcs_sync_content' );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create database tables.
	 */
	private function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'ldmcs_sync_log';

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			sync_type varchar(50) NOT NULL,
			content_type varchar(50) NOT NULL,
			content_id bigint(20) NOT NULL,
			status varchar(20) NOT NULL,
			message text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY sync_type (sync_type),
			KEY content_type (content_type),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Set default options.
	 */
	private function set_default_options() {
		$default_options = array(
			'ldmcs_mode'                => 'client', // 'master' or 'client'
			'ldmcs_api_key'             => wp_generate_password( 32, false ),
			'ldmcs_master_url'          => '',
			'ldmcs_master_api_key'      => '',
			'ldmcs_sync_interval'       => 'hourly',
			'ldmcs_auto_sync_enabled'   => false,
			'ldmcs_sync_courses'        => true,
			'ldmcs_sync_lessons'        => true,
			'ldmcs_sync_topics'         => true,
			'ldmcs_sync_quizzes'        => true,
			'ldmcs_sync_questions'      => true,
			'ldmcs_conflict_resolution' => 'skip', // 'overwrite' or 'skip'
			'ldmcs_batch_size'          => 10,
		);

		foreach ( $default_options as $option_name => $option_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $option_value );
			}
		}
	}
}

/**
 * Initialize the plugin.
 *
 * @return LearnDash_Master_Client_Sync
 */
function ldmcs() {
	return LearnDash_Master_Client_Sync::get_instance();
}

// Start the plugin.
ldmcs();
