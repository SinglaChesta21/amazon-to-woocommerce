<?php
/**
 * Plugin Name: Amazon Product Fetcher 2.0
 * Description: Import Amazon products to WooCommerce without API.
 * Version: 2.0
 */

if (!defined('ABSPATH')) exit;

// Includes
require_once plugin_dir_path(__FILE__) . 'includes/ats-utils.php';
require_once plugin_dir_path(__FILE__) . 'includes/ats-run-campaigns.php';
require_once plugin_dir_path(__FILE__) . 'admin/ats-admin-page.php';

add_action('admin_menu', function () {
    add_menu_page(
        'Amazon Sync',
        'Amazon Sync',
        'manage_options',
        'amazon-sync',
        'ats_render_admin_panel',
        'dashicons-amazon',
        56
    );
});
// Cron scheduler
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('ats_run_campaigns')) {
        wp_schedule_event(time(), 'quarter_hour', 'ats_run_campaigns');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('ats_run_campaigns');
});

// Custom cron interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['quarter_hour'] = [
        'interval' => 900,
        'display'  => 'Every 15 Minutes',
    ];
    return $schedules;
});
add_action('admin_enqueue_scripts', 'ats_enqueue_fontawesome');

function ats_enqueue_fontawesome() {
    wp_enqueue_script('font-awesome-kit', 'https://kit.fontawesome.com/ee7f5a487f.js', [], null, false);
}



