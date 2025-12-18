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
 * Endpoint Manager - Dynamic REST API Endpoint Registration System
 *
 * This class handles the registration and execution of dynamically configured
 * custom endpoints. It supports various handler types including:
 * - webhook: Receives and stores incoming webhook data
 * - action: Executes WordPress actions/hooks
 * - script: Executes custom PHP callbacks
 * - forward: Forwards requests to external services
 *
 * @since 1.1.0
 */

final class Endpoint_Manager
{
    /**
     * Handler type constants
     */
    public const HANDLER_WEBHOOK = 'webhook';
    public const HANDLER_ACTION = 'action';
    public const HANDLER_SCRIPT = 'script';
    public const HANDLER_FORWARD = 'forward';
    public const HANDLER_ETL = 'etl';

    /**
     * Permission type constants
     */
    public const PERMISSION_PUBLIC = 'public';
    public const PERMISSION_SIGNATURE = 'signature';
    public const PERMISSION_API_KEY = 'api_key';
    public const PERMISSION_TOKEN = 'token';
    public const PERMISSION_IP_WHITELIST = 'ip_whitelist';

    /**
     * Cached endpoints
     */
    private static ?array $endpoints = null;

    /**
     * Registered custom callbacks for script handlers
     */
    private static array $custom_callbacks = [];

    /**
     * Initialize the Endpoint Manager
     * Registers all active custom endpoints with WordPress REST API
     *
     * @return void
     */
    public static function init(): void
    {
        // Ensure tables exist
        self::ensure_tables_exist();

        // Register dynamic endpoints
        add_action('rest_api_init', [self::class, 'register_dynamic_endpoints']);

        // Register hooks for custom endpoint actions
        do_action('wp_custom_api_endpoint_manager_init');
    }

    /**
     * Ensure all required tables exist
     *
     * @return void
     */
    private static function ensure_tables_exist(): void
    {
        $tables = [
            Custom_Endpoint_Model::TABLE_NAME => Custom_Endpoint_Model::schema(),
            Webhook_Log_Model::TABLE_NAME => Webhook_Log_Model::schema(),
            ETL_Template_Model::TABLE_NAME => ETL_Template_Model::schema(),
            ETL_Job_Model::TABLE_NAME => ETL_Job_Model::schema(),
            External_Service_Model::TABLE_NAME => External_Service_Model::schema(),
        ];

        foreach ($tables as $table_name => $schema) {
            if (!Database::table_exists($table_name)) {
                Database::create_table($table_name, $schema);
            }
        }
    }

    /**
     * Register a custom callback for script handlers
     *
     * @param string $callback_name Unique name for the callback
     * @param callable $callback The callback function
     * @return void
     */
    public static function register_callback(string $callback_name, callable $callback): void
    {
        self::$custom_callbacks[$callback_name] = $callback;
    }

    /**
     * Get all active endpoints from database
     *
     * @param bool $force_refresh Force refresh from database
     * @return array
     */
    public static function get_active_endpoints(bool $force_refresh = false): array
    {
        if (self::$endpoints !== null && !$force_refresh) {
            return self::$endpoints;
        }

        $result = Database::get_rows_data(
            Custom_Endpoint_Model::TABLE_NAME,
            'is_active',
            1
        );

        self::$endpoints = $result->ok && is_array($result->data) ? $result->data : [];

        return self::$endpoints;
    }

    /**
     * Register all dynamic endpoints with WordPress REST API
     *
     * @return void
     */
    public static function register_dynamic_endpoints(): void
    {
        $endpoints = self::get_active_endpoints();

        foreach ($endpoints as $endpoint) {
            self::register_single_endpoint($endpoint);
        }

        do_action('wp_custom_api_dynamic_endpoints_registered', $endpoints);
    }

    /**
     * Register a single endpoint
     *
     * @param array $endpoint Endpoint configuration
     * @return void
     */
    private static function register_single_endpoint(array $endpoint): void
    {
        $route = self::build_route($endpoint);
        $methods = strtoupper($endpoint['method'] ?? 'POST');

        register_rest_route(Config::BASE_API_ROUTE, $route, [
            'methods' => $methods,
            'callback' => function (WP_REST_Request $request) use ($endpoint) {
                return self::handle_request($request, $endpoint);
            },
            'permission_callback' => function (WP_REST_Request $request) use ($endpoint) {
                return self::check_permission($request, $endpoint);
            },
        ]);
    }

