<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Config;
use WP_Custom_API\Includes\Database;
use WP_Custom_API\Includes\Response_Handler;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Configuration Manager - Dynamic Settings Management
 *
 * Manages dynamic system configuration with:
 * - Database-backed settings storage
 * - Caching for performance
 * - Type casting and validation
 * - Default value handling
 * - Settings import/export
 * - Environment-aware configuration
 *
 * @since 1.1.0
 */

final class Configuration_Manager
{
    /**
     * Settings cache
     */
    private static array $cache = [];

    /**
     * Cache loaded flag
     */
    private static bool $cache_loaded = false;

    /**
     * WordPress option key for settings
     */
    private const OPTION_KEY = 'wp_custom_api_settings';

    /**
     * Default settings
     */
    private static array $defaults = [
        // System settings
        'system_enabled' => true,
        'maintenance_mode' => false,
        'debug_mode' => false,
        'log_level' => 'info',

        // Endpoint settings
        'max_endpoints' => 100,
        'default_permission' => 'public',
        'rate_limit_enabled' => false,
        'rate_limit_requests' => 100,
        'rate_limit_window' => 60,

        // Webhook settings
        'webhook_log_retention' => 30,
        'webhook_max_payload_size' => 1048576, // 1MB
        'webhook_signature_required' => false,
        'webhook_auto_retry' => true,
        'webhook_max_retries' => 3,

        // ETL settings
        'etl_job_retention' => 30,
        'etl_max_concurrent_jobs' => 5,
        'etl_timeout' => 300,
        'etl_batch_size' => 100,

        // External service settings
        'external_default_timeout' => 30,
        'external_max_retries' => 3,
        'external_health_check_interval' => 3600,

        // Scheduler settings
        'scheduler_enabled' => true,
        'scheduler_max_tasks_per_run' => 10,

        // Security settings
        'api_key_header' => 'X-API-Key',
        'allowed_origins' => [],
        'ip_whitelist' => [],
        'ip_blacklist' => [],
    ];

    /**
     * Settings schema for validation
     */
    private static array $schema = [
        'system_enabled' => ['type' => 'bool'],
        'maintenance_mode' => ['type' => 'bool'],
        'debug_mode' => ['type' => 'bool'],
        'log_level' => ['type' => 'string', 'enum' => ['debug', 'info', 'warning', 'error', 'critical']],
        'max_endpoints' => ['type' => 'int', 'min' => 1, 'max' => 1000],
        'rate_limit_enabled' => ['type' => 'bool'],
        'rate_limit_requests' => ['type' => 'int', 'min' => 1, 'max' => 10000],
        'rate_limit_window' => ['type' => 'int', 'min' => 1, 'max' => 3600],
        'webhook_log_retention' => ['type' => 'int', 'min' => 1, 'max' => 365],
        'webhook_max_payload_size' => ['type' => 'int', 'min' => 1024, 'max' => 52428800],
        'etl_timeout' => ['type' => 'int', 'min' => 10, 'max' => 3600],
        'etl_batch_size' => ['type' => 'int', 'min' => 1, 'max' => 10000],
        'external_default_timeout' => ['type' => 'int', 'min' => 1, 'max' => 300],
        'allowed_origins' => ['type' => 'array'],
        'ip_whitelist' => ['type' => 'array'],
        'ip_blacklist' => ['type' => 'array'],
    ];

    /**
     * Initialize the configuration manager
     *
     * @return void
     */
    public static function init(): void
    {
        self::load_cache();

        // Allow settings override via constants
        self::apply_constant_overrides();

        // Allow settings override via filter
        self::$cache = apply_filters('wp_custom_api_settings', self::$cache);
    }

    /**
     * Load settings into cache
     *
     * @return void
     */
    private static function load_cache(): void
    {
        if (self::$cache_loaded) {
            return;
        }

        // Try WordPress options first
        $stored = get_option(self::OPTION_KEY, []);

        if (is_string($stored)) {
            $stored = json_decode($stored, true) ?: [];
        }

        // Merge with defaults
        self::$cache = array_merge(self::$defaults, $stored);
        self::$cache_loaded = true;
    }

