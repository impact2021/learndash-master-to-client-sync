# LearnDash Master-Client Sync - Developer Guide

## Overview

This plugin combines two functionalities into a single, easy-to-edit file:
1. **Master Push** - Push courses from a master site to multiple client sites
2. **Client Receive** - Receive and sync courses on client sites

## File Structure

The plugin is organized in `/learndash-sync.php` with the following sections:

### 0. Configuration Constants (Lines ~16-18)
- `LD_SYNC_PUSH_TIMEOUT` - Push request timeout (default: 45 seconds)
- `LD_SYNC_RATE_LIMIT` - Max test endpoint requests per minute per IP (default: 10)
- **Easy to edit**: Change these values to adjust plugin behavior

### 1. Admin Menu Registration (Lines ~23-48)
- Registers main menu "LearnDash Sync"
- Creates two submenus: "Master Push" and "Client Receive"
- **Easy to edit**: Change menu labels, icons, or positions here

### 2. Master Push Section (Lines ~47-445)

#### Admin Page (Lines ~47-65)
Main page rendering function that calls all component functions.

#### Client Management (Lines ~67-170)
- **Add/Update Clients**: Form to add client site URLs and secret keys
- **Delete Clients**: Remove client sites from the list
- **Display Clients**: Table showing all configured clients
- **Easy to edit**: Modify form fields, add validation, or change table columns

#### Course Push (Lines ~172-275)
- **Push Logic**: Sends selected courses to all client sites
- **Course Selection**: Display courses with checkboxes and UUID column
- **Easy to edit**: Add filters, change timeout values, modify response display

#### Export Function (Lines ~277-445)
- **UUID Generation**: Creates or retrieves UUIDs for tracking
- **Hierarchical Export**: Exports courses → lessons → topics/quizzes → questions
- **Easy to edit**: Add custom fields, modify data structure, change query parameters

### 3. Client Receive Section (Lines ~447-600)

#### Admin Page (Lines ~447-510)
- **Secret Key Form**: Configure authentication
- **REST Endpoints Display**: Shows URLs for API access
- **Easy to edit**: Add status indicators, test buttons, or logging

#### REST API Routes (Lines ~512-535)
- **Receive Endpoint**: POST `/ld-sync/v1/receive` - receives course data
- **Test Endpoint**: GET `/ld-sync/v1/test` - tests API connectivity
- **Easy to edit**: Add new endpoints, modify authentication, change response format

#### Receive Callback (Lines ~537-600)
- **Authentication**: Validates secret key
- **Recursive Import**: Processes courses, lessons, topics, quizzes, questions
- **Easy to edit**: Add custom processing, logging, or error handling

#### Insert/Update Function (Lines ~602-660)
- **UUID Matching**: Finds existing content by UUID
- **Content Sync**: Creates new or updates existing posts
- **Relationship Management**: Maintains parent-child connections
- **Easy to edit**: Add custom meta fields, modify sanitization, change relationship logic

## Common Customization Tasks

### 1. Add Custom Fields to Export

Find the export function (line ~277) and add fields to the export array:

```php
$export = [
    'uuid' => $uuid,
    'title' => $course->post_title,
    'content' => $course->post_content,
    'my_custom_field' => get_post_meta($course_id, 'my_custom_field', true),
    'lessons' => []
];
```

Then in the import function (line ~602), save the custom field:

```php
if (!empty($data['my_custom_field'])) {
    update_post_meta($post_id, 'my_custom_field', sanitize_text_field($data['my_custom_field']));
}
```

### 2. Change Timeout for Push

Find the constant at the top of the file (line ~16):

```php
define('LD_SYNC_PUSH_TIMEOUT', 45); // Change this value (in seconds)
```

Or find line ~203 in the push function:

```php
$response = wp_remote_post($url, [
    'method'  => 'POST',
    'timeout' => LD_SYNC_PUSH_TIMEOUT,  // Uses constant defined at top
    'headers' => ['Content-Type' => 'application/json'],
    'body'    => wp_json_encode($body),
]);
```

### 3. Add Logging

Add logging to track operations. Example in the receive callback (line ~537):

```php
function ld_sync_receive_callback($request) {
    // Add at the start
    error_log('LearnDash Sync: Received push request');
    
    $body = $request->get_json_params();
    error_log('LearnDash Sync: Processing ' . count($body['courses'] ?? []) . ' courses');
    
    // ... rest of function
}
```

### 4. Filter Courses by Category

In the push form rendering (line ~237), add a category filter:

