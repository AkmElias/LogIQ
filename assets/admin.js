jQuery(document).ready(function($) {
    // Load logs on page load
    loadLogs();

    // Refresh logs button
    $('#logiq-refresh-logs').on('click', function() {
        loadLogs();
    });

    // Clear logs button
    $('#logiq-clear-logs').on('click', function() {
        if (confirm(logiqAdmin.i18n.confirmClear)) {
            clearLogs();
        }
    });

    // Handle pagination clicks
    $(document).on('click', '.pagination-links a', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        loadLogs(page);
    });

    /**
     * Load logs via AJAX
     * 
     * @param {number} page Page number to load
     */
    function loadLogs(page = 1) {
        $.ajax({
            url: logiqAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'logiq_get_logs',
                nonce: logiqAdmin.nonce,
                page: page
            },
            beforeSend: function() {
                $('#logiq-log-viewer').html('<p class="description">Loading logs...</p>');
            },
            success: function(response) {
                if (response.success) {
                    $('#logiq-log-viewer').html(response.data.html);
                    if (response.data.pagination) {
                        $('#logiq-pagination').html(response.data.pagination);
                    } else {
                        $('#logiq-pagination').empty();
                    }
                } else {
                    $('#logiq-log-viewer').html('<p class="error">' + response.data + '</p>');
                }
            },
            error: function() {
                $('#logiq-log-viewer').html('<p class="error">Failed to load logs. Please try again.</p>');
            }
        });
    }

    /**
     * Clear logs via AJAX
     */
    function clearLogs() {
        $.ajax({
            url: logiqAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'logiq_clear_logs',
                nonce: logiqAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadLogs();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('Failed to clear logs. Please try again.');
            }
        });
    }
}); 