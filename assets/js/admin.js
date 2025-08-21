/**
 * Admin JavaScript for AI TL;DR Block
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Test OpenAI connection
        $('#test-openai-connection').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $result = $('#openai-test-result');
            
            // Show loading state
            $button.prop('disabled', true).text(tldrAdmin.strings.testing);
            $result.removeClass('success error').text('');
            
            // Make API request
            $.ajax({
                url: tldrAdmin.restUrl + 'test-openai',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wp.rest.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text('✓ ' + tldrAdmin.strings.success);
                    } else {
                        $result.addClass('error').text('✗ ' + tldrAdmin.strings.error + ' ' + response.error);
                    }
                },
                error: function(xhr) {
                    let errorMessage = tldrAdmin.strings.error;
                    
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage += ' ' + xhr.responseJSON.error;
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

        // Auto-save settings on change (debounced)
        let saveTimeout;
        $('.tldr-admin-content input, .tldr-admin-content select').on('change', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(function() {
                showSaveNotification();
            }, 1000);
        });

        function showSaveNotification() {
            // Create a subtle notification
            const $notification = $('<div class="tldr-save-notification">Settings will be saved when you click "Save Changes"</div>');
            
            if ($('.tldr-save-notification').length === 0) {
                $('.tldr-admin-content').prepend($notification);
                
                setTimeout(function() {
                    $notification.fadeOut(function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        }

        // Enhanced form validation
        $('form').on('submit', function(e) {
            const $form = $(this);
            let hasErrors = false;

            // Validate OpenAI API key format
            const $apiKey = $form.find('input[name="tldr_openai_settings[api_key]"]');
            if ($apiKey.length && $apiKey.val()) {
                const apiKeyValue = $apiKey.val().trim();
                if (apiKeyValue && !apiKeyValue.startsWith('sk-')) {
                    showFieldError($apiKey, 'OpenAI API keys should start with "sk-"');
                    hasErrors = true;
                } else {
                    clearFieldError($apiKey);
                }
            }

            // Validate MVDB endpoint URL
            const $mvdbEndpoint = $form.find('input[name="tldr_mvdb_settings[endpoint]"]');
            if ($mvdbEndpoint.length && $mvdbEndpoint.val()) {
                const urlValue = $mvdbEndpoint.val().trim();
                if (urlValue && !isValidUrl(urlValue)) {
                    showFieldError($mvdbEndpoint, 'Please enter a valid URL');
                    hasErrors = true;
                } else {
                    clearFieldError($mvdbEndpoint);
                }
            }

            // Validate temperature range
            const $temperature = $form.find('input[name="tldr_openai_settings[temperature]"]');
            if ($temperature.length && $temperature.val()) {
                const tempValue = parseFloat($temperature.val());
                if (isNaN(tempValue) || tempValue < 0 || tempValue > 1) {
                    showFieldError($temperature, 'Temperature must be between 0 and 1');
                    hasErrors = true;
                } else {
                    clearFieldError($temperature);
                }
            }

            if (hasErrors) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $('.tldr-field-error:first').offset().top - 100
                }, 500);
            }
        });

        function showFieldError($field, message) {
            clearFieldError($field);
            
            const $error = $('<div class="tldr-field-error">' + message + '</div>');
            $field.addClass('tldr-error').after($error);
        }

        function clearFieldError($field) {
            $field.removeClass('tldr-error').next('.tldr-field-error').remove();
        }

        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }

        // Queue management
        if (typeof tldrAdmin.queueActions !== 'undefined') {
            // Clear queue button
            $('#clear-queue').on('click', function(e) {
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
                        } else {
                            alert('Failed to clear queue: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Failed to clear queue');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Clear Queue');
                    }
                });
            });
        }

        // Tooltips for help text
        $('.tldr-help-tooltip').on('mouseenter', function() {
            const $tooltip = $(this);
            const text = $tooltip.data('tooltip');
            
            if (text) {
                const $bubble = $('<div class="tldr-tooltip-bubble">' + text + '</div>');
                $('body').append($bubble);
                
                const offset = $tooltip.offset();
                $bubble.css({
                    top: offset.top - $bubble.outerHeight() - 10,
                    left: offset.left + ($tooltip.outerWidth() / 2) - ($bubble.outerWidth() / 2)
                });
            }
        }).on('mouseleave', function() {
            $('.tldr-tooltip-bubble').remove();
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                $('.tldr-admin-content form').submit();
            }
        });

        // Auto-refresh queue status every 30 seconds
        if ($('.tldr-status-card').length) {
            setInterval(function() {
                refreshQueueStatus();
            }, 30000);
        }

        function refreshQueueStatus() {
            $.ajax({
                url: tldrAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'tldr_get_queue_status',
                    nonce: tldrAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $('.tldr-status-value').first().text(response.data.total);
                        
                        if (response.data.next_run) {
                            const nextRun = new Date(response.data.next_run * 1000);
                            $('.tldr-status-value').last().text(nextRun.toLocaleTimeString());
                        }
                    }
                }
            });
        }
    });

})(jQuery);
