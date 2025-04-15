<?php
/**
 * LogIQ Admin Class
 *
 * @package LogIQ
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LogIQ_Admin
 */
class LogIQ_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Register AJAX handlers
        add_action('wp_ajax_logiq_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_logiq_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_logiq_toggle_debug', array($this, 'ajax_toggle_debug'));
        add_action('wp_ajax_logiq_open_in_editor', array($this, 'open_in_editor'));
        add_action('wp_ajax_logiq_update_debug_settings', array($this, 'ajax_update_debug_settings'));
        add_action('wp_ajax_logiq_get_debug_settings', array($this, 'ajax_get_debug_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('LogIQ Debug', 'logiq'),
            __('LogIQ Debug', 'logiq'),
            'manage_options',
            'logiq-debug',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('logiq_options', 'logiq_debug_enabled', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ));
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'logiq'));
        }

        $debug_enabled = get_option('logiq_debug_enabled', true);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('LogIQ Debug', 'logiq'); ?></h1>

            <div class="logiq-settings-section">
                <h2><?php echo esc_html__('Debug Settings', 'logiq'); ?></h2>
                
                <table class="form-table" role="presentation">
                    <?php
                    $constants = $this->get_debug_constants();
                    foreach ($constants as $constant => $data) {
                        ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($data['name']); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="<?php echo esc_attr($data['name']); ?>" 
                                           class="logiq-debug-setting"
                                           value="1">
                                    <?php echo esc_html($data['info']); ?>
                                </label>
                                <?php if (isset($data['description'])): ?>
                                    <p class="description"><?php echo esc_html($data['description']); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>

                <p class="submit">
                    <button type="button" id="logiq-save-debug-settings" class="button button-primary">
                        <?php echo esc_html__('Save Changes', 'logiq'); ?>
                    </button>
                </p>
            </div>

            <div class="logiq-logs-section">
                <h2><?php echo esc_html__('LogIQ Debug Logs', 'logiq'); ?></h2>
                <div class="logiq-actions">
                    <button type="button" id="logiq-refresh-logs" class="button">
                        <?php echo esc_html__('Refresh Logs', 'logiq'); ?>
                    </button>
                    <button type="button" id="logiq-clear-logs" class="button">
                        <?php echo esc_html__('Clear Logs', 'logiq'); ?>
                    </button>
                </div>

                <div class="logiq-filters">
                    <button type="button" class="logiq-level-filter button active" data-level="all">
                        <?php echo esc_html__('All Logs', 'logiq'); ?> <span class="count">(0)</span>
                    </button>
                    <button type="button" class="logiq-level-filter button" data-level="fatal">
                        <?php echo esc_html__('Fatal', 'logiq'); ?> <span class="count">(0)</span>
                    </button>
                    <button type="button" class="logiq-level-filter button" data-level="error">
                        <?php echo esc_html__('Errors', 'logiq'); ?> <span class="count">(0)</span>
                    </button>
                    <button type="button" class="logiq-level-filter button" data-level="warning">
                        <?php echo esc_html__('Warnings', 'logiq'); ?> <span class="count">(0)</span>
                    </button>
                    <button type="button" class="logiq-level-filter button" data-level="notice">
                        <?php echo esc_html__('Notices', 'logiq'); ?> <span class="count">(0)</span>
                    </button>
                    <button type="button" class="logiq-level-filter button" data-level="info">
                        <?php echo esc_html__('Info', 'logiq'); ?> <span class="count">(0)</span>
                    </button>
                    <button type="button" class="logiq-level-filter button" data-level="debug">
                        <?php echo esc_html__('Debug', 'logiq'); ?> <span class="count">(0)</span>
                    </button>
                    <button type="button" class="logiq-level-filter button" data-level="deprecated">
                        <?php echo esc_html__('Deprecated', 'logiq'); ?> <span class="count">(0)</span>
                    </button>
                </div>

                <div id="logiq-entries" class="logiq-entries"></div>
                <div id="logiq-pagination" class="tablenav"></div>
                <div id="logiq-debug-info" class="logiq-debug-info"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle debug toggle
     */
    public function handle_debug_toggle() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'logiq'));
            return;
        }

        $debug_enabled = get_option('logiq_debug_enabled', true);
        $config_path = ABSPATH . 'wp-config.php';

        if (!file_exists($config_path) || !is_writable($config_path)) {
            wp_send_json_error(__('wp-config.php not found or not writable.', 'logiq'));
            return;
        }

        $config_content = file_get_contents($config_path);
        if ($debug_enabled) {
            // Enable debug logging
            if (!defined('WP_DEBUG')) {
                $config_content = preg_replace(
                    '/\n\s*\/\*\*\s*@package\s+WordPress\s*\*\//',
                    "\ndefine('WP_DEBUG', true);\ndefine('WP_DEBUG_LOG', true);\n$0",
                    $config_content
                );
            }
        } else {
            // Disable debug logging
            $config_content = preg_replace(
                '/\s*define\s*\(\s*[\'"]WP_DEBUG[\'"]\s*,\s*(?:true|false)\s*\)\s*;/',
                '',
                $config_content
            );
            $config_content = preg_replace(
                '/\s*define\s*\(\s*[\'"]WP_DEBUG_LOG[\'"]\s*,\s*(?:true|false)\s*\)\s*;/',
                '',
                $config_content
            );
        }

        if (file_put_contents($config_path, $config_content)) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to update wp-config.php.', 'logiq'));
        }
    }

    /**
     * Check if current page is a LogIQ page
     */
    private function is_logiq_page() {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'logiq') !== false;
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets() {
        if (!$this->is_logiq_page()) {
            return;
        }

        wp_enqueue_style(
            'logiq-admin',
            LOGIQ_URL . 'assets/css/logiq-admin.css',
            array(),
            LOGIQ_VERSION
        );

        wp_enqueue_script(
            'logiq-admin',
            LOGIQ_URL . 'assets/js/logiq-admin.js',
            array('jquery'),
            LOGIQ_VERSION,
            true
        );

        $editor_info = $this->get_editor_protocol();
        wp_localize_script('logiq-admin', 'logiq_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('logiq_ajax'),
            'strings' => array(
                'saving' => __('Saving...', 'logiq'),
                'save_changes' => __('Save Changes', 'logiq'),
                'error' => __('Error', 'logiq'),
                'success' => __('Success', 'logiq')
            ),
            'editor_protocol' => $editor_info['protocol'],
            'is_vscode' => $editor_info['is_vscode'],
            'is_windows' => PHP_OS === 'WINNT',
            'is_mac' => PHP_OS === 'Darwin',
            'debug' => WP_DEBUG,
            'abspath' => ABSPATH,
            'content_dir' => WP_CONTENT_DIR
        ));
    }

    /**
     * Get the appropriate editor protocol based on system settings
     */
    private function get_editor_protocol() {
        $editor_info = array(
            'protocol' => 'vscode://file',
            'is_vscode' => false
        );

        // Check if VS Code is installed
        if (PHP_OS === 'Darwin') { // macOS
            if (file_exists('/Applications/Visual Studio Code.app')) {
                $editor_info['is_vscode'] = true;
            }
        } elseif (PHP_OS === 'WINNT') { // Windows
            $vscode_paths = array(
                getenv('LOCALAPPDATA') . '\\Programs\\Microsoft VS Code\\Code.exe',
                getenv('ProgramFiles') . '\\Microsoft VS Code\\Code.exe',
                getenv('ProgramFiles(x86)') . '\\Microsoft VS Code\\Code.exe'
            );
            foreach ($vscode_paths as $path) {
                if (file_exists($path)) {
                    $editor_info['is_vscode'] = true;
                    break;
                }
            }
        } else { // Linux
            $vscode_paths = array(
                '/usr/bin/code',
                '/usr/local/bin/code',
                '/snap/bin/code'
            );
            foreach ($vscode_paths as $path) {
                if (file_exists($path)) {
                    $editor_info['is_vscode'] = true;
                    break;
                }
            }
        }

        // If VS Code is not found, try other editors
        if (!$editor_info['is_vscode']) {
            if (PHP_OS === 'Darwin') {
                if (file_exists('/Applications/Sublime Text.app')) {
                    $editor_info['protocol'] = 'subl://open';
                } elseif (file_exists('/Applications/PhpStorm.app')) {
                    $editor_info['protocol'] = 'phpstorm://open';
                }
            }
        }

        return $editor_info;
    }

    /**
     * AJAX handler for getting logs
     */
    public function ajax_get_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'logiq'));
            return;
        }

        check_ajax_referer('logiq_ajax');

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : 'all';
        $per_page = 10;

        $log_file = logiq_get_log_file();
        if (!$log_file || !file_exists($log_file)) {
            wp_send_json_success(array(
                'html' => '<p class="description">' . esc_html__('No logs found.', 'logiq') . '</p>',
                'pagination' => '',
                'counts' => array_fill_keys(['all', 'fatal', 'error', 'warning', 'notice', 'deprecated', 'info', 'debug'], 0),
                'debug_info' => array(
                    'total_raw_entries' => 0,
                    'total_parsed_entries' => 0,
                    'filtered_entries' => 0,
                    'current_page' => 1,
                    'total_pages' => 1,
                    'log_file' => basename($log_file),
                    'log_file_size' => 0,
                    'log_file_modified' => ''
                )
            ));
            return;
        }

        $logs = file_get_contents($log_file);
        if ($logs === false) {
            wp_send_json_error(__('Could not read log file.', 'logiq'));
            return;
        }

        // Split logs by timestamp pattern
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
                    break;
                }
            }
        }

        // Filter by level if not 'all'
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

        // Generate pagination if needed
        if ($total_pages > 1) {
            $pagination = $this->generate_pagination($page, $total_pages);
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
                'total_pages' => $total_pages,
                'log_file' => basename($log_file),
                'log_file_size' => filesize($log_file),
                'log_file_modified' => date('Y-m-d H:i:s', filemtime($log_file))
            )
        ));
    }

    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'logiq'));
            return;
        }

        check_ajax_referer('logiq_ajax');

        $log_file = logiq_get_log_file();
        if (!$log_file || !file_exists($log_file)) {
            wp_send_json_error(__('No log file found.', 'logiq'));
            return;
        }

        if (file_put_contents($log_file, '') !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to clear log file.', 'logiq'));
        }
    }

    /**
     * AJAX handler for toggling debug
     */
    public function ajax_toggle_debug() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'logiq'));
            return;
        }

        check_ajax_referer('logiq_ajax');

        $enabled = isset($_POST['enabled']) ? rest_sanitize_boolean($_POST['enabled']) : false;
        update_option('logiq_debug_enabled', $enabled);

        $this->handle_debug_toggle();
    }

    /**
     * Parse a log entry
     */
    private function parse_log_entry($entry) {
        if (empty($entry)) {
            return null;
        }

        // Default values
        $parsed = array(
            'timestamp' => '',
            'level' => 'info',
            'context' => 'unknown',
            'file' => '',
            'line' => 0,
            'data' => $entry
        );

        // Parse timestamp and message
        if (preg_match('/^\[(.+?)\] (.+)$/s', $entry, $matches)) {
            $parsed['timestamp'] = $matches[1];
            $message = $matches[2];

            // Handle PHP Parse Errors
            if (preg_match('/PHP Parse error:\s+(.+?)\s+in\s+(.+?)\s+on\s+line\s+(\d+)/', $message, $parse_error_matches)) {
                $parsed['level'] = 'error';
                $parsed['context'] = 'php_parse';
                $parsed['file'] = str_replace(ABSPATH, '', $parse_error_matches[2]);
                $parsed['line'] = intval($parse_error_matches[3]);
                $parsed['data'] = 'PHP Parse Error: ' . $parse_error_matches[1];
            }
            // Handle LogIQ Debug messages
            else if (strpos($message, 'LogIQ Debug - Data:') !== false) {
                $parsed['level'] = 'debug';
                $parsed['context'] = 'logiq';
            }
            // Handle array debug data
            else if (preg_match('/^Array\s*\(/s', $message) || strpos($message, 'ajax_url') !== false) {
                $parsed['level'] = 'debug';
                $parsed['context'] = 'array_debug';
                $parsed['data'] = $message;
            }
            // PHP Notices
            else if (strpos($message, 'PHP Notice:') !== false || strpos($message, '_load_textdomain_just_in_time') !== false) {
                $parsed['level'] = 'notice';
                $parsed['context'] = 'wp_notice';
            }
            // PHP Warnings
            else if (strpos($message, 'PHP Warning:') !== false || strpos($message, 'Warning:') !== false) {
                $parsed['level'] = 'warning';
                $parsed['context'] = 'php_warning';
            }
            // PHP Deprecated
            else if (strpos($message, 'PHP Deprecated:') !== false || strpos($message, 'deprecated') !== false) {
                $parsed['level'] = 'deprecated';
                $parsed['context'] = 'php_deprecated';
            }
            // PHP Fatal Errors
            else if (strpos($message, 'PHP Fatal error:') !== false) {
                $parsed['level'] = 'fatal';
                $parsed['context'] = 'php_fatal';
            }
            // PHP Errors
            else if (strpos($message, 'PHP Error:') !== false) {
                $parsed['level'] = 'error';
                $parsed['context'] = 'php_error';
            }

            // Extract file and line information if not already set
            if (empty($parsed['file']) && preg_match('/in (.+?) on line (\d+)/', $message, $file_matches)) {
                $parsed['file'] = str_replace(ABSPATH, '', $file_matches[1]);
                $parsed['line'] = intval($file_matches[2]);
            }
        }

        return $parsed;
    }

    /**
     * Check if an entry matches a log level
     */
    private function entry_matches_level($entry, $level) {
        return $entry['level'] === $level;
    }

    /**
     * Generate pagination HTML
     */
    private function generate_pagination($current_page, $total_pages) {
        $output = '<div class="tablenav-pages">';
        
        if ($total_pages > 1) {
            $output .= '<span class="pagination-links">';
            
            // First page
            if ($current_page > 1) {
                $output .= sprintf(
                    '<a class="first-page" href="#" data-page="1"><span class="screen-reader-text">%s</span>&laquo;</a>',
                    esc_html__('First page', 'logiq')
                );
            }
            
            // Previous page
            if ($current_page > 1) {
                $output .= sprintf(
                    '<a class="prev-page" href="#" data-page="%d"><span class="screen-reader-text">%s</span>&lsaquo;</a>',
                    $current_page - 1,
                    esc_html__('Previous page', 'logiq')
                );
            }
            
            // Current page indicator
            $output .= sprintf(
                '<span class="paging-input">%d of <span class="total-pages">%d</span></span>',
                $current_page,
                $total_pages
            );
            
            // Next page
            if ($current_page < $total_pages) {
                $output .= sprintf(
                    '<a class="next-page" href="#" data-page="%d"><span class="screen-reader-text">%s</span>&rsaquo;</a>',
                    $current_page + 1,
                    esc_html__('Next page', 'logiq')
                );
            }
            
            // Last page
            if ($current_page < $total_pages) {
                $output .= sprintf(
                    '<a class="last-page" href="#" data-page="%d"><span class="screen-reader-text">%s</span>&raquo;</a>',
                    $total_pages,
                    esc_html__('Last page', 'logiq')
                );
            }
            
            $output .= '</span>';
        }
        
        $output .= '</div>';
        return $output;
    }

    /**
     * Format a log entry for display
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
            
            // Format the file path for the OS
            $file_path = str_replace('\\', '/', $file_path);
            if (PHP_OS === 'Darwin') {
                $file_path = realpath($file_path);
            }
            
            $editor_data = array(
                'file' => $file_path,
                'line' => $entry['line']
            );
            
            $output .= '<span class="logiq-file-info">';
            $output .= '<a href="#" class="logiq-editor-link" data-editor=\'' . esc_attr(json_encode($editor_data)) . '\'>';
            $output .= esc_html(basename($entry['file'])) . ':' . esc_html($entry['line']);
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

    /**
     * Get debug constants
     */
    private function get_debug_constants() {
        return array(
            'WP_DEBUG' => array(
                'name' => 'WP_DEBUG',
                'info' => __('Enable WordPress debug logging', 'logiq'),
                'description' => __('When enabled, WordPress will log debug information to the debug.log file.', 'logiq'),
                'value' => true
            ),
            'WP_DEBUG_LOG' => array(
                'name' => 'WP_DEBUG_LOG',
                'info' => __('Log to debug.log file', 'logiq'),
                'value' => true
            ),
            'WP_DEBUG_DISPLAY' => array(
                'name' => 'WP_DEBUG_DISPLAY',
                'info' => __('Display errors and warnings', 'logiq'),
                'description' => __('Show debug messages in the HTML of your pages.', 'logiq'),
                'value' => false
            ),
            'SCRIPT_DEBUG' => array(
                'name' => 'SCRIPT_DEBUG',
                'info' => __('Use development versions of assets', 'logiq'),
                'description' => __('Load unminified versions of CSS and JavaScript files.', 'logiq'),
                'value' => false
            )
        );
    }

    /**
     * AJAX handler for updating debug settings
     */
    public function ajax_update_debug_settings() {
        try {
            // Add debug logging
            error_log('LogIQ Debug - Starting debug settings update');
            
            // Check nonce and capabilities
            if (!check_ajax_referer('logiq_ajax', '_ajax_nonce', false)) {
                error_log('LogIQ Debug - Nonce verification failed');
                wp_send_json_error('Invalid security token.');
                return;
            }

            if (!current_user_can('manage_options')) {
                error_log('LogIQ Debug - Permission check failed');
                wp_send_json_error('Permission denied.');
                return;
            }

            // Get and validate settings
            $raw_settings = isset($_POST['settings']) ? $_POST['settings'] : '';
            error_log('LogIQ Debug - Raw settings received: ' . print_r($raw_settings, true));
            
            if (empty($raw_settings)) {
                error_log('LogIQ Debug - No settings provided');
                wp_send_json_error('No settings provided.');
                return;
            }

            $settings = json_decode(stripslashes($raw_settings), true);
            error_log('LogIQ Debug - Decoded settings: ' . print_r($settings, true));
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('LogIQ Debug - JSON decode error: ' . json_last_error_msg());
                wp_send_json_error('Invalid settings format.');
                return;
            }

            // Get config path
            $config_path = ABSPATH . 'wp-config.php';
            if (!file_exists($config_path)) {
                $config_path = dirname(ABSPATH) . '/wp-config.php';
            }

            error_log('LogIQ Debug - Config path: ' . $config_path);
            error_log('LogIQ Debug - Config exists: ' . (file_exists($config_path) ? 'yes' : 'no'));
            error_log('LogIQ Debug - Config writable: ' . (is_writable($config_path) ? 'yes' : 'no'));

            if (!file_exists($config_path) || !is_writable($config_path)) {
                error_log('LogIQ Debug - Config file not found or not writable');
                wp_send_json_error('WordPress configuration file not found or not writable.');
                return;
            }

            // Debug logging
            error_log('LogIQ Debug - Creating transformer instance');
            
            $transformer = new LogIQ_Config_Transformer($config_path);
            $updated = 0;
            $debug_info = array();

            foreach ($settings as $setting) {
                if (empty($setting['name'])) {
                    continue;
                }

                $name = sanitize_text_field($setting['name']);
                $value = rest_sanitize_boolean($setting['value']);

                error_log("LogIQ Debug - Processing setting: {$name} = " . var_export($value, true));

                // Get value before update
                $old_value = $transformer->get_value($name);
                error_log("LogIQ Debug - Current value for {$name}: " . var_export($old_value, true));
                
                try {
                    // Update the value
                    if ($transformer->update($name, $value)) {
                        $updated++;
                        $debug_info[$name] = array(
                            'old_value' => $old_value,
                            'new_value' => $value,
                            'success' => true
                        );
                        error_log("LogIQ Debug - Successfully updated {$name}");
                    } else {
                        $debug_info[$name] = array(
                            'old_value' => $old_value,
                            'new_value' => $value,
                            'success' => false
                        );
                        error_log("LogIQ Debug - Failed to update {$name}");
                    }
                } catch (Exception $e) {
                    error_log("LogIQ Debug - Error updating {$name}: " . $e->getMessage());
                    $debug_info[$name] = array(
                        'old_value' => $old_value,
                        'new_value' => $value,
                        'success' => false,
                        'error' => $e->getMessage()
                    );
                }
            }

            error_log('LogIQ Debug - Update complete. Updated count: ' . $updated);
            error_log('LogIQ Debug - Debug info: ' . print_r($debug_info, true));

            if ($updated > 0) {
                wp_send_json_success(array(
                    'message' => sprintf(
                        _n(
                            '%d setting updated successfully.',
                            '%d settings updated successfully.',
                            $updated,
                            'logiq'
                        ),
                        $updated
                    ),
                    'debug_info' => $debug_info
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'No settings were updated.',
                    'debug_info' => $debug_info
                ));
            }

        } catch (Exception $e) {
            error_log('LogIQ Debug - Fatal error: ' . $e->getMessage());
            error_log('LogIQ Debug - Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'debug_info' => isset($debug_info) ? $debug_info : array()
            ));
        }
    }

    /**
     * Get current debug settings
     */
    public function ajax_get_debug_settings() {
        try {
            // Check nonce first
            if (!check_ajax_referer('logiq_ajax', '_ajax_nonce', false)) {
                wp_send_json_error(array(
                    'message' => 'Invalid security token',
                    'debug' => array(
                        'nonce_received' => isset($_POST['_ajax_nonce']) ? sanitize_text_field($_POST['_ajax_nonce']) : 'none',
                        'nonce_expected' => wp_create_nonce('logiq_ajax')
                    )
                ));
                return;
            }

            // Check capabilities
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => __('Permission denied.', 'logiq')
                ));
                return;
            }

            // Get config path
            $config_path = ABSPATH . 'wp-config.php';
            if (!file_exists($config_path)) {
                $config_path = dirname(ABSPATH) . '/wp-config.php';
            }

            if (!file_exists($config_path)) {
                wp_send_json_error(array(
                    'message' => 'WordPress configuration file not found',
                    'debug' => array(
                        'paths_checked' => array(
                            ABSPATH . 'wp-config.php',
                            dirname(ABSPATH) . '/wp-config.php'
                        )
                    )
                ));
                return;
            }

            if (!is_readable($config_path)) {
                wp_send_json_error(array(
                    'message' => 'WordPress configuration file is not readable',
                    'debug' => array(
                        'path' => $config_path,
                        'permissions' => decoct(fileperms($config_path) & 0777)
                    )
                ));
                return;
            }

            $transformer = new LogIQ_Config_Transformer($config_path);
            $constants = $this->get_debug_constants();
            $settings = array();

            foreach ($constants as $key => $constant) {
                $value = $transformer->exists($key) ? $transformer->get_value($key) : $constant['value'];
                $settings[] = array(
                    'name' => $key,
                    'value' => $value === 'true' || $value === true,
                    'info' => $constant['info']
                );
            }

            wp_send_json_success(array(
                'settings' => $settings,
                'debug' => array(
                    'config_path' => $config_path,
                    'constants_found' => array_keys($constants),
                    'transformer_loaded' => true
                )
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'debug' => array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                )
            ));
        }
    }
} 