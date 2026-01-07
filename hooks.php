<?php

namespace WP_Custom_API;

use WP_Custom_API\Includes\Endpoint_Manager\System_Manager;
use WP_Custom_API\Includes\Endpoint_Manager\Endpoint_Manager;
use WP_Custom_API\Includes\Endpoint_Manager\Action_Executor;
use WP_Custom_API\Includes\Endpoint_Manager\Configuration_Manager;
use WP_Custom_API\Includes\Endpoint_Manager\Event_Logger;
use WP_Custom_API\Includes\Endpoint_Manager\Scheduler;

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
 * - System management and monitoring
 * - Event logging and audit trail
 * - Scheduled task execution
 * - Configuration management
 *
 * @since 1.0.0
 * @since 1.1.0 Added System Manager integration
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
        // Initialize the System Manager which orchestrates all components
        // This will initialize: Configuration, Event Logger, Scheduler, Endpoint Manager
        System_Manager::instance()->init();

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

    /**
     * Initialize Endpoint Manager only (without requiring full plugin initialization)
     * This is called on plugins_loaded to ensure dynamic endpoints are always available
     *
     * @return void
     */
    public static function init_endpoint_manager_only(): void
    {
        // Register built-in action handlers
        Action_Executor::register_builtin_handlers();

        // Allow users to register custom action handlers
        do_action('wp_custom_api_register_handlers');

        // Initialize the System Manager which orchestrates all Endpoint Manager components
        // This will initialize: Configuration, Event Logger, Scheduler, Endpoint Manager
        System_Manager::instance()->init();

        // Allow users to add custom initialization code
        do_action('wp_custom_api_after_endpoint_manager_init');
    }
}
