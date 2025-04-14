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
 * Filter sensitive data
 */
function logiq_filter_sensitive_data($data) {
    if (is_string($data)) {
        // Filter passwords
        $data = preg_replace('/password["\']?\s*[:=]\s*["\']([^"\']*)["\']/i', 'password=*****', $data);
        
        // Filter API keys
        $data = preg_replace('/api[_-]?key["\']?\s*[:=]\s*["\']([^"\']*)["\']/i', 'api_key=*****', $data);
        
        // Filter auth tokens
        $data = preg_replace('/auth[_-]?token["\']?\s*[:=]\s*["\']([^"\']*)["\']/i', 'auth_token=*****', $data);
    }
    return $data;
}

/**
 * Prepare data for logging by handling different types
 *
 * @param mixed $data The data to prepare
 * @return string Prepared data
 */
function logiq_prepare_data($data) {
    $data = logiq_filter_sensitive_data($data);
    switch (true) {
        case is_null($data):
            return 'NULL';
            
        case is_bool($data):
            return $data ? 'true' : 'false';
            
        case is_array($data):
            return print_r($data, true);
            
        case is_object($data):
            if ($data instanceof Exception || $data instanceof Error) {
                return sprintf(
                    "Exception: %s\nCode: %d\nFile: %s\nLine: %d\nTrace:\n%s",
                    $data->getMessage(),
                    $data->getCode(),
                    $data->getFile(),
                    $data->getLine(),
                    $data->getTraceAsString()
                );
            }
            return print_r($data, true);
            
        case is_resource($data):
            return sprintf('Resource [%s]', get_resource_type($data));
            
        default:
            return (string) $data;
    }
}

/**
 * Get the path to the log file
 *
 * @return string|false Returns the log file path or false if not found
 */
function logiq_get_log_file() {    
    // Check WP_DEBUG_LOG setting first
    if (defined('WP_DEBUG_LOG')) {
        if (is_string(WP_DEBUG_LOG)) {
            return WP_DEBUG_LOG;
        } elseif (WP_DEBUG_LOG === true) {
            $default_log = WP_CONTENT_DIR . '/debug.log';
            return $default_log;
        }
    }

    // Check PHP error_log setting
    $ini_error_log = ini_get('error_log');
    if (!empty($ini_error_log) && file_exists($ini_error_log)) {
        return $ini_error_log;
    }

    // Scan for log files in wp-content directory
    $logs = glob(WP_CONTENT_DIR . '/debug*.log');
    if (!empty($logs)) {
        // Sort by modification time, newest first
        usort($logs, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latest_log = $logs[0];
        return $latest_log;
    }

    return null;
}

/**
 * Write data to log file
 *
 * @param string $data The data to write
 * @return bool True if successful, false otherwise
 */
function logiq_write_to_log($data) {
    $log_file = logiq_get_log_file();
    
    if ($log_file === false) {
        return false;
    }
    
    
    // Create directory if it doesn't exist
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        if (!wp_mkdir_p($log_dir)) {
            return false;
        }
    }
    
    // Write to file with exclusive lock
    $result = @file_put_contents($log_file, $data, FILE_APPEND | LOCK_EX);
    
    if ($result === false) {
        return false;
    }
    
    return true;
}

/**
 * Rate limit for logging
 */
function logiq_check_rate_limit() {
    $rate_limit_key = 'logiq_rate_limit_' . get_current_user_id();
    $rate_limit = get_transient($rate_limit_key);
    
    if ($rate_limit === false) {
        set_transient($rate_limit_key, 1, MINUTE_IN_SECONDS);
        return true;
    }
    
    if ($rate_limit > 100) { // 100 logs per minute
        return false;
    }
    
    set_transient($rate_limit_key, $rate_limit + 1, MINUTE_IN_SECONDS);
    return true;
}

/**
 * Check and rotate log file if needed
 */
function logiq_check_log_rotation() {
    $log_file = logiq_get_log_file();
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if (file_exists($log_file) && filesize($log_file) > $max_size) {
        $backup_file = $log_file . '.' . date('Y-m-d-H-i-s') . '.bak';
        rename($log_file, $backup_file);
        
        // Keep only last 5 backup files
        $backup_files = glob($log_file . '.*.bak');
        if (count($backup_files) > 5) {
            array_map('unlink', array_slice($backup_files, 0, -5));
        }
    }
}

/**
 * Log a message with context
 *
 * @param mixed $data The data to log
 * @param string $level Log level (fatal, error, warning, info, debug, deprecated)
 * @param string $context Optional. Context identifier for the log entry
 * @param array $additional Optional. Additional context data
 * @return bool True if logged successfully, false otherwise
 */
function logiq_log($data, $level = LOGIQ_INFO, $context = '', $additional = array()) {
    static $logged_messages = array();
    
    // Create a unique key for this message
    $key = md5(serialize(array($data, $level, $context)));
    
    // Only log if we haven't seen this exact message before
    if (!isset($logged_messages[$key])) {
        $logged_messages[$key] = true;
        if (!logiq_check_rate_limit()) {
            return false;
        }

        // Check if debug logging is enabled
        if (!get_option('logiq_debug_enabled', true)) {
            return false;
        }

        // Get debug backtrace for file and line info
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        // Use provided file/line if available
        $file = isset($additional['file']) ? $additional['file'] : $trace['file'];
        $line = isset($additional['line']) ? $additional['line'] : $trace['line'];

        // Prepare log entry
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'level'     => $level,
            'context'   => $context,
            'file'      => str_replace(ABSPATH, '', $file),
            'line'      => $line,
            'user'      => get_current_user_id(),
            'data'      => logiq_prepare_data($data)
        );

        // Convert to JSON
        $log_line = wp_json_encode($log_entry) . PHP_EOL;

        // Write to log file
        logiq_check_log_rotation();
        return logiq_write_to_log($log_line);
    }
    return false;
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

