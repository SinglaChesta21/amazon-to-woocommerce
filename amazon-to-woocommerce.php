<?php
/**
 * Plugin Name: Amazon to WooCommerce Sync by chesta
 * Description: Import Amazon products to WooCommerce without API.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

// Include admin pages
require_once plugin_dir_path(__FILE__) . 'admin/ats-settings.php';
require_once plugin_dir_path(__FILE__) . 'admin/ats-import-asin.php';
require_once plugin_dir_path(__FILE__) . 'admin/ats-import-keyword.php';
require_once plugin_dir_path(__FILE__) . 'admin/ats-campaign.php';
require_once plugin_dir_path(__FILE__) . 'includes/ats-utils.php';
require_once plugin_dir_path(__FILE__) . 'includes/ats-run-campaigns.php';
require_once plugin_dir_path(__FILE__) . 'admin/ats-imported-products.php';




// Register admin menu
add_action('admin_menu', 'ats_register_amazon_sync_menu');
function ats_register_amazon_sync_menu() {
    add_menu_page('Amazon Sync', 'Amazon Sync', 'manage_options', 'amazon-sync', 'ats_render_settings_page');
    add_submenu_page('amazon-sync', 'Import by ASIN', 'Import by ASIN', 'manage_options', 'ats-import-asin', 'ats_render_asin_import_page');
    add_submenu_page('amazon-sync', 'Import by Keyword', 'Import by Keyword', 'manage_options', 'ats-import-keyword', 'ats_render_keyword_import_page');
    add_submenu_page('amazon-sync', 'Campaign Import', 'Campaign Import', 'manage_options', 'ats-campaign-import', 'ats_render_campaign_import_page');
    add_submenu_page('amazon-sync','Imported Products','Imported Products','manage_options','ats-imported-products','ats_render_imported_products_page'
);
}


// Register custom cron event on plugin activation
register_activation_hook(__FILE__, 'ats_schedule_campaign_import');
function ats_schedule_campaign_import() {
    if (!wp_next_scheduled('ats_run_campaigns')) {
        wp_schedule_event(time(), 'quarter_hour', 'ats_run_campaigns');
    }
}

// Unschedule on plugin deactivation
register_deactivation_hook(__FILE__, 'ats_unschedule_campaign_import');
function ats_unschedule_campaign_import() {
    wp_clear_scheduled_hook('ats_run_campaigns');
}

// Add 15-minute interval to cron
add_filter('cron_schedules', function ($schedules) {
    $schedules['quarter_hour'] = [
        'interval' => 900, // 15 min
        'display' => 'Every 15 Minutes'
    ];
    return $schedules;
});

