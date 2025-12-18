<?php

declare(strict_types=1);

namespace WP_Custom_API\Api\Endpoint_Manager;

use WP_Custom_API\Includes\Model_Interface;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Endpoint Manager API Model
 *
 * This model initializes the Endpoint Manager tables by delegating to the
 * individual model classes in the endpoint_manager includes folder.
 *
 * @since 1.1.0
 */

final class Model extends Model_Interface
{
    public static function table_name(): string
    {
        // Return empty - we don't need a single table, the manager creates its own
        return '';
    }

    public static function schema(): array
    {
        return [];
    }

    public static function create_table(): bool
    {
        return false;
    }
}
