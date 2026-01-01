<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Includes\Model_Interface;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Model for ETL (Extract, Transform, Load) templates.
 * Stores transformation templates for processing webhook data and sending to external services.
 *
 * @since 1.1.0
 */

final class ETL_Template_Model extends Model_Interface
{
    /**
     * Table name for ETL templates
     */
    public const TABLE_NAME = 'etl_templates';

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
            'source_endpoint_id' => [
                'query'    => 'MEDIUMINT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 99999999,
            ],
            'external_service_id' => [
                'query'    => 'MEDIUMINT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 99999999,
            ],
            'extract_config' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'transform_config' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'load_config' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'field_mappings' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'filters' => [
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
            'trigger_type' => [
                'query'    => 'VARCHAR(50)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 50,
            ],
            'schedule_config' => [
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
