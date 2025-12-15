# Contributing to LearnDash Master to Client Sync

Thank you for your interest in contributing to this project! This document provides guidelines and information for contributors.

## How to Contribute

### Reporting Bugs

If you find a bug, please create an issue with:

1. **Clear title**: Describe the issue concisely
2. **Description**: Detailed explanation of the problem
3. **Steps to reproduce**: Numbered steps to recreate the issue
4. **Expected behavior**: What should happen
5. **Actual behavior**: What actually happens
6. **Environment details**:
   - WordPress version
   - PHP version
   - LearnDash version
   - Plugin version
   - Server configuration (shared/VPS/dedicated)

### Suggesting Features

Feature requests are welcome! Please include:

1. **Use case**: Why is this feature needed?
2. **Description**: What should the feature do?
3. **Benefits**: How will this help users?
4. **Alternative solutions**: Other approaches you've considered

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Test thoroughly
5. Commit with clear messages (`git commit -m 'Add amazing feature'`)
6. Push to your branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## Development Guidelines

### Code Standards

This project follows WordPress Coding Standards:

- **PHP**: [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- **JavaScript**: [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- **CSS**: [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)

### Key Principles

1. **Security First**: Always sanitize input, escape output, validate data
2. **Performance**: Minimize database queries, use caching where appropriate
3. **Compatibility**: Support WordPress 5.0+ and PHP 7.2+
4. **User Experience**: Keep it simple and intuitive
5. **Documentation**: Comment complex code, update docs

### Code Style

**PHP:**
```php
// Use WordPress function naming
function ldmcs_function_name() {
    // Use tabs for indentation
    $variable = 'value';
    
    // Use Yoda conditions
    if ( 'value' === $variable ) {
        // Code here
    }
}
```

**JavaScript:**
```javascript
// Use camelCase
function functionName() {
    // Use tabs for indentation
    var variable = 'value';
    
    if ( variable === 'value' ) {
        // Code here
    }
}
```

### File Organization

```
learndash-master-to-client-sync/
├── assets/              # Frontend assets
│   ├── css/            # Stylesheets
│   └── js/             # JavaScript files
├── includes/           # PHP class files
│   └── class-*.php    # One class per file
├── languages/          # Translation files (if added)
└── *.php              # Main plugin file
```

### Naming Conventions

- **Prefix**: Use `ldmcs_` or `LDMCS_` for all functions, classes, and variables
- **Classes**: `LDMCS_Class_Name` (uppercase, underscores)
- **Functions**: `ldmcs_function_name()` (lowercase, underscores)
- **Hooks**: `ldmcs_hook_name` (lowercase, underscores)
- **Options**: `ldmcs_option_name` (lowercase, underscores)

## Testing

### Manual Testing Checklist

Before submitting a PR, test:

- [ ] Plugin activation/deactivation
- [ ] Settings save correctly
- [ ] Master site exposes API endpoints
- [ ] Client site connects to master
- [ ] Content syncs correctly
- [ ] Logs are created
- [ ] No PHP errors or warnings
- [ ] Works on WordPress 5.0+
- [ ] Works on PHP 7.2+
- [ ] Compatible with latest LearnDash version

### Testing Environments

Test in multiple environments:

1. **Shared hosting** (limited resources)
2. **VPS** (moderate resources)
3. **Local development** (Docker, Local, XAMPP)

### Security Testing

Check for:

- SQL injection vulnerabilities
- XSS vulnerabilities
- CSRF vulnerabilities
- Proper nonce verification
- Capability checks
- Input sanitization
- Output escaping

## Documentation

When making changes:

1. Update inline code comments
2. Update README.md if user-facing
3. Update INSTALL.md for setup changes
4. Add to CHANGELOG.md
5. Update API documentation

## Git Workflow

### Branch Naming

- `feature/description` - New features
- `fix/description` - Bug fixes
- `docs/description` - Documentation updates
- `refactor/description` - Code refactoring

### Commit Messages

Use clear, descriptive commit messages:

```
Add manual sync button to admin interface

- Created sync button in settings page
- Added AJAX handler for sync requests
- Implemented progress feedback
- Updated admin JavaScript
```

Format:
- First line: Brief summary (50 chars max)
- Blank line
- Detailed explanation (wrap at 72 chars)

### Version Control

- Commit logical units of work
- Don't commit generated files
- Don't commit debug code
- Test before committing

## Areas Needing Contribution

### High Priority

1. **Unit Tests**: PHP unit tests for core functionality
2. **Internationalization**: Translation support
3. **Documentation**: Video tutorials, more examples
4. **Performance**: Optimization for large datasets

### Medium Priority

1. **Webhooks**: Real-time sync triggers
2. **Advanced Filtering**: Sync specific courses/categories
3. **Sync Scheduling**: More granular control
4. **Rollback**: Ability to undo sync operations

### Low Priority

1. **UI Improvements**: Better admin interface
2. **Statistics**: Sync analytics and reports
3. **Notifications**: Email alerts for sync status
4. **Multi-master**: Support for multiple master sites

## Code Review Process

All contributions go through code review:

1. Automated checks (syntax, standards)
2. Manual review by maintainers
3. Testing verification
4. Security review
5. Documentation review

## Questions?

Feel free to:

- Open an issue for discussion
- Reach out to maintainers
- Check existing issues and PRs

## License

By contributing, you agree that your contributions will be licensed under the GPL v2 or later license.

## Recognition

Contributors will be:

- Listed in CONTRIBUTORS.md
- Mentioned in release notes
- Credited in commit history

Thank you for helping improve this plugin!
