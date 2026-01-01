<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Includes\Model_Interface;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Model for external service configurations.
 * Stores API connection details for external services used in ETL processes.
 *
 * @since 1.1.0
 */

final class External_Service_Model extends Model_Interface
{
    /**
     * Table name for external services
     */
    public const TABLE_NAME = 'external_services';

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
            'base_url' => [
                'query'    => 'VARCHAR(500)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 500,
            ],
            'auth_type' => [
                'query'    => 'VARCHAR(50)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 50,
            ],
            'auth_config' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'default_headers' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'timeout' => [
                'query'    => 'INT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 1,
                'maximum'  => 300,
            ],
            'retry_config' => [
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
            'rate_limit_config' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'health_check_config' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'last_health_check' => [
                'query'    => 'BIGINT(12)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999999,
            ],
            'health_status' => [
                'query'    => 'VARCHAR(20)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 20,
            ],
        ];
    }

    public static function create_table(): bool
    {
        return true;
    }
}
