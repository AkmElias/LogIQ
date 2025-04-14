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
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_management_page(
            __('LogIQ Debug', 'logiq'),
            __('LogIQ Debug', 'logiq'),
            'manage_options',
            'logiq-debug',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'logiq_settings',
            'logiq_debug_enabled',
            array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'handle_debug_toggle'),
                'default' => true,
            )
        );
    }

    /**
     * Handle debug toggle and update WP_DEBUG_LOG
     */
    public function handle_debug_toggle($value) {
        $value = (bool) $value;
        
        // Get the wp-config.php file path
        $config_file = ABSPATH . 'wp-config.php';
        
        if (file_exists($config_file)) {
            $config_content = file_get_contents($config_file);
            
            if ($value) {
                // Enable debug logging
                if (!defined('WP_DEBUG_LOG')) {
                    // Add WP_DEBUG_LOG if not defined
                    $config_content = preg_replace(
                        "/(define\s*\(\s*'WP_DEBUG'\s*,\s*)(?:true|false)(\s*\)\s*;)/i",
                        "$1true$2\ndefine('WP_DEBUG_LOG', true);",
                        $config_content
                    );
                } else {
                    // Update existing WP_DEBUG_LOG
                    $config_content = preg_replace(
                        "/(define\s*\(\s*'WP_DEBUG_LOG'\s*,\s*)(?:true|false)(\s*\)\s*;)/i",
                        "$1true$2",
                        $config_content
                    );
                }
            } else {
                // Disable debug logging
                if (defined('WP_DEBUG_LOG')) {
                    $config_content = preg_replace(
                        "/(define\s*\(\s*'WP_DEBUG_LOG'\s*,\s*)(?:true|false)(\s*\)\s*;)/i",
                        "$1false$2",
                        $config_content
                    );
                }
            }
            
            // Write the changes back to wp-config.php
            if (is_writable($config_file)) {
                file_put_contents($config_file, $config_content);
            }
        }
        
        return $value;
    }

    /**
     * Check if current page is a LogIQ page
     */
    private function is_logiq_page() {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'logiq') !== false;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (!$this->is_logiq_page()) {
            return;
        }

        wp_enqueue_style(
            'logiq-admin',
            LOGIQ_PLUGIN_URL . 'assets/admin.css',
            array(),
            LOGIQ_VERSION
        );

        wp_enqueue_script(
            'logiq-admin',
            LOGIQ_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            LOGIQ_VERSION,
            true
        );

        wp_localize_script('logiq-admin', 'logiqAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('logiq_admin_nonce'),
            'i18n' => array(
                'confirmClear' => __('Are you sure you want to clear all logs?', 'logiq')
            )
        ));
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'logiq'));
        }
        
        // Check if wp-config.php is writable
        $config_file = ABSPATH . 'wp-config.php';
        $config_writable = is_writable($config_file);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (!$config_writable): ?>
            <div class="notice notice-warning">
                <p><?php _e('Warning: wp-config.php is not writable. Debug settings may need to be updated manually.', 'logiq'); ?></p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('logiq_settings');
                do_settings_sections('logiq_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Debug Logging', 'logiq'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="logiq_debug_enabled" 
                                       value="1" 
                                       <?php checked(get_option('logiq_debug_enabled', true)); ?>>
                                <?php _e('Enable WordPress debug logging', 'logiq'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, WordPress will log debug information to the debug.log file.', 'logiq'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>

            <?php include LOGIQ_PLUGIN_DIR . 'templates/admin-log-viewer.php'; ?>
        </div>
        <?php
    }
} 