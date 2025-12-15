# Push Button Modal Fix

## Issue
When clicking the "Push" button on LearnDash content list pages (Lessons, Topics, Quizzes, Questions), the modal dialog was not appearing to show the progress of the push operation. Users reported "nothing seems to happen" when clicking the Push button.

## Root Cause
The issue had two parts:

1. **JavaScript and CSS not loaded**: The admin scripts and styles were only being enqueued on pages with 'ldmcs' in the hook name. LearnDash post type pages have different hook names (like `edit.php?post_type=sfwd-lessons`), so the JavaScript that shows the modal was never loaded.

2. **Modal HTML missing**: The modal HTML structure was only rendered on the "LearnDash Sync > Courses" page. When users clicked Push buttons on other LearnDash content pages, the JavaScript tried to show a modal that didn't exist in the DOM.

## Solution
Made the following changes to `includes/class-ldmcs-admin.php`:

### 1. Added LearnDash Post Types Constant
```php
const LEARNDASH_POST_TYPES = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-question' );
```
This eliminates duplication and ensures consistency across all methods.

### 2. Enhanced Script Enqueuing
Modified `enqueue_admin_scripts()` to detect and load scripts on LearnDash post type pages:
```php
public function enqueue_admin_scripts( $hook ) {
    // Load on LDMCS pages and LearnDash post type pages.
    $is_ldmcs_page     = strpos( $hook, 'ldmcs' ) !== false;
    $is_learndash_page = false;

    // Check if we're on a LearnDash post type page.
    $screen = get_current_screen();
    if ( $screen && in_array( $screen->post_type, self::LEARNDASH_POST_TYPES, true ) ) {
        $is_learndash_page = true;
    }

    if ( ! $is_ldmcs_page && ! $is_learndash_page ) {
        return;
    }
    
    // ... enqueue scripts and styles
}
```

### 3. Created Global Modal Rendering Method
Added `render_push_modal()` method that renders the modal HTML in the admin footer:
```php
public function render_push_modal() {
    $mode = get_option( 'ldmcs_mode', 'client' );

    // Only render on master site.
    if ( 'master' !== $mode ) {
        return;
    }

    // Check if we're on a relevant page.
    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }

    $is_ldmcs_page     = strpos( $screen->id, 'ldmcs' ) !== false;
    $is_learndash_page = in_array( $screen->post_type, self::LEARNDASH_POST_TYPES, true );

    if ( ! $is_ldmcs_page && ! $is_learndash_page ) {
        return;
    }

    // Render the modal HTML
    ?>
    <!-- Push Progress Modal -->
    <div id="ldmcs-push-modal" class="ldmcs-modal">
        <!-- ... modal structure -->
    </div>
    <?php
}
```

### 4. Registered Admin Footer Hook
Added the admin footer hook in the constructor:
```php
add_action( 'admin_footer', array( $this, 'render_push_modal' ) );
```

### 5. Removed Duplicate Modal HTML
Removed the modal HTML from `render_courses_page()` since it's now rendered globally via the admin_footer hook.

## Testing the Fix

### Prerequisites
- Master site with this plugin installed and activated
- Site mode set to "Master Site"
- At least one piece of LearnDash content (course, lesson, topic, quiz, or question)
- Optionally: one or more connected client sites

### Test Scenario 1: Push from Courses Page
1. Go to **LearnDash Sync > Courses**
2. Click **"Push to Clients"** next to any course
3. **Expected Result**: Modal appears showing "Pushing Content to Client Sites"
   - If no client sites connected: Modal shows error message
   - If client sites connected: Modal shows push progress and results

### Test Scenario 2: Push from Lessons Page
1. Go to **Lessons > All Lessons**
2. Look for the "Push to Clients" column
3. Click **"Push"** button next to any lesson
4. **Expected Result**: Same modal behavior as above

### Test Scenario 3: Push from Other Content Types
Repeat the above test for:
- Topics > All Topics
- Quizzes > All Quizzes  
- Questions > All Questions

In all cases, the modal should appear and show the push operation progress.

### Verifying JavaScript is Loaded
1. Open browser developer tools (F12)
2. Go to any LearnDash content list page
3. Check the Network tab
4. Look for `admin.js` and `admin.css` files being loaded
5. **Expected Result**: Both files should be present

### Verifying Modal HTML Exists
1. Go to any LearnDash content list page
2. Open browser developer tools (F12)
3. Inspect the page source
4. Search for `id="ldmcs-push-modal"`
5. **Expected Result**: The modal HTML structure should be present in the DOM

## What Users Should See Now

### When Pushing with No Client Sites
- Click Push button
- Confirmation dialog appears
- Modal opens showing "Pushing Content to Client Sites"
- Modal immediately shows error: "No client sites connected. Client sites must verify their connection first."

### When Pushing with Connected Client Sites
- Click Push button
- Confirmation dialog appears
- Modal opens showing "Pushing Content to Client Sites"
- Modal shows "Pushing [content title] to client sites..."
- After completion, modal updates with results:
  - ✓ Success for each client site
  - ✗ Error for any failed sites
  - Detailed information about what was synced

### If Modal Still Doesn't Appear
Check these things:
1. **Site Mode**: Ensure the site is set to "Master Site" mode
2. **Browser Console**: Check for JavaScript errors in the browser console
3. **Script Conflicts**: Disable other plugins to check for JavaScript conflicts
4. **Browser Cache**: Clear browser cache and hard refresh (Ctrl+Shift+R / Cmd+Shift+R)

## Security Considerations
All changes maintain existing security practices:
- Modal only renders on master site (`'master' === get_option( 'ldmcs_mode' )`)
- All HTML output is properly escaped using `esc_html_e()`, `esc_attr()`, etc.
- Scripts only load on authenticated admin pages
- No new user input handling introduced
- No changes to authentication, authorization, or API key validation

## Files Modified
- `includes/class-ldmcs-admin.php` (1 file, ~50 lines changed)

## Backward Compatibility
This fix is fully backward compatible:
- No database changes
- No API changes
- No changes to existing functionality
- Only enhances where scripts load and where modal renders
