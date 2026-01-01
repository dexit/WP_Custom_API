<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Config;
use WP_Custom_API\Includes\Database;
use WP_Custom_API\Includes\Response_Handler;
use WP_Custom_API\Includes\Error_Generator;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * External Service Connector - Handles outbound HTTP requests to external services
 *
 * This class handles:
 * - Configuring external service connections
 * - Authentication (API key, OAuth, Basic Auth, etc.)
 * - Making HTTP requests with retry logic
 * - Rate limiting
 * - Health checks
 *
 * @since 1.1.0
 */

final class External_Service_Connector
{
    /**
     * Authentication type constants
     */
    public const AUTH_NONE = 'none';
    public const AUTH_API_KEY = 'api_key';
    public const AUTH_BEARER = 'bearer';
    public const AUTH_BASIC = 'basic';
    public const AUTH_OAUTH2 = 'oauth2';
    public const AUTH_CUSTOM = 'custom';

    /**
     * Health status constants
     */
    public const HEALTH_HEALTHY = 'healthy';
    public const HEALTH_DEGRADED = 'degraded';
    public const HEALTH_UNHEALTHY = 'unhealthy';
    public const HEALTH_UNKNOWN = 'unknown';

    /**
     * Cache for service configurations
     */
    private static array $service_cache = [];

    /**
     * Forward a request to an external service
     *
     * @param WP_REST_Request $request
     * @param int $service_id
     * @param string $target_path
     * @param array $config
     * @return WP_REST_Response
     */
    public function forward(
        WP_REST_Request $request,
        int $service_id,
        string $target_path,
        array $config = []
    ): WP_REST_Response {
        try {
            $service = $this->get_service($service_id);

            if (!$service) {
                return new WP_REST_Response(['message' => 'External service not found'], 404);
            }

            if (!($service['is_active'] ?? true)) {
                return new WP_REST_Response(['message' => 'External service is disabled'], 503);
            }

            // Build request data
            $method = $config['preserve_method'] ?? true
                ? $request->get_method()
                : ($config['method'] ?? 'POST');

            $body = Endpoint_Manager::get_request_data($request);
            $headers = $this->merge_headers($service, $config, $request);

            // Make the request
            $response = $this->send(
                $service_id,
                $target_path,
                $body,
                $method,
                array_merge($config, ['headers' => $headers])
            );

            // Return the response
            $status_code = $response['code'] ?? 500;
            $response_body = $response['body'] ?? [];

            if (is_string($response_body)) {
                $decoded = json_decode($response_body, true);
                $response_body = $decoded ?: ['raw' => $response_body];
            }

            return new WP_REST_Response($response_body, $status_code);

        } catch (\Exception $e) {
            Error_Generator::generate('External Service Forward Error', $e->getMessage());

            return new WP_REST_Response([
                'message' => Config::DEBUG_MESSAGE_MODE ? $e->getMessage() : 'Failed to forward request'
            ], 500);
        }
    }

    /**
     * Send a request to an external service
     *
     * @param int $service_id
     * @param string $endpoint_path
     * @param array $data
     * @param string $method
     * @param array $options
     * @return array
     */
    public function send(
        int $service_id,
        string $endpoint_path,
        array $data,
        string $method = 'POST',
        array $options = []
    ): array {
        $service = $this->get_service($service_id);

        if (!$service) {
            return [
                'success' => false,
                'code' => 404,
                'body' => 'External service not found',
                'error' => 'Service not found'
            ];
        }

        // Check rate limiting
        if (!$this->check_rate_limit($service)) {
            return [
                'success' => false,
                'code' => 429,
                'body' => 'Rate limit exceeded',
                'error' => 'Too many requests'
            ];
        }

        // Build URL
        $base_url = rtrim($service['base_url'], '/');
        $endpoint_path = ltrim($endpoint_path, '/');
        $url = $endpoint_path ? "{$base_url}/{$endpoint_path}" : $base_url;

        // Build headers
        $headers = $this->build_headers($service, $options);

        // Build request body
        $body = $this->build_body($data, $options);

        // Get timeout
        $timeout = $options['timeout'] ?? $service['timeout'] ?? 30;

        // Make request with retry logic
        $retry_config = $this->decode_json($service['retry_config'] ?? '{}');
        $max_retries = $retry_config['max_retries'] ?? 3;
        $retry_delay = $retry_config['retry_delay'] ?? 1000;

        $response = null;
        $last_error = null;

        for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
            if ($attempt > 0) {
                // Exponential backoff
                $delay = $retry_delay * pow(2, $attempt - 1);
                usleep($delay * 1000);
            }

            $response = $this->make_request($url, $method, $headers, $body, $timeout);

            // Check if we should retry
            if ($response['success'] || !$this->should_retry($response, $retry_config)) {
                break;
            }

            $last_error = $response['error'] ?? 'Unknown error';
        }

