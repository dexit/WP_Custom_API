<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Endpoint_Manager;

use WP_Custom_API\Config;
use WP_Custom_API\Includes\Database;
use WP_Custom_API\Includes\Response_Handler;
use WP_Custom_API\Includes\Error_Generator;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * ETL Engine - Extract, Transform, Load processing
 *
 * This class handles:
 * - Extracting data from webhook payloads using configured paths
 * - Transforming data using field mappings and custom transformations
 * - Loading transformed data to external services
 *
 * @since 1.1.0
 */

final class ETL_Engine
{
    /**
     * Job status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXTRACTING = 'extracting';
    public const STATUS_TRANSFORMING = 'transforming';
    public const STATUS_LOADING = 'loading';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Registered custom transformers
     */
    private static array $transformers = [];

    /**
     * Register a custom transformer function
     *
     * @param string $name Transformer name
     * @param callable $transformer The transformer function
     * @return void
     */
    public static function register_transformer(string $name, callable $transformer): void
    {
        self::$transformers[$name] = $transformer;
    }

    /**
     * Process an ETL request from an endpoint
     *
     * @param WP_REST_Request $request
     * @param int $template_id
     * @param array $endpoint
     * @return WP_REST_Response
     */
    public function process(WP_REST_Request $request, int $template_id, array $endpoint): WP_REST_Response
    {
        // Get template
        $template_result = Database::get_rows_data(
            ETL_Template_Model::TABLE_NAME,
            'id',
            $template_id,
            false
        );

        if (!$template_result->ok || empty($template_result->data)) {
            return new WP_REST_Response(['message' => 'ETL template not found'], 404);
        }

        $template = $template_result->data;

        // Get input data
        $input_data = Endpoint_Manager::get_request_data($request);

        // Create job record
        $job_data = [
            'template_id' => $template_id,
            'status' => self::STATUS_PENDING,
            'started_at' => time(),
            'input_data' => json_encode($input_data),
            'retry_count' => 0
        ];

        $job_result = Database::insert_row(ETL_Job_Model::TABLE_NAME, $job_data);

        if (!$job_result->ok) {
            return new WP_REST_Response(['message' => 'Failed to create ETL job'], 500);
        }

        $job_id = $job_result->data['id'];

        // Run the ETL process
        $result = $this->run_job($job_id, $template_id, $input_data);

        return $result instanceof WP_REST_Response ? $result : new WP_REST_Response($result, 200);
    }

    /**
     * Run an ETL job
     *
     * @param int $job_id
     * @param int $template_id
     * @param array $input_data
     * @return WP_REST_Response|array
     */
    public function run_job(int $job_id, int $template_id, array $input_data): WP_REST_Response|array
    {
        // Get template
        $template_result = Database::get_rows_data(
            ETL_Template_Model::TABLE_NAME,
            'id',
            $template_id,
            false
        );

        if (!$template_result->ok || empty($template_result->data)) {
            $this->update_job_status($job_id, self::STATUS_FAILED, null, 'Template not found');
            return new WP_REST_Response(['message' => 'ETL template not found'], 404);
        }

        $template = $template_result->data;

        try {
            // EXTRACT
            $this->update_job_status($job_id, self::STATUS_EXTRACTING);
            $extracted_data = $this->extract($input_data, $template);

            Database::update_row(ETL_Job_Model::TABLE_NAME, $job_id, [
                'extracted_data' => json_encode($extracted_data)
            ]);

            // TRANSFORM
            $this->update_job_status($job_id, self::STATUS_TRANSFORMING);
            $transformed_data = $this->transform($extracted_data, $template);

            Database::update_row(ETL_Job_Model::TABLE_NAME, $job_id, [
                'transformed_data' => json_encode($transformed_data)
            ]);

            // LOAD
            $this->update_job_status($job_id, self::STATUS_LOADING);
            $load_result = $this->load($transformed_data, $template);

            // Update job with final status
            Database::update_row(ETL_Job_Model::TABLE_NAME, $job_id, [
                'status' => self::STATUS_COMPLETED,
                'completed_at' => time(),
                'load_result' => json_encode($load_result),
                'external_response_code' => $load_result['response_code'] ?? null,
                'external_response_body' => $load_result['response_body'] ?? null
            ]);

            do_action('wp_custom_api_etl_job_completed', $job_id, $template_id, $load_result);

            return [
                'message' => 'ETL job completed successfully',
                'job_id' => $job_id,
                'result' => $load_result
            ];

        } catch (\Exception $e) {
            $error_stage = match (true) {
                str_contains($e->getMessage(), 'extract') => 'extract',
                str_contains($e->getMessage(), 'transform') => 'transform',
                str_contains($e->getMessage(), 'load') => 'load',
                default => 'unknown'
            };

            $this->update_job_status($job_id, self::STATUS_FAILED, $error_stage, $e->getMessage());

            Error_Generator::generate('ETL Job Error', $e->getMessage());

            do_action('wp_custom_api_etl_job_failed', $job_id, $template_id, $e->getMessage());

            return new WP_REST_Response([
                'message' => Config::DEBUG_MESSAGE_MODE ? $e->getMessage() : 'ETL job failed',
                'job_id' => $job_id
            ], 500);
        }
    }

