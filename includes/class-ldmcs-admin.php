<?php
/**
 * Admin interface and settings.
 *
 * @package LearnDash_Master_Client_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class LDMCS_Admin {

	/**
	 * Instance of this class.
	 *
	 * @var LDMCS_Admin
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return LDMCS_Admin
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'LearnDash Sync', 'learndash-master-client-sync' ),
			__( 'LearnDash Sync', 'learndash-master-client-sync' ),
			'manage_options',
			'ldmcs-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-update',
			80
		);

		add_submenu_page(
			'ldmcs-settings',
			__( 'Settings', 'learndash-master-client-sync' ),
			__( 'Settings', 'learndash-master-client-sync' ),
			'manage_options',
			'ldmcs-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'ldmcs-settings',
			__( 'Sync Logs', 'learndash-master-client-sync' ),
			__( 'Sync Logs', 'learndash-master-client-sync' ),
			'manage_options',
			'ldmcs-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// Register settings.
		register_setting( 'ldmcs_settings_group', 'ldmcs_mode' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_api_key' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_master_url' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_master_api_key' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_auto_sync_enabled' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_sync_interval' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_sync_courses' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_sync_lessons' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_sync_topics' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_sync_quizzes' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_sync_questions' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_conflict_resolution' );
		register_setting( 'ldmcs_settings_group', 'ldmcs_batch_size' );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'ldmcs' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'ldmcs-admin',
			LDMCS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			LDMCS_VERSION
		);

		wp_enqueue_script(
			'ldmcs-admin',
			LDMCS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			LDMCS_VERSION,
			true
		);

		wp_localize_script(
			'ldmcs-admin',
			'ldmcsAdmin',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'manualSyncNonce'       => wp_create_nonce( 'ldmcs_manual_sync' ),
				'verifyConnectionNonce' => wp_create_nonce( 'ldmcs_verify_connection' ),
				'strings'               => array(
					'syncing'             => __( 'Syncing...', 'learndash-master-client-sync' ),
					'syncComplete'        => __( 'Sync completed!', 'learndash-master-client-sync' ),
					'syncError'           => __( 'Sync failed. Please check the logs.', 'learndash-master-client-sync' ),
					'verifying'           => __( 'Verifying connection...', 'learndash-master-client-sync' ),
					'connectionVerified'  => __( 'Connection verified successfully!', 'learndash-master-client-sync' ),
					'connectionFailed'    => __( 'Connection failed. Please check your settings.', 'learndash-master-client-sync' ),
				),
			)
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		$mode = get_option( 'ldmcs_mode', 'client' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LearnDash Master to Client Sync Settings', 'learndash-master-client-sync' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'ldmcs_settings_group' );
				?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="ldmcs_mode"><?php esc_html_e( 'Site Mode', 'learndash-master-client-sync' ); ?></label>
						</th>
						<td>
							<select name="ldmcs_mode" id="ldmcs_mode">
								<option value="client" <?php selected( $mode, 'client' ); ?>><?php esc_html_e( 'Client Site', 'learndash-master-client-sync' ); ?></option>
								<option value="master" <?php selected( $mode, 'master' ); ?>><?php esc_html_e( 'Master Site', 'learndash-master-client-sync' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Select whether this site is a master (source) or client (destination) site.', 'learndash-master-client-sync' ); ?></p>
						</td>
					</tr>

					<?php if ( 'master' === $mode ) : ?>
					<tr>
						<th scope="row">
							<label for="ldmcs_api_key"><?php esc_html_e( 'API Key', 'learndash-master-client-sync' ); ?></label>
						</th>
						<td>
							<input type="text" name="ldmcs_api_key" id="ldmcs_api_key" value="<?php echo esc_attr( get_option( 'ldmcs_api_key' ) ); ?>" class="regular-text" readonly />
							<button type="button" class="button" id="ldmcs-regenerate-key"><?php esc_html_e( 'Regenerate', 'learndash-master-client-sync' ); ?></button>
							<p class="description"><?php esc_html_e( 'This API key is used by client sites to authenticate. Keep it secure.', 'learndash-master-client-sync' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>

					<?php if ( 'client' === $mode ) : ?>
					<tr>
						<th scope="row">
							<label for="ldmcs_master_url"><?php esc_html_e( 'Master Site URL', 'learndash-master-client-sync' ); ?></label>
						</th>
						<td>
							<input type="url" name="ldmcs_master_url" id="ldmcs_master_url" value="<?php echo esc_url( get_option( 'ldmcs_master_url' ) ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Enter the URL of the master site (e.g., https://master-site.com).', 'learndash-master-client-sync' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="ldmcs_master_api_key"><?php esc_html_e( 'Master Site API Key', 'learndash-master-client-sync' ); ?></label>
						</th>
						<td>
							<input type="text" name="ldmcs_master_api_key" id="ldmcs_master_api_key" value="<?php echo esc_attr( get_option( 'ldmcs_master_api_key' ) ); ?>" class="regular-text" />
							<button type="button" class="button" id="ldmcs-verify-connection"><?php esc_html_e( 'Verify Connection', 'learndash-master-client-sync' ); ?></button>
							<p class="description"><?php esc_html_e( 'Enter the API key from the master site.', 'learndash-master-client-sync' ); ?></p>
							<div id="ldmcs-connection-status"></div>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="ldmcs_auto_sync_enabled"><?php esc_html_e( 'Auto Sync', 'learndash-master-client-sync' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="ldmcs_auto_sync_enabled" id="ldmcs_auto_sync_enabled" value="1" <?php checked( get_option( 'ldmcs_auto_sync_enabled' ), 1 ); ?> />
								<?php esc_html_e( 'Enable automatic synchronization', 'learndash-master-client-sync' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When enabled, content will be synced automatically based on the schedule below.', 'learndash-master-client-sync' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="ldmcs_sync_interval"><?php esc_html_e( 'Sync Interval', 'learndash-master-client-sync' ); ?></label>
						</th>
						<td>
							<select name="ldmcs_sync_interval" id="ldmcs_sync_interval">
								<option value="hourly" <?php selected( get_option( 'ldmcs_sync_interval' ), 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'learndash-master-client-sync' ); ?></option>
								<option value="twicedaily" <?php selected( get_option( 'ldmcs_sync_interval' ), 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'learndash-master-client-sync' ); ?></option>
								<option value="daily" <?php selected( get_option( 'ldmcs_sync_interval' ), 'daily' ); ?>><?php esc_html_e( 'Daily', 'learndash-master-client-sync' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'How often to sync content automatically.', 'learndash-master-client-sync' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Content Types to Sync', 'learndash-master-client-sync' ); ?></th>
						<td>
							<label><input type="checkbox" name="ldmcs_sync_courses" value="1" <?php checked( get_option( 'ldmcs_sync_courses', true ), 1 ); ?> /> <?php esc_html_e( 'Courses', 'learndash-master-client-sync' ); ?></label><br />
							<label><input type="checkbox" name="ldmcs_sync_lessons" value="1" <?php checked( get_option( 'ldmcs_sync_lessons', true ), 1 ); ?> /> <?php esc_html_e( 'Lessons', 'learndash-master-client-sync' ); ?></label><br />
							<label><input type="checkbox" name="ldmcs_sync_topics" value="1" <?php checked( get_option( 'ldmcs_sync_topics', true ), 1 ); ?> /> <?php esc_html_e( 'Topics', 'learndash-master-client-sync' ); ?></label><br />
							<label><input type="checkbox" name="ldmcs_sync_quizzes" value="1" <?php checked( get_option( 'ldmcs_sync_quizzes', true ), 1 ); ?> /> <?php esc_html_e( 'Quizzes', 'learndash-master-client-sync' ); ?></label><br />
							<label><input type="checkbox" name="ldmcs_sync_questions" value="1" <?php checked( get_option( 'ldmcs_sync_questions', true ), 1 ); ?> /> <?php esc_html_e( 'Questions', 'learndash-master-client-sync' ); ?></label>
							<p class="description"><?php esc_html_e( 'Select which content types to sync from the master site.', 'learndash-master-client-sync' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="ldmcs_conflict_resolution"><?php esc_html_e( 'Conflict Resolution', 'learndash-master-client-sync' ); ?></label>
						</th>
						<td>
							<select name="ldmcs_conflict_resolution" id="ldmcs_conflict_resolution">
								<option value="skip" <?php selected( get_option( 'ldmcs_conflict_resolution' ), 'skip' ); ?>><?php esc_html_e( 'Skip existing content', 'learndash-master-client-sync' ); ?></option>
								<option value="overwrite" <?php selected( get_option( 'ldmcs_conflict_resolution' ), 'overwrite' ); ?>><?php esc_html_e( 'Overwrite existing content', 'learndash-master-client-sync' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'How to handle content that already exists on this site.', 'learndash-master-client-sync' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="ldmcs_batch_size"><?php esc_html_e( 'Batch Size', 'learndash-master-client-sync' ); ?></label>
						</th>
						<td>
							<input type="number" name="ldmcs_batch_size" id="ldmcs_batch_size" value="<?php echo esc_attr( get_option( 'ldmcs_batch_size', 10 ) ); ?>" min="1" max="50" class="small-text" />
							<p class="description"><?php esc_html_e( 'Number of items to sync per batch (1-50). Lower values reduce server load.', 'learndash-master-client-sync' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Manual Sync', 'learndash-master-client-sync' ); ?></th>
						<td>
							<button type="button" class="button button-primary" id="ldmcs-manual-sync"><?php esc_html_e( 'Sync Now', 'learndash-master-client-sync' ); ?></button>
							<p class="description"><?php esc_html_e( 'Manually trigger a sync operation.', 'learndash-master-client-sync' ); ?></p>
							<div id="ldmcs-sync-status"></div>
						</td>
					</tr>
					<?php endif; ?>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render logs page.
	 */
	public function render_logs_page() {
		$logs = LDMCS_Logger::get_recent_logs( 100 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sync Logs', 'learndash-master-client-sync' ); ?></h1>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date/Time', 'learndash-master-client-sync' ); ?></th>
						<th><?php esc_html_e( 'Sync Type', 'learndash-master-client-sync' ); ?></th>
						<th><?php esc_html_e( 'Content Type', 'learndash-master-client-sync' ); ?></th>
						<th><?php esc_html_e( 'Content ID', 'learndash-master-client-sync' ); ?></th>
						<th><?php esc_html_e( 'Status', 'learndash-master-client-sync' ); ?></th>
						<th><?php esc_html_e( 'Message', 'learndash-master-client-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'No sync logs found.', 'learndash-master-client-sync' ); ?></td>
					</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log->created_at ); ?></td>
							<td><?php echo esc_html( $log->sync_type ); ?></td>
							<td><?php echo esc_html( $log->content_type ); ?></td>
							<td><?php echo esc_html( $log->content_id ); ?></td>
							<td>
								<span class="ldmcs-status ldmcs-status-<?php echo esc_attr( $log->status ); ?>">
									<?php echo esc_html( ucfirst( $log->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $log->message ); ?></td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
