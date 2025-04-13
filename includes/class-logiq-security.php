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
     * Sanitize log data for display
     */
    public static function sanitize_log_data($data) {
        if (is_array($data)) {
            return array_map(array(__CLASS__, 'sanitize_log_data'), $data);
        }
        return wp_kses($data, array(
            'pre' => array(),
            'code' => array(),
            'strong' => array(),
            'br' => array()
        ));
    }

    /**
     * Verify admin ajax requests
     */
    public static function verify_admin_ajax() {
        if (!check_ajax_referer('logiq_admin_nonce', 'nonce', false)) {
            wp_send_json_error(__('Invalid security token.', 'logiq'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'logiq'));
        }
    }
} 