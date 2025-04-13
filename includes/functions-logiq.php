<?php
/**
 * LogIQ logging functions
 *
 * @package LogIQ
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the WordPress debug log file path
 *
 * @return string
 */
function logiq_get_log_file() {
    // Check for custom debug log path
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        if (is_string(WP_DEBUG_LOG)) {
            return WP_DEBUG_LOG;
        }
        return WP_CONTENT_DIR . '/debug.log';
    }

    // Check for default WordPress debug log
    $default_log = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($default_log)) {
        return $default_log;
    }

    // Check for dynamic debug log files
    $content_dir = WP_CONTENT_DIR;
    $files = glob($content_dir . '/debug-*.log');
    if (!empty($files)) {
        // Sort by modification time, newest first
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        return $files[0];
    }

    // Fallback to our custom log file
    $custom_log = WP_CONTENT_DIR . '/logiq-logs/debug.log';
    if (!file_exists(dirname($custom_log))) {
        wp_mkdir_p(dirname($custom_log));
    }
    return $custom_log;
}

/**
 * Main logging function
 *
 * @param mixed  $data     The data to log
 * @param string $context  Additional context information
 * @return bool           Whether the log was written successfully
 */
function logiq_log($data, $context = '') {
    // Check if logging is enabled
    if (!get_option('logiq_debug_enabled', false)) {
        return false;
    }

    // Prepare log entry
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'data'      => $data,
        'context'   => $context,
        'file'      => '',
        'line'      => '',
        'hook'      => current_filter(),
        'user'      => get_current_user_id(),
    );

    // Get file and line information
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    if (!empty($backtrace[0])) {
        $log_entry['file'] = str_replace(ABSPATH, '', $backtrace[0]['file']);
        $log_entry['line'] = $backtrace[0]['line'];
    }

    // Serialize data if it's an array or object
    if (is_array($data) || is_object($data)) {
        $log_entry['data'] = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    // Convert to JSON
    $json_entry = json_encode($log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Get log file path
    $log_file = logiq_get_log_file();

    // Write to log file
    $result = file_put_contents(
        $log_file,
        $json_entry . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    return $result !== false;
}

/**
 * Helper function to log exceptions
 *
 * @param Exception $exception The exception to log
 * @param string    $context   Additional context information
 * @return bool               Whether the log was written successfully
 */
function logiq_log_exception(Exception $exception, $context = '') {
    $exception_data = array(
        'message' => $exception->getMessage(),
        'code'    => $exception->getCode(),
        'file'    => str_replace(ABSPATH, '', $exception->getFile()),
        'line'    => $exception->getLine(),
        'trace'   => $exception->getTraceAsString(),
    );

    return logiq_log($exception_data, $context);
}

// Add this function temporarily for testing
function logiq_generate_test_logs() {
    for ($i = 1; $i <= 50; $i++) {
        logiq_log("Test log entry #{$i}", "TEST");
    }
}

// Call this once from your plugin activation or manually to generate test data
// add_action('init', 'logiq_generate_test_logs'); 