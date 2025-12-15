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
	 * Number of days before a client site is considered inactive.
	 *
	 * @var int
	 */
	const INACTIVE_THRESHOLD_DAYS = 7;

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

		// Add UUID column to LearnDash post types.
		$learndash_post_types = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-question' );
		foreach ( $learndash_post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_uuid_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_uuid_column' ), 10, 2 );
		}
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

		// Add courses page for master site.
		$mode = get_option( 'ldmcs_mode', 'client' );
		if ( 'master' === $mode ) {
			add_submenu_page(
				'ldmcs-settings',
				__( 'Courses', 'learndash-master-client-sync' ),
				__( 'Courses', 'learndash-master-client-sync' ),
				'manage_options',
				'ldmcs-courses',
				array( $this, 'render_courses_page' )
			);

			add_submenu_page(
				'ldmcs-settings',
				__( 'Client Sites', 'learndash-master-client-sync' ),
				__( 'Client Sites', 'learndash-master-client-sync' ),
				'manage_options',
				'ldmcs-client-sites',
				array( $this, 'render_client_sites_page' )
			);
		}

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
				'pushCourseNonce'       => wp_create_nonce( 'ldmcs_push_course' ),
				'pushContentNonce'      => wp_create_nonce( 'ldmcs_push_content' ),
				'generateUuidsNonce'    => wp_create_nonce( 'ldmcs_generate_uuids' ),
				'strings'               => array(
					'syncing'             => __( 'Syncing...', 'learndash-master-client-sync' ),
					'syncComplete'        => __( 'Sync completed!', 'learndash-master-client-sync' ),
					'syncError'           => __( 'Sync failed. Please check the logs.', 'learndash-master-client-sync' ),
					'verifying'           => __( 'Verifying connection...', 'learndash-master-client-sync' ),
					'connectionVerified'  => __( 'Connection verified successfully!', 'learndash-master-client-sync' ),
					'connectionFailed'    => __( 'Connection failed. Please check your settings.', 'learndash-master-client-sync' ),
					'pushing'             => __( 'Pushing content...', 'learndash-master-client-sync' ),
					'pushComplete'        => __( 'Content pushed successfully!', 'learndash-master-client-sync' ),
					'pushError'           => __( 'Failed to push content.', 'learndash-master-client-sync' ),
					'confirmPush'         => __( 'Push this content to all connected client sites?', 'learndash-master-client-sync' ),
					'generatingUuids'     => __( 'Generating UUIDs...', 'learndash-master-client-sync' ),
					'uuidsGenerated'      => __( 'UUIDs generated successfully!', 'learndash-master-client-sync' ),
					'uuidsError'          => __( 'Failed to generate UUIDs.', 'learndash-master-client-sync' ),
					'confirmGenerateUuids' => __( 'This will generate unique UUIDs for all LearnDash content that doesn\'t have one. This is required for accurate content mapping. Continue?', 'learndash-master-client-sync' ),
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

			<?php if ( 'master' === $mode ) : ?>
			<div class="notice notice-success">
				<p>
					<strong><?php esc_html_e( '✓ User Data Protection:', 'learndash-master-client-sync' ); ?></strong>
					<?php esc_html_e( 'This plugin syncs ONLY course structure and content. User enrollments, progress, quiz attempts, course completions, and all user data remain completely untouched and safe on all sites.', 'learndash-master-client-sync' ); ?>
				</p>
			</div>
			<?php endif; ?>

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

					<tr>
						<th scope="row"><?php esc_html_e( 'Content Mapping', 'learndash-master-client-sync' ); ?></th>
						<td>
							<button type="button" class="button button-secondary" id="ldmcs-generate-uuids"><?php esc_html_e( 'Generate UUIDs for All Content', 'learndash-master-client-sync' ); ?></button>
							<p class="description">
								<?php esc_html_e( 'Generate unique identifiers (UUIDs) for all LearnDash content (Courses, Lessons, Topics, Quizzes, Questions). This ensures accurate mapping between master and client sites. Run this once before pushing content.', 'learndash-master-client-sync' ); ?>
							</p>
							<div id="ldmcs-generate-uuids-status"></div>
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

	/**
	 * Render courses page.
	 */
	public function render_courses_page() {
		// Get all LearnDash courses.
		$args = array(
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$courses = get_posts( $args );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LearnDash Courses', 'learndash-master-client-sync' ); ?></h1>
			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Safe Content Sync:', 'learndash-master-client-sync' ); ?></strong>
					<?php esc_html_e( 'Pushing courses only syncs course content and structure. User enrollments, progress, quiz attempts, and completion data are NEVER affected or overwritten. Users on client sites can continue their learning without interruption.', 'learndash-master-client-sync' ); ?>
				</p>
			</div>
			<p class="description">
				<?php esc_html_e( 'Push individual courses to connected client sites. The Master UUID is the unique identifier for each course on this master site.', 'learndash-master-client-sync' ); ?>
			</p>

			<?php if ( empty( $courses ) ) : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'No courses found. Create some LearnDash courses first.', 'learndash-master-client-sync' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 60px;"><?php esc_html_e( 'Master UUID', 'learndash-master-client-sync' ); ?></th>
							<th><?php esc_html_e( 'Course Title', 'learndash-master-client-sync' ); ?></th>
							<th><?php esc_html_e( 'Status', 'learndash-master-client-sync' ); ?></th>
							<th><?php esc_html_e( 'Last Modified', 'learndash-master-client-sync' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Actions', 'learndash-master-client-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $courses as $course ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $this->get_display_uuid( $course->ID ) ); ?></strong></td>
							<td>
								<strong><?php echo esc_html( $course->post_title ); ?></strong>
								<div class="row-actions">
									<span class="edit">
										<a href="<?php echo esc_url( get_edit_post_link( $course->ID ) ); ?>">
											<?php esc_html_e( 'Edit', 'learndash-master-client-sync' ); ?>
										</a>
									</span>
									|
									<span class="view">
										<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>" target="_blank">
											<?php esc_html_e( 'View', 'learndash-master-client-sync' ); ?>
										</a>
									</span>
								</div>
							</td>
							<td>
								<span class="ldmcs-status ldmcs-status-<?php echo esc_attr( $course->post_status ); ?>">
									<?php echo esc_html( ucfirst( $course->post_status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( get_the_modified_date( 'Y-m-d H:i:s', $course->ID ) ); ?></td>
							<td>
								<button type="button" class="button button-primary ldmcs-push-course" data-course-id="<?php echo esc_attr( $course->ID ); ?>" data-course-title="<?php echo esc_attr( $course->post_title ); ?>">
									<?php esc_html_e( 'Push to Clients', 'learndash-master-client-sync' ); ?>
								</button>
								<div class="ldmcs-push-status" id="ldmcs-push-status-<?php echo esc_attr( $course->ID ); ?>"></div>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render client sites page.
	 */
	public function render_client_sites_page() {
		// Get list of client sites.
		$client_sites = get_option( 'ldmcs_client_sites', array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connected Client Sites', 'learndash-master-client-sync' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'List of client sites that have connected to this master site. Client sites are automatically registered when they verify their connection.', 'learndash-master-client-sync' ); ?>
			</p>

			<?php if ( empty( $client_sites ) ) : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'No client sites have connected yet. Configure a client site to connect to this master site.', 'learndash-master-client-sync' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Site URL', 'learndash-master-client-sync' ); ?></th>
							<th><?php esc_html_e( 'Site Name', 'learndash-master-client-sync' ); ?></th>
							<th><?php esc_html_e( 'First Connected', 'learndash-master-client-sync' ); ?></th>
							<th><?php esc_html_e( 'Last Connected', 'learndash-master-client-sync' ); ?></th>
							<th><?php esc_html_e( 'Status', 'learndash-master-client-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $client_sites as $client_id => $client_data ) : ?>
						<?php
						$first_connected = isset( $client_data['first_connected'] ) ? $client_data['first_connected'] : __( 'Unknown', 'learndash-master-client-sync' );
						$last_connected  = isset( $client_data['last_connected'] ) ? $client_data['last_connected'] : __( 'Unknown', 'learndash-master-client-sync' );
						$site_url        = isset( $client_data['site_url'] ) ? $client_data['site_url'] : __( 'Unknown', 'learndash-master-client-sync' );
						$site_name       = isset( $client_data['site_name'] ) ? $client_data['site_name'] : __( 'Unknown', 'learndash-master-client-sync' );

						// Calculate status based on last connection time.
						$status       = 'active';
						$status_label = __( 'Active', 'learndash-master-client-sync' );
						if ( 'Unknown' !== $last_connected ) {
							$last_time = strtotime( $last_connected );
							$now       = current_time( 'timestamp' );
							$diff_days = ( $now - $last_time ) / DAY_IN_SECONDS;
							if ( $diff_days > self::INACTIVE_THRESHOLD_DAYS ) {
								$status       = 'inactive';
								$status_label = __( 'Inactive', 'learndash-master-client-sync' );
							}
						}
						?>
						<tr>
							<td>
								<strong><a href="<?php echo esc_url( $site_url ); ?>" target="_blank"><?php echo esc_html( $site_url ); ?></a></strong>
							</td>
							<td><?php echo esc_html( $site_name ); ?></td>
							<td><?php echo esc_html( $first_connected ); ?></td>
							<td><?php echo esc_html( $last_connected ); ?></td>
							<td>
								<span class="ldmcs-status ldmcs-status-<?php echo esc_attr( $status ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Add UUID column to courses list.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_uuid_column( $columns ) {
		$mode = get_option( 'ldmcs_mode', 'client' );

		// Insert UUID column after the title column.
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				if ( 'master' === $mode ) {
					$new_columns['ldmcs_uuid'] = __( 'Master UUID', 'learndash-master-client-sync' );
				} else {
					$new_columns['ldmcs_uuid'] = __( 'UUID', 'learndash-master-client-sync' );
				}
			}
		}

		// Add push action column for master sites.
		if ( 'master' === $mode ) {
			$new_columns['ldmcs_push'] = __( 'Push to Clients', 'learndash-master-client-sync' );
		}

		return $new_columns;
	}

	/**
	 * Get UUID for display.
	 *
	 * @param int $post_id Post ID.
	 * @return string UUID or post ID as fallback.
	 */
	private function get_display_uuid( $post_id ) {
		$uuid = get_post_meta( $post_id, 'ld_uuid', true );
		return ! empty( $uuid ) ? $uuid : $post_id;
	}

	/**
	 * Render UUID column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_uuid_column( $column, $post_id ) {
		$mode = get_option( 'ldmcs_mode', 'client' );

		if ( 'ldmcs_uuid' === $column ) {
			if ( 'master' === $mode ) {
				// On master site, show the ld_uuid custom field if available, otherwise show post ID.
				echo '<strong>' . esc_html( $this->get_display_uuid( $post_id ) ) . '</strong>';
			} else {
				// On client site, show both local ID and master UUID.
				$master_id = get_post_meta( $post_id, '_ldmcs_master_id', true );
				
				echo '<div class="ldmcs-uuid-info">';
				echo '<div><strong>' . esc_html__( 'Local:', 'learndash-master-client-sync' ) . '</strong> ' . esc_html( $post_id ) . '</div>';
				
				if ( $master_id ) {
					echo '<div><strong>' . esc_html__( 'Master:', 'learndash-master-client-sync' ) . '</strong> ' . esc_html( $master_id ) . '</div>';
				} else {
					echo '<div class="ldmcs-no-master-id"><em>' . esc_html__( 'Not synced', 'learndash-master-client-sync' ) . '</em></div>';
				}
				echo '</div>';
			}
		} elseif ( 'ldmcs_push' === $column && 'master' === $mode ) {
			// Push button for master site.
			$post = get_post( $post_id );
			$content_type = $this->get_content_type_from_post_type( $post->post_type );
			
			if ( $content_type ) {
				?>
				<button type="button" 
					class="button button-small ldmcs-push-content" 
					data-content-id="<?php echo esc_attr( $post_id ); ?>" 
					data-content-type="<?php echo esc_attr( $content_type ); ?>"
					data-content-title="<?php echo esc_attr( $post->post_title ); ?>">
					<?php esc_html_e( 'Push', 'learndash-master-client-sync' ); ?>
				</button>
				<div class="ldmcs-push-status" id="ldmcs-push-status-<?php echo esc_attr( $content_type ); ?>-<?php echo esc_attr( $post_id ); ?>"></div>
				<?php
			}
		}
	}

	/**
	 * Get content type from post type.
	 *
	 * @param string $post_type Post type.
	 * @return string|false Content type or false if not a LearnDash type.
	 */
	private function get_content_type_from_post_type( $post_type ) {
		$mapping = array(
			'sfwd-courses'  => 'courses',
			'sfwd-lessons'  => 'lessons',
			'sfwd-topic'    => 'topics',
			'sfwd-quiz'     => 'quizzes',
			'sfwd-question' => 'questions',
		);

		return isset( $mapping[ $post_type ] ) ? $mapping[ $post_type ] : false;
	}
}
