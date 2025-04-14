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
        error_log('LogIQ Debug - Attempting to read log file: ' . $log_file);
        
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $level = isset($_POST['level']) ? LogIQ_Security::sanitize_log_level($_POST['level']) : 'all';
        $per_page = 10; // Increased to show more logs per page
        
        if (!file_exists($log_file)) {
            error_log('LogIQ Debug - Log file not found: ' . $log_file);
            wp_send_json_success(array(
                'html' => '<p class="description">' . __('No logs found.', 'logiq') . '</p>',
                'pagination' => '',
                'counts' => array_fill_keys(['all', 'fatal', 'error', 'warning', 'deprecated', 'info', 'debug'], 0)
            ));
            return;
        }

        // Read logs
        error_log('LogIQ Debug - Reading file contents from: ' . $log_file);
        $logs = file_get_contents($log_file);
        $log_entries = array_filter(explode(PHP_EOL, $logs));
        $total_raw_entries = count($log_entries);
        error_log('LogIQ Debug - Total raw log entries found: ' . $total_raw_entries);
        
        // Log first few entries for debugging
        for ($i = 0; $i < min(5, count($log_entries)); $i++) {
            error_log('LogIQ Debug - Sample entry ' . $i . ': ' . $log_entries[$i]);
        }
        
        $log_entries = array_reverse($log_entries);
        $parsed_entries = [];
        $debug_counts = array_fill_keys(['all', 'fatal', 'error', 'warning', 'notice', 'deprecated', 'info', 'debug'], 0);

        // Parse all entries
        foreach ($log_entries as $index => $entry) {
            $parsed = $this->parse_log_entry($entry);
            if ($parsed === null) {
                error_log('LogIQ Debug - Failed to parse entry ' . $index . ': ' . substr($entry, 0, 100));
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

            // Log every 100th entry for debugging
            if ($index % 100 === 0) {
                error_log('LogIQ Debug - Processing entry ' . $index . ' Level: ' . $parsed['level']);
            }
        }

        error_log('LogIQ Debug - Total parsed entries: ' . count($parsed_entries));
        error_log('LogIQ Debug - Counts by level: ' . print_r($debug_counts, true));

        // Filter by requested level if not 'all'
        if ($level !== 'all') {
            $filtered_entries = array_filter($parsed_entries, function($entry) use ($level) {
                $matches = $this->entry_matches_level($entry, $level);
                error_log('LogIQ Debug - Checking entry level match: ' . $entry['level'] . ' against ' . $level . ' = ' . ($matches ? 'true' : 'false'));
                return $matches;
            });
            $parsed_entries = array_values($filtered_entries);
            error_log('LogIQ Debug - Filtered entries for level ' . $level . ': ' . count($parsed_entries));
        }

        // Calculate pagination
        $total_entries = count($parsed_entries);
        $total_pages = ceil($total_entries / $per_page);
        $offset = ($page - 1) * $per_page;
        
        // Slice the array for current page
        $current_page_entries = array_slice($parsed_entries, $offset, $per_page);
        error_log('LogIQ Debug - Current page entries: ' . count($current_page_entries));

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
            'pagination' => $pagination,
            'counts' => $debug_counts,
            'debug_info' => array(
                'total_raw_entries' => $total_raw_entries,
                'total_parsed_entries' => count($parsed_entries),
                'filtered_entries' => $total_entries,
                'current_page' => $page,
                'entries_per_page' => $per_page,
                'total_pages' => $total_pages,
                'log_file' => basename($log_file),
                'log_file_full_path' => $log_file,
                'log_file_size' => filesize($log_file),
                'log_file_modified' => date('Y-m-d H:i:s', filemtime($log_file))
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

        // Default values
        $parsed = array(
            'timestamp' => current_time('mysql'),
            'level' => 'info',
            'context' => 'unknown',
            'file' => '',
            'line' => 0,
            'data' => $entry // Store full entry as default data
        );

        // Parse standard PHP error log format with timestamp
        if (preg_match('/^\[(.*?)\] (.+)$/', $entry, $matches)) {
            $parsed['timestamp'] = $matches[1];
            $message = $matches[2];

            // Determine the log level and context
            if (strpos($message, 'PHP Notice:') !== false || strpos($message, '_load_textdomain_just_in_time') !== false) {
                $parsed['level'] = 'notice';
                $parsed['context'] = 'wp_notice';
            } 
            else if (strpos($message, 'PHP Warning:') !== false || strpos($message, 'Warning:') !== false) {
                $parsed['level'] = 'warning';
                $parsed['context'] = 'php_warning';
            }
            else if (strpos($message, 'PHP Deprecated:') !== false || strpos($message, 'deprecated') !== false || strpos($message, 'Deprecated:') !== false) {
                $parsed['level'] = 'deprecated';
                $parsed['context'] = 'php_deprecated';
            }
            else if (strpos($message, 'PHP Fatal error:') !== false) {
                $parsed['level'] = 'fatal';
                $parsed['context'] = 'php_fatal';
            }
            else if (strpos($message, 'PHP Error:') !== false) {
                $parsed['level'] = 'error';
                $parsed['context'] = 'php_error';
            }

            // Special handling for dynamic property messages
            if (strpos($message, 'Creation of dynamic property') !== false) {
                $parsed['level'] = 'deprecated';
                $parsed['context'] = 'php_deprecated';
            }

            $parsed['data'] = $message;

            // Extract file and line information
            if (preg_match('/in (.+?) on line (\d+)/', $message, $file_matches)) {
                $parsed['file'] = str_replace(ABSPATH, '', $file_matches[1]);
                $parsed['line'] = intval($file_matches[2]);
            }
        }

        return $parsed;
    }
} 