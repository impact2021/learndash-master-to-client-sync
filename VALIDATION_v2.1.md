# Validation Guide for Version 2.1 Logging Changes

## Overview
Version 2.1 fixes logging issues where client sites only showed "client_pull" entries and master logs showed WordPress post IDs instead of UUIDs.

## What Changed
1. Client sites now log "master_push" when receiving pushed content
2. Master logs now use UUIDs instead of post IDs
3. Master logs now include sync statistics (Synced, Skipped, Errors)

## Validation Steps

### Step 1: Verify Plugin Version
**Location**: Master site and client site admin

1. Go to **Plugins** page
2. Find "LearnDash Master to Client Sync"
3. **Expected**: Version should show **2.1.0**

### Step 2: Test Master Push Operation with UUID Logging
**Location**: Master site

#### Prerequisites
- Ensure UUIDs are generated for content (LearnDash Sync > Settings > Generate UUIDs button)
- At least one client site connected

#### Steps
1. Go to **LearnDash Sync > Courses**
2. Find a course with a UUID
3. Note the UUID displayed (e.g., `550e8400-e29b-41d4-a716-446655440000`)
4. Click the **"Push to Clients"** button
5. Wait for confirmation
6. Go to **LearnDash Sync > Sync Logs**

#### Expected Results in Master Logs
Look for log entry like:
```
Date/Time: [timestamp]
Sync Type: master_push
Content Type: courses
Content ID: 550e8400-e29b-41d4-a716-446655440000  ← UUID (not post ID like 71963852)
Status: Success
Message: Successfully pushed to https://client-site.com | Synced: 15 | Skipped: 1 | Errors: 0
```

**Key Validations**:
- ✅ Content ID is a UUID format (with hyphens)
- ✅ Content ID matches the UUID shown in the course list
- ✅ Message includes sync statistics: "Synced: X | Skipped: Y | Errors: Z"
- ✅ Sync Type is "master_push"

### Step 3: Test Client Push Reception Logging
**Location**: Client site

#### Steps
1. After pushing from master (Step 2), go to client site
2. Navigate to **LearnDash Sync > Sync Logs**

#### Expected Results in Client Logs
Look for log entry like:
```
Date/Time: [timestamp]
Sync Type: master_push  ← Changed from "client_pull"
Content Type: courses
Content ID: 550e8400-e29b-41d4-a716-446655440000  ← Same UUID as master
Status: Success
Message: Content created (or "Content updated" or "Content already exists")
```

**Key Validations**:
- ✅ Sync Type is "master_push" (not "client_pull")
- ✅ Content ID matches the UUID from master logs
- ✅ Status shows success/skipped/error appropriately
- ✅ Timestamp is recent (matches when push was performed)

### Step 4: Test Client Pull Operation (Verify Backward Compatibility)
**Location**: Client site

#### Steps
1. Go to **LearnDash Sync > Settings**
2. Click **"Sync Now"** button to pull from master
3. Wait for completion
4. Go to **LearnDash Sync > Sync Logs**

#### Expected Results in Client Logs
Look for log entries like:
```
Date/Time: [timestamp]
Sync Type: client_pull  ← Should still be client_pull
Content Type: courses
Content ID: 550e8400-e29b-41d4-a716-446655440000  ← UUID
Status: Success/Skipped
Message: Content created / Content already exists
```

**Key Validations**:
- ✅ Sync Type is "client_pull" (not "master_push")
- ✅ Content ID is UUID format
- ✅ Pull operations still work correctly

### Step 5: Cross-Site UUID Correlation
**Location**: Both master and client sites

#### Steps
1. Pick a specific piece of content (e.g., a course)
2. Note its UUID from master site course list
3. Check master logs for that UUID
4. Check client logs for that same UUID
5. Verify you can see the full sync history

#### Expected Results
- ✅ Same UUID appears in both master and client logs
- ✅ Master shows "master_push" entries with sync stats
- ✅ Client shows "master_push" when content was pushed
- ✅ Client shows "client_pull" when content was pulled
- ✅ Easy to correlate content across sites using UUID

## Troubleshooting

### Issue: Client logs still show "client_pull" for pushed content
**Cause**: Old version still cached or plugin not updated on client
**Solution**: 
1. Verify plugin version is 2.1.0 on client site
2. Clear any object cache
3. Retry pushing from master

### Issue: Master logs still show post IDs instead of UUIDs
**Cause**: UUIDs not generated for content
**Solution**:
1. Go to master site **LearnDash Sync > Settings**
2. Click **"Generate UUIDs for All Content"**
3. Wait for completion
4. Retry pushing content

### Issue: No sync statistics in master logs
**Cause**: Old log entries (before v2.1)
**Solution**: 
- Old entries won't have statistics
- Only new push operations (after v2.1 upgrade) will include stats
- Perform a new push to see statistics

## Success Criteria

All of the following should be true after validation:

- [ ] Plugin version shows 2.1.0 on both master and client sites
- [ ] Master logs use UUIDs as content IDs (not WordPress post IDs)
- [ ] Master logs include sync statistics in success messages
- [ ] Client logs show "master_push" when receiving pushed content
- [ ] Client logs show "client_pull" when actively pulling content
- [ ] Same UUID appears in both master and client logs for same content
- [ ] No PHP errors or warnings in WordPress debug.log
- [ ] All push operations complete successfully
- [ ] All pull operations complete successfully

## Regression Testing

To ensure no functionality was broken:

1. **Push complete course with lessons/topics/quizzes**
   - Should push all related content
   - Should log each item appropriately
   
2. **Pull content from client site**
   - Should work as before
   - Should log as "client_pull"
   
3. **Conflict resolution (skip mode)**
   - Push same content twice
   - Second push should skip existing content
   - Should log as "skipped" status
   
4. **Multiple client sites**
   - Push to multiple clients
   - Each should have its own log entry
   - Each should show sync statistics

## Notes

- The `$sync_type` parameter in `sync_single_item()` defaults to 'client_pull' for backward compatibility
- Existing code that doesn't pass the third parameter will continue to work
- No database schema changes were made
- Log table structure remains the same
- Changes are purely in how data is logged, not in sync functionality
