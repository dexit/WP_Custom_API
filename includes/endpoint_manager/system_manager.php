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
 * System Manager - Core Dynamic System Orchestrator
 *
 * Central management system that coordinates all endpoint manager components:
 * - Component initialization and lifecycle management
 * - System health monitoring and diagnostics
 * - Component registration and dependency management
 * - Global event dispatching
 * - Plugin state management
 *
 * @since 1.1.0
 */

final class System_Manager
{
    /**
     * System status constants
     */
    public const STATUS_INITIALIZING = 'initializing';
    public const STATUS_READY = 'ready';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_ERROR = 'error';
    public const STATUS_MAINTENANCE = 'maintenance';

    /**
     * Component status constants
     */
    public const COMPONENT_ENABLED = 'enabled';
    public const COMPONENT_DISABLED = 'disabled';
    public const COMPONENT_ERROR = 'error';

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * System status
     */
    private string $status = self::STATUS_INITIALIZING;

    /**
     * Registered components
     */
    private array $components = [];

    /**
     * Component instances
     */
    private array $instances = [];

    /**
     * Initialization timestamp
     */
    private int $initialized_at = 0;

    /**
     * System errors
     */
    private array $errors = [];

    /**
     * System warnings
     */
    private array $warnings = [];

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->initialized_at = time();
    }

    /**
     * Initialize the system manager and all components
     *
     * @return void
     */
    public function init(): void
    {
        try {
            // Register core components
            $this->register_core_components();

            // Ensure all database tables exist
            $this->ensure_database_tables();

            // Initialize configuration manager
            $this->init_component('configuration');

            // Initialize event logger
            $this->init_component('event_logger');

            // Log system start
            Event_Logger::log('system', 'System Manager initialized', [
                'components' => array_keys($this->components)
            ]);

            // Initialize scheduler
            $this->init_component('scheduler');

            // Initialize endpoint manager
            $this->init_component('endpoint_manager');

            // Set system ready
            $this->status = self::STATUS_READY;

            // Fire ready action
            do_action('wp_custom_api_system_ready', $this);

        } catch (\Throwable $e) {
            $this->status = self::STATUS_ERROR;
            $this->errors[] = [
                'component' => 'system_manager',
                'message' => $e->getMessage(),
                'timestamp' => time()
            ];

            Error_Generator::generate('System Manager Error', $e->getMessage());
        }
    }

    /**
     * Register core system components
     *
     * @return void
     */
    private function register_core_components(): void
    {
        // Configuration Manager
        $this->register_component('configuration', [
            'class' => Configuration_Manager::class,
            'priority' => 1,
            'required' => true,
            'description' => 'Manages dynamic system configuration'
        ]);

        // Event Logger
        $this->register_component('event_logger', [
            'class' => Event_Logger::class,
            'priority' => 2,
            'required' => true,
            'description' => 'Handles system event logging and audit trail'
        ]);

        // Scheduler
        $this->register_component('scheduler', [
            'class' => Scheduler::class,
            'priority' => 3,
            'required' => false,
            'description' => 'Manages scheduled tasks and cron jobs'
        ]);

        // Endpoint Manager
        $this->register_component('endpoint_manager', [
            'class' => Endpoint_Manager::class,
            'priority' => 10,
            'required' => true,
            'description' => 'Manages dynamic REST API endpoints'
        ]);

        // Allow additional component registration
        do_action('wp_custom_api_register_components', $this);
    }

    /**
     * Register a component
     *
     * @param string $name Component identifier
     * @param array $config Component configuration
     * @return void
     */
    public function register_component(string $name, array $config): void
    {
        $this->components[$name] = array_merge([
            'class' => null,
            'priority' => 10,
            'required' => false,
            'description' => '',
            'status' => self::COMPONENT_DISABLED,
            'init_callback' => null,
            'shutdown_callback' => null
        ], $config);
    }

    /**
     * Initialize a specific component
     *
     * @param string $name Component name
     * @return bool
     */
    public function init_component(string $name): bool
    {
        if (!isset($this->components[$name])) {
            $this->warnings[] = "Component not registered: {$name}";
            return false;
        }

        $config = $this->components[$name];

        try {
            // Check if class exists
            if ($config['class'] && class_exists($config['class'])) {
                // Check for static init method
                if (method_exists($config['class'], 'init')) {
                    call_user_func([$config['class'], 'init']);
                }

                // Check for instance method
                if (method_exists($config['class'], 'instance')) {
                    $this->instances[$name] = call_user_func([$config['class'], 'instance']);
                }
            }

            // Call custom init callback
            if ($config['init_callback'] && is_callable($config['init_callback'])) {
                call_user_func($config['init_callback'], $this);
            }

            $this->components[$name]['status'] = self::COMPONENT_ENABLED;

            do_action("wp_custom_api_component_{$name}_initialized", $this);

            return true;

        } catch (\Throwable $e) {
            $this->components[$name]['status'] = self::COMPONENT_ERROR;
            $this->errors[] = [
                'component' => $name,
                'message' => $e->getMessage(),
                'timestamp' => time()
            ];

            if ($config['required']) {
                $this->status = self::STATUS_DEGRADED;
            }

            Error_Generator::generate("Component Init Error: {$name}", $e->getMessage());

            return false;
        }
    }

    /**
     * Get a component instance
     *
     * @param string $name Component name
     * @return mixed|null
     */
    public function get_component(string $name): mixed
    {
        return $this->instances[$name] ?? null;
    }

    /**
     * Ensure all database tables exist
     *
     * @return void
     */
    private function ensure_database_tables(): void
    {
        $tables = [
            // Core endpoint manager tables
            Custom_Endpoint_Model::TABLE_NAME => Custom_Endpoint_Model::schema(),
            Webhook_Log_Model::TABLE_NAME => Webhook_Log_Model::schema(),
            ETL_Template_Model::TABLE_NAME => ETL_Template_Model::schema(),
            ETL_Job_Model::TABLE_NAME => ETL_Job_Model::schema(),
            External_Service_Model::TABLE_NAME => External_Service_Model::schema(),
            // System manager tables
            System_Settings_Model::TABLE_NAME => System_Settings_Model::schema(),
            Event_Log_Model::TABLE_NAME => Event_Log_Model::schema(),
            Scheduled_Task_Model::TABLE_NAME => Scheduled_Task_Model::schema(),
        ];

        $created = [];
        $errors = [];

        foreach ($tables as $table_name => $schema) {
            if (!Database::table_exists($table_name)) {
                $result = Database::create_table($table_name, $schema);
                if ($result->ok) {
                    $created[] = $table_name;
                } else {
                    $errors[] = $table_name;
                }
            }
        }

        if (!empty($created)) {
            do_action('wp_custom_api_system_tables_created', $created);
        }

        if (!empty($errors)) {
            $this->warnings[] = 'Failed to create tables: ' . implode(', ', $errors);
        }
    }

    /**
     * Get system status
     *
     * @return array
     */
    public function get_status(): array
    {
        $components_status = [];
        foreach ($this->components as $name => $config) {
            $components_status[$name] = [
                'status' => $config['status'],
                'description' => $config['description'],
                'required' => $config['required']
            ];
        }

        return [
            'status' => $this->status,
            'initialized_at' => $this->initialized_at,
            'uptime' => time() - $this->initialized_at,
            'components' => $components_status,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
    }

    /**
     * Get system health check
     *
     * @return array
     */
    public function health_check(): array
    {
        $checks = [];

        // Database connectivity
        $checks['database'] = [
            'status' => $this->check_database_health(),
            'message' => 'Database connection'
        ];

        // Required tables
        $checks['tables'] = [
            'status' => $this->check_tables_health(),
            'message' => 'Required database tables'
        ];

        // Component health
        foreach ($this->components as $name => $config) {
            $checks["component_{$name}"] = [
                'status' => $config['status'] === self::COMPONENT_ENABLED,
                'message' => "Component: {$name}"
            ];
        }

        // WordPress cron
        $checks['cron'] = [
            'status' => defined('DISABLE_WP_CRON') ? !DISABLE_WP_CRON : true,
            'message' => 'WordPress cron enabled'
        ];

        // Memory
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        $checks['memory'] = [
            'status' => $memory_bytes >= 128 * 1024 * 1024,
            'message' => "Memory limit: {$memory_limit}"
        ];

        // Overall health
        $all_passed = array_reduce($checks, fn($carry, $check) => $carry && $check['status'], true);

        return [
            'healthy' => $all_passed,
            'status' => $all_passed ? 'healthy' : ($this->status === self::STATUS_ERROR ? 'critical' : 'degraded'),
            'checks' => $checks,
            'timestamp' => time()
        ];
    }

    /**
     * Check database health
     *
     * @return bool
     */
    private function check_database_health(): bool
    {
        global $wpdb;
        return $wpdb->check_connection();
    }

    /**
     * Check if required tables exist
     *
     * @return bool
     */
    private function check_tables_health(): bool
    {
        $required_tables = [
            Custom_Endpoint_Model::TABLE_NAME,
            Webhook_Log_Model::TABLE_NAME,
            ETL_Template_Model::TABLE_NAME,
        ];

        foreach ($required_tables as $table) {
            if (!Database::table_exists($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert memory string to bytes
     *
     * @param string $value Memory value string
     * @return int
     */
    private function convert_to_bytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $bytes = (int) $value;

        return match ($unit) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => $bytes
        };
    }

    /**
     * Get system statistics
     *
     * @return array
     */
    public function get_statistics(): array
    {
        return [
            'endpoints' => $this->count_table_rows(Custom_Endpoint_Model::TABLE_NAME),
            'active_endpoints' => $this->count_active_endpoints(),
            'webhook_logs' => $this->count_table_rows(Webhook_Log_Model::TABLE_NAME),
            'webhook_logs_today' => $this->count_today_webhooks(),
            'etl_templates' => $this->count_table_rows(ETL_Template_Model::TABLE_NAME),
            'etl_jobs' => $this->count_table_rows(ETL_Job_Model::TABLE_NAME),
            'etl_jobs_today' => $this->count_today_etl_jobs(),
            'external_services' => $this->count_table_rows(External_Service_Model::TABLE_NAME),
            'events_today' => $this->count_today_events(),
            'scheduled_tasks' => $this->count_scheduled_tasks()
        ];
    }

    /**
     * Count rows in a table
     *
     * @param string $table_name
     * @return int
     */
    private function count_table_rows(string $table_name): int
    {
        global $wpdb;
        $full_name = Database::get_table_full_name($table_name);

        if (!$full_name || !Database::table_exists($table_name)) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$full_name}");
    }

    /**
     * Count active endpoints
     *
     * @return int
     */
    private function count_active_endpoints(): int
    {
        global $wpdb;
        $table = Database::get_table_full_name(Custom_Endpoint_Model::TABLE_NAME);

        if (!$table) return 0;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1");
    }

    /**
     * Count today's webhooks
     *
     * @return int
     */
    private function count_today_webhooks(): int
    {
        global $wpdb;
        $table = Database::get_table_full_name(Webhook_Log_Model::TABLE_NAME);

        if (!$table) return 0;

        $today = date('Y-m-d 00:00:00');
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
            $today
        ));
    }

    /**
     * Count today's ETL jobs
     *
     * @return int
     */
    private function count_today_etl_jobs(): int
    {
        global $wpdb;
        $table = Database::get_table_full_name(ETL_Job_Model::TABLE_NAME);

        if (!$table) return 0;

        $today = date('Y-m-d 00:00:00');
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
            $today
        ));
    }

    /**
     * Count today's events
     *
     * @return int
     */
    private function count_today_events(): int
    {
        global $wpdb;
        $table = Database::get_table_full_name(Event_Log_Model::TABLE_NAME);

        if (!$table || !Database::table_exists(Event_Log_Model::TABLE_NAME)) {
            return 0;
        }

        $today = date('Y-m-d 00:00:00');
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
            $today
        ));
    }

    /**
     * Count scheduled tasks
     *
     * @return int
     */
    private function count_scheduled_tasks(): int
    {
        global $wpdb;
        $table = Database::get_table_full_name(Scheduled_Task_Model::TABLE_NAME);

        if (!$table || !Database::table_exists(Scheduled_Task_Model::TABLE_NAME)) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1");
    }

    /**
     * Enable maintenance mode
     *
     * @param string $reason
     * @return void
     */
    public function enable_maintenance_mode(string $reason = ''): void
    {
        $this->status = self::STATUS_MAINTENANCE;

        Configuration_Manager::set('maintenance_mode', true);
        Configuration_Manager::set('maintenance_reason', $reason);
        Configuration_Manager::set('maintenance_started_at', time());

        Event_Logger::log('system', 'Maintenance mode enabled', ['reason' => $reason]);

        do_action('wp_custom_api_maintenance_mode_enabled', $reason);
    }

    /**
     * Disable maintenance mode
     *
     * @return void
     */
    public function disable_maintenance_mode(): void
    {
        $this->status = self::STATUS_READY;

        Configuration_Manager::set('maintenance_mode', false);
        Configuration_Manager::delete('maintenance_reason');
        Configuration_Manager::delete('maintenance_started_at');

        Event_Logger::log('system', 'Maintenance mode disabled');

        do_action('wp_custom_api_maintenance_mode_disabled');
    }

    /**
     * Check if in maintenance mode
     *
     * @return bool
     */
    public function is_maintenance_mode(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE
            || Configuration_Manager::get('maintenance_mode', false);
    }

    /**
     * Shutdown hook
     *
     * @return void
     */
    public function shutdown(): void
    {
        // Call shutdown callbacks for all components
        foreach ($this->components as $name => $config) {
            if ($config['shutdown_callback'] && is_callable($config['shutdown_callback'])) {
                try {
                    call_user_func($config['shutdown_callback'], $this);
                } catch (\Throwable $e) {
                    Error_Generator::generate("Component Shutdown Error: {$name}", $e->getMessage());
                }
            }
        }

        do_action('wp_custom_api_system_shutdown', $this);
    }

    /**
     * Reset the system (for testing)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
