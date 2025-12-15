<?php
/**
 * Plugin Name: LearnDash Master-Client Sync
 * Plugin URI:  https://example.com/
 * Description: Unified plugin to push/receive courses, lessons, topics, quizzes, and questions between LearnDash master and client sites via REST API.
 * Version:     2.0
 * Author:      Impact Websites
 * Author URI:  https://example.com/
 * License:     GPL2
 * Text Domain: learndash-sync
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Plugin Configuration Constants
define('LD_SYNC_PUSH_TIMEOUT', 45); // Push request timeout in seconds
define('LD_SYNC_RATE_LIMIT', 10);   // Max test endpoint requests per minute per IP

// =========================================================================
// ADMIN MENU REGISTRATION
// =========================================================================

add_action('admin_menu', 'ld_sync_register_menus');

function ld_sync_register_menus() {
    // Main menu
    add_menu_page(
        'LearnDash Sync',
        'LearnDash Sync',
        'manage_options',
        'learndash-sync',
        'ld_sync_main_page',
        'dashicons-update',
        100
    );
    
    // Master Push submenu
    add_submenu_page(
        'learndash-sync',
        'Master Push',
        'Master Push',
        'manage_options',
        'learndash-sync',
        'ld_sync_main_page'
    );
    
    // Client Receive submenu
    add_submenu_page(
        'learndash-sync',
        'Client Receive',
        'Client Receive',
        'manage_options',
        'learndash-sync-receive',
        'ld_sync_receive_page'
    );
}

// =========================================================================
// MASTER PUSH - ADMIN PAGE
// =========================================================================

function ld_sync_main_page() {
    if (!current_user_can('manage_options')) return;
    
    ?>
    <div class="wrap">
        <h1>LearnDash Sync - Master Push</h1>
        <p>Push courses from this master site to multiple client sites.</p>

        <?php ld_sync_handle_client_actions(); ?>
        <?php ld_sync_handle_push_action(); ?>
        <?php ld_sync_render_add_client_form(); ?>
        <?php ld_sync_render_existing_clients(); ?>
        <?php ld_sync_render_push_courses_form(); ?>
    </div>
    <?php
}

// =========================================================================
// MASTER PUSH - CLIENT MANAGEMENT
// =========================================================================

function ld_sync_handle_client_actions() {
    $clients = get_option('ld_master_clients', []);

    // Handle add/update client
    if (isset($_POST['ld_master_add_client']) && check_admin_referer('ld_master_add_client_action', 'ld_master_add_client_nonce')) {
        $url = esc_url_raw($_POST['client_url']);
        $secret = sanitize_text_field($_POST['client_secret']);
        if ($url && $secret) {
            $clients[$url] = $secret;
            update_option('ld_master_clients', $clients);
            echo '<div class="notice notice-success"><p>Client added/updated successfully.</p></div>';
        }
    }

    // Handle delete client
    if (isset($_GET['delete_client']) && check_admin_referer('ld_master_delete_client_action', 'ld_master_delete_client_nonce')) {
        $del_url = esc_url_raw($_GET['delete_client']);
        if (isset($clients[$del_url])) {
            unset($clients[$del_url]);
            update_option('ld_master_clients', $clients);
            echo '<div class="notice notice-success"><p>Client deleted successfully.</p></div>';
        }
    }
}

function ld_sync_render_add_client_form() {
    ?>
    <h2>Add Client Site</h2>
    <form method="post">
        <?php wp_nonce_field('ld_master_add_client_action', 'ld_master_add_client_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="client_url">Client URL</label></th>
                <td>
                    <input type="url" id="client_url" name="client_url" size="50" required 
                           placeholder="https://client-site.com/wp-json/ld-sync/v1/receive" 
                           class="regular-text" />
                    <p class="description">Full REST API endpoint URL for the client site.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="client_secret">Secret Key</label></th>
                <td>
                    <input type="text" id="client_secret" name="client_secret" size="50" required 
                           placeholder="SECRET_KEY1" class="regular-text" />
                    <p class="description">Shared secret key configured on the client site.</p>
                </td>
            </tr>
        </table>
        <p><input type="submit" name="ld_master_add_client" class="button button-primary" value="Add / Update Client"></p>
    </form>
    <?php
}

function ld_sync_render_existing_clients() {
    $clients = get_option('ld_master_clients', []);
    ?>
    <h2>Existing Client Sites</h2>
    <?php if ($clients): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Client URL</th>
                    <th>Secret Key</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $url => $secret): ?>
                    <tr>
                        <td><?php echo esc_url($url); ?></td>
                        <td><code><?php echo esc_html($secret); ?></code></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=learndash-sync&delete_client=' . urlencode($url)), 'ld_master_delete_client_action', 'ld_master_delete_client_nonce'); ?>" 
                               class="button button-small" 
                               onclick="return confirm('Are you sure you want to delete this client?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No client sites configured yet.</p>
    <?php endif;
}

// =========================================================================
// MASTER PUSH - COURSE PUSH
// =========================================================================

function ld_sync_handle_push_action() {
    if (!isset($_POST['ld_master_push_all']) || !check_admin_referer('ld_master_push_all_action', 'ld_master_push_all_nonce')) {
        return;
    }

    $clients = get_option('ld_master_clients', []);
    $selected_courses = !empty($_POST['course_ids']) ? array_map('intval', $_POST['course_ids']) : [];
    
    if (empty($selected_courses)) {
        echo '<div class="notice notice-error"><p>Please select at least one course to push.</p></div>';
        return;
    }

    $results = [];
    foreach ($clients as $url => $secret) {
        $course_data = [];

        foreach ($selected_courses as $course_id) {
            $course_data[] = ld_sync_export_course($course_id);
        }

        $body = [
            'courses' => $course_data,
            'SECRET_KEY1' => $secret
        ];

        $response = wp_remote_post($url, [
            'method'  => 'POST',
            'timeout' => LD_SYNC_PUSH_TIMEOUT,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            $results[$url] = ['error' => $response->get_error_message()];
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            $results[$url] = [
                'code' => $response_code,
                'response' => $response_body
            ];
        }
    }

    echo '<div class="notice notice-success"><p>Push completed!</p></div>';
    echo '<h2>Push Results</h2>';
    echo '<div style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 3px;">';
    
    // Display results in a safe, formatted way (without exposing sensitive data)
    foreach ($results as $url => $result) {
        // Extract and display only the scheme and host (hide full URL paths)
        $parsed_url = parse_url($url);
        $safe_url = esc_html(($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? 'unknown'));
        echo '<h3>' . $safe_url . '</h3>';
        
        if (isset($result['error'])) {
            echo '<p style="color: #dc3232;"><strong>Error:</strong> ' . esc_html($result['error']) . '</p>';
        } else {
            echo '<p style="color: #46b450;"><strong>Status Code:</strong> ' . intval($result['code']) . '</p>';
            if (isset($result['response']['status'])) {
                echo '<p><strong>Response:</strong> ' . esc_html($result['response']['status']) . '</p>';
            }
            if (isset($result['response']['message'])) {
                echo '<p><strong>Message:</strong> ' . esc_html($result['response']['message']) . '</p>';
            }
            if (isset($result['response']['result'])) {
                $counts = [
                    'courses' => count($result['response']['result']['courses'] ?? []),
                    'lessons' => count($result['response']['result']['lessons'] ?? []),
                    'topics' => count($result['response']['result']['topics'] ?? []),
                    'quizzes' => count($result['response']['result']['quizzes'] ?? []),
                    'questions' => count($result['response']['result']['questions'] ?? []),
                ];
                echo '<p><strong>Synced:</strong> ' . intval($counts['courses']) . ' courses, ' . 
                     intval($counts['lessons']) . ' lessons, ' . intval($counts['topics']) . ' topics, ' . 
                     intval($counts['quizzes']) . ' quizzes, ' . intval($counts['questions']) . ' questions</p>';
            }
        }
        echo '<hr>';
    }
    
    echo '</div>';
}

function ld_sync_render_push_courses_form() {
    ?>
    <h2>Push Selected Courses</h2>
    <form method="post">
        <?php wp_nonce_field('ld_master_push_all_action', 'ld_master_push_all_nonce'); ?>
        <?php
        $courses = get_posts(['post_type' => 'sfwd-courses', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        if ($courses):
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th style="width: 50px;"><input type="checkbox" id="select-all-courses" /></th>';
            echo '<th>Course Title</th>';
            echo '<th>UUID</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($courses as $course):
                $uuid = get_post_meta($course->ID, 'ld_uuid', true) ?: '(No UUID - will be generated)';
                echo '<tr>';
                echo '<td><input type="checkbox" name="course_ids[]" value="' . intval($course->ID) . '" class="course-checkbox"></td>';
                echo '<td><strong>' . esc_html($course->post_title) . '</strong></td>';
                echo '<td><code>' . esc_html($uuid) . '</code></td>';
                echo '</tr>';
            endforeach;
            echo '</tbody></table>';
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#select-all-courses').on('change', function() {
                    $('.course-checkbox').prop('checked', $(this).prop('checked'));
                });
            });
            </script>
            <?php
        else:
            echo '<p>No LearnDash courses found. Make sure LearnDash is installed and you have created some courses.</p>';
        endif;
        ?>
        <p>
            <input type="submit" name="ld_master_push_all" class="button button-primary button-large" value="Push Selected Courses to All Clients">
        </p>
    </form>
    <?php
}

// =========================================================================
// MASTER PUSH - EXPORT COURSE DATA
// =========================================================================

function ld_sync_export_course($course_id) {
    $course = get_post($course_id);
    if (!$course) return [];

    // Generate or retrieve UUID
    $uuid = get_post_meta($course_id, 'ld_uuid', true);
    if (!$uuid) {
        $uuid = wp_generate_uuid4();
        update_post_meta($course_id, 'ld_uuid', $uuid);
    }

    $export = [
        'uuid' => $uuid,
        'title' => $course->post_title,
        'content' => $course->post_content,
        'lessons' => [],
        'quizzes' => []
    ];

    // Export lessons
    $lessons = get_posts([
        'post_type' => 'sfwd-lessons',
        'numberposts' => -1,
        'meta_key' => 'course_id',
        'meta_value' => $course_id,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ]);

    foreach ($lessons as $lesson) {
        $lesson_uuid = get_post_meta($lesson->ID, 'ld_uuid', true);
        if (!$lesson_uuid) {
            $lesson_uuid = wp_generate_uuid4();
            update_post_meta($lesson->ID, 'ld_uuid', $lesson_uuid);
        }

        $lesson_export = [
            'uuid' => $lesson_uuid,
            'title' => $lesson->post_title,
            'content' => $lesson->post_content,
            'topics' => [],
            'quizzes' => []
        ];

        // Export topics for this lesson
        $topics = get_posts([
            'post_type' => 'sfwd-topic',
            'numberposts' => -1,
            'meta_key' => 'lesson_id',
            'meta_value' => $lesson->ID,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);

        foreach ($topics as $topic) {
            $topic_uuid = get_post_meta($topic->ID, 'ld_uuid', true);
            if (!$topic_uuid) {
                $topic_uuid = wp_generate_uuid4();
                update_post_meta($topic->ID, 'ld_uuid', $topic_uuid);
            }
            
            $lesson_export['topics'][] = [
                'uuid' => $topic_uuid,
                'title' => $topic->post_title,
                'content' => $topic->post_content
            ];
        }

        // Export quizzes for this lesson
        $lesson_quizzes = get_posts([
            'post_type' => 'sfwd-quiz',
            'numberposts' => -1,
            'meta_key' => 'lesson_id',
            'meta_value' => $lesson->ID,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);

        foreach ($lesson_quizzes as $quiz) {
            $quiz_uuid = get_post_meta($quiz->ID, 'ld_uuid', true);
            if (!$quiz_uuid) {
                $quiz_uuid = wp_generate_uuid4();
                update_post_meta($quiz->ID, 'ld_uuid', $quiz_uuid);
            }

            $quiz_export = [
                'uuid' => $quiz_uuid,
                'title' => $quiz->post_title,
                'content' => $quiz->post_content,
                'questions' => []
            ];

            // Export questions for this quiz
            $questions = get_posts([
                'post_type' => 'sfwd-question',
                'numberposts' => -1,
                'meta_key' => 'quiz_id',
                'meta_value' => $quiz->ID,
                'orderby' => 'menu_order',
                'order' => 'ASC'
            ]);

            foreach ($questions as $question) {
                $question_uuid = get_post_meta($question->ID, 'ld_uuid', true);
                if (!$question_uuid) {
                    $question_uuid = wp_generate_uuid4();
                    update_post_meta($question->ID, 'ld_uuid', $question_uuid);
                }

                $quiz_export['questions'][] = [
                    'uuid' => $question_uuid,
                    'title' => $question->post_title,
                    'content' => $question->post_content
                ];
            }

            $lesson_export['quizzes'][] = $quiz_export;
        }

        $export['lessons'][] = $lesson_export;
    }

    // Export course-level quizzes
    $course_quizzes = get_posts([
        'post_type' => 'sfwd-quiz',
        'numberposts' => -1,
        'meta_key' => 'course_id',
        'meta_value' => $course_id,
        'meta_compare' => '=',
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ]);

    // Filter out quizzes that belong to lessons
    foreach ($course_quizzes as $quiz) {
        $lesson_id = get_post_meta($quiz->ID, 'lesson_id', true);
        if ($lesson_id) continue; // Skip if belongs to a lesson

        $quiz_uuid = get_post_meta($quiz->ID, 'ld_uuid', true);
        if (!$quiz_uuid) {
            $quiz_uuid = wp_generate_uuid4();
            update_post_meta($quiz->ID, 'ld_uuid', $quiz_uuid);
        }

        $quiz_export = [
            'uuid' => $quiz_uuid,
            'title' => $quiz->post_title,
            'content' => $quiz->post_content,
            'questions' => []
        ];

        // Export questions for this quiz
        $questions = get_posts([
            'post_type' => 'sfwd-question',
            'numberposts' => -1,
            'meta_key' => 'quiz_id',
            'meta_value' => $quiz->ID,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);

        foreach ($questions as $question) {
            $question_uuid = get_post_meta($question->ID, 'ld_uuid', true);
            if (!$question_uuid) {
                $question_uuid = wp_generate_uuid4();
                update_post_meta($question->ID, 'ld_uuid', $question_uuid);
            }

            $quiz_export['questions'][] = [
                'uuid' => $question_uuid,
                'title' => $question->post_title,
                'content' => $question->post_content
            ];
        }

        $export['quizzes'][] = $quiz_export;
    }

    return $export;
}

// =========================================================================
// CLIENT RECEIVE - ADMIN PAGE
// =========================================================================

function ld_sync_receive_page() {
    if (!current_user_can('manage_options')) return;

    // Handle secret key save
    if (isset($_POST['ld_client_secret_save']) && check_admin_referer('ld_client_secret_action', 'ld_client_secret_nonce')) {
        update_option('ld_client_secret_key', sanitize_text_field($_POST['client_secret']));
        echo '<div class="notice notice-success"><p>Secret key saved successfully.</p></div>';
    }

    $current_key = get_option('ld_client_secret_key', '');
    ?>
    <div class="wrap">
        <h1>LearnDash Sync - Client Receive</h1>
        <p>Configure this site to receive courses from a master site.</p>

        <h2>Secret Key Configuration</h2>
        <form method="post">
            <?php wp_nonce_field('ld_client_secret_action', 'ld_client_secret_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="client_secret">Secret Key (SECRET_KEY1)</label></th>
                    <td>
                        <input type="text" id="client_secret" name="client_secret" size="50" 
                               value="<?php echo esc_attr($current_key); ?>" class="regular-text" required>
                        <p class="description">This key must match the secret key configured on the master site for this client.</p>
                    </td>
                </tr>
            </table>
            <p>
                <input type="submit" name="ld_client_secret_save" class="button button-primary" value="Save Secret Key">
            </p>
        </form>

        <h2>REST API Endpoints</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Receive Endpoint</th>
                <td>
                    <code><?php echo esc_url(rest_url('ld-sync/v1/receive')); ?></code>
                    <p class="description">Use this URL on the master site to push courses to this client.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Test Endpoint</th>
                <td>
                    <code><?php echo esc_url(rest_url('ld-sync/v1/test')); ?></code>
                    <p class="description">Test if the REST API is working correctly.</p>
                </td>
            </tr>
        </table>

        <h2>Test Connection</h2>
        <p>
            <a href="<?php echo esc_url(rest_url('ld-sync/v1/test')); ?>" class="button" target="_blank">Test REST API</a>
        </p>
    </div>
    <?php
}

// =========================================================================
// CLIENT RECEIVE - REST API ROUTES
// =========================================================================

add_action('rest_api_init', 'ld_sync_register_rest_routes');

function ld_sync_register_rest_routes() {
    // Receive endpoint - publicly accessible but requires valid SECRET_KEY1 in request body
    register_rest_route('ld-sync/v1', '/receive', [
        'methods'  => 'POST',
        'callback' => 'ld_sync_receive_callback',
        'permission_callback' => '__return_true', // Authentication via secret key in callback
    ]);

    // Test endpoint (with rate limiting via transient)
    register_rest_route('ld-sync/v1', '/test', [
        'methods'  => 'GET',
        'callback' => function () {
            // Rate limiting based on IP address
            // Get client IP - use REMOTE_ADDR first as it's most reliable
            // X-Forwarded-For can be manipulated, so only use as fallback
            $ip = 'unknown';
            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // If behind proxy, get first IP (client IP) from the chain
                $forwarded_ips = array_map('trim', explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']))));
                $ip = $forwarded_ips[0];
            }
            $transient_key = 'ld_sync_test_rate_' . md5($ip);
            $request_count = get_transient($transient_key) ?: 0;
            
            if ($request_count >= LD_SYNC_RATE_LIMIT) {
                return new WP_Error(
                    'rate_limit_exceeded',
                    'Too many requests. Please try again later.',
                    ['status' => 429]
                );
            }
            
            set_transient($transient_key, $request_count + 1, 60);
            
            return [
                'status' => 'success',
                'message' => 'LearnDash Sync REST API is working',
                'timestamp' => current_time('mysql')
            ];
        },
        'permission_callback' => '__return_true',
    ]);
}

// =========================================================================
// CLIENT RECEIVE - RECEIVE CALLBACK
// =========================================================================

function ld_sync_receive_callback($request) {
    $body = $request->get_json_params();

    // Verify secret key
    $received_key = $body['SECRET_KEY1'] ?? '';
    $expected_key = get_option('ld_client_secret_key');

    if ($received_key !== $expected_key || empty($expected_key)) {
        return new WP_Error(
            'invalid_secret',
            'Invalid or missing SECRET_KEY1',
            ['status' => 403]
        );
    }

    $result = [];

    if (!empty($body['courses']) && is_array($body['courses'])) {
        foreach ($body['courses'] as $course) {
            // Sync course
            $course_id = ld_sync_insert_or_update_post($course, 'sfwd-courses');
            $result['courses'][] = ['uuid' => $course['uuid'], 'id' => $course_id];

            // Sync lessons
            if (!empty($course['lessons']) && is_array($course['lessons'])) {
                foreach ($course['lessons'] as $lesson) {
                    $lesson['parent_course_id'] = $course_id;
                    $lesson_id = ld_sync_insert_or_update_post($lesson, 'sfwd-lessons');
                    $result['lessons'][] = ['uuid' => $lesson['uuid'], 'id' => $lesson_id];

                    // Sync topics
                    if (!empty($lesson['topics']) && is_array($lesson['topics'])) {
                        foreach ($lesson['topics'] as $topic) {
                            $topic['parent_lesson_id'] = $lesson_id;
                            $topic['parent_course_id'] = $course_id;
                            $topic_id = ld_sync_insert_or_update_post($topic, 'sfwd-topic');
                            $result['topics'][] = ['uuid' => $topic['uuid'], 'id' => $topic_id];
                        }
                    }

                    // Sync lesson quizzes
                    if (!empty($lesson['quizzes']) && is_array($lesson['quizzes'])) {
                        foreach ($lesson['quizzes'] as $quiz) {
                            $quiz['parent_lesson_id'] = $lesson_id;
                            $quiz['parent_course_id'] = $course_id;
                            $quiz_id = ld_sync_insert_or_update_post($quiz, 'sfwd-quiz');
                            $result['quizzes'][] = ['uuid' => $quiz['uuid'], 'id' => $quiz_id];

                            // Sync questions
                            if (!empty($quiz['questions']) && is_array($quiz['questions'])) {
                                foreach ($quiz['questions'] as $question) {
                                    $question['parent_quiz_id'] = $quiz_id;
                                    $question_id = ld_sync_insert_or_update_post($question, 'sfwd-question');
                                    $result['questions'][] = ['uuid' => $question['uuid'], 'id' => $question_id];
                                }
                            }
                        }
                    }
                }
            }

            // Sync course-level quizzes
            if (!empty($course['quizzes']) && is_array($course['quizzes'])) {
                foreach ($course['quizzes'] as $quiz) {
                    $quiz['parent_course_id'] = $course_id;
                    $quiz_id = ld_sync_insert_or_update_post($quiz, 'sfwd-quiz');
                    $result['quizzes'][] = ['uuid' => $quiz['uuid'], 'id' => $quiz_id];

                    // Sync questions
                    if (!empty($quiz['questions']) && is_array($quiz['questions'])) {
                        foreach ($quiz['questions'] as $question) {
                            $question['parent_quiz_id'] = $quiz_id;
                            $question_id = ld_sync_insert_or_update_post($question, 'sfwd-question');
                            $result['questions'][] = ['uuid' => $question['uuid'], 'id' => $question_id];
                        }
                    }
                }
            }
        }
    }

    return [
        'status'  => 'success',
        'message' => 'LearnDash content synced successfully',
        'result'  => $result,
        'timestamp' => current_time('mysql')
    ];
}

// =========================================================================
// CLIENT RECEIVE - INSERT OR UPDATE POST
// =========================================================================

function ld_sync_insert_or_update_post($data, $post_type) {
    $uuid = isset($data['uuid']) ? sanitize_text_field($data['uuid']) : '';
    if (!$uuid) return 0;

    // Check if post exists by UUID
    $existing = get_posts([
        'post_type'      => $post_type,
        'meta_key'       => 'ld_uuid',
        'meta_value'     => $uuid,
        'posts_per_page' => 1,
        'post_status'    => 'any',
    ]);

    $post_data = [
        'post_title'   => sanitize_text_field($data['title'] ?? ''),
        'post_content' => wp_kses_post($data['content'] ?? ''),
        'post_type'    => $post_type,
        'post_status'  => 'publish',
    ];

    if ($existing) {
        // Update existing post
        $post_data['ID'] = $existing[0]->ID;
        $post_id = wp_update_post($post_data);
    } else {
        // Create new post
        $post_id = wp_insert_post($post_data);
        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, 'ld_uuid', $uuid);
        }
    }

    if (is_wp_error($post_id)) {
        return 0;
    }

    // Set parent relationships
    if (!empty($data['parent_course_id'])) {
        update_post_meta($post_id, 'course_id', (int) $data['parent_course_id']);
    }
    if (!empty($data['parent_lesson_id'])) {
        update_post_meta($post_id, 'lesson_id', (int) $data['parent_lesson_id']);
    }
    if (!empty($data['parent_quiz_id'])) {
        update_post_meta($post_id, 'quiz_id', (int) $data['parent_quiz_id']);
    }

    return $post_id;
}
