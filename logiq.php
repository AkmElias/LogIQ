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

// Log levels
define('LOGIQ_FATAL', 'fatal');
define('LOGIQ_ERROR', 'error');
define('LOGIQ_WARNING', 'warning');
define('LOGIQ_INFO', 'info');
define('LOGIQ_DEBUG', 'debug');
define('LOGIQ_DEPRECATED', 'deprecated');

/**
 * Initialize the plugin
 */
function logiq_init() {
    // Include required files first
    require_once LOGIQ_PLUGIN_DIR . 'includes/functions-logiq.php';
    require_once LOGIQ_PLUGIN_DIR . 'includes/class-logiq-admin.php';
    require_once LOGIQ_PLUGIN_DIR . 'includes/class-logiq-ajax.php';
    require_once LOGIQ_PLUGIN_DIR . 'includes/class-logiq-security.php';
    
    // Now register the error handlers after functions are loaded
    if (get_option('logiq_debug_enabled', true)) {
        // Remove existing handlers properly
        if (has_action('shutdown', 'logiq_fatal_error_handler')) {
            remove_action('shutdown', 'logiq_fatal_error_handler');
        }
        if (has_action('error_handler', 'logiq_error_handler')) {
            remove_action('error_handler', 'logiq_error_handler');
        }
        
        register_shutdown_function('logiq_fatal_error_handler');
        set_error_handler('logiq_error_handler', E_DEPRECATED | E_USER_DEPRECATED);
    }
    
    // Initialize classes with error handling
    try {
        new LogIQ_Admin();
        new LogIQ_Ajax();
        new LogIQ_Security();
    } catch (Exception $e) {
        error_log('LogIQ Plugin Error: ' . $e->getMessage());
    }
}

// Initialize plugin
add_action('plugins_loaded', 'logiq_init');

/**
 * Load plugin textdomain
 */
function logiq_load_textdomain() {
    load_plugin_textdomain(
        'logiq',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('init', 'logiq_load_textdomain');

// Activation Hook
register_activation_hook(__FILE__, 'logiq_activate');

/**
 * Plugin activation function
 */
function logiq_activate() {
    // Create log directory with proper error handling
    $log_dir = dirname(__FILE__) . '/logiq-logs';
    
    if (!file_exists($log_dir)) {
        // Check parent directory permissions
        $parent_dir = dirname($log_dir);
        if (!is_writable($parent_dir)) {
            wp_die(
                sprintf(
                    __('Cannot create log directory. Parent directory %s is not writable.', 'logiq'),
                    esc_html($parent_dir)
                )
            );
        }
        
        // Try to create directory
        if (!mkdir($log_dir, 0755, true)) {
            wp_die(
                sprintf(
                    __('Failed to create log directory %s. Please check permissions.', 'logiq'),
                    esc_html($log_dir)
                )
            );
        }
        
        // Create .htaccess to protect logs
        $htaccess = $log_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "Order deny,allow\nDeny from all";
            @file_put_contents($htaccess, $content);
        }
    }
    
    // Enable debug logging by default
    add_option('logiq_debug_enabled', true);
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

// Add this after the logiq_init() function
add_action('admin_init', function() {
    if (isset($_GET['logiq_test']) && current_user_can('manage_options')) {
        $result = logiq_test_all_levels();
        add_action('admin_notices', function() use ($result) {
            echo '<div class="notice notice-success"><p>' . esc_html($result) . '</p></div>';
        });
    }
});

// Add this after your existing code
function logiq_run_comprehensive_tests() {
    // 1. Info Logs
    logiq_log("Regular information message", LOGIQ_INFO, 'info_test');
    logiq_log(["user" => "admin", "action" => "login"], LOGIQ_INFO, 'info_array');

    // 2. Debug Logs
    logiq_log("Debug message with variable data", LOGIQ_DEBUG, 'debug_test');
    logiq_log(
        [
            'request' => $_SERVER['REQUEST_URI'],
            'method' => $_SERVER['REQUEST_METHOD']
        ],
        LOGIQ_DEBUG,
        'debug_server'
    );

    // 3. Warning Logs
    logiq_log("Warning: Resource usage high", LOGIQ_WARNING, 'warning_test');
    logiq_log(
        [
            'memory_usage' => memory_get_usage(),
            'peak_memory' => memory_get_peak_usage()
        ],
        LOGIQ_WARNING,
        'warning_memory'
    );

    // 4. Error Logs
    logiq_log("Database connection failed", LOGIQ_ERROR, 'error_test');
    logiq_log_error("Custom error with stack trace");

    // 5. Fatal Error (Simulated)
    logiq_log("Simulated fatal error", LOGIQ_FATAL, 'fatal_test');

    // 6. Deprecated Notice
    trigger_error("Using deprecated function", E_USER_DEPRECATED);

    // 7. Exception Logging
    try {
        throw new Exception("Test exception message");
    } catch (Exception $e) {
        logiq_log_exception($e, 'exception_test');
    }

    // 8. Different Data Types
    logiq_log(null, LOGIQ_INFO, 'null_test');
    logiq_log(true, LOGIQ_INFO, 'boolean_test');
    logiq_log(['a' => 1, 'b' => 2], LOGIQ_INFO, 'array_test');
    logiq_log((object)['name' => 'Test Object'], LOGIQ_INFO, 'object_test');
    logiq_log(3.14159, LOGIQ_INFO, 'number_test');

    // 9. Complex Data Structure
    logiq_log([
        'user' => [
            'id' => 1,
            'name' => 'Admin',
            'roles' => ['administrator'],
            'meta' => (object)['last_login' => time()]
        ],
        'system' => [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]
    ], LOGIQ_DEBUG, 'complex_data');

    return "Comprehensive test logs generated successfully!";
}

// Add this to run the tests via URL parameter
add_action('admin_init', function() {
    if (isset($_GET['logiq_comprehensive_test']) && current_user_can('manage_options')) {
        $result = logiq_run_comprehensive_tests();
        add_action('admin_notices', function() use ($result) {
            echo '<div class="notice notice-success"><p>' . esc_html($result) . '</p></div>';
        });
    }
}); 