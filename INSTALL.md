# Installation Guide

## Quick Start

Follow these steps to set up the LearnDash Master to Client Sync plugin.

## Prerequisites

Before installing, ensure you have:

1. WordPress 5.0 or higher installed
2. PHP 7.2 or higher
3. LearnDash LMS plugin installed and activated on both master and client sites
4. Administrator access to both master and client WordPress installations
5. HTTPS enabled (strongly recommended for security)

## Installation Steps

### Step 1: Install the Plugin

#### Option A: Manual Installation

1. Download the plugin files
2. Connect to your server via FTP or file manager
3. Upload the entire `learndash-master-to-client-sync` folder to `/wp-content/plugins/`
4. Ensure proper file permissions (644 for files, 755 for directories)

#### Option B: WordPress Upload

1. Zip the `learndash-master-to-client-sync` folder
2. Go to WordPress admin > Plugins > Add New
3. Click "Upload Plugin"
4. Choose the zip file and click "Install Now"

### Step 2: Activate the Plugin

1. Go to **Plugins** in WordPress admin
2. Find "LearnDash Master to Client Sync"
3. Click **Activate**
4. You should see a new menu item "LearnDash Sync" in the admin sidebar

### Step 3: Configure Master Site

On your master/source site (e.g., IELTStestONLINE):

1. Navigate to **LearnDash Sync > Settings**
2. Set **Site Mode** to "Master Site"
3. Copy the **API Key** displayed (you'll need this for each client site)
4. Click **Save Changes**

**Important:** Keep the API key secure and only share it with authorized client sites.

### Step 4: Configure Client Site(s)

On each client/destination site (e.g., affiliate sites):

1. Navigate to **LearnDash Sync > Settings**
2. Set **Site Mode** to "Client Site"
3. Enter **Master Site URL**: The full URL of your master site (e.g., `https://ieltsonline.com`)
4. Enter **Master Site API Key**: The API key from the master site
5. Click **Verify Connection** to test the connection
   - If successful, you'll see a green message with master site details
   - If failed, check the URL, API key, and ensure both sites are accessible
6. Configure sync settings:
   - **Auto Sync**: Check to enable automatic synchronization
   - **Sync Interval**: Choose how often to sync (Hourly, Twice Daily, or Daily)
   - **Content Types**: Select which content to sync (Courses, Lessons, Topics, Quizzes, Questions)
   - **Conflict Resolution**: Choose "Skip existing content" or "Overwrite existing content"
   - **Batch Size**: Set to 10 (default) or lower for shared hosting, higher for dedicated servers
7. Click **Save Changes**

### Step 5: Test Initial Sync

1. On the client site, go to **LearnDash Sync > Settings**
2. Click the **Sync Now** button
3. Wait for the sync to complete
4. Check the results displayed on the page
5. Go to **LearnDash Sync > Sync Logs** to view detailed logs

### Step 6: Verify Synced Content

1. Go to **LearnDash LMS** in the WordPress admin
2. Check that courses, lessons, and other content appear correctly
3. Verify content matches the master site
4. Test a sample course to ensure all components work properly

## Post-Installation Configuration

### Recommended Settings for Production

**For Small Sites (Shared Hosting):**
- Batch Size: 5-10
- Sync Interval: Daily or Twice Daily
- Auto Sync: Enabled

**For Medium Sites (VPS):**
- Batch Size: 10-20
- Sync Interval: Twice Daily or Hourly
- Auto Sync: Enabled

**For Large Sites (Dedicated Server):**
- Batch Size: 20-50
- Sync Interval: Hourly
- Auto Sync: Enabled

### Conflict Resolution Strategy

**Skip existing content:**
- Use when you make local modifications to synced content
- Prevents overwriting custom changes
- New content will still be synced

**Overwrite existing content:**
- Use for true read-only client sites
- Ensures content stays 100% in sync with master
- Local changes will be lost

## Troubleshooting Installation

### Plugin Not Appearing After Activation

**Solution:** Clear your browser cache and WordPress object cache.

```bash
# If using WP-CLI
wp cache flush
```

### LearnDash Missing Notice

**Problem:** You see "LearnDash Master to Client Sync requires LearnDash LMS"

**Solution:** Install and activate the LearnDash LMS plugin first.

### Connection Verification Fails

**Common causes:**
1. Incorrect master site URL (check for typos, http vs https)
2. Wrong API key (copy it again from master site)
3. Master site's REST API is blocked (check firewall, security plugins)
4. SSL certificate issues (ensure valid SSL on both sites)

**To diagnose:**
1. Try accessing `https://your-master-site.com/wp-json/` directly
2. Check error logs on both master and client sites
3. Temporarily disable security plugins to rule out conflicts

### Permission Issues

If you see permission errors:

```bash
# Fix file permissions (run on server)
find /path/to/wp-content/plugins/learndash-master-to-client-sync -type f -exec chmod 644 {} \;
find /path/to/wp-content/plugins/learndash-master-to-client-sync -type d -exec chmod 755 {} \;
```

### Database Table Creation Failed

If activation doesn't create the database table:

1. Check database user has CREATE TABLE permissions
2. Manually create the table using SQL in phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS wp_ldmcs_sync_log (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    sync_type varchar(50) NOT NULL,
    content_type varchar(50) NOT NULL,
    content_id bigint(20) NOT NULL,
    status varchar(20) NOT NULL,
    message text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY sync_type (sync_type),
    KEY content_type (content_type),
    KEY status (status),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Uninstallation

To completely remove the plugin:

1. Go to **Plugins** in WordPress admin
2. **Deactivate** the plugin first
3. Click **Delete**
4. To remove all data including logs, manually drop the database table:

```sql
DROP TABLE IF EXISTS wp_ldmcs_sync_log;
```

## Need Help?

- Check the main README.md for detailed documentation
- Review sync logs for error messages
- Check WordPress debug.log for technical errors
- Visit the GitHub repository for support

## Security Checklist

After installation:

- [ ] HTTPS is enabled on both master and client sites
- [ ] API key is unique and not shared publicly
- [ ] Only authorized sites have the master API key
- [ ] Regular backups are configured
- [ ] WordPress and all plugins are up to date
- [ ] Strong admin passwords are in use
- [ ] Two-factor authentication is enabled for admin accounts

## Next Steps

After successful installation:

1. Set up automatic backups before first sync
2. Test sync with a small batch size first
3. Monitor logs after first few syncs
4. Gradually increase batch size if needed
5. Schedule regular monitoring of sync operations
6. Document your configuration for team reference

Congratulations! Your LearnDash Master to Client Sync plugin is now installed and ready to use.
