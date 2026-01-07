<?php

/**
 * Plugin Name: WP Custom API
 * Description: This custom made plugin is meant for those seeking to utilize the Wordress REST API with their own custom PHP code.  This plugin provides a structure for routing, controllers, and models and a database helper for managing custom API routing.
 * Author: Chris Paschall
 * Version: 1.0.0
 * PHP Version Minimum: 8.1
 */

/**
 * NOTE - AVOID MODIFYING FILES INSIDE THE "INCLUDES" FOLDER. ALSO AVOID RENAMING FILE NAMES WITHIN API FOLDER.
 * 
 * You can create, update and delete files within the "controllers", "permissions", "models", and "routes" folders inside the app folder only.
 * Avoid changing the names of the files, especially the routes.php files, as those are loaded through the api_routes_files_autoloader method that loads filenames specifically to routes.php.
 * The config.php file can also be adjusted as needed.
 */

/** 
 * Prevent direct access from sources other than the Wordpress environment.
 */

if (!defined('ABSPATH')) exit;

/** 
 * Define WP Custom API Plugin Folder Path.  Used for requiring plugin files and auto loader on init class.
 */

define("WP_CUSTOM_API_FOLDER_PATH", 
    preg_replace(
        '#/+#', 
        '/',
        str_replace("\\", "/", plugin_dir_path(__FILE__))
    )
);

/**
 * Load Vendor dependencies (Composer packages)
 */

require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/vendor-loader.php';

use WP_Custom_API\Includes\Vendor_Loader;

Vendor_Loader::init();

/**
 * Load Error Generator to output errors that occur from the plugin
 */

require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/error_generator.php';

use WP_Custom_API\Includes\Error_Generator;

/**
 * Load Init class to initialize plugin.
 */

require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/init.php';

use WP_Custom_API\Includes\Init;

/** 
 * Check that Wordpress is running PHP version 8.1 or higher.
 * If so, plugin is initialized.  Otherwise the plugin doesn't run and an error notice message is shown in the Wordpress dashboard.
 */

if (!version_compare(PHP_VERSION, '8.1.0', '>=')) {
    Error_Generator::generate('WP Custom API plugin is currently not running', 'This plugin requires PHP version 8.1 or higher to be installed.');
} else {
    Init::run();
}

/**
 * Register plugin activation hook to set up Endpoint Manager on activation
 */

register_activation_hook(__FILE__, function() {
    if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
        // Load necessary classes for activation
        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/database.php';
        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/response_handler.php';

        // Load all Endpoint Manager model classes to ensure tables can be created
        $endpoint_manager_models = [
            'custom_endpoint_model.php',
            'webhook_log_model.php',
            'etl_template_model.php',
            'etl_job_model.php',
            'external_service_model.php',
            'system_settings_model.php',
            'event_log_model.php',
            'scheduled_task_model.php'
        ];

        foreach ($endpoint_manager_models as $model_file) {
            $path = WP_CUSTOM_API_FOLDER_PATH . 'includes/endpoint_manager/' . $model_file;
            if (file_exists($path)) {
                require_once $path;
            }
        }

        // Create all Endpoint Manager tables
        Init::create_endpoint_manager_tables();

        // Log activation
        error_log('WP Custom API: Plugin activated, Endpoint Manager tables created');
    }
});

/**
 * Initialize Endpoint Manager on all WordPress loads
 * This ensures the Endpoint Manager is always available, not just during API requests
 */

add_action('plugins_loaded', function() {
    if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
        Init::init_endpoint_manager();
    }
}, 5);

/**
 * Output error messages that occurred when running the plugin.
 */

add_action('admin_notices', [Error_Generator::class, 'display_errors']);
