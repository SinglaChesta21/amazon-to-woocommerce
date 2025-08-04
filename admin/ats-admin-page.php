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
 echo '<div class="ats-tab-content">';
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

    echo '</div>';
}
?>
<style>
    
    .wrap {
        margin-top: 20px;
    }

    .nav-tab-wrapper {
        padding: 0;
        margin: 0;
        border-bottom: none;
    }

    .nav-tab {
        border-radius: 6px 6px 0px 0px;
        padding: 10px 20px !important;
        font-size: 16px !important;
        font-weight: 600 !important;
        background-color: #f0f0f0 !important;  /* Inactive tab */
        color: #2E8BA6 !important;
        border: 1px solid #ddd;
        border-bottom: none !important;
        margin-right: 2px;
        margin-left: 0 !important;
    }

    .nav-tab-active {
        background-color: white !important;
        border: 1px solid #ddd;
        border-bottom: none !important;
        position: relative;
        z-index: 2;
    }

    .ats-tab-content {
        background-color: white;
        padding: 20px;
        border: 1px solid #ddd;
        border-top: none;
        border-radius: 0 0 6px 6px;
        margin-top: -1px;
        position: relative;
        z-index: 1;
    }

    /* Optional: Remove unwanted whitespace around everything */
    .wrap > h1 {
        margin-bottom: 0.5rem;
    }
</style>
