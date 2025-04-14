<?php
/**
 * LogIQ AJAX Handler Class
 *
 * @package LogIQ
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LogIQ_Ajax
 */
class LogIQ_Ajax {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_logiq_open_in_editor', array($this, 'open_in_editor'));
    }
    /**
     * Open file in editor
     */
    public function open_in_editor() {
        check_ajax_referer('logiq_ajax');

        // Sanitize and validate input
        $file = isset($_POST['file']) ? sanitize_text_field($_POST['file']) : '';
        $line = isset($_POST['line']) ? absint($_POST['line']) : 1;

        if (empty($file)) {
            wp_send_json_error(array(
                'message' => __('No file specified.', 'logiq')
            ));
            return;
        }

        // Clean up the file path
        $file = wp_normalize_path($file);
        
        // If path is relative, make it absolute
        if (!path_is_absolute($file)) {
            if (defined('ABSPATH')) {
                $file = ABSPATH . ltrim($file, '/\\');
            } else {
                wp_send_json_error(array(
                    'message' => __('Unable to determine WordPress root path.', 'logiq')
                ));
                return;
            }
        }

        // Verify file exists
        if (!file_exists($file)) {
            wp_send_json_error(array(
                'message' => sprintf(__('File not found: %s', 'logiq'), esc_html($file))
            ));
            return;
        }

        // Check if file is within allowed directories
        if (!LogIQ_Security::is_file_in_allowed_directory($file)) {
            wp_send_json_error(array(
                'message' => __('Access to this file is not allowed for security reasons.', 'logiq')
            ));
            return;
        }

        // Get editor info and construct URL
        $editor_info = logiq_get_editor_info();
        $editor_url = logiq_construct_editor_url($file, $line);

        wp_send_json_success(array(
            'editor_url' => $editor_url, // Don't escape the URL as it needs to be used directly
            'editor_info' => $editor_info,
            'debug' => array(
                'file' => esc_html($file),
                'line' => esc_html($line),
                'os' => PHP_OS,
                'abspath' => defined('ABSPATH') ? esc_html(ABSPATH) : 'undefined'
            )
        ));
    }
} 