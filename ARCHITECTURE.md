# Plugin Architecture

## Overview

The LearnDash Master to Client Sync plugin follows a modular, object-oriented architecture with clear separation of concerns.

## Component Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    WordPress Environment                     │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │          LearnDash Master to Client Sync            │   │
│  │                                                       │   │
│  │  ┌──────────────┐         ┌──────────────┐         │   │
│  │  │ Master Mode  │         │ Client Mode  │         │   │
│  │  │              │         │              │         │   │
│  │  │ LDMCS_Master │         │ LDMCS_Client │         │   │
│  │  │ LDMCS_API    │◄────────┤ LDMCS_Sync   │         │   │
│  │  │              │  REST   │              │         │   │
│  │  └──────┬───────┘  API    └──────┬───────┘         │   │
│  │         │                         │                  │   │
│  │         └─────────┬───────────────┘                  │   │
│  │                   │                                   │   │
│  │         ┌─────────▼──────────┐                       │   │
│  │         │    LDMCS_Admin     │                       │   │
│  │         │  (Settings & Logs) │                       │   │
│  │         └─────────┬──────────┘                       │   │
│  │                   │                                   │   │
│  │         ┌─────────▼──────────┐                       │   │
│  │         │   LDMCS_Logger     │                       │   │
│  │         │   (Sync History)   │                       │   │
│  │         └────────────────────┘                       │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

## Class Structure

### Main Plugin Class: `LearnDash_Master_Client_Sync`

**Location:** `learndash-master-to-client-sync.php`

**Responsibilities:**
- Plugin initialization and bootstrap
- Hook registration
- Component instantiation
- Activation/deactivation handling
- Database table creation

**Key Methods:**
- `get_instance()` - Singleton instance
- `init()` - Initialize components
- `activate()` - Plugin activation
- `deactivate()` - Plugin deactivation

### Master Site: `LDMCS_Master`

**Location:** `includes/class-ldmcs-master.php`

**Responsibilities:**
- Track content updates on master site
- Provide API key management
- Emit hooks for content changes

**Key Methods:**
- `on_content_save()` - Handle content save events
- `get_api_key()` - Retrieve API key
- `regenerate_api_key()` - Generate new API key

### REST API: `LDMCS_API`

**Location:** `includes/class-ldmcs-api.php`

**Responsibilities:**
- Expose REST API endpoints
- Handle API authentication
- Serialize LearnDash content
- Return paginated responses

**REST Endpoints:**
```
GET /wp-json/ldmcs/v1/verify
GET /wp-json/ldmcs/v1/content/{type}?page=1&per_page=10
GET /wp-json/ldmcs/v1/content/{type}/{id}
```

**Key Methods:**
- `register_routes()` - Register API endpoints
- `check_api_key()` - Validate authentication
- `get_content()` - Fetch content list
- `get_single_content()` - Fetch single item

### Client Site: `LDMCS_Client`

**Location:** `includes/class-ldmcs-client.php`

**Responsibilities:**
- Handle scheduled sync operations
- Process AJAX requests
- Coordinate with sync engine

**Key Methods:**
- `run_scheduled_sync()` - Execute cron sync
- `handle_manual_sync()` - Process manual sync
- `handle_verify_connection()` - Verify master connection

### Sync Engine: `LDMCS_Sync`

**Location:** `includes/class-ldmcs-sync.php`

**Responsibilities:**
- Fetch content from master site
- Process batch sync operations
- Handle conflict resolution
- Create/update content on client

**Key Methods:**
- `sync_from_master()` - Main sync orchestrator
- `sync_content_type()` - Sync specific content type
- `sync_single_item()` - Process individual item
- `create_content()` - Create new content
- `update_content()` - Update existing content

### Admin Interface: `LDMCS_Admin`

**Location:** `includes/class-ldmcs-admin.php`

**Responsibilities:**
- Render admin pages
- Handle settings management
- Display sync logs
- Enqueue admin assets

**Admin Pages:**
- Settings page (`ldmcs-settings`)
- Sync logs page (`ldmcs-logs`)

### Logger: `LDMCS_Logger`

**Location:** `includes/class-ldmcs-logger.php`

