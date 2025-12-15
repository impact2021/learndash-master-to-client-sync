# Validation Checklist for Course Content Sync Fix

This checklist helps validate that the course content sync fix is working correctly.

## Pre-Validation Setup

### Master Site Requirements
- [ ] WordPress 5.0+ installed
- [ ] PHP 7.2+ installed
- [ ] LearnDash LMS plugin active
- [ ] LearnDash Master to Client Sync plugin active
- [ ] Plugin mode set to "Master Site"
- [ ] API key generated

### Client Site Requirements
- [ ] WordPress 5.0+ installed
- [ ] PHP 7.2+ installed
- [ ] LearnDash LMS plugin active
- [ ] LearnDash Master to Client Sync plugin active
- [ ] Plugin mode set to "Client Site"
- [ ] Master URL and API key configured
- [ ] Connection verified successfully

## Test Data Setup

### Create Test Course on Master Site
- [ ] Create course: "Test Course Alpha"
- [ ] Add lesson: "Lesson 1: Introduction"
- [ ] Add lesson: "Lesson 2: Advanced"
- [ ] Add topic to Lesson 1: "Topic 1.1: Getting Started"
- [ ] Add topic to Lesson 1: "Topic 1.2: Basic Concepts"
- [ ] Add topic to Lesson 2: "Topic 2.1: Deep Dive"
- [ ] Create quiz: "Course Final Quiz" (associated with course)
- [ ] Add 3 questions to "Course Final Quiz"
- [ ] Create quiz: "Lesson 1 Quiz" (associated with Lesson 1)
- [ ] Add 2 questions to "Lesson 1 Quiz"
- [ ] Ensure all content is **Published** status
- [ ] Use LearnDash Builder to verify all associations

### Generate UUIDs
- [ ] Go to LearnDash Sync > Settings on master site
- [ ] Click "Generate UUIDs for All Content" button
- [ ] Verify success message appears
- [ ] Check that UUID count matches your content count

## Validation Tests

### Test 1: Initial Course Push

**Steps:**
1. Go to Courses page on master site
2. Find "Test Course Alpha" in list
3. Click "Push to Clients" button
4. Wait for success message

**Expected Results:**
- [ ] Success message appears: "Course pushed to 1 client site(s)"
- [ ] No error messages displayed

**Verify on Master Site Logs:**
- [ ] Go to LearnDash Sync > Sync Logs
- [ ] Find recent "master_push" entry for "Test Course Alpha"
- [ ] Log shows: "Found X related content items" (where X > 0)
- [ ] Status is "success" or "info"

**Verify on Client Site:**
- [ ] Course "Test Course Alpha" exists
- [ ] Lesson "Lesson 1: Introduction" exists
- [ ] Lesson "Lesson 2: Advanced" exists
- [ ] Topic "Topic 1.1: Getting Started" exists
- [ ] Topic "Topic 1.2: Basic Concepts" exists
- [ ] Topic "Topic 2.1: Deep Dive" exists
- [ ] Quiz "Course Final Quiz" exists
- [ ] Quiz "Lesson 1 Quiz" exists
- [ ] All 5 questions exist (3 from course quiz + 2 from lesson quiz)

**Verify Content Details:**
- [ ] Course title matches
- [ ] Course description/content matches
- [ ] Lessons are in correct order
- [ ] Topics are associated with correct lessons
- [ ] Quizzes are associated correctly
- [ ] Questions are in correct quizzes

### Test 2: Course Update

**Steps:**
1. On master site, edit "Test Course Alpha"
2. Change title to "Test Course Alpha - Updated"
3. Edit course description
4. Save changes
5. Push course again

**Expected Results:**
- [ ] Success message appears
- [ ] No duplicate courses created on client
- [ ] Client site shows updated title
- [ ] Client site shows updated description
- [ ] All lessons, topics, quizzes still intact

### Test 3: Add New Content to Existing Course

**Steps:**
1. On master site, add new lesson: "Lesson 3: Expert Level"
2. Add topic to Lesson 3: "Topic 3.1: Mastery"
3. Add quiz to Lesson 3 with 2 questions
4. Save course structure
5. Push course again

**Expected Results:**
- [ ] New lesson appears on client
- [ ] New topic appears on client
- [ ] New quiz appears on client
- [ ] New questions appear on client
- [ ] Existing content unchanged
- [ ] No duplicates created

### Test 4: User Data Safety (CRITICAL)

**Steps:**
1. On client site, create test user: "testuser"
2. Enroll testuser in "Test Course Alpha - Updated"
3. Log in as testuser
4. Complete "Lesson 1: Introduction"
5. Complete "Topic 1.1: Getting Started"
6. Take "Lesson 1 Quiz" and submit answers
7. Log out
8. On master site, push the course again

