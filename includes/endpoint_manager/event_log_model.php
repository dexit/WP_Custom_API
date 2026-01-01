<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Includes\Model_Interface;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Model for event logs.
 * Stores system events for audit trail and debugging.
 *
 * @since 1.1.0
 */

final class Event_Log_Model extends Model_Interface
{
    /**
     * Table name for event logs
     */
    public const TABLE_NAME = 'event_logs';

    public static function table_name(): string
    {
        return self::TABLE_NAME;
    }

    public static function schema(): array
    {
        return [
            'level' => [
                'query'    => 'VARCHAR(20)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 20,
            ],
            'category' => [
                'query'    => 'VARCHAR(50)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 50,
            ],
            'message' => [
                'query'    => 'TEXT',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 65535,
            ],
            'context' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'user_id' => [
                'query'    => 'BIGINT(12)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999999,
            ],
            'ip_address' => [
                'query'    => 'VARCHAR(45)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 45,
            ],
            'user_agent' => [
                'query'    => 'VARCHAR(500)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 500,
            ],
            'request_uri' => [
                'query'    => 'VARCHAR(500)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 500,
            ],
            'request_method' => [
                'query'    => 'VARCHAR(10)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 10,
            ],
            'timestamp' => [
                'query'    => 'BIGINT(12)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999999,
            ],
        ];
    }

    public static function create_table(): bool
    {
        return true;
    }
}