    /**
     * Build the route pattern for an endpoint
     *
     * @param array $endpoint Endpoint configuration
     * @return string
     */
    private static function build_route(array $endpoint): string
    {
        $slug = sanitize_title($endpoint['slug'] ?? '');
        $route = trim($endpoint['route'] ?? '', '/');

        // Replace {param} with regex patterns
        $route = preg_replace('/\{(\w+)\}/', '(?P<$1>[a-zA-Z0-9_-]+)', $route);

        return "custom/{$slug}" . ($route ? "/{$route}" : '');
    }

    /**
     * Check permission for endpoint access
     *
     * @param WP_REST_Request $request
     * @param array $endpoint
     * @return bool
     */
    private static function check_permission(WP_REST_Request $request, array $endpoint): bool
    {
        $permission_type = $endpoint['permission_type'] ?? self::PERMISSION_PUBLIC;
        $permission_config = self::decode_json($endpoint['permission_config'] ?? '{}');

        switch ($permission_type) {
            case self::PERMISSION_PUBLIC:
                return true;

            case self::PERMISSION_SIGNATURE:
                return self::validate_signature($request, $permission_config);

            case self::PERMISSION_API_KEY:
                return self::validate_api_key($request, $permission_config);

            case self::PERMISSION_TOKEN:
                return self::validate_token($request, $permission_config);

            case self::PERMISSION_IP_WHITELIST:
                return self::validate_ip_whitelist($request, $permission_config);

            default:
                // Allow custom permission checks via filter
                return apply_filters(
                    'wp_custom_api_custom_permission_check',
                    false,
                    $permission_type,
                    $request,
                    $permission_config
                );
        }
    }

    /**
     * Handle an incoming request to a dynamic endpoint
     *
     * @param WP_REST_Request $request
     * @param array $endpoint
     * @return WP_REST_Response
     */
    private static function handle_request(WP_REST_Request $request, array $endpoint): WP_REST_Response
    {
        $handler_type = $endpoint['handler_type'] ?? self::HANDLER_WEBHOOK;
        $handler_config = self::decode_json($endpoint['handler_config'] ?? '{}');

        // Allow pre-processing
        $pre_result = apply_filters(
            'wp_custom_api_pre_handle_request',
            null,
            $request,
            $endpoint
        );

        if ($pre_result instanceof WP_REST_Response) {
            return $pre_result;
        }

        try {
            $response = match ($handler_type) {
                self::HANDLER_WEBHOOK => self::handle_webhook($request, $endpoint, $handler_config),
                self::HANDLER_ACTION => self::handle_action($request, $endpoint, $handler_config),
                self::HANDLER_SCRIPT => self::handle_script($request, $endpoint, $handler_config),
                self::HANDLER_FORWARD => self::handle_forward($request, $endpoint, $handler_config),
                self::HANDLER_ETL => self::handle_etl($request, $endpoint, $handler_config),
                default => new WP_REST_Response(['message' => 'Unknown handler type'], 400),
            };
        } catch (\Exception $e) {
            Error_Generator::generate('Endpoint Handler Error', $e->getMessage());
            $response = new WP_REST_Response([
                'message' => Config::DEBUG_MESSAGE_MODE ? $e->getMessage() : 'An error occurred processing the request'
            ], 500);
        }

        // Allow post-processing
        return apply_filters('wp_custom_api_post_handle_request', $response, $request, $endpoint);
    }

    /**
     * Handle webhook type endpoints
     *
     * @param WP_REST_Request $request
     * @param array $endpoint
     * @param array $config
     * @return WP_REST_Response
     */
    private static function handle_webhook(WP_REST_Request $request, array $endpoint, array $config): WP_REST_Response
    {
        $webhook_handler = new Webhook_Handler();
        return $webhook_handler->receive($request, $endpoint, $config);
    }

    /**
     * Handle action type endpoints (WordPress hooks)
     *
     * @param WP_REST_Request $request
     * @param array $endpoint
     * @param array $config
     * @return WP_REST_Response
     */
    private static function handle_action(WP_REST_Request $request, array $endpoint, array $config): WP_REST_Response
    {
        $action_name = $config['action_name'] ?? '';

        if (empty($action_name)) {
            return new WP_REST_Response(['message' => 'No action name configured'], 400);
        }

        $request_data = self::get_request_data($request);

        // Execute the action
        do_action($action_name, $request_data, $endpoint, $request);

        // Check for response set by action
        $response_data = apply_filters(
            "wp_custom_api_action_response_{$action_name}",
            ['message' => 'Action executed successfully'],
            $request_data,
            $endpoint
        );

        $status_code = $response_data['status_code'] ?? 200;
        unset($response_data['status_code']);

        return new WP_REST_Response($response_data, $status_code);
    }

