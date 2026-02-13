<?php
/**
 * REST API Handler Class
 * 
 * Handles REST endpoints for summary generation and management
 */

// Prevent direct access
defined('ABSPATH') || exit;

class TLDR_Rest {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Rate limiting cache
     */
    private static $rate_limit_cache = array();
    
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
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Generate summary endpoint
        register_rest_route('ai-tldr/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_summary'),
            'permission_callback' => array($this, 'check_edit_post_permission'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_post_id')
                ),
                'length' => array(
                    'required' => false,
                    'type' => 'string',
                    'enum' => array('short', 'medium', 'bullets'),
                    'default' => 'medium'
                ),
                'tone' => array(
                    'required' => false,
                    'type' => 'string',
                    'enum' => array('neutral', 'executive', 'casual'),
                    'default' => 'neutral'
                ),
                'force_regenerate' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false
                )
            )
        ));
        
        // Get summary endpoint
        register_rest_route('ai-tldr/v1', '/summary/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_summary'),
            'permission_callback' => array($this, 'check_read_post_permission'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_post_id')
                )
            )
        ));
        
        // Update summary endpoint
        register_rest_route('ai-tldr/v1', '/update', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_summary'),
            'permission_callback' => array($this, 'check_edit_post_permission'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_post_id')
                ),
                'summary' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                ),
                'auto_regen' => array(
                    'required' => false,
                    'type' => 'boolean'
                )
            )
        ));
        
        // Pin/unpin summary endpoint
        register_rest_route('ai-tldr/v1', '/pin', array(
            'methods' => 'POST',
            'callback' => array($this, 'toggle_pin'),
            'permission_callback' => array($this, 'check_edit_post_permission'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_post_id')
                ),
                'pinned' => array(
                    'required' => true,
                    'type' => 'boolean'
                )
            )
        ));
        
        // Revert to AI copy endpoint
        register_rest_route('ai-tldr/v1', '/revert', array(
            'methods' => 'POST',
            'callback' => array($this, 'revert_to_ai'),
            'permission_callback' => array($this, 'check_edit_post_permission'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_post_id')
                )
            )
        ));
        
        // Delete summary endpoint
        register_rest_route('ai-tldr/v1', '/delete', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_summary'),
            'permission_callback' => array($this, 'check_edit_post_permission'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_post_id')
                )
            )
        ));
        
        // Test OpenAI connection endpoint
        register_rest_route('ai-tldr/v1', '/test-openai', array(
            'methods' => 'GET',
            'callback' => array($this, 'test_openai'),
            'permission_callback' => array($this, 'check_manage_options_permission')
        ));
    }
    
    /**
     * Generate summary endpoint handler
     */
    public function generate_summary($request) {
        $post_id = $request->get_param('post_id');
        $length = $request->get_param('length');
        $tone = $request->get_param('tone');
        $force_regenerate = $request->get_param('force_regenerate');
        
        // Check rate limiting
        if (!$this->check_rate_limit($post_id, 'generate')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Rate limit exceeded. Please wait before generating another summary.'
            ), 429);
        }
        
        // Verify nonce
        if (!$this->verify_nonce($request)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid nonce'
            ), 403);
        }
        
        $options = array(
            'length' => $length,
            'tone' => $tone,
            'force_regenerate' => $force_regenerate
        );
        
        $result = TLDR_Service::generate_summary($post_id, $options);
        
        if ($result['success']) {
            return new WP_REST_Response($result, 200);
        } else {
            return new WP_REST_Response($result, 400);
        }
    }
    
    /**
     * Get summary endpoint handler
     */
    public function get_summary($request) {
        $post_id = $request->get_param('post_id');
        
        $result = TLDR_Service::get_summary($post_id);
        
        return new WP_REST_Response($result, 200);
    }
    
    /**
     * Update summary endpoint handler
     */
    public function update_summary($request) {
        $post_id = $request->get_param('post_id');
        $summary = $request->get_param('summary');
        $auto_regen = $request->get_param('auto_regen');
        
        // Verify nonce
        if (!$this->verify_nonce($request)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid nonce'
            ), 403);
        }
        
        if ($summary !== null) {
            $summary_success = TLDR_Service::update_summary($post_id, $summary);

            if (!$summary_success) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'Failed to update summary'
                ), 400);
            }
        }

        if ($auto_regen !== null) {
            TLDR_Service::set_auto_regen_status($post_id, $auto_regen);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Summary updated successfully',
            'auto_regen' => TLDR_Service::get_auto_regen_status($post_id) ? 'true' : 'false'
        ), 200);
    }
    
    /**
     * Toggle pin endpoint handler
     */
    public function toggle_pin($request) {
        $post_id = $request->get_param('post_id');
        $pinned = $request->get_param('pinned');
        
        // Verify nonce
        if (!$this->verify_nonce($request)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid nonce'
            ), 403);
        }
        
        $success = TLDR_Service::set_pinned_status($post_id, $pinned);
        
        if ($success) {
            return new WP_REST_Response(array(
                'success' => true,
                'pinned' => $pinned,
                'message' => $pinned ? 'Summary pinned' : 'Summary unpinned'
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Failed to update pin status'
            ), 400);
        }
    }
    
    /**
     * Revert to AI copy endpoint handler
     */
    public function revert_to_ai($request) {
        $post_id = $request->get_param('post_id');
        
        // Verify nonce
        if (!$this->verify_nonce($request)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid nonce'
            ), 403);
        }
        
        $success = TLDR_Service::revert_to_ai_copy($post_id);
        
        if ($success) {
            $summary_data = TLDR_Service::get_summary($post_id);
            return new WP_REST_Response(array(
                'success' => true,
                'summary' => $summary_data['summary'],
                'message' => 'Reverted to AI-generated copy'
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'No AI copy available to revert to'
            ), 400);
        }
    }
    
    /**
     * Delete summary endpoint handler
     */
    public function delete_summary($request) {
        $post_id = $request->get_param('post_id');
        
        // Verify nonce
        if (!$this->verify_nonce($request)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid nonce'
            ), 403);
        }
        
        $success = TLDR_Service::delete_summary($post_id);
        
        if ($success) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Summary deleted successfully'
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Failed to delete summary'
            ), 400);
        }
    }
    
    /**
     * Test OpenAI connection endpoint handler
     */
    public function test_openai($request) {
        $result = TLDR_Service::test_openai_connection();
        
        if ($result['success']) {
            return new WP_REST_Response($result, 200);
        } else {
            return new WP_REST_Response($result, 400);
        }
    }
    
    /**
     * Check if user can edit the specific post
     */
    public function check_edit_post_permission($request) {
        $post_id = $request->get_param('post_id');
        
        if (!$post_id) {
            return false;
        }
        
        return current_user_can('edit_post', $post_id);
    }
    
    /**
     * Check if user can read the specific post
     */
    public function check_read_post_permission($request) {
        $post_id = $request->get_param('post_id');
        
        if (!$post_id) {
            return false;
        }
        
        return current_user_can('read_post', $post_id);
    }
    
    /**
     * Check if user can manage options
     */
    public function check_manage_options_permission($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * Validate post ID parameter
     */
    public function validate_post_id($param, $request, $key) {
        $post = get_post($param);
        return $post !== null;
    }
    
    /**
     * Verify nonce for security
     */
    private function verify_nonce($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        
        if (!$nonce) {
            return false;
        }
        
        return wp_verify_nonce($nonce, 'wp_rest');
    }
    
    /**
     * Check rate limiting
     * 
     * @param int $post_id Post ID
     * @param string $action Action type
     * @return bool True if within rate limit
     */
    private function check_rate_limit($post_id, $action) {
        $user_id = get_current_user_id();
        $key = "tldr_rate_limit_{$user_id}_{$action}";
        
        // Get current count from transient
        $current_count = get_transient($key);
        
        if ($current_count === false) {
            // First request in this minute
            set_transient($key, 1, 60); // 1 minute
            return true;
        }
        
        // Check if limit exceeded (3 requests per minute)
        if ($current_count >= 3) {
            return false;
        }
        
        // Increment counter
        set_transient($key, $current_count + 1, 60);
        
        return true;
    }
    
    /**
     * Get rate limit status for user
     * 
     * @param string $action Action type
     * @return array Rate limit info
     */
    public static function get_rate_limit_status($action = 'generate') {
        $user_id = get_current_user_id();
        $key = "tldr_rate_limit_{$user_id}_{$action}";
        
        $current_count = get_transient($key);
        $remaining = max(0, 3 - ($current_count ?: 0));
        
        return array(
            'limit' => 3,
            'used' => $current_count ?: 0,
            'remaining' => $remaining,
            'reset_in' => $current_count ? 60 : 0 // seconds
        );
    }
}
