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
        add_action('wp_ajax_logiq_get_logs', array($this, 'get_logs'));
        add_action('wp_ajax_logiq_clear_logs', array($this, 'clear_logs'));
        add_action('wp_ajax_logiq_open_in_editor', array($this, 'open_in_editor'));
    }

    /**
     * Get logs via AJAX
     */
    public function get_logs() {
        //test array logs
        $data = array(
            'action' => 'get_logs',
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        );
        error_log('LogIQ Debug - Data: ' . print_r($data, true));

        // Verify AJAX request
        if (!LogIQ_Security::verify_ajax_request('get_logs')) {
            return;
        }
        
        // Get and sanitize log file
        $log_file = logiq_get_log_file();
        if (!$log_file || !LogIQ_Security::is_file_in_allowed_directory($log_file)) {
            wp_send_json_error(__('Invalid log file.', 'logiq'));
            return;
        }
        
        // Sanitize input
        $page = LogIQ_Security::sanitize_page_number(isset($_POST['page']) ? $_POST['page'] : 1);
        $level = isset($_POST['level']) ? LogIQ_Security::sanitize_log_level($_POST['level']) : 'all';
        $per_page = 100; // Show more logs per page
        
        if (!file_exists($log_file)) {
            wp_send_json_success(array(
                'html' => '<p class="description">' . esc_html__('No logs found.', 'logiq') . '</p>',
                'pagination' => '',
                'counts' => array_fill_keys(['all', 'fatal', 'error', 'warning', 'deprecated', 'info', 'debug'], 0)
            ));
            return;
        }

        // Read logs
        $logs = file_get_contents($log_file);
        if ($logs === false) {
            wp_send_json_error(__('Could not read log file.', 'logiq'));
            return;
        }

        // Split logs by timestamp pattern instead of newlines
        $log_entries = preg_split('/(?=\[\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2} UTC\])/', $logs, -1, PREG_SPLIT_NO_EMPTY);
        $total_raw_entries = count($log_entries);
        
        $log_entries = array_reverse($log_entries);
        $parsed_entries = [];
        $debug_counts = array_fill_keys(['all', 'fatal', 'error', 'warning', 'notice', 'deprecated', 'info', 'debug'], 0);

        // Parse all entries
        foreach ($log_entries as $entry) {
            $parsed = $this->parse_log_entry(trim($entry));
            if ($parsed === null) {
                continue;
            }

            $parsed_entries[] = $parsed;
            $debug_counts['all']++;
            
            // Count by level
            foreach (['fatal', 'error', 'warning', 'notice', 'deprecated', 'info', 'debug'] as $log_level) {
                if ($this->entry_matches_level($parsed, $log_level)) {
                    $debug_counts[$log_level]++;
                    break; // Each entry should only count for one level
                }
            }
        }

        // Filter by requested level if not 'all'
        if ($level !== 'all') {
            $filtered_entries = array_filter($parsed_entries, function($entry) use ($level) {
                return $this->entry_matches_level($entry, $level);
            });
            $parsed_entries = array_values($filtered_entries);
        }

        // Calculate pagination
        $total_entries = count($parsed_entries);
        $total_pages = ceil($total_entries / $per_page);
        $offset = ($page - 1) * $per_page;
        
        // Slice the array for current page
        $current_page_entries = array_slice($parsed_entries, $offset, $per_page);

        $output = '';
        $pagination = '';
        
        // Generate logs output
        if (!empty($current_page_entries)) {
            foreach ($current_page_entries as $entry) {
                $output .= $this->format_log_entry($entry);
            }
        }

        if (empty($output)) {
            $output = '<p class="description">' . esc_html__('No logs found.', 'logiq') . '</p>';
        }

        // Only generate pagination if there are multiple pages
        if ($total_pages > 1) {
            $pagination = $this->generate_pagination($page, $total_pages, $total_entries);
        }

        wp_send_json_success(array(
            'html' => $output,
            'pagination' => $pagination,
            'counts' => $debug_counts,
            'debug_info' => array(
                'total_raw_entries' => $total_raw_entries,
                'total_parsed_entries' => count($parsed_entries),
                'filtered_entries' => $total_entries,
                'current_page' => $page,
                'entries_per_page' => $per_page,
                'total_pages' => $total_pages,
                'log_file' => esc_html(basename($log_file)),
                'log_file_size' => esc_html(filesize($log_file)),
                'log_file_modified' => esc_html(date('Y-m-d H:i:s', filemtime($log_file)))
            )
        ));
    }

    /**
     * Check if a log entry matches a given log level/category
     *
     * @param array $entry Log entry data
     * @param string $level Target level/category
     * @return bool True if matches
     */
    private function entry_matches_level($entry, $level) {
        $data = strtolower($entry['data']);
        $entry_level = strtolower($entry['level']);
        
        switch ($level) {
            case 'all':
                return true;

            case 'notice':
                return (
                    $entry_level === 'notice' ||
                    strpos($data, 'php notice:') !== false ||
                    strpos($data, '_load_textdomain_just_in_time') !== false
                ) && !strpos($data, 'deprecated');

            case 'deprecated':
                return (
                    $entry_level === 'deprecated' ||
                    strpos($data, 'deprecated') !== false ||
                    strpos($data, 'creation of dynamic property') !== false
                );

            case 'warning':
                return (
                    $entry_level === 'warning' ||
                    strpos($data, 'php warning:') !== false ||
                    strpos($data, 'warning:') !== false
                ) && !strpos($data, 'deprecated');

            case 'error':
                return $entry_level === 'error' || strpos($data, 'php error:') !== false;

            case 'fatal':
                return $entry_level === 'fatal' || strpos($data, 'php fatal') !== false;

            case 'info':
                return $entry_level === 'info';

            case 'debug':
                return $entry_level === 'debug';

            default:
                return false;
        }
    }


    /**
     * Generate pagination HTML
     */
    private function generate_pagination($current_page, $total_pages, $total_entries) {
        $output = '<div class="tablenav-pages">';
        $output .= '<span class="displaying-num">' . sprintf(__('%d items', 'logiq'), $total_entries) . '</span>';
        
        $output .= '<span class="pagination-links">';
        
        // First page
        if ($current_page > 1) {
            $output .= sprintf(
                '<a class="first-page button" href="#" data-page="1"><span class="screen-reader-text">%s</span><span aria-hidden="true">«</span></a>',
                __('First page', 'logiq')
            );
        } else {
            $output .= '<span class="first-page button disabled"><span aria-hidden="true">«</span></span>';
        }

        // Previous page
        if ($current_page > 1) {
            $output .= sprintf(
                '<a class="prev-page button" href="#" data-page="%d"><span class="screen-reader-text">%s</span><span aria-hidden="true">‹</span></a>',
                $current_page - 1,
                __('Previous page', 'logiq')
            );
        } else {
            $output .= '<span class="prev-page button disabled"><span aria-hidden="true">‹</span></span>';
        }

        // Current page info
        $output .= sprintf(
            '<span class="paging-input"><span class="tablenav-paging-text">%d of <span class="total-pages">%d</span></span></span>',
            $current_page,
            $total_pages
        );

        // Next page
        if ($current_page < $total_pages) {
            $output .= sprintf(
                '<a class="next-page button" href="#" data-page="%d"><span class="screen-reader-text">%s</span><span aria-hidden="true">›</span></a>',
                $current_page + 1,
                __('Next page', 'logiq')
            );
        } else {
            $output .= '<span class="next-page button disabled"><span aria-hidden="true">›</span></span>';
        }

        // Last page
        if ($current_page < $total_pages) {
            $output .= sprintf(
                '<a class="last-page button" href="#" data-page="%d"><span class="screen-reader-text">%s</span><span aria-hidden="true">»</span></a>',
                $total_pages,
                __('Last page', 'logiq')
            );
        } else {
            $output .= '<span class="last-page button disabled"><span aria-hidden="true">»</span></span>';
        }

        $output .= '</span></div>';
        
        return $output;
    }

    /**
     * Clear logs via AJAX
     */
    public function clear_logs() {
        // Verify user capabilities and nonce
        LogIQ_Security::verify_admin_ajax();
        
        if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'true') {
            wp_send_json_error(__('Confirmation required', 'logiq'));
            return;
        }
        
        $log_file = logiq_get_log_file();
        
        if ($log_file === false) {
            wp_send_json_error(__('Could not determine log file location.', 'logiq'));
            return;
        }
        
        error_log('LogIQ Debug - Attempting to clear log file: ' . $log_file);
        
        if (!file_exists($log_file)) {
            wp_send_json_error(sprintf(
                __('Log file not found: %s', 'logiq'),
                basename($log_file)
            ));
            return;
        }
        
        if (!is_writable($log_file)) {
            wp_send_json_error(sprintf(
                __('Log file is not writable: %s', 'logiq'),
                basename($log_file)
            ));
            return;
        }
        
        // Clear the file
        $result = @file_put_contents($log_file, '');
        
        if ($result === false) {
            wp_send_json_error(sprintf(
                __('Failed to clear log file: %s', 'logiq'),
                error_get_last()['message'] ?? 'Unknown error'
            ));
            return;
        }
        
        // Clear cache
        clearstatcache(true, $log_file);
        
        wp_send_json_success(__('Logs cleared successfully.', 'logiq'));
    }

    /**
     * Format a single log entry
     *
     * @param array $entry The log entry data
     * @return string Formatted HTML
     */
    private function format_log_entry($entry) {
        $level_class = 'logiq-level-' . $entry['level'];
        $context_class = 'logiq-context-' . $entry['context'];
        
        $output = '<div class="logiq-entry ' . esc_attr($level_class) . ' ' . esc_attr($context_class) . '">';
        $output .= '<div class="logiq-entry-header">';
        $output .= '<span class="logiq-timestamp">' . esc_html($entry['timestamp']) . '</span>';
        $output .= '<span class="logiq-level">' . esc_html(strtoupper($entry['level'])) . '</span>';
        
        // Add file and line information with editor link if available
        if (!empty($entry['file']) && !empty($entry['line'])) {
            $file_path = $entry['file'];
            if (defined('ABSPATH') && strpos($file_path, ABSPATH) === false) {
                $file_path = ABSPATH . $file_path;
            }
            
            $editor_data = array(
                'file' => $file_path,
                'line' => $entry['line']
            );
            
            $output .= '<span class="logiq-file-info">';
            $output .= '<a href="#" class="logiq-editor-link" data-editor=\'' . esc_attr(json_encode($editor_data)) . '\'>';
            $output .= esc_html($entry['file']) . ':' . esc_html($entry['line']);
            $output .= '</a>';
            $output .= '</span>';
        }
        
        $output .= '</div>'; // End header
        
        // Format the data based on type
        $output .= '<div class="logiq-entry-content">';
        $output .= '<pre>' . esc_html($entry['data']) . '</pre>';
        $output .= '</div>';
        
        $output .= '</div>';
        return $output;
    }

    private function parse_log_entry($entry) {
        if (empty($entry)) {
            return null;
        }

        // Default values
        $parsed = array(
            'timestamp' => current_time('mysql'),
            'level' => 'info',
            'context' => 'unknown',
            'file' => '',
            'line' => 0,
            'data' => $entry // Store full entry as default data
        );

        // Parse timestamp and message
        if (preg_match('/^\[(.+?)\] (.+)$/s', $entry, $matches)) {
            $parsed['timestamp'] = $matches[1];
            $message = $matches[2];

            // Handle LogIQ Debug messages specifically
            if (strpos($message, 'LogIQ Debug - Data:') !== false) {
                $parsed['level'] = 'debug';
                $parsed['context'] = 'logiq';
                $parsed['data'] = $message;
            }
            // Other message types...
            else if (strpos($message, 'PHP Notice:') !== false || strpos($message, '_load_textdomain_just_in_time') !== false) {
                $parsed['level'] = 'notice';
                $parsed['context'] = 'wp_notice';
                $parsed['data'] = $message;
            } 
            else if (strpos($message, 'PHP Warning:') !== false || strpos($message, 'Warning:') !== false) {
                $parsed['level'] = 'warning';
                $parsed['context'] = 'php_warning';
                $parsed['data'] = $message;
            }
            else if (strpos($message, 'PHP Deprecated:') !== false || strpos($message, 'deprecated') !== false || strpos($message, 'Deprecated:') !== false) {
                $parsed['level'] = 'deprecated';
                $parsed['context'] = 'php_deprecated';
                $parsed['data'] = $message;
            }
            else if (strpos($message, 'PHP Fatal error:') !== false) {
                $parsed['level'] = 'fatal';
                $parsed['context'] = 'php_fatal';
                $parsed['data'] = $message;
            }
            else if (strpos($message, 'PHP Error:') !== false) {
                $parsed['level'] = 'error';
                $parsed['context'] = 'php_error';
                $parsed['data'] = $message;
            }

            // Special handling for dynamic property messages
            if (strpos($message, 'Creation of dynamic property') !== false) {
                $parsed['level'] = 'deprecated';
                $parsed['context'] = 'php_deprecated';
            }

            // Extract file and line information - try multiple patterns
            $file_patterns = array(
                '/in (.+?) on line (\d+)/',           // Standard PHP format
                '/in ([^:]+):(\d+)/',                 // Alternative format
                '/([^:]+):(\d+)$/',                   // Simple format at end
                '/([^:]+) on line (\d+)/',           // Format without 'in'
                '/([^:]+):(\d+)(?:\)|$)/'            // Format with optional closing parenthesis
            );

            foreach ($file_patterns as $pattern) {
                if (preg_match($pattern, $message, $file_matches)) {
                    $file = $file_matches[1];
                    // Clean up the file path
                    $file = trim($file, "'\"()");
                    // Convert Windows backslashes to forward slashes
                    $file = str_replace('\\', '/', $file);
                    // Remove ABSPATH if it's at the start
                    if (defined('ABSPATH')) {
                        $file = str_replace(ABSPATH, '', $file);
                    }
                    $parsed['file'] = $file;
                    $parsed['line'] = intval($file_matches[2]);
                    break;
                }
            }
        }

        return $parsed;
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