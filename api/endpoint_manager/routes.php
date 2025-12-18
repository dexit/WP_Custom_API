<?php

declare(strict_types=1);

namespace WP_Custom_API\Api\Endpoint_Manager;

use WP_Custom_API\Includes\Router;

/**
 * Prevent direct access from sources other than the Wordpress environment
 */

if (!defined('ABSPATH')) exit;

/**
 * Endpoint Manager API Routes
 *
 * Defines all routes for managing custom endpoints, webhooks, ETL, and external services.
 * All management routes require admin or API key authentication.
 *
 * Base path: /wp-json/custom-api/v1/endpoint_manager/
 *
 * @since 1.1.0
 */

// ==================== CUSTOM ENDPOINTS ====================

/**
 * List all endpoints
 * GET /endpoint_manager/endpoints
 */
Router::get(
    '/endpoints',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'list_endpoints']
);

/**
 * Get specific endpoint
 * GET /endpoint_manager/endpoints/{id}
 */
Router::get(
    '/endpoints/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'get_endpoint']
);

/**
 * Create new endpoint
 * POST /endpoint_manager/endpoints
 */
Router::post(
    '/endpoints',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'create_endpoint']
);

/**
 * Update endpoint
 * PUT /endpoint_manager/endpoints/{id}
 */
Router::put(
    '/endpoints/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'update_endpoint']
);

/**
 * Delete endpoint
 * DELETE /endpoint_manager/endpoints/{id}
 */
Router::delete(
    '/endpoints/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'delete_endpoint']
);

// ==================== WEBHOOK LOGS ====================

/**
 * List webhook logs for an endpoint
 * GET /endpoint_manager/webhooks/{endpoint_id}/logs
 */
Router::get(
    '/webhooks/{endpoint_id}/logs',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'list_webhook_logs']
);

/**
 * Get specific webhook log
 * GET /endpoint_manager/webhooks/logs/{id}
 */
Router::get(
    '/webhooks/logs/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'get_webhook_log']
);

/**
 * Retry failed webhook
 * POST /endpoint_manager/webhooks/logs/{id}/retry
 */
Router::post(
    '/webhooks/logs/{id}/retry',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'retry_webhook']
);

/**
 * Cleanup old webhook logs
 * POST /endpoint_manager/webhooks/cleanup
 */
Router::post(
    '/webhooks/cleanup',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'cleanup_webhook_logs']
);

// ==================== ETL TEMPLATES ====================

/**
 * List all ETL templates
 * GET /endpoint_manager/etl/templates
 */
Router::get(
    '/etl/templates',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'list_etl_templates']
);

/**
 * Get specific ETL template
 * GET /endpoint_manager/etl/templates/{id}
 */
Router::get(
    '/etl/templates/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'get_etl_template']
);

/**
 * Create new ETL template
 * POST /endpoint_manager/etl/templates
 */
Router::post(
    '/etl/templates',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'create_etl_template']
);

/**
 * Update ETL template
 * PUT /endpoint_manager/etl/templates/{id}
 */
Router::put(
    '/etl/templates/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'update_etl_template']
);

/**
 * Delete ETL template
 * DELETE /endpoint_manager/etl/templates/{id}
 */
Router::delete(
    '/etl/templates/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'delete_etl_template']
);

/**
 * Test ETL template with sample data
 * POST /endpoint_manager/etl/templates/{id}/test
 */
Router::post(
    '/etl/templates/{id}/test',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'test_etl_template']
);

// ==================== ETL JOBS ====================

/**
 * List ETL jobs for a template
 * GET /endpoint_manager/etl/templates/{template_id}/jobs
 */
Router::get(
    '/etl/templates/{template_id}/jobs',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'list_etl_jobs']
);

/**
 * Get specific ETL job
 * GET /endpoint_manager/etl/jobs/{id}
 */
Router::get(
    '/etl/jobs/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'get_etl_job']
);

// ==================== EXTERNAL SERVICES ====================

/**
 * List all external services
 * GET /endpoint_manager/services
 */
Router::get(
    '/services',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'list_external_services']
);

/**
 * Get specific external service
 * GET /endpoint_manager/services/{id}
 */
Router::get(
    '/services/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'get_external_service']
);

/**
 * Create new external service
 * POST /endpoint_manager/services
 */
Router::post(
    '/services',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'create_external_service']
);

/**
 * Update external service
 * PUT /endpoint_manager/services/{id}
 */
Router::put(
    '/services/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'update_external_service']
);

/**
 * Delete external service
 * DELETE /endpoint_manager/services/{id}
 */
Router::delete(
    '/services/{id}',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'delete_external_service']
);

/**
 * Health check external service
 * GET /endpoint_manager/services/{id}/health
 */
Router::get(
    '/services/{id}/health',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'health_check_service']
);

/**
 * Test external service connection
 * POST /endpoint_manager/services/{id}/test
 */
Router::post(
    '/services/{id}/test',
    [Permission::class, 'admin_or_api_key'],
    [Controller::class, 'test_external_service']
);