**Responsibilities:**
- Record sync operations
- Store operation results
- Provide log retrieval
- Clean old logs

**Key Methods:**
- `log()` - Create log entry
- `get_recent_logs()` - Retrieve logs
- `clear_old_logs()` - Cleanup old entries

## Data Flow

### Master to Client Sync Flow

```
┌─────────────────┐
│   Client Site   │
│  (Cron/Manual)  │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  1. LDMCS_Sync::sync_from_master()      │
│     - Get configuration                  │
│     - Determine content types            │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  2. Fetch content from master           │
│     - HTTP GET with API key             │
│     - Paginated requests                │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  3. Process each content item           │
│     - Check if exists                   │
│     - Apply conflict resolution         │
│     - Create or update content          │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│  4. Log results                         │
│     - LDMCS_Logger::log()               │
│     - Store in database                 │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────┐
│  Return Results │
│  to Admin UI    │
└─────────────────┘
```

### API Request Flow

```
┌──────────────┐
│ Client Site  │
└──────┬───────┘
       │ HTTP GET + API Key
       ▼
┌────────────────────────────────────┐
│ Master Site REST API               │
│ LDMCS_API::check_api_key()        │
└──────┬─────────────────────────────┘
       │ Authenticated
       ▼
┌────────────────────────────────────┐
│ LDMCS_API::get_content()           │
│ - Query WordPress posts            │
│ - Serialize with metadata          │
│ - Include taxonomies               │
└──────┬─────────────────────────────┘
       │ JSON Response
       ▼
┌──────────────┐
│ Client Site  │
│ Process Data │
└──────────────┘
```

## Database Schema

### Sync Log Table: `wp_ldmcs_sync_log`

```sql
CREATE TABLE wp_ldmcs_sync_log (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    sync_type varchar(50) NOT NULL,        -- 'master_update', 'client_pull'
    content_type varchar(50) NOT NULL,      -- 'courses', 'lessons', etc.
    content_id bigint(20) NOT NULL,         -- WordPress post ID
    status varchar(20) NOT NULL,            -- 'success', 'error', 'skipped'
    message text,                           -- Detailed message
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY sync_type (sync_type),
    KEY content_type (content_type),
    KEY status (status),
    KEY created_at (created_at)
);
```

### Post Meta

Custom post meta added to synced content:

- `_ldmcs_master_id` - Original post ID from master site
- `_ldmcs_last_sync` - Timestamp of last sync

## Configuration Options

All options stored in WordPress `wp_options` table with `ldmcs_` prefix:

```php
ldmcs_mode                // 'master' or 'client'
ldmcs_api_key             // Master site API key (32 chars)
ldmcs_master_url          // Master site URL (client only)
ldmcs_master_api_key      // Master API key (client only)
ldmcs_auto_sync_enabled   // Boolean
ldmcs_sync_interval       // 'hourly', 'twicedaily', 'daily'
ldmcs_sync_courses        // Boolean
ldmcs_sync_lessons        // Boolean
ldmcs_sync_topics         // Boolean
ldmcs_sync_quizzes        // Boolean
ldmcs_sync_questions      // Boolean
ldmcs_conflict_resolution // 'skip' or 'overwrite'
ldmcs_batch_size          // Integer 1-50
```

## Security Architecture

### Authentication Flow

```
Client Request
    │
    ▼
┌─────────────────────────┐
│ X-LDMCS-API-Key header  │
└───────────┬─────────────┘
            │
            ▼
┌────────────────────────────────┐
│ LDMCS_API::check_api_key()     │
│ - Validate presence             │
│ - Compare with stored key       │
└───────────┬────────────────────┘
            │
     ┌──────┴──────┐
     │             │
  Invalid      Valid
     │             │
     ▼             ▼
┌─────────┐   ┌──────────┐
│ 401/403 │   │ Continue │
│ Error   │   │ Request  │
└─────────┘   └──────────┘
```

### Security Measures

1. **API Authentication**: 32-character random API keys
2. **Nonce Verification**: All AJAX requests require valid nonces
3. **Capability Checks**: Admin actions require `manage_options`
4. **Input Sanitization**: All user input sanitized
5. **Output Escaping**: All output properly escaped
6. **HTTPS**: Recommended for all communications

