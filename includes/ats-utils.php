<?php
if (!defined('ABSPATH')) exit;

// ✅ Helper to fetch HTML content using cURL
function ats_get_remote_content($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

// ✅ Bulk Price Updater – uses Amazon API if enabled, otherwise Scraper
function ats_bulk_price_update() {
    $logs = get_option('ats_imported_products_log', []);
    $country = get_option('ats_amazon_country', 'in');
    $use_amazon_api = get_option('ats_use_amazon_api', 0);
    $api_key = '77f345657c94fb130865a9f21b63a935'; // fallback scraper API key

    foreach ($logs as $log) {
        $asin = $log['asin'];
        $product_id = $log['product_id'];
        $product = wc_get_product($product_id);
        if (!$product) continue;

        $price = 0;

        if ($use_amazon_api) {
            // ✅ Use Amazon API
            if (!function_exists('ats_fetch_product_from_amazon_api')) {
                require_once plugin_dir_path(__FILE__) . 'ats-fetch-product.php';
            }
            $data = ats_fetch_product_from_amazon_api($asin, $country);
            $price = isset($data['price']) ? floatval($data['price']) : 0;
        } else {
            // 🕸️ Use Scraper API (fallback)
            $url = "https://www.amazon.$country/dp/$asin";
            $scraper_url = "http://api.scraperapi.com?api_key=$api_key&render=true&country_code=$country&url=" . urlencode($url);
            $html = ats_get_remote_content($scraper_url);

            if (preg_match('/<span class="a-offscreen">₹?([\d,]+\.?\d*)<\/span>/', $html, $pm)) {
                $price = floatval(str_replace(',', '', $pm[1]));
            }
        }

        // ✅ Update price if needed
        if ($price > 0 && $price != $product->get_price()) {
            $product->set_price($price);
            $product->set_regular_price($price);
            $product->save();
        }
    }
}

// ✅ Log product import to avoid duplicates
function ats_log_product($product_id, $asin) {
    $product_id = absint($product_id);
    $asin = sanitize_text_field($asin);

    $logs = get_option('ats_imported_products_log', []);
    foreach ($logs as $entry) {
        if ($entry['product_id'] === $product_id && $entry['asin'] === $asin) {
            return; // Already logged
        }
    }

    $logs[] = [
        'product_id'  => $product_id,
        'asin'        => $asin,
        'imported_on' => current_time('mysql')
    ];
    update_option('ats_imported_products_log', $logs);
}
