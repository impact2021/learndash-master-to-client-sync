# LearnDash Master-Client Sync

A unified WordPress plugin to sync LearnDash courses, lessons, topics, quizzes, and questions between a master site and multiple client sites via REST API.

## Features

### Master Push
- Manage multiple client sites with unique secret keys
- Select and push specific courses to all configured client sites
- Automatic UUID generation for tracking content across sites
- Support for courses, lessons, topics, quizzes, and questions

### Client Receive
- REST API endpoint to receive course content from master site
- Secret key authentication for security
- Automatic creation or update of content based on UUIDs
- Preserves HTML content and maintains parent-child relationships

## Installation

1. Upload `learndash-sync.php` to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'LearnDash Sync' in the WordPress admin menu

## Configuration

### On Master Site
1. Go to **LearnDash Sync > Master Push**
2. Add client site URLs and their secret keys
3. Select courses to push
4. Click "Push Selected Courses to All Clients"

### On Client Sites
1. Go to **LearnDash Sync > Client Receive**
2. Configure the secret key (must match the key on master site)
3. Copy the REST API endpoint URL
4. Provide this URL to the master site administrator

## Requirements

- WordPress 5.0 or higher
- LearnDash LMS plugin
- PHP 7.0 or higher

## Version

2.0 - Combined Master Push and Client Receive into unified plugin