    /**
     * Handle script type endpoints (custom PHP callbacks)
     *
     * @param WP_REST_Request $request
     * @param array $endpoint
     * @param array $config
     * @return WP_REST_Response
     */
    private static function handle_script(WP_REST_Request $request, array $endpoint, array $config): WP_REST_Response
    {
        $callback_name = $config['callback_name'] ?? '';

        if (empty($callback_name) || !isset(self::$custom_callbacks[$callback_name])) {
            return new WP_REST_Response([
                'message' => 'Callback not registered: ' . $callback_name
            ], 400);
        }

        $callback = self::$custom_callbacks[$callback_name];
        $request_data = self::get_request_data($request);

        $result = call_user_func($callback, $request_data, $endpoint, $request);

        if ($result instanceof WP_REST_Response) {
            return $result;
        }

        return new WP_REST_Response(
            is_array($result) ? $result : ['data' => $result],
            200
        );
    }

    /**
     * Handle forward type endpoints (proxy to external services)
     *
     * @param WP_REST_Request $request
     * @param array $endpoint
     * @param array $config
     * @return WP_REST_Response
     */
    private static function handle_forward(WP_REST_Request $request, array $endpoint, array $config): WP_REST_Response
    {
        $service_id = $config['external_service_id'] ?? null;
        $target_path = $config['target_path'] ?? '';

        if (!$service_id) {
            return new WP_REST_Response(['message' => 'No external service configured'], 400);
        }

        $connector = new External_Service_Connector();
        return $connector->forward($request, $service_id, $target_path, $config);
    }

    /**
     * Handle ETL type endpoints
     *
     * @param WP_REST_Request $request
     * @param array $endpoint
     * @param array $config
     * @return WP_REST_Response
     */
    private static function handle_etl(WP_REST_Request $request, array $endpoint, array $config): WP_REST_Response
    {
        $template_id = $config['template_id'] ?? null;

        if (!$template_id) {
            return new WP_REST_Response(['message' => 'No ETL template configured'], 400);
        }

        $etl_engine = new ETL_Engine();
        return $etl_engine->process($request, $template_id, $endpoint);
    }

    /**
     * Validate webhook signature
     *
     * @param WP_REST_Request $request
     * @param array $config
     * @return bool
     */
    private static function validate_signature(WP_REST_Request $request, array $config): bool
    {
        $header_name = $config['signature_header'] ?? 'X-Webhook-Signature';
        $secret = $config['signature_secret'] ?? '';
        $algorithm = $config['signature_algorithm'] ?? 'sha256';

        $signature = $request->get_header($header_name);
        if (!$signature || !$secret) {
            return false;
        }

        $payload = $request->get_body();
        $expected = hash_hmac($algorithm, $payload, $secret);

        // Handle various signature formats
        $signature = preg_replace('/^sha256=/', '', $signature);

        return hash_equals($expected, $signature);
    }

    /**
     * Validate API key
     *
     * @param WP_REST_Request $request
     * @param array $config
     * @return bool
     */
    private static function validate_api_key(WP_REST_Request $request, array $config): bool
    {
        $header_name = $config['api_key_header'] ?? 'X-API-Key';
        $query_param = $config['api_key_param'] ?? 'api_key';
        $valid_keys = $config['api_keys'] ?? [];

        if (!is_array($valid_keys)) {
            $valid_keys = [$valid_keys];
        }

        // Check header first
        $api_key = $request->get_header($header_name);

        // Fall back to query parameter
        if (!$api_key) {
            $api_key = $request->get_param($query_param);
        }

        return $api_key && in_array($api_key, $valid_keys, true);
    }

    /**
     * Validate token
     *
     * @param WP_REST_Request $request
     * @param array $config
     * @return bool
     */
    private static function validate_token(WP_REST_Request $request, array $config): bool
    {
        $header_name = $config['token_header'] ?? 'Authorization';
        $token = $request->get_header($header_name);

        if (!$token) {
            return false;
        }

        // Remove Bearer prefix if present
        $token = preg_replace('/^Bearer\s+/i', '', $token);

        // Use the plugin's built-in token validation
        $auth_token = new \WP_Custom_API\Includes\Auth_Token();
        $validation = $auth_token->validate($token);

        return $validation->ok ?? false;
    }

