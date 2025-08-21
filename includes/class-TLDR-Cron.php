<?php
/**
 * Cron Handler Class
 * 
 * Handles background processing and auto-regeneration of summaries
 */

// Prevent direct access
defined('ABSPATH') || exit;

class TLDR_Cron {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Queue option name
     */
    const QUEUE_OPTION = 'tldr_processing_queue';
    
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
        // Register cron hooks
        add_action('tldr_process_queue', array($this, 'process_queue'));
        add_action('tldr_process_single_post', array($this, 'process_single_post'), 10, 1);
        
        // Hook into post save for immediate processing
        add_action('save_post', array($this, 'maybe_queue_post'), 20, 2);
        
        // Add custom cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        // Add 5-minute schedule for more frequent processing
        $schedules['five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'ai-tldr-block')
        );
        
        return $schedules;
    }
    
    /**
     * Queue post for processing
     * 
     * @param int $post_id Post ID
     * @param array $options Processing options
     * @return bool Success
     */
    public static function queue_post_for_processing($post_id, $options = array()) {
        $queue = get_option(self::QUEUE_OPTION, array());
        
        // Default options
        $options = wp_parse_args($options, array(
            'priority' => 'normal', // 'high', 'normal', 'low'
            'retry_count' => 0,
            'max_retries' => 3,
            'queued_at' => current_time('mysql'),
            'length' => get_post_meta($post_id, '_ai_tldr_len', true) ?: 'medium',
            'tone' => get_post_meta($post_id, '_ai_tldr_tone', true) ?: 'neutral'
        ));
        
        // Check if already queued
        foreach ($queue as $item) {
            if ($item['post_id'] == $post_id) {
                return false; // Already queued
            }
        }
        
        // Add to queue
        $queue[] = array(
            'post_id' => $post_id,
            'options' => $options
        );
        
        // Sort by priority
        usort($queue, array(__CLASS__, 'sort_queue_by_priority'));
        
        update_option(self::QUEUE_OPTION, $queue);
        
        // Schedule immediate processing for high priority items
        if ($options['priority'] === 'high') {
            wp_schedule_single_event(time() + 30, 'tldr_process_single_post', array($post_id));
        }
        
        return true;
    }
    
    /**
     * Sort queue by priority
     */
    private static function sort_queue_by_priority($a, $b) {
        $priority_order = array('high' => 1, 'normal' => 2, 'low' => 3);
        
        $a_priority = $priority_order[$a['options']['priority']] ?? 2;
        $b_priority = $priority_order[$b['options']['priority']] ?? 2;
        
        return $a_priority - $b_priority;
    }
    
    /**
     * Process the entire queue
     */
    public function process_queue() {
        $queue = get_option(self::QUEUE_OPTION, array());
        
        if (empty($queue)) {
            return;
        }
        
        $processed = 0;
        $max_per_run = 5; // Process max 5 posts per cron run
        $new_queue = array();
        
        foreach ($queue as $item) {
            if ($processed >= $max_per_run) {
                // Keep remaining items in queue
                $new_queue[] = $item;
                continue;
            }
            
            $post_id = $item['post_id'];
            $options = $item['options'];
            
            // Check if post still exists and is published
            $post = get_post($post_id);
            if (!$post || $post->post_status !== 'publish') {
                continue; // Skip deleted or unpublished posts
            }
            
            // Check if summary is pinned
            $is_pinned = get_post_meta($post_id, '_ai_tldr_is_pinned', true);
            if ($is_pinned === 'true' || $is_pinned === true) {
                continue; // Skip pinned summaries
            }
            
            // Process the post
            $result = $this->process_post($post_id, $options);
            
            if (!$result['success']) {
                // Handle retry logic
                $options['retry_count']++;
                
                if ($options['retry_count'] < $options['max_retries']) {
                    // Re-queue with exponential backoff
                    $delay = pow(2, $options['retry_count']) * 300; // 5min, 10min, 20min
                    wp_schedule_single_event(time() + $delay, 'tldr_process_single_post', array($post_id));
                } else {
                    // Max retries reached, log error
                    error_log("TLDR: Failed to process post {$post_id} after {$options['max_retries']} retries: " . $result['error']);
                }
            }
            
            $processed++;
        }
        
        // Update queue with remaining items
        update_option(self::QUEUE_OPTION, $new_queue);
    }
    
    /**
     * Process a single post
     * 
     * @param int $post_id Post ID
     */
    public function process_single_post($post_id) {
        // Get post from queue or use defaults
        $queue = get_option(self::QUEUE_OPTION, array());
        $options = array();
        
        foreach ($queue as $key => $item) {
            if ($item['post_id'] == $post_id) {
                $options = $item['options'];
                // Remove from queue
                unset($queue[$key]);
                update_option(self::QUEUE_OPTION, array_values($queue));
                break;
            }
        }
        
        // Use defaults if not in queue
        if (empty($options)) {
            $options = array(
                'length' => get_post_meta($post_id, '_ai_tldr_len', true) ?: 'medium',
                'tone' => get_post_meta($post_id, '_ai_tldr_tone', true) ?: 'neutral',
                'retry_count' => 0,
                'max_retries' => 3
            );
        }
        
        $this->process_post($post_id, $options);
    }
    
    /**
     * Process a post for summary generation
     * 
     * @param int $post_id Post ID
     * @param array $options Processing options
     * @return array Result
     */
    private function process_post($post_id, $options) {
        // Check if content has changed
        if (!TLDR_Content::has_content_changed($post_id)) {
            return array(
                'success' => true,
                'message' => 'Content unchanged, skipping'
            );
        }
        
        // Check if auto-regeneration is enabled for this post
        $auto_regen = get_post_meta($post_id, '_ai_tldr_auto_regen', true);
        if ($auto_regen === 'false' || $auto_regen === false) {
            return array(
                'success' => true,
                'message' => 'Auto-regeneration disabled'
            );
        }
        
        // Generate summary
        $generation_options = array(
            'length' => $options['length'],
            'tone' => $options['tone'],
            'force_regenerate' => true
        );
        
        $result = TLDR_Service::generate_summary($post_id, $generation_options);
        
        if ($result['success']) {
            // Log successful processing
            $this->log_processing_result($post_id, 'success', $result);
        } else {
            // Log error
            $this->log_processing_result($post_id, 'error', $result);
        }
        
        return $result;
    }
    
    /**
     * Maybe queue post on save
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function maybe_queue_post($post_id, $post) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only process published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Check if content changed
        if (!TLDR_Content::has_content_changed($post_id)) {
            return;
        }
        
        // Check if auto-regeneration is enabled
        $auto_regen = get_post_meta($post_id, '_ai_tldr_auto_regen', true);
        if ($auto_regen === 'false' || $auto_regen === false) {
            return;
        }
        
        // Check if summary is pinned
        $is_pinned = get_post_meta($post_id, '_ai_tldr_is_pinned', true);
        if ($is_pinned === 'true' || $is_pinned === true) {
            return;
        }
        
        // Queue for processing
        self::queue_post_for_processing($post_id, array('priority' => 'normal'));
    }
    
    /**
     * Get queue status
     * 
     * @return array Queue information
     */
    public static function get_queue_status() {
        $queue = get_option(self::QUEUE_OPTION, array());
        
        $status = array(
            'total' => count($queue),
            'by_priority' => array(
                'high' => 0,
                'normal' => 0,
                'low' => 0
            ),
            'oldest_queued' => null,
            'next_run' => wp_next_scheduled('tldr_process_queue')
        );
        
        foreach ($queue as $item) {
            $priority = $item['options']['priority'] ?? 'normal';
            $status['by_priority'][$priority]++;
            
            $queued_at = $item['options']['queued_at'] ?? '';
            if ($queued_at && (!$status['oldest_queued'] || $queued_at < $status['oldest_queued'])) {
                $status['oldest_queued'] = $queued_at;
            }
        }
        
        return $status;
    }
    
    /**
     * Clear the processing queue
     * 
     * @return bool Success
     */
    public static function clear_queue() {
        return update_option(self::QUEUE_OPTION, array());
    }
    
    /**
     * Remove specific post from queue
     * 
     * @param int $post_id Post ID
     * @return bool Success
     */
    public static function remove_from_queue($post_id) {
        $queue = get_option(self::QUEUE_OPTION, array());
        $new_queue = array();
        
        foreach ($queue as $item) {
            if ($item['post_id'] != $post_id) {
                $new_queue[] = $item;
            }
        }
        
        return update_option(self::QUEUE_OPTION, $new_queue);
    }
    
    /**
     * Log processing result
     * 
     * @param int $post_id Post ID
     * @param string $status Status (success/error)
     * @param array $result Processing result
     */
    private function log_processing_result($post_id, $status, $result) {
        $log_entry = array(
            'post_id' => $post_id,
            'status' => $status,
            'timestamp' => current_time('mysql'),
            'result' => $result
        );
        
        // Store in transient for recent activity (24 hours)
        $recent_logs = get_transient('tldr_recent_processing_logs') ?: array();
        array_unshift($recent_logs, $log_entry);
        
        // Keep only last 50 entries
        $recent_logs = array_slice($recent_logs, 0, 50);
        
        set_transient('tldr_recent_processing_logs', $recent_logs, DAY_IN_SECONDS);
    }
    
    /**
     * Get recent processing logs
     * 
     * @return array Recent logs
     */
    public static function get_recent_logs() {
        return get_transient('tldr_recent_processing_logs') ?: array();
    }
    
    /**
     * Schedule queue processing
     */
    public static function schedule_processing() {
        if (!wp_next_scheduled('tldr_process_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'tldr_process_queue');
        }
    }
    
    /**
     * Unschedule queue processing
     */
    public static function unschedule_processing() {
        wp_clear_scheduled_hook('tldr_process_queue');
    }
}
