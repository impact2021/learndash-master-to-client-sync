# Logging Fix Summary - Version 2.1.0

## Issues Addressed

### 1. Client Site Logs Only Show "client_pull"
**Problem**: Client sites only had "client_pull" entries in logs, even when content was pushed from master.

**Solution**: 
- Added a `$sync_type` parameter to `LDMCS_Sync::sync_single_item()` method (defaults to 'client_pull')
- When content is received via the `/receive` endpoint (push from master), the API now passes 'master_push' as the sync type
- Client sites will now properly log:
  - `client_pull` - When actively pulling content from master
  - `master_push` - When receiving pushed content from master

**Files Modified**:
- `includes/class-ldmcs-sync.php` - Added $sync_type parameter to sync_single_item()
- `includes/class-ldmcs-api.php` - Pass 'master_push' in receive_pushed_content()

### 2. Master Logs Show WordPress Post IDs Instead of UUIDs
**Problem**: Master logs showed WordPress internal post IDs (e.g., 71963852) which are not meaningful for tracking content across sites.

**Solution**:
- Modified `push_to_clients()` method to retrieve UUID from post meta before logging
- All master_push log entries now use UUID as the content_id instead of post_id
- Falls back to post_id if UUID is not available

**Files Modified**:
- `includes/class-ldmcs-master.php` - Get UUID and use as log_id in all logging calls

### 3. Master Doesn't Log Sync Statistics
**Problem**: The master site shows sync statistics (Synced: X | Skipped: Y | Errors: Z) in the UI, but this information wasn't being logged.

**Solution**:
- Enhanced the success logging in `push_to_clients()` to include sync statistics
- Log messages now include: "Successfully pushed to {URL} | Synced: X | Skipped: Y | Errors: Z"

**Files Modified**:
- `includes/class-ldmcs-master.php` - Added sync statistics to success log message

### 4. Version Update
**Update**: Plugin version updated from 2.0.0 to 2.1.0

**Files Modified**:
- `learndash-master-to-client-sync.php` - Updated version in header and constant

## Before and After Examples

### Master Site Logs

**Before:**
```
2025-12-15 05:54:49 | master_push | courses | 71963852 | Success | Successfully pushed to https://study.canadavisaservices.org
```

**After:**
```
2025-12-15 05:54:49 | master_push | courses | 550e8400-e29b-41d4-a716-446655440000 | Success | Successfully pushed to https://study.canadavisaservices.org | Synced: 15 | Skipped: 1 | Errors: 0
```

### Client Site Logs

**Before:**
```
(Only client_pull entries, no indication of pushed content)
2025-12-15 05:54:50 | client_pull | courses | 12345 | Success | Content created
```

**After:**
```
(Both pull and push operations properly logged)
2025-12-15 05:54:50 | master_push | courses | 550e8400-e29b-41d4-a716-446655440000 | Success | Content created
2025-12-15 05:55:10 | client_pull | courses | 550e8400-e29b-41d4-a716-446655440000 | Skipped | Content already exists
```

## Technical Details

### Sync Type Parameter
The `sync_single_item()` method now accepts a third parameter:
```php
public static function sync_single_item( $item, $content_type, $sync_type = 'client_pull' )
```

- **Default**: 'client_pull' (maintains backward compatibility)
- **When receiving pushed content**: 'master_push'

### UUID Usage in Logging
Both master and client now use UUID as the content_id in logs:
```php
// Get UUID for logging
$uuid = get_post_meta( $post_id, LDMCS_Sync::UUID_META_KEY, true );
$log_id = ! empty( $uuid ) ? $uuid : $post_id;

// On client, use UUID from item data
$log_content_id = isset( $item['id'] ) ? $item['id'] : 0;
```

This ensures:
1. Content can be tracked across sites using the same identifier
2. Logs are more meaningful and easier to correlate
3. Post IDs remain internal to each site

## Benefits

1. **Better Visibility**: Client sites now show when content is pushed vs. pulled
2. **Easier Troubleshooting**: UUID-based logging makes it easy to track content across master and client sites
3. **Complete Statistics**: Master logs include full sync statistics for each push operation
4. **Cross-Site Correlation**: Same UUID appears in both master and client logs for the same content

## Compatibility

- **Backward Compatible**: The `$sync_type` parameter defaults to 'client_pull', so existing code continues to work
- **No Database Changes**: Uses existing log table structure
- **No Breaking Changes**: All changes are additive