    /**
     * Validate IP whitelist
     *
     * @param WP_REST_Request $request
     * @param array $config
     * @return bool
     */
    private static function validate_ip_whitelist(WP_REST_Request $request, array $config): bool
    {
        $whitelist = $config['ip_whitelist'] ?? [];

        if (empty($whitelist)) {
            return true;
        }

        $client_ip = self::get_client_ip();

        foreach ($whitelist as $allowed_ip) {
            if (self::ip_matches($client_ip, $allowed_ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public static function get_client_ip(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxies
            'HTTP_X_REAL_IP',            // Nginx
            'REMOTE_ADDR',               // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return '';
    }

    /**
     * Check if IP matches pattern (supports CIDR notation)
     *
     * @param string $ip
     * @param string $pattern
     * @return bool
     */
    private static function ip_matches(string $ip, string $pattern): bool
    {
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation
        if (strpos($pattern, '/') !== false) {
            list($subnet, $mask) = explode('/', $pattern);
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask_long = -1 << (32 - (int)$mask);

            return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
        }

        // Wildcard notation
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('.', '\.', $pattern);
            $pattern = str_replace('*', '.*', $pattern);
            return (bool) preg_match("/^{$pattern}$/", $ip);
        }

        return false;
    }

    /**
     * Get all request data as array
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public static function get_request_data(WP_REST_Request $request): array
    {
        $params = $request->get_params() ?? [];
        $json = json_decode($request->get_body(), true) ?? [];
        $form = $request->get_body_params() ?? [];

        return array_merge($params, $json, $form);
    }

    /**
     * Decode JSON safely
     *
     * @param string|array $json
     * @return array
     */
    private static function decode_json(string|array $json): array
    {
        if (is_array($json)) {
            return $json;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Create a new custom endpoint
     *
     * @param array $data Endpoint configuration
     * @return Response_Handler
     */
    public static function create_endpoint(array $data): Response_Handler
    {
        // Validate required fields
        $required = ['name', 'slug', 'route', 'method', 'handler_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return Response_Handler::response(false, 400, "Missing required field: {$field}");
            }
        }

        // Set defaults
        $data['is_active'] = $data['is_active'] ?? 1;
        $data['permission_type'] = $data['permission_type'] ?? self::PERMISSION_PUBLIC;

        // Encode JSON fields
        $json_fields = ['handler_config', 'permission_config', 'request_schema', 'response_schema'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        $result = Database::insert_row(Custom_Endpoint_Model::TABLE_NAME, $data);

        if ($result->ok) {
            self::$endpoints = null; // Clear cache
            do_action('wp_custom_api_endpoint_created', $result->data['id'], $data);
        }

        return $result;
    }

    /**
     * Update an existing endpoint
     *
     * @param int $id Endpoint ID
     * @param array $data Updated configuration
     * @return Response_Handler
     */
    public static function update_endpoint(int $id, array $data): Response_Handler
    {
        // Encode JSON fields
        $json_fields = ['handler_config', 'permission_config', 'request_schema', 'response_schema'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        $result = Database::update_row(Custom_Endpoint_Model::TABLE_NAME, $id, $data);

        if ($result->ok) {
            self::$endpoints = null; // Clear cache
            do_action('wp_custom_api_endpoint_updated', $id, $data);
        }

        return $result;
    }

    /**
     * Delete an endpoint
     *
     * @param int $id Endpoint ID
     * @return Response_Handler
     */
    public static function delete_endpoint(int $id): Response_Handler
    {
        $result = Database::delete_row(Custom_Endpoint_Model::TABLE_NAME, $id);

        if ($result->ok) {
            self::$endpoints = null; // Clear cache
            do_action('wp_custom_api_endpoint_deleted', $id);
        }

        return $result;
    }

    /**
     * Get endpoint by ID
     *
     * @param int $id Endpoint ID
     * @return Response_Handler
     */
    public static function get_endpoint(int $id): Response_Handler
    {
        return Database::get_rows_data(Custom_Endpoint_Model::TABLE_NAME, 'id', $id, false);
    }

    /**
     * Get all endpoints
     *
     * @return Response_Handler
     */
    public static function get_all_endpoints(): Response_Handler
    {
        return Database::get_table_data(Custom_Endpoint_Model::TABLE_NAME);
    }
}
