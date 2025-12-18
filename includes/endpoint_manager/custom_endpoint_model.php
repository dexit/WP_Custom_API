<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Includes\Model_Interface;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Model for custom endpoint definitions.
 * Stores dynamically registered endpoint configurations.
 *
 * @since 1.1.0
 */

final class Custom_Endpoint_Model extends Model_Interface
{
    /**
     * Table name for custom endpoints
     */
    public const TABLE_NAME = 'custom_endpoints';

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
            'slug' => [
                'query'    => 'VARCHAR(100)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 100,
            ],
            'route' => [
                'query'    => 'VARCHAR(255)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 255,
            ],
            'method' => [
                'query'    => 'VARCHAR(20)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 2,
                'maximum'  => 20,
            ],
            'handler_type' => [
                'query'    => 'VARCHAR(50)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 50,
            ],
            'handler_config' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'permission_type' => [
                'query'    => 'VARCHAR(50)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 50,
            ],
            'permission_config' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'description' => [
                'query'    => 'TEXT',
                'type'     => 'text',
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
            'request_schema' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'response_schema' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
        ];
    }

    public static function create_table(): bool
    {
        return true;
    }
}
