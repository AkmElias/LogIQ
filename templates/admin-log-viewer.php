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

    <div id="logiq-log-viewer">
        <!-- Logs will be loaded here via AJAX -->
    </div>

    <div id="logiq-pagination">
        <!-- Pagination will be loaded here via AJAX -->
    </div>
</div> 