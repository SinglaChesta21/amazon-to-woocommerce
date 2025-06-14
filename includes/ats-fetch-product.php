<?php
if (!defined('ABSPATH')) exit;

// ✅ Main function to fetch and import Amazon product
function ats_fetch_amazon_product($asin, $country) {
    $asin = sanitize_text_field($asin);
    $country = sanitize_key($country);

    // ✅ Validate ASIN format
    if (!preg_match('/^B0[A-Z0-9]{8}$/', $asin)) {
        echo "<p style='color:red;'>❌ Invalid ASIN format: $asin</p>";
        return false;
    }

    $api_key = '90268f5fcbdd46de8d88db4489589048';
    $amazon_url = "https://www.amazon.$country/dp/$asin";
    $scraper_url = "http://api.scraperapi.com?api_key=$api_key&render=true&country_code=$country&url=" . urlencode($amazon_url);

    // ✅ Get affiliate ID based on selected country
    $affiliates = get_option('ats_affiliates_data', []);
    $affiliate = isset($affiliates[$country]) && preg_match('/^[a-zA-Z0-9\-]+$/', $affiliates[$country]) ? $affiliates[$country] : '';

    // ✅ Defaults
    $title = 'No Title';
    $price = 0.00;
    $description = 'No Description';

    // ✅ Fetch page HTML
    $html = ats_get_remote_content($scraper_url);
    if (!$html || strpos($html, '<html') === false || strpos($html, 'amazon') === false) {
        echo "<p style='color:red;'>❌ Failed or invalid HTML for ASIN: $asin</p>";
        return false;
    }

    // ✅ Extract Title
    if (preg_match('/<span[^>]*id="productTitle"[^>]*>(.*?)<\/span>/s', $html, $match)) {
        $title = trim($match[1]);
    }

    // ✅ Extract Price
    if (preg_match('/<span class="a-offscreen">₹?([\d,.]+)<\/span>/', $html, $pm)) {
        $price = floatval(str_replace(',', '', $pm[1]));
    }

    // ✅ Extract Description
    if (preg_match('/<div id="feature-bullets"[^>]*>(.*?)<\/div>/s', $html, $descMatch)) {
        $raw = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $descMatch[1]);
        $description = strip_tags($raw, '<ul><li><br><b><strong>');
        $description = wp_trim_words($description, 150, '...');
    }

    // ✅ Extract Images
    preg_match_all('/"hiRes":"(.*?)"/', $html, $imgs);
    $images = array_filter(array_unique($imgs[1] ?? []));
    if (empty($images)) {
        preg_match_all('/"large":"(.*?)"/', $html, $imgs);
        $images = array_filter(array_unique($imgs[1] ?? []));
    }

    // ✅ Create WooCommerce External Product
    $product = new WC_Product_External();
    $product->set_name($title);
    $product->set_description($description);
    $product->set_regular_price($price);
    $product->set_price($price);

    $product_url = "https://www.amazon.$country/dp/$asin";
    if (!empty($affiliate)) {
        $product_url .= "?tag=" . urlencode($affiliate);
    }

    $product->set_product_url($product_url);
    $product->set_button_text('Buy on Amazon');

    // ✅ Add Images
    $image_ids = [];
    foreach (array_slice($images, 0, 5) as $url) {
        $id = ats_sideload_image(esc_url_raw($url));
        if ($id) {
            $image_ids[] = $id;
        } else {
            error_log("Image failed: $url");
        }
    }

    if (!empty($image_ids)) {
        $product->set_image_id($image_ids[0]);
        if (count($image_ids) > 1) {
            $product->set_gallery_image_ids(array_slice($image_ids, 1));
        }
    }

    // ✅ Save Product
    $product_id = $product->save();

    if ($product_id) {
        echo "<p style='color:green;'>✅ Imported Product #$product_id — $title</p>";

        // ✅ Log product
        if (!function_exists('ats_log_product')) {
            require_once plugin_dir_path(__FILE__) . 'ats-utils.php';
        }
        ats_log_product($product_id, $asin);

        return $product_id;
    }

    return false;
}

// ✅ Secure function to download and compress image
function ats_sideload_image($image_url) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) return false;

    $file = [
        'name'     => basename($image_url),
        'type'     => mime_content_type($tmp),
        'tmp_name' => $tmp,
        'error'    => 0,
        'size'     => filesize($tmp),
    ];

    $id = media_handle_sideload($file, 0);
    if (is_wp_error($id)) return false;

    // ✅ Resize & compress image
    $image_path = get_attached_file($id);
    $editor = wp_get_image_editor($image_path);
    if (!is_wp_error($editor)) {
        $editor->resize(800, 800, false); // Maintain aspect ratio
        $editor->set_quality(75);
        $editor->save($image_path);
    }

    return $id;
}
