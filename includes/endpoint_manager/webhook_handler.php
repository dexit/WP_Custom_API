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
 * Webhook Handler - Receives and stores incoming webhook data
 *
 * This class handles:
 * - Receiving webhook payloads
 * - Validating webhook signatures
 * - Logging all webhook requests
 * - Triggering ETL processes automatically
 *
 * @since 1.1.0
 */

final class Webhook_Handler
{
    /**
     * Webhook status constants
     */
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';

    /**
     * Receive and process an incoming webhook
     *
     * @param WP_REST_Request $request The incoming request
     * @param array $endpoint The endpoint configuration
     * @param array $config Handler configuration
     * @return WP_REST_Response
     */
    public function receive(WP_REST_Request $request, array $endpoint, array $config): WP_REST_Response
    {
        $log_id = null;
        $status = self::STATUS_RECEIVED;
        $error_message = null;

        try {
            // Create initial log entry
            $log_data = $this->create_log_entry($request, $endpoint);
            $log_result = Database::insert_row(Webhook_Log_Model::TABLE_NAME, $log_data);

            if (!$log_result->ok) {
                throw new \Exception('Failed to create webhook log entry');
            }

            $log_id = $log_result->data['id'];

            // Validate signature if configured
            $signature_valid = $this->validate_signature($request, $config);

            // Update signature validation status
            Database::update_row(Webhook_Log_Model::TABLE_NAME, $log_id, [
                'signature_valid' => $signature_valid ? 1 : 0
            ]);

            if (!$signature_valid && ($config['require_signature'] ?? false)) {
                throw new \Exception('Invalid webhook signature');
            }

            // Get the payload
            $payload = $this->extract_payload($request, $config);

            // Fire action for custom processing
            do_action('wp_custom_api_webhook_received', $payload, $endpoint, $request, $log_id);

            // Trigger auto-ETL if configured
            if (!empty($config['auto_etl_template_id'])) {
                $this->trigger_etl($log_id, $config['auto_etl_template_id'], $payload);
                $status = self::STATUS_QUEUED;
            } else {
                $status = self::STATUS_PROCESSED;
            }

            // Custom response handling
            $response_data = $this->build_response($config, $payload, $log_id);

            // Update log status
            Database::update_row(Webhook_Log_Model::TABLE_NAME, $log_id, [
                'status' => $status,
                'processed_at' => time(),
                'response_code' => 200,
                'response_body' => json_encode($response_data)
            ]);

            return new WP_REST_Response($response_data, 200);

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            Error_Generator::generate('Webhook Handler Error', $error_message);

            // Update log with error
            if ($log_id) {
                Database::update_row(Webhook_Log_Model::TABLE_NAME, $log_id, [
                    'status' => self::STATUS_FAILED,
                    'error_message' => $error_message,
                    'processed_at' => time()
                ]);
            }

            return new WP_REST_Response([
                'message' => Config::DEBUG_MESSAGE_MODE ? $error_message : 'Webhook processing failed',
                'webhook_id' => $log_id
            ], 500);
        }
    }

    /**
     * Create initial log entry data
     *
     * @param WP_REST_Request $request
     * @param array $endpoint
     * @return array
     */
    private function create_log_entry(WP_REST_Request $request, array $endpoint): array
    {
        $headers = $request->get_headers();
        $filtered_headers = $this->filter_sensitive_headers($headers);

        return [
            'endpoint_id' => (int) ($endpoint['id'] ?? 0),
            'source_ip' => Endpoint_Manager::get_client_ip(),
            'source_identifier' => $this->identify_source($request, $endpoint),
            'request_method' => $request->get_method(),
            'request_headers' => json_encode($filtered_headers),
            'request_payload' => $request->get_body(),
            'query_params' => json_encode($request->get_query_params()),
            'status' => self::STATUS_PENDING,
            'retry_count' => 0
        ];
    }

