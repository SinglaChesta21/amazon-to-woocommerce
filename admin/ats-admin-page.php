<?php
if (!defined('ABSPATH')) exit;

function ats_render_admin_panel() {
    $active_tab = $_GET['tab'] ?? 'dashboard';
    $method = $_GET['method'] ?? '';

    echo '<div class="wrap"><h1>Amazon Sync</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="?page=amazon-sync&tab=dashboard" class="nav-tab ' . ($active_tab === 'dashboard' ? 'nav-tab-active' : '') . '">Dashboard</a>';
    echo '<a href="?page=amazon-sync&tab=import" class="nav-tab ' . ($active_tab === 'import' ? 'nav-tab-active' : '') . '">Import</a>';
    echo '<a href="?page=amazon-sync&tab=products" class="nav-tab ' . ($active_tab === 'products' ? 'nav-tab-active' : '') . '">Products</a>';
    echo '<a href="?page=amazon-sync&tab=settings" class="nav-tab ' . ($active_tab === 'settings' ? 'nav-tab-active' : '') . '">Settings</a>';
    echo '<a href="?page=amazon-sync&tab=amazon_api_integration" class="nav-tab ' . ($active_tab === 'amazon_api_integration' ? 'nav-tab-active' : '') . '">Amazon API Integration</a>';

    echo '</h2>';

    // Load respective files
    $base = plugin_dir_path(__FILE__);

    switch ($active_tab) {
        case 'dashboard':
            include $base . 'ats-dashboard.php';
            break;

        case 'import':
            if ($method === 'asin') {
                include $base . 'ats-import-asin.php';
            } elseif ($method === 'keyword') {
                include $base . 'ats-import-keyword.php';
            } elseif ($method === 'campaign') {
                include $base . 'ats-campaign.php';
            } else {
                include $base . 'ats-import-tab.php';
            }
            break;

        case 'products':
            include $base . 'ats-imported-products.php';
            break;

        case 'settings':
            include $base . 'ats-settings.php';
            break;
        // Add the case for Amazon API Integration
        case 'amazon_api_integration':
            include $base . 'ats-amazon-api-integration.php'; // We'll create this new file next
            break;    
    }

    echo '</div>';
}
