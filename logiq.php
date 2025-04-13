<?php
/**
 * Plugin Name: LogIQ
 * Plugin URI: https://wordpress.org/plugins/logiq/
 * Description: Intelligent Debugging and Structured Logging for WordPress Developers
 * Version: 1.0.0
 * Author: A K M Elias
 * Author URI: https://akmelias.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: logiq
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LOGIQ_VERSION', '1.0.0');
define('LOGIQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LOGIQ_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Initialize the plugin
 */
function logiq_init() {
    // Include required files
    require_once LOGIQ_PLUGIN_DIR . 'includes/functions-logiq.php';
    require_once LOGIQ_PLUGIN_DIR . 'includes/class-logiq-admin.php';
    require_once LOGIQ_PLUGIN_DIR . 'includes/class-logiq-ajax.php';
    
    // Initialize classes
    new LogIQ_Admin();
    new LogIQ_Ajax();
}

// Initialize plugin
add_action('plugins_loaded', 'logiq_init');

// Activation Hook
register_activation_hook(__FILE__, 'logiq_activate');

/**
 * Plugin activation function
 * 
 * @return void
 */
function logiq_activate() {
    // Enable debug logging by default
    add_option('logiq_debug_enabled', true);
    
    // Create log directory if it doesn't exist
    $log_dir = WP_CONTENT_DIR . '/logiq-logs';
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }
}

// Uninstall Hook
register_uninstall_hook(__FILE__, 'logiq_uninstall');

/**
 * Plugin uninstall function
 * 
 * @return void
 */
function logiq_uninstall() {
    // Remove plugin options
    delete_option('logiq_debug_enabled');
    
    // Remove log directory and its contents
    $log_dir = WP_CONTENT_DIR . '/logiq-logs';
    if (file_exists($log_dir)) {
        array_map('unlink', glob("$log_dir/*.*"));
        rmdir($log_dir);
    }
} 