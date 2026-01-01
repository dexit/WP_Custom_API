<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Config;
use WP_Custom_API\Includes\Database;
use WP_Custom_API\Includes\Response_Handler;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Event Logger - Unified Audit Trail System
 *
 * Provides comprehensive event logging for:
 * - System events (startup, shutdown, errors)
 * - Endpoint events (creation, updates, deletions)
 * - Webhook events (received, processed, failed)
 * - ETL events (jobs started, completed, failed)
 * - Security events (auth failures, permission denied)
 * - User actions (configuration changes)
 *
 * @since 1.1.0
 */

final class Event_Logger
{
    /**
     * Log level constants
     */
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_CRITICAL = 'critical';

    /**
     * Event category constants
     */
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_ENDPOINT = 'endpoint';
    public const CATEGORY_WEBHOOK = 'webhook';
    public const CATEGORY_ETL = 'etl';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_USER = 'user';
    public const CATEGORY_EXTERNAL = 'external';
    public const CATEGORY_SCHEDULER = 'scheduler';

    /**
     * In-memory log buffer for batch writing
     */
    private static array $buffer = [];

    /**
     * Buffer size before auto-flush
     */
    private static int $buffer_limit = 50;

    /**
     * Minimum log level to record
     */
    private static string $min_level = self::LEVEL_DEBUG;

