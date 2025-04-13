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
} 