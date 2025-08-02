<?php
if (!defined('ABSPATH')) exit;

// Ensure fetch product function is loaded
if (!function_exists('ats_fetch_amazon_product')) {
    require_once plugin_dir_path(__FILE__) . 'ats-fetch-product.php';
}

function ats_run_campaigns_cron_job() {
    $campaigns = get_option('ats_campaigns', []);
    if (empty($campaigns) || !is_array($campaigns)) {
        error_log("No campaigns found or invalid campaign data");
        return;
    }

    $current_time = current_time('timestamp');
    $country = get_option('ats_amazon_country', 'in');
    $use_amazon_api = get_option('ats_use_amazon_api', false);
    error_log("Cron Run: Time=$current_time, Country=$country, Use Amazon API=" . ($use_amazon_api ? 'Yes' : 'No'));

    foreach ($campaigns as $index => $c) {
        if (empty($c['active'])) {
            error_log("Campaign #$index: Skipped (inactive)");
            continue;
        }

        // Get imported ASINs and last import time
        $imported = get_option("ats_campaign_imported_$index", []);
        if (!is_array($imported)) $imported = [];
        $last_import_time = get_option("ats_campaign_last_import_time_$index", 0);
        $rate = max(1, intval($c['rate'])); // Ensure rate is at least 1
        $import_interval = 3600 / $rate; // Seconds between imports
        error_log("Campaign #$index: Rate=$rate, Interval=$import_interval, Last Import=$last_import_time, Next Import=" . ($last_import_time + $import_interval));

        // Check if it's time to import the next product
        if ($current_time < $last_import_time + $import_interval) {
            error_log("Campaign #$index: Skipped (not time yet)");
            continue;
        }

        // Fetch ASINs
        $asins = [];
        if ($use_amazon_api) {
            if (!function_exists('ats_search_products_from_amazon_api')) {
                require_once plugin_dir_path(__FILE__) . 'ats-amazon-api.php';
            }
            $search_results = ats_search_products_from_amazon_api($c['keyword'], $country, $c['category']);
            if (isset($search_results['error'])) {
                error_log("❌ Amazon API failed for campaign #$index. Full Response: " . print_r($search_results, true));
                continue;
            }
            error_log("Campaign #$index: Fetched " . count($search_results) . " ASINs via Amazon API");
            $asins = is_array($search_results) ? $search_results : [];
        } else {
            $api_key = '77f345657c94fb130865a9f21b63a935';
            $search_url = "https://www.amazon.$country/s?k=" . urlencode($c['keyword']) . "&i=" . urlencode($c['category']);
            $scraper_url = "http://api.scraperapi.com?api_key=$api_key&render=true&country_code=$country&url=" . urlencode($search_url);

            $html = ats_get_remote_content($scraper_url);
            if (!$html) {
                error_log("Scraper API failed for campaign #$index");
                continue;
            }
            file_put_contents(__DIR__ . "/debug_scraper_$index.html", $html); // Save for debugging
            preg_match_all('/data-asin="([A-Z0-9]{10})"/', $html, $matches);
            $asins = array_filter(array_unique($matches[1] ?? []));
            error_log("Campaign #$index: Fetched " . count($asins) . " ASINs via Scraper API");
        }

        if (empty($asins)) {
            error_log("Campaign #$index: No valid ASINs found");
            continue;
        }

        $to_import = array_diff($asins, $imported);
        if (empty($to_import)) {
            error_log("Campaign #$index: All ASINs already imported");
            continue;
        }

        $asin = reset($to_import);
        ob_start();
        $result = ats_fetch_amazon_product($asin, $country, $c['wc_category'] ?? 0);
        ob_end_clean();

        if ($result) {
            $log = get_option("ats_campaign_log_$index", []);
            if (!is_array($log)) $log = [];
            $log[] = [
                'time' => current_time('mysql'),
                'asin' => $asin,
            ];
            update_option("ats_campaign_log_$index", $log);
            $imported[] = $asin;
            update_option("ats_campaign_imported_$index", array_unique($imported));
            update_option("ats_campaign_last_import_time_$index", $current_time);
            update_option('ats_last_sync_time', $current_time);
            error_log("Campaign #$index: Imported ASIN $asin (Product ID: $result)");
        } else {
            error_log("Campaign #$index: Failed to import ASIN $asin");
        }
    }
}

// Schedule cron
if (!wp_next_scheduled('ats_campaigns_cron')) {
    wp_schedule_event(time(), 'one_minute', 'ats_campaigns_cron');
}

add_filter('cron_schedules', function ($schedules) {
    $schedules['one_minute'] = [
        'interval' => 60,
        'display'  => __('Every Minute'),
    ];
    return $schedules;
});

add_action('ats_campaigns_cron', 'ats_run_campaigns_cron_job');

// Manual test endpoint
if (isset($_GET['test_ats_cron'])) {
    ats_run_campaigns_cron_job();
    wp_die('Cron tested');
}

