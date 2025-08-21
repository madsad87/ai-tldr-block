<?php
/**
 * Plugin Name: Minimal Test Block
 * Description: Ultra-minimal test block to debug registration issues
 * Version: 1.0.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Register the minimal test block
function register_minimal_test_block() {
    error_log('MINIMAL TEST: register_minimal_test_block() called');
    
    // Register script
    wp_register_script(
        'minimal-test-block',
        plugin_dir_url(__FILE__) . 'minimal-test.js',
        array('wp-blocks', 'wp-element'),
        '1.0.0',
        true
    );
    
    // Register block type
    register_block_type('minimal/test-block', array(
        'editor_script' => 'minimal-test-block',
    ));
    
    error_log('MINIMAL TEST: Block registration completed');
}
add_action('init', 'register_minimal_test_block');
