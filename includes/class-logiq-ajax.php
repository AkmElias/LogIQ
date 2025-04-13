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
        check_ajax_referer('logiq_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to view logs.', 'logiq'));
        }

        $log_file = logiq_get_log_file();
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = 10; // Reduced from 50 to 10 for better testing
        
        if (!file_exists($log_file)) {
            wp_send_json_success(array(
                'html' => '<p class="description">' . __('No logs found.', 'logiq') . '</p>',
                'pagination' => '',
                'total_pages' => 0,
                'current_page' => 1
            ));
            return;
        }

        // Read the log file
        $logs = file_get_contents($log_file);
        $log_entries = array_filter(explode(PHP_EOL, $logs));
        $log_entries = array_reverse($log_entries); // Show newest first
        
        // Calculate pagination
        $total_entries = count($log_entries);
        $total_pages = ceil($total_entries / $per_page);
        $offset = ($page - 1) * $per_page;
        
        // Slice the array for current page
        $current_page_entries = array_slice($log_entries, $offset, $per_page);

        $output = '';
        foreach ($current_page_entries as $entry) {
            if (empty($entry)) {
                continue;
            }

            $log_data = json_decode($entry, true);
            if (!$log_data) {
                $output .= '<div class="log-entry"><div class="log-data">' . esc_html($entry) . '</div></div>';
                continue;
            }

            $output .= $this->format_log_entry($log_data);
        }

        if (empty($output)) {
            $output = '<p class="description">' . __('No logs found.', 'logiq') . '</p>';
        }

        // Generate pagination only if there are multiple pages
        $pagination = '';
        if ($total_pages > 1) {
            $pagination = $this->get_pagination_html($page, $total_pages, $total_entries);
        }

        wp_send_json_success(array(
            'html' => $output,
            'pagination' => $pagination,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'total_entries' => $total_entries
        ));
    }

    /**
     * Generate pagination HTML
     *
     * @param int $current_page Current page number
     * @param int $total_pages Total number of pages
     * @param int $total_entries Total number of log entries
     * @return string HTML for pagination controls
     */
    private function get_pagination_html($current_page, $total_pages, $total_entries) {
        if ($total_pages <= 1) {
            return '';
        }

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
            '<span class="paging-input"><label for="current-page-selector" class="screen-reader-text">%s</label><input class="current-page" id="current-page-selector" type="text" name="paged" value="%d" size="%d" aria-describedby="table-paging"><span class="tablenav-paging-text"> %s <span class="total-pages">%d</span></span></span>',
            __('Current Page', 'logiq'),
            $current_page,
            strlen($total_pages),
            __('of', 'logiq'),
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
        check_ajax_referer('logiq_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to clear logs.', 'logiq'));
        }

        $log_file = logiq_get_log_file();
        
        if (file_exists($log_file)) {
            if (file_put_contents($log_file, '') === false) {
                wp_send_json_error(__('Failed to clear logs.', 'logiq'));
            }
        }

        wp_send_json_success(__('Logs cleared successfully.', 'logiq'));
    }

    /**
     * Format a single log entry
     *
     * @param array $log_data The log entry data
     * @return string Formatted HTML
     */
    private function format_log_entry($log_data) {
        $output = '<div class="log-entry">';
        
        // Timestamp
        $output .= sprintf(
            '<div class="log-timestamp">%s</div>',
            esc_html($log_data['timestamp'])
        );

        // Context
        if (!empty($log_data['context'])) {
            $output .= sprintf(
                '<div class="log-context">%s</div>',
                esc_html($log_data['context'])
            );
        }

        // File and line
        if (!empty($log_data['file']) && !empty($log_data['line'])) {
            $output .= sprintf(
                '<div class="log-file">%s:%d</div>',
                esc_html($log_data['file']),
                intval($log_data['line'])
            );
        }

        // User
        if (!empty($log_data['user'])) {
            $user = get_user_by('id', $log_data['user']);
            if ($user) {
                $output .= sprintf(
                    '<div class="log-user">%s</div>',
                    esc_html($user->display_name)
                );
            }
        }

        // Data
        $output .= sprintf(
            '<div class="log-data">%s</div>',
            esc_html($log_data['data'])
        );

        $output .= '</div>';

        return $output;
    }
} 