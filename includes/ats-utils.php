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
    if (!class_exists('WooCommerce')) {
        error_log("WooCommerce not loaded");
        return ['error' => 'WooCommerce not loaded', 'updated' => 0, 'errors' => []];
    }

    $logs = get_option('ats_imported_products_log', []);
    $country = get_option('ats_amazon_country', 'in');
    $use_amazon_api = get_option('ats_use_amazon_api', 0);
    $user_api_key = trim(get_option('ats_amazon_api_key', ''));
    $api_key = !empty($user_api_key) ? $user_api_key : '77f345657c94fb130865a9f21b63a935';

    if ($use_amazon_api) {
        if (!function_exists('ats_fetch_product_from_amazon_api')) {
            $api_file = plugin_dir_path(__FILE__) . 'ats-amazon-api.php';
            if (file_exists($api_file)) {
                require_once $api_file;
            } else {
                error_log("Missing ats-amazon-api.php");
                return ['error' => 'Missing Amazon API file', 'updated' => 0, 'errors' => []];
            }
        }
        
        $access_key = get_option('ats_amazon_access_key');
        $secret_key = get_option('ats_amazon_secret_key');
        $associate_tag = get_option('ats_amazon_associate_tag');
        
        if (empty($access_key)) {
            error_log("Missing Amazon Access Key");
            return ['error' => 'Missing Amazon Access Key', 'updated' => 0, 'errors' => []];
        }
        if (empty($secret_key)) {
            error_log("Missing Amazon Secret Key");
            return ['error' => 'Missing Amazon Secret Key', 'updated' => 0, 'errors' => []];
        }
        if (empty($associate_tag)) {
            error_log("Missing Amazon Associate Tag");
            return ['error' => 'Missing Amazon Associate Tag', 'updated' => 0, 'errors' => []];
        }
    }

    set_time_limit(300); // Increase execution time
    $max_products = 50; // Limit to 50 products per run
    $updated_count = 0;
    $errors = [];
    $processed = 0;

    foreach ($logs as $log) {
        if ($processed >= $max_products) {
            $errors[] = "Reached maximum products ($max_products) for this update. Please run again to process more.";
            break;
        }
        $processed++;

        $asin = $log['asin'] ?? '';
        $product_id = $log['product_id'] ?? 0;
        $product = wc_get_product($product_id);

        if (!$product || !is_a($product, 'WC_Product')) {
            $errors[] = "Invalid or missing product for ID: $product_id";
            error_log("Invalid or missing product for ID: $product_id");
            continue;
        }
        if (empty($asin)) {
            $errors[] = "Missing ASIN for product ID: $product_id";
            continue;
        }

        try {
            $price = 0;
            $stock_status = $product->get_stock_status(); // Default to current status
            $needs_update = false;

            if ($use_amazon_api) {
                $data = ats_fetch_product_from_amazon_api($asin, $country);
                
                if (isset($data['error']) || !isset($data['price']) || !is_numeric($data['price']) || $data['price'] <= 0) {
                    $errors[] = "Invalid or missing price for ASIN $asin: " . (isset($data['error']) ? $data['error'] : 'No price data');
                    continue;
                }
                
                $price = floatval($data['price']);
                // Skip stock status update in API mode
            } else {
                $url = "https://www.amazon.$country/dp/$asin";
                $scraper_url = "http://api.scraperapi.com?api_key=$api_key&render=true&country_code=$country&url=" . urlencode($url);
                $html = ats_get_remote_content($scraper_url);

                if (!$html) {
                    $errors[] = "No HTML returned for ASIN $asin";
                    continue;
                }

                if (strpos($html, 'Currently unavailable') !== false || 
                    strpos($html, 'Out of Stock') !== false ||
                    strpos($html, 'Temporarily out of stock') !== false) {
                    $stock_status = 'outofstock';
                } else {
                    $stock_status = 'instock';
                }

                if (preg_match('/<span[^>]*class="a-offscreen"[^>]*>\s*([^\d\s<]*[\d,.]+)\s*<\/span>/i', $html, $pm)) {
                    $price = floatval(str_replace([',', '₹', '$', '€', '£', '¥', ' '], '', $pm[1]));
                } elseif (preg_match('/data-a-color="price".*?<span[^>]*class="a-offscreen"[^>]*>(.*?)<\/span>/is', $html, $alt_pm)) {
                    $price = floatval(str_replace([',', '₹', '$', '€', '£', '¥', ' '], '', $alt_pm[1]));
                }
            }

            if (is_numeric($price) && $price > 0 && $price != $product->get_price()) {
                $product->set_price($price);
                $product->set_regular_price($price);
                $needs_update = true;
            }
            
            if (!$use_amazon_api && $stock_status != $product->get_stock_status()) {
                $product->set_stock_status($stock_status);
                $needs_update = true;
            }
            
            if ($needs_update) {
                try {
                    if ($product->save()) {
                        $updated_count++;
                    } else {
                        $errors[] = "Failed to save product ID: $product_id";
                        error_log("Failed to save product ID: $product_id");
                    }
                } catch (Exception $e) {
                    $errors[] = "Error saving product ID $product_id: " . $e->getMessage();
                    error_log("Error saving product ID $product_id: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            $errors[] = "Error processing ASIN $asin: " . $e->getMessage();
            error_log("Error updating product $product_id: " . $e->getMessage());
        }
    }

    return [
        'updated' => $updated_count,
        'errors' => $errors,
        'success' => empty($errors) && $updated_count > 0
    ];
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
