<?php
if (!defined('ABSPATH')) exit;

function ats_get_remote_content($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function ats_bulk_price_update() {
    $logs = get_option('ats_imported_products_log', []);
    $country = get_option('ats_amazon_country', 'in');
    $api_key = '90268f5fcbdd46de8d88db4489589048';

    foreach ($logs as $log) {
        $asin = $log['asin'];
        $product_id = $log['product_id'];
        $product = wc_get_product($product_id);
        if (!$product) continue;

        $url = "https://www.amazon.$country/dp/$asin";
        $scraper_url = "http://api.scraperapi.com?api_key=$api_key&render=true&country_code=$country&url=" . urlencode($url);
        $html = ats_get_remote_content($scraper_url);

        $price = 0;
        if (preg_match('/<span class="a-offscreen">₹?([\d,]+\.?\d*)<\/span>/', $html, $pm)) {
            $price = floatval(str_replace(',', '', $pm[1]));
        }

        if ($price > 0 && $price != $product->get_price()) {
            $product->set_price($price);
            $product->set_regular_price($price);
        }

        // ❌ Don't use this for external products
        // $product->set_stock_status('outofstock'); ❌

        $product->save();
    }
}


// ✅ Prevent duplicate log entries
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
