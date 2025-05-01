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
        /* translators: 1: PHP version */
        $issues[] = sprintf(__('LogIQ requires PHP 7.4 or higher. Your current PHP version is %s.', 'LogIQ'),PHP_VERSION);
    }
    
    // Check WordPress version
    global $wp_version;
    if (version_compare($wp_version, '5.8', '<')) {
        /* translators: 1: WordPress version */
        $issues[] = sprintf(__('LogIQ requires WordPress 5.8 or higher. Your current WordPress version is %s.', 'LogIQ'),$wp_version);
    }
    
    return $issues;
}

/**
 * Get the default editor protocol and path
 * 
 * @return array Array containing editor protocol and whether it's installed
 */
function logiq_get_editor_info() {
    $editor_info = array(
        'protocol' => 'file://',  // Default fallback
        'is_installed' => false
    );

    // Check for VS Code
    $vscode_paths = array(
        'Darwin' => '/Applications/Visual Studio Code.app',  // macOS
        'WINNT' => 'C:\\Program Files\\Microsoft VS Code\\Code.exe',  // Windows 64-bit
        'Linux' => '/usr/bin/code'  // Linux
    );

    $os = PHP_OS;
    if (isset($vscode_paths[$os]) && file_exists($vscode_paths[$os])) {
        $editor_info['protocol'] = 'vscode://file';
        $editor_info['is_installed'] = true;
        return $editor_info;
    }

    // Check for Sublime Text
    $sublime_paths = array(
        'Darwin' => '/Applications/Sublime Text.app',
        'WINNT' => 'C:\\Program Files\\Sublime Text\\sublime_text.exe',
        'Linux' => '/usr/bin/subl'
    );

    if (isset($sublime_paths[$os]) && file_exists($sublime_paths[$os])) {
        $editor_info['protocol'] = 'subl://open';
        $editor_info['is_installed'] = true;
        return $editor_info;
    }

    // Check for PhpStorm
    $phpstorm_paths = array(
        'Darwin' => '/Applications/PhpStorm.app',
        'WINNT' => 'C:\\Program Files\\JetBrains\\PhpStorm*',
        'Linux' => '/usr/bin/phpstorm'
    );

    if (isset($phpstorm_paths[$os])) {
        $path = $phpstorm_paths[$os];
        if ($os === 'WINNT') {
            // Use glob for Windows PhpStorm's versioned directories
            $matches = glob($path);
            if (!empty($matches)) {
                $editor_info['protocol'] = 'phpstorm://open';
                $editor_info['is_installed'] = true;
                return $editor_info;
            }
        } elseif (file_exists($path)) {
            $editor_info['protocol'] = 'phpstorm://open';
            $editor_info['is_installed'] = true;
            return $editor_info;
        }
    }

    return $editor_info;
}

/**
 * Construct editor URL for a file
 * 
 * @param string $file_path File path
 * @param int $line Line number
 * @return string Editor URL
 */
function logiq_construct_editor_url($file_path, $line = 1) {
    $editor_info = logiq_get_editor_info();
    $protocol = $editor_info['protocol'];

    // Format the file path based on OS
    if (PHP_OS === 'WINNT') {
        $file_path = str_replace('/', '\\', $file_path);
    } else {
        $file_path = str_replace('\\', '/', $file_path);
    }

    // Ensure absolute path
    if (defined('ABSPATH') && strpos($file_path, ABSPATH) === false) {
        $file_path = ABSPATH . $file_path;
    }

    // Construct URL based on editor protocol
    switch ($protocol) {
        case 'vscode://file':
            return $protocol . '/' . $file_path . ':' . $line;
            
        case 'subl://open':
        case 'phpstorm://open':
            return $protocol . '?file=' . urlencode($file_path) . '&line=' . $line;
            
        default:
            return 'file://' . $file_path;
    }
} 