/**
 * Register error handlers
 */
function logiq_register_error_handlers() {
    // For deprecated notices
    set_error_handler('logiq_error_handler', E_DEPRECATED | E_USER_DEPRECATED);
    
    // For fatal errors
    register_shutdown_function('logiq_fatal_error_handler');
}

/**
 * Error handler for deprecated notices
 */
function logiq_error_handler($errno, $errstr, $errfile, $errline) {
    // Only handle deprecated notices
    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
        logiq_log(
            $errstr,
            LOGIQ_DEPRECATED,
            'deprecated_notice',
            array(
                'file' => $errfile,
                'line' => $errline
            )
        );
    }
    // Don't stop PHP's error handling
    return false;
}

/**
 * Fatal error handler
 */
function logiq_fatal_error_handler() {
    $error = error_get_last();
    
    if ($error !== null) {
        $fatal_errors = array(
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
            E_RECOVERABLE_ERROR
        );

        if (in_array($error['type'], $fatal_errors)) {
            // Simple file logging without using WordPress functions
            $log_dir = dirname(__FILE__) . '/../logiq-logs';
            $log_file = $log_dir . '/fatal-errors.log';
            
            // Create directory if it doesn't exist
            if (!file_exists($log_dir)) {
                mkdir($log_dir, 0755, true);
            }

            // Format the error message
            $error_message = sprintf(
                "[%s] Fatal Error: %s in %s on line %d\n",
                date('Y-m-d H:i:s'),
                $error['message'],
                $error['file'],
                $error['line']
            );

            // Write directly to file without using WordPress functions
            error_log($error_message, 3, $log_file);
        }
    }
}

/**
 * Helper function to log errors with stack trace
 */
function logiq_log_error($error_message, $error_type = LOGIQ_ERROR) {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $trace_output = array();
    
    foreach ($trace as $t) {
        $trace_output[] = sprintf(
            '%s:%d - %s%s%s()',
            isset($t['file']) ? str_replace(ABSPATH, '', $t['file']) : 'unknown',
            isset($t['line']) ? $t['line'] : 0,
            isset($t['class']) ? $t['class'] : '',
            isset($t['type']) ? $t['type'] : '',
            $t['function']
        );
    }

    logiq_log(
        array(
            'message' => $error_message,
            'trace' => $trace_output
        ),
        $error_type,
        'error'
    );
}

/**
 * Fallback logging function that doesn't depend on WordPress functions
 */
function logiq_fallback_log($message) {
    $log_dir = dirname(__FILE__) . '/../logiq-logs';
    $log_file = $log_dir . '/fallback.log';
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_entry = sprintf(
        "[%s] %s\n",
        date('Y-m-d H:i:s'),
        $message
    );

    error_log($log_entry, 3, $log_file);
}

/**
 * Test all log levels and features
 */
function logiq_test_all_levels() {
    // Test INFO level
    logiq_log(
        "This is a test info message",
        LOGIQ_INFO,
        'test_info'
    );

    // Test DEBUG level
    logiq_log(
        array('debug' => 'test', 'data' => array(1, 2, 3)),
        LOGIQ_DEBUG,
        'test_debug'
    );

    // Test WARNING level
    logiq_log(
        "This is a test warning",
        LOGIQ_WARNING,
        'test_warning'
    );

    // Test ERROR level
    logiq_log(
        "This is a test error",
        LOGIQ_ERROR,
        'test_error'
    );

    // Test DEPRECATED level
    trigger_error(
        "This is a test deprecated notice",
        E_USER_DEPRECATED
    );

    // Test FATAL level (simulated)
    logiq_log(
        "This is a simulated fatal error",
        LOGIQ_FATAL,
        'test_fatal'
    );

    // Test Exception logging
    try {
        throw new Exception("Test exception");
    } catch (Exception $e) {
        logiq_log_exception($e, 'test_exception');
    }

    // Test different data types
    logiq_log(null, LOGIQ_INFO, 'test_null');
    logiq_log(true, LOGIQ_INFO, 'test_boolean');
    logiq_log(array('test' => 'array'), LOGIQ_INFO, 'test_array');
    logiq_log(new stdClass(), LOGIQ_INFO, 'test_object');
    
    // Test error with stack trace
    logiq_log_error("Test error with stack trace");

    return "Test logs generated successfully. Please check the log viewer.";
}

function logiq_check_system_compatibility() {
    $issues = array();
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $issues[] = sprintf(
            __('LogIQ requires PHP 7.4 or higher. Your current PHP version is %s.', 'logiq'),
            PHP_VERSION
        );
    }
    
    // Check WordPress version
    global $wp_version;
    if (version_compare($wp_version, '5.8', '<')) {
        $issues[] = sprintf(
            __('LogIQ requires WordPress 5.8 or higher. Your current WordPress version is %s.', 'logiq'),
            $wp_version
        );
    }
    
    // Check log directory permissions
    $log_dir = dirname(__FILE__) . '/logiq-logs';
    if (file_exists($log_dir) && !is_writable($log_dir)) {
        $issues[] = sprintf(
            __('Log directory %s is not writable. Please check permissions.', 'logiq'),
            esc_html($log_dir)
        );
    }
    
    return $issues;
} 