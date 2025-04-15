jQuery(document).ready(function($) {
    var refreshInterval;
    var currentLevel = 'all';
    var currentPage = 1;

    // Function to load logs
    function loadLogs(page, level) {
        $.ajax({
            url: logiq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'logiq_get_logs',
                page: page || 1,
                level: level || 'all',
                _ajax_nonce: logiq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#logiq-entries').html(response.data.html);
                    $('#logiq-pagination').html(response.data.pagination);
                    updateCountBadges(response.data.counts);
                    updateDebugInfo(response.data.debug_info);
                }
            }
        });
    }

    // Initial load
    loadLogs(1, 'all');

    // Auto-refresh every 30 seconds
    refreshInterval = setInterval(function() {
        loadLogs(currentPage, currentLevel);
    }, 30000);

    // Handle level filter clicks
    $('.logiq-level-filter').on('click', function(e) {
        e.preventDefault();
        currentLevel = $(this).data('level');
        currentPage = 1;
        loadLogs(currentPage, currentLevel);
        
        // Update active state
        $('.logiq-level-filter').removeClass('active');
        $(this).addClass('active');
    });

    // Handle pagination clicks
    $(document).on('click', '.tablenav-pages a', function(e) {
        e.preventDefault();
        currentPage = $(this).data('page');
        loadLogs(currentPage, currentLevel);
    });

    // Handle refresh button
    $('#logiq-refresh-logs').on('click', function(e) {
        e.preventDefault();
        loadLogs(currentPage, currentLevel);
    });

    // Handle clear logs
    $('#logiq-clear-logs').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to clear all logs?')) {
            return;
        }

        $.ajax({
            url: logiq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'logiq_clear_logs',
                _ajax_nonce: logiq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadLogs(1, 'all');
                } else {
                    alert(response.data.message || 'Failed to clear logs.');
                }
            }
        });
    });

    // Handle editor link clicks
    $(document).on('click', '.logiq-editor-link', function(e) {
        e.preventDefault();
        
        const editorData = $(this).data('editor');
        if (!editorData || !editorData.file) {
            console.error('Invalid editor data:', editorData);
            return;
        }

        openInEditor(editorData.file, editorData.line || 1);
    });

    // Handle debug toggle
    $('input[name="logiq_debug_enabled"]').on('change', function() {
        var isEnabled = $(this).prop('checked');
        
        $.ajax({
            url: logiq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'logiq_toggle_debug',
                enabled: isEnabled,
                _ajax_nonce: logiq_ajax.nonce
            },
            success: function(response) {
                if (!response.success) {
                    alert(response.data || 'Failed to update debug settings.');
                    // Revert checkbox state
                    $('input[name="logiq_debug_enabled"]').prop('checked', !isEnabled);
                }
            },
            error: function() {
                alert('Failed to update debug settings.');
                // Revert checkbox state
                $('input[name="logiq_debug_enabled"]').prop('checked', !isEnabled);
            }
        });
    });

    // Update count badges
    function updateCountBadges(counts) {
        Object.keys(counts).forEach(function(level) {
            $('.logiq-level-filter[data-level="' + level + '"] .count')
                .text('(' + counts[level] + ')');
        });
    }

    // Update debug information
    function updateDebugInfo(debug) {
        if (debug) {
            var info = [
                'Total entries: ' + debug.total_raw_entries,
                'Parsed entries: ' + debug.total_parsed_entries,
                'Filtered entries: ' + debug.filtered_entries,
                'Page ' + debug.current_page + ' of ' + debug.total_pages,
                'Log file: ' + debug.log_file,
                'Size: ' + formatBytes(debug.log_file_size),
                'Modified: ' + debug.log_file_modified
            ];
            $('#logiq-debug-info').html(info.join(' | '));
        }
    }

    // Format bytes to human readable size
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function openInEditor(file, line) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'logiq_open_in_editor',
                file: file,
                line: line,
                _ajax_nonce: logiq_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.editor_url) {
                    try {
                        // Try to open with location.href first
                        window.location.href = response.data.editor_url;
                    } catch (error) {
                        // If that fails, try window.open
                        try {
                            window.open(response.data.editor_url, '_blank');
                        } catch (error) {
                            alert('Failed to open editor. Please check your editor settings.');
                        }
                    }
                } else {
                    alert('Failed to open editor. Please check your editor settings.');
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to open editor. Please check your editor settings.');
            }
        });
    }

    // Debug Settings Handler
    (function($) {
        'use strict';

        // Load initial settings
        function loadDebugSettings() {
            $.ajax({
                url: logiq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'logiq_get_debug_settings',
                    _ajax_nonce: logiq_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.settings) {
                        response.data.settings.forEach(function(setting) {
                            $('input[name="' + setting.name + '"]').prop('checked', setting.value);
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load debug settings:', error);
                }
            });
        }

        // Save all settings
        function saveDebugSettings() {
            var settings = [];
            $('.logiq-debug-setting').each(function() {
                settings.push({
                    name: $(this).attr('name'),
                    value: $(this).is(':checked')
                });
            });

            $.ajax({
                url: logiq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'logiq_update_debug_settings',
                    _ajax_nonce: logiq_ajax.nonce,
                    settings: JSON.stringify(settings)
                },
                beforeSend: function() {
                    $('#logiq-save-debug-settings').prop('disabled', true)
                        .text(logiq_ajax.strings.saving);
                },
                success: function(response) {
                    if (response.success) {
                        var $notice = $('<div class="notice notice-success is-dismissible"><p>' + 
                            response.data.message + '</p></div>');
                        $('.wrap h1').after($notice);
                    } else {
                        var $notice = $('<div class="notice notice-error is-dismissible"><p>' + 
                            (response.data || 'Failed to update settings.') + '</p></div>');
                        $('.wrap h1').after($notice);
                    }
                },
                error: function(xhr, status, error) {
                    var $notice = $('<div class="notice notice-error is-dismissible"><p>Error: ' + 
                        (error || 'Failed to update settings.') + '</p></div>');
                    $('.wrap h1').after($notice);
                    console.error('Failed to save settings:', error);
                },
                complete: function() {
                    $('#logiq-save-debug-settings').prop('disabled', false)
                        .text(logiq_ajax.strings.save_changes);
                }
            });
        }

        // Initialize
        $(document).ready(function() {
            if ($('.logiq-settings-section').length) {
                loadDebugSettings();

                // Handle save button click
                $('#logiq-save-debug-settings').on('click', function(e) {
                    e.preventDefault();
                    saveDebugSettings();
                });
            }
        });

    })(jQuery);
}); 