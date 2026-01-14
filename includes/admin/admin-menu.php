<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Admin;

/**
 * Prevent direct access from sources other than the WordPress environment
 */
if (!defined('ABSPATH')) exit;

/**
 * Admin Menu - Main menu registration and structure
 *
 * Registers the WP Custom API admin menu with all submenu pages.
 * Inspired by WordPress best practices and modern plugin architectures.
 *
 * Menu Structure:
 * - Dashboard (overview with statistics)
 * - Endpoints (list/add/edit custom endpoints)
 * - Webhooks (incoming webhook logs)
 * - External Services (outgoing API configurations)
 * - ETL Templates (data transformation pipelines)
 * - Workflows (visual workflow builder)
 * - Jobs Queue (view and manage queued jobs)
 * - Logs (request/response/error/system logs)
 * - Settings (plugin configuration)
 *
 * @since 2.0.0
 */
final class Admin_Menu
{
    /**
     * Menu slug for main page
     */
    public const MENU_SLUG = 'wp-custom-api';

    /**
     * Required capability to access admin pages
     */
    public const REQUIRED_CAPABILITY = 'manage_options';

    /**
     * Initialize the admin menu
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);

        // Load AJAX handlers
        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/class-ajax-handlers.php';
    }

    /**
     * Register admin menu and submenus
     *
     * @return void
     */
    public static function register_menu(): void
    {
        // Main menu page (Dashboard)
        add_menu_page(
            __('WP Custom API', 'wp-custom-api'),           // Page title
            __('Custom API', 'wp-custom-api'),               // Menu title
            self::REQUIRED_CAPABILITY,                       // Capability
            self::MENU_SLUG,                                 // Menu slug
            [self::class, 'render_dashboard'],               // Callback
            'dashicons-rest-api',                            // Icon
            30                                               // Position (after Comments)
        );

        // Dashboard (duplicate to rename first submenu)
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'wp-custom-api'),
            __('Dashboard', 'wp-custom-api'),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG,
            [self::class, 'render_dashboard']
        );

        // Endpoints
        add_submenu_page(
            self::MENU_SLUG,
            __('Endpoints', 'wp-custom-api'),
            __('Endpoints', 'wp-custom-api'),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-endpoints',
            [self::class, 'render_endpoints']
        );

        // Add New Endpoint
        add_submenu_page(
            self::MENU_SLUG,
            __('Add New Endpoint', 'wp-custom-api'),
            __('Add New', 'wp-custom-api'),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-endpoint-new',
            [self::class, 'render_endpoint_new']
        );

        // Webhooks
        add_submenu_page(
            self::MENU_SLUG,
            __('Webhooks', 'wp-custom-api'),
            __('Webhooks', 'wp-custom-api'),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-webhooks',
            [self::class, 'render_webhooks']
        );

        // External Services
        add_submenu_page(
            self::MENU_SLUG,
            __('External Services', 'wp-custom-api'),
            __('External Services', 'wp-custom-api'),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-external-services',
            [self::class, 'render_external_services']
        );

        // ETL Templates
        add_submenu_page(
            self::MENU_SLUG,
            __('ETL Templates', 'wp-custom-api'),
            __('ETL Templates', 'wp-custom-api'),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-etl-templates',
            [self::class, 'render_etl_templates']
        );

        // Workflows (Phase 5)
        add_submenu_page(
            self::MENU_SLUG,
            __('Workflows', 'wp-custom-api'),
            __('Workflows', 'wp-custom-api'),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-workflows',
            [self::class, 'render_workflows']
        );

        // Jobs Queue
        add_submenu_page(
            self::MENU_SLUG,
            __('Jobs Queue', 'wp-custom-api'),
            __('Jobs Queue', 'wp-custom-api'),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-jobs',
            [self::class, 'render_jobs']
        );

        // Logs
        add_submenu_page(
            self::MENU_SLUG,
            __('Logs', 'wp-custom-api'),
            __('Logs', 'wp-custom-api'),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-logs',
            [self::class, 'render_logs']
        );

        // Settings
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'wp-custom-api'),
            __('Settings', 'wp-custom-api'),
            self::REQUIRED_CAPABILITY,
            self::MENU_SLUG . '-settings',
            [self::class, 'render_settings']
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public static function enqueue_assets(string $hook): void
    {
        // Only load on our admin pages
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        $plugin_url = plugins_url('', WP_CUSTOM_API_FOLDER_PATH . 'wp_custom_api.php');
        $version = '2.0.0';

        // Styles
        wp_enqueue_style(
            'wp-custom-api-admin',
            $plugin_url . '/assets/css/admin.css',
            [],
            $version
        );

        // Core WordPress dependencies
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_code_editor(['type' => 'application/json']);
        wp_enqueue_code_editor(['type' => 'application/x-httpd-php']);

        // Main admin script
        wp_enqueue_script(
            'wp-custom-api-admin',
            $plugin_url . '/assets/js/admin.js',
            ['jquery', 'wp-api', 'wp-i18n'],
            $version,
            true
        );

        // Localize script with data
        wp_localize_script('wp-custom-api-admin', 'wpCustomAPI', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('wp-custom-api/v1'),
            'nonce' => wp_create_nonce('wp_custom_api_nonce'),
            'pluginUrl' => $plugin_url,
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this?', 'wp-custom-api'),
                'saved' => __('Saved successfully', 'wp-custom-api'),
                'error' => __('An error occurred', 'wp-custom-api'),
            ]
        ]);

        // Page-specific assets
        self::enqueue_page_specific_assets($hook);
    }

    /**
     * Enqueue page-specific assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    private static function enqueue_page_specific_assets(string $hook): void
    {
        $plugin_url = plugins_url('', WP_CUSTOM_API_FOLDER_PATH . 'wp_custom_api.php');
        $version = '2.0.0';

        // Dashboard - Chart library
        if (strpos($hook, self::MENU_SLUG) !== false && strpos($hook, 'endpoints') === false) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                [],
                '4.4.0',
                true
            );
        }

        // Endpoint Editor - Monaco Editor
        if (strpos($hook, 'endpoint') !== false) {
            // Endpoint Tester - Test modal interface
            wp_enqueue_script(
                'wp-custom-api-endpoint-tester',
                $plugin_url . '/assets/js/endpoint-tester.js',
                ['wp-custom-api-admin'],
                $version,
                true
            );

            // Endpoint Builder - Code editor
            wp_enqueue_script(
                'wp-custom-api-endpoint-builder',
                $plugin_url . '/assets/js/endpoint-builder.js',
                ['wp-custom-api-admin'],
                $version,
                true
            );
        }

        // ETL Templates - Visual builder
        if (strpos($hook, 'etl') !== false) {
            wp_enqueue_script(
                'wp-custom-api-etl-builder',
                $plugin_url . '/assets/js/etl-builder.js',
                ['wp-custom-api-admin'],
                $version,
                true
            );
        }

        // Workflows - React builder (Phase 5)
        if (strpos($hook, 'workflows') !== false) {
            // React workflow builder will be enqueued here in Phase 5
        }

        // Logs - Real-time updates
        if (strpos($hook, 'logs') !== false) {
            wp_enqueue_script(
                'wp-custom-api-log-viewer',
                $plugin_url . '/assets/js/log-viewer.js',
                ['wp-custom-api-admin'],
                $version,
                true
            );
        }
    }

    /**
     * Render Dashboard page
     *
     * @return void
     */
    public static function render_dashboard(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/pages/dashboard.php';
    }

    /**
     * Render Endpoints list page
     *
     * @return void
     */
    public static function render_endpoints(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/pages/endpoints.php';
    }

    /**
     * Render Add New Endpoint page
     *
     * @return void
     */
    public static function render_endpoint_new(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/pages/endpoint-edit.php';
    }

    /**
     * Render Webhooks page
     *
     * @return void
     */
    public static function render_webhooks(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/pages/webhooks.php';
    }

    /**
     * Render External Services page
     *
     * @return void
     */
    public static function render_external_services(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/pages/external-services.php';
    }

    /**
     * Render ETL Templates page
     *
     * @return void
     */
    public static function render_etl_templates(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/pages/etl-templates.php';
    }

    /**
     * Render Workflows page
     *
     * @return void
     */
    public static function render_workflows(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/pages/workflows.php';
    }

    /**
     * Render Jobs Queue page
     *
     * @return void
     */
    public static function render_jobs(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/pages/jobs.php';
    }

    /**
     * Render Logs page
     *
     * @return void
     */
    public static function render_logs(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/pages/logs.php';
    }

    /**
     * Render Settings page
     *
     * @return void
     */
    public static function render_settings(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/pages/settings.php';
    }

    /**
     * Add admin body class for styling
     *
     * @param string $classes Current body classes
     * @return string
     */
    public static function admin_body_class(string $classes): string
    {
        $screen = get_current_screen();

        if ($screen && strpos($screen->id, self::MENU_SLUG) !== false) {
            $classes .= ' wp-custom-api-admin';
        }

        return $classes;
    }
}

// Initialize admin menu
add_action('init', [Admin_Menu::class, 'init']);
add_filter('admin_body_class', [Admin_Menu::class, 'admin_body_class']);
