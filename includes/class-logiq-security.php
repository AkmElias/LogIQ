<?php
/**
 * LogIQ Security Class
 *
 * @package LogIQ
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class LogIQ_Security {
    /**
     * Constructor
     */
    public function __construct() {
        // Protect log directory
        add_action('init', array($this, 'protect_logs_directory'));
        
        // Add capability checks
        add_action('admin_init', array($this, 'check_capabilities'));
    }

    /**
     * Protect logs directory with .htaccess
     */
    public function protect_logs_directory() {
        $log_dir = WP_CONTENT_DIR . '/logiq-logs';
        $htaccess_file = $log_dir . '/.htaccess';

        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            @file_put_contents($htaccess_file, $htaccess_content);
        }

        // Also add index.php to prevent directory listing
        $index_file = $log_dir . '/index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '<?php // Silence is golden');
        }
    }

    /**
     * Check if current user has required capabilities
     */
    public function check_capabilities() {
        if ($this->is_logiq_page() && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'logiq'));
        }
    }

    /**
     * Check if current page is a LogIQ admin page
     *
     * @return boolean
     */
    private function is_logiq_page() {
        if (!is_admin()) {
            return false;
        }
        
        $screen = get_current_screen();
        if (!$screen || !is_object($screen)) {
            return false;
        }

        // List of LogIQ admin pages
        $logiq_pages = array(
            'tools_page_logiq-debug',    // Main debug page
            'admin_page_logiq-settings', // Settings page
            'admin_page_logiq-logs'      // Logs page
        );

        return isset($screen->id) && in_array($screen->id, $logiq_pages, true);
    }

    /**
     * Verify admin AJAX requests
     */
    public static function verify_admin_ajax() {
        if (!check_ajax_referer('logiq_admin_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid security token.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }
    }

    /**
     * Sanitize log data for output
     */
    public static function sanitize_log_data($data) {
        if (is_array($data)) {
            return array_map([__CLASS__, 'sanitize_log_data'], $data);
        }
        
        // Check for JSON string
        if (is_string($data) && self::is_json($data)) {
            return '<pre>' . esc_html($data) . '</pre>';
        }
        
        // Handle potential HTML in error messages
        return esc_html($data);
    }

    /**
     * Check if string is valid JSON
     */
    private static function is_json($string) {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Sanitize log level
     *
     * @param string|null $level The log level to sanitize
     * @return string Sanitized log level
     */
    public static function sanitize_log_level($level) {
        // Ensure $level is a string
        $level = is_string($level) ? $level : '';
        
        // Valid log levels
        $valid_levels = array(
            'all',
            'fatal',
            'error',
            'warning',
            'info',
            'debug',
            'deprecated'
        );

        // Check if the level contains any valid level as a substring
        foreach ($valid_levels as $valid_level) {
            if (strpos(strtolower($level), $valid_level) !== false) {
                return $valid_level;
            }
        }

        // Default to 'all' if no valid level found
        return 'all';
    }

    /**
     * Sanitize file path
     *
     * @param string $path The file path to sanitize
     * @return string Sanitized path
     */
    public static function sanitize_file_path($path) {
        // Remove any directory traversal attempts
        $path = str_replace(array('../', '..\\'), '', $path);
        
        // Remove any null bytes
        $path = str_replace("\0", '', $path);
        
        // Remove any control characters
        $path = preg_replace('/[\x00-\x1F\x7F]/', '', $path);
        
        return $path;
    }

    /**
     * Sanitize and validate page number
     *
     * @param mixed $page The page number to sanitize
     * @return int Sanitized page number
     */
    public static function sanitize_page_number($page) {
        $page = absint($page);
        return max(1, $page); // Ensure page is at least 1
    }

    /**
     * Sanitize log file name
     *
     * @param string $filename The log file name to sanitize
     * @return string Sanitized filename
     */
    public static function sanitize_log_filename($filename) {
        // Remove any directory traversal attempts
        $filename = self::sanitize_file_path($filename);
        
        // Only allow alphanumeric, dash, underscore, and dot
        $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename);
        
        // Ensure it ends with .log
        if (!preg_match('/\.log$/', $filename)) {
            $filename .= '.log';
        }
        
        return $filename;
    }

    /**
     * Verify file is within allowed directories
     *
     * @param string $file_path The file path to verify
     * @return bool True if file is in allowed directory
     */
    public static function is_file_in_allowed_directory($file_path) {
        $allowed_dirs = array(
            WP_CONTENT_DIR,
            dirname(WP_CONTENT_DIR) . '/logs'
        );
        
        $file_path = realpath($file_path);
        if ($file_path === false) {
            return false;
        }
        
        foreach ($allowed_dirs as $dir) {
            if (strpos($file_path, realpath($dir)) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate nonce for AJAX requests
     *
     * @return string The nonce
     */
    public static function generate_ajax_nonce() {
        return wp_create_nonce('logiq_admin_nonce');
    }

    /**
     * Verify AJAX request
     *
     * @param string $action The action to verify
     * @return bool True if verified
     */
    public static function verify_ajax_request($action) {
        if (!check_ajax_referer('logiq_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Invalid security token.', 'logiq'));
            return false;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'logiq'));
            return false;
        }
        
        return true;
    }
} 