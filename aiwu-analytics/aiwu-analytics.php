<?php
/**
 * Plugin Name: AIWU Analytics Dashboard
 * Plugin URI: https://aiwuplugin.com
 * Description: Comprehensive analytics dashboard for AIWU plugin usage, conversions, and user behavior
 * Version: 1.0.0
 * Author: AIWU Team
 * Author URI: https://aiwuplugin.com
 * License: GPL v2 or later
 * Text Domain: aiwu-analytics
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIWU_ANALYTICS_VERSION', '1.0.0');
define('AIWU_ANALYTICS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIWU_ANALYTICS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main AIWU Analytics Class
 */
class AIWU_Analytics {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance
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
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_aiwu_get_analytics_data', array($this, 'ajax_get_analytics_data'));
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once AIWU_ANALYTICS_PLUGIN_DIR . 'includes/class-database.php';
        require_once AIWU_ANALYTICS_PLUGIN_DIR . 'includes/class-analytics.php';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'AIWU Analytics',                    // Page title
            'AIWU Analytics',                    // Menu title
            'manage_options',                    // Capability
            'aiwu-analytics',                    // Menu slug
            array($this, 'render_dashboard'),    // Callback
            'dashicons-chart-bar',               // Icon
            30                                   // Position
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin page
        if ($hook !== 'toplevel_page_aiwu-analytics') {
            return;
        }
        
        // Enqueue Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );
        
        // Enqueue custom CSS
        wp_enqueue_style(
            'aiwu-analytics-css',
            AIWU_ANALYTICS_PLUGIN_URL . 'assets/css/dashboard.css',
            array(),
            AIWU_ANALYTICS_VERSION
        );
        
        // Enqueue custom JS
        wp_enqueue_script(
            'aiwu-analytics-js',
            AIWU_ANALYTICS_PLUGIN_URL . 'assets/js/dashboard.js',
            array('jquery', 'chartjs'),
            AIWU_ANALYTICS_VERSION,
            true
        );
        
        // Pass data to JS
        wp_localize_script('aiwu-analytics-js', 'aiwuAnalytics', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiwu_analytics_nonce')
        ));
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Load dashboard template
        include AIWU_ANALYTICS_PLUGIN_DIR . 'templates/dashboard.php';
    }
    
    /**
     * AJAX handler for getting analytics data
     */
    public function ajax_get_analytics_data() {
        // Check nonce
        check_ajax_referer('aiwu_analytics_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get filters from request
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        // Get analytics data
        $analytics = new AIWU_Analytics_Calculator();
        $data = $analytics->get_dashboard_data($date_from, $date_to);

        // Return data
        wp_send_json_success($data);
    }
}

/**
 * Initialize plugin
 */
function aiwu_analytics_init() {
    return AIWU_Analytics::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'aiwu_analytics_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    // Check if wp_lms_stats tables exist
    global $wpdb;
    
    $stats_table = $wpdb->prefix . 'lms_stats';
    $details_table = $wpdb->prefix . 'lms_stats_details';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$stats_table'") != $stats_table) {
        wp_die('Error: wp_lms_stats table not found. Please make sure AIWU plugin is installed.');
    }
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$details_table'") != $details_table) {
        wp_die('Error: wp_lms_stats_details table not found. Please make sure AIWU plugin is installed.');
    }
    
    // Set default options
    add_option('aiwu_analytics_version', AIWU_ANALYTICS_VERSION);
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
});
