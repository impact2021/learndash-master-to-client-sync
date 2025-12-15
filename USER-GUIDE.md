# LearnDash Master-Client Sync - User Guide

## What Does This Plugin Do?

This plugin allows you to:
1. **Push courses** from your main (master) WordPress site to multiple other (client) sites
2. **Automatically sync** all course content including lessons, topics, quizzes, and questions
3. **Keep content updated** - pushing the same course again will update it, not duplicate it

## Installation

### Step 1: Install the Plugin

#### On ALL Sites (Master and Clients)
1. Download `learndash-sync.php`
2. Upload to `/wp-content/plugins/` folder
3. Activate in WordPress Admin → Plugins
4. **Important**: LearnDash LMS must be installed and activated first

## Setup Guide

### On Your MASTER Site (Where Courses Originate)

#### Step 1: Access Master Push Settings
1. Log into WordPress Admin
2. Go to **LearnDash Sync → Master Push**

#### Step 2: Add Client Sites
1. In the "Add Client Site" section:
   - **Client URL**: Enter the full REST API URL from your client site
     - Example: `https://clientsite.com/wp-json/ld-sync/v1/receive`
   - **Secret Key**: Enter a secure password (you'll use the same on client site)
     - Example: `MySecureKey123!`
2. Click **"Add / Update Client"**
3. Repeat for each client site

#### Step 3: Push Courses
1. Scroll to "Push Selected Courses" section
2. Check the boxes next to courses you want to push
   - Use the checkbox in the header to select all courses
3. Click **"Push Selected Courses to All Clients"**
4. Wait for the results to appear
5. Check for success messages

### On Your CLIENT Sites (Where Courses Are Received)

#### Step 1: Access Client Receive Settings
1. Log into WordPress Admin
2. Go to **LearnDash Sync → Client Receive**

#### Step 2: Configure Secret Key
1. In the "Secret Key Configuration" section:
   - **Secret Key**: Enter the SAME password you used on the master site
     - Example: `MySecureKey123!`
2. Click **"Save Secret Key"**

#### Step 3: Get Your REST API URL
1. Look at "REST API Endpoints" section
2. Copy the **Receive Endpoint** URL
   - Example: `https://yourclientsite.com/wp-json/ld-sync/v1/receive`
3. Send this URL to your master site administrator
4. They will add it to the Master Push settings

#### Step 4: Test Connection (Optional)
1. Click the **"Test REST API"** button
2. A new tab should open showing: `{"status":"success"...}`
3. If you see an error, contact your hosting provider

## Common Questions

### Q: What gets synced?
**A:** Everything in the selected courses:
- Course title and content
- All lessons with their content
- All topics with their content
- All quizzes with their content
- All quiz questions

### Q: Will it create duplicates?
**A:** No. The plugin uses UUIDs (unique identifiers) to track content. If you push the same course twice, it will update the existing course instead of creating a duplicate.

### Q: What happens to students' progress?
**A:** Student progress is NOT synced. This plugin only syncs course content (structure and materials).

### Q: Can I push to just one client?
**A:** Currently, the plugin pushes to ALL configured clients at once. To push to just one client:
1. Temporarily delete other clients from the list
2. Push your courses
3. Re-add the deleted clients

### Q: How do I update a course on client sites?
**A:** Simply push the course again from the master site. The plugin will detect the existing course (by UUID) and update it.

### Q: What if a push fails?
**A:** The results section will show error messages. Common issues:
- **Wrong secret key**: Make sure keys match on both sites
- **URL not accessible**: Check the client site URL is correct
- **Timeout**: Course is too large - try pushing fewer courses at once

### Q: Can I see what was synced?
**A:** Yes, the "Push Results" section shows detailed information about what was synced, including:
- Course IDs
- Lesson IDs
- Topic IDs
- Quiz IDs
- Question IDs

## Step-by-Step Example

Let's say you want to push a course called "Introduction to IELTS" to two client sites.

### Setup Phase

**Master Site (mainsite.com):**
1. Go to LearnDash Sync → Master Push
2. Add first client:
   - URL: `https://client1.com/wp-json/ld-sync/v1/receive`
   - Secret: `SecretKey123`
3. Add second client:
   - URL: `https://client2.com/wp-json/ld-sync/v1/receive`
   - Secret: `DifferentKey456`

**Client Site 1 (client1.com):**
1. Go to LearnDash Sync → Client Receive
2. Set Secret Key: `SecretKey123`
3. Copy Receive URL: `https://client1.com/wp-json/ld-sync/v1/receive`
4. Send to master site admin

**Client Site 2 (client2.com):**
1. Go to LearnDash Sync → Client Receive
2. Set Secret Key: `DifferentKey456`
3. Copy Receive URL: `https://client2.com/wp-json/ld-sync/v1/receive`
4. Send to master site admin

### Push Phase

**Back to Master Site:**
1. Go to LearnDash Sync → Master Push
2. Scroll to "Push Selected Courses"
3. Check the box next to "Introduction to IELTS"
4. Click "Push Selected Courses to All Clients"
5. Wait 10-30 seconds (depending on course size)
6. See success message with details

**Verify on Client Sites:**
1. Go to LearnDash LMS → Courses
2. See "Introduction to IELTS" with all lessons, topics, and quizzes
3. Course is ready to use!

## Troubleshooting

### "Invalid SECRET_KEY1" Error
- **Problem**: Secret key doesn't match
- **Solution**: Check that both sites use the EXACT same secret key (case-sensitive, no extra spaces)

### "Connection Timeout" Error
- **Problem**: Course too large or slow connection
- **Solution**: 
  - Try pushing fewer courses at once
  - Contact hosting provider to increase PHP timeout
  - Try again during off-peak hours

### Courses Not Appearing on Client
- **Problem**: Multiple possible causes
- **Solutions**:
  1. Check client site has LearnDash installed and activated
  2. Verify REST API is working (use Test button)
  3. Check WordPress debug log for errors
  4. Ensure client site URL is correct

### Duplicate Courses Created
- **Problem**: UUID system not working
- **Solution**: 
  1. Don't manually edit UUIDs in database
  2. Delete duplicate courses manually
  3. Push again - it should update, not duplicate

### Can't Find the Menu
- **Problem**: Plugin not activated or permissions issue
- **Solution**:
  1. Check Plugins page - is "LearnDash Master-Client Sync" active?
  2. Make sure you're logged in as Administrator
  3. Try deactivating and reactivating the plugin

## Best Practices

### 1. Test First
- Set up one client site first
- Test with a simple course
- Verify everything works before adding more clients

### 2. Keep Secret Keys Secure
- Use strong, unique passwords
- Don't share secret keys publicly
- Use different keys for each client for better security

### 3. Regular Backups
- Backup your databases before major pushes
- Keep backups of both master and client sites

### 4. Update During Low Traffic
- Push courses when sites have fewer visitors
- Reduces chances of conflicts or slowdowns

### 5. Document Your Setup
- Keep a list of which client sites use which secret keys
- Note the date of each major push
- Track which courses have been pushed where

## Support Checklist

If you need help, gather this information:

- [ ] WordPress version (both sites)
- [ ] LearnDash version (both sites)
- [ ] Plugin version
- [ ] Error message (exact text)
- [ ] What you were trying to do
- [ ] Screenshot of the error
- [ ] Did it work before? When did it stop?

## Quick Reference

### Master Site Workflow
1. LearnDash Sync → Master Push
2. Add client sites
3. Select courses
4. Push to clients
5. Review results

### Client Site Workflow
1. LearnDash Sync → Client Receive
2. Set secret key
3. Copy REST URL
4. Give URL to master admin
5. Wait for courses

### Required Info to Share
**Master Site Admin needs from each client:**
- REST API URL (from Client Receive page)
- Agreed upon secret key

**Client Site Admin needs from master:**
- Notification when courses are pushed
- Secret key to configure

---

**Need More Help?** Contact your site administrator or developer with information from the Support Checklist above.
