<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Admin;

use WP_Custom_API\Includes\Database;
use WP_Custom_API\Includes\Endpoint_Manager\Endpoint_Manager;
use WP_Custom_API\Includes\Endpoint_Manager\Custom_Endpoint_Model;

/**
 * Prevent direct access from sources other than the WordPress environment
 */
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Admin Actions
 *
 * Handles AJAX requests from the admin interface:
 * - Test endpoint
 * - Delete endpoint
 * - Toggle endpoint status
 * - Duplicate endpoint
 *
 * @since 2.0.0
 */
final class AJAX_Handlers
{
    /**
     * Initialize AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        // Test endpoint
        add_action('wp_ajax_wp_custom_api_test_endpoint', [self::class, 'test_endpoint']);

        // Delete endpoint
        add_action('wp_ajax_wp_custom_api_delete_endpoint', [self::class, 'delete_endpoint']);

        // Toggle endpoint status
        add_action('wp_ajax_wp_custom_api_toggle_endpoint_status', [self::class, 'toggle_status']);

        // Duplicate endpoint
        add_action('wp_ajax_wp_custom_api_duplicate_endpoint', [self::class, 'duplicate_endpoint']);
    }

    /**
     * Test an endpoint with sample request
     *
     * @return void
     */
    public static function test_endpoint(): void
    {
        // Verify nonce
        check_ajax_referer('wp_custom_api_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $endpoint_id = isset($_POST['endpoint_id']) ? intval($_POST['endpoint_id']) : 0;
        $test_data = isset($_POST['test_data']) ? json_decode(stripslashes($_POST['test_data']), true) : [];

        if ($endpoint_id <= 0) {
            wp_send_json_error(['message' => 'Invalid endpoint ID'], 400);
        }

        // Get endpoint configuration
        $result = Endpoint_Manager::get_endpoint($endpoint_id);
        if (!$result->ok) {
            wp_send_json_error(['message' => 'Endpoint not found'], 404);
        }

        $endpoint = is_array($result->data) ? $result->data[0] : $result->data;

        // Build full URL
        $base_url = home_url('/wp-json/' . \WP_Custom_API\Config::BASE_API_ROUTE . '/custom/');
        $full_url = $base_url . $endpoint['slug'];
        if (!empty($endpoint['route'])) {
            $full_url .= '/' . ltrim($endpoint['route'], '/');
        }

        // Prepare test request
        $method = strtoupper($endpoint['method'] ?? 'POST');
        $headers = [
            'Content-Type' => 'application/json'
        ];

        // Add custom headers from test data
        if (!empty($test_data['headers'])) {
            $headers = array_merge($headers, $test_data['headers']);
        }

        // Add query params to URL
        $query_params = $test_data['query_params'] ?? [];
        if (!empty($query_params)) {
            $full_url .= '?' . http_build_query($query_params);
        }

        // Prepare body
        $body = $test_data['body'] ?? [];
        if (!is_string($body)) {
            $body = json_encode($body);
        }

        // Make request
        $start_time = microtime(true);
        $start_memory = memory_get_usage();

        $response = wp_remote_request($full_url, [
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30,
            'sslverify' => false // For local testing
        ]);

        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        // Process response
        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => 'Request failed: ' . $response->get_error_message(),
                'url' => $full_url,
                'method' => $method
            ], 500);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_headers = wp_remote_retrieve_headers($response);
        $response_body = wp_remote_retrieve_body($response);

        // Try to decode JSON response
        $decoded_body = json_decode($response_body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $response_body = $decoded_body;
        }

