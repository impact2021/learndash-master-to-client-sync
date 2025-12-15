<?php
/**
 * Uninstall script for LearnDash Master to Client Sync
 *
 * This file is executed when the plugin is deleted via the WordPress admin.
 * It removes all plugin data including options, database tables, and scheduled events.
 *
 * @package LearnDash_Master_Client_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Exit if not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data.
 */
function ldmcs_uninstall() {
	global $wpdb;

	// Remove scheduled events.
	$timestamp = wp_next_scheduled( 'ldmcs_sync_content' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'ldmcs_sync_content' );
	}

	// Remove plugin options.
	$options = array(
		'ldmcs_mode',
		'ldmcs_api_key',
		'ldmcs_master_url',
		'ldmcs_master_api_key',
		'ldmcs_sync_interval',
		'ldmcs_auto_sync_enabled',
		'ldmcs_sync_courses',
		'ldmcs_sync_lessons',
		'ldmcs_sync_topics',
		'ldmcs_sync_quizzes',
		'ldmcs_sync_questions',
		'ldmcs_conflict_resolution',
		'ldmcs_batch_size',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Remove database table.
	$table_name = $wpdb->prefix . 'ldmcs_sync_log';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

	// Remove post meta added by plugin.
	$wpdb->query(
		"DELETE FROM {$wpdb->postmeta} 
		WHERE meta_key IN ('_ldmcs_master_id', '_ldmcs_last_sync')"
	);

	// Clear any cached data.
	wp_cache_flush();
}

// Run uninstall.
ldmcs_uninstall();
