# Testing Guide - Push to Clients Functionality

This guide will help you test the push functionality to ensure everything works correctly.

## Prerequisites

- Master site with LearnDash activated
- At least one client site with LearnDash activated
- Both sites have the LearnDash Master to Client Sync plugin installed and activated

## Test 1: UUID Generation

### Objective
Verify that UUIDs are automatically generated for all LearnDash content.

### Steps
1. Go to master site admin
2. Navigate to **LearnDash Sync > Settings**
3. Ensure **Site Mode** is set to "Master Site"
4. Click **"Generate UUIDs for All Content"** button
5. Confirm the action

### Expected Results
- Success message appears showing:
  - Number of items updated
  - Number of items that already had UUIDs
  - Breakdown by content type (courses, lessons, topics, quizzes, questions)
- Check logs at **LearnDash Sync > Sync Logs** to see the UUID generation entry

### Verification
1. Go to **Courses > All Courses**
2. Look for the "Master UUID" column
3. Each course should display a UUID (e.g., `a1b2c3d4-e5f6-7890-ab12-cd34ef567890`)
4. Repeat for Lessons, Topics, Quizzes, and Questions

## Test 2: Client Site Connection

### Objective
Verify that client sites can connect to the master site.

### Steps on Master Site
1. Go to **LearnDash Sync > Settings**
2. Copy the **API Key**

### Steps on Client Site
1. Go to **LearnDash Sync > Settings**
2. Set **Site Mode** to "Client Site"
3. Enter **Master Site URL** (e.g., `https://master-site.com`)
4. Paste the **Master Site API Key**
5. Click **"Verify Connection"**
6. Wait for response

### Expected Results
- Success message: "Connection verified successfully!"
- Shows master site URL and plugin version
- Click **Save Changes**

### Verification on Master Site
1. Go to **LearnDash Sync > Client Sites**
2. The client site should be listed with:
   - Site URL
   - Site name
   - Connection timestamps
   - Status: Active

## Test 3: Push Individual Content

### Objective
Test pushing a single lesson to client sites.

### Setup
1. On master site, create or edit a lesson
2. Note the lesson title and content

### Steps
1. Go to **Lessons > All Lessons**
2. Find the lesson in the list
3. Look for the "Push to Clients" column
4. Click the **"Push"** button next to the lesson
5. Confirm the action when prompted

### Expected Results
- Loading indicator appears
- Success message: "Content pushed to X client site(s) successfully"
- No errors reported

### Verification on Client Site
1. Go to **Lessons > All Lessons**
2. The lesson should appear in the list
3. Open the lesson and verify:
   - Title matches master
   - Content matches master
   - Settings are preserved

### Verification in Logs
1. On master site, go to **LearnDash Sync > Sync Logs**
2. Look for the push entry showing the lesson was pushed
3. On client site, check logs for received content entry

## Test 4: Push Complete Course

### Objective
Test pushing an entire course with all related content.

### Setup
On master site, create a test course with:
- Course title and description
- 2 lessons
- 1 topic per lesson
- 1 quiz with 2 questions

### Steps
1. Go to **LearnDash Sync > Courses**
2. Find your test course
3. Click **"Push to Clients"** button
4. Confirm the action

### Expected Results
- Loading indicator appears
- Success message showing push to client site(s)
- Message indicates course AND related content were pushed

### Verification on Client Site
Check that ALL of these were created:
1. **Course**: Go to **Courses > All Courses**
   - Course exists with correct title and content
   
2. **Lessons**: Go to **Lessons > All Lessons**
   - Both lessons exist
   - They're associated with the course
   
3. **Topics**: Go to **Topics > All Topics**
   - Topics exist
   - They're associated with the correct lessons
   
4. **Quiz**: Go to **Quizzes > All Quizzes**
   - Quiz exists
   - Associated with the course
   
5. **Questions**: Go to **Questions > All Questions**
   - Questions exist
   - Associated with the quiz

### Verify UUID Mapping
1. On client site, go to any of the synced content
2. Check the "UUID" column
3. Under "Master:", you should see the master UUID
4. This UUID should match the "Master UUID" on the master site

## Test 5: Update Existing Content

### Objective
Verify that pushing updated content overwrites existing content without creating duplicates.

### Setup
Use the course from Test 4 that's already on the client site.

### Steps
1. On master site, edit the course:
   - Change the title to "Updated Course Title"
   - Modify the description
   - Edit one of the lessons
2. Save changes
3. Go to **LearnDash Sync > Courses**
4. Click **"Push to Clients"** again
5. Confirm

### Expected Results
- Push succeeds

### Verification on Client Site
1. Go to the course that was previously synced
2. Verify:
   - Title is updated to "Updated Course Title"
   - Description reflects the new content
   - Lesson changes are present
   - **No duplicate courses were created**
   - **Only one course exists with that UUID**