    /**
     * Log levels priority (higher = more severe)
     */
    private static array $level_priority = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
        self::LEVEL_CRITICAL => 4,
    ];

    /**
     * Initialize the event logger
     *
     * @return void
     */
    public static function init(): void
    {
        // Load minimum log level from config
        self::$min_level = Configuration_Manager::get('log_level', self::LEVEL_INFO);

        // Register shutdown handler to flush buffer
        register_shutdown_function([self::class, 'flush']);

        // Register WordPress hooks for auto-logging
        self::register_auto_log_hooks();
    }

    /**
     * Register WordPress hooks for automatic logging
     *
     * @return void
     */
    private static function register_auto_log_hooks(): void
    {
        // Endpoint events
        add_action('wp_custom_api_endpoint_created', function($id, $data) {
            self::log(self::CATEGORY_ENDPOINT, 'Endpoint created', [
                'endpoint_id' => $id,
                'name' => $data['name'] ?? '',
                'slug' => $data['slug'] ?? ''
            ], self::LEVEL_INFO);
        }, 10, 2);

        add_action('wp_custom_api_endpoint_updated', function($id, $data) {
            self::log(self::CATEGORY_ENDPOINT, 'Endpoint updated', [
                'endpoint_id' => $id
            ], self::LEVEL_INFO);
        }, 10, 2);

        add_action('wp_custom_api_endpoint_deleted', function($id) {
            self::log(self::CATEGORY_ENDPOINT, 'Endpoint deleted', [
                'endpoint_id' => $id
            ], self::LEVEL_INFO);
        });

        // Webhook events
        add_action('wp_custom_api_webhook_received', function($payload, $endpoint, $request, $log_id) {
            self::log(self::CATEGORY_WEBHOOK, 'Webhook received', [
                'webhook_log_id' => $log_id,
                'endpoint_id' => $endpoint['id'] ?? null,
                'endpoint_name' => $endpoint['name'] ?? ''
            ], self::LEVEL_DEBUG);
        }, 10, 4);

        // ETL events
        add_action('wp_custom_api_etl_job_completed', function($job_id, $template_id, $result) {
            self::log(self::CATEGORY_ETL, 'ETL job completed', [
                'job_id' => $job_id,
                'template_id' => $template_id,
                'success' => $result['success'] ?? false
            ], self::LEVEL_INFO);
        }, 10, 3);

        add_action('wp_custom_api_etl_job_failed', function($job_id, $template_id, $error) {
            self::log(self::CATEGORY_ETL, 'ETL job failed', [
                'job_id' => $job_id,
                'template_id' => $template_id,
                'error' => $error
            ], self::LEVEL_ERROR);
        }, 10, 3);

        // External service events
        add_action('wp_custom_api_external_request', function($service_id, $url, $method, $response) {
            self::log(self::CATEGORY_EXTERNAL, 'External API request', [
                'service_id' => $service_id,
                'url' => $url,
                'method' => $method,
                'response_code' => $response['code'] ?? null,
                'success' => $response['success'] ?? false
            ], self::LEVEL_DEBUG);
        }, 10, 4);

        // Scheduler events
        add_action('wp_custom_api_task_executed', function($task_id, $result) {
            self::log(self::CATEGORY_SCHEDULER, 'Scheduled task executed', [
                'task_id' => $task_id,
                'success' => $result['success'] ?? false
            ], self::LEVEL_INFO);
        }, 10, 2);
    }

    /**
     * Log an event
     *
     * @param string $category Event category
     * @param string $message Event message
     * @param array $context Additional context data
     * @param string $level Log level
     * @return bool
     */
    public static function log(
        string $category,
        string $message,
        array $context = [],
        string $level = self::LEVEL_INFO
    ): bool {
        // Check if level should be logged
        if (!self::should_log($level)) {
            return false;
        }

        $event = [
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => json_encode($context),
            'user_id' => get_current_user_id(),
            'ip_address' => Endpoint_Manager::get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'request_uri' => substr($_SERVER['REQUEST_URI'] ?? '', 0, 500),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'timestamp' => time()
        ];

        // Add to buffer
        self::$buffer[] = $event;

        // Flush if buffer is full
        if (count(self::$buffer) >= self::$buffer_limit) {
            self::flush();
        }

        // Fire action for external integrations
        do_action('wp_custom_api_event_logged', $event, $category, $level);

        return true;
    }

    /**
     * Log helper methods for common levels
     */
    public static function debug(string $category, string $message, array $context = []): bool
    {
        return self::log($category, $message, $context, self::LEVEL_DEBUG);
    }

    public static function info(string $category, string $message, array $context = []): bool
    {
        return self::log($category, $message, $context, self::LEVEL_INFO);
    }

    public static function warning(string $category, string $message, array $context = []): bool
    {
        return self::log($category, $message, $context, self::LEVEL_WARNING);
    }

    public static function error(string $category, string $message, array $context = []): bool
    {
        return self::log($category, $message, $context, self::LEVEL_ERROR);
    }

    public static function critical(string $category, string $message, array $context = []): bool
    {
        return self::log($category, $message, $context, self::LEVEL_CRITICAL);
    }

    /**
     * Check if a level should be logged based on minimum level setting
     *
     * @param string $level
     * @return bool
     */
    private static function should_log(string $level): bool
    {
        $level_priority = self::$level_priority[$level] ?? 0;
        $min_priority = self::$level_priority[self::$min_level] ?? 0;

        return $level_priority >= $min_priority;
    }

    /**
     * Flush the log buffer to database
     *
     * @return int Number of events written
     */
    public static function flush(): int
    {
        if (empty(self::$buffer)) {
            return 0;
        }

        // Check if table exists
        if (!Database::table_exists(Event_Log_Model::TABLE_NAME)) {
            // Fallback to WordPress error log
            foreach (self::$buffer as $event) {
                error_log(sprintf(
                    '[WP Custom API] [%s] [%s] %s - %s',
                    strtoupper($event['level']),
                    $event['category'],
                    $event['message'],
                    $event['context']
                ));
            }
            self::$buffer = [];
            return 0;
        }

        $written = 0;

        foreach (self::$buffer as $event) {
            $result = Database::insert_row(Event_Log_Model::TABLE_NAME, $event);
            if ($result->ok) {
                $written++;
            }
        }

        self::$buffer = [];

        return $written;
    }

    /**
     * Get events with filtering
     *
     * @param array $filters Filter options
     * @return Response_Handler
     */
    public static function get_events(array $filters = []): Response_Handler
    {
        global $wpdb;
        $table = Database::get_table_full_name(Event_Log_Model::TABLE_NAME);

        if (!$table || !Database::table_exists(Event_Log_Model::TABLE_NAME)) {
            return Response_Handler::response(false, 500, 'Event log table not found');
        }

        $where = [];
        $params = [];

        // Filter by category
        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $params[] = $filters['category'];
        }

        // Filter by level
        if (!empty($filters['level'])) {
            $where[] = 'level = %s';
            $params[] = $filters['level'];
        }

        // Filter by minimum level
        if (!empty($filters['min_level'])) {
            $min_priority = self::$level_priority[$filters['min_level']] ?? 0;
            $levels = array_filter(
                self::$level_priority,
                fn($p) => $p >= $min_priority
            );
            $placeholders = implode(',', array_fill(0, count($levels), '%s'));
            $where[] = "level IN ({$placeholders})";
            $params = array_merge($params, array_keys($levels));
        }

        // Filter by date range
        if (!empty($filters['from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['to'];
        }

        // Filter by user
        if (isset($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int) $filters['user_id'];
        }

        // Filter by search term
        if (!empty($filters['search'])) {
            $where[] = '(message LIKE %s OR context LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        // Build query
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Pagination
        $page = max(1, (int) ($filters['page'] ?? 1));
        $per_page = min(100, max(1, (int) ($filters['per_page'] ?? 50)));
        $offset = ($page - 1) * $per_page;

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$table} {$where_clause}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, $params);
        }
        $total = (int) $wpdb->get_var($count_query);

        // Get events
        $query = "SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_params = array_merge($params, [$per_page, $offset]);
        $events = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);

        // Set pagination headers
        $total_pages = (int) ceil($total / $per_page);
        Database::pagination_headers($total, $total_pages, $per_page, $page);

        return Response_Handler::response(true, 200, 'Events retrieved', $events);
    }

    /**
     * Get event statistics
     *
     * @param string $period Period for stats (today, week, month, all)
     * @return array
     */
    public static function get_statistics(string $period = 'today'): array
    {
        global $wpdb;
        $table = Database::get_table_full_name(Event_Log_Model::TABLE_NAME);

        if (!$table || !Database::table_exists(Event_Log_Model::TABLE_NAME)) {
            return [];
        }

        $date_filter = match ($period) {
            'today' => date('Y-m-d 00:00:00'),
            'week' => date('Y-m-d 00:00:00', strtotime('-7 days')),
            'month' => date('Y-m-d 00:00:00', strtotime('-30 days')),
            default => '1970-01-01 00:00:00'
        };

        // Events by level
        $by_level = $wpdb->get_results($wpdb->prepare("
            SELECT level, COUNT(*) as count
            FROM {$table}
            WHERE created_at >= %s
            GROUP BY level
        ", $date_filter), ARRAY_A);

        // Events by category
        $by_category = $wpdb->get_results($wpdb->prepare("
            SELECT category, COUNT(*) as count
            FROM {$table}
            WHERE created_at >= %s
            GROUP BY category
        ", $date_filter), ARRAY_A);

        // Recent errors
        $recent_errors = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$table}
            WHERE level IN ('error', 'critical')
            AND created_at >= %s
            ORDER BY created_at DESC
            LIMIT 10
        ", $date_filter), ARRAY_A);

        // Total count
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
            $date_filter
        ));

        return [
            'period' => $period,
            'total' => $total,
            'by_level' => array_column($by_level, 'count', 'level'),
            'by_category' => array_column($by_category, 'count', 'category'),
            'recent_errors' => $recent_errors
        ];
    }

    /**
     * Cleanup old events
     *
     * @param int $days_old Delete events older than this many days
     * @return Response_Handler
     */
    public static function cleanup(int $days_old = 90): Response_Handler
    {
        global $wpdb;
        $table = Database::get_table_full_name(Event_Log_Model::TABLE_NAME);

        if (!$table || !Database::table_exists(Event_Log_Model::TABLE_NAME)) {
            return Response_Handler::response(false, 500, 'Event log table not found');
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));

        // Keep critical errors longer
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s AND level != 'critical'",
            $cutoff
        ));

        if ($deleted === false) {
            return Response_Handler::response(false, 500, 'Failed to cleanup event logs');
        }

        self::log(self::CATEGORY_SYSTEM, 'Event log cleanup completed', [
            'deleted_count' => $deleted,
            'days_old' => $days_old
        ]);

        return Response_Handler::response(true, 200, "Deleted {$deleted} old event logs");
    }

    /**
     * Export events to file
     *
     * @param array $filters Filter options
     * @param string $format Export format (json, csv)
     * @return array File info
     */
    public static function export(array $filters = [], string $format = 'json'): array
    {
        $events_result = self::get_events(array_merge($filters, ['per_page' => 10000]));

        if (!$events_result->ok) {
            return ['error' => 'Failed to retrieve events'];
        }

        $events = $events_result->data;

        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/wp-custom-api-exports/';

        if (!is_dir($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        $filename = 'event-log-' . date('Y-m-d-His') . '.' . $format;
        $filepath = $export_dir . $filename;

        if ($format === 'csv') {
            $fp = fopen($filepath, 'w');
            if (!empty($events)) {
                fputcsv($fp, array_keys($events[0]));
                foreach ($events as $event) {
                    fputcsv($fp, $event);
                }
            }
            fclose($fp);
        } else {
            file_put_contents($filepath, json_encode($events, JSON_PRETTY_PRINT));
        }

        return [
            'filename' => $filename,
            'path' => $filepath,
            'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $filepath),
            'count' => count($events),
            'size' => filesize($filepath)
        ];
    }

    /**
     * Set minimum log level
     *
     * @param string $level
     * @return void
     */
    public static function set_min_level(string $level): void
    {
        if (isset(self::$level_priority[$level])) {
            self::$min_level = $level;
            Configuration_Manager::set('log_level', $level);
        }
    }

    /**
     * Get current minimum log level
     *
     * @return string
     */
    public static function get_min_level(): string
    {
        return self::$min_level;
    }
}