## Performance Considerations

### Batch Processing

```
Total Content: 100 items
Batch Size: 10
Pages: 10

Page 1: Items 1-10   ─┐
Page 2: Items 11-20   │
Page 3: Items 21-30   │
...                   ├─ Process sequentially
Page 9: Items 81-90   │
Page 10: Items 91-100 ┘
```

### Cron Scheduling

```
WordPress Cron
    │
    ▼
┌────────────────────┐
│ ldmcs_sync_content │
│ (Scheduled Event)  │
└─────────┬──────────┘
          │
          ▼
┌───────────────────────────┐
│ LDMCS_Client::run_...()   │
│ - Check if enabled        │
│ - Execute in background   │
└───────────────────────────┘
```

## Extension Points

### Action Hooks

```php
// Fired when content is updated on master
do_action( 'ldmcs_content_updated', $post_id, $post );
```

### Future Hook Points

```php
// Before sync starts
do_action( 'ldmcs_before_sync', $content_types );

// After sync completes
do_action( 'ldmcs_after_sync', $results );

// Before creating content
do_action( 'ldmcs_before_create_content', $item, $post_type );

// After creating content
do_action( 'ldmcs_after_create_content', $post_id, $item );
```

## File Structure

```
learndash-master-to-client-sync/
│
├── learndash-master-to-client-sync.php  # Main plugin file
├── uninstall.php                         # Cleanup script
│
├── includes/                             # PHP classes
│   ├── class-ldmcs-admin.php            # Admin interface
│   ├── class-ldmcs-api.php              # REST API handler
│   ├── class-ldmcs-client.php           # Client functionality
│   ├── class-ldmcs-logger.php           # Logging system
│   ├── class-ldmcs-master.php           # Master functionality
│   └── class-ldmcs-sync.php             # Sync engine
│
├── assets/                               # Frontend assets
│   ├── css/
│   │   └── admin.css                    # Admin styles
│   └── js/
│       └── admin.js                     # Admin scripts
│
└── [documentation files]                 # README, INSTALL, etc.
```

## Design Patterns

### Singleton Pattern

All main classes use the singleton pattern:

```php
class LDMCS_Class {
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize
    }
}
```

### Factory Pattern (Content Type Mapping)

```php
private function get_post_type_from_content_type( $content_type ) {
    $mapping = array(
        'courses'   => 'sfwd-courses',
        'lessons'   => 'sfwd-lessons',
        // ...
    );
    return $mapping[ $content_type ];
}
```

## Error Handling

### Error Levels

1. **Critical**: Connection failures, authentication errors
2. **Warning**: Skipped items, conflict resolution
3. **Info**: Successful operations

### Error Flow

```
Operation
    │
    ├─ Success → Log success → Continue
    │
    ├─ Skipped → Log skipped → Continue
    │
    └─ Error → Log error → Continue (non-blocking)
```

## Testing Considerations

### Manual Testing Checklist

- [ ] Plugin activation/deactivation
- [ ] Settings save and load
- [ ] API key generation
- [ ] Connection verification
- [ ] Manual sync trigger
- [ ] Automatic sync execution
- [ ] Content creation/update
- [ ] Conflict resolution
- [ ] Log recording
- [ ] Multi-site compatibility

### Performance Testing

- Test with 10, 100, 1000+ items
- Monitor memory usage
- Check execution time
- Verify batch processing
- Test under load

## Future Architecture Enhancements

1. **Webhook Support**: Real-time push notifications
2. **Queue System**: Better async processing
3. **Caching Layer**: Reduce API calls
4. **Multi-master**: Support multiple source sites
5. **Selective Sync**: Filter by category, tag, etc.
6. **Rollback**: Undo sync operations
7. **Analytics**: Sync statistics and reports

---

This architecture is designed to be:
- **Scalable**: Handle sites of any size
- **Maintainable**: Clear separation of concerns
- **Extensible**: Hook system for customization
- **Secure**: Multiple layers of protection
- **Performant**: Optimized for minimal impact
