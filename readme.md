 === LogIQ ===
Contributors: akmelias
Tags: debug, log viewer, development, error log, log management
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A powerful and user-friendly debug log viewer for WordPress with editor integration.

== Description ==

LogIQ is a powerful debug log viewer that makes WordPress debugging easier and more efficient. With its intuitive interface and smart features, you can quickly identify and resolve issues in your WordPress site.

= Key Features =

* **Real-time Log Monitoring**: View WordPress debug logs directly from your admin dashboard
* **Complex Data Handling**: Beautifully formats and displays complex data types like JSON and arrays with proper indentation and syntax highlighting
* **Smart Log Parsing**: Automatically categorizes logs into different levels (Fatal, Error, Warning, Notice, Deprecated, Info, Debug)
* **Advanced Filtering**: Filter logs by level to focus on specific types of issues
* **Clickable File Links**: Click on file paths in log entries to open them directly in your code editor
* **Editor Integration**: 
    * Supports Visual Studio Code
    * Supports Sublime Text
    * Supports PhpStorm
    * Falls back to file:// protocol if no supported editor is found
* **Clean Interface**: Modern, responsive design with color-coded log levels
* **Pagination**: Navigate through large log files with ease
* **Security**: Built with WordPress security best practices

= Editor Integration =

LogIQ makes debugging easier by providing clickable file links in your log entries. When you click a file link:

1. The file opens in your default code editor
2. The cursor moves to the specific line mentioned in the log
3. Supported editors (VS Code, Sublime Text, PhpStorm) open directly to the relevant line
4. No configuration needed - works automatically with your installed editor

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* WP_DEBUG and WP_DEBUG_LOG must be enabled in wp-config.php

== Installation ==

1. Upload the `logiq` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools > LogIQ to start viewing your debug logs

== Usage ==

= Viewing Logs =
1. Navigate to Tools > LogIQ in your WordPress admin
2. Use the filter buttons to show specific log levels
3. Click the refresh button to see new logs
4. Use pagination to navigate through older logs

= Using File Links =
1. Each log entry that includes a file reference will show a clickable link
2. Click the link to open the file in your default code editor
3. The file will open at the specific line number mentioned in the log
4. Supported editors will open directly to the relevant line

= Editor Configuration =
The plugin automatically detects installed editors in the following order:
1. Visual Studio Code
2. Sublime Text
3. PhpStorm
4. System default text editor

No additional configuration is needed - the plugin will automatically use your installed editor.

== Frequently Asked Questions ==

= Which code editors are supported? =

LogIQ supports Visual Studio Code, Sublime Text, and PhpStorm. If none of these are installed, it will fall back to your system's default text editor.

= Do I need to configure my editor? =

No configuration is needed. LogIQ automatically detects your installed editors and uses the appropriate protocol to open files.

= What if I don't have any supported editors installed? =

The plugin will fall back to using the file:// protocol, which will open the file in your system's default text editor.

= How does the editor integration work? =

When you click a file link in a log entry, LogIQ detects your installed editors and constructs the appropriate URL to open the file. For supported editors, it will open directly to the specific line number mentioned in the log.

= How does LogIQ handle complex data types? =

LogIQ automatically detects and formats complex data types like JSON and arrays. When these data types are logged, they are displayed with proper indentation, syntax highlighting, and collapsible sections for better readability. This makes it easier to debug and understand complex data structures in your logs.

== Screenshots ==

1. Main log viewer interface with color-coded log levels
2. Log filtering in action
3. Clickable file links in log entries
4. Editor integration demonstration

== Changelog ==

= 1.0.0 =
* Initial release
* Real-time log viewing with automatic updates
* Smart log parsing and categorization
* Advanced filtering by log level
* Editor integration with clickable file links
* Support for VS Code, Sublime Text, and PhpStorm
* Security enhancements and responsive design

== Upgrade Notice ==

= 1.0.0 =
Initial release with editor integration and clickable file links. 