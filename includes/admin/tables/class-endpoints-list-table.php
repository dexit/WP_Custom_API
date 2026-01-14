<?php

declare(strict_types=1);

namespace WP_Custom_API\Includes\Admin\Tables;

use WP_Custom_API\Includes\Database;
use WP_Custom_API\Includes\Endpoint_Manager\Custom_Endpoint_Model;

/**
 * Prevent direct access from sources other than the WordPress environment
 */
if (!defined('ABSPATH')) exit;

/**
 * Endpoints List Table
 *
 * Displays all custom endpoints in a WordPress admin list table with:
 * - Bulk actions (activate, deactivate, delete)
 * - Status indicators
 * - Search and filtering
 * - Sortable columns
 * - Quick actions (edit, test, duplicate, delete)
 *
 * @since 2.0.0
 */
class Endpoints_List_Table extends \WP_List_Table
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => 'endpoint',
            'plural'   => 'endpoints',
            'ajax'     => true
        ]);
    }

    /**
     * Get table columns
     *
     * @return array
     */
    public function get_columns(): array
    {
        return [
            'cb'           => '<input type="checkbox" />',
            'name'         => __('Name', 'wp-custom-api'),
            'route'        => __('Route', 'wp-custom-api'),
            'method'       => __('Method', 'wp-custom-api'),
            'handler'      => __('Handler Type', 'wp-custom-api'),
            'status'       => __('Status', 'wp-custom-api'),
            'requests_24h' => __('Requests (24h)', 'wp-custom-api'),
            'last_called'  => __('Last Called', 'wp-custom-api'),
            'actions'      => __('Actions', 'wp-custom-api')
        ];
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    protected function get_sortable_columns(): array
    {
        return [
            'name'         => ['name', true],
            'route'        => ['route', false],
            'method'       => ['method', false],
            'handler'      => ['handler_type', false],
            'status'       => ['is_active', false],
            'requests_24h' => ['requests_count', false],
            'last_called'  => ['last_called_at', false]
        ];
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    protected function get_bulk_actions(): array
    {
        return [
            'activate'   => __('Activate', 'wp-custom-api'),
            'deactivate' => __('Deactivate', 'wp-custom-api'),
            'delete'     => __('Delete', 'wp-custom-api'),
            'duplicate'  => __('Duplicate', 'wp-custom-api')
        ];
    }

    /**
     * Render checkbox column
     *
     * @param array $item
     * @return string
     */
    protected function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="endpoint[]" value="%s" />',
            $item['id']
        );
    }

    /**
     * Render name column
     *
     * @param array $item
     * @return string
     */
    protected function column_name($item): string
    {
        $edit_url = admin_url('admin.php?page=wp-custom-api-endpoint-new&id=' . $item['id']);

        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                $edit_url,
                __('Edit', 'wp-custom-api')
            ),
            'test' => sprintf(
                '<a href="#" class="wp-custom-api-test-endpoint" data-endpoint-id="%s">%s</a>',
                $item['id'],
                __('Test', 'wp-custom-api')
            ),
            'duplicate' => sprintf(
                '<a href="#" class="wp-custom-api-duplicate" data-endpoint-id="%s">%s</a>',
                $item['id'],
                __('Duplicate', 'wp-custom-api')
            ),
            'delete' => sprintf(
                '<a href="#" class="wp-custom-api-delete" data-item-id="%s" data-item-type="endpoint" style="color: #b32d2e;">%s</a>',
                $item['id'],
                __('Delete', 'wp-custom-api')
            )
        ];

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            $edit_url,
            esc_html($item['name']),
            $this->row_actions($actions)
        );
    }

    /**
     * Render route column
     *
     * @param array $item
     * @return string
     */
    protected function column_route($item): string
    {
        $base_route = \WP_Custom_API\Config::BASE_API_ROUTE;
        $full_route = '/wp-json/' . $base_route . '/custom/' . $item['slug'];

        if (!empty($item['route'])) {
            $full_route .= '/' . ltrim($item['route'], '/');
        }

        return sprintf(
            '<code class="endpoint-route">%s</code>',
            esc_html($full_route)
        );
    }

    /**
     * Render method column
     *
     * @param array $item
     * @return string
     */
    protected function column_method($item): string
    {
        $method = strtoupper($item['method'] ?? 'POST');
        $class = strtolower($method);

        return sprintf(
            '<span class="endpoint-method %s">%s</span>',
            $class,
            $method
        );
    }

    /**
     * Render handler column
     *
     * @param array $item
     * @return string
     */
    protected function column_handler($item): string
    {
        $handler_labels = [
            'webhook' => __('Webhook', 'wp-custom-api'),
            'action'  => __('Action', 'wp-custom-api'),
            'script'  => __('Script', 'wp-custom-api'),
            'forward' => __('Forward', 'wp-custom-api'),
            'etl'     => __('ETL', 'wp-custom-api')
        ];

        $handler_type = $item['handler_type'] ?? 'webhook';
        return $handler_labels[$handler_type] ?? ucfirst($handler_type);
    }

    /**
     * Render status column
     *
     * @param array $item
     * @return string
     */
    protected function column_status($item): string
    {
        $is_active = (int)($item['is_active'] ?? 0);

        if ($is_active) {
            $badge = '<span class="status-badge status-success">' . __('Active', 'wp-custom-api') . '</span>';
        } else {
            $badge = '<span class="status-badge status-error">' . __('Inactive', 'wp-custom-api') . '</span>';
        }

        $toggle_text = $is_active ? __('Deactivate', 'wp-custom-api') : __('Activate', 'wp-custom-api');
        $toggle_link = sprintf(
            '<a href="#" class="wp-custom-api-toggle-status" data-endpoint-id="%s" data-status="%s">%s</a>',
            $item['id'],
            $is_active,
            $toggle_text
        );

        return $badge . '<br><small>' . $toggle_link . '</small>';
    }

    /**
     * Render requests (24h) column
     *
     * @param array $item
     * @return string
     */
    protected function column_requests_24h($item): string
    {
        global $wpdb;

        $webhook_table = Database::get_table_full_name('webhook_log');
        if (!$webhook_table) {
            return '<span style="color: #646970;">—</span>';
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$webhook_table}
             WHERE endpoint_id = %d
             AND created_at >= %s",
            $item['id'],
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));

        return number_format((int)$count);
    }

    /**
     * Render last called column
     *
     * @param array $item
     * @return string
     */
    protected function column_last_called($item): string
    {
        global $wpdb;

        $webhook_table = Database::get_table_full_name('webhook_log');
        if (!$webhook_table) {
            return '<span style="color: #646970;">—</span>';
        }

        $last_call = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$webhook_table}
             WHERE endpoint_id = %d
             ORDER BY created_at DESC
             LIMIT 1",
            $item['id']
        ));

        if (!$last_call) {
            return '<span style="color: #646970;">' . __('Never', 'wp-custom-api') . '</span>';
        }

        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr($last_call),
            human_time_diff(strtotime($last_call), current_time('timestamp')) . ' ago'
        );
    }

    /**
     * Render actions column
     *
     * @param array $item
     * @return string
     */
    protected function column_actions($item): string
    {
        $edit_url = admin_url('admin.php?page=wp-custom-api-endpoint-new&id=' . $item['id']);

        return sprintf(
            '<a href="%s" class="button button-small">%s</a> ' .
            '<a href="#" class="button button-small wp-custom-api-test-endpoint" data-endpoint-id="%s">%s</a>',
            $edit_url,
            __('Edit', 'wp-custom-api'),
            $item['id'],
            __('Test', 'wp-custom-api')
        );
    }

    /**
     * Prepare items for display
     *
     * @return void
     */
    public function prepare_items(): void
    {
        // Register columns
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns()
        ];

        // Process bulk actions
        $this->process_bulk_action();

        // Get pagination parameters
        $per_page = 20;
        $current_page = $this->get_pagenum();

        // Get filters
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $handler_type = isset($_REQUEST['handler_type']) ? sanitize_text_field($_REQUEST['handler_type']) : '';
        $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';

        // Get sorting
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';

        // Build query
        $items = $this->get_endpoints($search, $handler_type, $status, $orderby, $order, $per_page, $current_page);
        $total_items = $this->get_endpoints_count($search, $handler_type, $status);

        // Set items
        $this->items = $items;

        // Set pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    /**
     * Get endpoints from database
     *
     * @param string $search
     * @param string $handler_type
     * @param string $status
     * @param string $orderby
     * @param string $order
     * @param int $per_page
     * @param int $current_page
     * @return array
     */
    private function get_endpoints(string $search, string $handler_type, string $status, string $orderby, string $order, int $per_page, int $current_page): array
    {
        global $wpdb;

        $table = Database::get_table_full_name(Custom_Endpoint_Model::TABLE_NAME);
        if (!$table) return [];

        $where = ['1=1'];
        $where_values = [];

        // Search filter
        if (!empty($search)) {
            $where[] = '(name LIKE %s OR slug LIKE %s OR route LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        // Handler type filter
        if (!empty($handler_type)) {
            $where[] = 'handler_type = %s';
            $where_values[] = $handler_type;
        }

        // Status filter
        if ($status !== '') {
            $where[] = 'is_active = %d';
            $where_values[] = (int)$status;
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($current_page - 1) * $per_page;

        // Validate orderby
        $allowed_orderby = ['id', 'name', 'route', 'method', 'handler_type', 'is_active', 'created_at'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'id';
        }

        // Validate order
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $where_values[] = $per_page;
        $where_values[] = $offset;

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get endpoints count
     *
     * @param string $search
     * @param string $handler_type
     * @param string $status
     * @return int
     */
    private function get_endpoints_count(string $search, string $handler_type, string $status): int
    {
        global $wpdb;

        $table = Database::get_table_full_name(Custom_Endpoint_Model::TABLE_NAME);
        if (!$table) return 0;

        $where = ['1=1'];
        $where_values = [];

        if (!empty($search)) {
            $where[] = '(name LIKE %s OR slug LIKE %s OR route LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        if (!empty($handler_type)) {
            $where[] = 'handler_type = %s';
            $where_values[] = $handler_type;
        }

        if ($status !== '') {
            $where[] = 'is_active = %d';
            $where_values[] = (int)$status;
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return (int)$wpdb->get_var($sql);
    }

    /**
     * Process bulk actions
     *
     * @return void
     */
    public function process_bulk_action(): void
    {
        $action = $this->current_action();

        if (!$action) {
            return;
        }

        // Security check
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
            wp_die('Security check failed');
        }

        $endpoint_ids = isset($_REQUEST['endpoint']) ? array_map('intval', (array)$_REQUEST['endpoint']) : [];

        if (empty($endpoint_ids)) {
            return;
        }

        switch ($action) {
            case 'activate':
                $this->bulk_activate($endpoint_ids);
                break;

            case 'deactivate':
                $this->bulk_deactivate($endpoint_ids);
                break;

            case 'delete':
                $this->bulk_delete($endpoint_ids);
                break;

            case 'duplicate':
                $this->bulk_duplicate($endpoint_ids);
                break;
        }

        // Redirect to avoid resubmission
        wp_redirect(admin_url('admin.php?page=wp-custom-api-endpoints'));
        exit;
    }

    /**
     * Bulk activate endpoints
     *
     * @param array $ids
     * @return void
     */
    private function bulk_activate(array $ids): void
    {
        global $wpdb;
        $table = Database::get_table_full_name(Custom_Endpoint_Model::TABLE_NAME);

        if (!$table) return;

        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_active = 1 WHERE id IN ({$ids_placeholder})",
            $ids
        ));
    }

    /**
     * Bulk deactivate endpoints
     *
     * @param array $ids
     * @return void
     */
    private function bulk_deactivate(array $ids): void
    {
        global $wpdb;
        $table = Database::get_table_full_name(Custom_Endpoint_Model::TABLE_NAME);

        if (!$table) return;

        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_active = 0 WHERE id IN ({$ids_placeholder})",
            $ids
        ));
    }

    /**
     * Bulk delete endpoints
     *
     * @param array $ids
     * @return void
     */
    private function bulk_delete(array $ids): void
    {
        foreach ($ids as $id) {
            Database::delete_row(Custom_Endpoint_Model::TABLE_NAME, $id);
        }
    }

    /**
     * Bulk duplicate endpoints
     *
     * @param array $ids
     * @return void
     */
    private function bulk_duplicate(array $ids): void
    {
        global $wpdb;
        $table = Database::get_table_full_name(Custom_Endpoint_Model::TABLE_NAME);

        if (!$table) return;

        foreach ($ids as $id) {
            $endpoint = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ), ARRAY_A);

            if ($endpoint) {
                unset($endpoint['id']);
                $endpoint['name'] = $endpoint['name'] . ' (Copy)';
                $endpoint['slug'] = $endpoint['slug'] . '-copy-' . time();
                $endpoint['is_active'] = 0;

                Database::insert_row(Custom_Endpoint_Model::TABLE_NAME, $endpoint);
            }
        }
    }

    /**
     * Display filter controls
     *
     * @param string $which
     * @return void
     */
    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        $handler_type = isset($_REQUEST['handler_type']) ? $_REQUEST['handler_type'] : '';
        $status = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
        ?>
        <div class="alignleft actions">
            <select name="handler_type">
                <option value=""><?php _e('All Handler Types', 'wp-custom-api'); ?></option>
                <option value="webhook" <?php selected($handler_type, 'webhook'); ?>><?php _e('Webhook', 'wp-custom-api'); ?></option>
                <option value="action" <?php selected($handler_type, 'action'); ?>><?php _e('Action', 'wp-custom-api'); ?></option>
                <option value="script" <?php selected($handler_type, 'script'); ?>><?php _e('Script', 'wp-custom-api'); ?></option>
                <option value="forward" <?php selected($handler_type, 'forward'); ?>><?php _e('Forward', 'wp-custom-api'); ?></option>
                <option value="etl" <?php selected($handler_type, 'etl'); ?>><?php _e('ETL', 'wp-custom-api'); ?></option>
            </select>

            <select name="status">
                <option value=""><?php _e('All Statuses', 'wp-custom-api'); ?></option>
                <option value="1" <?php selected($status, '1'); ?>><?php _e('Active', 'wp-custom-api'); ?></option>
                <option value="0" <?php selected($status, '0'); ?>><?php _e('Inactive', 'wp-custom-api'); ?></option>
            </select>

            <?php submit_button(__('Filter', 'wp-custom-api'), 'secondary', 'filter_action', false); ?>
        </div>
        <?php
    }
}