        // Log the request
        do_action('wp_custom_api_external_request', $service_id, $url, $method, $response);

        return $response;
    }

    /**
     * Make an HTTP request
     *
     * @param string $url
     * @param string $method
     * @param array $headers
     * @param mixed $body
     * @param int $timeout
     * @return array
     */
    private function make_request(
        string $url,
        string $method,
        array $headers,
        mixed $body,
        int $timeout
    ): array {
        $args = [
            'method' => strtoupper($method),
            'headers' => $headers,
            'timeout' => $timeout,
            'sslverify' => true,
        ];

        if ($body !== null && !in_array(strtoupper($method), ['GET', 'HEAD'])) {
            $args['body'] = $body;
        }

        // Add query params for GET requests
        if (strtoupper($method) === 'GET' && is_array($body) && !empty($body)) {
            $url = add_query_arg($body, $url);
            unset($args['body']);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'code' => 0,
                'body' => null,
                'error' => $response->get_error_message(),
                'headers' => []
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response)->getAll();

        // Try to decode JSON response
        $decoded_body = json_decode($response_body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $response_body = $decoded_body;
        }

        return [
            'success' => $response_code >= 200 && $response_code < 300,
            'code' => $response_code,
            'body' => $response_body,
            'headers' => $response_headers,
            'error' => $response_code >= 400 ? "HTTP {$response_code}" : null
        ];
    }

    /**
     * Get service configuration
     *
     * @param int $service_id
     * @return array|null
     */
    private function get_service(int $service_id): ?array
    {
        if (isset(self::$service_cache[$service_id])) {
            return self::$service_cache[$service_id];
        }

        $result = Database::get_rows_data(
            External_Service_Model::TABLE_NAME,
            'id',
            $service_id,
            false
        );

        if (!$result->ok || empty($result->data)) {
            return null;
        }

        self::$service_cache[$service_id] = $result->data;

        return $result->data;
    }

    /**
     * Build request headers
     *
     * @param array $service
     * @param array $options
     * @return array
     */
    private function build_headers(array $service, array $options): array
    {
        // Start with default headers
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        // Add service default headers
        $default_headers = $this->decode_json($service['default_headers'] ?? '{}');
        $headers = array_merge($headers, $default_headers);

        // Add authentication headers
        $auth_headers = $this->get_auth_headers($service);
        $headers = array_merge($headers, $auth_headers);

        // Add option headers
        if (!empty($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        return $headers;
    }

    /**
     * Get authentication headers based on service config
     *
     * @param array $service
     * @return array
     */
    private function get_auth_headers(array $service): array
    {
        $auth_type = $service['auth_type'] ?? self::AUTH_NONE;
        $auth_config = $this->decode_json($service['auth_config'] ?? '{}');

        return match ($auth_type) {
            self::AUTH_API_KEY => $this->get_api_key_auth($auth_config),
            self::AUTH_BEARER => $this->get_bearer_auth($auth_config),
            self::AUTH_BASIC => $this->get_basic_auth($auth_config),
            self::AUTH_OAUTH2 => $this->get_oauth2_auth($auth_config, $service),
            self::AUTH_CUSTOM => $auth_config['headers'] ?? [],
            default => []
        };
    }

    /**
     * Get API key authentication headers
     *
     * @param array $config
     * @return array
     */
    private function get_api_key_auth(array $config): array
    {
        $header_name = $config['header_name'] ?? 'X-API-Key';
        $api_key = $config['api_key'] ?? '';

        return $api_key ? [$header_name => $api_key] : [];
    }

    /**
     * Get Bearer token authentication headers
     *
     * @param array $config
     * @return array
     */
    private function get_bearer_auth(array $config): array
    {
        $token = $config['token'] ?? '';

        return $token ? ['Authorization' => "Bearer {$token}"] : [];
    }

    /**
     * Get Basic authentication headers
     *
     * @param array $config
     * @return array
     */
    private function get_basic_auth(array $config): array
    {
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if (!$username) {
            return [];
        }

        $credentials = base64_encode("{$username}:{$password}");

        return ['Authorization' => "Basic {$credentials}"];
    }

    /**
     * Get OAuth2 authentication headers
     *
     * @param array $config
     * @param array $service
     * @return array
     */
    private function get_oauth2_auth(array $config, array $service): array
    {
        // Check for cached token
        $token_key = 'wp_custom_api_oauth_token_' . $service['id'];
        $cached_token = get_transient($token_key);

        if ($cached_token) {
            return ['Authorization' => "Bearer {$cached_token}"];
        }

        // Fetch new token
        $token = $this->fetch_oauth2_token($config);

        if ($token) {
            // Cache the token (expire 5 minutes before actual expiry)
            $expires_in = ($config['expires_in'] ?? 3600) - 300;
            set_transient($token_key, $token, max(60, $expires_in));

            return ['Authorization' => "Bearer {$token}"];
        }

        return [];
    }

    /**
     * Fetch OAuth2 access token
     *
     * @param array $config
     * @return string|null
     */
    private function fetch_oauth2_token(array $config): ?string
    {
        $token_url = $config['token_url'] ?? '';
        $client_id = $config['client_id'] ?? '';
        $client_secret = $config['client_secret'] ?? '';
        $grant_type = $config['grant_type'] ?? 'client_credentials';

        if (!$token_url || !$client_id) {
            return null;
        }

        $body = [
            'grant_type' => $grant_type,
            'client_id' => $client_id,
        ];

        if ($client_secret) {
            $body['client_secret'] = $client_secret;
        }

        // Add scope if specified
        if (!empty($config['scope'])) {
            $body['scope'] = $config['scope'];
        }

        $response = wp_remote_post($token_url, [
            'body' => $body,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Error_Generator::generate('OAuth2 Token Error', $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body['access_token'] ?? null;
    }

    /**
     * Build request body
     *
     * @param array $data
     * @param array $options
     * @return mixed
     */
    private function build_body(array $data, array $options): mixed
    {
        $content_type = $options['content_type'] ?? 'application/json';

        return match (true) {
            str_contains($content_type, 'application/json') => json_encode($data),
            str_contains($content_type, 'application/x-www-form-urlencoded') => http_build_query($data),
            str_contains($content_type, 'multipart/form-data') => $data,
            default => json_encode($data)
        };
    }

    /**
     * Merge headers from service, config, and request
     *
     * @param array $service
     * @param array $config
     * @param WP_REST_Request $request
     * @return array
     */
    private function merge_headers(array $service, array $config, WP_REST_Request $request): array
    {
        $headers = [];

        // Add forwarded headers if configured
        if ($config['forward_headers'] ?? false) {
            $forward_list = $config['forward_header_list'] ?? ['Content-Type', 'Accept'];
            foreach ($forward_list as $header) {
                $value = $request->get_header($header);
                if ($value) {
                    $headers[$header] = $value;
                }
            }
        }

        // Add custom headers from config
        if (!empty($config['custom_headers'])) {
            $headers = array_merge($headers, $config['custom_headers']);
        }

        return $headers;
    }

    /**
     * Check rate limiting
     *
     * @param array $service
     * @return bool
     */
    private function check_rate_limit(array $service): bool
    {
        $rate_config = $this->decode_json($service['rate_limit_config'] ?? '{}');

        if (empty($rate_config) || !($rate_config['enabled'] ?? false)) {
            return true;
        }

        $max_requests = $rate_config['max_requests'] ?? 100;
        $time_window = $rate_config['time_window'] ?? 60;

        $cache_key = 'wp_custom_api_rate_' . $service['id'];
        $requests = get_transient($cache_key) ?: 0;

        if ($requests >= $max_requests) {
            return false;
        }

        // Increment request count
        set_transient($cache_key, $requests + 1, $time_window);

        return true;
    }

    /**
     * Check if request should be retried
     *
     * @param array $response
     * @param array $retry_config
     * @return bool
     */
    private function should_retry(array $response, array $retry_config): bool
    {
        // Don't retry if successful
        if ($response['success']) {
            return false;
        }

        // Retry on connection errors
        if ($response['code'] === 0) {
            return true;
        }

        // Check retry status codes
        $retry_codes = $retry_config['retry_codes'] ?? [408, 429, 500, 502, 503, 504];

        return in_array($response['code'], $retry_codes);
    }

    /**
     * Perform health check on a service
     *
     * @param int $service_id
     * @return array
     */
    public function health_check(int $service_id): array
    {
        $service = $this->get_service($service_id);

        if (!$service) {
            return [
                'status' => self::HEALTH_UNKNOWN,
                'message' => 'Service not found'
            ];
        }

        $health_config = $this->decode_json($service['health_check_config'] ?? '{}');
        $endpoint = $health_config['endpoint'] ?? '/health';
        $expected_code = $health_config['expected_code'] ?? 200;
        $timeout = $health_config['timeout'] ?? 10;

        try {
            $response = $this->send($service_id, $endpoint, [], 'GET', ['timeout' => $timeout]);

            $status = match (true) {
                $response['code'] === $expected_code => self::HEALTH_HEALTHY,
                $response['code'] >= 200 && $response['code'] < 500 => self::HEALTH_DEGRADED,
                default => self::HEALTH_UNHEALTHY
            };

            // Update service health status
            Database::update_row(External_Service_Model::TABLE_NAME, $service_id, [
                'last_health_check' => time(),
                'health_status' => $status
            ]);

            // Clear cache
            unset(self::$service_cache[$service_id]);

            return [
                'status' => $status,
                'response_code' => $response['code'],
                'response_time_ms' => $response['response_time'] ?? null,
                'checked_at' => time()
            ];

        } catch (\Exception $e) {
            Database::update_row(External_Service_Model::TABLE_NAME, $service_id, [
                'last_health_check' => time(),
                'health_status' => self::HEALTH_UNHEALTHY
            ]);

            return [
                'status' => self::HEALTH_UNHEALTHY,
                'message' => $e->getMessage(),
                'checked_at' => time()
            ];
        }
    }

    /**
     * Decode JSON safely
     *
     * @param string|array $json
     * @return array
     */
    private function decode_json(string|array $json): array
    {
        if (is_array($json)) {
            return $json;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Create a new external service
     *
     * @param array $data
     * @return Response_Handler
     */
    public static function create_service(array $data): Response_Handler
    {
        // Validate required fields
        if (empty($data['name']) || empty($data['base_url'])) {
            return Response_Handler::response(false, 400, 'Name and base_url are required');
        }

        // Encode JSON fields
        $json_fields = ['auth_config', 'default_headers', 'retry_config', 'rate_limit_config', 'health_check_config'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        $data['is_active'] = $data['is_active'] ?? 1;
        $data['timeout'] = $data['timeout'] ?? 30;
        $data['health_status'] = self::HEALTH_UNKNOWN;

        return Database::insert_row(External_Service_Model::TABLE_NAME, $data);
    }

    /**
     * Update an external service
     *
     * @param int $id
     * @param array $data
     * @return Response_Handler
     */
    public static function update_service(int $id, array $data): Response_Handler
    {
        // Encode JSON fields
        $json_fields = ['auth_config', 'default_headers', 'retry_config', 'rate_limit_config', 'health_check_config'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        // Clear cache
        unset(self::$service_cache[$id]);

        return Database::update_row(External_Service_Model::TABLE_NAME, $id, $data);
    }

    /**
     * Delete an external service
     *
     * @param int $id
     * @return Response_Handler
     */
    public static function delete_service(int $id): Response_Handler
    {
        // Clear cache
        unset(self::$service_cache[$id]);

        return Database::delete_row(External_Service_Model::TABLE_NAME, $id);
    }

    /**
     * Get external service by ID
     *
     * @param int $id
     * @return Response_Handler
     */
    public static function get_service_by_id(int $id): Response_Handler
    {
        return Database::get_rows_data(External_Service_Model::TABLE_NAME, 'id', $id, false);
    }

    /**
     * Get all external services
     *
     * @return Response_Handler
     */
    public static function get_all_services(): Response_Handler
    {
        return Database::get_table_data(External_Service_Model::TABLE_NAME);
    }
}
