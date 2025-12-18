<?php

namespace WP_Custom_API;

use WP_Custom_API\Includes\Endpoint_Manager\Endpoint_Manager;
use WP_Custom_API\Includes\Endpoint_Manager\Action_Executor;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Used for adding additional functionality to this plugin.
 * Two hooks are provided: before_init and after_init for adding functionality before and after the plugin initializes.
 *
 * This file now also initializes the Endpoint Manager system which provides:
 * - Dynamic custom endpoint registration
 * - Webhook receiving and logging
 * - ETL (Extract, Transform, Load) processing
 * - External service connections
 * - Custom action/hook execution
 */

final class Hooks
{

    /**
     * Code that run before the plugin initializes
     *
     * This is where you can add any additional code you want to run before the plugin initializes
     */

    public static function before_init(): void
    {
        // Register built-in action handlers
        Action_Executor::register_builtin_handlers();

        // Allow users to register custom action handlers before initialization
        do_action('wp_custom_api_register_handlers');
    }

    /**
     * Code that runs after the plugin initializes
     *
     * This is where you can add any additional code you want to run after the plugin initializes
     */

    public static function after_init(): void
    {
        // Initialize the Endpoint Manager to register dynamic endpoints
        Endpoint_Manager::init();

        // Allow users to add custom initialization code
        do_action('wp_custom_api_after_endpoint_manager_init');
    }

    /**
     * Register a custom endpoint handler
     *
     * Helper method to easily register custom action handlers from themes or plugins.
     *
     * @param string $name Handler name
     * @param callable $callback Handler callback
     * @param array $options Handler options
     * @return void
     */
    public static function register_handler(string $name, callable $callback, array $options = []): void
    {
        Action_Executor::register($name, $callback, $options);
    }

    /**
     * Register a custom endpoint callback for script-type handlers
     *
     * @param string $name Callback name
     * @param callable $callback Callback function
     * @return void
     */
    public static function register_endpoint_callback(string $name, callable $callback): void
    {
        Endpoint_Manager::register_callback($name, $callback);
    }
}
