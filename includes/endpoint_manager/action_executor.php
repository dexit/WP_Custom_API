<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Includes\Response_Handler;
use WP_Custom_API\Includes\Error_Generator;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Action Executor - Handles custom PHP execution for endpoints
 *
 * This class provides a secure way to:
 * - Register custom callback handlers
 * - Execute WordPress actions/hooks
 * - Run custom PHP scripts safely
 * - Provide sandboxed execution environment
 *
 * @since 1.1.0
 */

final class Action_Executor
{
    /**
     * Registered action handlers
     */
    private static array $handlers = [];

    /**
     * Registered action groups
     */
    private static array $groups = [];

    /**
     * Execution history for debugging
     */
    private static array $execution_log = [];

    /**
     * Register a custom action handler
     *
     * Usage:
     * Action_Executor::register('my_handler', function($data, $endpoint, $request) {
     *     // Process data
     *     return ['result' => 'success', 'data' => $processed];
     * });
     *
     * @param string $name Handler name
     * @param callable $callback The callback function
     * @param array $options Handler options
     * @return void
     */
    public static function register(string $name, callable $callback, array $options = []): void
    {
        self::$handlers[$name] = [
            'callback' => $callback,
            'options' => array_merge([
                'description' => '',
                'group' => 'default',
                'priority' => 10,
                'accepts_request' => true,
                'returns_response' => false,
            ], $options)
        ];

        // Also register with the Endpoint Manager
        Endpoint_Manager::register_callback($name, $callback);

        // Add to group
        $group = $options['group'] ?? 'default';
        if (!isset(self::$groups[$group])) {
            self::$groups[$group] = [];
        }
        self::$groups[$group][] = $name;

        do_action('wp_custom_api_action_registered', $name, $options);
    }

    /**
     * Register multiple handlers at once
     *
     * @param array $handlers Array of ['name' => callable] or ['name' => ['callback' => callable, 'options' => []]]
     * @return void
     */
    public static function register_many(array $handlers): void
    {
        foreach ($handlers as $name => $handler) {
            if (is_callable($handler)) {
                self::register($name, $handler);
            } elseif (is_array($handler) && isset($handler['callback'])) {
                self::register($name, $handler['callback'], $handler['options'] ?? []);
            }
        }
    }

    /**
     * Unregister a handler
     *
     * @param string $name Handler name
     * @return bool
     */
    public static function unregister(string $name): bool
    {
        if (isset(self::$handlers[$name])) {
            $group = self::$handlers[$name]['options']['group'] ?? 'default';

            unset(self::$handlers[$name]);

            // Remove from group
            if (isset(self::$groups[$group])) {
                self::$groups[$group] = array_filter(
                    self::$groups[$group],
                    fn($h) => $h !== $name
                );
            }

            do_action('wp_custom_api_action_unregistered', $name);

            return true;
        }

        return false;
    }

    /**
     * Check if a handler exists
     *
     * @param string $name Handler name
     * @return bool
     */
    public static function exists(string $name): bool
    {
        return isset(self::$handlers[$name]);
    }

    /**
     * Execute a registered handler
     *
     * @param string $name Handler name
     * @param array $data Input data
     * @param array $endpoint Endpoint configuration
     * @param WP_REST_Request|null $request Original request
     * @return mixed
     */
    public static function execute(
        string $name,
        array $data,
        array $endpoint = [],
        ?WP_REST_Request $request = null
    ): mixed {
        if (!isset(self::$handlers[$name])) {
            Error_Generator::generate('Action Executor Error', "Handler not found: {$name}");
            return new WP_REST_Response(['message' => "Handler not found: {$name}"], 404);
        }

        $handler = self::$handlers[$name];
        $callback = $handler['callback'];
        $options = $handler['options'];

        $start_time = microtime(true);
        $error = null;
        $result = null;

        try {
            // Pre-execution hook
            do_action('wp_custom_api_before_action_execute', $name, $data, $endpoint);

            // Execute the callback
            if ($options['accepts_request'] && $request) {
                $result = call_user_func($callback, $data, $endpoint, $request);
            } else {
                $result = call_user_func($callback, $data, $endpoint);
            }

            // Post-execution hook
            do_action('wp_custom_api_after_action_execute', $name, $result, $data, $endpoint);

        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Error_Generator::generate('Action Execution Error', "{$name}: {$error}");

            $result = new WP_REST_Response([
                'message' => 'Action execution failed',
                'error' => $error
            ], 500);
        }

        // Log execution
        $execution_time = microtime(true) - $start_time;
        self::$execution_log[] = [
            'handler' => $name,
            'time' => $execution_time,
            'success' => $error === null,
            'error' => $error,
            'timestamp' => time()
        ];

        // Convert result to response if needed
        if ($options['returns_response'] && !($result instanceof WP_REST_Response)) {
            $result = new WP_REST_Response(
                is_array($result) ? $result : ['data' => $result],
                200
            );
        }

        return $result;
    }

