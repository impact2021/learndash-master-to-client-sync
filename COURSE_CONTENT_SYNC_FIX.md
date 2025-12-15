# Course Content Sync Fix Documentation

## Issue
When syncing a course from master to client sites, none of the course content (Lessons, Topics, Quizzes, Questions) was being transferred. Only the course itself was synced.

## Root Cause
The `get_related_course_content()` method in `class-ldmcs-master.php` was using LearnDash API functions that either:
1. Returned data in unexpected formats
2. Were not available in all LearnDash versions
3. Did not properly retrieve all course relationships

## Solution
The method has been completely rewritten to:
1. Use the proper LearnDash 3.0+ API functions when available
2. Provide comprehensive fallback methods using post meta queries
3. Handle multiple data formats from different LearnDash versions
4. Add detailed logging for troubleshooting

## Changes Made

### 1. Primary Method: Modern LearnDash API (3.0+)
```php
// Uses learndash_course_get_steps() to get all steps (lessons and topics)
$course_steps = learndash_course_get_steps( $course_id );

// Uses learndash_course_get_children() to get quizzes
$course_quizzes = learndash_course_get_children( $course_id, 'sfwd-quiz' );
```

### 2. Fallback Method: Post Meta Queries
When modern API functions are not available, the plugin now:
- Queries `ld_course_steps` post meta (LearnDash 3.0+ meta structure)
- Queries `course_lessons`, `course_quiz` meta keys (older structure)
- Uses WP_Query to find topics by lesson parent relationship

### 3. Improved Question Retrieval
Questions are now retrieved using:
- `learndash_get_quiz_questions()` function (if available)
- `ld_quiz_questions` post meta
- `quiz_question_list` post meta (fallback)

### 4. New Helper Methods
Six new private methods handle specific retrieval tasks:
- `get_related_course_content_legacy()` - Fallback for older LearnDash versions
- `get_quiz_questions()` - Retrieve questions for a quiz
- `get_course_lessons_meta()` - Get lessons from post meta
- `get_lesson_topics_meta()` - Get topics for a lesson
- `get_course_quizzes_meta()` - Get course quizzes from meta
- `get_lesson_quizzes_meta()` - Get lesson quizzes from meta
- `get_quiz_questions_meta()` - Get quiz questions from meta

## How to Test

### Prerequisites
1. Master site with LearnDash installed
2. At least one client site connected
3. A course with lessons, topics, quizzes, and questions

### Test Steps

#### 1. Create Test Course Content
On the master site:
1. Create a new course: "Test Course"
2. Add 2-3 lessons to the course
3. Add 2-3 topics to one of the lessons
4. Add a quiz to the course
5. Add 3-5 questions to the quiz
6. Add a quiz to one of the lessons
7. Add questions to that quiz as well

#### 2. Generate UUIDs
1. Go to **LearnDash Sync > Settings**
2. Click **"Generate UUIDs for All Content"**
3. Verify success message shows all content received UUIDs

#### 3. Push the Course
1. Go to **Courses** in WordPress admin
2. Find "Test Course" in the list
3. Click the **"Push to Clients"** button
4. Wait for the success message

#### 4. Verify on Client Site
On the client site, check:
1. Navigate to **Courses** - "Test Course" should exist
2. Navigate to **Lessons** - All lessons should exist
3. Navigate to **Topics** - All topics should exist
4. Navigate to **Quizzes** - All quizzes should exist
5. Navigate to **Questions** - All questions should exist

#### 5. Check Sync Logs
On both master and client:
1. Go to **LearnDash Sync > Sync Logs**
2. Look for the recent push operation
3. Verify log shows:
   - "Found X related content items" message
   - Success status for the push
   - No error messages

#### 6. Test Update Scenario
On the master site:
1. Edit the course title or content
2. Edit one of the lessons
3. Push the course again
4. Verify on client that:
   - Changes are reflected
   - No duplicate content was created
   - Course structure remains intact

#### 7. Verify User Data Safety (CRITICAL)
On the client site:
1. Enroll a test user in the course
2. Have the user complete one lesson
3. Return to master and push the course again
4. Verify on client that:
   - User is still enrolled
   - Lesson completion is preserved
   - Progress is not reset

## Debugging

### Enable WordPress Debug Logging
Add to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### Check Logs
1. **Sync Logs**: Go to **LearnDash Sync > Sync Logs** in WordPress admin
2. **PHP Error Log**: Check `wp-content/debug.log`
3. **Browser Console**: Check for JavaScript errors

### Common Issues and Solutions

#### Issue: "Found 0 related content items"
**Cause**: Course has no associated lessons/topics/quizzes, or LearnDash relationships not properly set.
**Solution**: 
1. Edit the course in WordPress admin
2. Go to **Builder** tab
3. Verify lessons, topics, and quizzes are properly associated
4. Save the course
5. Try pushing again

#### Issue: Some content types missing
**Cause**: Content may be associated but not published, or meta data is incomplete.
**Solution**:
1. Check that all content is published (not draft)
2. Verify course structure in LearnDash Builder
3. Re-save the course structure
4. Try pushing again

#### Issue: Quizzes synced but questions missing
**Cause**: Questions may not be properly associated with the quiz.
**Solution**:
1. Edit the quiz in WordPress admin
2. Check the **Questions** section
3. Verify questions are associated
4. Save the quiz
5. Push the course again

## LearnDash Version Compatibility

This fix has been designed to work with:
- **LearnDash 3.0+**: Uses modern API (`learndash_course_get_steps()`, etc.)
- **LearnDash 2.x**: Falls back to meta-based queries
- **LearnDash 4.0+**: Compatible with latest API

The code automatically detects which API functions are available and uses the appropriate method.

## Performance Considerations

For large courses (100+ lessons/topics):
1. The sync may take 30-60 seconds
2. Monitor the PHP memory limit (recommend 256MB+)
3. Monitor PHP execution time (recommend 60+ seconds)
4. Consider pushing smaller batches of content

## Security

This fix maintains all existing security measures:
- API key authentication required
- Only course structure is synced (no user data)
- Input validation on all data
- XSS protection in place

## Future Improvements

Possible enhancements:
1. Parallel processing for large courses
2. Progress indicator for long-running pushes
3. Option to selectively push specific lessons/topics
4. Caching of course structure to improve performance
5. Better error messages for specific failure scenarios

## Support

If you encounter issues:
1. Check sync logs for detailed error messages
2. Verify LearnDash is properly installed and activated
3. Ensure course structure is properly set up in LearnDash Builder
4. Check PHP error logs for any fatal errors
5. Test with a simple course (1 lesson, 1 topic, 1 quiz) first

## Summary

This fix ensures that when you push a course from the master site, all associated content (lessons, topics, quizzes, and questions) is properly transferred to client sites. The solution is robust, with multiple fallback methods to handle different LearnDash versions and configurations.
