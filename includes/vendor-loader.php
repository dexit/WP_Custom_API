<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes;

/**
 * Prevent direct access from sources other than the WordPress environment
 */
if (!defined('ABSPATH')) exit;

/**
 * Vendor Loader - Safely loads Composer dependencies
 *
 * This class ensures external dependencies are loaded without conflicts
 * by checking if they're already loaded by other plugins.
 *
 * @since 2.0.0
 */
final class Vendor_Loader
{
    /**
     * Vendor directory path
     */
    private static string $vendor_dir;

    /**
     * Initialize the vendor loader
     *
     * @return void
     */
    public static function init(): void
    {
        self::$vendor_dir = WP_CUSTOM_API_FOLDER_PATH . 'vendor/';

        // Load Composer autoloader first
        self::load_composer_autoloader();

        // Load individual packages with conflict checks
        self::load_action_scheduler();
        self::load_logger();
    }

    /**
     * Load Composer autoloader
     *
     * @return void
     */
    private static function load_composer_autoloader(): void
    {
        $autoloader = self::$vendor_dir . 'autoload.php';

        if (file_exists($autoloader)) {
            require_once $autoloader;
        } else {
            // Composer dependencies not installed
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>WP Custom API Error:</strong> Composer dependencies are not installed. ';
                echo 'Please run <code>composer install</code> in the plugin directory.';
                echo '</p></div>';
            });
        }
    }

    /**
     * Load WooCommerce Action Scheduler
     *
     * @return void
     */
    private static function load_action_scheduler(): void
    {
        // Check if Action Scheduler is already loaded by WooCommerce or another plugin
        if (class_exists('ActionScheduler')) {
            return;
        }

        $action_scheduler = self::$vendor_dir . 'woocommerce/action-scheduler/action-scheduler.php';

        if (file_exists($action_scheduler)) {
            require_once $action_scheduler;

            // Initialize Action Scheduler with our plugin file
            if (class_exists('ActionScheduler_Versions')) {
                \ActionScheduler_Versions::instance()->register(
                    '3.7.0',
                    'wp_custom_api_action_scheduler_initialize'
                );
            }
        }
    }

    /**
     * Load Logger (Monolog)
     *
     * @return void
     */
    private static function load_logger(): void
    {
        // Monolog is loaded automatically by Composer autoloader
        // Just verify it's available
        if (!class_exists('Monolog\Logger')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>WP Custom API Warning:</strong> Monolog logger not found. ';
                echo 'Advanced logging features will be disabled.';
                echo '</p></div>';
            });
        }
    }

    /**
     * Check if a package is available
     *
     * @param string $package Package name or class name
     * @return bool
     */
    public static function is_available(string $package): bool
    {
        return match ($package) {
            'action-scheduler', 'ActionScheduler' => class_exists('ActionScheduler'),
            'monolog', 'Monolog' => class_exists('Monolog\Logger'),
            default => false
        };
    }

    /**
     * Get vendor directory path
     *
     * @return string
     */
    public static function get_vendor_dir(): string
    {
        return self::$vendor_dir;
    }
}

/**
 * Initialize Action Scheduler callback
 * Called by ActionScheduler_Versions after registration
 *
 * @return void
 */
function wp_custom_api_action_scheduler_initialize(): void
{
    require_once Vendor_Loader::get_vendor_dir() . 'woocommerce/action-scheduler/action-scheduler.php';
}
