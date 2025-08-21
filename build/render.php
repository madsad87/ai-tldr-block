<?php
/**
 * Server-side rendering for AI TL;DR Block
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Get block attributes
$post_id = $attributes['postId'] ?? get_the_ID();
$background_color = $attributes['backgroundColor'] ?? '#f8f9fa';
$border_radius = $attributes['borderRadius'] ?? 8;
$expand_threshold = $attributes['expandThreshold'] ?? 300;
$show_metadata = $attributes['showMetadata'] ?? true;
$length = $attributes['length'] ?? 'medium';
$tone = $attributes['tone'] ?? 'neutral';

// Get summary from post meta
$summary = get_post_meta($post_id, '_ai_tldr_summary', true);

// Don't render if no summary
if (empty($summary)) {
    return '';
}

// Get metadata
$metadata = array(
    'source' => get_post_meta($post_id, '_ai_tldr_source', true),
    'generated_at' => get_post_meta($post_id, '_ai_tldr_generated_at', true),
    'tokens' => get_post_meta($post_id, '_ai_tldr_tokens', true),
    'is_pinned' => get_post_meta($post_id, '_ai_tldr_is_pinned', true)
);

// Determine if expand/collapse is needed
$should_show_expand = strlen($summary) > $expand_threshold;

// Build CSS classes
$css_classes = array(
    'ai-tldr-block-frontend',
    'ai-tldr-' . $length,
    'ai-tldr-' . $tone
);

// Enqueue frontend script if expand/collapse is needed
if ($should_show_expand) {
    wp_enqueue_script(
        'ai-tldr-frontend',
        TLDR_PLUGIN_URL . 'build/frontend.js',
        array(),
        TLDR_VERSION,
        true
    );
}

// Format summary for display
$formatted_summary = wp_kses_post($summary);

// Handle bullet points
if ($length === 'bullets' && strpos($formatted_summary, '•') !== false) {
    // Convert bullet points to proper list
    $lines = explode("\n", $formatted_summary);
    $list_items = array();
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Remove bullet characters
        $line = preg_replace('/^[•\-\*]\s*/', '', $line);
        if (!empty($line)) {
            $list_items[] = '<li>' . esc_html($line) . '</li>';
        }
    }
    
    if (!empty($list_items)) {
        $formatted_summary = '<ul>' . implode('', $list_items) . '</ul>';
    }
}

// Format date
if (!function_exists('format_tldr_date')) {
    function format_tldr_date($date_string) {
        if (empty($date_string)) return '';
        
        $date = new DateTime($date_string);
        $now = new DateTime();
        $diff = $now->diff($date);
        
        if ($diff->days == 0) {
            if ($diff->h == 0) {
                return sprintf(_n('%d minute ago', '%d minutes ago', $diff->i, 'ai-tldr-block'), $diff->i);
            }
            return sprintf(_n('%d hour ago', '%d hours ago', $diff->h, 'ai-tldr-block'), $diff->h);
        } elseif ($diff->days == 1) {
            return __('Yesterday', 'ai-tldr-block');
        } elseif ($diff->days < 7) {
            return sprintf(_n('%d day ago', '%d days ago', $diff->days, 'ai-tldr-block'), $diff->days);
        } else {
            return $date->format(get_option('date_format'));
        }
    }
}

// Get source label
if (!function_exists('get_tldr_source_label')) {
    function get_tldr_source_label($source) {
        switch ($source) {
            case 'mvdb':
                return __('Vector Database', 'ai-tldr-block');
            case 'raw':
                return __('Post Content', 'ai-tldr-block');
            default:
                return __('Unknown', 'ai-tldr-block');
        }
    }
}

?>

<div <?php echo get_block_wrapper_attributes(array(
    'class' => implode(' ', $css_classes),
    'style' => sprintf(
        'background-color: %s; border-radius: %dpx; --ai-tldr-bg-color: %s;',
        esc_attr($background_color),
        intval($border_radius),
        esc_attr($background_color)
    )
)); ?>>
    <div class="ai-tldr-summary-container">
        <div class="ai-tldr-header">
            <h4 class="ai-tldr-title">
                <?php echo esc_html__('TL;DR', 'ai-tldr-block'); ?>
                <?php if ($metadata['is_pinned'] === 'true'): ?>
                    <span class="ai-tldr-pinned-indicator" title="<?php echo esc_attr__('This summary is pinned', 'ai-tldr-block'); ?>">📌</span>
                <?php endif; ?>
            </h4>
        </div>
        
        <div class="ai-tldr-content">
            <div class="ai-tldr-summary<?php echo $should_show_expand ? ' ai-tldr-expandable' : ''; ?>"
                 <?php if ($should_show_expand): ?>data-expand-threshold="<?php echo esc_attr($expand_threshold); ?>"<?php endif; ?>>
                <?php echo $formatted_summary; ?>
            </div>
            
            <?php if ($should_show_expand): ?>
                <button class="ai-tldr-expand-toggle"
                        aria-expanded="false"
                        data-expand-text="<?php echo esc_attr__('Read more', 'ai-tldr-block'); ?>"
                        data-collapse-text="<?php echo esc_attr__('Read less', 'ai-tldr-block'); ?>">
                    <?php echo esc_html__('Read more', 'ai-tldr-block'); ?>
                </button>
            <?php endif; ?>
            
            <?php if ($show_metadata && !empty($metadata['generated_at'])): ?>
                <div class="ai-tldr-metadata">
                    <small>
                        <?php
                        $metadata_parts = array();
                        
                        if (!empty($metadata['generated_at'])) {
                            $metadata_parts[] = sprintf(
                                __('Generated %s', 'ai-tldr-block'),
                                format_tldr_date($metadata['generated_at'])
                            );
                        }
                        
                        if (!empty($metadata['source'])) {
                            $metadata_parts[] = sprintf(
                                __('from %s', 'ai-tldr-block'),
                                get_tldr_source_label($metadata['source'])
                            );
                        }
                        
                        if (!empty($metadata['tokens'])) {
                            $metadata_parts[] = sprintf(
                                __('%d tokens', 'ai-tldr-block'),
                                intval($metadata['tokens'])
                            );
                        }
                        
                        echo esc_html(implode(' • ', $metadata_parts));
                        ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
