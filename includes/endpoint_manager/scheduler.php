<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Config;
use WP_Custom_API\Includes\Database;
use WP_Custom_API\Includes\Response_Handler;
use WP_Custom_API\Includes\Error_Generator;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Scheduler - Cron-based Task Scheduling System
 *
 * Manages scheduled tasks for:
 * - Periodic ETL job execution
 * - Webhook log cleanup
 * - Event log cleanup
 * - External service health checks
 * - Custom scheduled tasks
 *
 * @since 1.1.0
 */

final class Scheduler
{
    /**
     * Task status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PAUSED = 'paused';

    /**
     * Schedule frequency constants
     */
    public const FREQ_ONCE = 'once';
    public const FREQ_HOURLY = 'hourly';
    public const FREQ_TWICE_DAILY = 'twicedaily';
    public const FREQ_DAILY = 'daily';
    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_MONTHLY = 'monthly';

    /**
     * Task type constants
     */
    public const TYPE_ETL = 'etl';
    public const TYPE_CLEANUP = 'cleanup';
    public const TYPE_HEALTH_CHECK = 'health_check';
    public const TYPE_WEBHOOK_RETRY = 'webhook_retry';
    public const TYPE_CUSTOM = 'custom';

    /**
     * WordPress cron hook name
     */
    private const CRON_HOOK = 'wp_custom_api_scheduled_task';

    /**
     * Custom cron schedules
     */
    private static array $custom_schedules = [];

    /**
     * Initialize the scheduler
     *
     * @return void
     */
    public static function init(): void
    {
        // Register custom cron schedules
        add_filter('cron_schedules', [self::class, 'register_schedules']);

        // Register the main cron action
        add_action(self::CRON_HOOK, [self::class, 'execute_due_tasks']);

        // Schedule the main cron if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'every_minute', self::CRON_HOOK);
        }

        // Register built-in scheduled tasks
        self::register_builtin_tasks();

