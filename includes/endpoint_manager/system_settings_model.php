<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Includes\Model_Interface;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Model for system settings.
 * Stores key-value configuration pairs for the endpoint manager system.
 *
 * @since 1.1.0
 */

final class System_Settings_Model extends Model_Interface
{
    /**
     * Table name for system settings
     */
    public const TABLE_NAME = 'system_settings';

    public static function table_name(): string
    {
        return self::TABLE_NAME;
    }

    public static function schema(): array
    {
        return [
            'setting_key' => [
                'query'    => 'VARCHAR(100)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 100,
            ],
            'setting_value' => [
                'query'    => 'LONGTEXT',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 16777215,
            ],
            'setting_type' => [
                'query'    => 'VARCHAR(20)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 20,
            ],
            'is_encrypted' => [
                'query'    => 'TINYINT(1)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 1,
            ],
            'description' => [
                'query'    => 'TEXT',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'category' => [
                'query'    => 'VARCHAR(50)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 50,
            ],
        ];
    }

    public static function create_table(): bool
    {
        return true;
    }
}
