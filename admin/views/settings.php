<?php
/**
 * Admin Settings Page Template
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'openai';
$tabs = array(
    'openai' => __('OpenAI Settings', 'ai-tldr-block'),
    'mvdb' => __('MVDB Settings', 'ai-tldr-block'),
    'block' => __('Block Defaults', 'ai-tldr-block')
);

// Get queue status for display
$queue_status = TLDR_Cron::get_queue_status();
$recent_logs = TLDR_Cron::get_recent_logs();
?>

<div class="wrap">
    <h1><?php echo esc_html__('AI TL;DR Settings', 'ai-tldr-block'); ?></h1>
    
    <div class="tldr-admin-header">
        <div class="tldr-admin-info">
            <h2><?php echo esc_html__('AI Post Summary Configuration', 'ai-tldr-block'); ?></h2>
            <p><?php echo esc_html__('Configure your AI-powered post summarization settings below.', 'ai-tldr-block'); ?></p>
        </div>
        
        <div class="tldr-admin-status">
            <div class="tldr-status-card">
                <h3><?php echo esc_html__('Processing Queue', 'ai-tldr-block'); ?></h3>
                <div class="tldr-status-item">
                    <span class="tldr-status-label"><?php echo esc_html__('Pending:', 'ai-tldr-block'); ?></span>
                    <span class="tldr-status-value"><?php echo esc_html($queue_status['total']); ?></span>
                </div>
                <?php if ($queue_status['next_run']): ?>
                <div class="tldr-status-item">
                    <span class="tldr-status-label"><?php echo esc_html__('Next Run:', 'ai-tldr-block'); ?></span>
                    <span class="tldr-status-value"><?php echo esc_html(date('H:i:s', $queue_status['next_run'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_key => $tab_name): ?>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=ai-tldr-settings&tab=' . $tab_key)); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_name); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="tldr-admin-content">
        <?php if ($current_tab === 'openai'): ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('tldr_openai_settings');
                do_settings_sections('tldr_openai_settings');
                submit_button();
                ?>
            </form>
            
        <?php elseif ($current_tab === 'mvdb'): ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('tldr_mvdb_settings');
                do_settings_sections('tldr_mvdb_settings');
                submit_button();
                ?>
            </form>
            
        <?php elseif ($current_tab === 'block'): ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('tldr_block_settings');
                do_settings_sections('tldr_block_settings');
                submit_button();
                ?>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($recent_logs)): ?>
    <div class="tldr-admin-logs">
        <h2><?php echo esc_html__('Recent Activity', 'ai-tldr-block'); ?></h2>
        <div class="tldr-logs-container">
            <?php foreach (array_slice($recent_logs, 0, 10) as $log): ?>
                <div class="tldr-log-entry tldr-log-<?php echo esc_attr($log['status']); ?>">
                    <div class="tldr-log-time">
                        <?php echo esc_html(date('H:i:s', strtotime($log['timestamp']))); ?>
                    </div>
                    <div class="tldr-log-content">
                        <strong><?php echo esc_html__('Post ID:', 'ai-tldr-block'); ?> <?php echo esc_html($log['post_id']); ?></strong>
                        <?php if ($log['status'] === 'success'): ?>
                            <span class="tldr-log-success">✓ <?php echo esc_html__('Summary generated successfully', 'ai-tldr-block'); ?></span>
                        <?php else: ?>
                            <span class="tldr-log-error">✗ <?php echo esc_html($log['result']['error'] ?? __('Unknown error', 'ai-tldr-block')); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.tldr-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.tldr-admin-info h2 {
    margin-top: 0;
    color: #1d2327;
}

.tldr-status-card {
    background: #f6f7f7;
    padding: 15px;
    border-radius: 4px;
    min-width: 200px;
}

.tldr-status-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #1d2327;
}

.tldr-status-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.tldr-status-label {
    font-weight: 500;
}

.tldr-status-value {
    color: #0073aa;
    font-weight: bold;
}

.tldr-admin-content {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.tldr-admin-logs {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.tldr-logs-container {
    max-height: 300px;
    overflow-y: auto;
}

.tldr-log-entry {
    display: flex;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #f0f0f1;
    gap: 15px;
}

.tldr-log-entry:last-child {
    border-bottom: none;
}

.tldr-log-time {
    font-family: monospace;
    color: #646970;
    font-size: 12px;
    min-width: 60px;
}

.tldr-log-content {
    flex: 1;
}

.tldr-log-success {
    color: #00a32a;
    font-size: 12px;
}

.tldr-log-error {
    color: #d63638;
    font-size: 12px;
}

#openai-test-result {
    margin-left: 10px;
    font-weight: bold;
}

#openai-test-result.success {
    color: #00a32a;
}

#openai-test-result.error {
    color: #d63638;
}

@media (max-width: 782px) {
    .tldr-admin-header {
        flex-direction: column;
        gap: 20px;
    }
    
    .tldr-status-card {
        width: 100%;
    }
}
</style>
