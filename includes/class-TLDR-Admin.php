<?php
/**
 * Admin Interface Class
 * 
 * Handles admin settings page and configuration
 */

// Prevent direct access
defined('ABSPATH') || exit;

class TLDR_Admin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_tldr_test_openai', array($this, 'test_openai_connection'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('AI TL;DR Settings', 'ai-tldr-block'),
            __('AI TL;DR', 'ai-tldr-block'),
            'manage_options',
            'ai-tldr-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // OpenAI Settings
        register_setting('tldr_openai_settings', 'tldr_openai_settings', array(
            'sanitize_callback' => array($this, 'sanitize_openai_settings')
        ));
        
        add_settings_section(
            'tldr_openai_section',
            __('OpenAI Configuration', 'ai-tldr-block'),
            array($this, 'openai_section_callback'),
            'tldr_openai_settings'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'ai-tldr-block'),
            array($this, 'api_key_field'),
            'tldr_openai_settings',
            'tldr_openai_section'
        );
        
        add_settings_field(
            'model',
            __('Model', 'ai-tldr-block'),
            array($this, 'model_field'),
            'tldr_openai_settings',
            'tldr_openai_section'
        );
        
        add_settings_field(
            'temperature',
            __('Temperature', 'ai-tldr-block'),
            array($this, 'temperature_field'),
            'tldr_openai_settings',
            'tldr_openai_section'
        );
        
        // MVDB Settings
        register_setting('tldr_mvdb_settings', 'tldr_mvdb_settings', array(
            'sanitize_callback' => array($this, 'sanitize_mvdb_settings')
        ));
        
        add_settings_section(
            'tldr_mvdb_section',
            __('MVDB Configuration', 'ai-tldr-block'),
            array($this, 'mvdb_section_callback'),
            'tldr_mvdb_settings'
        );
        
        add_settings_field(
            'enabled',
            __('Enable MVDB', 'ai-tldr-block'),
            array($this, 'mvdb_enabled_field'),
            'tldr_mvdb_settings',
            'tldr_mvdb_section'
        );
        
        add_settings_field(
            'endpoint',
            __('Endpoint URL', 'ai-tldr-block'),
            array($this, 'mvdb_endpoint_field'),
            'tldr_mvdb_settings',
            'tldr_mvdb_section'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'ai-tldr-block'),
            array($this, 'mvdb_api_key_field'),
            'tldr_mvdb_settings',
            'tldr_mvdb_section'
        );
        
        // Block Settings
        register_setting('tldr_block_settings', 'tldr_block_settings', array(
            'sanitize_callback' => array($this, 'sanitize_block_settings')
        ));
        
        add_settings_section(
            'tldr_block_section',
            __('Block Defaults', 'ai-tldr-block'),
            array($this, 'block_section_callback'),
            'tldr_block_settings'
        );
        
        add_settings_field(
            'default_length',
            __('Default Length', 'ai-tldr-block'),
            array($this, 'default_length_field'),
            'tldr_block_settings',
            'tldr_block_section'
        );
        
        add_settings_field(
            'default_tone',
            __('Default Tone', 'ai-tldr-block'),
            array($this, 'default_tone_field'),
            'tldr_block_settings',
            'tldr_block_section'
        );
        
        add_settings_field(
            'auto_regen_enabled',
            __('Auto-regeneration', 'ai-tldr-block'),
            array($this, 'auto_regen_field'),
            'tldr_block_settings',
            'tldr_block_section'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_ai-tldr-settings') {
            return;
        }
        
        wp_enqueue_style(
            'tldr-admin-style',
            TLDR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TLDR_VERSION
        );
        
        wp_enqueue_script(
            'tldr-admin-script',
            TLDR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            TLDR_VERSION,
            true
        );
        
        wp_localize_script('tldr-admin-script', 'tldrAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tldr_admin_nonce'),
            'restUrl' => rest_url('ai-tldr/v1/'),
            'strings' => array(
                'testing' => __('Testing...', 'ai-tldr-block'),
                'success' => __('Connection successful!', 'ai-tldr-block'),
                'error' => __('Connection failed:', 'ai-tldr-block')
            )
        ));
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        include TLDR_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Section callbacks
     */
    public function openai_section_callback() {
        echo '<p>' . __('Configure your OpenAI API settings for summary generation.', 'ai-tldr-block') . '</p>';
    }
    
    public function mvdb_section_callback() {
        echo '<p>' . __('Configure WP Engine Managed Vector Database integration (optional).', 'ai-tldr-block') . '</p>';
    }
    
    public function block_section_callback() {
        echo '<p>' . __('Set default values for new AI TL;DR blocks.', 'ai-tldr-block') . '</p>';
    }
    
    /**
     * Field callbacks
     */
    public function api_key_field() {
        $settings = get_option('tldr_openai_settings', array());
        $value = isset($settings['api_key']) ? $settings['api_key'] : '';
        
        echo '<input type="password" name="tldr_openai_settings[api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your OpenAI API key. Get one from platform.openai.com', 'ai-tldr-block') . '</p>';
        echo '<button type="button" class="button" id="test-openai-connection">' . __('Test Connection', 'ai-tldr-block') . '</button>';
        echo '<span id="openai-test-result"></span>';
    }
    
    public function model_field() {
        $settings = get_option('tldr_openai_settings', array());
        $value = isset($settings['model']) ? $settings['model'] : 'gpt-4o-mini';
        
        $models = array(
            'gpt-4o-mini' => 'GPT-4o Mini (Recommended)',
            'gpt-4o' => 'GPT-4o',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
        );
        
        echo '<select name="tldr_openai_settings[model]">';
        foreach ($models as $model_key => $model_name) {
            echo '<option value="' . esc_attr($model_key) . '"' . selected($value, $model_key, false) . '>' . esc_html($model_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('The OpenAI model to use for summary generation.', 'ai-tldr-block') . '</p>';
    }
    
    public function temperature_field() {
        $settings = get_option('tldr_openai_settings', array());
        $value = isset($settings['temperature']) ? $settings['temperature'] : 0.3;
        
        echo '<input type="number" name="tldr_openai_settings[temperature]" value="' . esc_attr($value) . '" min="0" max="1" step="0.1" />';
        echo '<p class="description">' . __('Controls randomness. Lower values = more focused, higher values = more creative.', 'ai-tldr-block') . '</p>';
    }
    
    public function mvdb_enabled_field() {
        $settings = get_option('tldr_mvdb_settings', array());
        $value = isset($settings['enabled']) ? $settings['enabled'] : false;
        
        echo '<input type="checkbox" name="tldr_mvdb_settings[enabled]" value="1"' . checked($value, true, false) . ' />';
        echo '<label>' . __('Enable MVDB integration for content grounding', 'ai-tldr-block') . '</label>';
    }
    
    public function mvdb_endpoint_field() {
        $settings = get_option('tldr_mvdb_settings', array());
        $value = isset($settings['endpoint']) ? $settings['endpoint'] : '';
        
        echo '<input type="url" name="tldr_mvdb_settings[endpoint]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your MVDB GraphQL endpoint URL.', 'ai-tldr-block') . '</p>';
    }
    
    public function mvdb_api_key_field() {
        $settings = get_option('tldr_mvdb_settings', array());
        $value = isset($settings['api_key']) ? $settings['api_key'] : '';
        
        echo '<input type="password" name="tldr_mvdb_settings[api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('API key for MVDB authentication (if required).', 'ai-tldr-block') . '</p>';
    }
    
    public function default_length_field() {
        $settings = get_option('tldr_block_settings', array());
        $value = isset($settings['default_length']) ? $settings['default_length'] : 'medium';
        
        $lengths = array(
            'short' => __('Short (1 sentence)', 'ai-tldr-block'),
            'medium' => __('Medium (2-3 sentences)', 'ai-tldr-block'),
            'bullets' => __('Bullets (4-6 points)', 'ai-tldr-block')
        );
        
        echo '<select name="tldr_block_settings[default_length]">';
        foreach ($lengths as $length_key => $length_name) {
            echo '<option value="' . esc_attr($length_key) . '"' . selected($value, $length_key, false) . '>' . esc_html($length_name) . '</option>';
        }
        echo '</select>';
    }
    
    public function default_tone_field() {
        $settings = get_option('tldr_block_settings', array());
        $value = isset($settings['default_tone']) ? $settings['default_tone'] : 'neutral';
        
        $tones = array(
            'neutral' => __('Neutral', 'ai-tldr-block'),
            'executive' => __('Executive', 'ai-tldr-block'),
            'casual' => __('Casual', 'ai-tldr-block')
        );
        
        echo '<select name="tldr_block_settings[default_tone]">';
        foreach ($tones as $tone_key => $tone_name) {
            echo '<option value="' . esc_attr($tone_key) . '"' . selected($value, $tone_key, false) . '>' . esc_html($tone_name) . '</option>';
        }
        echo '</select>';
    }
    
    public function auto_regen_field() {
        $settings = get_option('tldr_block_settings', array());
        $value = isset($settings['auto_regen_enabled']) ? $settings['auto_regen_enabled'] : true;
        
        echo '<input type="checkbox" name="tldr_block_settings[auto_regen_enabled]" value="1"' . checked($value, true, false) . ' />';
        echo '<label>' . __('Enable auto-regeneration by default for new blocks', 'ai-tldr-block') . '</label>';
    }
    
    /**
     * Sanitize callbacks
     */
    public function sanitize_openai_settings($input) {
        $sanitized = array();
        
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        if (isset($input['model'])) {
            $allowed_models = array('gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo');
            $sanitized['model'] = in_array($input['model'], $allowed_models) ? $input['model'] : 'gpt-4o-mini';
        }
        
        if (isset($input['temperature'])) {
            $sanitized['temperature'] = max(0, min(1, floatval($input['temperature'])));
        }
        
        return $sanitized;
    }
    
    public function sanitize_mvdb_settings($input) {
        $sanitized = array();
        
        if (isset($input['enabled'])) {
            $sanitized['enabled'] = (bool) $input['enabled'];
        }
        
        if (isset($input['endpoint'])) {
            $sanitized['endpoint'] = esc_url_raw($input['endpoint']);
        }
        
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        return $sanitized;
    }
    
    public function sanitize_block_settings($input) {
        $sanitized = array();
        
        if (isset($input['default_length'])) {
            $allowed_lengths = array('short', 'medium', 'bullets');
            $sanitized['default_length'] = in_array($input['default_length'], $allowed_lengths) ? $input['default_length'] : 'medium';
        }
        
        if (isset($input['default_tone'])) {
            $allowed_tones = array('neutral', 'executive', 'casual');
            $sanitized['default_tone'] = in_array($input['default_tone'], $allowed_tones) ? $input['default_tone'] : 'neutral';
        }
        
        if (isset($input['auto_regen_enabled'])) {
            $sanitized['auto_regen_enabled'] = (bool) $input['auto_regen_enabled'];
        }
        
        return $sanitized;
    }
    
    /**
     * Test OpenAI connection via AJAX
     */
    public function test_openai_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tldr_admin_nonce')) {
            wp_die(__('Security check failed', 'ai-tldr-block'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-tldr-block'));
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        
        if (empty($api_key)) {
            wp_send_json_error(__('API key is required', 'ai-tldr-block'));
        }
        
        // Test the API key with a simple request
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Test connection'
                    )
                ),
                'max_tokens' => 5
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200) {
            wp_send_json_success(__('OpenAI API connection successful!', 'ai-tldr-block'));
        } elseif ($status_code === 401) {
            wp_send_json_error(__('Invalid API key', 'ai-tldr-block'));
        } elseif ($status_code === 429) {
            wp_send_json_error(__('Rate limit exceeded', 'ai-tldr-block'));
        } else {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown error', 'ai-tldr-block');
            wp_send_json_error($error_message);
        }
    }
}
