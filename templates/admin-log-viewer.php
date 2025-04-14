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
        // Define current level with a default value
        $current_level = isset($_GET['level']) ? sanitize_key($_GET['level']) : 'all';
        
        // Initialize counts array with defaults
        $counts = isset($counts) ? $counts : array_fill_keys(
            ['all', 'fatal', 'error', 'warning', 'notice', 'info', 'debug', 'deprecated'],
            0
        );

        $log_levels = array(
            'all' => __('All Logs', 'logiq'),
            'fatal' => __('Fatal', 'logiq'),
            'error' => __('Errors', 'logiq'),
            'warning' => __('Warnings', 'logiq'),
            'notice' => __('Notices', 'logiq'),
            'info' => __('Info', 'logiq'),
            'debug' => __('Debug', 'logiq'),
            'deprecated' => __('Deprecated', 'logiq')
        );

        foreach ($log_levels as $level => $label) {
            $active = ($current_level === $level) ? 'nav-tab-active' : '';
            $count = isset($counts[$level]) ? absint($counts[$level]) : 0;
            ?>
            <a href="#" 
               class="nav-tab <?php echo esc_attr($active); ?>" 
               data-level="<?php echo esc_attr($level); ?>">
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