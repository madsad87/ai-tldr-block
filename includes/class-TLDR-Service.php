<?php
/**
 * TL;DR Service Class
 * 
 * Handles OpenAI integration and summary generation logic
 */

// Prevent direct access
defined('ABSPATH') || exit;

class TLDR_Service {
    
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
        // Initialize service
    }
    
    /**
     * Generate summary for a post
     * 
     * @param int $post_id Post ID
     * @param array $options Summary options (length, tone, etc.)
     * @return array Result with summary, metadata, and status
     */
    public static function generate_summary($post_id, $options = array()) {
        $start_time = microtime(true);
        
        // Validate post
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'success' => false,
                'error' => 'Post not found'
            );
        }
        
        // Parse options
        $options = wp_parse_args($options, array(
            'length' => 'medium',
            'tone' => 'neutral',
            'force_regenerate' => false
        ));
        
        // Check if summary is pinned and not forcing regeneration
        $is_pinned = get_post_meta($post_id, '_ai_tldr_is_pinned', true);
        if ($is_pinned && !$options['force_regenerate']) {
            $existing_summary = get_post_meta($post_id, '_ai_tldr_summary', true);
            if (!empty($existing_summary)) {
                return array(
                    'success' => true,
                    'summary' => $existing_summary,
                    'source' => get_post_meta($post_id, '_ai_tldr_source', true),
                    'pinned' => true,
                    'cached' => true
                );
            }
        }
        
        // Get content for summarization
        $content_data = TLDR_Content::get_content_for_summary($post_id, $post->post_title);
        
        if (empty($content_data['content'])) {
            return array(
                'success' => false,
                'error' => 'No content available for summarization'
            );
        }
        
        // Generate summary using OpenAI
        $summary_result = self::call_openai_api($content_data['content'], $options);
        
        if (!$summary_result['success']) {
            return $summary_result;
        }
        
        $processing_time = microtime(true) - $start_time;
        
        // Store summary and metadata
        $content_hash = TLDR_Content::generate_content_hash($post_id);
        $generated_at = current_time('mysql');
        
        update_post_meta($post_id, '_ai_tldr_summary', $summary_result['summary']);
        update_post_meta($post_id, '_ai_tldr_source', $content_data['source']);
        update_post_meta($post_id, '_ai_tldr_len', $options['length']);
        update_post_meta($post_id, '_ai_tldr_tone', $options['tone']);
        update_post_meta($post_id, '_ai_tldr_content_hash', $content_hash);
        update_post_meta($post_id, '_ai_tldr_generated_at', $generated_at);
        update_post_meta($post_id, '_ai_tldr_ai_copy', $summary_result['summary']);
        
        // Store token usage if available
        if (isset($summary_result['tokens'])) {
            update_post_meta($post_id, '_ai_tldr_tokens', $summary_result['tokens']);
        }
        
        return array(
            'success' => true,
            'summary' => $summary_result['summary'],
            'source' => $content_data['source'],
            'tokens' => $summary_result['tokens'] ?? null,
            'processing_time' => $processing_time,
            'generated_at' => $generated_at,
            'pinned' => false,
            'cached' => false
        );
    }
    
    /**
     * Call OpenAI API for summary generation
     * 
     * @param string $content Content to summarize
     * @param array $options Summary options
     * @return array API response
     */
    private static function call_openai_api($content, $options) {
        $openai_settings = get_option('tldr_openai_settings', array());
        $api_key = trim($openai_settings['api_key'] ?? '');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'OpenAI API key not configured'
            );
        }
        
        $model = $openai_settings['model'] ?? 'gpt-4o-mini';
        $temperature = $openai_settings['temperature'] ?? 0.3;
        $max_tokens = self::get_max_tokens_for_length($options['length'], $openai_settings);
        
        // Build prompt
        $prompt = self::build_summarization_prompt($content, $options);
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You are a professional content summarizer. Create concise, accurate summaries that capture the key points and main ideas.'
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        // Make API request
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $max_tokens
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'API request failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if ($response_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'API error';
            return array(
                'success' => false,
                'error' => 'OpenAI API error: ' . $error_message
            );
        }
        
        if (empty($data['choices'][0]['message']['content'])) {
            return array(
                'success' => false,
                'error' => 'Empty response from OpenAI'
            );
        }
        
        $summary = trim($data['choices'][0]['message']['content']);
        $tokens = $data['usage']['total_tokens'] ?? null;
        
        return array(
            'success' => true,
            'summary' => $summary,
            'tokens' => $tokens
        );
    }
    
    /**
     * Build summarization prompt based on options
     * 
     * @param string $content Content to summarize
     * @param array $options Summary options
     * @return string Formatted prompt
     */
    private static function build_summarization_prompt($content, $options) {
        $length = $options['length'] ?? 'medium';
        $tone = $options['tone'] ?? 'neutral';
        
        // Length instructions
        $length_instructions = array(
            'short' => 'Create a single sentence summary (approximately 15-25 words) that captures the main point.',
            'medium' => 'Create a concise summary in 2-3 sentences that covers the key points and main ideas.',
            'bullets' => 'Create a bullet-point summary with 4-6 key points, each as a brief, clear statement.'
        );
        
        // Tone instructions
        $tone_instructions = array(
            'neutral' => 'Use a neutral, informative tone suitable for general audiences.',
            'executive' => 'Use a professional, executive tone focusing on key insights and actionable information.',
            'casual' => 'Use a conversational, accessible tone that\'s easy to understand.'
        );
        
        $length_instruction = $length_instructions[$length] ?? $length_instructions['medium'];
        $tone_instruction = $tone_instructions[$tone] ?? $tone_instructions['neutral'];
        
        $prompt = "Please summarize the following content:\n\n";
        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "- " . $length_instruction . "\n";
        $prompt .= "- " . $tone_instruction . "\n";
        $prompt .= "- Focus on the most important information and key takeaways.\n";
        $prompt .= "- Be accurate and avoid adding information not present in the original content.\n\n";
        $prompt .= "CONTENT TO SUMMARIZE:\n";
        $prompt .= $content;
        
        return $prompt;
    }
    
    /**
     * Get max tokens for summary length
     * 
     * @param string $length Length setting
     * @param array $settings OpenAI settings
     * @return int Max tokens
     */
    private static function get_max_tokens_for_length($length, $settings) {
        $defaults = array(
            'short' => 120,
            'medium' => 200,
            'bullets' => 220
        );
        
        $configured = $settings['max_tokens'] ?? array();
        
        return $configured[$length] ?? $defaults[$length] ?? $defaults['medium'];
    }
    
    /**
     * Get summary for post (from cache or generate)
     * 
     * @param int $post_id Post ID
     * @return array Summary data
     */
    public static function get_summary($post_id) {
        $summary = get_post_meta($post_id, '_ai_tldr_summary', true);
        
        if (empty($summary)) {
            return array(
                'exists' => false,
                'summary' => '',
                'metadata' => array()
            );
        }
        
        $metadata = array(
            'source' => get_post_meta($post_id, '_ai_tldr_source', true),
            'is_pinned' => get_post_meta($post_id, '_ai_tldr_is_pinned', true),
            'length' => get_post_meta($post_id, '_ai_tldr_len', true),
            'tone' => get_post_meta($post_id, '_ai_tldr_tone', true),
            'generated_at' => get_post_meta($post_id, '_ai_tldr_generated_at', true),
            'tokens' => get_post_meta($post_id, '_ai_tldr_tokens', true),
            'content_hash' => get_post_meta($post_id, '_ai_tldr_content_hash', true),
            'ai_copy' => get_post_meta($post_id, '_ai_tldr_ai_copy', true)
        );
        
        return array(
            'exists' => true,
            'summary' => $summary,
            'metadata' => $metadata
        );
    }
    
    /**
     * Update summary (manual edit)
     * 
     * @param int $post_id Post ID
     * @param string $summary New summary text
     * @return bool Success
     */
    public static function update_summary($post_id, $summary) {
        $summary = wp_strip_all_tags(trim($summary));
        
        if (empty($summary)) {
            return false;
        }
        
        update_post_meta($post_id, '_ai_tldr_summary', $summary);
        
        return true;
    }
    
    /**
     * Pin/unpin summary
     * 
     * @param int $post_id Post ID
     * @param bool $pinned Pin status
     * @return bool Success
     */
    public static function set_pinned_status($post_id, $pinned) {
        update_post_meta($post_id, '_ai_tldr_is_pinned', $pinned ? 'true' : 'false');
        return true;
    }
    
    /**
     * Revert to AI-generated copy
     * 
     * @param int $post_id Post ID
     * @return bool Success
     */
    public static function revert_to_ai_copy($post_id) {
        $ai_copy = get_post_meta($post_id, '_ai_tldr_ai_copy', true);
        
        if (empty($ai_copy)) {
            return false;
        }
        
        update_post_meta($post_id, '_ai_tldr_summary', $ai_copy);
        
        return true;
    }
    
    /**
     * Delete summary
     * 
     * @param int $post_id Post ID
     * @return bool Success
     */
    public static function delete_summary($post_id) {
        $meta_keys = array(
            '_ai_tldr_summary',
            '_ai_tldr_source',
            '_ai_tldr_is_pinned',
            '_ai_tldr_len',
            '_ai_tldr_tone',
            '_ai_tldr_content_hash',
            '_ai_tldr_generated_at',
            '_ai_tldr_ai_copy',
            '_ai_tldr_tokens'
        );
        
        foreach ($meta_keys as $key) {
            delete_post_meta($post_id, $key);
        }
        
        return true;
    }
    
    /**
     * Test OpenAI API connection
     * 
     * @return array Test result
     */
    public static function test_openai_connection() {
        $openai_settings = get_option('tldr_openai_settings', array());
        $api_key = trim($openai_settings['api_key'] ?? '');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'API key not configured'
            );
        }
        
        $model = $openai_settings['model'] ?? 'gpt-4o-mini';
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode(array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'user', 'content' => 'Test connection')
                ),
                'max_tokens' => 5
            )),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Connection failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => 'Connection successful'
            );
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
        
        return array(
            'success' => false,
            'error' => 'API error: ' . $error_message
        );
    }
}
