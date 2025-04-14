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
    }

    /**
     * Get logs via AJAX
     */
    public function get_logs() {
        // Verify user capabilities and nonce
        LogIQ_Security::verify_admin_ajax();
        
        $log_file = logiq_get_log_file();
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $level = isset($_POST['level']) ? LogIQ_Security::sanitize_log_level($_POST['level']) : 'all';
        $per_page = 10;
        
        if (!file_exists($log_file)) {
            wp_send_json_success(array(
                'html' => '<p class="description">' . __('No logs found.', 'logiq') . '</p>',
                'pagination' => '',
                'counts' => array_fill_keys(['all', 'fatal', 'error', 'warning', 'deprecated', 'info', 'debug'], 0)
            ));
            return;
        }

        // Read logs
        $logs = file_get_contents($log_file);
        $log_entries = array_filter(explode(PHP_EOL, $logs));
        $log_entries = array_reverse($log_entries);

        // Parse all entries and deduplicate
        $parsed_entries = [];
        $seen_entries = [];

        foreach ($log_entries as $entry) {
            $parsed = $this->parse_log_entry($entry);
            
            // Create a unique key for each log entry based on timestamp, message, and file
            $unique_key = md5($parsed['timestamp'] . $parsed['data'] . $parsed['file'] . $parsed['line']);
            
            // Only keep the entry if we haven't seen it before
            if (!isset($seen_entries[$unique_key])) {
                $parsed_entries[] = $parsed;
                $seen_entries[$unique_key] = true;
            }
        }

        // Filter by level if specified
        // List of log levels (for counts and filtering)
        $all_levels = ['all', 'fatal', 'error', 'warning', 'deprecated', 'notice', 'info', 'debug'];

        // Categorize and count entries
        $counts = array_fill_keys($all_levels, 0);

        foreach ($parsed_entries as $entry) {
            foreach ($all_levels as $log_level) {
                if ($this->entry_matches_level($entry, $log_level)) {
                    $counts[$log_level]++;
                }
            }
        }

        // Filter by requested level
        if ($level !== 'all') {
            $parsed_entries = array_filter($parsed_entries, function($entry) use ($level) {
                return $this->entry_matches_level($entry, $level);
            });
        }


        // var_dump($counts);
        // die();
        
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
            $output = '<p class="description">' . __('No logs found.', 'logiq') . '</p>';
        }

        // Only generate pagination if there are multiple pages
        if ($total_pages > 1) {
            $pagination = $this->generate_pagination($page, $total_pages, $total_entries);
        }

        wp_send_json_success(array(
            'html' => $output,
            'pagination' => $pagination, // Will be empty string if no pagination needed
            'counts' => $counts
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
        switch ($level) {
            case 'all':
                return true;

            case 'notice':
                // Check for PHP Notices including textdomain notices
                return (
                    strtolower($entry['level']) === 'notice' ||
                    (strpos($entry['data'], 'PHP Notice:') !== false) ||
                    (strpos($entry['context'], 'wp_notice') !== false)
                ) && 
                // Exclude deprecated notices
                strpos($entry['data'], 'deprecated') === false;

            case 'deprecated':
                return (
                    strtolower($entry['level']) === 'deprecated' ||
                    strpos($entry['context'], 'deprecated') !== false ||
                    strpos($entry['data'], 'deprecated') !== false ||
                    strpos($entry['data'], 'Deprecated:') !== false
                );

            case 'warning':
                return (
                    strtolower($entry['level']) === 'warning' ||
                    strpos($entry['data'], 'PHP Warning:') !== false
                );

            case 'error':
                return (
                    strtolower($entry['level']) === 'error' ||
                    strpos($entry['data'], 'PHP Error:') !== false
                );

            case 'fatal':
                return (
                    strtolower($entry['level']) === 'fatal' ||
                    strpos($entry['data'], 'PHP Fatal') !== false
                );

            case 'info':
                return strtolower($entry['level']) === 'info';

            case 'debug':
                return strtolower($entry['level']) === 'debug';

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
     * @param array $log_data The log entry data
     * @return string Formatted HTML
     */
    private function format_log_entry($log_data) {
        $level = isset($log_data['level']) ? esc_attr($log_data['level']) : 'info';
        $context = isset($log_data['context']) ? esc_attr($log_data['context']) : '';
        
        $output = sprintf('<div class="log-entry" data-level="%s" data-context="%s">', $level, $context);
        
        // Timestamp
        $output .= sprintf(
            '<div class="log-timestamp">%s</div>',
            esc_html($log_data['timestamp'])
        );

        // Level indicator with context
        $output .= sprintf(
            '<div class="log-level">%s%s</div>',
            esc_html(strtoupper($level)),
            $context ? ' - ' . esc_html($context) : ''
        );

        // File and line
        if (!empty($log_data['file'])) {
            $output .= sprintf(
                '<div class="log-file" title="%s">%s:%d</div>',
                esc_attr($log_data['file']),
                esc_html($log_data['file']),
                intval($log_data['line'])
            );
        }

        // Data/Message with better wrapping
        $output .= '<div class="log-data">';
        if (is_array($log_data['data'])) {
            $output .= '<pre>' . esc_html(print_r($log_data['data'], true)) . '</pre>';
        } else {
            // Format long lines better and handle HTML in notices
            $message = $log_data['context'] === 'wp_notice' ? 
                wp_kses($log_data['data'], array('code' => array(), 'strong' => array(), 'a' => array('href' => array()))) :
                esc_html($log_data['data']);
            $output .= '<pre>' . $message . '</pre>';
        }
        $output .= '</div>';

        $output .= '</div>';
        return $output;
    }

    private function parse_log_entry($entry) {
        if (empty($entry)) {
            return null;
        }

        // Default values for required fields
        $parsed = array(
            'timestamp' => current_time('mysql'),
            'level' => 'info',
            'context' => 'unknown',
            'file' => '',
            'line' => 0,
            'data' => '',
            'user' => get_current_user_id()
        );

        // First try to parse as JSON
        $json_data = json_decode($entry, true);
        if ($json_data && is_array($json_data)) {
            return array_merge($parsed, array_intersect_key($json_data, $parsed));
        }

        // Parse standard PHP error log format with timestamp
        if (preg_match('/^\[(.*?)\] (.+)$/', $entry, $matches)) {
            $parsed['timestamp'] = $matches[1];
            $message = $matches[2];

            // Check for WordPress textdomain notice
            if (strpos($message, '_load_textdomain_just_in_time') !== false) {
                $parsed['level'] = 'notice';
                $parsed['context'] = 'wp_notice';
                $parsed['data'] = $message;
                if (preg_match('/in (.+?) on line (\d+)$/', $message, $file_matches)) {
                    $parsed['file'] = str_replace(ABSPATH, '', $file_matches[1]);
                    $parsed['line'] = intval($file_matches[2]);
                }
                return $parsed;
            }

            // Check for PHP Deprecated message
            if (strpos($message, 'PHP Deprecated:') !== false || strpos($message, 'deprecated') !== false) {
                $parsed['level'] = 'deprecated';
                $parsed['context'] = 'deprecated_notice';
                if (preg_match('/PHP Deprecated:\s*(.+?) in (.+?) on line (\d+)/', $message, $dep_matches)) {
                    $parsed['data'] = $dep_matches[1];
                    $parsed['file'] = str_replace(ABSPATH, '', $dep_matches[2]);
                    $parsed['line'] = intval($dep_matches[3]);
                } else {
                    $parsed['data'] = $message;
                }
                return $parsed;
            }

            // Parse different error types
            if (preg_match('/PHP (?:(Parse|Fatal|Warning|Notice|Deprecated)) (?:error|warning|notice):\s*(.+?)(?:\s+in\s+(.+?)\s+on\s+line\s+(\d+))?$/i', $message, $error_matches)) {
                $error_type = strtolower($error_matches[1]);
                // Replace match expression with switch statement for better compatibility
                switch ($error_type) {
                    case 'parse':
                    case 'fatal':
                        $parsed['level'] = 'fatal';
                        break;
                    case 'warning':
                        $parsed['level'] = 'warning';
                        break;
                    case 'deprecated':
                        $parsed['level'] = 'deprecated';
                        break;
                    case 'notice':
                        $parsed['level'] = 'notice';
                        break;
                    default:
                        $parsed['level'] = 'info';
                }
                $parsed['context'] = 'php_' . $error_type;
                $parsed['data'] = $error_matches[2];
                
                if (!empty($error_matches[3])) {
                    $parsed['file'] = str_replace(ABSPATH, '', $error_matches[3]);
                    $parsed['line'] = intval($error_matches[4]);
                }
            } else {
                $parsed['data'] = $message;
            }
        }

        return $parsed;
    }
} 