    /**
     * Apply constant-based overrides
     *
     * @return void
     */
    private static function apply_constant_overrides(): void
    {
        $constant_map = [
            'WP_CUSTOM_API_DEBUG' => 'debug_mode',
            'WP_CUSTOM_API_LOG_LEVEL' => 'log_level',
            'WP_CUSTOM_API_MAINTENANCE' => 'maintenance_mode',
        ];

        foreach ($constant_map as $constant => $setting) {
            if (defined($constant)) {
                self::$cache[$setting] = constant($constant);
            }
        }
    }

    /**
     * Get a setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::load_cache();

        if (!array_key_exists($key, self::$cache)) {
            return $default ?? (self::$defaults[$key] ?? null);
        }

        return self::$cache[$key];
    }

    /**
     * Set a setting value
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param bool $persist Save to database immediately
     * @return bool
     */
    public static function set(string $key, mixed $value, bool $persist = true): bool
    {
        self::load_cache();

        // Validate if schema exists
        if (isset(self::$schema[$key])) {
            $validation = self::validate($key, $value);
            if (!$validation['valid']) {
                return false;
            }
            $value = $validation['value'];
        }

        self::$cache[$key] = $value;

        if ($persist) {
            return self::save();
        }

        return true;
    }

    /**
     * Set multiple settings at once
     *
     * @param array $settings Key-value pairs
     * @param bool $persist Save to database immediately
     * @return bool
     */
    public static function set_many(array $settings, bool $persist = true): bool
    {
        foreach ($settings as $key => $value) {
            self::set($key, $value, false);
        }

        if ($persist) {
            return self::save();
        }

        return true;
    }

    /**
     * Delete a setting
     *
     * @param string $key Setting key
     * @param bool $persist Save to database immediately
     * @return bool
     */
    public static function delete(string $key, bool $persist = true): bool
    {
        self::load_cache();

        if (array_key_exists($key, self::$cache)) {
            unset(self::$cache[$key]);

            if ($persist) {
                return self::save();
            }
        }

        return true;
    }

    /**
     * Check if a setting exists
     *
     * @param string $key Setting key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::load_cache();
        return array_key_exists($key, self::$cache);
    }

    /**
     * Get all settings
     *
     * @param bool $include_defaults Include default values for missing settings
     * @return array
     */
    public static function get_all(bool $include_defaults = true): array
    {
        self::load_cache();

        if ($include_defaults) {
            return array_merge(self::$defaults, self::$cache);
        }

        return self::$cache;
    }

    /**
     * Save settings to database
     *
     * @return bool
     */
    public static function save(): bool
    {
        return update_option(self::OPTION_KEY, self::$cache, false);
    }

    /**
     * Validate a setting value
     *
     * @param string $key Setting key
     * @param mixed $value Value to validate
     * @return array ['valid' => bool, 'value' => mixed, 'error' => string|null]
     */
    public static function validate(string $key, mixed $value): array
    {
        if (!isset(self::$schema[$key])) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }

        $schema = self::$schema[$key];
        $type = $schema['type'] ?? 'string';

        // Type casting and validation
        switch ($type) {
            case 'bool':
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;

            case 'int':
                $value = (int) $value;
                if (isset($schema['min']) && $value < $schema['min']) {
                    return [
                        'valid' => false,
                        'value' => $value,
                        'error' => "Value must be at least {$schema['min']}"
                    ];
                }
                if (isset($schema['max']) && $value > $schema['max']) {
                    return [
                        'valid' => false,
                        'value' => $value,
                        'error' => "Value must be at most {$schema['max']}"
                    ];
                }
                break;

            case 'float':
                $value = (float) $value;
                break;

            case 'string':
                $value = (string) $value;
                if (isset($schema['enum']) && !in_array($value, $schema['enum'])) {
                    return [
                        'valid' => false,
                        'value' => $value,
                        'error' => "Value must be one of: " . implode(', ', $schema['enum'])
                    ];
                }
                if (isset($schema['pattern']) && !preg_match($schema['pattern'], $value)) {
                    return [
                        'valid' => false,
                        'value' => $value,
                        'error' => "Value does not match required pattern"
                    ];
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        $value = is_array($decoded) ? $decoded : [$value];
                    } else {
                        $value = [$value];
                    }
                }
                break;