        wp_send_json_success([
            'request' => [
                'url' => $full_url,
                'method' => $method,
                'headers' => $headers,
                'body' => $test_data['body'] ?? []
            ],
            'response' => [
                'status_code' => $status_code,
                'status_text' => self::get_status_text($status_code),
                'headers' => $response_headers->getAll(),
                'body' => $response_body
            ],
            'metrics' => [
                'duration_ms' => round(($end_time - $start_time) * 1000, 2),
                'memory_used' => self::format_bytes($end_memory - $start_memory)
            ]
        ]);
    }

    /**
     * Delete an endpoint
     *
     * @return void
     */
    public static function delete_endpoint(): void
    {
        // Verify nonce
        check_ajax_referer('wp_custom_api_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $endpoint_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if ($endpoint_id <= 0) {
            wp_send_json_error(['message' => 'Invalid endpoint ID'], 400);
        }

        $result = Endpoint_Manager::delete_endpoint($endpoint_id);

        if ($result->ok) {
            wp_send_json_success([
                'message' => 'Endpoint deleted successfully',
                'id' => $endpoint_id
            ]);
        } else {
            wp_send_json_error([
                'message' => $result->message ?? 'Failed to delete endpoint'
            ], 500);
        }
    }

    /**
     * Toggle endpoint status (active/inactive)
     *
     * @return void
     */
    public static function toggle_status(): void
    {
        // Verify nonce
        check_ajax_referer('wp_custom_api_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $endpoint_id = isset($_POST['endpoint_id']) ? intval($_POST['endpoint_id']) : 0;
        $new_status = isset($_POST['status']) ? intval($_POST['status']) : 0;

        if ($endpoint_id <= 0) {
            wp_send_json_error(['message' => 'Invalid endpoint ID'], 400);
        }

        $result = Endpoint_Manager::update_endpoint($endpoint_id, [
            'is_active' => $new_status
        ]);

        if ($result->ok) {
            wp_send_json_success([
                'message' => 'Status updated successfully',
                'id' => $endpoint_id,
                'status' => $new_status,
                'status_text' => $new_status ? 'Active' : 'Inactive'
            ]);
        } else {
            wp_send_json_error([
                'message' => $result->message ?? 'Failed to update status'
            ], 500);
        }
    }

    /**
     * Duplicate an endpoint
     *
     * @return void
     */
    public static function duplicate_endpoint(): void
    {
        // Verify nonce
        check_ajax_referer('wp_custom_api_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $endpoint_id = isset($_POST['endpoint_id']) ? intval($_POST['endpoint_id']) : 0;

        if ($endpoint_id <= 0) {
            wp_send_json_error(['message' => 'Invalid endpoint ID'], 400);
        }

        global $wpdb;
        $table = Database::get_table_full_name(Custom_Endpoint_Model::TABLE_NAME);

        if (!$table) {
            wp_send_json_error(['message' => 'Database table not found'], 500);
        }

        // Get original endpoint
        $endpoint = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $endpoint_id
        ), ARRAY_A);

        if (!$endpoint) {
            wp_send_json_error(['message' => 'Endpoint not found'], 404);
        }

        // Prepare duplicate data
        unset($endpoint['id']);
        $endpoint['name'] = $endpoint['name'] . ' (Copy)';
        $endpoint['slug'] = $endpoint['slug'] . '-copy-' . time();
        $endpoint['is_active'] = 0; // Start inactive
        $endpoint['created_at'] = current_time('mysql');
        $endpoint['updated_at'] = current_time('mysql');

        // Insert duplicate
        $result = Database::insert_row(Custom_Endpoint_Model::TABLE_NAME, $endpoint);

        if ($result->ok) {
            wp_send_json_success([
                'message' => 'Endpoint duplicated successfully',
                'original_id' => $endpoint_id,
                'new_id' => $result->data['id'] ?? 0,
                'new_slug' => $endpoint['slug']
            ]);
        } else {
            wp_send_json_error([
                'message' => $result->message ?? 'Failed to duplicate endpoint'
            ], 500);
        }
    }

    /**
     * Get HTTP status text
     *
     * @param int $code
     * @return string
     */
    private static function get_status_text(int $code): string
    {
        $statuses = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout'
        ];

        return $statuses[$code] ?? 'Unknown';
    }

    /**
     * Format bytes to human readable
     *
     * @param int $bytes
     * @return string
     */
    private static function format_bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / 1048576, 2) . ' MB';
        }
    }
}

// Initialize AJAX handlers
AJAX_Handlers::init();
