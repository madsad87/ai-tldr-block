<?php
/**
 * Plugin Name: Minimal Test Block Fixed
 * Description: Fixed version of minimal test block with proper JavaScript loading
 * Version: 1.0.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Register the minimal test block with fixed JavaScript
function register_minimal_test_block_fixed() {
    error_log('MINIMAL TEST FIXED: register_minimal_test_block_fixed() called');
    
    // Register script with correct dependencies
    $script_registered = wp_register_script(
        'minimal-test-block-fixed',
        plugin_dir_url(__FILE__) . 'minimal-test-fixed.js',
        array('wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components'),
        '1.0.0',
        true
    );
    
    if ($script_registered) {
        error_log('MINIMAL TEST FIXED: Script registered successfully');
    } else {
        error_log('MINIMAL TEST FIXED: Script registration failed');
    }
    
    // Register block type
    $block_registered = register_block_type('minimal/test-block-fixed', array(
        'editor_script' => 'minimal-test-block-fixed',
    ));
    
    if ($block_registered) {
        error_log('MINIMAL TEST FIXED: Block type registered successfully');
    } else {
        error_log('MINIMAL TEST FIXED: Block type registration failed');
    }
    
    error_log('MINIMAL TEST FIXED: Block registration completed');
}
add_action('init', 'register_minimal_test_block_fixed');
