/**
 * Endpoint Tester - Test Interface for Custom Endpoints
 *
 * @since 2.0.0
 */

(function($) {
    'use strict';

    window.wpCustomAPITester = {
        /**
         * Initialize the tester
         */
        init: function() {
            this.createModal();
            this.bindEvents();
        },

        /**
         * Create the test modal HTML
         */
        createModal: function() {
            const modalHTML = `
                <div id="wp-custom-api-test-modal" class="wp-custom-api-modal" style="display:none;">
                    <div class="wp-custom-api-modal-backdrop"></div>
                    <div class="wp-custom-api-modal-dialog">
                        <div class="wp-custom-api-modal-header">
                            <h2>Test Endpoint</h2>
                            <button type="button" class="wp-custom-api-modal-close">&times;</button>
                        </div>
                        <div class="wp-custom-api-modal-body">
                            <div class="test-request-builder">
                                <h3>Request Configuration</h3>

                                <div class="form-group">
                                    <label>HTTP Headers</label>
                                    <div id="test-headers-container">
                                        <div class="header-row">
                                            <input type="text" placeholder="Header Name" class="header-name" />
                                            <input type="text" placeholder="Header Value" class="header-value" />
                                            <button type="button" class="button remove-header">Remove</button>
                                        </div>
                                    </div>
                                    <button type="button" class="button add-header">Add Header</button>
                                </div>

                                <div class="form-group">
                                    <label>Query Parameters</label>
                                    <div id="test-params-container">
                                        <div class="param-row">
                                            <input type="text" placeholder="Parameter Name" class="param-name" />
                                            <input type="text" placeholder="Parameter Value" class="param-value" />
                                            <button type="button" class="button remove-param">Remove</button>
                                        </div>
                                    </div>
                                    <button type="button" class="button add-param">Add Parameter</button>
                                </div>

                                <div class="form-group">
                                    <label>Request Body (JSON)</label>
                                    <textarea id="test-body" rows="8" class="large-text code" placeholder='{"key": "value"}'></textarea>
                                </div>

                                <div class="form-group">
                                    <button type="button" class="button button-primary button-large" id="execute-test">
                                        Send Test Request
                                    </button>
                                    <button type="button" class="button button-large" id="clear-test">
                                        Clear
                                    </button>
                                </div>
                            </div>

                            <div id="test-results" style="display:none;">
                                <hr>
                                <h3>Response</h3>

                                <div class="test-response-meta">
                                    <span class="status-code"></span>
                                    <span class="duration"></span>
                                    <span class="memory"></span>
                                </div>

                                <div class="test-tabs">
                                    <button type="button" class="test-tab active" data-tab="body">Body</button>
                                    <button type="button" class="test-tab" data-tab="headers">Headers</button>
                                    <button type="button" class="test-tab" data-tab="request">Request</button>
                                    <button type="button" class="test-tab" data-tab="curl">cURL</button>
                                </div>

                                <div class="test-tab-content">
                                    <div id="test-tab-body" class="test-tab-panel active">
                                        <pre><code id="response-body"></code></pre>
                                    </div>
                                    <div id="test-tab-headers" class="test-tab-panel">
                                        <pre><code id="response-headers"></code></pre>
                                    </div>
                                    <div id="test-tab-request" class="test-tab-panel">
                                        <pre><code id="request-details"></code></pre>
                                    </div>
                                    <div id="test-tab-curl" class="test-tab-panel">
                                        <pre><code id="curl-command"></code></pre>
                                        <button type="button" class="button" id="copy-curl">Copy to Clipboard</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Open modal
            $(document).on('click', '.wp-custom-api-test-endpoint', function(e) {
                e.preventDefault();
                const endpointId = $(this).data('endpoint-id');
                self.openModal(endpointId);
            });

            // Close modal
            $(document).on('click', '.wp-custom-api-modal-close, .wp-custom-api-modal-backdrop', function() {
                self.closeModal();
            });

            // Add header
            $(document).on('click', '.add-header', function() {
                self.addHeaderRow();
            });

            // Remove header
            $(document).on('click', '.remove-header', function() {
                $(this).closest('.header-row').remove();
            });

            // Add parameter
            $(document).on('click', '.add-param', function() {
                self.addParamRow();
            });

            // Remove parameter
            $(document).on('click', '.remove-param', function() {
                $(this).closest('.param-row').remove();
            });

            // Execute test
            $(document).on('click', '#execute-test', function() {
                self.executeTest();
            });

            // Clear form
            $(document).on('click', '#clear-test', function() {
                self.clearForm();
            });

            // Tab switching
            $(document).on('click', '.test-tab', function() {
                const tab = $(this).data('tab');
                $('.test-tab').removeClass('active');
                $(this).addClass('active');
                $('.test-tab-panel').removeClass('active');
                $('#test-tab-' + tab).addClass('active');
            });

            // Copy cURL
            $(document).on('click', '#copy-curl', function() {
                const curlCommand = $('#curl-command').text();
                navigator.clipboard.writeText(curlCommand).then(function() {
                    alert('cURL command copied to clipboard!');
                });
            });
        },

        /**
         * Open the test modal
         */
        openModal: function(endpointId) {
            this.currentEndpointId = endpointId;
            this.clearForm();
            $('#test-results').hide();
            $('#wp-custom-api-test-modal').fadeIn(200);
            $('body').addClass('modal-open');
        },

        /**
         * Close the modal
         */
        closeModal: function() {
            $('#wp-custom-api-test-modal').fadeOut(200);
            $('body').removeClass('modal-open');
        },

        /**
         * Add header row
         */
        addHeaderRow: function() {
            const row = `
                <div class="header-row">
                    <input type="text" placeholder="Header Name" class="header-name" />
                    <input type="text" placeholder="Header Value" class="header-value" />
                    <button type="button" class="button remove-header">Remove</button>
                </div>
            `;
            $('#test-headers-container').append(row);
        },

        /**
         * Add parameter row
         */
        addParamRow: function() {
            const row = `
                <div class="param-row">
                    <input type="text" placeholder="Parameter Name" class="param-name" />
                    <input type="text" placeholder="Parameter Value" class="param-value" />
                    <button type="button" class="button remove-param">Remove</button>
                </div>
            `;
            $('#test-params-container').append(row);
        },

        /**
         * Clear the form
         */
        clearForm: function() {
            $('#test-headers-container').html(`
                <div class="header-row">
                    <input type="text" placeholder="Header Name" class="header-name" />
                    <input type="text" placeholder="Header Value" class="header-value" />
                    <button type="button" class="button remove-header">Remove</button>
                </div>
            `);
            $('#test-params-container').html(`
                <div class="param-row">
                    <input type="text" placeholder="Parameter Name" class="param-name" />
                    <input type="text" placeholder="Parameter Value" class="param-value" />
                    <button type="button" class="button remove-param">Remove</button>
                </div>
            `);
            $('#test-body').val('');
            $('#test-results').hide();
        },

        /**
         * Execute the test
         */
        executeTest: function() {
            const self = this;
            const $button = $('#execute-test');
            const originalText = $button.text();

            // Collect headers
            const headers = {};
            $('#test-headers-container .header-row').each(function() {
                const name = $(this).find('.header-name').val();
                const value = $(this).find('.header-value').val();
                if (name && value) {
                    headers[name] = value;
                }
            });

            // Collect query params
            const queryParams = {};
            $('#test-params-container .param-row').each(function() {
                const name = $(this).find('.param-name').val();
                const value = $(this).find('.param-value').val();
                if (name && value) {
                    queryParams[name] = value;
                }
            });

            // Get body
            let body = $('#test-body').val();
            if (body) {
                try {
                    body = JSON.parse(body);
                } catch (e) {
                    alert('Invalid JSON in request body');
                    return;
                }
            }

            // Prepare test data
            const testData = {
                headers: headers,
                query_params: queryParams,
                body: body
            };

            // Disable button
            $button.prop('disabled', true).html('<span class="spinner is-active"></span> Testing...');

            // Make AJAX request
            $.ajax({
                url: wpCustomAPI.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wp_custom_api_test_endpoint',
                    nonce: wpCustomAPI.nonce,
                    endpoint_id: self.currentEndpointId,
                    test_data: JSON.stringify(testData)
                },
                success: function(response) {
                    if (response.success) {
                        self.displayResults(response.data);
                    } else {
                        alert('Test failed: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('AJAX error: ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Display test results
         */
        displayResults: function(data) {
            // Show results section
            $('#test-results').slideDown();

            // Display meta
            const statusClass = data.response.status_code >= 200 && data.response.status_code < 300 ? 'success' : 'error';
            $('.status-code').html(`<span class="status-badge status-${statusClass}">${data.response.status_code} ${data.response.status_text}</span>`);
            $('.duration').text(`Duration: ${data.metrics.duration_ms}ms`);
            $('.memory').text(`Memory: ${data.metrics.memory_used}`);

            // Display response body
            $('#response-body').text(JSON.stringify(data.response.body, null, 2));

            // Display response headers
            let headersText = '';
            for (const [key, value] of Object.entries(data.response.headers)) {
                headersText += `${key}: ${value}\n`;
            }
            $('#response-headers').text(headersText);

            // Display request details
            const requestText = `URL: ${data.request.url}\nMethod: ${data.request.method}\n\nHeaders:\n${JSON.stringify(data.request.headers, null, 2)}\n\nBody:\n${JSON.stringify(data.request.body, null, 2)}`;
            $('#request-details').text(requestText);

            // Generate cURL command
            const curlCommand = this.generateCurlCommand(data.request);
            $('#curl-command').text(curlCommand);
        },

        /**
         * Generate cURL command
         */
        generateCurlCommand: function(request) {
            let curl = `curl -X ${request.method} "${request.url}"`;

            // Add headers
            for (const [key, value] of Object.entries(request.headers)) {
                curl += ` \\\n  -H "${key}: ${value}"`;
            }

            // Add body
            if (request.body && Object.keys(request.body).length > 0) {
                curl += ` \\\n  -d '${JSON.stringify(request.body)}'`;
            }

            return curl;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        wpCustomAPITester.init();
    });

})(jQuery);
