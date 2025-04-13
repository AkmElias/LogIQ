# LogIQ – Development Task Checklist

## �� Initial Setup

- [x] Create plugin header (`logiq.php`)
- [x] Register activation hook → enable default logging
- [x] Create default option: `logiq_debug_enabled`
- [x] Register uninstall hook to clean up options

## 📜 Logging Logic

- [ ] Create `logiq_log()` function
- [ ] Serialize and log arrays, objects, exceptions
- [ ] Add context: timestamp, file, line, hook, user
- [ ] Log format: structured JSON
- [ ] Write to `wp-content/logiq-debug.log`
- [ ] Add `logiq_log_level()` or `logiq_log_type()` later

## ⚙️ Settings & Config

- [x] Add admin menu under Tools
- [x] Create checkbox toggle for debug mode
- [x] Save using `update_option()`

## 🖥️ Log Viewer Admin Page

- [x] Display logs in readable table format
- [x] Format JSON values nicely
- [x] Show time, context, user, etc.
- [x] Use colors/icons for log types

## 🔁 AJAX Functionality

- [x] Button to refresh log viewer via AJAX
- [x] Button to clear logs (truncate file)
- [x] Handle with `wp_ajax_` and nonces

## 🛡️ Security

- [X] Restrict access to admins only
- [X] Escape output in templates
- [X] Sanitize inputs in AJAX
- [X] Use `check_ajax_referer()`

## 🌍 Internationalization

- [X] Load plugin textdomain
- [X] Wrap strings with `__()` and `_e()`
- [X] Generate `.pot` file

## 🧪 Testing

- [ ] Test logging for all value types
- [ ] Test error edge cases (large data, invalid formats)
- [ ] Test viewer, refresh, clear actions
- [ ] Test toggling settings
- [ ] Test security (nonces, access control)

## 📝 WordPress.org Submission

- [ ] Create detailed readme.txt with:
  - Plugin name, description, and tags
  - Installation instructions
  - Frequently Asked Questions
  - Screenshots section
  - Changelog
  - Upgrade notice
- [ ] Create plugin banner (772x250px)
  - [ ] Create plugin icon (128x128px)
  - [ ] Create screenshots (880x660px)
- [ ] Ensure plugin meets WordPress.org requirements:
  - [ ] No premium/paid features
  - [ ] No external links to commercial sites
  - [ ] No spam or promotional content
  - [ ] Proper license (GPLv2 or later)
- [ ] Prepare for SVN submission:
  - [ ] Create stable tag
  - [ ] Version number format (x.x.x)
  - [ ] Update trunk and tags
- [ ] Documentation:
  - [ ] Add inline code documentation
  - [ ] Create user documentation
  - [ ] Add developer documentation
- [ ] Review and fix:
  - [ ] WordPress coding standards
  - [ ] PHP compatibility
  - [ ] WordPress version compatibility
  - [ ] Security best practices

## 📦 Final Prep

- [ ] Add banner/icon
- [ ] Create `readme.txt` for WP.org
- [ ] License and credits
- [ ] Optional: GitHub or WP submission