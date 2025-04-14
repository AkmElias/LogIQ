<?php
/**
 * Plugin Name: LogIQ
 * Plugin URI: https://wordpress.org/plugins/logiq/
 * Description: Intelligent Debugging for WordPress Developers
 * Version: 1.0.0
 * Author: A K M Elias
 * Author URI: https://akmelias.com
 * Text Domain: logiq
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
require_once LOGIQ_PLUGIN_DIR . 'includes/class-logiq-admin.php';
require_once LOGIQ_PLUGIN_DIR . 'includes/class-logiq-ajax.php';
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
        $this->ajax = new LogIQ_Ajax();
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
                '<h1>' . __('LogIQ Activation Error', 'logiq') . '</h1>' .
                '<p>' . implode('<br>', $issues) . '</p>' .
                '<p><a href="' . admin_url('plugins.php') . '">' . __('Return to plugins page', 'logiq') . '</a></p>'
            );
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
            'logiq',
            false,
            dirname(LOGIQ_PLUGIN_BASENAME) . '/languages/'
        );
    }
}

// Initialize plugin
LogIQ::get_instance(); 