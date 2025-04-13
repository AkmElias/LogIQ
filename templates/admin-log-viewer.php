<div class="wrap">
    <h1><?php esc_html_e('LogIQ Debug Logs', 'logiq'); ?></h1>
    
    <div class="logiq-actions">
        <?php wp_nonce_field('logiq_admin_nonce', 'logiq_nonce'); ?>
        <button type="button" id="logiq-refresh-logs" class="button">
            <?php esc_html_e('Refresh Logs', 'logiq'); ?>
        </button>
        <button type="button" id="logiq-clear-logs" class="button button-secondary">
            <?php esc_html_e('Clear Logs', 'logiq'); ?>
        </button>
    </div>

    <div class="nav-tab-wrapper">
        <?php
        $log_levels = array(
            'all' => __('All Logs', 'logiq'),
            'fatal' => __('Fatal', 'logiq'),
            'error' => __('Errors', 'logiq'),
            'warning' => __('Warnings', 'logiq'),
            'info' => __('Info', 'logiq'),
            'debug' => __('Debug', 'logiq'),
            'deprecated' => __('Deprecated', 'logiq')
        );

        foreach ($log_levels as $level => $label) {
            $active = ($current_level === $level) ? 'nav-tab-active' : '';
            $count = isset($counts[$level]) ? $counts[$level] : 0;
            ?>
            <a href="#" class="nav-tab <?php echo $active; ?>" data-level="<?php echo esc_attr($level); ?>">
                <?php echo esc_html($label); ?>
                <span class="count"><?php echo esc_html($count); ?></span>
            </a>
            <?php
        }
        ?>
    </div>

    <div id="logiq-log-viewer">
        <!-- Logs will be loaded here via AJAX -->
    </div>

    <div id="logiq-pagination">
        <!-- Pagination will be loaded here via AJAX -->
    </div>
</div>

<div class="log-entry" data-level="<?php echo esc_attr($log_data['level']); ?>">
    <div class="log-timestamp"><?php echo esc_html($log_data['timestamp']); ?></div>
    <div class="log-level"><?php echo esc_html(strtoupper($log_data['level'])); ?></div>
    <?php if (!empty($log_data['context'])): ?>
        <div class="log-context"><?php echo esc_html($log_data['context']); ?></div>
    <?php endif; ?>
    <div class="log-file"><?php echo esc_html($log_data['file']); ?>:<?php echo absint($log_data['line']); ?></div>
    <div class="log-data"><?php echo LogIQ_Security::sanitize_log_data($log_data['data']); ?></div>
</div> 