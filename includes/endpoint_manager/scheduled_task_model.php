<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Includes\Model_Interface;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Model for scheduled tasks.
 * Stores configuration and execution history for scheduled jobs.
 *
 * @since 1.1.0
 */

final class Scheduled_Task_Model extends Model_Interface
{
    /**
     * Table name for scheduled tasks
     */
    public const TABLE_NAME = 'scheduled_tasks';

    public static function table_name(): string
    {
        return self::TABLE_NAME;
    }

    public static function schema(): array
    {
        return [
            'name' => [
                'query'    => 'VARCHAR(100)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 100,
            ],
            'description' => [
                'query'    => 'TEXT',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'task_type' => [
                'query'    => 'VARCHAR(50)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 50,
            ],
            'handler' => [
                'query'    => 'VARCHAR(100)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 100,
            ],
            'frequency' => [
                'query'    => 'VARCHAR(50)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 50,
            ],
            'config' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'is_active' => [
                'query'    => 'TINYINT(1)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 1,
            ],
            'is_system' => [
                'query'    => 'TINYINT(1)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 1,
            ],
            'priority' => [
                'query'    => 'INT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 100,
            ],
            'status' => [
                'query'    => 'VARCHAR(20)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 20,
            ],
            'next_run_at' => [
                'query'    => 'BIGINT(12)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999999,
            ],
            'last_run_at' => [
                'query'    => 'BIGINT(12)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999999,
            ],
            'last_result' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'last_duration' => [
                'query'    => 'INT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999,
            ],
            'run_count' => [
                'query'    => 'INT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999,
            ],
            'fail_count' => [
                'query'    => 'INT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999,
            ],
        ];
    }

    public static function create_table(): bool
    {
        return true;
    }
}
