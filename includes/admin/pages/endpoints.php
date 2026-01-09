<?php
/**
 * Endpoints List Admin Page
 *
 * Displays all custom endpoints in a WP_List_Table
 *
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Load the list table class
require_once WP_CUSTOM_API_FOLDER_PATH . 'includes/admin/tables/class-endpoints-list-table.php';

use WP_Custom_API\Includes\Admin\Tables\Endpoints_List_Table;

// Create an instance of the list table
$endpoints_table = new Endpoints_List_Table();
$endpoints_table->prepare_items();
?>

<div class="wrap wp-custom-api-endpoints">
    <h1 class="wp-heading-inline">
        <?php _e('Endpoints', 'wp-custom-api'); ?>
    </h1>

    <a href="<?php echo admin_url('admin.php?page=wp-custom-api-endpoint-new'); ?>" class="page-title-action">
        <?php _e('Add New', 'wp-custom-api'); ?>
    </a>

    <hr class="wp-header-end">

    <?php
    // Display success message if coming from save
    if (isset($_GET['message']) && $_GET['message'] === 'saved') {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Endpoint saved successfully.', 'wp-custom-api') . '</p></div>';
    }

    if (isset($_GET['message']) && $_GET['message'] === 'deleted') {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Endpoint deleted successfully.', 'wp-custom-api') . '</p></div>';
    }
    ?>

    <form method="get">
        <input type="hidden" name="page" value="wp-custom-api-endpoints" />
        <?php
        $endpoints_table->search_box(__('Search endpoints', 'wp-custom-api'), 'search_endpoints');
        $endpoints_table->display();
        ?>
    </form>

    <div class="wp-custom-api-panel" style="margin-top: 20px;">
        <h2><?php _e('💡 Quick Tips', 'wp-custom-api'); ?></h2>
        <ul>
            <li><strong><?php _e('Webhook:', 'wp-custom-api'); ?></strong> <?php _e('Receives and stores incoming webhook data', 'wp-custom-api'); ?></li>
            <li><strong><?php _e('Action:', 'wp-custom-api'); ?></strong> <?php _e('Executes WordPress actions/hooks', 'wp-custom-api'); ?></li>
            <li><strong><?php _e('Script:', 'wp-custom-api'); ?></strong> <?php _e('Runs custom PHP callbacks', 'wp-custom-api'); ?></li>
            <li><strong><?php _e('Forward:', 'wp-custom-api'); ?></strong> <?php _e('Proxies requests to external services', 'wp-custom-api'); ?></li>
            <li><strong><?php _e('ETL:', 'wp-custom-api'); ?></strong> <?php _e('Processes data through ETL pipelines', 'wp-custom-api'); ?></li>
        </ul>
    </div>
</div>
