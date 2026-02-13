<?php
/**
 * Content Processing Class
 * 
 * Handles content normalization, hashing, and preparation for summarization
 */

// Prevent direct access
defined('ABSPATH') || exit;

class TLDR_Content {
    
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
        // Hook into post save to detect content changes
        add_action('save_post', array($this, 'on_post_save'), 10, 2);
    }
    
    /**
     * Normalize post content for processing
     * 
     * @param int $post_id Post ID
     * @return string Normalized content
     */
    public static function normalize_content($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }
        
        // Get post content and title
        $content = $post->post_content;
        $title = $post->post_title;
        
        // Apply content filters (shortcodes, etc.)
        $content = apply_filters('the_content', $content);
        
        // Strip HTML tags and normalize whitespace
        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        // Combine title and content
        $normalized = $title . "\n\n" . $content;
        
        return $normalized;
    }
    
    /**
     * Generate content hash for change detection
     * 
     * @param int $post_id Post ID
     * @return string SHA256 hash
     */
    public static function generate_content_hash($post_id) {
        $content = self::normalize_content($post_id);
        return hash('sha256', $content);
    }
    
    /**
     * Check if post content has changed since last summary
     * 
     * @param int $post_id Post ID
     * @return bool True if content changed
     */
    public static function has_content_changed($post_id) {
        $current_hash = self::generate_content_hash($post_id);
        $stored_hash = get_post_meta($post_id, '_ai_tldr_content_hash', true);
        
        return $current_hash !== $stored_hash;
    }
    
    /**
     * Truncate content for fallback processing
     * 
     * @param string $content Full content
     * @param int $max_chars Maximum characters (default 4000)
     * @return string Truncated content
     */
    public static function truncate_content($content, $max_chars = 4000) {
        if (strlen($content) <= $max_chars) {
            return $content;
        }
        
        // Try to truncate at sentence boundary
        $truncated = substr($content, 0, $max_chars);
        $last_period = strrpos($truncated, '.');
        $last_exclamation = strrpos($truncated, '!');
        $last_question = strrpos($truncated, '?');
        
        $last_sentence = max($last_period, $last_exclamation, $last_question);
        
        if ($last_sentence !== false && $last_sentence > ($max_chars * 0.8)) {
            return substr($truncated, 0, $last_sentence + 1);
        }
        
        // Fallback: truncate at word boundary
        $last_space = strrpos($truncated, ' ');
        if ($last_space !== false) {
            return substr($truncated, 0, $last_space) . '...';
        }
        
        return $truncated . '...';
    }
    
    /**
     * Get content for summarization (MVDB chunks or fallback)
     * 
     * @param int $post_id Post ID
     * @param string $query Search query for MVDB
     * @return array Array with 'content', 'source', and 'chunks'
     */
    public static function get_content_for_summary($post_id, $query = '') {
        // Try MVDB first if enabled
        $mvdb_settings = get_option('tldr_mvdb_settings', array());
        if (!empty($mvdb_settings['enabled']) && !empty($mvdb_settings['endpoint'])) {
            $chunks = self::get_mvdb_chunks($post_id, $query);
            if (!empty($chunks)) {
                $content = self::format_chunks_for_summary($chunks);
                return array(
                    'content' => $content,
                    'source' => 'mvdb',
                    'chunks' => $chunks
                );
            }
        }
        
        // Fallback to raw content
        $raw_content = self::normalize_content($post_id);
        $truncated = self::truncate_content($raw_content, 4000);
        
        return array(
            'content' => $truncated,
            'source' => 'raw',
            'chunks' => array()
        );
    }
    
    /**
     * Get MVDB chunks for post
     * 
     * @param int $post_id Post ID
     * @param string $query Search query
     * @return array MVDB chunks
     */
    private static function get_mvdb_chunks($post_id, $query = '') {
        $mvdb_settings = get_option('tldr_mvdb_settings', array());
        $endpoint = trim($mvdb_settings['endpoint'] ?? '');
        $api_key = trim($mvdb_settings['api_key'] ?? '');
        
        if (empty($endpoint) || empty($api_key)) {
            return array();
        }
        
        // Use post title as query if none provided
        if (empty($query)) {
            $post = get_post($post_id);
            $query = $post ? $post->post_title : '';
        }
        
        if (empty($query)) {
            return array();
        }
        
        // Build GraphQL query
        $graphql_query = '
            query($q: String!, $post_id: Int!) {
                similarity(query: $q) {
                    docs {
                        score
                        data
                    }
                }
            }
        ';
        
        $variables = array(
            'q' => $query,
            'post_id' => $post_id
        );
        
        // Make request
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode(array(
                'query' => $graphql_query,
                'variables' => $variables
            )),
            'timeout' => 20
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['data']['similarity']['docs'])) {
            return array();
        }
        
        return self::normalize_mvdb_chunks($data['data']['similarity']['docs']);
    }
    
    /**
     * Normalize MVDB chunks to consistent format
     * 
     * @param array $raw_chunks Raw MVDB response
     * @return array Normalized chunks
     */
    private static function normalize_mvdb_chunks($raw_chunks) {
        $normalized = array();
        
        foreach ($raw_chunks as $chunk) {
            $data = $chunk['data'] ?? array();
            $score = $chunk['score'] ?? 0;
            
            // Extract content using heuristics
            $content = '';
            $title = '';
            
            // Try different field names for content
            $content_fields = array('post_content', 'content', 'excerpt', 'summary', 'text', 'description', 'body');
            foreach ($content_fields as $field) {
                if (!empty($data[$field])) {
                    $content = is_array($data[$field]) ? $data[$field]['rendered'] ?? '' : $data[$field];
                    break;
                }
            }
            
            // Try different field names for title
            $title_fields = array('post_title', 'title', 'name', 'heading');
            foreach ($title_fields as $field) {
                if (!empty($data[$field])) {
                    $title = is_array($data[$field]) ? $data[$field]['rendered'] ?? '' : $data[$field];
                    break;
                }
            }
            
            if (!empty($content)) {
                $normalized[] = array(
                    'content' => wp_strip_all_tags($content),
                    'title' => wp_strip_all_tags($title),
                    'score' => (float) $score
                );
            }
        }
        
        return $normalized;
    }
    
    /**
     * Format chunks for summary generation
     * 
     * @param array $chunks Normalized chunks
     * @return string Formatted content
     */
    private static function format_chunks_for_summary($chunks) {
        $formatted_chunks = array();
        
        foreach ($chunks as $chunk) {
            $chunk_text = '';
            if (!empty($chunk['title'])) {
                $chunk_text .= "Title: " . $chunk['title'] . "\n";
            }
            $chunk_text .= "Content: " . $chunk['content'];
            $formatted_chunks[] = $chunk_text;
        }
        
        return implode("\n\n---\n\n", $formatted_chunks);
    }
    
    /**
     * Handle post save event
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function on_post_save($post_id, $post) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only process published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Check if content changed
        if (!self::has_content_changed($post_id)) {
            return;
        }
        
        // Check if auto-regeneration is enabled
        if (!TLDR_Service::get_auto_regen_status($post_id)) {
            return;
        }
        
        // Check if summary is pinned
        $is_pinned = get_post_meta($post_id, '_ai_tldr_is_pinned', true);
        if ($is_pinned === 'true' || $is_pinned === true) {
            return;
        }
        
        // Queue for background processing
        TLDR_Cron::queue_post_for_processing($post_id);
    }
}
