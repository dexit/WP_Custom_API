<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Includes\Model_Interface;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Model for webhook logs.
 * Stores received webhook payloads and metadata for later processing.
 *
 * @since 1.1.0
 */

final class Webhook_Log_Model extends Model_Interface
{
    /**
     * Table name for webhook logs
     */
    public const TABLE_NAME = 'webhook_logs';

    public static function table_name(): string
    {
        return self::TABLE_NAME;
    }

    public static function schema(): array
    {
        return [
            'endpoint_id' => [
                'query'    => 'MEDIUMINT(11)',
                'type'     => 'int',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 99999999,
            ],
            'source_ip' => [
                'query'    => 'VARCHAR(45)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 45,
            ],
            'source_identifier' => [
                'query'    => 'VARCHAR(255)',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 255,
            ],
            'request_method' => [
                'query'    => 'VARCHAR(10)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 10,
            ],
            'request_headers' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'request_payload' => [
                'query'    => 'LONGTEXT',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 16777215,
            ],
            'query_params' => [
                'query'    => 'JSON',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'status' => [
                'query'    => 'VARCHAR(20)',
                'type'     => 'text',
                'required' => true,
                'minimum'  => 1,
                'maximum'  => 20,
            ],
            'response_code' => [
                'query'    => 'INT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 100,
                'maximum'  => 599,
            ],
            'response_body' => [
                'query'    => 'LONGTEXT',
                'type'     => 'raw',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 16777215,
            ],
            'processed_at' => [
                'query'    => 'BIGINT(12)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 999999999999,
            ],
            'error_message' => [
                'query'    => 'TEXT',
                'type'     => 'text',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 65535,
            ],
            'signature_valid' => [
                'query'    => 'TINYINT(1)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 1,
            ],
            'retry_count' => [
                'query'    => 'INT(11)',
                'type'     => 'int',
                'required' => false,
                'minimum'  => 0,
                'maximum'  => 100,
            ],
        ];
    }

    public static function create_table(): bool
    {
        return true;
    }
}