    /**
     * Extract data from input using configured paths
     *
     * @param array $input_data
     * @param array $template
     * @return array
     */
    private function extract(array $input_data, array $template): array
    {
        $extract_config = $this->decode_json($template['extract_config'] ?? '{}');

        // If no extract config, return all input data
        if (empty($extract_config)) {
            return $input_data;
        }

        $extracted = [];

        // Extract specific paths
        if (!empty($extract_config['paths'])) {
            foreach ($extract_config['paths'] as $key => $path) {
                $value = $this->get_nested_value($input_data, $path);
                if ($value !== null) {
                    $extracted[$key] = $value;
                }
            }
        }

        // Extract root element
        if (!empty($extract_config['root'])) {
            $root_data = $this->get_nested_value($input_data, $extract_config['root']);
            if (is_array($root_data)) {
                $extracted = array_merge($extracted, $root_data);
            }
        }

        // Apply filters
        if (!empty($extract_config['filters'])) {
            $extracted = $this->apply_filters($extracted, $extract_config['filters']);
        }

        // Allow custom extraction via hook
        $extracted = apply_filters(
            'wp_custom_api_etl_extract',
            $extracted,
            $input_data,
            $template
        );

        return $extracted;
    }

    /**
     * Transform extracted data using field mappings
     *
     * @param array $data
     * @param array $template
     * @return array
     */
    private function transform(array $data, array $template): array
    {
        $transform_config = $this->decode_json($template['transform_config'] ?? '{}');
        $field_mappings = $this->decode_json($template['field_mappings'] ?? '{}');

        $transformed = [];

        // Apply field mappings
        foreach ($field_mappings as $target_field => $mapping) {
            if (is_string($mapping)) {
                // Simple mapping: target_field => source_field
                $transformed[$target_field] = $this->get_nested_value($data, $mapping);
            } elseif (is_array($mapping)) {
                // Complex mapping with transformations
                $value = $this->get_nested_value($data, $mapping['source'] ?? $target_field);
                $value = $this->apply_transformations($value, $mapping);
                $transformed[$target_field] = $value;
            }
        }

        // If no mappings, use input data
        if (empty($field_mappings)) {
            $transformed = $data;
        }

        // Apply global transformations
        if (!empty($transform_config['transformations'])) {
            foreach ($transform_config['transformations'] as $transformation) {
                $transformed = $this->apply_global_transformation($transformed, $transformation);
            }
        }

        // Add static fields
        if (!empty($transform_config['static_fields'])) {
            $transformed = array_merge($transformed, $transform_config['static_fields']);
        }

        // Add computed fields
        if (!empty($transform_config['computed_fields'])) {
            foreach ($transform_config['computed_fields'] as $field => $expression) {
                $transformed[$field] = $this->compute_field($expression, $transformed);
            }
        }

        // Allow custom transformation via hook
        $transformed = apply_filters(
            'wp_custom_api_etl_transform',
            $transformed,
            $data,
            $template
        );

        return $transformed;
    }