```php
$courses = get_posts([
    'post_type' => 'sfwd-courses',
    'numberposts' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
    'tax_query' => [ // Add this
        [
            'taxonomy' => 'ld_course_category',
            'field' => 'slug',
            'terms' => 'your-category-slug',
        ]
    ]
]);
```

### 5. Add Email Notifications

Add to the push completion (after line ~218):

```php
// After push results
$admin_email = get_option('admin_email');
$subject = 'LearnDash Sync: Push Completed';
$message = 'Courses pushed successfully: ' . print_r($results, true);
wp_mail($admin_email, $subject, $message);
```

## Security Notes

### Current Security Measures
1. **Nonce verification** on all forms
2. **Capability checks** (`manage_options` required)
3. **Input sanitization** using WordPress functions
4. **Secret key authentication** for REST API receive endpoint
5. **Rate limiting** on test endpoint (10 requests per minute per IP)
6. **HTML sanitization** using `wp_kses_post()` for content

### Best Practices
- Always use WordPress sanitization functions
- Never trust user input
- Use nonces for all forms
- Check user capabilities before sensitive operations
- Keep secret keys secure and unique per client

## Database Schema

### Custom Meta Fields

#### All Post Types
- `ld_uuid` (string) - Universal unique identifier for tracking across sites

#### Lessons, Topics, Quizzes
- `course_id` (int) - Parent course ID
- `lesson_id` (int) - Parent lesson ID (for topics and quizzes)
- `quiz_id` (int) - Parent quiz ID (for questions)

## WordPress Options

### Master Site
- `ld_master_clients` (array) - Stores client URLs and secret keys
  ```php
  [
      'https://client1.com/wp-json/ld-sync/v1/receive' => 'secret123',
      'https://client2.com/wp-json/ld-sync/v1/receive' => 'secret456'
  ]
  ```

### Client Sites
- `ld_client_secret_key` (string) - Authentication secret for receiving pushes

## Testing

### Test Master Push
1. Install LearnDash on master site
2. Create test course with lessons, topics, quizzes
3. Add a client site in Master Push settings
4. Select course and push

### Test Client Receive
1. Install LearnDash on client site
2. Configure secret key
3. Test endpoint: Visit `/wp-json/ld-sync/v1/test`
4. Should return: `{"status":"success","message":"LearnDash Sync REST API is working"}`

### Test Full Sync
1. Configure both master and client
2. Push a course from master
3. Verify course appears on client with correct hierarchy
4. Modify course on master and push again
5. Verify updates appear on client (not duplicated)

## Troubleshooting

### Push Fails
- Check REST API is enabled on client site
- Verify secret key matches on both sites
- Check PHP error logs for timeout issues
- Increase timeout if needed (line ~200)

### Duplicates Created
- Verify UUID meta field name matches: `ld_uuid` on master, `ld_uuid` check on client
- Check existing posts query (line ~623)
- Ensure UUIDs are being saved correctly

### Relationships Lost
- Check LearnDash meta field names match your version
- Verify `course_id`, `lesson_id`, `quiz_id` meta is being saved
- Check LearnDash documentation for relationship structure

## Hooks and Filters (Future Enhancement)

Consider adding these action hooks for extensibility:

```php
// After course export
do_action('ld_sync_after_export', $course_id, $export);

// Before course import
do_action('ld_sync_before_import', $course_data);

// After course import
do_action('ld_sync_after_import', $course_id, $course_data);

// Filter export data
$export = apply_filters('ld_sync_export_data', $export, $course_id);

// Filter import data
$data = apply_filters('ld_sync_import_data', $data, $post_type);
```

## Performance Considerations

### Large Course Exports
- Export function processes recursively (can be slow for large courses)
- Consider adding pagination for course selection
- Add progress indicator for push operations
- Use WordPress background processing for large syncs

### Database Queries
- Queries use meta_key/meta_value (can be slow)
- Consider adding indexes to meta tables
- Use `posts_per_page` limits when appropriate
- Cache results when querying same data multiple times

## Version History

### 2.0 (Current)
- Combined Master Push and Client Receive into single plugin
- Improved code organization and documentation
- Added select-all checkbox for courses
- Enhanced error handling and response display
- Better HTML preservation with `wp_kses_post()`

### 1.5.1 (Client Receive)
- Improved HTML sanitization
- Fixed question syncing

### 1.5 (Master Push)
- Added UUID column
- Improved client management

## Support

For issues or questions:
1. Check WordPress and LearnDash error logs
2. Enable WordPress debug mode: `define('WP_DEBUG', true);`
3. Review this guide for customization examples
4. Check GitHub repository for updates
