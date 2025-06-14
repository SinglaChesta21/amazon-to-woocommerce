<?php
if (!defined('ABSPATH')) exit;

// ✅ Make sure the fetch product function is available
if (!function_exists('ats_fetch_amazon_product')) {
    require_once plugin_dir_path(__FILE__) . 'ats-fetch-product.php';
}

function ats_run_campaigns_cron_job() {
    $campaigns = get_option('ats_campaigns', []);
    if (empty($campaigns) || !is_array($campaigns)) return;

    foreach ($campaigns as $index => $c) {
        if (empty($c['active'])) continue;

        $imported = get_option("ats_campaign_imported_$index", []);
        if (!is_array($imported)) $imported = [];

        $keyword = urlencode($c['keyword']);
        $category = urlencode($c['category']);
        $country = get_option('ats_amazon_country', 'in');

        $url = "https://www.amazon.$country/s?k=$keyword&i=$category";
        $api_key = '90268f5fcbdd46de8d88db4489589048';
        $scraper_url = "http://api.scraperapi.com?api_key=$api_key&render=true&country_code=$country&url=" . urlencode($url);

        $html = ats_get_remote_content($scraper_url);

        // ✅ Fail-safe: check if HTML is fetched
        if (!$html) continue;

        preg_match_all('/data-asin="(B0[A-Z0-9]{8})"/', $html, $matches);
        $asins = array_filter(array_unique($matches[1] ?? []));

        // ✅ Skip if no valid ASINs found
        if (empty($asins)) continue;

        $to_import = array_diff($asins, $imported);
        $count = 0;

        foreach ($to_import as $asin) {
            if ($count >= intval($c['rate'])) break;

            ob_start();
            $result = ats_fetch_amazon_product($asin, $country);
            ob_end_clean();

            if (!$result) continue; // skip on failure

            // ✅ Log the import
            $log = get_option("ats_campaign_log_$index", []);
            if (!is_array($log)) $log = [];

            $log[] = [
                'time' => current_time('mysql'),
                'asin' => $asin,
            ];
            update_option("ats_campaign_log_$index", $log);

            $imported[] = $asin;
            $count++;
        }

        // ✅ Update imported ASINs
        update_option("ats_campaign_imported_$index", array_unique($imported));
    }
}

// ✅ Schedule campaign cron hourly if not already
if (!wp_next_scheduled('ats_campaigns_cron')) {
    wp_schedule_event(time(), 'hourly', 'ats_campaigns_cron');
}

add_action('ats_campaigns_cron', 'ats_run_campaigns_cron_job');