        // Allow custom task registration
        do_action('wp_custom_api_register_scheduled_tasks');
    }

    /**
     * Register custom WordPress cron schedules
     *
     * @param array $schedules
     * @return array
     */
    public static function register_schedules(array $schedules): array
    {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display' => 'Every Minute'
        ];

        $schedules['every_5_minutes'] = [
            'interval' => 300,
            'display' => 'Every 5 Minutes'
        ];

        $schedules['every_15_minutes'] = [
            'interval' => 900,
            'display' => 'Every 15 Minutes'
        ];

        $schedules['every_30_minutes'] = [
            'interval' => 1800,
            'display' => 'Every 30 Minutes'
        ];

        // Merge any custom schedules
        return array_merge($schedules, self::$custom_schedules);
    }

    /**
     * Register built-in scheduled tasks
     *
     * @return void
     */
    private static function register_builtin_tasks(): void
    {
        // Webhook log cleanup (daily)
        self::ensure_task_exists([
            'name' => 'Webhook Log Cleanup',
            'task_type' => self::TYPE_CLEANUP,
            'handler' => 'cleanup_webhook_logs',
            'frequency' => self::FREQ_DAILY,
            'config' => ['days_old' => 30],
            'is_system' => true
        ]);

        // Event log cleanup (daily)
        self::ensure_task_exists([
            'name' => 'Event Log Cleanup',
            'task_type' => self::TYPE_CLEANUP,
            'handler' => 'cleanup_event_logs',
            'frequency' => self::FREQ_DAILY,
            'config' => ['days_old' => 90],
            'is_system' => true
        ]);

        // External service health checks (hourly)
        self::ensure_task_exists([
            'name' => 'External Service Health Checks',
            'task_type' => self::TYPE_HEALTH_CHECK,
            'handler' => 'check_external_services',
            'frequency' => self::FREQ_HOURLY,
            'config' => [],
            'is_system' => true
        ]);

        // Failed webhook retry (every 15 minutes)
        self::ensure_task_exists([
            'name' => 'Failed Webhook Retry',
            'task_type' => self::TYPE_WEBHOOK_RETRY,
            'handler' => 'retry_failed_webhooks',
            'frequency' => 'every_15_minutes',
            'config' => ['max_retries' => 3],
            'is_system' => true
        ]);
    }

    /**
     * Ensure a task exists in the database
     *
     * @param array $task
     * @return void
     */
    private static function ensure_task_exists(array $task): void
    {
        if (!Database::table_exists(Scheduled_Task_Model::TABLE_NAME)) {
            return;
        }

        global $wpdb;
        $table = Database::get_table_full_name(Scheduled_Task_Model::TABLE_NAME);

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE handler = %s",
            $task['handler']
        ));

        if (!$existing) {
            self::create_task($task);
        }
    }

    /**
     * Execute all due tasks
     *
     * @return void
     */
    public static function execute_due_tasks(): void
    {
        if (!Database::table_exists(Scheduled_Task_Model::TABLE_NAME)) {
            return;
        }

        global $wpdb;
        $table = Database::get_table_full_name(Scheduled_Task_Model::TABLE_NAME);
        $now = time();

        // Get due tasks
        $due_tasks = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$table}
            WHERE is_active = 1
            AND status != %s
            AND (next_run_at IS NULL OR next_run_at <= %d)
            ORDER BY priority DESC
            LIMIT 10
        ", self::STATUS_RUNNING, $now), ARRAY_A);

        foreach ($due_tasks as $task) {
            self::execute_task($task);
        }
    }

    /**
     * Execute a single task
     *
     * @param array $task
     * @return array
     */
    public static function execute_task(array $task): array
    {
        $task_id = (int) $task['id'];
        $start_time = microtime(true);
        $result = ['success' => false, 'message' => '', 'data' => null];

        // Mark as running
        Database::update_row(Scheduled_Task_Model::TABLE_NAME, $task_id, [
            'status' => self::STATUS_RUNNING,
            'last_run_at' => time()
        ]);

        try {
            $handler = $task['handler'];
            $config = json_decode($task['config'] ?? '{}', true) ?: [];

            // Execute based on task type
            $result = match ($task['task_type']) {
                self::TYPE_ETL => self::execute_etl_task($task, $config),
                self::TYPE_CLEANUP => self::execute_cleanup_task($handler, $config),
                self::TYPE_HEALTH_CHECK => self::execute_health_check_task($config),
                self::TYPE_WEBHOOK_RETRY => self::execute_webhook_retry_task($config),
                self::TYPE_CUSTOM => self::execute_custom_task($handler, $config),
                default => ['success' => false, 'message' => 'Unknown task type']
            };

            $status = $result['success'] ? self::STATUS_COMPLETED : self::STATUS_FAILED;

        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ];
            $status = self::STATUS_FAILED;

            Error_Generator::generate('Scheduled Task Error', $e->getMessage());
        }

        $execution_time = microtime(true) - $start_time;

        // Calculate next run
        $next_run = self::calculate_next_run($task['frequency']);

        // Update task
        $run_count = (int) ($task['run_count'] ?? 0) + 1;
        $fail_count = $task['fail_count'] ?? 0;

        if (!$result['success']) {
            $fail_count++;
        }

        Database::update_row(Scheduled_Task_Model::TABLE_NAME, $task_id, [
            'status' => $status,
            'next_run_at' => $next_run,
            'last_result' => json_encode($result),
            'last_duration' => (int) ($execution_time * 1000),
            'run_count' => $run_count,
            'fail_count' => $fail_count
        ]);

        // Log execution
        Event_Logger::log(
            Event_Logger::CATEGORY_SCHEDULER,
            $result['success'] ? 'Task completed' : 'Task failed',
            [
                'task_id' => $task_id,
                'task_name' => $task['name'],
                'duration_ms' => (int) ($execution_time * 1000),
                'message' => $result['message']
            ],
            $result['success'] ? Event_Logger::LEVEL_INFO : Event_Logger::LEVEL_ERROR
        );

        // Fire action
        do_action('wp_custom_api_task_executed', $task_id, $result);

        return $result;
    }

    /**
     * Execute ETL task
     *
     * @param array $task
     * @param array $config
     * @return array
     */
    private static function execute_etl_task(array $task, array $config): array
    {
        $template_id = $config['template_id'] ?? null;

        if (!$template_id) {
            return ['success' => false, 'message' => 'No template ID configured'];
        }

        // Get data source
        $data = [];

        if (!empty($config['source_query'])) {
            // Execute SQL query for data
            $query_result = Database::execute_query($config['source_query']);
            if ($query_result->ok) {
                $data = $query_result->data;
            }
        } elseif (!empty($config['source_endpoint'])) {
            // Fetch from external endpoint
            $connector = new External_Service_Connector();
            $response = $connector->send(
                (int) $config['source_service_id'],
                $config['source_endpoint'],
                [],
                'GET'
            );
            if ($response['success']) {
                $data = $response['body'];
            }
        }

        if (empty($data)) {
            return ['success' => true, 'message' => 'No data to process'];
        }

        // Run ETL for each record or batch
        $etl_engine = new ETL_Engine();
        $processed = 0;
        $failed = 0;

        $data_items = isset($data[0]) ? $data : [$data];

        foreach ($data_items as $item) {
            // Create a job for each item
            $job_data = [
                'template_id' => $template_id,
                'status' => 'pending',
                'started_at' => time(),
                'input_data' => json_encode($item),
                'retry_count' => 0
            ];

            $job_result = Database::insert_row(ETL_Job_Model::TABLE_NAME, $job_data);

            if ($job_result->ok) {
                $result = $etl_engine->run_job($job_result->data['id'], $template_id, $item);
                if (is_array($result) && ($result['success'] ?? false)) {
                    $processed++;
                } else {
                    $failed++;
                }
            }
        }

        return [
            'success' => $failed === 0,
            'message' => "Processed: {$processed}, Failed: {$failed}",
            'data' => ['processed' => $processed, 'failed' => $failed]
        ];
    }

    /**
     * Execute cleanup task
     *
     * @param string $handler
     * @param array $config
     * @return array
     */
    private static function execute_cleanup_task(string $handler, array $config): array
    {
        $days_old = $config['days_old'] ?? 30;

        return match ($handler) {
            'cleanup_webhook_logs' => self::cleanup_webhook_logs($days_old),
            'cleanup_event_logs' => self::cleanup_event_logs($days_old),
            'cleanup_etl_jobs' => self::cleanup_etl_jobs($days_old),
            default => ['success' => false, 'message' => 'Unknown cleanup handler']
        };
    }

    /**
     * Cleanup webhook logs
     *
     * @param int $days_old
     * @return array
     */
    private static function cleanup_webhook_logs(int $days_old): array
    {
        $result = Webhook_Handler::cleanup($days_old);
        return [
            'success' => $result->ok,
            'message' => $result->message
        ];
    }

    /**
     * Cleanup event logs
     *
     * @param int $days_old
     * @return array
     */
    private static function cleanup_event_logs(int $days_old): array
    {
        $result = Event_Logger::cleanup($days_old);
        return [
            'success' => $result->ok,
            'message' => $result->message
        ];
    }

    /**
     * Cleanup ETL jobs
     *
     * @param int $days_old
     * @return array
     */
    private static function cleanup_etl_jobs(int $days_old): array
    {
        global $wpdb;
        $table = Database::get_table_full_name(ETL_Job_Model::TABLE_NAME);

        if (!$table || !Database::table_exists(ETL_Job_Model::TABLE_NAME)) {
            return ['success' => false, 'message' => 'ETL jobs table not found'];
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s AND status = 'completed'",
            $cutoff
        ));

        return [
            'success' => $deleted !== false,
            'message' => "Deleted {$deleted} old ETL jobs"
        ];
    }

    /**
     * Execute health check task
     *
     * @param array $config
     * @return array
     */
    private static function execute_health_check_task(array $config): array
    {
        if (!Database::table_exists(External_Service_Model::TABLE_NAME)) {
            return ['success' => true, 'message' => 'No services to check'];
        }

        global $wpdb;
        $table = Database::get_table_full_name(External_Service_Model::TABLE_NAME);

        $services = $wpdb->get_results(
            "SELECT id FROM {$table} WHERE is_active = 1",
            ARRAY_A
        );

        $connector = new External_Service_Connector();
        $results = [];

        foreach ($services as $service) {
            $check = $connector->health_check((int) $service['id']);
            $results[$service['id']] = $check['status'];
        }

        $healthy = count(array_filter($results, fn($s) => $s === 'healthy'));
        $total = count($results);

        return [
            'success' => true,
            'message' => "Checked {$total} services, {$healthy} healthy",
            'data' => $results
        ];
    }

    /**
     * Execute webhook retry task
     *
     * @param array $config
     * @return array
     */
    private static function execute_webhook_retry_task(array $config): array
    {
        if (!Database::table_exists(Webhook_Log_Model::TABLE_NAME)) {
            return ['success' => true, 'message' => 'No webhooks to retry'];
        }

        global $wpdb;
        $table = Database::get_table_full_name(Webhook_Log_Model::TABLE_NAME);
        $max_retries = $config['max_retries'] ?? 3;

        $failed_webhooks = $wpdb->get_results($wpdb->prepare("
            SELECT id
            FROM {$table}
            WHERE status = 'failed'
            AND retry_count < %d
            ORDER BY created_at ASC
            LIMIT 10
        ", $max_retries), ARRAY_A);

        $retried = 0;

        foreach ($failed_webhooks as $webhook) {
            $result = Webhook_Handler::retry((int) $webhook['id']);
            if ($result->ok) {
                $retried++;
            }
        }

        return [
            'success' => true,
            'message' => "Retried {$retried} webhooks",
            'data' => ['retried' => $retried]
        ];
    }

    /**
     * Execute custom task
     *
     * @param string $handler
     * @param array $config
     * @return array
     */
    private static function execute_custom_task(string $handler, array $config): array
    {
        // Check if handler is registered with Action_Executor
        if (Action_Executor::exists($handler)) {
            $result = Action_Executor::execute($handler, $config, []);

            if ($result instanceof \WP_REST_Response) {
                $data = $result->get_data();
                return [
                    'success' => $result->get_status() < 400,
                    'message' => $data['message'] ?? '',
                    'data' => $data
                ];
            }

            return [
                'success' => true,
                'message' => 'Task executed',
                'data' => $result
            ];
        }

        // Try WordPress action
        $response = apply_filters("wp_custom_api_task_{$handler}", [
            'success' => false,
            'message' => 'Handler not found'
        ], $config);

        return $response;
    }

    /**
     * Calculate next run time based on frequency
     *
     * @param string $frequency
     * @return int
     */
    private static function calculate_next_run(string $frequency): int
    {
        $now = time();

        return match ($frequency) {
            self::FREQ_ONCE => 0,
            self::FREQ_HOURLY => $now + 3600,
            self::FREQ_TWICE_DAILY => $now + 43200,
            self::FREQ_DAILY => $now + 86400,
            self::FREQ_WEEKLY => $now + 604800,
            self::FREQ_MONTHLY => $now + 2592000,
            'every_minute' => $now + 60,
            'every_5_minutes' => $now + 300,
            'every_15_minutes' => $now + 900,
            'every_30_minutes' => $now + 1800,
            default => $now + 3600
        };
    }

    /**
     * Create a new scheduled task
     *
     * @param array $data
     * @return Response_Handler
     */
    public static function create_task(array $data): Response_Handler
    {
        $required = ['name', 'task_type', 'handler', 'frequency'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return Response_Handler::response(false, 400, "Missing required field: {$field}");
            }
        }

        $task_data = [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'task_type' => $data['task_type'],
            'handler' => $data['handler'],
            'frequency' => $data['frequency'],
            'config' => is_array($data['config'] ?? null) ? json_encode($data['config']) : ($data['config'] ?? '{}'),
            'is_active' => $data['is_active'] ?? 1,
            'is_system' => $data['is_system'] ?? 0,
            'priority' => $data['priority'] ?? 10,
            'status' => self::STATUS_PENDING,
            'next_run_at' => $data['next_run_at'] ?? time(),
            'run_count' => 0,
            'fail_count' => 0
        ];

        $result = Database::insert_row(Scheduled_Task_Model::TABLE_NAME, $task_data);

        if ($result->ok) {
            Event_Logger::log('scheduler', 'Task created', [
                'task_id' => $result->data['id'],
                'name' => $data['name']
            ]);
        }

        return $result;
    }

    /**
     * Update a scheduled task
     *
     * @param int $id
     * @param array $data
     * @return Response_Handler
     */
    public static function update_task(int $id, array $data): Response_Handler
    {
        if (isset($data['config']) && is_array($data['config'])) {
            $data['config'] = json_encode($data['config']);
        }

        return Database::update_row(Scheduled_Task_Model::TABLE_NAME, $id, $data);
    }

    /**
     * Delete a scheduled task
     *
     * @param int $id
     * @return Response_Handler
     */
    public static function delete_task(int $id): Response_Handler
    {
        return Database::delete_row(Scheduled_Task_Model::TABLE_NAME, $id);
    }

    /**
     * Get a scheduled task
     *
     * @param int $id
     * @return Response_Handler
     */
    public static function get_task(int $id): Response_Handler
    {
        return Database::get_rows_data(Scheduled_Task_Model::TABLE_NAME, 'id', $id, false);
    }

    /**
     * Get all scheduled tasks
     *
     * @return Response_Handler
     */
    public static function get_all_tasks(): Response_Handler
    {
        return Database::get_table_data(Scheduled_Task_Model::TABLE_NAME);
    }

    /**
     * Pause a task
     *
     * @param int $id
     * @return Response_Handler
     */
    public static function pause_task(int $id): Response_Handler
    {
        return self::update_task($id, [
            'is_active' => 0,
            'status' => self::STATUS_PAUSED
        ]);
    }

    /**
     * Resume a task
     *
     * @param int $id
     * @return Response_Handler
     */
    public static function resume_task(int $id): Response_Handler
    {
        return self::update_task($id, [
            'is_active' => 1,
            'status' => self::STATUS_PENDING,
            'next_run_at' => time()
        ]);
    }

    /**
     * Run a task immediately
     *
     * @param int $id
     * @return array
     */
    public static function run_now(int $id): array
    {
        $task_result = self::get_task($id);

        if (!$task_result->ok || empty($task_result->data)) {
            return ['success' => false, 'message' => 'Task not found'];
        }

        return self::execute_task($task_result->data);
    }
}