**Expected Results:**
- [ ] testuser is still enrolled in the course
- [ ] Lesson 1 still shows as completed
- [ ] Topic 1.1 still shows as completed
- [ ] Quiz attempt is preserved
- [ ] Quiz score is preserved
- [ ] Progress percentage unchanged
- [ ] NO progress reset

### Test 5: Multiple Content Types Push

**Steps:**
1. On master site, create standalone lesson (not in course)
2. Push the lesson individually using push button
3. Create standalone quiz
4. Push the quiz individually

**Expected Results:**
- [ ] Lesson syncs to client correctly
- [ ] Quiz syncs to client correctly
- [ ] Both have valid UUIDs

### Test 6: Large Course Test

**Steps:**
1. Create course "Large Course" with:
   - 10 lessons
   - 3 topics per lesson (30 topics total)
   - 5 quizzes (1 per 2 lessons)
   - 5 questions per quiz (25 questions total)
2. Generate UUIDs
3. Push the course

**Expected Results:**
- [ ] Push completes successfully (may take 30-60 seconds)
- [ ] All 10 lessons synced
- [ ] All 30 topics synced
- [ ] All 5 quizzes synced
- [ ] All 25 questions synced
- [ ] Sync log shows "Found 60 related content items"
- [ ] No timeout errors
- [ ] No memory errors

### Test 7: Error Handling

**Steps:**
1. Create course with lesson
2. Don't generate UUIDs
3. Push the course
4. Check logs

**Expected Results:**
- [ ] Push completes (UUIDs generated automatically on save)
- [ ] Content syncs successfully
- [ ] Or: Clear error message if UUID is required

## Performance Checks

### Master Site Performance
- [ ] Push button responds within 2 seconds
- [ ] No PHP errors in error log
- [ ] No browser console errors
- [ ] Page doesn't freeze during push

### Client Site Performance
- [ ] Content creation completes in reasonable time
- [ ] No PHP timeout errors
- [ ] No memory exhaustion errors
- [ ] Site remains responsive during sync

## Logging Verification

### Master Site Logs
- [ ] "master_push" entries exist
- [ ] "Found X related content items" messages present
- [ ] Success status for each client
- [ ] Detailed error messages if failures

### Client Site Logs
- [ ] "client_pull" or sync entries exist
- [ ] Each content type logged separately
- [ ] Success/skipped/error status recorded
- [ ] Clear messages for any issues

## Edge Cases

### Empty Course
- [ ] Push course with no lessons/topics/quizzes
- [ ] Should succeed with 0 related items

### Draft Content
- [ ] Create course with draft lesson
- [ ] Push course
- [ ] Draft lesson should NOT sync

### Unpublished Content
- [ ] Create course with unpublished quiz
- [ ] Push course
- [ ] Unpublished quiz should NOT sync

### Trashed Content
- [ ] Trash a lesson in a course
- [ ] Push course
- [ ] Trashed lesson should NOT sync

## Rollback Test

**Steps:**
1. Note current content on client
2. Push course with updates
3. Verify changes applied
4. No automatic rollback needed (feature not implemented)

**Manual Rollback:**
- [ ] Can restore from WordPress revision history
- [ ] Can re-push from master to overwrite

## Multi-Client Test

If you have multiple client sites:

**Steps:**
1. Connect 2-3 client sites to master
2. Verify all connections
3. Push a course from master

**Expected Results:**
- [ ] Course appears on all client sites
- [ ] All content synced to all clients
- [ ] Each client logs the sync
- [ ] Master logs show success for each client

## Documentation Verification

- [ ] README.md updated with troubleshooting steps
- [ ] COURSE_CONTENT_SYNC_FIX.md provides detailed explanation
- [ ] Code comments are clear and accurate
- [ ] No misleading or outdated documentation

## Final Validation

- [ ] All critical tests passed
- [ ] No data loss observed
- [ ] User data safety confirmed
- [ ] Performance is acceptable
- [ ] Error handling works correctly
- [ ] Logs provide useful information
- [ ] Documentation is complete

## Sign-Off

**Tested By:** _________________  
**Date:** _________________  
**LearnDash Version:** _________________  
**WordPress Version:** _________________  
**PHP Version:** _________________  

**Result:** ☐ PASS  ☐ FAIL  

**Notes:**
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________

## If Tests Fail

1. Check COURSE_CONTENT_SYNC_FIX.md troubleshooting section
2. Enable WordPress debug logging
3. Review PHP error logs
4. Check sync logs for detailed error messages
5. Verify LearnDash version compatibility
6. Ensure course structure is properly configured in LearnDash Builder
7. Test with a simple course first (1 lesson, 1 topic, 1 quiz)
