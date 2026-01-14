<?php
/**
 * Endpoint Add/Edit Form Page
 *
 * Multi-tab form for creating and editing custom REST API endpoints
 *
 * Tabs:
 * 1. Basic Info - Name, slug, route, method, description
 * 2. Handler - Handler type and configuration
 * 3. Authentication - Auth type and credentials
 * 4. Performance - Rate limiting, caching, timeout
 * 5. Validation - Request/response schema, validation rules
 * 6. Advanced - Custom headers, transformations, logging level
 *
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

use WP_Custom_API\Includes\Database;
use WP_Custom_API\Includes\Endpoint_Manager\Endpoint_Manager;
use WP_Custom_API\Includes\Endpoint_Manager\Custom_Endpoint_Model;

// Get endpoint ID if editing
$endpoint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$endpoint = null;
$is_edit = false;

if ($endpoint_id > 0) {
    $result = Endpoint_Manager::get_endpoint($endpoint_id);
    if ($result->ok && !empty($result->data)) {
        $endpoint = is_array($result->data) ? $result->data : [$result->data];
        $endpoint = $endpoint[0] ?? null;
        $is_edit = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wp_custom_api_save_endpoint'])) {
    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'save_endpoint')) {
        wp_die('Security check failed');
    }

    $endpoint_data = [
        'name' => sanitize_text_field($_POST['name'] ?? ''),
        'slug' => sanitize_title($_POST['slug'] ?? ''),
        'route' => sanitize_text_field($_POST['route'] ?? ''),
        'method' => sanitize_text_field($_POST['method'] ?? 'POST'),
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'handler_type' => sanitize_text_field($_POST['handler_type'] ?? 'webhook'),
        'handler_config' => wp_json_encode($_POST['handler_config'] ?? []),
        'permission_type' => sanitize_text_field($_POST['permission_type'] ?? 'public'),
        'permission_config' => wp_json_encode($_POST['permission_config'] ?? []),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'rate_limit_per_minute' => intval($_POST['rate_limit_per_minute'] ?? 60),
        'rate_limit_per_hour' => intval($_POST['rate_limit_per_hour'] ?? 1000),
        'cache_ttl' => intval($_POST['cache_ttl'] ?? 0),
        'queue_async' => isset($_POST['queue_async']) ? 1 : 0,
        'timeout_seconds' => intval($_POST['timeout_seconds'] ?? 30),
        'retry_attempts' => intval($_POST['retry_attempts'] ?? 3),
        'retry_delay' => intval($_POST['retry_delay'] ?? 60),
        'request_schema' => $_POST['request_schema'] ?? '',
        'response_schema' => $_POST['response_schema'] ?? '',
    ];

    if ($is_edit) {
        $result = Endpoint_Manager::update_endpoint($endpoint_id, $endpoint_data);
    } else {
        $result = Endpoint_Manager::create_endpoint($endpoint_data);
    }

    if ($result->ok) {
        wp_redirect(admin_url('admin.php?page=wp-custom-api-endpoints&message=saved'));
        exit;
    } else {
        $error_message = $result->message ?? 'Failed to save endpoint';
    }
}

// Default values
$default = [
    'name' => '',
    'slug' => '',
    'route' => '',
    'method' => 'POST',
    'description' => '',
    'handler_type' => 'webhook',
    'handler_config' => '{}',
    'permission_type' => 'public',
    'permission_config' => '{}',
    'is_active' => 1,
    'rate_limit_per_minute' => 60,
    'rate_limit_per_hour' => 1000,
    'cache_ttl' => 0,
    'queue_async' => 0,
    'timeout_seconds' => 30,
    'retry_attempts' => 3,
    'retry_delay' => 60,
    'request_schema' => '',
    'response_schema' => '',
];

$data = $endpoint ? array_merge($default, $endpoint) : $default;
?>

<div class="wrap wp-custom-api-endpoint-edit">
    <h1 class="wp-heading-inline">
        <?php echo $is_edit ? __('Edit Endpoint', 'wp-custom-api') : __('Add New Endpoint', 'wp-custom-api'); ?>
    </h1>

    <a href="<?php echo admin_url('admin.php?page=wp-custom-api-endpoints'); ?>" class="page-title-action">
        <?php _e('Back to List', 'wp-custom-api'); ?>
    </a>

    <hr class="wp-header-end">

    <?php if (isset($error_message)): ?>
        <div class="notice notice-error"><p><?php echo esc_html($error_message); ?></p></div>
    <?php endif; ?>

    <form method="post" id="endpoint-form">
        <?php wp_nonce_field('save_endpoint'); ?>

        <!-- Tabs Navigation -->
        <div class="wp-custom-api-tabs">
            <div class="nav-tab-wrapper">
                <a href="#tab-basic" class="nav-tab nav-tab-active" data-tab="tab-basic">
                    <?php _e('Basic Info', 'wp-custom-api'); ?>
                </a>
                <a href="#tab-handler" class="nav-tab" data-tab="tab-handler">
                    <?php _e('Handler', 'wp-custom-api'); ?>
                </a>
                <a href="#tab-auth" class="nav-tab" data-tab="tab-auth">
                    <?php _e('Authentication', 'wp-custom-api'); ?>
                </a>
                <a href="#tab-performance" class="nav-tab" data-tab="tab-performance">
                    <?php _e('Performance', 'wp-custom-api'); ?>
                </a>
                <a href="#tab-validation" class="nav-tab" data-tab="tab-validation">
                    <?php _e('Validation', 'wp-custom-api'); ?>
                </a>
                <a href="#tab-advanced" class="nav-tab" data-tab="tab-advanced">
                    <?php _e('Advanced', 'wp-custom-api'); ?>
                </a>
            </div>

            <div class="wp-custom-api-tab-content">

                <!-- TAB 1: Basic Info -->
                <div id="tab-basic" class="wp-custom-api-tab-panel active">

                    <div class="wp-custom-api-form-section">
                        <h3><?php _e('Basic Information', 'wp-custom-api'); ?></h3>

                        <div class="wp-custom-api-form-row">
                            <label for="name">
                                <?php _e('Endpoint Name', 'wp-custom-api'); ?>
                                <span style="color: red;">*</span>
                            </label>
                            <div>
                                <input type="text" id="name" name="name" value="<?php echo esc_attr($data['name']); ?>" required class="regular-text" />
                                <p class="description"><?php _e('A descriptive name for this endpoint', 'wp-custom-api'); ?></p>
                            </div>
                        </div>

                        <div class="wp-custom-api-form-row">
                            <label for="slug">
                                <?php _e('Slug', 'wp-custom-api'); ?>
                                <span style="color: red;">*</span>
                            </label>
                            <div>
                                <input type="text" id="slug" name="slug" value="<?php echo esc_attr($data['slug']); ?>" required class="regular-text" />
                                <p class="description">
                                    <?php _e('URL-friendly identifier (lowercase, hyphens allowed)', 'wp-custom-api'); ?><br>
                                    <?php _e('Example: my-webhook', 'wp-custom-api'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="wp-custom-api-form-row">
                            <label for="route">
                                <?php _e('Route Pattern', 'wp-custom-api'); ?>
                            </label>
                            <div>
                                <input type="text" id="route" name="route" value="<?php echo esc_attr($data['route']); ?>" class="regular-text" placeholder="/optional/path" />
                                <p class="description">
                                    <?php _e('Optional path after slug. Use {param} for dynamic segments.', 'wp-custom-api'); ?><br>
                                    <?php _e('Example: /users/{id} becomes /custom/my-webhook/users/{id}', 'wp-custom-api'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="wp-custom-api-form-row">
                            <label for="method">
                                <?php _e('HTTP Method', 'wp-custom-api'); ?>
                                <span style="color: red;">*</span>
                            </label>
                            <div>
                                <select id="method" name="method" required>
                                    <option value="GET" <?php selected($data['method'], 'GET'); ?>>GET</option>
                                    <option value="POST" <?php selected($data['method'], 'POST'); ?>>POST</option>
                                    <option value="PUT" <?php selected($data['method'], 'PUT'); ?>>PUT</option>
                                    <option value="PATCH" <?php selected($data['method'], 'PATCH'); ?>>PATCH</option>
                                    <option value="DELETE" <?php selected($data['method'], 'DELETE'); ?>>DELETE</option>
                                </select>
                                <p class="description"><?php _e('The HTTP method this endpoint will accept', 'wp-custom-api'); ?></p>
                            </div>
                        </div>

                        <div class="wp-custom-api-form-row">
                            <label for="description">
                                <?php _e('Description', 'wp-custom-api'); ?>
                            </label>
                            <div>
                                <textarea id="description" name="description" rows="4" class="large-text"><?php echo esc_textarea($data['description']); ?></textarea>
                                <p class="description"><?php _e('Optional description of what this endpoint does', 'wp-custom-api'); ?></p>
                            </div>
                        </div>

                        <div class="wp-custom-api-form-row">
                            <label for="is_active">
                                <?php _e('Status', 'wp-custom-api'); ?>
                            </label>
                            <div>
                                <label class="wp-custom-api-toggle">
                                    <input type="checkbox" id="is_active" name="is_active" value="1" <?php checked($data['is_active'], 1); ?> />
                                    <span class="slider"></span>
                                </label>
                                <span style="margin-left: 10px;"><?php _e('Active (endpoint is live and receiving requests)', 'wp-custom-api'); ?></span>
                            </div>
                        </div>

                    </div>

                    <div class="wp-custom-api-form-section">
                        <h3><?php _e('Preview', 'wp-custom-api'); ?></h3>
                        <p>
                            <strong><?php _e('Full URL:', 'wp-custom-api'); ?></strong><br>
                            <code id="preview-url" style="background: #f6f7f7; padding: 8px 12px; display: inline-block; border-radius: 4px;">
                                <?php echo home_url('/wp-json/' . \WP_Custom_API\Config::BASE_API_ROUTE . '/custom/'); ?><span id="preview-slug"><?php echo esc_html($data['slug'] ?: 'your-slug'); ?></span><span id="preview-route"><?php echo esc_html($data['route']); ?></span>
                            </code>
                        </p>
                    </div>

                </div>

                <!-- TAB 2: Handler -->
                <div id="tab-handler" class="wp-custom-api-tab-panel">
                    <div class="wp-custom-api-form-section">
                        <h3><?php _e('Handler Configuration', 'wp-custom-api'); ?></h3>
                        <p><?php _e('Choose how this endpoint processes incoming requests.', 'wp-custom-api'); ?></p>

                        <div class="wp-custom-api-form-row">
                            <label><?php _e('Handler Type', 'wp-custom-api'); ?></label>
                            <div>
                                <select name="handler_type" id="handler_type">
                                    <option value="webhook" <?php selected($data['handler_type'], 'webhook'); ?>><?php _e('Webhook (store data)', 'wp-custom-api'); ?></option>
                                    <option value="action" <?php selected($data['handler_type'], 'action'); ?>><?php _e('Action (trigger hooks)', 'wp-custom-api'); ?></option>
                                    <option value="script" <?php selected($data['handler_type'], 'script'); ?>><?php _e('Script (custom PHP)', 'wp-custom-api'); ?></option>
                                    <option value="forward" <?php selected($data['handler_type'], 'forward'); ?>><?php _e('Forward (proxy to external)', 'wp-custom-api'); ?></option>
                                    <option value="etl" <?php selected($data['handler_type'], 'etl'); ?>><?php _e('ETL (transform data)', 'wp-custom-api'); ?></option>
                                </select>
                                <p class="description"><?php _e('Handler configuration options will appear based on selection', 'wp-custom-api'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: Authentication -->
                <div id="tab-auth" class="wp-custom-api-tab-panel">
                    <div class="wp-custom-api-form-section">
                        <h3><?php _e('Authentication Settings', 'wp-custom-api'); ?></h3>
                        <p><?php _e('Configure how requests are authenticated and authorized.', 'wp-custom-api'); ?></p>

                        <div class="wp-custom-api-form-row">
                            <label><?php _e('Authentication Type', 'wp-custom-api'); ?></label>
                            <div>
                                <select name="permission_type">
                                    <option value="public" <?php selected($data['permission_type'], 'public'); ?>><?php _e('Public (no auth)', 'wp-custom-api'); ?></option>
                                    <option value="api_key" <?php selected($data['permission_type'], 'api_key'); ?>><?php _e('API Key', 'wp-custom-api'); ?></option>
                                    <option value="signature" <?php selected($data['permission_type'], 'signature'); ?>><?php _e('Signature (HMAC)', 'wp-custom-api'); ?></option>
                                    <option value="token" <?php selected($data['permission_type'], 'token'); ?>><?php _e('Bearer Token', 'wp-custom-api'); ?></option>
                                    <option value="ip_whitelist" <?php selected($data['permission_type'], 'ip_whitelist'); ?>><?php _e('IP Whitelist', 'wp-custom-api'); ?></option>
                                </select>
                                <p class="description"><?php _e('Auth configuration fields will appear based on selection', 'wp-custom-api'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: Performance -->
                <div id="tab-performance" class="wp-custom-api-tab-panel">
                    <div class="wp-custom-api-form-section">
                        <h3><?php _e('Performance & Limits', 'wp-custom-api'); ?></h3>

                        <div class="wp-custom-api-form-row">
                            <label><?php _e('Rate Limiting', 'wp-custom-api'); ?></label>
                            <div>
                                <input type="number" name="rate_limit_per_minute" value="<?php echo esc_attr($data['rate_limit_per_minute']); ?>" min="1" style="width: 100px;" />
                                <span><?php _e('requests per minute', 'wp-custom-api'); ?></span>
                                <br><br>
                                <input type="number" name="rate_limit_per_hour" value="<?php echo esc_attr($data['rate_limit_per_hour']); ?>" min="1" style="width: 100px;" />
                                <span><?php _e('requests per hour', 'wp-custom-api'); ?></span>
                                <p class="description"><?php _e('Prevent abuse by limiting request frequency', 'wp-custom-api'); ?></p>
                            </div>
                        </div>

                        <div class="wp-custom-api-form-row">
                            <label><?php _e('Cache TTL', 'wp-custom-api'); ?></label>
                            <div>
                                <input type="number" name="cache_ttl" value="<?php echo esc_attr($data['cache_ttl']); ?>" min="0" style="width: 100px;" />
                                <span><?php _e('seconds (0 = no caching)', 'wp-custom-api'); ?></span>
                                <p class="description"><?php _e('Cache responses to improve performance', 'wp-custom-api'); ?></p>
                            </div>
                        </div>

                        <div class="wp-custom-api-form-row">
                            <label><?php _e('Timeout', 'wp-custom-api'); ?></label>
                            <div>
                                <input type="number" name="timeout_seconds" value="<?php echo esc_attr($data['timeout_seconds']); ?>" min="1" max="300" style="width: 100px;" />
                                <span><?php _e('seconds', 'wp-custom-api'); ?></span>
                                <p class="description"><?php _e('Maximum execution time before timeout', 'wp-custom-api'); ?></p>
                            </div>
                        </div>

                        <div class="wp-custom-api-form-row">
                            <label><?php _e('Async Processing', 'wp-custom-api'); ?></label>
                            <div>
                                <label class="wp-custom-api-toggle">
                                    <input type="checkbox" name="queue_async" value="1" <?php checked($data['queue_async'], 1); ?> />
                                    <span class="slider"></span>
                                </label>
                                <span style="margin-left: 10px;"><?php _e('Queue requests for background processing', 'wp-custom-api'); ?></span>
                                <p class="description"><?php _e('Use Action Scheduler for heavy operations', 'wp-custom-api'); ?></p>
                            </div>
                        </div>

                        <div class="wp-custom-api-form-row">
                            <label><?php _e('Retry Logic', 'wp-custom-api'); ?></label>
                            <div>
                                <input type="number" name="retry_attempts" value="<?php echo esc_attr($data['retry_attempts']); ?>" min="0" max="10" style="width: 100px;" />
                                <span><?php _e('retry attempts', 'wp-custom-api'); ?></span>
                                <br><br>
                                <input type="number" name="retry_delay" value="<?php echo esc_attr($data['retry_delay']); ?>" min="1" style="width: 100px;" />
                                <span><?php _e('seconds delay between retries', 'wp-custom-api'); ?></span>
                                <p class="description"><?php _e('Automatic retry with exponential backoff', 'wp-custom-api'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 5: Validation -->
                <div id="tab-validation" class="wp-custom-api-tab-panel">
                    <div class="wp-custom-api-form-section">
                        <h3><?php _e('Request/Response Schemas', 'wp-custom-api'); ?></h3>

                        <div class="wp-custom-api-form-row">
                            <label><?php _e('Request Schema (JSON)', 'wp-custom-api'); ?></label>
                            <div>
                                <textarea name="request_schema" class="wp-custom-api-code-editor large-text" data-editor-type="application/json" rows="10"><?php echo esc_textarea($data['request_schema']); ?></textarea>
                                <p class="description"><?php _e('JSON Schema for validating incoming requests', 'wp-custom-api'); ?></p>
                            </div>
                        </div>

                        <div class="wp-custom-api-form-row">
                            <label><?php _e('Response Schema (JSON)', 'wp-custom-api'); ?></label>
                            <div>
                                <textarea name="response_schema" class="wp-custom-api-code-editor large-text" data-editor-type="application/json" rows="10"><?php echo esc_textarea($data['response_schema']); ?></textarea>
                                <p class="description"><?php _e('JSON Schema for validating outgoing responses', 'wp-custom-api'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 6: Advanced -->
                <div id="tab-advanced" class="wp-custom-api-tab-panel">
                    <div class="wp-custom-api-form-section">
                        <h3><?php _e('Advanced Configuration', 'wp-custom-api'); ?></h3>
                        <p><?php _e('Advanced options for custom headers, transformations, and logging.', 'wp-custom-api'); ?></p>
                        <p><em><?php _e('Additional advanced features coming in Phase 6', 'wp-custom-api'); ?></em></p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Save Button -->
        <p class="submit">
            <button type="submit" name="wp_custom_api_save_endpoint" class="button button-primary button-large">
                <?php _e('Save Endpoint', 'wp-custom-api'); ?>
            </button>
            <?php if ($is_edit): ?>
                <a href="#" class="button button-large wp-custom-api-test-endpoint" data-endpoint-id="<?php echo $endpoint_id; ?>">
                    <?php _e('Test Endpoint', 'wp-custom-api'); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo admin_url('admin.php?page=wp-custom-api-endpoints'); ?>" class="button button-large">
                <?php _e('Cancel', 'wp-custom-api'); ?>
            </a>
        </p>

    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Auto-generate slug from name
    $('#name').on('blur', function() {
        var name = $(this).val();
        var slug = $('#slug').val();

        if (!slug && name) {
            slug = name.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            $('#slug').val(slug);
            updatePreview();
        }
    });

    // Update preview on slug/route change
    $('#slug, #route').on('input', updatePreview);

    function updatePreview() {
        var slug = $('#slug').val() || 'your-slug';
        var route = $('#route').val();

        $('#preview-slug').text(slug);
        $('#preview-route').text(route);
    }
});
</script>
