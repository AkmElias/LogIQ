=== LogIQ - Intelligent Debug Logging ===
Contributors: akmelias
Tags: debug, logging, development, error logging, debugging
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Intelligent debugging and structured logging for WordPress developers with an elegant interface and powerful features.

== Description ==

LogIQ provides developers with a powerful, user-friendly logging system for WordPress. Track errors, warnings, deprecated notices, and debug information with ease.

= Features =

* Beautiful, tabbed interface for different log types
* Real-time log filtering and pagination
* Structured JSON logging for all data types
* Stack traces for errors and exceptions
* Color-coded log levels
* AJAX-powered refresh and clear functionality
* Secure logging with proper sanitization
* Rate limiting to prevent log flooding
* Automatic log rotation
* Translation-ready

= Log Types =

* Fatal Errors
* Regular Errors
* Warnings
* Deprecated Notices
* Info Messages
* Debug Data

= Developer Friendly =

```php
// Log any type of data
logiq_log($data, LOGIQ_INFO, 'context');

// Log errors with stack traces
logiq_log_error('Error message');

// Log exceptions
try {
    // Your code
} catch (Exception $e) {
    logiq_log_exception($e);
}
```

== Installation ==

1. Upload the `logiq` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools > LogIQ Debug to view and manage logs

== Frequently Asked Questions ==

= Where are the logs stored? =

Logs are stored in `/wp-content/logiq-logs/debug.log` with proper security measures to prevent direct access.

= How do I enable/disable logging? =

Go to Tools > LogIQ Debug and use the toggle switch at the top of the page.

= Is it safe to use in production? =

Yes, LogIQ implements several security measures including:
* Rate limiting
* Data sanitization
* Access control
* Secure file storage
* Log rotation

== Screenshots ==

1. Main logging interface with tabbed navigation
2. Error log display with stack traces
3. Settings panel
4. Real-time log filtering

== Changelog ==

= 1.0.0 - 2025-04-13 =
* Initial release
* Added beautiful tabbed interface for different log types
* Added real-time AJAX-powered log viewer
* Added color-coded log levels (Fatal, Error, Warning, Info, Debug, Deprecated)
* Added stack traces for errors and exceptions
* Added JSON structured logging
* Added context tracking with file and line information
* Added user action tracking
* Implemented rate limiting to prevent log flooding
* Added secure log file storage with .htaccess protection
* Added automatic log rotation
* Added log filtering by type
* Added pagination for large log files
* Added AJAX-powered refresh and clear functionality
* Added full internationalization support
* Added developer API with hooks and filters
* Implemented security features (XSS prevention, data sanitization)
* Added responsive admin interface
* Added documentation and code examples

== Upgrade Notice ==

= 1.0.0 =
Initial release of LogIQ with comprehensive logging features, beautiful interface, and robust security measures. 