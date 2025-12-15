# Push to Clients Implementation

## Overview

This document describes the implementation of the "Push to Clients" functionality that allows the master site to push LearnDash content to connected client sites.

## Key Features Implemented

### 1. UUID-Based Content Mapping

**Problem Solved:** Post IDs differ between master and client sites, making it impossible to accurately map and update content without creating duplicates.

**Solution:** Automatic UUID generation for all LearnDash content.

- **Automatic UUID Generation:** When any LearnDash content is saved on the master site, a UUID is automatically generated if it doesn't exist.
- **Bulk UUID Generation:** A "Generate UUIDs for All Content" button in the master site settings allows you to generate UUIDs for all existing content at once.
- **UUID Display:** UUIDs are displayed in the admin list tables for all LearnDash content types.

#### How to Generate UUIDs

1. Go to **LearnDash Sync > Settings** on your master site
2. Click the **"Generate UUIDs for All Content"** button
3. Confirm the action
4. The system will generate UUIDs for all Courses, Lessons, Topics, Quizzes, and Questions that don't already have one
5. You'll see a summary showing how many items were updated

### 2. Push Functionality

**Problem Solved:** The push buttons didn't actually push content to client sites.

**Solution:** Complete push implementation with REST API endpoints.

#### Push from Courses Page

- Navigate to **LearnDash Sync > Courses** on the master site
- Click **"Push to Clients"** next to any course
- The course and ALL related content (lessons, topics, quizzes, questions) will be pushed to all connected client sites

#### Push from Content List Pages

- On any LearnDash content list page (Courses, Lessons, Topics, Quizzes, Questions), there's now a **"Push"** button in the list table
- Click the button to push that specific content item to all client sites

### 3. Complete Content Hierarchy

When you push a course, the system automatically includes:
- The course itself
- All lessons in the course
- All topics in those lessons
- All quizzes associated with the course
- All questions in those quizzes

This ensures the entire course structure is synchronized.

### 4. User Data Protection

**CRITICAL:** This implementation ensures user data is NEVER affected.

#### What Gets Synced
- Course, Lesson, Topic, Quiz, Question titles and content
- Course structure (prerequisites, drip settings)
- Course/lesson/topic settings
- Categories and tags

#### What NEVER Gets Synced (Protected User Data)
- User enrollments
- User progress/completion
- Quiz attempts and scores
- Course/lesson completion records
- User activity logs
- Any user-specific metadata

#### Safety Mechanisms
1. **Metadata Filtering:** All user-related metadata is filtered out before syncing
2. **Pattern Matching:** Multiple exclusion patterns prevent accidental user data sync
3. **Double-Check:** Both the master and sync classes verify metadata is safe
4. **Clear Documentation:** Prominent notices in the admin interface

## Technical Architecture

### REST API Endpoints

#### Master Site Endpoints (existing)
- `GET /wp-json/ldmcs/v1/content/{type}` - List content
- `GET /wp-json/ldmcs/v1/content/{type}/{id}` - Get single content item
- `GET /wp-json/ldmcs/v1/verify` - Verify connection

#### Client Site Endpoints (new)
- `POST /wp-json/ldmcs/v1/receive` - Receive pushed content from master

### AJAX Handlers

#### Master Site
- `ldmcs_push_course` - Push a specific course
- `ldmcs_push_content` - Push any content type (generic handler)
- `ldmcs_generate_uuids` - Generate UUIDs for all content

### Content Mapping

1. **On Master Site:**
   - UUID is generated and stored in `ld_uuid` post meta
   - UUID is used as the content identifier in API responses

2. **On Client Site:**
   - Master UUID is stored in `_ldmcs_master_id` post meta
   - When receiving pushed content, the client looks up existing content by UUID
   - If found, content is updated; if not found, new content is created

3. **Why This Works:**
   - UUIDs are unique across all sites
   - Same UUID always maps to the same content
   - Updates overwrite the correct content
   - No duplicates are created

## Usage Instructions

### Initial Setup (One-Time)

1. **On Master Site:**
   - Go to **LearnDash Sync > Settings**
   - Set **Site Mode** to "Master Site"
   - Copy the **API Key**
   - Click **"Generate UUIDs for All Content"**
   - Wait for the process to complete

2. **On Each Client Site:**
   - Go to **LearnDash Sync > Settings**
   - Set **Site Mode** to "Client Site"
   - Enter the **Master Site URL**
   - Enter the **Master Site API Key**
   - Click **"Verify Connection"**
   - Save settings

