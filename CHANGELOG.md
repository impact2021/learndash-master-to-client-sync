# Changelog

All notable changes to the LearnDash Master-Client Sync plugin will be documented in this file.

## [2.0] - 2025-12-15

### Major Changes
- **Combined Plugins**: Merged "LD Master Push" and "LD Client Receive" into a single unified plugin
- **New Name**: Renamed to "LearnDash Master-Client Sync" for clarity
- **Menu Structure**: Unified menu with two submenus (Master Push and Client Receive)

### Added
- Select-all checkbox for course selection on Master Push page
- Enhanced push results display with formatted output
- Test REST API button on Client Receive page
- Comprehensive inline code documentation
- Support for quiz questions in sync process
- Better error handling and response codes
- Timestamp information in REST API responses
- Confirmation dialog when deleting clients
- Organized code structure with clear section headers

### Improved
- **Security**: Enhanced nonce verification on all forms
- **UI/UX**: Better form layouts with descriptions and proper labels
- **Code Organization**: Clear separation of concerns with descriptive function names
- **Documentation**: Complete user and developer guides
- **Performance**: Increased timeout to 45 seconds for large courses
- **Data Integrity**: Better HTML preservation with wp_kses_post()
- **Relationships**: Improved parent-child relationship handling

### Fixed
- UUID generation and tracking for all content types
- Course-level quiz export (separate from lesson quizzes)
- Proper query for lessons, topics, and quizzes using meta fields
- Response handling for failed pushes

### Documentation
- Added INSTALL.md - Installation and troubleshooting guide
- Added USER-GUIDE.md - Complete user manual with examples
- Added DEVELOPER-GUIDE.md - Technical documentation with customization examples
- Updated README.md - Project overview and features
- Added CHANGELOG.md - Version history
- Added .gitignore - Exclude unnecessary files from repository

### Technical Details
- Plugin file: learndash-sync.php (687 lines)
- Main functions: 10
- Admin pages: 2
- REST endpoints: 2
- Post types supported: 5 (courses, lessons, topics, quizzes, questions)

## [1.5.1] - Previous Version (LD Client Receive)

### Changed
- Improved HTML sanitization with wp_kses_post()
- Enhanced content preservation during sync

### Fixed
- Question syncing issues
- Meta field name consistency

## [1.5] - Previous Version (LD Master Push)

### Added
- UUID column in course selection table
- UUID tracking for content synchronization

### Improved
- Client management interface
- Course export functionality

---

## Version Numbering

This project follows [Semantic Versioning](https://semver.org/):
- **MAJOR** version for incompatible API changes
- **MINOR** version for new functionality in a backward-compatible manner
- **PATCH** version for backward-compatible bug fixes

## Upgrade Notes

### From 1.5/1.5.1 to 2.0

**Breaking Changes:**
- Two separate plugins are now combined into one
- Plugin name changed from "LD Master Push"/"LD Client Receive" to "LearnDash Master-Client Sync"
- Menu location changed

**Migration Steps:**

1. **Export Data** (Important!)
   - On master site: Note down all client site URLs and secret keys
   - On client sites: Note down secret key configuration

2. **Deactivate Old Plugins**
   - Deactivate "LD Master Push" (on master site)
   - Deactivate "LD Client Receive" (on client sites)

3. **Install New Plugin**
   - Install "LearnDash Master-Client Sync" on all sites
   - See INSTALL.md for installation instructions

4. **Reconfigure**
   - On master site: Re-add client sites in "LearnDash Sync → Master Push"
   - On client sites: Re-enter secret key in "LearnDash Sync → Client Receive"

5. **Test**
   - Use Test REST API button to verify connectivity
   - Do a test push with one course

**Data Preserved:**
- All existing UUIDs are preserved
- Course content is not affected
- Settings are stored in same database options

**What's NOT Migrated Automatically:**
- Client site configurations (must be re-entered)
- Secret keys (must be re-entered for security)

## Future Roadmap

### Planned Features
- [ ] Selective client push (push to specific clients only)
- [ ] Push history and logging
- [ ] Scheduled/automated pushes
- [ ] Content comparison before push
- [ ] Conflict resolution interface
- [ ] Multi-site support
- [ ] Push notifications via email
- [ ] Progress indicators for large pushes
- [ ] Rollback functionality
- [ ] Import/export client configurations

### Under Consideration
- [ ] Two-way sync capability
- [ ] Content versioning
- [ ] Differential updates (only changed content)
- [ ] Custom field mapping
- [ ] Media file sync
- [ ] User enrollment sync
- [ ] REST API authentication with JWT
- [ ] WP-CLI commands

## Support

For issues, questions, or feature requests:
- Review documentation: INSTALL.md, USER-GUIDE.md, DEVELOPER-GUIDE.md
- Check this changelog for known issues and migration notes
- Enable WordPress debug mode for detailed error messages
- Contact: Impact Websites

## Credits

- **Author**: Impact Websites
- **Contributors**: [List contributors here]
- **License**: GPL2
- **Repository**: https://github.com/impact2021/learndash-master-to-client-sync

---

**Last Updated**: December 15, 2025
