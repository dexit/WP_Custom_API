<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Includes\Model_Interface;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Model for ETL job execution history.
 * Tracks each execution of an ETL template including status and results.
 *
 * @since 1.1.0
 */

final class ETL_Job_Model extends Model_Interface
{
    /**
     * Table name for ETL jobs
     */
    public const TABLE_NAME = 'etl_jobs';

    public static function table_name(): string
    {
        return self::TABLE_NAME;
    }

    public static function schema(): array
    {
        return [
            'template_id' => [
                'query'    => 'MEDIUMINT(11)',
                'type'     => 'int',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 99999999,
            ],
            'webhook_log_id' => [
                'query'    => 'MEDIUMINT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 99999999,
            ],
            'status' => [
                'query'    => 'VARCHAR(20)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 20,
            ],
            'started_at' => [
                'query'    => 'BIGINT(12)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999999,
            ],
            'completed_at' => [
                'query'    => 'BIGINT(12)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999999,
            ],
            'input_data' => [
                'query'    => 'LONGTEXT',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 16777215,
            ],
            'extracted_data' => [
                'query'    => 'LONGTEXT',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 16777215,
            ],
            'transformed_data' => [
                'query'    => 'LONGTEXT',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 16777215,
            ],
            'load_result' => [
                'query'    => 'LONGTEXT',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 16777215,
            ],
            'error_message' => [
                'query'    => 'TEXT',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'error_stage' => [
                'query'    => 'VARCHAR(20)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 20,
            ],
            'retry_count' => [
                'query'    => 'INT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 100,
            ],
            'external_response_code' => [
                'query'    => 'INT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 100,
                'maximum'  => 599,
            ],
            'external_response_body' => [
                'query'    => 'LONGTEXT',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 16777215,
            ],
        ];
    }

    public static function create_table(): bool
    {
        return true;
    }
}