    /**
     * Execute a WordPress action with data
     *
     * @param string $action_name WordPress action name
     * @param array $data Data to pass to action
     * @param array $endpoint Endpoint configuration
     * @return array
     */
    public static function do_action(string $action_name, array $data, array $endpoint = []): array
    {
        // Execute the WordPress action
        do_action($action_name, $data, $endpoint);

        // Get any response data set by the action via filter
        $response = apply_filters(
            "wp_custom_api_action_response_{$action_name}",
            ['message' => 'Action executed successfully', 'action' => $action_name],
            $data,
            $endpoint
        );

        return $response;
    }

    /**
     * Execute multiple actions in sequence
     *
     * @param array $actions Array of action names or [name => data] pairs
     * @param array $shared_data Data shared across all actions
     * @param array $endpoint Endpoint configuration
     * @return array Results from each action
     */
    public static function execute_chain(
        array $actions,
        array $shared_data = [],
        array $endpoint = []
    ): array {
        $results = [];
        $current_data = $shared_data;

        foreach ($actions as $key => $value) {
            // Determine action name and specific data
            if (is_numeric($key)) {
                $action_name = $value;
                $action_data = $current_data;
            } else {
                $action_name = $key;
                $action_data = is_array($value) ? array_merge($current_data, $value) : $current_data;
            }

            // Execute the action
            if (self::exists($action_name)) {
                $result = self::execute($action_name, $action_data, $endpoint);
            } else {
                $result = self::do_action($action_name, $action_data, $endpoint);
            }

            $results[$action_name] = $result;

            // Pass result data to next action if it's an array
            if (is_array($result)) {
                $current_data = array_merge($current_data, $result);
            }
        }

        return $results;
    }

    /**
     * Execute actions in parallel (simulated, actually sequential but independent)
     *
     * @param array $actions Array of [name => data] pairs
     * @param array $endpoint Endpoint configuration
     * @return array Results from each action
     */
    public static function execute_parallel(array $actions, array $endpoint = []): array
    {
        $results = [];

        foreach ($actions as $name => $data) {
            if (is_numeric($name)) {
                $name = $data;
                $data = [];
            }

            if (self::exists($name)) {
                $results[$name] = self::execute($name, $data, $endpoint);
            } else {
                $results[$name] = self::do_action($name, $data, $endpoint);
            }
        }

        return $results;
    }

    /**
     * Get all registered handlers
     *
     * @param string|null $group Filter by group
     * @return array
     */
    public static function get_handlers(?string $group = null): array
    {
        if ($group === null) {
            return array_map(fn($h) => [
                'name' => array_search($h, self::$handlers),
                'options' => $h['options']
            ], self::$handlers);
        }

        if (!isset(self::$groups[$group])) {
            return [];
        }

        $handlers = [];
        foreach (self::$groups[$group] as $name) {
            if (isset(self::$handlers[$name])) {
                $handlers[$name] = self::$handlers[$name]['options'];
            }
        }

        return $handlers;
    }

    /**
     * Get all handler groups
     *
     * @return array
     */
    public static function get_groups(): array
    {
        return array_keys(self::$groups);
    }

    /**
     * Get execution log
     *
     * @param int $limit Number of entries to return
     * @return array
     */
    public static function get_execution_log(int $limit = 100): array
    {
        return array_slice(self::$execution_log, -$limit);
    }

    /**
     * Clear execution log
     *
     * @return void
     */
    public static function clear_execution_log(): void
    {
        self::$execution_log = [];
    }

    /**
     * Create a handler that transforms data through a pipeline
     *
     * @param array $transformers Array of transformer functions
     * @return callable
     */
    public static function create_pipeline(array $transformers): callable
    {
        return function($data, $endpoint = []) use ($transformers) {
            $result = $data;

            foreach ($transformers as $transformer) {
                if (is_callable($transformer)) {
                    $result = call_user_func($transformer, $result, $endpoint);
                } elseif (is_string($transformer) && self::exists($transformer)) {
                    $result = self::execute($transformer, $result, $endpoint);
                }
            }

            return $result;
        };
    }

    /**
     * Create a conditional handler
     *
     * @param callable $condition Condition function
     * @param callable $if_true Handler if condition is true
     * @param callable|null $if_false Handler if condition is false
     * @return callable
     */
    public static function create_conditional(
        callable $condition,
        callable $if_true,
        ?callable $if_false = null
    ): callable {
        return function($data, $endpoint = [], $request = null) use ($condition, $if_true, $if_false) {
            if (call_user_func($condition, $data, $endpoint, $request)) {
                return call_user_func($if_true, $data, $endpoint, $request);
            } elseif ($if_false !== null) {
                return call_user_func($if_false, $data, $endpoint, $request);
            }

            return $data;
        };
    }

