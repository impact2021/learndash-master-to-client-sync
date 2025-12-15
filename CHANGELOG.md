# Changelog

All notable changes to the LearnDash Master to Client Sync plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2024-12-15

### Fixed

#### Critical API Key Authentication Bug
- **Fixed:** Push operations from master to client sites were failing with "Invalid API key" error even after successful connection verification
- **Root Cause:** The `/receive` endpoint on client sites was incorrectly checking the received API key against the client's own API key instead of the master's API key
- **Solution:** Created new `check_master_api_key()` method that validates against `ldmcs_master_api_key` (the master's API key stored by the client)
- The `/receive` endpoint now uses `check_master_api_key()` instead of `check_api_key()` for proper authentication
- This fixes the issue where verified connections would still fail during push operations

#### Security Improvements
- Added `esc_html()` escaping for post titles in error messages to prevent potential XSS vulnerabilities
- Properly sanitized all user-facing content in error reporting

### Improved

#### Error Reporting
- Enhanced push failure error messages to include specific reasons for failures
- Added detailed error information showing which client sites failed and why
- Error messages now include per-site failure details instead of generic "failed to push" messages
- Improved debugging capability by providing actionable error information

### Technical Details

#### API Authentication Flow
- **Master site endpoints** (GET /content, GET /verify): Use `check_api_key()` to validate against master's own API key
- **Client site endpoint** (POST /receive): Now uses `check_master_api_key()` to validate against the master's API key that the client has stored
- This ensures proper bidirectional authentication between master and client sites

#### Push Error Handling
- Modified `handle_push_course()` to build detailed error messages with per-site failure reasons
- Modified `handle_push_content()` to build detailed error messages with per-site failure reasons
- Error details now include site URL and specific failure message for each failed client
- Better user experience with more informative error messages

## [1.0.0] - 2024-12-15

### Added

#### Core Features
- Initial release of LearnDash Master to Client Sync plugin
- Support for syncing LearnDash courses, lessons, topics, quizzes, and questions
- Master site mode for exposing content via REST API
- Client site mode for consuming and syncing content

#### Master Site Features
- REST API endpoints for content exposure
- API key authentication system
- Automatic API key generation on activation
- API key regeneration functionality
- Content serialization with metadata and taxonomies

#### Client Site Features
- Master site connection configuration
- Connection verification functionality
- Manual sync trigger
- Automatic scheduled sync via WordPress cron
- Configurable sync intervals (hourly, twice daily, daily)
- Selective content type syncing
- Batch processing for performance optimization
- Configurable batch sizes (1-50 items)
- Conflict resolution options (skip or overwrite)

#### Admin Interface
- Settings page with comprehensive configuration options
- Sync logs page for operation history
- Real-time sync progress feedback
- Connection status indicators
- AJAX-powered actions for better UX

#### Security Features
- API key authentication for all API requests
- WordPress nonce verification for admin actions
- Input sanitization and validation
- Permission checks (manage_options capability)
- Secure password generation for API keys

#### Performance Optimizations
- Background processing via WordPress cron
- Batch processing to prevent timeouts
- Configurable batch sizes
- Smart conflict resolution to skip unnecessary updates
- Efficient database queries with proper indexing

#### Logging and Monitoring
- Comprehensive sync logging system
- Database table for log storage
- Log viewing interface
- Status tracking (success, error, skipped)
- Detailed error messages

#### Developer Features
- Clean object-oriented code structure
- Action hooks for extensibility
- Well-documented inline code
- Modular architecture
- WordPress Coding Standards compliance

### Technical Details

#### Database
- Created `wp_ldmcs_sync_log` table for storing sync history
- Indexed columns for optimal query performance
- Automatic table creation on activation

#### REST API Endpoints
- `GET /wp-json/ldmcs/v1/verify` - Connection verification
- `GET /wp-json/ldmcs/v1/content/{type}` - Get content list with pagination
- `GET /wp-json/ldmcs/v1/content/{type}/{id}` - Get single content item

#### Options
- `ldmcs_mode` - Site mode (master/client)
- `ldmcs_api_key` - Master site API key
- `ldmcs_master_url` - Master site URL (client only)
- `ldmcs_master_api_key` - Master API key (client only)
- `ldmcs_auto_sync_enabled` - Auto sync toggle
- `ldmcs_sync_interval` - Sync frequency
- `ldmcs_sync_*` - Content type toggles
- `ldmcs_conflict_resolution` - Conflict handling strategy
- `ldmcs_batch_size` - Items per batch

#### Post Meta
- `_ldmcs_master_id` - Original post ID from master site
- `_ldmcs_last_sync` - Last sync timestamp

### Documentation
- Comprehensive README.md with features and usage
- Detailed INSTALL.md with step-by-step instructions
- CONTRIBUTING.md for developers
- Inline code documentation
- PHPDoc blocks for all functions and methods

### Files Added
- `learndash-master-to-client-sync.php` - Main plugin file
- `includes/class-ldmcs-master.php` - Master site functionality
- `includes/class-ldmcs-client.php` - Client site functionality
- `includes/class-ldmcs-admin.php` - Admin interface
- `includes/class-ldmcs-api.php` - REST API handler
- `includes/class-ldmcs-sync.php` - Sync engine
- `includes/class-ldmcs-logger.php` - Logging functionality
- `assets/css/admin.css` - Admin styles
- `assets/js/admin.js` - Admin JavaScript
- `uninstall.php` - Clean uninstall script
- `.gitignore` - Git ignore rules
- `README.md` - Main documentation
- `INSTALL.md` - Installation guide
- `CONTRIBUTING.md` - Contribution guidelines
- `CHANGELOG.md` - This file

### Requirements
- WordPress 5.0 or higher
- PHP 7.2 or higher
- LearnDash LMS plugin
- HTTPS recommended

### Known Limitations
- Maximum 50 items per batch
- No webhook support (scheduled sync only)
- No selective course/category filtering
- No rollback functionality
- Single master site per client

### Tested With
- WordPress 5.0 - 6.4
- PHP 7.2 - 8.2
- LearnDash 3.0+

## Future Plans

### Version 1.1.0 (Planned)
- Webhook support for real-time sync
- Advanced filtering options
- Sync status dashboard widget
- Email notifications

### Version 1.2.0 (Planned)
- Multi-master support
- Rollback functionality
- Conflict resolution preview
- Selective content filtering

### Version 2.0.0 (Planned)
- Two-way sync support
- Advanced scheduling options
- Performance analytics
- REST API v2

---

[2.0.0]: https://github.com/impact2021/learndash-master-to-client-sync/releases/tag/v2.0.0
[1.0.0]: https://github.com/impact2021/learndash-master-to-client-sync/releases/tag/v1.0.0
