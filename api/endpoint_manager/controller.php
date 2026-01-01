<?php

declare(strict_types=1);

namespace WP_Custom_API\Api\Endpoint_Manager;

use WP_Custom_API\Includes\Controller_Interface;
use WP_Custom_API\Includes\Endpoint_Manager\Endpoint_Manager;
use WP_Custom_API\Includes\Endpoint_Manager\Webhook_Handler;
use WP_Custom_API\Includes\Endpoint_Manager\ETL_Engine;
use WP_Custom_API\Includes\Endpoint_Manager\External_Service_Connector;
use WP_Custom_API\Includes\Endpoint_Manager\System_Manager;
use WP_Custom_API\Includes\Endpoint_Manager\Configuration_Manager;
use WP_Custom_API\Includes\Endpoint_Manager\Event_Logger;
use WP_Custom_API\Includes\Endpoint_Manager\Scheduler;
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
 * - System Dashboard
 * - Configuration
 * - Event Logs
 * - Scheduled Tasks
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

    // ==================== SYSTEM DASHBOARD ====================

    /**
     * Get system status overview
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_system_status(WP_REST_Request $request): WP_REST_Response
    {
        $system = System_Manager::instance();
        return new WP_REST_Response([
            'status' => $system->get_status(),
            'statistics' => $system->get_statistics()
        ], 200);
    }

    /**
     * Get system health check
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_system_health(WP_REST_Request $request): WP_REST_Response
    {
        $system = System_Manager::instance();
        $health = $system->health_check();
        $status_code = $health['healthy'] ? 200 : 503;
        return new WP_REST_Response($health, $status_code);
    }

    /**
     * Get system statistics
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_system_statistics(WP_REST_Request $request): WP_REST_Response
    {
        $system = System_Manager::instance();
        return new WP_REST_Response($system->get_statistics(), 200);
    }

    /**
     * Enable maintenance mode
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function enable_maintenance(WP_REST_Request $request): WP_REST_Response
    {
        $reason = self::get_request_body($request)['reason'] ?? '';
        $system = System_Manager::instance();
        $system->enable_maintenance_mode($reason);
        return new WP_REST_Response([
            'message' => 'Maintenance mode enabled',
            'reason' => $reason
        ], 200);
    }

    /**
     * Disable maintenance mode
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function disable_maintenance(WP_REST_Request $request): WP_REST_Response
    {
        $system = System_Manager::instance();
        $system->disable_maintenance_mode();
        return new WP_REST_Response([
            'message' => 'Maintenance mode disabled'
        ], 200);
    }

    // ==================== CONFIGURATION ====================

    /**
     * Get all configuration settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_configuration(WP_REST_Request $request): WP_REST_Response
    {
        $grouped = $request->get_param('grouped') === 'true';

        if ($grouped) {
            return new WP_REST_Response(Configuration_Manager::get_grouped(), 200);
        }

        return new WP_REST_Response(Configuration_Manager::get_all(), 200);
    }

    /**
     * Get a specific configuration setting
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_config_setting(WP_REST_Request $request): WP_REST_Response
    {
        $key = $request->get_param('key');
        $value = Configuration_Manager::get($key);

        if ($value === null) {
            return new WP_REST_Response(['message' => 'Setting not found'], 404);
        }

        return new WP_REST_Response([
            'key' => $key,
            'value' => $value,
            'default' => Configuration_Manager::get_default($key)
        ], 200);
    }

    /**
     * Update configuration settings
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function update_configuration(WP_REST_Request $request): WP_REST_Response
    {
        $settings = self::get_request_body($request);

        if (empty($settings)) {
            return new WP_REST_Response(['message' => 'No settings provided'], 400);
        }

        $success = Configuration_Manager::set_many($settings);

        return new WP_REST_Response([
            'message' => $success ? 'Settings updated' : 'Failed to update settings',
            'settings' => $settings
        ], $success ? 200 : 500);
    }

    /**
     * Reset configuration to defaults
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function reset_configuration(WP_REST_Request $request): WP_REST_Response
    {
        $keys = self::get_request_body($request)['keys'] ?? null;
        $success = Configuration_Manager::reset($keys);

        return new WP_REST_Response([
            'message' => $success ? 'Settings reset to defaults' : 'Failed to reset settings'
        ], $success ? 200 : 500);
    }

    /**
     * Export configuration
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function export_configuration(WP_REST_Request $request): WP_REST_Response
    {
        $export = Configuration_Manager::export();
        return new WP_REST_Response([
            'config' => json_decode($export, true),
            'exported_at' => time()
        ], 200);
    }

    /**
     * Import configuration
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function import_configuration(WP_REST_Request $request): WP_REST_Response
    {
        $body = self::get_request_body($request);
        $config = $body['config'] ?? $body;
        $merge = $body['merge'] ?? true;

        $json = is_array($config) ? json_encode($config) : $config;
        $success = Configuration_Manager::import($json, $merge);

        return new WP_REST_Response([
            'message' => $success ? 'Configuration imported' : 'Failed to import configuration'
        ], $success ? 200 : 500);
    }

    // ==================== EVENT LOGS ====================

    /**
     * Get event logs
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_event_logs(WP_REST_Request $request): WP_REST_Response
    {
        $filters = [
            'category' => $request->get_param('category'),
            'level' => $request->get_param('level'),
            'min_level' => $request->get_param('min_level'),
            'from' => $request->get_param('from'),
            'to' => $request->get_param('to'),
            'user_id' => $request->get_param('user_id'),
            'search' => $request->get_param('search'),
            'page' => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
        ];

        // Remove null filters
        $filters = array_filter($filters, fn($v) => $v !== null);

        $result = Event_Logger::get_events($filters);
        return self::response($result, $result->status_code);
    }

    /**
     * Get event log statistics
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_event_statistics(WP_REST_Request $request): WP_REST_Response
    {
        $period = $request->get_param('period') ?? 'today';
        $stats = Event_Logger::get_statistics($period);
        return new WP_REST_Response($stats, 200);
    }

    /**
     * Export event logs
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function export_event_logs(WP_REST_Request $request): WP_REST_Response
    {
        $filters = self::get_request_body($request);
        $format = $request->get_param('format') ?? 'json';

        $result = Event_Logger::export($filters, $format);
        return new WP_REST_Response($result, 200);
    }

    /**
     * Cleanup event logs
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function cleanup_event_logs(WP_REST_Request $request): WP_REST_Response
    {
        $days = (int) ($request->get_param('days') ?? 90);
        $result = Event_Logger::cleanup($days);
        return self::response($result, $result->status_code);
    }

    // ==================== SCHEDULED TASKS ====================

    /**
     * List all scheduled tasks
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function list_scheduled_tasks(WP_REST_Request $request): WP_REST_Response
    {
        $result = Scheduler::get_all_tasks();
        return self::response($result, $result->status_code);
    }

    /**
     * Get a specific scheduled task
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_scheduled_task(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = Scheduler::get_task($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Create a scheduled task
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function create_scheduled_task(WP_REST_Request $request): WP_REST_Response
    {
        $data = self::get_request_body($request);
        $result = Scheduler::create_task($data);
        return self::response($result, $result->status_code);
    }

    /**
     * Update a scheduled task
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function update_scheduled_task(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $data = self::get_request_body($request);
        $result = Scheduler::update_task($id, $data);
        return self::response($result, $result->status_code);
    }

    /**
     * Delete a scheduled task
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function delete_scheduled_task(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = Scheduler::delete_task($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Pause a scheduled task
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function pause_scheduled_task(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = Scheduler::pause_task($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Resume a scheduled task
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function resume_scheduled_task(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = Scheduler::resume_task($id);
        return self::response($result, $result->status_code);
    }

    /**
     * Run a scheduled task immediately
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function run_scheduled_task(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = Scheduler::run_now($id);
        return new WP_REST_Response($result, $result['success'] ? 200 : 500);
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
