/**
 * Admin JavaScript for AI TL;DR Block
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        $('#test-openai-connection').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const $result = $('#openai-test-result');

            $button.prop('disabled', true).text(tldrAdmin.strings.testing);
            $result.removeClass('success error').text('');

            $.ajax({
                url: tldrAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'tldr_test_openai',
                    nonce: tldrAdmin.nonce,
                    api_key: $('input[name="tldr_openai_settings[api_key]"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        const message = response.data && response.data.message ? response.data.message : tldrAdmin.strings.success;
                        $result.addClass('success').text('✓ ' + message);
                        return;
                    }

                    const error = response.data || 'Unknown error';
                    $result.addClass('error').text('✗ ' + tldrAdmin.strings.error + ' ' + error);
                },
                error: function(xhr) {
                    let errorMessage = tldrAdmin.strings.error;

                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage += ' ' + xhr.responseJSON.data;
                    } else if (xhr.status === 0) {
                        errorMessage += ' Network error';
                    } else {
                        errorMessage += ' HTTP ' + xhr.status;
                    }

                    $result.addClass('error').text('✗ ' + errorMessage);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        });

        const $clearQueue = $('#clear-queue');
        if ($clearQueue.length) {
            $clearQueue.on('click', function(e) {
                e.preventDefault();

                if (!confirm('Are you sure you want to clear the processing queue?')) {
                    return;
                }

                const $button = $(this);
                $button.prop('disabled', true).text('Clearing...');

                $.ajax({
                    url: tldrAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'tldr_clear_queue',
                        nonce: tldrAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                            return;
                        }

                        alert('Failed to clear queue: ' + (response.data || 'Unknown error'));
                    },
                    error: function(xhr) {
                        const message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'Failed to clear queue';
                        alert(message);
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Clear Queue');
                    }
                });
            });
        }

        if ($('.tldr-status-card').length) {
            setInterval(function() {
                $.ajax({
                    url: tldrAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'tldr_get_queue_status',
                        nonce: tldrAdmin.nonce
                    },
                    success: function(response) {
                        if (!response.success || !response.data) {
                            return;
                        }

                        $('.tldr-status-value').first().text(response.data.total);

                        if (response.data.next_run) {
                            const nextRun = new Date(response.data.next_run * 1000);
                            $('.tldr-status-value').last().text(nextRun.toLocaleTimeString());
                        }
                    }
                });
            }, 30000);
        }
    });

})(jQuery);
