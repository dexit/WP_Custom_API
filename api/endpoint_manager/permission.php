<?php

declare(strict_types=1);

namespace WP_Custom_API\Api\Endpoint_Manager;

use WP_Custom_API\Includes\Permission_Interface;
use WP_REST_Request;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Endpoint Manager API Permissions
 *
 * Handles authorization for the Endpoint Manager API endpoints.
 * By default, management endpoints require admin capability.
 *
 * @since 1.1.0
 */

final class Permission extends Permission_Interface
{
    /**
     * Public access - for reading public endpoint configurations
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public static function public(WP_REST_Request $request): bool
    {
        return true;
    }

    /**
     * Admin access - for managing endpoints, templates, and services
     * Requires the user to be logged in and have manage_options capability
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public static function admin(WP_REST_Request $request): bool
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }

        // Check for admin capability
        return current_user_can('manage_options');
    }

    /**
     * Editor access - for managing content-related items
     * Requires the user to be logged in and have edit_others_posts capability
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public static function editor(WP_REST_Request $request): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        return current_user_can('edit_others_posts');
    }

    /**
     * API key validation for programmatic access
     *
     * @param WP_REST_Request $request
     * @return bool|array
     */
    public static function api_key(WP_REST_Request $request): bool|array
    {
        $api_key = $request->get_header('X-API-Key');

        if (!$api_key) {
            return false;
        }

        // Validate against stored API keys
        $valid_keys = apply_filters('wp_custom_api_valid_management_keys', []);

        if (in_array($api_key, $valid_keys, true)) {
            return [true, ['api_key_validated' => true]];
        }

        return false;
    }

    /**
     * Combined permission check - allows either admin or API key
     *
     * @param WP_REST_Request $request
     * @return bool|array
     */
    public static function admin_or_api_key(WP_REST_Request $request): bool|array
    {
        // First try admin check
        if (self::admin($request)) {
            return [true, ['auth_method' => 'admin']];
        }

        // Then try API key
        $api_result = self::api_key($request);
        if ($api_result === true || (is_array($api_result) && $api_result[0])) {
            return [true, ['auth_method' => 'api_key']];
        }

        return false;
    }
}
