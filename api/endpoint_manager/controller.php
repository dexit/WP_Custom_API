<?php

declare(strict_types=1);

namespace WP_Custom_API\Api\Endpoint_Manager;

use WP_Custom_API\Includes\Controller_Interface;
use WP_Custom_API\Includes\Endpoint_Manager\Endpoint_Manager;
use WP_Custom_API\Includes\Endpoint_Manager\Webhook_Handler;
use WP_Custom_API\Includes\Endpoint_Manager\ETL_Engine;
use WP_Custom_API\Includes\Endpoint_Manager\External_Service_Connector;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Endpoint Manager API Controller
 *
 * Handles all CRUD operations for:
 * - Custom Endpoints
 * - Webhook Logs
 * - ETL Templates
 * - ETL Jobs
 * - External Services
 *
 * @since 1.1.0
 */

final class Controller extends Controller_Interface
{
    // ==================== ENDPOINTS ====================

    /**
     * List all custom endpoints
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function list_endpoints(WP_REST_Request $request): WP_REST_Response
    {
        $result = Endpoint_Manager::get_all_endpoints();
        return self::response($result, $result->status_code);
    }

    /**
     * Get a specific endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_endpoint(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = Endpoint_Manager::get_endpoint($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Create a new endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function create_endpoint(WP_REST_Request $request): WP_REST_Response
    {
        $data = self::get_endpoint_data($request);
        $result = Endpoint_Manager::create_endpoint($data);
        return self::response($result, $result->status_code);
    }

    /**
     * Update an endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function update_endpoint(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $data = self::get_endpoint_data($request);
        $result = Endpoint_Manager::update_endpoint($id, $data);
        return self::response($result, $result->status_code);
    }

    /**
     * Delete an endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function delete_endpoint(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = Endpoint_Manager::delete_endpoint($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Extract endpoint data from request
     *
     * @param WP_REST_Request $request
     * @return array
     */
    private static function get_endpoint_data(WP_REST_Request $request): array
    {
        $params = $request->get_params();
        $json = json_decode($request->get_body(), true) ?? [];

        $data = array_merge($params, $json);

        // Remove route params
        unset($data['id']);

        return $data;
    }

    // ==================== WEBHOOK LOGS ====================

    /**
     * List webhook logs for an endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function list_webhook_logs(WP_REST_Request $request): WP_REST_Response
    {
        $endpoint_id = (int) $request->get_param('endpoint_id');
        $result = Webhook_Handler::get_logs($endpoint_id);
        return self::response($result, $result->status_code);
    }

    /**
     * Get a specific webhook log
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_webhook_log(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = Webhook_Handler::get_log($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Retry a failed webhook
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function retry_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = Webhook_Handler::retry($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Cleanup old webhook logs
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function cleanup_webhook_logs(WP_REST_Request $request): WP_REST_Response
    {
        $days = (int) ($request->get_param('days') ?? 30);
        $result = Webhook_Handler::cleanup($days);
        return self::response($result, $result->status_code);
    }

    // ==================== ETL TEMPLATES ====================

    /**
     * List all ETL templates
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function list_etl_templates(WP_REST_Request $request): WP_REST_Response
    {
        $result = ETL_Engine::get_all_templates();
        return self::response($result, $result->status_code);
    }

    /**
     * Get a specific ETL template
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_etl_template(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = ETL_Engine::get_template($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Create a new ETL template
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function create_etl_template(WP_REST_Request $request): WP_REST_Response
    {
        $data = self::get_request_body($request);
        $result = ETL_Engine::create_template($data);
        return self::response($result, $result->status_code);
    }

    /**
     * Update an ETL template
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function update_etl_template(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $data = self::get_request_body($request);
        $result = ETL_Engine::update_template($id, $data);
        return self::response($result, $result->status_code);
    }

    /**
     * Delete an ETL template
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function delete_etl_template(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = ETL_Engine::delete_template($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Test an ETL template with sample data
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function test_etl_template(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $etl_engine = new ETL_Engine();

        // Get test data from request
        $test_data = self::get_request_body($request)['test_data'] ?? [];

        // Create a mock endpoint config
        $endpoint = ['id' => 0, 'name' => 'test'];

        return $etl_engine->process($request, $id, $endpoint);
    }

    // ==================== ETL JOBS ====================

    /**
     * List ETL jobs for a template
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function list_etl_jobs(WP_REST_Request $request): WP_REST_Response
    {
        $template_id = (int) $request->get_param('template_id');
        $result = ETL_Engine::get_jobs_for_template($template_id);
        return self::response($result, $result->status_code);
    }

    /**
     * Get a specific ETL job
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_etl_job(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = ETL_Engine::get_job($id);
        return self::response($result, $result->status_code);
    }

    // ==================== EXTERNAL SERVICES ====================

    /**
     * List all external services
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function list_external_services(WP_REST_Request $request): WP_REST_Response
    {
        $result = External_Service_Connector::get_all_services();
        return self::response($result, $result->status_code);
    }

    /**
     * Get a specific external service
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_external_service(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = External_Service_Connector::get_service_by_id($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Create a new external service
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function create_external_service(WP_REST_Request $request): WP_REST_Response
    {
        $data = self::get_request_body($request);
        $result = External_Service_Connector::create_service($data);
        return self::response($result, $result->status_code);
    }

    /**
     * Update an external service
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function update_external_service(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $data = self::get_request_body($request);
        $result = External_Service_Connector::update_service($id, $data);
        return self::response($result, $result->status_code);
    }

    /**
     * Delete an external service
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function delete_external_service(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = External_Service_Connector::delete_service($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Health check an external service
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function health_check_service(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $connector = new External_Service_Connector();
        $result = $connector->health_check($id);
        return new WP_REST_Response($result, 200);
    }

    /**
     * Test connection to an external service
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function test_external_service(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $path = $request->get_param('path') ?? '/';
        $method = $request->get_param('method') ?? 'GET';
        $data = self::get_request_body($request)['data'] ?? [];

        $connector = new External_Service_Connector();
        $result = $connector->send($id, $path, $data, $method);

        return new WP_REST_Response($result, $result['code'] ?? 200);
    }

    // ==================== HELPERS ====================

    /**
     * Get request body as array
     *
     * @param WP_REST_Request $request
     * @return array
     */
    private static function get_request_body(WP_REST_Request $request): array
    {
        $json = json_decode($request->get_body(), true);

        if (is_array($json)) {
            return $json;
        }

        return array_merge(
            $request->get_params() ?? [],
            $request->get_body_params() ?? []
        );
    }
}
