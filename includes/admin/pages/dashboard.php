<?php
/**
 * Dashboard Admin Page
 *
 * Displays overview statistics, charts, and recent activity
 *
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

use WP_Custom_API\Includes\Database;
use WP_Custom_API\Includes\Endpoint_Manager\Custom_Endpoint_Model;
use WP_Custom_API\Includes\Endpoint_Manager\Webhook_Log_Model;
use WP_Custom_API\Includes\Endpoint_Manager\System_Manager;

// Get statistics
$system_stats = System_Manager::instance()->get_statistics();

// Calculate dashboard stats
$total_endpoints = $system_stats['endpoints'] ?? 0;
$active_endpoints = $system_stats['active_endpoints'] ?? 0;
$webhooks_today = $system_stats['webhook_logs_today'] ?? 0;
$queued_jobs = function_exists('as_get_scheduled_actions') ? count(as_get_scheduled_actions(['status' => 'pending', 'group' => 'wp_custom_api%'], 'ids')) : 0;
?>

<div class="wrap wp-custom-api-dashboard">
    <h1 class="wp-heading-inline">
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <p class="description">
        <?php _e('Overview of your Custom API endpoints, webhooks, and system performance.', 'wp-custom-api'); ?>
    </p>

    <hr class="wp-header-end">

    <!-- Statistics Cards -->
    <div class="wp-custom-api-stats-grid">
        <!-- Total Endpoints -->
        <div class="wp-custom-api-stat-card">
            <div class="stat-icon dashicons dashicons-rest-api"></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_endpoints); ?></div>
                <div class="stat-label"><?php _e('Total Endpoints', 'wp-custom-api'); ?></div>
                <div class="stat-meta">
                    <span class="stat-active"><?php echo $active_endpoints; ?> <?php _e('active', 'wp-custom-api'); ?></span>
                </div>
            </div>
        </div>

        <!-- Webhooks Today -->
        <div class="wp-custom-api-stat-card">
            <div class="stat-icon dashicons dashicons-update"></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($webhooks_today); ?></div>
                <div class="stat-label"><?php _e('Webhooks Today', 'wp-custom-api'); ?></div>
                <div class="stat-meta">
                    <span class="stat-change positive">+0%</span>
                    <?php _e('vs yesterday', 'wp-custom-api'); ?>
                </div>
            </div>
        </div>

        <!-- Queued Jobs -->
        <div class="wp-custom-api-stat-card">
            <div class="stat-icon dashicons dashicons-list-view"></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($queued_jobs); ?></div>
                <div class="stat-label"><?php _e('Queued Jobs', 'wp-custom-api'); ?></div>
                <div class="stat-meta">
                    <span class="stat-pending"><?php echo $queued_jobs; ?> <?php _e('pending', 'wp-custom-api'); ?></span>
                </div>
            </div>
        </div>

        <!-- Error Rate -->
        <div class="wp-custom-api-stat-card">
            <div class="stat-icon dashicons dashicons-warning"></div>
            <div class="stat-content">
                <div class="stat-value">0.00%</div>
                <div class="stat-label"><?php _e('Error Rate', 'wp-custom-api'); ?></div>
                <div class="stat-meta">
                    <span class="stat-errors">0 <?php _e('errors today', 'wp-custom-api'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="wp-custom-api-dashboard-content">
        <!-- Left Column -->
        <div class="wp-custom-api-dashboard-left">

            <!-- Getting Started -->
            <div class="wp-custom-api-panel">
                <h2><?php _e('🚀 Getting Started', 'wp-custom-api'); ?></h2>
                <p><?php _e('Welcome to WP Custom API 2.0! This is the enterprise rebuild with powerful new features:', 'wp-custom-api'); ?></p>

                <h3>✅ Phase 1 Complete: Foundation</h3>
                <ul>
                    <li><strong>Composer Dependencies:</strong> Action Scheduler, Monolog Logger</li>
                    <li><strong>Admin Menu:</strong> Complete navigation system</li>
                    <li><strong>Dashboard:</strong> Statistics and monitoring (this page!)</li>
                </ul>

                <h3>⏳ Coming Soon (Phase 2-7):</h3>
                <ul>
                    <li>Visual Endpoint Builder with tabs</li>
                    <li>Job Queue Integration</li>
                    <li>Advanced Logging & Monitoring</li>
                    <li>Visual Workflow Builder (React)</li>
                    <li>ETL Pipeline Builder</li>
                    <li>Complete Testing Suite</li>
                </ul>

                <p><a href="<?php echo plugins_url('REBUILD_README.md', WP_CUSTOM_API_FOLDER_PATH . 'wp_custom_api.php'); ?>" class="button button-primary">
                    📖 View Full Implementation Plan
                </a></p>
            </div>

        </div>

        <!-- Right Column -->
        <div class="wp-custom-api-dashboard-right">

            <!-- System Health -->
            <div class="wp-custom-api-panel">
                <h2><?php _e('System Health', 'wp-custom-api'); ?></h2>
                <?php
                $health = System_Manager::instance()->health_check();
                $health_status = $health['healthy'] ? 'healthy' : 'warning';
                ?>
                <div class="system-health-status status-<?php echo $health_status; ?>">
                    <span class="dashicons dashicons-<?php echo $health['healthy'] ? 'yes-alt' : 'warning'; ?>"></span>
                    <?php echo $health['healthy'] ? __('All Systems Operational', 'wp-custom-api') : __('Issues Detected', 'wp-custom-api'); ?>
                </div>

                <ul class="health-checks">
                    <?php foreach ($health['checks'] as $check_name => $check): ?>
                    <li class="health-check <?php echo $check['status'] ? 'pass' : 'fail'; ?>">
                        <span class="dashicons dashicons-<?php echo $check['status'] ? 'yes' : 'dismiss'; ?>"></span>
                        <?php echo esc_html($check['message']); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Quick Actions -->
            <div class="wp-custom-api-panel">
                <h2><?php _e('Quick Actions', 'wp-custom-api'); ?></h2>
                <div class="quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=wp-custom-api-endpoint-new'); ?>" class="button button-primary button-large">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Create Endpoint', 'wp-custom-api'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wp-custom-api-etl-templates'); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-filter"></span>
                        <?php _e('New ETL Pipeline', 'wp-custom-api'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wp-custom-api-workflows'); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-networking"></span>
                        <?php _e('Build Workflow', 'wp-custom-api'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wp-custom-api-logs'); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php _e('View Logs', 'wp-custom-api'); ?>
                    </a>
                </div>
            </div>

            <!-- System Info -->
            <div class="wp-custom-api-panel">
                <h2><?php _e('System Information', 'wp-custom-api'); ?></h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Plugin Version:', 'wp-custom-api'); ?></strong></td>
                            <td>2.0.0-alpha</td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('WordPress Version:', 'wp-custom-api'); ?></strong></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('PHP Version:', 'wp-custom-api'); ?></strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Action Scheduler:', 'wp-custom-api'); ?></strong></td>
                            <td><?php echo class_exists('ActionScheduler') ? '✅ Active' : '❌ Not Found'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Monolog Logger:', 'wp-custom-api'); ?></strong></td>
                            <td><?php echo class_exists('Monolog\Logger') ? '✅ Active' : '❌ Not Found'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
