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

		// Push course buttons - use event delegation to handle dynamically loaded content
		$(document).on('click', '.ldmcs-push-course', handlePushCourse);

		// Generate UUIDs button
		$('#ldmcs-generate-uuids').on('click', handleGenerateUuids);

		// Push content buttons (for all content types) - use event delegation
		$(document).on('click', '.ldmcs-push-content', handlePushContent);
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

		// Debug logging
		console.log('Push course button clicked', {courseId: courseId, courseTitle: courseTitle});

		if (!confirm(ldmcsAdmin.strings.confirmPush + '\n\n' + courseTitle)) {
			console.log('Push cancelled by user');
			return;
		}

		// Show modal
		console.log('Showing push modal for:', courseTitle);
		showPushModal(courseTitle);

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
					updateModalWithResults(response.data);
					
					// Auto-hide status after 5 seconds
					setTimeout(function() {
						$status.slideUp();
					}, 5000);
				} else {
					showStatus($status, 'error', ldmcsAdmin.strings.pushError + '<br>' + (response.data.message || ''));
					updateModalWithError(response.data.message || ldmcsAdmin.strings.pushError);
				}
			},
			error: function(xhr, status, error) {
				showStatus($status, 'error', ldmcsAdmin.strings.pushError);
				updateModalWithError(ldmcsAdmin.strings.pushError + ': ' + error);
			},
			complete: function() {
				$button.prop('disabled', false).removeClass('ldmcs-disabled');
			}
		});
	}

	/**
	 * Handle generate UUIDs button click.
	 */
	function handleGenerateUuids() {
		var $button = $(this);
		var $status = $('#ldmcs-generate-uuids-status');

		if (!confirm(ldmcsAdmin.strings.confirmGenerateUuids)) {
			return;
		}

		$button.prop('disabled', true).addClass('ldmcs-disabled');
		showStatus($status, 'loading', '<span class="ldmcs-spinner"></span>' + ldmcsAdmin.strings.generatingUuids);

		$.ajax({
			url: ldmcsAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ldmcs_generate_uuids',
				nonce: ldmcsAdmin.generateUuidsNonce
			},
			success: function(response) {
				if (response.success) {
					var message = $('<div>').text(response.data.message).html();
					if (response.data.results && response.data.results.details) {
						var $table = $('<table>').addClass('ldmcs-uuid-results-table');
						var $thead = $('<thead>').append(
							$('<tr>').append(
								$('<th>').text('Content Type'),
								$('<th>').text('Updated'),
								$('<th>').text('Already Had UUID'),
								$('<th>').text('Total')
							)
						);
						var $tbody = $('<tbody>');
						
						for (var type in response.data.results.details) {
							if (response.data.results.details.hasOwnProperty(type)) {
								var detail = response.data.results.details[type];
								$tbody.append(
									$('<tr>').append(
										$('<td>').text(type),
										$('<td>').text(detail.updated),
										$('<td>').text(detail.skipped),
										$('<td>').text(detail.total)
									)
								);
							}
						}
						
						$table.append($thead, $tbody);
						message += '<div class="ldmcs-uuid-results"></div>';
						showStatus($status, 'success', message);
						$status.find('.ldmcs-uuid-results').append($table);
					} else {
						showStatus($status, 'success', message);
					}
				} else {
					var errorMsg = ldmcsAdmin.strings.uuidsError;
					if (response.data && response.data.message) {
						errorMsg += '<br>' + $('<div>').text(response.data.message).html();
					}
					showStatus($status, 'error', errorMsg);
				}
			},
			error: function() {
				showStatus($status, 'error', ldmcsAdmin.strings.uuidsError);
			},
			complete: function() {
				$button.prop('disabled', false).removeClass('ldmcs-disabled');
			}
		});
	}

	/**
	 * Handle push content button click (generic for all content types).
	 */
	function handlePushContent() {
		var $button = $(this);
		var contentId = $button.data('content-id');
		var contentType = $button.data('content-type');
		var contentTitle = $button.data('content-title');
		var $status = $('#ldmcs-push-status-' + contentType + '-' + contentId);

		// Debug logging
		console.log('Push content button clicked', {
			contentId: contentId,
			contentType: contentType,
			contentTitle: contentTitle
		});

		if (!confirm(ldmcsAdmin.strings.confirmPush + '\n\n' + contentTitle)) {
			console.log('Push cancelled by user');
			return;
		}

		// Show modal
		console.log('Showing push modal for:', contentTitle);
		showPushModal(contentTitle);

		$button.prop('disabled', true).addClass('ldmcs-disabled');
		showStatus($status, 'loading', '<span class="ldmcs-spinner"></span>' + ldmcsAdmin.strings.pushing);

		$.ajax({
			url: ldmcsAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ldmcs_push_content',
				nonce: ldmcsAdmin.pushContentNonce,
				content_id: contentId,
				content_type: contentType
			},
			success: function(response) {
				if (response.success) {
					var message = response.data.message;
					showStatus($status, 'success', message);
					updateModalWithResults(response.data);
					
					// Auto-hide status after 5 seconds
					setTimeout(function() {
						$status.slideUp();
					}, 5000);
				} else {
					showStatus($status, 'error', ldmcsAdmin.strings.pushError + '<br>' + (response.data.message || ''));
					updateModalWithError(response.data.message || ldmcsAdmin.strings.pushError);
				}
			},
			error: function(xhr, status, error) {
				showStatus($status, 'error', ldmcsAdmin.strings.pushError);
				updateModalWithError(ldmcsAdmin.strings.pushError + ': ' + error);
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

	/**
	 * Escape HTML for safe display.
	 *
	 * @param {string} text Text to escape.
	 * @return {string} Escaped HTML.
	 */
	function escapeHtml(text) {
		return $('<div>').text(text).html();
	}

	/**
	 * Show push modal.
	 *
	 * @param {string} contentTitle Content title being pushed.
	 */
	function showPushModal(contentTitle) {
		var $modal = $('#ldmcs-push-modal');
		var $body = $('#ldmcs-modal-body');
		var escapedTitle = escapeHtml(contentTitle);

		// Debug logging
		console.log('showPushModal called', {
			modalFound: $modal.length > 0,
			bodyFound: $body.length > 0,
			contentTitle: contentTitle
		});

		if ($modal.length === 0) {
			console.error('Modal element #ldmcs-push-modal not found in DOM!');
			alert('Error: Push modal not found. Please refresh the page and try again.');
			return;
		}

		// Reset modal body
		$body.html('<div class="ldmcs-progress-item loading"><div class="ldmcs-progress-site"><span class="ldmcs-spinner"></span> Pushing "' + escapedTitle + '" to client sites...</div></div>');

		// Show modal
		console.log('Displaying modal');
		$modal.fadeIn();

		// Setup close handlers
		$modal.find('.ldmcs-modal-close, #ldmcs-modal-close-btn').off('click').on('click', function() {
			$modal.fadeOut();
		});

		// Close on outside click
		$(window).off('click.ldmcs-modal').on('click.ldmcs-modal', function(event) {
			if (event.target.id === 'ldmcs-push-modal') {
				$modal.fadeOut();
			}
		});
	}

	/**
	 * Update modal with push results.
	 *
	 * @param {object} data Response data from push operation.
	 */
	function updateModalWithResults(data) {
		var $body = $('#ldmcs-modal-body');
		var html = '';

		// Show overall message
		html += '<div class="ldmcs-progress-item success">';
		html += '<div class="ldmcs-progress-site">✓ ' + escapeHtml(data.message || 'Push completed successfully') + '</div>';
		html += '</div>';

		// Show details per client site
		if (data.results && data.results.details) {
			html += '<h3 style="margin-top: 20px; margin-bottom: 10px;">Client Site Results:</h3>';

			for (var siteUrl in data.results.details) {
				if (data.results.details.hasOwnProperty(siteUrl)) {
					var detail = data.results.details[siteUrl];
					var statusClass = detail.success ? 'success' : 'error';
					var icon = detail.success ? '✓' : '✗';

					html += '<div class="ldmcs-progress-item ' + statusClass + '">';
					html += '<div class="ldmcs-progress-site">' + icon + ' ' + escapeHtml(siteUrl) + '</div>';
					html += '<div class="ldmcs-progress-message">' + escapeHtml(detail.message || '') + '</div>';

					// Show additional data if available
					if (detail.data) {
						html += '<div class="ldmcs-progress-details">';
						if (detail.data.synced !== undefined) {
							html += '<strong>Synced:</strong> ' + detail.data.synced + ' | ';
						}
						if (detail.data.skipped !== undefined) {
							html += '<strong>Skipped:</strong> ' + detail.data.skipped + ' | ';
						}
						if (detail.data.errors !== undefined) {
							html += '<strong>Errors:</strong> ' + detail.data.errors;
						}
						html += '</div>';
					}

					html += '</div>';
				}
			}
		}

		$body.html(html);
	}

	/**
	 * Update modal with error message.
	 *
	 * @param {string} errorMessage Error message.
	 */
	function updateModalWithError(errorMessage) {
		var $body = $('#ldmcs-modal-body');
		var html = '<div class="ldmcs-progress-item error">';
		html += '<div class="ldmcs-progress-site">✗ Push Failed</div>';
		html += '<div class="ldmcs-progress-message">' + escapeHtml(errorMessage) + '</div>';
		html += '</div>';
		$body.html(html);
	}

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);
