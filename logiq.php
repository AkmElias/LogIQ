<?php
/**
 * Plugin Name: LogIQ
 * Plugin URI: https://wordpress.org/plugins/logiq/
 * Description: Intelligent Debugging for WordPress Developers
 * Version: 1.0.0
 * Author: A K M Elias
 * Author URI: https://akmelias.com
 * License: GPL-2.0+
 * Text Domain: LogIQ
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LOGIQ_VERSION', '1.0.0');
define('LOGIQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LOGIQ_URL', plugin_dir_url(__FILE__));
define('LOGIQ_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once LOGIQ_PLUGIN_DIR . 'includes/functions-logiq.php';
require_once LOGIQ_PLUGIN_DIR . 'includes/class-logiq-config-transformer.php';
require_once LOGIQ_PLUGIN_DIR . 'includes/class-logiq-admin.php';
require_once LOGIQ_PLUGIN_DIR . 'includes/class-logiq-security.php';

/**
 * Main plugin class
 */
class LogIQ {
    private static $instance = null;
    private $admin;
    private $ajax;
    private $security;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize components
        $this->admin = new LogIQ_Admin();
        $this->security = new LogIQ_Security();

        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Add actions and filters
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_admin_assets'));
        add_action('wp_ajax_logiq_get_logs', array($this->ajax, 'get_logs'));
        add_action('wp_ajax_logiq_clear_logs', array($this->ajax, 'clear_logs'));
        add_action('wp_ajax_logiq_toggle_debug', array($this->admin, 'handle_debug_toggle'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Check system requirements
        $issues = logiq_check_system_compatibility();
        if (!empty($issues)) {
            deactivate_plugins(LOGIQ_PLUGIN_BASENAME);
            wp_die(
                '<h1>' . esc_html__('LogIQ Activation Error', 'LogIQ') . '</h1>' .
                '<p>' . implode('<br>', esc_html($issues)) . '</p>' .
                '<p><a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Return to plugins page', 'LogIQ') . '</a></p>'
            );
        }

        // Set default debug settings
        try {    
            $config_path = ABSPATH . 'wp-config.php';
            if (!file_exists($config_path)) {
                $config_path = dirname(ABSPATH) . '/wp-config.php';
            }

            if (!file_exists($config_path)) {
                return;
            }

            $transformer = new LogIQ_Config_Transformer($config_path);

            // Set WP_DEBUG if not defined
            if (!$transformer->exists('WP_DEBUG')) {
                $transformer->update('WP_DEBUG', true);
            }

            // Set WP_DEBUG_LOG if not defined
            if (!$transformer->exists('WP_DEBUG_LOG')) {
                $transformer->update('WP_DEBUG_LOG', true);
            }

        } catch (Exception $e) {
           throw new Exception(esc_html($e->getMessage()));
        }

        // Register settings
        $this->admin->register_settings();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up any temporary data
        delete_option('logiq_debug_enabled');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'LogIQ',
            false,
            dirname(LOGIQ_PLUGIN_BASENAME) . '/languages/'
        );
    }
}

// Initialize plugin
LogIQ::get_instance(); 