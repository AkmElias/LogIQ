jQuery(document).ready(function($) {
    // Current active tab
    let currentLevel = 'all';

    // Load logs on page load
    loadLogs();

    // Tab switching
    $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        currentLevel = $(this).data('level');
        loadLogs(1);
    });

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
                page: page,
                level: currentLevel
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
                    
                    // Update tab counts
                    $('.nav-tab').each(function() {
                        var level = $(this).data('level');
                        var count = response.data.counts[level];
                        $(this).html($(this).text().split('(')[0] + ' (' + count + ')');
                    });
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