    /**
     * Load transformed data to destination
     *
     * @param array $data
     * @param array $template
     * @return array
     */
    private function load(array $data, array $template): array
    {
        $load_config = $this->decode_json($template['load_config'] ?? '{}');
        $external_service_id = $template['external_service_id'] ?? null;

        // Determine load destination
        $destination = $load_config['destination'] ?? 'external_service';

        return match ($destination) {
            'external_service' => $this->load_to_external_service($data, $template, $load_config),
            'database' => $this->load_to_database($data, $template, $load_config),
            'action' => $this->load_to_action($data, $template, $load_config),
            'file' => $this->load_to_file($data, $template, $load_config),
            default => throw new \Exception('Unknown load destination: ' . $destination)
        };
    }

    /**
     * Load data to external service via HTTP
     *
     * @param array $data
     * @param array $template
     * @param array $config
     * @return array
     */
    private function load_to_external_service(array $data, array $template, array $config): array
    {
        $external_service_id = $template['external_service_id'] ?? null;

        if (!$external_service_id) {
            throw new \Exception('No external service configured for load');
        }

        $connector = new External_Service_Connector();
        $endpoint_path = $config['endpoint_path'] ?? '';
        $method = $config['method'] ?? 'POST';

        $response = $connector->send(
            (int) $external_service_id,
            $endpoint_path,
            $data,
            $method,
            $config
        );

        return [
            'destination' => 'external_service',
            'service_id' => $external_service_id,
            'success' => $response['success'] ?? false,
            'response_code' => $response['code'] ?? null,
            'response_body' => $response['body'] ?? null
        ];
    }

    /**
     * Load data to database table
     *
     * @param array $data
     * @param array $template
     * @param array $config
     * @return array
     */
    private function load_to_database(array $data, array $template, array $config): array
    {
        $table_name = $config['table_name'] ?? null;
        $operation = $config['operation'] ?? 'insert';

        if (!$table_name) {
            throw new \Exception('No table name configured for database load');
        }

        $result = match ($operation) {
            'insert' => Database::insert_row($table_name, $data),
            'update' => Database::update_row($table_name, (int)($data['id'] ?? 0), $data),
            default => throw new \Exception('Unknown database operation: ' . $operation)
        };

        return [
            'destination' => 'database',
            'table' => $table_name,
            'operation' => $operation,
            'success' => $result->ok,
            'message' => $result->message,
            'data' => $result->data ?? null
        ];
    }

    /**
     * Load data via WordPress action
     *
     * @param array $data
     * @param array $template
     * @param array $config
     * @return array
     */
    private function load_to_action(array $data, array $template, array $config): array
    {
        $action_name = $config['action_name'] ?? 'wp_custom_api_etl_load';

        do_action($action_name, $data, $template, $config);

        // Get result from filter
        $result = apply_filters(
            "wp_custom_api_etl_load_result_{$action_name}",
            ['success' => true, 'message' => 'Action executed'],
            $data,
            $template
        );

        return [
            'destination' => 'action',
            'action' => $action_name,
            'success' => $result['success'] ?? true,
            'result' => $result
        ];
    }

    /**
     * Load data to file
     *
     * @param array $data
     * @param array $template
     * @param array $config
     * @return array
     */
    private function load_to_file(array $data, array $template, array $config): array
    {
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/wp-custom-api-etl/';

        // Ensure directory exists
        if (!is_dir($base_path)) {
            wp_mkdir_p($base_path);
        }

        $filename = $config['filename'] ?? 'etl-output-' . time() . '.json';
        $file_path = $base_path . $filename;

        // Determine format
        $format = $config['format'] ?? 'json';

        $content = match ($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'csv' => $this->array_to_csv($data),
            default => json_encode($data)
        };

        $written = file_put_contents($file_path, $content);

        return [
            'destination' => 'file',
            'path' => $file_path,
            'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path),
            'success' => $written !== false,
            'bytes_written' => $written
        ];
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array $data
     * @param string $path Dot notation path (e.g., "user.profile.name")
     * @return mixed
     */
    private function get_nested_value(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            // Handle array index notation [0]
            if (preg_match('/^(.+)\[(\d+)\]$/', $key, $matches)) {
                $key = $matches[1];
                $index = (int) $matches[2];

                if (!isset($value[$key][$index])) {
                    return null;
                }
                $value = $value[$key][$index];
            } else {
                if (!isset($value[$key])) {
                    return null;
                }
                $value = $value[$key];
            }
        }

        return $value;
    }

