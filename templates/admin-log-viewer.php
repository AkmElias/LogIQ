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
        <a href="#" class="nav-tab nav-tab-active" data-level="all"><?php esc_html_e('All Logs', 'logiq'); ?> <span class="count">(0)</span></a>
        <a href="#" class="nav-tab" data-level="fatal"><?php esc_html_e('Fatal', 'logiq'); ?> <span class="count">(0)</span></a>
        <a href="#" class="nav-tab" data-level="error"><?php esc_html_e('Errors', 'logiq'); ?> <span class="count">(0)</span></a>
        <a href="#" class="nav-tab" data-level="warning"><?php esc_html_e('Warnings', 'logiq'); ?> <span class="count">(0)</span></a>
        <a href="#" class="nav-tab" data-level="deprecated"><?php esc_html_e('Deprecated', 'logiq'); ?> <span class="count">(0)</span></a>
        <a href="#" class="nav-tab" data-level="info"><?php esc_html_e('Info', 'logiq'); ?> <span class="count">(0)</span></a>
        <a href="#" class="nav-tab" data-level="debug"><?php esc_html_e('Debug', 'logiq'); ?> <span class="count">(0)</span></a>
    </div>

    <div id="logiq-log-viewer">
        <!-- Logs will be loaded here via AJAX -->
    </div>

    <div id="logiq-pagination">
        <!-- Pagination will be loaded here via AJAX -->
    </div>
</div> 