    /**
     * Filter sensitive headers from logging
     *
     * @param array $headers
     * @return array
     */
    private function filter_sensitive_headers(array $headers): array
    {
        $sensitive = [
            'authorization',
            'x-api-key',
            'x-auth-token',
            'cookie',
            'x-webhook-secret'
        ];

        $filtered = [];
        foreach ($headers as $key => $value) {
            $lower_key = strtolower($key);
            if (in_array($lower_key, $sensitive, true)) {
                $filtered[$key] = '[REDACTED]';
            } else {
                $filtered[$key] = is_array($value) ? $value[0] ?? '' : $value;
            }
        }

        return $filtered;
    }

    /**
     * Identify the source of the webhook
     *
     * @param WP_REST_Request $request
     * @param array $endpoint
     * @return string
     */
    private function identify_source(WP_REST_Request $request, array $endpoint): string
    {
        // Try common identification headers
        $identifiers = [
            'X-GitHub-Delivery',
            'X-Stripe-Webhook-Id',
            'X-Shopify-Hmac-SHA256',
            'X-Twilio-Signature',
            'X-Request-Id',
            'X-Correlation-Id',
        ];

        foreach ($identifiers as $header) {
            $value = $request->get_header($header);
            if ($value) {
                return "{$header}: {$value}";
            }
        }

        // Fall back to user agent
        $user_agent = $request->get_header('User-Agent') ?? 'Unknown';
        return "User-Agent: {$user_agent}";
    }

    /**
     * Validate webhook signature
     *
     * @param WP_REST_Request $request
     * @param array $config
     * @return bool
     */
    private function validate_signature(WP_REST_Request $request, array $config): bool
    {
        if (empty($config['signature_secret'])) {
            return true; // No signature validation configured
        }

        $header_name = $config['signature_header'] ?? 'X-Webhook-Signature';
        $algorithm = $config['signature_algorithm'] ?? 'sha256';
        $signature_format = $config['signature_format'] ?? 'hex'; // hex or base64

        $received_signature = $request->get_header($header_name);
        if (!$received_signature) {
            return false;
        }

        $payload = $request->get_body();
        $secret = $config['signature_secret'];

        // Calculate expected signature
        $expected = match ($signature_format) {
            'base64' => base64_encode(hash_hmac($algorithm, $payload, $secret, true)),
            default => hash_hmac($algorithm, $payload, $secret),
        };

        // Handle various signature formats (sha256=..., etc.)
        $received_signature = preg_replace('/^(sha\d+|hmac)=/i', '', $received_signature);

        return hash_equals($expected, $received_signature);
    }

    /**
     * Extract payload from request
     *
     * @param WP_REST_Request $request
     * @param array $config
     * @return array
     */
    private function extract_payload(WP_REST_Request $request, array $config): array
    {
        $content_type = $request->get_content_type()['value'] ?? '';

        // JSON payload
        if (strpos($content_type, 'application/json') !== false) {
            $json = json_decode($request->get_body(), true);
            return is_array($json) ? $json : ['raw' => $request->get_body()];
        }

        // Form data
        if (strpos($content_type, 'application/x-www-form-urlencoded') !== false
            || strpos($content_type, 'multipart/form-data') !== false) {
            return $request->get_body_params() ?: [];
        }

        // XML payload
        if (strpos($content_type, 'text/xml') !== false
            || strpos($content_type, 'application/xml') !== false) {
            return $this->parse_xml($request->get_body());
        }

        // Default: return all available data
        return Endpoint_Manager::get_request_data($request);
    }

    /**
     * Parse XML payload
     *
     * @param string $xml
     * @return array
     */
    private function parse_xml(string $xml): array
    {
        libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($parsed === false) {
            return ['raw' => $xml, 'parse_error' => 'Failed to parse XML'];
        }

        return json_decode(json_encode($parsed), true) ?: [];
    }

