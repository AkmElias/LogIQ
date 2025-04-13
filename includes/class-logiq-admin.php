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
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
            )
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets($hook) {
        if ('tools_page_logiq-debug' !== $hook) {
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

        wp_localize_script(
            'logiq-admin',
            'logiqAdmin',
            array(
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('logiq_admin_nonce'),
                'i18n'      => array(
                    'confirmClear' => __('Are you sure you want to clear all logs?', 'logiq'),
                ),
            )
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'logiq'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('logiq_settings');
                do_settings_sections('logiq_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Debug Mode', 'logiq'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="logiq_debug_enabled" 
                                       value="1" 
                                       <?php checked(get_option('logiq_debug_enabled'), true); ?>>
                                <?php _e('Enable debug logging', 'logiq'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, LogIQ will log debug information to the log file.', 'logiq'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>

            <div class="logiq-actions">
                <button type="button" class="button" id="logiq-refresh-logs">
                    <?php _e('Refresh Logs', 'logiq'); ?>
                </button>
                <button type="button" class="button" id="logiq-clear-logs">
                    <?php _e('Clear Logs', 'logiq'); ?>
                </button>
            </div>

            <div id="logiq-log-viewer">
                <div class="loading"><?php _e('Loading logs...', 'logiq'); ?></div>
            </div>
            <div id="logiq-pagination"></div>
        </div>
        <?php
    }
} 