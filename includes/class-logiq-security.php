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
     */
    private function is_logiq_page() {
        if (!is_admin()) {
            return false;
        }
        
        $screen = get_current_screen();
        return strpos($screen->id, 'logiq') !== false;
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
     */
    public static function sanitize_log_level($level) {
        $allowed_levels = ['all', 'fatal', 'error', 'warning', 'info', 'debug', 'deprecated'];
        return in_array($level, $allowed_levels) ? $level : 'all';
    }
} 