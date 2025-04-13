# LogIQ - Intelligent Debug Logging for WordPress

![LogIQ Banner](/.wordpress-org/banner-772x250.png)

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/logiq)](https://wordpress.org/plugins/logiq/)
[![WordPress Tested Up To](https://img.shields.io/wordpress/v/logiq)](https://wordpress.org/plugins/logiq/)
[![PHP Tested Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://wordpress.org/plugins/logiq/)
[![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

LogIQ is a powerful, developer-friendly logging solution for WordPress that provides structured logging with an elegant interface.

## 🚀 Features

- **Beautiful Interface**
  - Tabbed navigation for different log types
  - Color-coded log levels
  - Real-time AJAX updates
  - Responsive design

- **Log Types Support**
  - Fatal Errors
  - Regular Errors
  - Warnings
  - Deprecated Notices
  - Info Messages
  - Debug Data

- **Developer Tools**
  - Stack traces for errors
  - JSON structured logging
  - Context tracking
  - File and line information
  - User tracking

- **Security Features**
  - Rate limiting
  - Data sanitization
  - Access control
  - Secure file storage
  - Log rotation

## 📦 Installation

1. Download the latest release
2. Upload to `/wp-content/plugins/`
3. Activate through WordPress admin
4. Go to Tools > LogIQ Debug

## 🔧 Usage

### Basic Logging

```php
// Log any data type
logiq_log($data, LOGIQ_INFO, 'context');

// Log arrays
logiq_log([
    'user' => wp_get_current_user(),
    'action' => 'login',
    'timestamp' => time()
], LOGIQ_INFO);

// Log errors
logiq_log_error('Database connection failed');

// Log exceptions
try {
    // Your code
} catch (Exception $e) {
    logiq_log_exception($e, 'custom_context');
}
```

### Log Levels

```php
// Available log levels
logiq_log($data, LOGIQ_FATAL);     // Fatal errors
logiq_log($data, LOGIQ_ERROR);     // Regular errors
logiq_log($data, LOGIQ_WARNING);   // Warnings
logiq_log($data, LOGIQ_INFO);      // Information
logiq_log($data, LOGIQ_DEBUG);     // Debug data
logiq_log($data, LOGIQ_DEPRECATED);// Deprecated notices
```

## 🛡️ Security

LogIQ implements several security measures:

- Rate limiting to prevent log flooding
- Proper data sanitization
- Admin-only access control
- Secure log file storage
- Automatic log rotation
- XSS prevention

## 🌐 Internationalization

LogIQ is fully translatable:

- Uses WordPress i18n
- POT file included
- RTL support
- Translation ready

## 🔄 API

### Functions

```php
logiq_log($data, $level = LOGIQ_INFO, $context = '');
logiq_log_error($message, $context = '');
logiq_log_exception($exception, $context = '');
logiq_get_log_file();
logiq_clear_logs();
```

### Hooks

```php
// Filters
add_filter('logiq_log_data', 'your_function');
add_filter('logiq_log_level', 'your_function');
add_filter('logiq_log_context', 'your_function');

// Actions
add_action('logiq_before_log', 'your_function');
add_action('logiq_after_log', 'your_function');
add_action('logiq_logs_cleared', 'your_function');
```

## 🤝 Contributing

Contributions are welcome! Please read our [Contributing Guidelines](CONTRIBUTING.md).

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Submit a pull request

## 📝 License

LogIQ is licensed under the GPL v2 or later.

See [LICENSE](LICENSE) for more information.

## 👥 Credits

Developed by [A K M Elias](https://akmelias.com)

## 📞 Support

- [WordPress.org Plugin Page](https://wordpress.org/plugins/logiq/)
- [GitHub Issues](https://github.com/yourusername/logiq/issues)
- [Documentation](https://github.com/yourusername/logiq/wiki)

## 🔄 Changelog

See [CHANGELOG.md](CHANGELOG.md) for all version updates. 