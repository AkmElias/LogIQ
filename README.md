# LogIQ - WordPress Debug Log Viewer

LogIQ is a powerful WordPress plugin that provides a user-friendly interface for viewing, managing, and analyzing WordPress debug logs. It integrates seamlessly with WordPress's built-in logging system and offers advanced filtering and visualization capabilities.

## Features

### Log Viewing
- Real-time log viewing with automatic updates
- Clean, organized display of log entries with timestamps
- Syntax highlighting for different log types
- Pagination support for handling large log files
- Responsive design that works on all screen sizes

### Log Filtering
- Filter logs by severity level:
  - Fatal Errors
  - Errors
  - Warnings
  - Notices
  - Deprecated
  - Debug
  - Info
- Context-aware log categorization
- Smart parsing of PHP errors and WordPress notices

### Security
- Role-based access control (requires manage_options capability)
- Secure log file handling
- Input sanitization and validation
- Protection against directory traversal
- Sensitive data filtering (passwords, API keys, auth tokens)

### Log Management
- Clear logs with confirmation
- Log file information display (size, modification time)
- Automatic log file detection
- Support for custom log file locations

### Debug Information
- File and line number tracking for errors
- Stack traces for exceptions
- Context preservation for debugging
- Detailed debug information panel

## Requirements
- WordPress 5.8 or higher
- PHP 7.4 or higher
- WP_DEBUG must be enabled
- Write permissions for the log directory

## Installation

1. Upload the `logiq` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Tools > LogIQ to view your logs

## Configuration

### Basic Setup
1. Ensure `WP_DEBUG` is set to `true` in your `wp-config.php`
2. Set `WP_DEBUG_LOG` to `true` to enable logging
3. Access the LogIQ interface from the WordPress admin menu under Tools

### Advanced Configuration
The plugin automatically detects log files in the following order:
1. Custom path specified in `WP_DEBUG_LOG`
2. Default WordPress debug.log in wp-content directory
3. PHP error_log location
4. Any debug*.log files in wp-content directory

## Usage

### Viewing Logs
1. Navigate to Tools > LogIQ in your WordPress admin panel
2. Logs are displayed with the most recent entries at the top
3. Use the filter buttons to show specific log types
4. Click on log entries to expand detailed information

### Managing Logs
- Use the "Clear Logs" button to empty the log file
- Confirmation is required before logs are cleared
- File permissions are automatically checked

### Security Considerations
- Only users with `manage_options` capability can access logs
- Sensitive data is automatically filtered from log display
- All user inputs are sanitized and validated

## Support

For bug reports and feature requests, please use the [GitHub issue tracker](https://github.com/yourusername/logiq/issues).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Your Name/Company]

## Changelog

### 1.0.0
- Initial release
- Real-time log viewing
- Log filtering by type
- Security enhancements
- Responsive design
- WordPress debug log integration 