## Test 6: User Data Safety

### Objective
Verify that user enrollments and progress are NOT affected by content pushes.

### Setup on Client Site
1. Create a test user
2. Enroll the user in the test course
3. Have the user:
   - Complete one lesson
   - Start a quiz
4. Note the user's progress

### Steps
1. On master site, edit the course:
   - Change course description
   - Add a new lesson
2. Push the course to clients
3. Wait for push to complete

### Expected Results on Client Site
After the push, verify the user's data:
1. User is STILL enrolled in the course ✅
2. Completed lesson is STILL marked as complete ✅
3. Quiz progress is STILL preserved ✅
4. Course description IS updated ✅
5. New lesson IS added ✅
6. User can continue where they left off ✅

### Critical Check
- User's enrollment status: **Should NOT change**
- User's progress data: **Should NOT be lost**
- User's quiz attempts: **Should remain intact**

## Test 7: Push Multiple Content Types

### Objective
Test push buttons on all content type list pages.

### Steps
1. Test pushing from each list page:
   - **Courses > All Courses** - Click "Push" button
   - **Lessons > All Lessons** - Click "Push" button
   - **Topics > All Topics** - Click "Push" button
   - **Quizzes > All Quizzes** - Click "Push" button
   - **Questions > All Questions** - Click "Push" button

### Expected Results
- Each push should succeed
- Success messages appear
- Content appears on client site
- No errors in logs

## Test 8: Error Handling

### Objective
Verify proper error handling when things go wrong.

### Test 8a: No Client Sites Connected
1. On a fresh master site with no connected clients
2. Try to push content
3. **Expected:** Error message: "No client sites connected"

### Test 8b: Invalid API Key
1. On client site, enter wrong API key
2. Try to verify connection
3. **Expected:** "Connection failed" error

### Test 8c: Client Site Unreachable
1. Temporarily disable a client site (e.g., put it in maintenance mode)
2. Push from master
3. **Expected:** Push fails for that client, succeeds for others
4. Error logged in sync logs

## Test 9: Performance with Large Courses

### Objective
Verify the system handles large courses efficiently.

### Setup
Create a course with:
- 20 lessons
- 3 topics per lesson (60 topics total)
- 5 quizzes
- 10 questions per quiz (50 questions total)

### Steps
1. Push the large course
2. Monitor the process

### Expected Results
- Push completes successfully (may take 30-60 seconds)
- All content arrives on client site
- No timeout errors
- Logs show all items were pushed

## Test 10: Multiple Client Sites

### Objective
Verify pushing to multiple client sites works correctly.

### Setup
Connect 2-3 client sites to the master.

### Steps
1. Push a course from master
2. Watch for completion

### Expected Results
- Push succeeds for all client sites
- Each client site receives the content
- Logs show separate entries for each client
- If one fails, others still succeed

### Verification
Check each client site to ensure they all have the pushed content.

## Common Issues and Solutions

### Issue: UUIDs show Post IDs instead
**Solution:** Run "Generate UUIDs for All Content" button

### Issue: Duplicate content created
**Solution:** 
- Ensure UUIDs were generated BEFORE first push
- Delete duplicates manually
- Re-push after verifying UUIDs exist

### Issue: Push button doesn't appear
**Solution:** 
- Ensure site mode is set to "Master"
- Refresh the page
- Check that JavaScript is loaded (look for errors in browser console)

### Issue: "No client sites connected" error
**Solution:** Each client must verify connection via Settings page

### Issue: Content not updating, only new items created
**Solution:** UUID mismatch - verify UUIDs match between master and client

## Success Criteria

Your implementation is working correctly if:

✅ UUID generation creates UUIDs for all content  
✅ Push buttons appear on all content list pages on master site  
✅ Push buttons DO NOT appear on client sites  
✅ Pushing creates content on client sites  
✅ Pushing updates existing content without creating duplicates  
✅ User enrollments and progress are preserved  
✅ Complete course hierarchies are pushed together  
✅ Logs accurately record all push operations  
✅ Error messages are clear and helpful  
✅ Multiple client sites can be pushed to simultaneously  

## Final Verification Checklist

Before going to production:

- [ ] Run UUID generation on master site
- [ ] Verify all client sites are connected
- [ ] Test push on each content type
- [ ] Verify user data is safe (Test 6)
- [ ] Test updating existing content
- [ ] Review sync logs for any errors
- [ ] Test with real course content
- [ ] Verify performance with large courses
- [ ] Document any site-specific settings needed

## Support

If you encounter issues:
1. Check **LearnDash Sync > Sync Logs** for detailed error messages
2. Review this testing guide for solutions
3. Check PUSH_IMPLEMENTATION.md for technical details
4. Review WordPress and PHP error logs