    /**
     * Apply transformations to a value
     *
     * @param mixed $value
     * @param array $mapping
     * @return mixed
     */
    private function apply_transformations(mixed $value, array $mapping): mixed
    {
        $transformations = $mapping['transformations'] ?? [];

        foreach ($transformations as $transformation) {
            $value = $this->apply_single_transformation($value, $transformation);
        }

        // Apply default if value is null/empty
        if (($value === null || $value === '') && isset($mapping['default'])) {
            $value = $mapping['default'];
        }

        return $value;
    }

    /**
     * Apply a single transformation
     *
     * @param mixed $value
     * @param array|string $transformation
     * @return mixed
     */
    private function apply_single_transformation(mixed $value, array|string $transformation): mixed
    {
        if (is_string($transformation)) {
            $transformation = ['type' => $transformation];
        }

        $type = $transformation['type'] ?? '';

        // Check for custom transformer
        if (isset(self::$transformers[$type])) {
            return call_user_func(self::$transformers[$type], $value, $transformation);
        }

        // Built-in transformations
        return match ($type) {
            'uppercase' => is_string($value) ? strtoupper($value) : $value,
            'lowercase' => is_string($value) ? strtolower($value) : $value,
            'trim' => is_string($value) ? trim($value) : $value,
            'int', 'integer' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'json_encode' => json_encode($value),
            'json_decode' => is_string($value) ? json_decode($value, true) : $value,
            'date' => $this->transform_date($value, $transformation),
            'replace' => str_replace(
                $transformation['search'] ?? '',
                $transformation['replace'] ?? '',
                (string) $value
            ),
            'regex_replace' => preg_replace(
                $transformation['pattern'] ?? '//',
                $transformation['replacement'] ?? '',
                (string) $value
            ),
            'substr' => substr(
                (string) $value,
                $transformation['start'] ?? 0,
                $transformation['length'] ?? null
            ),
            'concat' => ($transformation['prefix'] ?? '') . $value . ($transformation['suffix'] ?? ''),
            'split' => explode($transformation['delimiter'] ?? ',', (string) $value),
            'join' => is_array($value) ? implode($transformation['delimiter'] ?? ',', $value) : $value,
            'map' => $transformation['values'][$value] ?? ($transformation['default'] ?? $value),
            'multiply' => is_numeric($value) ? $value * ($transformation['factor'] ?? 1) : $value,
            'divide' => is_numeric($value) && ($transformation['divisor'] ?? 1) != 0
                ? $value / $transformation['divisor']
                : $value,
            'round' => is_numeric($value) ? round($value, $transformation['precision'] ?? 0) : $value,
            'hash' => hash($transformation['algorithm'] ?? 'sha256', (string) $value),
            'base64_encode' => base64_encode((string) $value),
            'base64_decode' => base64_decode((string) $value),
            'url_encode' => urlencode((string) $value),
            'url_decode' => urldecode((string) $value),
            'html_encode' => htmlspecialchars((string) $value),
            'html_decode' => htmlspecialchars_decode((string) $value),
            'strip_tags' => strip_tags((string) $value),
            default => $value
        };
    }

    /**
     * Transform date value
     *
     * @param mixed $value
     * @param array $transformation
     * @return string|int
     */
    private function transform_date(mixed $value, array $transformation): string|int
    {
        $input_format = $transformation['input_format'] ?? null;
        $output_format = $transformation['output_format'] ?? 'Y-m-d H:i:s';

        if ($input_format) {
            $date = \DateTime::createFromFormat($input_format, (string) $value);
        } else {
            $date = new \DateTime((string) $value);
        }

        if (!$date) {
            return $value;
        }

        if ($output_format === 'timestamp') {
            return $date->getTimestamp();
        }

        return $date->format($output_format);
    }

