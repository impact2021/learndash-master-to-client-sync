<?php
/**
 * Logger class for tracking sync operations.
 *
 * @package LearnDash_Master_Client_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class.
 */
class LDMCS_Logger {

	/**
	 * Log a sync operation.
	 *
	 * @param string $sync_type    Sync type (master_push, client_pull).
	 * @param string $content_type Content type (course, lesson, topic, quiz, question).
	 * @param int    $content_id   Content ID.
	 * @param string $status       Status (success, error, skipped).
	 * @param string $message      Optional message.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function log( $sync_type, $content_type, $content_id, $status, $message = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ldmcs_sync_log';

		$result = $wpdb->insert(
			$table_name,
			array(
				'sync_type'    => sanitize_text_field( $sync_type ),
				'content_type' => sanitize_text_field( $content_type ),
				'content_id'   => absint( $content_id ),
				'status'       => sanitize_text_field( $status ),
				'message'      => sanitize_textarea_field( $message ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get recent logs.
	 *
	 * @param int $limit Number of logs to retrieve.
	 * @return array
	 */
	public static function get_recent_logs( $limit = 100 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ldmcs_sync_log';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Clear old logs.
	 *
	 * @param int $days Number of days to keep logs.
	 * @return int|false Number of rows deleted or false on failure.
	 */
	public static function clear_old_logs( $days = 30 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ldmcs_sync_log';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return $result;
	}
}