### Regular Use

#### Push All Course Content
1. Go to **LearnDash Sync > Courses** on master site
2. Click **"Push to Clients"** next to any course
3. Wait for confirmation
4. All connected client sites will receive the course and all its content

#### Push Individual Content
1. Go to any LearnDash content list (Courses, Lessons, Topics, Quizzes, Questions)
2. Click the **"Push"** button in the list table
3. Content will be pushed to all connected client sites

### Monitoring

- Check **LearnDash Sync > Sync Logs** to see the history of push operations
- Logs show:
  - What was pushed
  - When it was pushed
  - Which client sites received it
  - Success/failure status

## Content Update Workflow

### Scenario: Updating an Existing Course

1. Edit the course on the master site
2. Make your changes (content, settings, add lessons, etc.)
3. Save the course
4. Go to **LearnDash Sync > Courses**
5. Click **"Push to Clients"**
6. The updated course is pushed to all client sites
7. On client sites:
   - Existing course is found by UUID
   - Content and structure are updated
   - **User enrollments and progress are preserved**
   - Users continue learning without interruption

## Safety Guarantees

### User Progress Protection

The system has multiple layers of protection:

1. **Separate Data Storage:** LearnDash stores user progress in completely separate database tables and user meta, which are never touched by the sync
2. **Metadata Filtering:** All metadata is filtered through exclusion lists
3. **Safe Key Validation:** Every meta key is validated before being synced
4. **Documentation:** Clear warnings in code and UI

### Testing User Safety

To verify user data is safe:

1. On a client site, enroll a user in a course
2. Have them complete some lessons
3. Push an update to that course from master
4. Verify on client site:
   - User is still enrolled ✓
   - Progress is still there ✓
   - Completed lessons are still marked complete ✓
   - Course content is updated ✓

## Troubleshooting

### Client Sites Not Connected

**Problem:** "No client sites connected" error when pushing

**Solution:** Client sites must verify their connection to the master site first:
1. On each client site, go to **LearnDash Sync > Settings**
2. Click **"Verify Connection"**
3. Save settings

### UUIDs Not Showing

**Problem:** UUID column shows post IDs instead of UUIDs

**Solution:** Generate UUIDs:
1. Go to **LearnDash Sync > Settings** on master site
2. Click **"Generate UUIDs for All Content"**

### Push Failed

**Problem:** Push operation fails for some client sites

**Solution:** 
1. Check **LearnDash Sync > Sync Logs** for error details
2. Verify client site is accessible (not behind firewall)
3. Verify API key is correct on client site
4. Check client site's error logs

### Duplicates Created

**Problem:** Pushing content creates duplicates instead of updating

**Solution:** This happens when UUIDs don't match:
1. Ensure UUIDs were generated on master BEFORE first push
2. If duplicates exist, delete them manually
3. Re-push after ensuring UUIDs are in place

## Performance Considerations

### Pushing Large Courses

When pushing a course with many lessons/topics/quizzes:
- The entire hierarchy is pushed in a single request
- Large courses may take 30-60 seconds to push
- The push happens in the background
- Users are not affected during the push

### Multiple Client Sites

- Each client site is pushed to sequentially
- If you have 10 client sites, the push will take longer
- Failed pushes to one site don't affect others
- All results are logged for review

## Security

### Authentication

- All push operations require admin capabilities
- API key authentication for all REST API requests
- Nonce verification for all AJAX requests

### Data Validation

- All input is sanitized
- All output is escaped
- Content is validated before syncing

## Future Enhancements

Possible future improvements:

1. **Selective Push:** Choose which lessons/topics to push
2. **Scheduled Push:** Automatically push at specific times
3. **Push History:** Detailed history of what was pushed when
4. **Rollback:** Ability to undo a push operation
5. **Diff View:** See what changed before pushing
6. **Client Selection:** Choose which clients to push to

## Summary

This implementation provides:

✅ **Accurate Content Mapping:** UUID-based system prevents duplicates  
✅ **Easy Bulk Operations:** Generate UUIDs for all content at once  
✅ **Complete Push Functionality:** Push courses with all related content  
✅ **User Data Protection:** Multiple safeguards ensure user progress is never affected  
✅ **Push on All Content Types:** Courses, Lessons, Topics, Quizzes, Questions  
✅ **Clear User Interface:** Push buttons in list tables and dedicated courses page  
✅ **Comprehensive Logging:** Track all push operations  

The system is safe, efficient, and ready for production use.