    /**
     * Apply global transformation to entire data array
     *
     * @param array $data
     * @param array $transformation
     * @return array
     */
    private function apply_global_transformation(array $data, array $transformation): array
    {
        $type = $transformation['type'] ?? '';

        return match ($type) {
            'flatten' => $this->flatten_array($data, $transformation['separator'] ?? '_'),
            'unflatten' => $this->unflatten_array($data, $transformation['separator'] ?? '_'),
            'filter_empty' => array_filter($data, fn($v) => !empty($v)),
            'filter_null' => array_filter($data, fn($v) => $v !== null),
            'sort' => $this->sort_array($data, $transformation),
            'rename_keys' => $this->rename_keys($data, $transformation['mappings'] ?? []),
            'remove_keys' => array_diff_key($data, array_flip($transformation['keys'] ?? [])),
            'keep_keys' => array_intersect_key($data, array_flip($transformation['keys'] ?? [])),
            default => $data
        };
    }

    /**
     * Compute a field value from expression
     *
     * @param string|array $expression
     * @param array $data
     * @return mixed
     */
    private function compute_field(string|array $expression, array $data): mixed
    {
        if (is_array($expression)) {
            $type = $expression['type'] ?? 'concat';

            return match ($type) {
                'concat' => implode(
                    $expression['separator'] ?? '',
                    array_map(fn($f) => $data[$f] ?? '', $expression['fields'] ?? [])
                ),
                'sum' => array_sum(
                    array_map(fn($f) => (float)($data[$f] ?? 0), $expression['fields'] ?? [])
                ),
                'avg' => ($fields = $expression['fields'] ?? []) && count($fields) > 0
                    ? array_sum(array_map(fn($f) => (float)($data[$f] ?? 0), $fields)) / count($fields)
                    : 0,
                'count' => count($expression['fields'] ?? []),
                'now' => date($expression['format'] ?? 'Y-m-d H:i:s'),
                'uuid' => wp_generate_uuid4(),
                default => null
            };
        }

        // Simple string expression - replace {{field}} with values
        return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($data) {
            return $data[$matches[1]] ?? '';
        }, $expression);
    }

    /**
     * Apply filters to data
     *
     * @param array $data
     * @param array $filters
     * @return array
     */
    private function apply_filters(array $data, array $filters): array
    {
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? '';
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;

            $field_value = $data[$field] ?? null;

            $matches = match ($operator) {
                'equals' => $field_value === $value,
                'not_equals' => $field_value !== $value,
                'contains' => is_string($field_value) && str_contains($field_value, $value),
                'not_contains' => is_string($field_value) && !str_contains($field_value, $value),
                'gt', 'greater_than' => is_numeric($field_value) && $field_value > $value,
                'gte' => is_numeric($field_value) && $field_value >= $value,
                'lt', 'less_than' => is_numeric($field_value) && $field_value < $value,
                'lte' => is_numeric($field_value) && $field_value <= $value,
                'in' => is_array($value) && in_array($field_value, $value),
                'not_in' => is_array($value) && !in_array($field_value, $value),
                'regex' => is_string($field_value) && preg_match($value, $field_value),
                'empty' => empty($field_value),
                'not_empty' => !empty($field_value),
                default => true
            };

            if (!$matches) {
                return []; // Filter didn't match, return empty
            }
        }

        return $data;
    }

    /**
     * Flatten nested array
     *
     * @param array $data
     * @param string $separator
     * @param string $prefix
     * @return array
     */
    private function flatten_array(array $data, string $separator = '_', string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $new_key = $prefix ? $prefix . $separator . $key : $key;

            if (is_array($value) && !empty($value) && array_keys($value) !== range(0, count($value) - 1)) {
                $result = array_merge($result, $this->flatten_array($value, $separator, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }

        return $result;
    }

    /**
     * Unflatten array from flat keys
     *
     * @param array $data
     * @param string $separator
     * @return array
     */
    private function unflatten_array(array $data, string $separator = '_'): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $keys = explode($separator, $key);
            $current = &$result;

            foreach ($keys as $i => $k) {
                if ($i === count($keys) - 1) {
                    $current[$k] = $value;
                } else {
                    if (!isset($current[$k]) || !is_array($current[$k])) {
                        $current[$k] = [];
                    }
                    $current = &$current[$k];
                }
            }
        }

        return $result;
    }

    /**
     * Sort array
     *
     * @param array $data
     * @param array $config
     * @return array
     */
    private function sort_array(array $data, array $config): array
    {
        $direction = $config['direction'] ?? 'asc';

        if ($direction === 'desc') {
            arsort($data);
        } else {
            asort($data);
        }

        return $data;
    }

    /**
     * Rename array keys
     *
     * @param array $data
     * @param array $mappings
     * @return array
     */
    private function rename_keys(array $data, array $mappings): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $new_key = $mappings[$key] ?? $key;
            $result[$new_key] = $value;
        }

        return $result;
    }

    /**
     * Convert array to CSV string
     *
     * @param array $data
     * @return string
     */
    private function array_to_csv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        // Handle single-dimensional array
        if (!is_array(reset($data))) {
            $data = [$data];
        }

        $output = fopen('php://temp', 'r+');

        // Write header
        fputcsv($output, array_keys(reset($data)));

        // Write rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Update job status
     *
     * @param int $job_id
     * @param string $status
     * @param string|null $error_stage
     * @param string|null $error_message
     * @return void
     */
    private function update_job_status(
        int $job_id,
        string $status,
        ?string $error_stage = null,
        ?string $error_message = null
    ): void {
        $update_data = ['status' => $status];

        if ($error_stage !== null) {
            $update_data['error_stage'] = $error_stage;
        }

        if ($error_message !== null) {
            $update_data['error_message'] = $error_message;
        }

        if ($status === self::STATUS_COMPLETED || $status === self::STATUS_FAILED) {
            $update_data['completed_at'] = time();
        }

        Database::update_row(ETL_Job_Model::TABLE_NAME, $job_id, $update_data);
    }

    /**
     * Decode JSON safely
     *
     * @param string|array $json
     * @return array
     */
    private function decode_json(string|array $json): array
    {
        if (is_array($json)) {
            return $json;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get ETL template by ID
     *
     * @param int $id
     * @return Response_Handler
     */
    public static function get_template(int $id): Response_Handler
    {
        return Database::get_rows_data(ETL_Template_Model::TABLE_NAME, 'id', $id, false);
    }

    /**
     * Get all ETL templates
     *
     * @return Response_Handler
     */
    public static function get_all_templates(): Response_Handler
    {
        return Database::get_table_data(ETL_Template_Model::TABLE_NAME);
    }

    /**
     * Create ETL template
     *
     * @param array $data
     * @return Response_Handler
     */
    public static function create_template(array $data): Response_Handler
    {
        // Encode JSON fields
        $json_fields = ['extract_config', 'transform_config', 'load_config', 'field_mappings', 'filters', 'schedule_config'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        $data['is_active'] = $data['is_active'] ?? 1;

        return Database::insert_row(ETL_Template_Model::TABLE_NAME, $data);
    }

    /**
     * Update ETL template
     *
     * @param int $id
     * @param array $data
     * @return Response_Handler
     */
    public static function update_template(int $id, array $data): Response_Handler
    {
        // Encode JSON fields
        $json_fields = ['extract_config', 'transform_config', 'load_config', 'field_mappings', 'filters', 'schedule_config'];
        foreach ($json_fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        return Database::update_row(ETL_Template_Model::TABLE_NAME, $id, $data);
    }

    /**
     * Delete ETL template
     *
     * @param int $id
     * @return Response_Handler
     */
    public static function delete_template(int $id): Response_Handler
    {
        return Database::delete_row(ETL_Template_Model::TABLE_NAME, $id);
    }

    /**
     * Get ETL job by ID
     *
     * @param int $id
     * @return Response_Handler
     */
    public static function get_job(int $id): Response_Handler
    {
        return Database::get_rows_data(ETL_Job_Model::TABLE_NAME, 'id', $id, false);
    }

    /**
     * Get jobs for a template
     *
     * @param int $template_id
     * @return Response_Handler
     */
    public static function get_jobs_for_template(int $template_id): Response_Handler
    {
        return Database::get_rows_data(ETL_Job_Model::TABLE_NAME, 'template_id', $template_id);
    }
}
