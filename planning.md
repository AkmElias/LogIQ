# LogIQ Debugging Plugin – Planning Document

## 🔹 Plugin Name
**LogIQ** – Intelligent Debugging and Structured Logging for WordPress Developers

## 🎯 Purpose
A lightweight, developer-focused plugin that:
- Logs all value types (string, array, object, etc.)
- Adds semantic context (timestamp, file, line, hook, user)
- Offers a clean, secure admin UI for viewing, refreshing, and clearing logs
- Complies with WordPress coding and security standards

## 🧩 Directory Structure

```
logiq/
├── logiq.php
├── includes/
│   ├── class-logiq-logger.php
│   ├── class-logiq-admin.php
│   └── functions-logiq.php
├── templates/
│   └── admin-log-viewer.php
├── assets/
│   ├── admin.css
│   └── admin.js
├── languages/
│   └── logiq.pot
├── uninstall.php
└── readme.txt
```

## 🔧 Core Features

- Structured logging for all PHP data types
- Debug log file: `wp-content/logiq-debug.log`
- Auto-enable on plugin activation
- Manual toggle via settings page
- Admin log viewer (Tools > LogIQ)
- Refresh logs via AJAX
- Clear logs via AJAX
- Strict security: nonces, caps, sanitization
- Optional: WP CLI support, log levels, database storage

## 🔐 Security Goals

- Sanitize and escape everything
- Nonces for all state-changing actions
- Role checks (`manage_options`) for admin views
- Hide or redact sensitive data
- Lock down log file via `.htaccess` or block direct access

## 🌱 Future Add-ons

- Log levels: info, debug, warning, error
- Filter logs by type or content
- Export logs (CSV/JSON)
- Log rotation / size limits
- Integration with WP REST API
- WP-CLI support
- Logging to database (optional)