    /**
     * Register built-in utility handlers
     *
     * @return void
     */
    public static function register_builtin_handlers(): void
    {
        // Log handler - logs data and passes through
        self::register('log', function($data, $endpoint) {
            error_log('[WP Custom API] Data: ' . json_encode($data));
            return $data;
        }, ['description' => 'Logs data to error log', 'group' => 'utility']);

        // Email notification handler
        self::register('send_email', function($data, $endpoint) {
            $to = $data['to'] ?? get_option('admin_email');
            $subject = $data['subject'] ?? 'WP Custom API Notification';
            $message = $data['message'] ?? json_encode($data, JSON_PRETTY_PRINT);

            $sent = wp_mail($to, $subject, $message);

            return ['sent' => $sent, 'to' => $to];
        }, ['description' => 'Sends email notification', 'group' => 'notification']);

        // Slack notification handler
        self::register('notify_slack', function($data, $endpoint) {
            $webhook_url = $data['webhook_url'] ?? '';
            $message = $data['message'] ?? json_encode($data);

            if (!$webhook_url) {
                return ['error' => 'No webhook URL provided'];
            }

            $response = wp_remote_post($webhook_url, [
                'body' => json_encode(['text' => $message]),
                'headers' => ['Content-Type' => 'application/json']
            ]);

            return [
                'sent' => !is_wp_error($response),
                'error' => is_wp_error($response) ? $response->get_error_message() : null
            ];
        }, ['description' => 'Sends Slack notification', 'group' => 'notification']);

        // Store to database handler
        self::register('store_data', function($data, $endpoint) {
            $table = $data['_table'] ?? 'webhook_data';
            unset($data['_table']);

            $result = \WP_Custom_API\Includes\Database::insert_row($table, [
                'data' => json_encode($data),
                'endpoint_id' => $endpoint['id'] ?? 0,
                'created_at' => current_time('mysql')
            ]);

            return ['stored' => $result->ok, 'id' => $result->data['id'] ?? null];
        }, ['description' => 'Stores data to database', 'group' => 'storage']);

        // WordPress user creation handler
        self::register('create_wp_user', function($data, $endpoint) {
            $username = $data['username'] ?? $data['email'] ?? '';
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? wp_generate_password();
            $role = $data['role'] ?? 'subscriber';

            if (!$username || !$email) {
                return ['error' => 'Username and email are required'];
            }

            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                return ['error' => $user_id->get_error_message()];
            }

            $user = new \WP_User($user_id);
            $user->set_role($role);

            // Add custom meta if provided
            if (!empty($data['meta'])) {
                foreach ($data['meta'] as $key => $value) {
                    update_user_meta($user_id, $key, $value);
                }
            }

            return [
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email,
                'role' => $role
            ];
        }, ['description' => 'Creates WordPress user', 'group' => 'wordpress']);

        // WordPress post creation handler
        self::register('create_wp_post', function($data, $endpoint) {
            $post_data = [
                'post_title' => $data['title'] ?? 'Untitled',
                'post_content' => $data['content'] ?? '',
                'post_status' => $data['status'] ?? 'draft',
                'post_type' => $data['type'] ?? 'post',
                'post_author' => $data['author'] ?? get_current_user_id(),
            ];

            if (!empty($data['excerpt'])) {
                $post_data['post_excerpt'] = $data['excerpt'];
            }

            $post_id = wp_insert_post($post_data, true);

            if (is_wp_error($post_id)) {
                return ['error' => $post_id->get_error_message()];
            }

            // Add categories/tags
            if (!empty($data['categories'])) {
                wp_set_post_categories($post_id, $data['categories']);
            }

            if (!empty($data['tags'])) {
                wp_set_post_tags($post_id, $data['tags']);
            }

            // Add custom meta
            if (!empty($data['meta'])) {
                foreach ($data['meta'] as $key => $value) {
                    update_post_meta($post_id, $key, $value);
                }
            }

            return [
                'post_id' => $post_id,
                'url' => get_permalink($post_id)
            ];
        }, ['description' => 'Creates WordPress post', 'group' => 'wordpress']);

        // HTTP request handler
        self::register('http_request', function($data, $endpoint) {
            $url = $data['url'] ?? '';
            $method = $data['method'] ?? 'GET';
            $headers = $data['headers'] ?? [];
            $body = $data['body'] ?? [];

            if (!$url) {
                return ['error' => 'URL is required'];
            }

            $args = [
                'method' => strtoupper($method),
                'headers' => $headers,
                'timeout' => $data['timeout'] ?? 30,
            ];

            if (!empty($body) && !in_array(strtoupper($method), ['GET', 'HEAD'])) {
                $args['body'] = is_array($body) ? json_encode($body) : $body;
            }

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                return ['error' => $response->get_error_message()];
            }

            return [
                'code' => wp_remote_retrieve_response_code($response),
                'body' => json_decode(wp_remote_retrieve_body($response), true) ?: wp_remote_retrieve_body($response),
                'headers' => wp_remote_retrieve_headers($response)->getAll()
            ];
        }, ['description' => 'Makes HTTP request', 'group' => 'utility']);

        do_action('wp_custom_api_builtin_handlers_registered');
    }
}
