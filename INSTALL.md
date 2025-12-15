# Quick Installation Guide

## Prerequisites
- WordPress 5.0 or higher
- LearnDash LMS plugin installed and activated
- Administrator access
- PHP 7.0 or higher

## Installation Steps

### 1. Download the Plugin
Download or copy the `learndash-sync.php` file from this repository.

### 2. Install on WordPress

#### Method A: Manual Upload (Recommended)
1. Connect to your site via FTP or File Manager
2. Navigate to `/wp-content/plugins/`
3. Create a new folder: `learndash-sync`
4. Upload `learndash-sync.php` into this folder
5. Final path should be: `/wp-content/plugins/learndash-sync/learndash-sync.php`

#### Method B: WordPress Admin Upload
1. Compress `learndash-sync.php` into a ZIP file
2. In WordPress Admin, go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### 3. Activate the Plugin
1. Go to **Plugins** in WordPress Admin
2. Find "LearnDash Master-Client Sync"
3. Click **Activate**

### 4. Verify Installation
1. Check WordPress Admin menu for "LearnDash Sync"
2. If you see the menu, installation is successful!

## What to Do Next

### If This is Your MASTER Site:
1. Go to **LearnDash Sync → Master Push**
2. Follow the [USER-GUIDE.md](USER-GUIDE.md) to configure client sites
3. Push your first course

### If This is a CLIENT Site:
1. Go to **LearnDash Sync → Client Receive**
2. Follow the [USER-GUIDE.md](USER-GUIDE.md) to configure your secret key
3. Share your REST API URL with your master site administrator

## Troubleshooting Installation

### "Plugin does not have a valid header"
- **Problem**: File is not in the correct location
- **Solution**: Make sure the file is in `/wp-content/plugins/learndash-sync/learndash-sync.php`

### Menu Doesn't Appear
- **Problem**: Plugin not activated or permissions issue
- **Solution**: 
  1. Go to Plugins page and activate the plugin
  2. Make sure you're logged in as Administrator
  3. Clear browser cache and refresh

### "Plugin could not be activated because it triggered a fatal error"
- **Problem**: PHP version too old or LearnDash not installed
- **Solution**:
  1. Check PHP version (requires 7.0+)
  2. Install and activate LearnDash first
  3. Check error logs for specific error message

## Uninstallation

### To Remove the Plugin:
1. **Deactivate**: Go to Plugins and click Deactivate
2. **Delete**: Click Delete after deactivating

### Data Removal:
The plugin stores data in WordPress options and post meta:
- `ld_master_clients` option (master sites)
- `ld_client_secret_key` option (client sites)
- `ld_uuid` meta field on posts

These are NOT automatically deleted. To remove manually:
```sql
-- Remove options
DELETE FROM wp_options WHERE option_name = 'ld_master_clients';
DELETE FROM wp_options WHERE option_name = 'ld_client_secret_key';

-- Remove UUID meta (optional - only if you want to lose sync capability)
DELETE FROM wp_postmeta WHERE meta_key = 'ld_uuid';
```

## Need Help?

- **Users**: See [USER-GUIDE.md](USER-GUIDE.md)
- **Developers**: See [DEVELOPER-GUIDE.md](DEVELOPER-GUIDE.md)
- **Overview**: See [README.md](README.md)

## System Requirements Check

Run this on your site to check compatibility:

```php
// Add to a test page or use WP CLI
echo 'WordPress: ' . get_bloginfo('version') . "\n";
echo 'PHP: ' . phpversion() . "\n";
echo 'LearnDash: ' . (class_exists('SFWD_LMS') ? 'Installed' : 'NOT INSTALLED') . "\n";
echo 'REST API: ' . (get_option('permalink_structure') ? 'Enabled' : 'Disabled - Enable Permalinks!') . "\n";
```

### Minimum Requirements:
- ✅ WordPress 5.0+
- ✅ PHP 7.0+
- ✅ LearnDash (any version)
- ✅ Permalinks enabled (for REST API)

---

**Installation Complete?** Proceed to [USER-GUIDE.md](USER-GUIDE.md) for setup instructions.
