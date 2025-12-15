# LearnDash Master to Client Sync

A WordPress plugin that syncs LearnDash content from a master site to client sites without impacting users. Designed for IELTStestONLINE to affiliate sites synchronization.

## Description

This plugin enables seamless synchronization of LearnDash LMS content (courses, lessons, topics, quizzes, and questions) from a master/source site to multiple client/destination sites. The sync process is designed to be:

- **Non-intrusive**: Background processing with configurable batch sizes to avoid performance impact
- **Flexible**: Choose which content types to sync and how to handle conflicts
- **Secure**: API key authentication for all communications
- **Reliable**: Comprehensive logging and error handling

## Features

- ✅ Sync LearnDash courses, lessons, topics, quizzes, and questions
- ✅ REST API for secure master-client communication
- ✅ Automatic scheduled syncing or manual on-demand sync
- ✅ Configurable conflict resolution (skip or overwrite)
- ✅ Batch processing to minimize server load
- ✅ Comprehensive sync logging
- ✅ Connection verification
- ✅ User-friendly admin interface

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- LearnDash LMS plugin installed and activated
- HTTPS enabled (recommended for secure API communication)

## Installation

1. Download the plugin files
2. Upload the `learndash-master-to-client-sync` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **LearnDash Sync** in the WordPress admin menu

## Configuration

### Master Site Setup

1. Go to **LearnDash Sync > Settings**
2. Set **Site Mode** to "Master Site"
3. Copy the generated **API Key** (you'll need this for client sites)
4. Save settings

### Client Site Setup

1. Go to **LearnDash Sync > Settings**
2. Set **Site Mode** to "Client Site"
3. Enter the **Master Site URL** (e.g., `https://master-site.com`)
4. Enter the **Master Site API Key** (copied from the master site)
5. Click **Verify Connection** to test the connection
6. Configure sync options:
   - **Auto Sync**: Enable automatic synchronization
   - **Sync Interval**: Choose how often to sync (hourly, twice daily, or daily)
   - **Content Types**: Select which content types to sync
   - **Conflict Resolution**: Choose how to handle existing content
   - **Batch Size**: Set the number of items to sync per batch (1-50)
7. Save settings

## Usage

### Manual Sync

On a client site, go to **LearnDash Sync > Settings** and click the **Sync Now** button to manually trigger a sync operation.

### Automatic Sync

Enable **Auto Sync** in the settings to automatically sync content based on the configured interval. The plugin uses WordPress cron to schedule background sync operations.

### View Sync Logs

Go to **LearnDash Sync > Sync Logs** to view a history of sync operations, including:
- Date/time of sync
- Sync type (master update, client pull)
- Content type and ID
- Status (success, error, skipped)
- Detailed messages

## Performance Considerations

The plugin is designed to minimize impact on site performance:

1. **Background Processing**: Syncs run via WordPress cron, not during user requests
2. **Batch Processing**: Content is synced in small batches to avoid timeouts
3. **Configurable Intervals**: Choose sync frequency based on your needs
4. **Selective Syncing**: Only sync the content types you need
5. **Smart Conflict Resolution**: Skip existing content to avoid unnecessary updates

## API Endpoints

The plugin exposes the following REST API endpoints on the master site:

- `GET /wp-json/ldmcs/v1/verify` - Verify connection
- `GET /wp-json/ldmcs/v1/content/{type}` - Get content list (courses, lessons, topics, quizzes, questions)
- `GET /wp-json/ldmcs/v1/content/{type}/{id}` - Get single content item

All endpoints require authentication via the `X-LDMCS-API-Key` header.

## Security

- API key authentication for all API requests
- WordPress nonce verification for admin actions
- Input sanitization and validation
- HTTPS recommended for all communications
- API keys are randomly generated 32-character strings

## Troubleshooting

### Connection Failed

- Verify the master site URL is correct and accessible
- Ensure the API key matches the one on the master site
- Check that the LearnDash plugin is active on the master site
- Verify that REST API is not blocked on either site

### Sync Not Working

- Check that Auto Sync is enabled
- Verify WordPress cron is functioning (`wp cron test`)
- Review sync logs for error messages
- Ensure sufficient server resources (memory, execution time)

### Content Not Appearing

- Check conflict resolution setting
- Review sync logs for skipped items
- Verify content types are enabled in settings
- Check that content exists on the master site

## Development

### File Structure

```
learndash-master-to-client-sync/
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── includes/
│   ├── class-ldmcs-admin.php
│   ├── class-ldmcs-api.php
│   ├── class-ldmcs-client.php
│   ├── class-ldmcs-logger.php
│   ├── class-ldmcs-master.php
│   └── class-ldmcs-sync.php
├── learndash-master-to-client-sync.php
└── README.md
```

### Hooks and Filters

**Actions:**
- `ldmcs_content_updated` - Fired when content is updated on master site

**Filters:**
- Coming soon...

## Changelog

### 1.0.0
- Initial release
- Master site REST API
- Client site sync functionality
- Admin interface
- Automatic and manual sync
- Comprehensive logging
- Batch processing
- Conflict resolution

## Support

For issues, questions, or contributions, please visit:
- GitHub: https://github.com/impact2021/learndash-master-to-client-sync

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Credits

Developed by Impact 2021 for IELTStestONLINE affiliate network.
