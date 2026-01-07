/**
 * WP Custom API - Admin JavaScript
 *
 * @since 2.0.0
 */

(function($) {
    'use strict';

    // Global namespace
    window.wpCustomAPI = window.wpCustomAPI || {};

    /**
     * Initialize admin functionality
     */
    wpCustomAPI.init = function() {
        wpCustomAPI.initTabs();
        wpCustomAPI.initToggles();
        wpCustomAPI.initCodeEditors();
        wpCustomAPI.initAjaxActions();
    };

    /**
     * Initialize tab navigation
     */
    wpCustomAPI.initTabs = function() {
        $('.wp-custom-api-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();

            const $tab = $(this);
            const targetPanel = $tab.data('tab');

            // Update tab active state
            $tab.addClass('nav-tab-active')
                .siblings().removeClass('nav-tab-active');

            // Show target panel
            $('#' + targetPanel).addClass('active')
                .siblings('.wp-custom-api-tab-panel').removeClass('active');
        });
    };

    /**
     * Initialize toggle switches
     */
    wpCustomAPI.initToggles = function() {
        $('.wp-custom-api-toggle input').on('change', function() {
            const $input = $(this);
            const isChecked = $input.is(':checked');

            // Trigger custom event
            $input.trigger('wpCustomAPIToggleChange', [isChecked]);

            // Show/hide dependent fields
            const dependentFields = $input.data('dependent-fields');
            if (dependentFields) {
                const $dependentElements = $(dependentFields);
                if (isChecked) {
                    $dependentElements.slideDown();
                } else {
                    $dependentElements.slideUp();
                }
            }
        });
    };

    /**
     * Initialize code editors
     */
    wpCustomAPI.initCodeEditors = function() {
        if (typeof wp.codeEditor !== 'undefined') {
            $('.wp-custom-api-code-editor').each(function() {
                const $textarea = $(this);
                const editorType = $textarea.data('editor-type') || 'application/json';

                wp.codeEditor.initialize($textarea, {
                    type: editorType,
                    codemirror: {
                        lineNumbers: true,
                        mode: editorType,
                        theme: 'default',
                        lineWrapping: true,
                        indentUnit: 2,
                        tabSize: 2
                    }
                });
            });
        }
    };

    /**
     * Initialize AJAX actions
     */
    wpCustomAPI.initAjaxActions = function() {
        // Test endpoint button
        $(document).on('click', '.wp-custom-api-test-endpoint', function(e) {
            e.preventDefault();

            const $button = $(this);
            const endpointId = $button.data('endpoint-id');

            wpCustomAPI.testEndpoint(endpointId, $button);
        });

        // Delete action with confirmation
        $(document).on('click', '.wp-custom-api-delete', function(e) {
            e.preventDefault();

            if (!confirm(wpCustomAPI.i18n.confirmDelete)) {
                return;
            }

            const $button = $(this);
            const itemId = $button.data('item-id');
            const itemType = $button.data('item-type');

            wpCustomAPI.deleteItem(itemType, itemId, $button);
        });

        // Toggle endpoint status
        $(document).on('click', '.wp-custom-api-toggle-status', function(e) {
            e.preventDefault();

            const $button = $(this);
            const endpointId = $button.data('endpoint-id');
            const currentStatus = $button.data('status');

            wpCustomAPI.toggleEndpointStatus(endpointId, currentStatus, $button);
        });
    };

    /**
     * Test an endpoint
     */
    wpCustomAPI.testEndpoint = function(endpointId, $button) {
        const originalText = $button.text();
        $button.prop('disabled', true).html('<span class="wp-custom-api-loading"></span> Testing...');

        $.ajax({
            url: wpCustomAPI.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wp_custom_api_test_endpoint',
                nonce: wpCustomAPI.nonce,
                endpoint_id: endpointId
            },
            success: function(response) {
                if (response.success) {
                    wpCustomAPI.showNotice('success', 'Endpoint test successful!');
                    // Display result modal or update UI
                    wpCustomAPI.displayTestResult(response.data);
                } else {
                    wpCustomAPI.showNotice('error', response.data.message || 'Test failed');
                }
            },
            error: function(xhr, status, error) {
                wpCustomAPI.showNotice('error', 'AJAX error: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    };

    /**
     * Delete an item
     */
    wpCustomAPI.deleteItem = function(itemType, itemId, $button) {
        const $row = $button.closest('tr');

        $.ajax({
            url: wpCustomAPI.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wp_custom_api_delete_' + itemType,
                nonce: wpCustomAPI.nonce,
                id: itemId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    wpCustomAPI.showNotice('success', wpCustomAPI.i18n.saved);
                } else {
                    wpCustomAPI.showNotice('error', response.data.message || wpCustomAPI.i18n.error);
                }
            },
            error: function(xhr, status, error) {
                wpCustomAPI.showNotice('error', 'AJAX error: ' + error);
            }
        });
    };

    /**
     * Toggle endpoint status
     */
    wpCustomAPI.toggleEndpointStatus = function(endpointId, currentStatus, $button) {
        const newStatus = currentStatus === '1' ? '0' : '1';

        $.ajax({
            url: wpCustomAPI.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wp_custom_api_toggle_endpoint_status',
                nonce: wpCustomAPI.nonce,
                endpoint_id: endpointId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    $button.data('status', newStatus);
                    $button.text(newStatus === '1' ? 'Deactivate' : 'Activate');
                    wpCustomAPI.showNotice('success', 'Status updated');
                } else {
                    wpCustomAPI.showNotice('error', response.data.message || wpCustomAPI.i18n.error);
                }
            },
            error: function(xhr, status, error) {
                wpCustomAPI.showNotice('error', 'AJAX error: ' + error);
            }
        });
    };

    /**
     * Display test result modal
     */
    wpCustomAPI.displayTestResult = function(result) {
        // TODO: Create modal to display test results
        console.log('Test Result:', result);
    };

    /**
     * Show admin notice
     */
    wpCustomAPI.showNotice = function(type, message) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    };

    /**
     * Field mapping utilities
     */
    wpCustomAPI.fieldMapper = {
        init: function() {
            $('.wp-custom-api-field-mapper').each(function() {
                const $mapper = $(this);
                wpCustomAPI.fieldMapper.initDragDrop($mapper);
            });
        },

        initDragDrop: function($mapper) {
            const $sourceFields = $mapper.find('.field-mapper-source .field-item');
            const $targetFields = $mapper.find('.field-mapper-target .field-item');

            $sourceFields.draggable({
                helper: 'clone',
                revert: 'invalid',
                cursor: 'move'
            });

            $targetFields.droppable({
                accept: '.field-item',
                hoverClass: 'field-drop-hover',
                drop: function(event, ui) {
                    const sourceField = ui.draggable.data('field');
                    const targetField = $(this).data('field');
                    wpCustomAPI.fieldMapper.createMapping(sourceField, targetField);
                }
            });
        },

        createMapping: function(sourceField, targetField) {
            console.log('Mapping:', sourceField, '->', targetField);
            // TODO: Implement mapping logic
        }
    };

    /**
     * Query builder utilities
     */
    wpCustomAPI.queryBuilder = {
        init: function() {
            $('.wp-custom-api-query-builder').each(function() {
                const $builder = $(this);
                wpCustomAPI.queryBuilder.initBuilder($builder);
            });
        },

        initBuilder: function($builder) {
            // Add condition button
            $builder.find('.add-condition').on('click', function(e) {
                e.preventDefault();
                wpCustomAPI.queryBuilder.addCondition($builder);
            });

            // Remove condition button
            $builder.on('click', '.remove-condition', function(e) {
                e.preventDefault();
                $(this).closest('.query-condition').remove();
            });
        },

        addCondition: function($builder) {
            const conditionHtml = `
                <div class="query-condition">
                    <select name="condition_field[]">
                        <option value="post_type">Post Type</option>
                        <option value="category">Category</option>
                        <option value="tag">Tag</option>
                        <option value="author">Author</option>
                        <option value="status">Status</option>
                    </select>
                    <select name="condition_operator[]">
                        <option value="equals">Equals</option>
                        <option value="not_equals">Not Equals</option>
                        <option value="contains">Contains</option>
                        <option value="in">In</option>
                    </select>
                    <input type="text" name="condition_value[]" placeholder="Value">
                    <button type="button" class="button remove-condition">Remove</button>
                </div>
            `;

            $builder.find('.query-conditions').append(conditionHtml);
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        wpCustomAPI.init();
        wpCustomAPI.fieldMapper.init();
        wpCustomAPI.queryBuilder.init();
    });

    // Expose i18n strings
    wpCustomAPI.i18n = wpCustomAPI.i18n || {};

})(jQuery);