            case 'json':
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                break;
        }

        return ['valid' => true, 'value' => $value, 'error' => null];
    }

    /**
     * Reset settings to defaults
     *
     * @param array|null $keys Specific keys to reset, or null for all
     * @return bool
     */
    public static function reset(array $keys = null): bool
    {
        if ($keys === null) {
            self::$cache = self::$defaults;
        } else {
            foreach ($keys as $key) {
                if (isset(self::$defaults[$key])) {
                    self::$cache[$key] = self::$defaults[$key];
                } else {
                    unset(self::$cache[$key]);
                }
            }
        }

        return self::save();
    }

    /**
     * Export settings to JSON
     *
     * @return string
     */
    public static function export(): string
    {
        return json_encode(self::get_all(false), JSON_PRETTY_PRINT);
    }

    /**
     * Import settings from JSON
     *
     * @param string $json JSON string
     * @param bool $merge Merge with existing settings
     * @return bool
     */
    public static function import(string $json, bool $merge = true): bool
    {
        $settings = json_decode($json, true);

        if (!is_array($settings)) {
            return false;
        }

        if ($merge) {
            self::$cache = array_merge(self::$cache, $settings);
        } else {
            self::$cache = array_merge(self::$defaults, $settings);
        }

        return self::save();
    }

    /**
     * Get default value for a setting
     *
     * @param string $key Setting key
     * @return mixed
     */
    public static function get_default(string $key): mixed
    {
        return self::$defaults[$key] ?? null;
    }

    /**
     * Get all default settings
     *
     * @return array
     */
    public static function get_defaults(): array
    {
        return self::$defaults;
    }

    /**
     * Get the settings schema
     *
     * @return array
     */
    public static function get_schema(): array
    {
        return self::$schema;
    }

    /**
     * Register a custom setting
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @param array $schema Validation schema
     * @return void
     */
    public static function register(string $key, mixed $default, array $schema = []): void
    {
        self::$defaults[$key] = $default;

        if (!empty($schema)) {
            self::$schema[$key] = $schema;
        }
    }

    /**
     * Clear the settings cache
     *
     * @return void
     */
    public static function clear_cache(): void
    {
        self::$cache = [];
        self::$cache_loaded = false;
    }

    /**
     * Check if system is enabled
     *
     * @return bool
     */
    public static function is_enabled(): bool
    {
        return self::get('system_enabled', true);
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public static function is_debug(): bool
    {
        return self::get('debug_mode', false) || (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * Get settings grouped by category
     *
     * @return array
     */
    public static function get_grouped(): array
    {
        $settings = self::get_all();

        return [
            'system' => [
                'system_enabled' => $settings['system_enabled'],
                'maintenance_mode' => $settings['maintenance_mode'],
                'debug_mode' => $settings['debug_mode'],
                'log_level' => $settings['log_level'],
            ],
            'endpoints' => [
                'max_endpoints' => $settings['max_endpoints'],
                'default_permission' => $settings['default_permission'],
                'rate_limit_enabled' => $settings['rate_limit_enabled'],
                'rate_limit_requests' => $settings['rate_limit_requests'],
                'rate_limit_window' => $settings['rate_limit_window'],
            ],
            'webhooks' => [
                'webhook_log_retention' => $settings['webhook_log_retention'],
                'webhook_max_payload_size' => $settings['webhook_max_payload_size'],
                'webhook_signature_required' => $settings['webhook_signature_required'],
                'webhook_auto_retry' => $settings['webhook_auto_retry'],
                'webhook_max_retries' => $settings['webhook_max_retries'],
            ],
            'etl' => [
                'etl_job_retention' => $settings['etl_job_retention'],
                'etl_max_concurrent_jobs' => $settings['etl_max_concurrent_jobs'],
                'etl_timeout' => $settings['etl_timeout'],
                'etl_batch_size' => $settings['etl_batch_size'],
            ],
            'external_services' => [
                'external_default_timeout' => $settings['external_default_timeout'],
                'external_max_retries' => $settings['external_max_retries'],
                'external_health_check_interval' => $settings['external_health_check_interval'],
            ],
            'scheduler' => [
                'scheduler_enabled' => $settings['scheduler_enabled'],
                'scheduler_max_tasks_per_run' => $settings['scheduler_max_tasks_per_run'],
            ],
            'security' => [
                'api_key_header' => $settings['api_key_header'],
                'allowed_origins' => $settings['allowed_origins'],
                'ip_whitelist' => $settings['ip_whitelist'],
                'ip_blacklist' => $settings['ip_blacklist'],
            ],
        ];
    }
}
