# Troubleshooting "Found 0 related content items"

## Issue Description

When pushing a course from the master site to client sites, the sync log shows:
```
Found 0 related content items for course "Course Name"
```

This results in the course being synced without its lessons, topics, quizzes, and questions, effectively stripping the course of all content on the client site.

## Common Causes

### 1. Course Content Not Published

**Problem**: LearnDash's `learndash_course_get_steps()` function only returns published content. If your lessons, topics, or quizzes are in "Draft", "Pending", or any other non-published status, they won't be included in the sync.

**Solution**: 
1. Go to the master site admin
2. Check the status of all course content:
   - Navigate to **Lessons > All Lessons** - ensure all lessons are Published
   - Navigate to **Topics > All Topics** - ensure all topics are Published
   - Navigate to **Quizzes > All Quizzes** - ensure all quizzes are Published
   - Navigate to **Questions > All Questions** - ensure all questions are Published
3. Publish any content that is in Draft or other status
4. Try pushing the course again

**Note**: With the latest fix, the plugin now includes a fallback that reads course structure directly from metadata, which should work even with non-published content. However, it's still best practice to keep all course content published.

### 2. Course Structure Not Properly Set Up

**Problem**: The course may not have its lessons, topics, and quizzes properly associated in LearnDash's course builder.

**Solution**:
1. Edit the course on the master site
2. Go to the **Builder** tab
3. Verify that all lessons, topics, and quizzes appear in the course structure
4. If content is missing from the builder:
   - Add the content to the course using the Builder interface
   - Save the course
5. Re-save the course even if nothing appears to be wrong (this refreshes metadata)
6. Try pushing the course again

### 3. Missing or Corrupted Course Metadata

**Problem**: The course's `ld_course_steps` metadata may be missing or corrupted.

**Solution**:
1. Edit the course on the master site
2. Go to the **Builder** tab
3. Make a small change (e.g., reorder a lesson)
4. Save the course
5. This will regenerate the course metadata
6. Try pushing the course again

Alternatively, you can check the metadata directly:
```php
// Add this to your theme's functions.php temporarily
add_action('admin_init', function() {
    if (isset($_GET['check_course_meta']) && isset($_GET['course_id'])) {
        $course_id = intval($_GET['course_id']);
        $steps = get_post_meta($course_id, 'ld_course_steps', true);
        echo '<pre>';
        print_r($steps);
        echo '</pre>';
        exit;
    }
});
```

Then visit: `yoursite.com/wp-admin/?check_course_meta=1&course_id=123` (replace 123 with your course ID)

### 4. LearnDash Version Compatibility

**Problem**: Older versions of LearnDash may use different metadata structures.

**Solution**:
1. Update LearnDash to the latest version (3.0+)
2. After updating, re-save all courses to regenerate metadata
3. Try pushing the course again

The plugin includes legacy fallbacks for older LearnDash versions, but the modern API is more reliable.

### 5. Empty Course

**Problem**: The course genuinely has no lessons, topics, or quizzes associated with it.

**Solution**:
1. Verify the course has content on the master site
2. Add lessons, topics, quizzes, and questions to the course
3. Try pushing the course again

## Diagnostic Steps

### Step 1: Check Sync Logs with Debug Information

With the latest fix, detailed debug logs are now available. To view them:

1. On the master site, go to **LearnDash Sync > Sync Logs**
2. Find the most recent push operation for your course
3. Look for debug messages that show:
   - "Starting get_related_course_content for course ID X"
   - "learndash_course_get_steps returned X steps"
   - "Direct metadata read returned X steps" (if fallback was used)
   - "learndash_course_get_children (quizzes) returned X quizzes"
   - "Total items collected: X"

These messages will help identify which part of the retrieval is failing.

### Step 2: Enable WordPress Debug Logging

Add these lines to your `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );
```

Then check `wp-content/debug.log` for any PHP errors or warnings during the push operation.

### Step 3: Verify Course ID

Ensure you're pushing the correct course:

1. Go to **Courses > All Courses**
2. Hover over the course name - the URL will show the course ID
3. Verify this matches the ID in the sync logs

### Step 4: Check for Plugin Conflicts

Temporarily deactivate other plugins (except LearnDash and this sync plugin) to rule out conflicts:

1. Go to **Plugins > Installed Plugins**
2. Deactivate all plugins except LearnDash and LearnDash Master to Client Sync
3. Try pushing the course again
4. Reactivate plugins one by one to identify the culprit

## Understanding the Fix

The latest version of the plugin includes these improvements:

1. **Metadata Fallback for Lessons/Topics**: If `learndash_course_get_steps()` returns empty (usually because content isn't published), the plugin now reads directly from the `ld_course_steps` metadata, which contains all associated content regardless of status.

2. **Metadata Fallback for Quizzes**: Similarly, if `learndash_course_get_children()` returns empty, the plugin reads quiz information from metadata.

3. **Comprehensive Logging**: Debug logs now show exactly what's happening at each step of the content retrieval process.

## Still Having Issues?

If you've tried all the above and still see "Found 0 related content items":

1. **Verify LearnDash is Active**: Ensure LearnDash is installed and activated on the master site
2. **Check PHP Version**: Ensure you're running PHP 7.2 or higher
3. **Review Sync Logs**: Share the complete sync log entries with debug information
4. **Check Error Logs**: Check `wp-content/debug.log` for PHP errors
5. **Test with Simple Course**: Create a test course with just one lesson and try pushing it

## Prevention

To prevent this issue in the future:

1. **Keep Content Published**: Always publish lessons, topics, quizzes, and questions before syncing
2. **Use Course Builder**: Always manage course structure through LearnDash's Builder interface
3. **Re-save After Changes**: After making changes to course structure, re-save the course before pushing
4. **Keep LearnDash Updated**: Use the latest version of LearnDash
5. **Generate UUIDs**: Always run "Generate UUIDs for All Content" before first sync

## Technical Details

The plugin retrieves course content in this order:

1. Calls `learndash_course_get_steps($course_id)` to get lessons and topics
   - If empty, falls back to reading `ld_course_steps` metadata directly
2. For each lesson/topic, retrieves associated quizzes
3. Calls `learndash_course_get_children($course_id, 'sfwd-quiz')` for course-level quizzes
   - If empty, falls back to reading from metadata
4. For each quiz, retrieves associated questions

The metadata fallback ensures that course structure is preserved even when LearnDash's API functions don't return results due to post status filtering.
