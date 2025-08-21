<?php
/**
 * Plugin Name: AI Post Summary (TL;DR) Block
 * Plugin URI: https://github.com/madsad87/ai-tldr-block
 * Description: Gutenberg block for generating AI-powered post summaries with WP Engine MVDB integration and OpenAI.
 * Version: 1.0.0
 * Author: Madison Sadler
 * License: GPL v2 or later
 * Text Domain: ai-tldr-block
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('TLDR_PLUGIN_FILE', __FILE__);
define('TLDR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TLDR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TLDR_VERSION', '1.0.0');

/**
 * Main AI TL;DR Block class
 */
class AI_TLDR_Block {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
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
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('ai-tldr-block', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        $this->init_components();
        
        // Register Gutenberg block
        add_action('init', array($this, 'register_block'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once TLDR_PLUGIN_DIR . 'includes/class-TLDR-Service.php';
        require_once TLDR_PLUGIN_DIR . 'includes/class-TLDR-Rest.php';
        require_once TLDR_PLUGIN_DIR . 'includes/class-TLDR-Cron.php';
        require_once TLDR_PLUGIN_DIR . 'includes/class-TLDR-Content.php';
        require_once TLDR_PLUGIN_DIR . 'includes/class-TLDR-Admin.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize core service
        TLDR_Service::get_instance();
        
        // Initialize REST API
        TLDR_Rest::get_instance();
        
        // Initialize cron handler
        TLDR_Cron::get_instance();
        
        // Initialize content processor
        TLDR_Content::get_instance();
        
        // Initialize admin interface
        if (is_admin()) {
            TLDR_Admin::get_instance();
        }
    }
    
    /**
     * Register Gutenberg block
     */
    public function register_block() {
        // Register block from block.json
        register_block_type(TLDR_PLUGIN_DIR . 'build');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $this->set_default_options();
        
        // Schedule cron events
        if (!wp_next_scheduled('tldr_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'tldr_process_queue');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('tldr_process_queue');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        // Default OpenAI settings
        if (!get_option('tldr_openai_settings')) {
            update_option('tldr_openai_settings', array(
                'api_key' => '',
                'model' => 'gpt-4o-mini',
                'temperature' => 0.3,
                'max_tokens' => array(
                    'short' => 120,
                    'medium' => 200,
                    'bullets' => 220
                )
            ));
        }
        
        // Default MVDB settings
        if (!get_option('tldr_mvdb_settings')) {
            update_option('tldr_mvdb_settings', array(
                'endpoint' => '',
                'api_key' => '',
                'enabled' => false,
                'fallback_enabled' => true
            ));
        }
        
        // Default block settings
        if (!get_option('tldr_block_settings')) {
            update_option('tldr_block_settings', array(
                'default_length' => 'medium',
                'default_tone' => 'neutral',
                'auto_regen_enabled' => true,
                'expand_threshold' => 300
            ));
        }
    }
}

// Initialize the plugin
AI_TLDR_Block::get_instance();
