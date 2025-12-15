/**
 * Admin JavaScript for LearnDash Master to Client Sync
 */

(function($) {
	'use strict';

	/**
	 * Initialize admin functionality.
	 */
	function init() {
		// Verify connection button
		$('#ldmcs-verify-connection').on('click', handleVerifyConnection);

		// Manual sync button
		$('#ldmcs-manual-sync').on('click', handleManualSync);

		// Regenerate API key button
		$('#ldmcs-regenerate-key').on('click', handleRegenerateApiKey);

		// Push course buttons
		$('.ldmcs-push-course').on('click', handlePushCourse);
	}

	/**
	 * Handle verify connection button click.
	 */
	function handleVerifyConnection() {
		var $button = $(this);
		var $status = $('#ldmcs-connection-status');
		var masterUrl = $('#ldmcs_master_url').val();
		var apiKey = $('#ldmcs_master_api_key').val();

		if (!masterUrl || !apiKey) {
			showStatus($status, 'error', ldmcsAdmin.strings.connectionFailed + ' ' + 'Please enter both Master URL and API Key.');
			return;
		}

		$button.prop('disabled', true).addClass('ldmcs-disabled');
		showStatus($status, 'loading', '<span class="ldmcs-spinner"></span>' + ldmcsAdmin.strings.verifying);

		$.ajax({
			url: ldmcsAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ldmcs_verify_connection',
				nonce: ldmcsAdmin.verifyConnectionNonce,
				master_url: masterUrl,
				api_key: apiKey
			},
			success: function(response) {
				if (response.success) {
					var message = ldmcsAdmin.strings.connectionVerified;
					if (response.data.site_url) {
						message += '<br><strong>Master Site:</strong> ' + response.data.site_url;
					}
					if (response.data.version) {
						message += '<br><strong>Plugin Version:</strong> ' + response.data.version;
					}
					showStatus($status, 'success', message);
				} else {
					showStatus($status, 'error', ldmcsAdmin.strings.connectionFailed + '<br>' + (response.data.message || ''));
				}
			},
			error: function() {
				showStatus($status, 'error', ldmcsAdmin.strings.connectionFailed);
			},
			complete: function() {
				$button.prop('disabled', false).removeClass('ldmcs-disabled');
			}
		});
	}

	/**
	 * Handle manual sync button click.
	 */
	function handleManualSync() {
		var $button = $(this);
		var $status = $('#ldmcs-sync-status');

		$button.prop('disabled', true).addClass('ldmcs-disabled');
		showStatus($status, 'loading', '<span class="ldmcs-spinner"></span>' + ldmcsAdmin.strings.syncing);

		$.ajax({
			url: ldmcsAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ldmcs_manual_sync',
				nonce: ldmcsAdmin.manualSyncNonce,
				content_types: [] // Empty array means sync all enabled types
			},
			success: function(response) {
				if (response.success) {
					var message = ldmcsAdmin.strings.syncComplete;
					message += '<br><strong>Synced:</strong> ' + response.data.synced;
					message += '<br><strong>Skipped:</strong> ' + response.data.skipped;
					message += '<br><strong>Errors:</strong> ' + response.data.errors;

					if (response.data.details) {
						message += '<div class="ldmcs-sync-results"><table>';
						message += '<thead><tr><th>Content Type</th><th>Synced</th><th>Skipped</th><th>Errors</th></tr></thead>';
						message += '<tbody>';
						for (var type in response.data.details) {
							var detail = response.data.details[type];
							message += '<tr>';
							message += '<td>' + type + '</td>';
							message += '<td>' + detail.synced + '</td>';
							message += '<td>' + detail.skipped + '</td>';
							message += '<td>' + detail.errors + '</td>';
							message += '</tr>';
						}
						message += '</tbody></table></div>';
					}

					showStatus($status, 'success', message);
				} else {
					showStatus($status, 'error', ldmcsAdmin.strings.syncError + '<br>' + (response.data.message || ''));
				}
			},
			error: function() {
				showStatus($status, 'error', ldmcsAdmin.strings.syncError);
			},
			complete: function() {
				$button.prop('disabled', false).removeClass('ldmcs-disabled');
			}
		});
	}

	/**
	 * Handle regenerate API key button click.
	 */
	function handleRegenerateApiKey() {
		if (!confirm('Are you sure you want to regenerate the API key? All client sites will need to be updated with the new key.')) {
			return;
		}

		var $button = $(this);
		var $input = $('#ldmcs_api_key');

		$button.prop('disabled', true);

		// Generate a random API key (client-side generation for immediate feedback)
		var newKey = generateRandomString(32);
		$input.val(newKey);

		// Note: The actual save happens when the form is submitted
		alert('API key has been regenerated. Please save the settings to apply the changes.');

		$button.prop('disabled', false);
	}

	/**
	 * Generate a random string.
	 *
	 * @param {number} length String length.
	 * @return {string} Random string.
	 */
	function generateRandomString(length) {
		var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		var result = '';
		for (var i = 0; i < length; i++) {
			result += chars.charAt(Math.floor(Math.random() * chars.length));
		}
		return result;
	}

	/**
	 * Handle push course button click.
	 */
	function handlePushCourse() {
		var $button = $(this);
		var courseId = $button.data('course-id');
		var courseTitle = $button.data('course-title');
		var $status = $('#ldmcs-push-status-' + courseId);

		if (!confirm(ldmcsAdmin.strings.confirmPush + '\n\n' + courseTitle)) {
			return;
		}

		$button.prop('disabled', true).addClass('ldmcs-disabled');
		showStatus($status, 'loading', '<span class="ldmcs-spinner"></span>' + ldmcsAdmin.strings.pushing);

		$.ajax({
			url: ldmcsAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ldmcs_push_course',
				nonce: ldmcsAdmin.pushCourseNonce,
				course_id: courseId
			},
			success: function(response) {
				if (response.success) {
					var message = response.data.message;
					showStatus($status, 'success', message);
					
					// Auto-hide after 5 seconds
					setTimeout(function() {
						$status.slideUp();
					}, 5000);
				} else {
					showStatus($status, 'error', ldmcsAdmin.strings.pushError + '<br>' + (response.data.message || ''));
				}
			},
			error: function() {
				showStatus($status, 'error', ldmcsAdmin.strings.pushError);
			},
			complete: function() {
				$button.prop('disabled', false).removeClass('ldmcs-disabled');
			}
		});
	}

	/**
	 * Show status message.
	 *
	 * @param {jQuery} $element Status element.
	 * @param {string} type     Status type (success, error, loading).
	 * @param {string} message  Status message.
	 */
	function showStatus($element, type, message) {
		$element
			.removeClass('success error loading')
			.addClass(type)
			.html(message)
			.slideDown();
	}

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);
