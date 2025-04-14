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
 * Filter sensitive data from log entries
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
 * Prepare data for display by handling different types
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
            return WP_CONTENT_DIR . '/debug.log';
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
        return $logs[0];
    }

    return null;
}

/**
 * Check system compatibility
 */
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
    
    return $issues;
} 