    /**
     * Trigger ETL processing
     *
     * @param int $log_id
     * @param int $template_id
     * @param array $payload
     * @return void
     */
    private function trigger_etl(int $log_id, int $template_id, array $payload): void
    {
        // Queue ETL job for async processing
        $job_data = [
            'template_id' => $template_id,
            'webhook_log_id' => $log_id,
            'status' => 'pending',
            'started_at' => time(),
            'input_data' => json_encode($payload),
            'retry_count' => 0
        ];

        $result = Database::insert_row(ETL_Job_Model::TABLE_NAME, $job_data);

        if ($result->ok) {
            // Fire action for async processing
            do_action('wp_custom_api_etl_job_queued', $result->data['id'], $template_id, $payload);

            // If async processing not available, process immediately
            if (!has_action('wp_custom_api_process_etl_job')) {
                $this->process_etl_immediately($result->data['id'], $template_id, $payload);
            }
        }
    }

    /**
     * Process ETL job immediately (synchronous)
     *
     * @param int $job_id
     * @param int $template_id
     * @param array $payload
     * @return void
     */
    private function process_etl_immediately(int $job_id, int $template_id, array $payload): void
    {
        try {
            $etl_engine = new ETL_Engine();
            $etl_engine->run_job($job_id, $template_id, $payload);
        } catch (\Exception $e) {
            Error_Generator::generate('ETL Processing Error', $e->getMessage());
        }
    }

    /**
     * Build response based on configuration
     *
     * @param array $config
     * @param array $payload
     * @param int $log_id
     * @return array
     */
    private function build_response(array $config, array $payload, int $log_id): array
    {
        $response = [
            'message' => $config['success_message'] ?? 'Webhook received successfully',
            'webhook_id' => $log_id,
            'timestamp' => time()
        ];

        // Include payload echo if configured
        if ($config['echo_payload'] ?? false) {
            $response['payload'] = $payload;
        }

        // Allow custom response via filter
        return apply_filters('wp_custom_api_webhook_response', $response, $payload, $log_id, $config);
    }

    /**
     * Get webhook logs for an endpoint
     *
     * @param int $endpoint_id
     * @param array $filters Optional filters (status, date range, etc.)
     * @return Response_Handler
     */
    public static function get_logs(int $endpoint_id, array $filters = []): Response_Handler
    {
        return Database::get_rows_data(
            Webhook_Log_Model::TABLE_NAME,
            'endpoint_id',
            $endpoint_id
        );
    }

    /**
     * Get a specific webhook log
     *
     * @param int $log_id
     * @return Response_Handler
     */
    public static function get_log(int $log_id): Response_Handler
    {
        return Database::get_rows_data(
            Webhook_Log_Model::TABLE_NAME,
            'id',
            $log_id,
            false
        );
    }

    /**
     * Retry processing a webhook
     *
     * @param int $log_id
     * @return Response_Handler
     */
    public static function retry(int $log_id): Response_Handler
    {
        $log = self::get_log($log_id);

        if (!$log->ok || empty($log->data)) {
            return Response_Handler::response(false, 404, 'Webhook log not found');
        }

        $log_data = $log->data;
        $max_retries = 3;

        if (($log_data['retry_count'] ?? 0) >= $max_retries) {
            return Response_Handler::response(false, 400, 'Maximum retry attempts reached');
        }

        // Increment retry count
        Database::update_row(Webhook_Log_Model::TABLE_NAME, $log_id, [
            'retry_count' => ($log_data['retry_count'] ?? 0) + 1,
            'status' => self::STATUS_PENDING
        ]);

        // Re-trigger processing
        $payload = json_decode($log_data['request_payload'] ?? '{}', true) ?: [];

        do_action('wp_custom_api_webhook_retry', $payload, $log_data, $log_id);

        return Response_Handler::response(true, 200, 'Webhook retry initiated', ['log_id' => $log_id]);
    }

    /**
     * Delete old webhook logs
     *
     * @param int $days_old Delete logs older than this many days
     * @return Response_Handler
     */
    public static function cleanup(int $days_old = 30): Response_Handler
    {
        global $wpdb;

        $table = Database::get_table_full_name(Webhook_Log_Model::TABLE_NAME);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );

        if ($deleted === false) {
            return Response_Handler::response(false, 500, 'Failed to cleanup webhook logs');
        }

        return Response_Handler::response(true, 200, "Deleted {$deleted} old webhook logs");
    }
}
