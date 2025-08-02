<?php
if (!defined('ABSPATH')) exit;

function ats_fetch_amazon_product($asin, $country, $assign_category_id = 0) {
    $asin = sanitize_text_field($asin);
    $country = sanitize_key($country);

    if (!preg_match('/^[A-Z0-9]{10}$/i', $asin)) {
        echo "<p style='color:red;'>❌ Invalid ASIN format: $asin</p>";
        return false;
    }

    $use_amazon_api = get_option('ats_use_amazon_api', 0);
    error_log("API Mode: " . ($use_amazon_api ? "Amazon API" : "Scraper API"));

    if ($use_amazon_api) {
        // Force .com for Amazon API, aligning with ats-amazon-api.php
        $country = 'com';
        if (!function_exists('ats_fetch_product_from_amazon_api')) {
            require_once plugin_dir_path(__FILE__) . 'ats-amazon-api.php';
        }
        $product_data = ats_fetch_product_from_amazon_api($asin, $country);
        error_log("Amazon API Response for ASIN $asin: " . (is_array($product_data) ? print_r($product_data, true) : 'Not an array: ' . var_export($product_data, true)));

        // Validate API response with fallback
        if (is_array($product_data) && isset($product_data['title']) && !isset($product_data['error'])) {
            $title = $product_data['title'];
            $price = $product_data['price'];
            $description = $product_data['description'] ?? '<h3>Product Details</h3><table class="shop_attributes" cellspacing="0"><tr><th>Rating</th><td>N/A / 5</td></tr><tr><th>Reviews</th><td>N/A reviews</td></tr></table>';
            $rating = $product_data['rating'];
            $review_count = $product_data['review_count'];
            $images = $product_data['images'] ?? [];
            $short_description = $product_data['short_description'] ?? '';
            $attributes = $product_data['attributes'] ?? [];
        } else {
            error_log("API fetch failed for ASIN $asin: " . (is_array($product_data) && isset($product_data['error']) ? $product_data['error'] : 'Unknown error'));
            echo "<p style='color:red;'>❌ Failed to fetch product via Amazon API for ASIN: $asin. Error: " . (is_array($product_data) && isset($product_data['error']) ? esc_html($product_data['error']) : 'Unknown error') . "</p>";
            return false;
        }
    } else {
        // Use user-selected country for Scraper API
        $country = get_option('ats_amazon_country', 'in');
        $user_api_key = trim(get_option('ats_amazon_api_key', ''));
        $api_key = !empty($user_api_key) ? $user_api_key : '77f345657c94fb130865a9f21b63a935';
        error_log("Scraper API Key: $api_key");

        $amazon_url = "https://www.amazon.$country/dp/$asin";
        $scraper_url = "http://api.scraperapi.com?api_key=$api_key&render=true&country_code=$country&url=" . urlencode($amazon_url);

        $affiliates = get_option('ats_affiliates_data', []);
        $affiliate = isset($affiliates[$country]) && preg_match('/^[a-zA-Z0-9\-]+$/', $affiliates[$country]) ? $affiliates[$country] : '';

        $title = 'No Title';
        $price = 0.00;
        $short_description = '';
        $rating = 'N/A';
        $review_count = 'N/A';
        $images = [];
        $attributes = [];

        if (!function_exists('ats_get_remote_content')) {
            require_once plugin_dir_path(__FILE__) . 'ats-utils.php';
        }
        $html = ats_get_remote_content($scraper_url);
        error_log("Scraper HTML for ASIN $asin: " . ($html ? substr($html, 0, 500) : 'Empty'));
        file_put_contents(__DIR__ . '/debug_amazon_html_' . $asin . '.html', $html ?: 'Empty response');

        if (!$html || strpos($html, '<html') === false || strpos($html, 'amazon') === false || 
            strpos($html, 'Enter the characters you see below') !== false || 
            strpos($html, 'captcha') !== false || 
            strpos($html, 'Robot Check') !== false) {
            echo "<p style='color:red;'>🚫 CAPTCHA/Blocked content or empty response for ASIN: $asin</p>";
            return false;
        }

        // Extract title
        if (preg_match('/<span[^>]*id="productTitle"[^>]*>(.*?)<\/span>/s', $html, $match)) {
            $title = trim($match[1]);
        }

        // Extract price
        if (preg_match('/<span[^>]*class="a-offscreen"[^>]*>\s*([^\d\s<]*[\d,.]+)\s*<\/span>/i', $html, $pm)) {
            $currency_price = trim($pm[1]);
            $price = floatval(str_replace([',', '₹', '$', '€', '£', '¥', ' '], '', $currency_price));
        }

        // Extract rating
        if (preg_match('/aria-label="([0-5](\.\d)?) out of 5 stars"/i', $html, $match_rating)) {
            $rating = $match_rating[1];
        } elseif (preg_match('/<span[^>]*class="a-icon-alt"[^>]*>([0-5](\.\d)?) out of 5 stars<\/span>/i', $html, $alt_rating)) {
            $rating = $alt_rating[1];
        }

        // Extract review count
        if (preg_match('/<span[^>]*id="acrCustomerReviewText"[^>]*>([\d,]+)\s*ratings?<\/span>/i', $html, $match_reviews)) {
            $review_count = str_replace(',', '', $match_reviews[1]);
        } elseif (preg_match('/([\d,]+)\s*global ratings/i', $html, $alt_reviews)) {
            $review_count = str_replace(',', '', $alt_reviews[1]);
        }

        // Extract "About This Item" for short description
        if (preg_match('/<div[^>]*id="feature-bullets"[^>]*>(.*?)<\/div>/is', $html, $f)) {
            $raw = preg_replace([
                '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
                '/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/i',
                '/\.aplus-[^{]+{[^}]+}/i'
            ], '', $f[1]);
            $text = strip_tags($raw, '<ul><li><br><strong><b>');
            $text = trim(preg_replace('/\s*(<br\s*\/?>\s*)+/', "\n", $text));
            if ($text) {
                $short_description = '<div class="about-this-item"><h3>About this item</h3>' . $text . '</div>';
            }
        }

        // Fallback to product description if feature-bullets is empty
        if (empty($short_description) && preg_match('/<div[^>]*id="productDescription"[^>]*>(.*?)<\/div>/is', $html, $fallback)) {
            $text = strip_tags($fallback[1], '<p><br><ul><li><b><strong>');
            $short_description = '<div class="about-this-item"><h3>About this item</h3>' . $text . '</div>';
        }

        // Extract product details for description (table format)
        $description = '<h3>Product Details</h3><table class="shop_attributes" cellspacing="0">';
        $rows = [];

        // Try table-based details (productDetails_techSpec_section_1)
        if (preg_match('/<table[^>]*id="productDetails_techSpec_section_1"[^>]*>(.*?)<\/table>/is', $html, $t)) {
            preg_match_all('/<tr[^>]*>\s*<th[^>]*>(.*?)<\/th>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/is', $t[1], $rows, PREG_SET_ORDER);
        }
        // Try table-based details (productDetails_detailBullets_sections1)
        elseif (preg_match('/<table[^>]*id="productDetails_detailBullets_sections1"[^>]*>(.*?)<\/table>/is', $html, $t2)) {
            preg_match_all('/<tr[^>]*>\s*<th[^>]*>(.*?)<\/th>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/is', $t2[1], $rows, PREG_SET_ORDER);
        }
        // Try list-based details (detailBulletsWrapper_feature_div)
        elseif (preg_match('/<div[^>]*id="detailBulletsWrapper_feature_div"[^>]*>(.*?)<\/div>/is', $html, $db)) {
            preg_match_all('/<span[^>]*class="a-text-bold"[^>]*>([^<:]+?):\s*<\/span>\s*<span[^>]*>([^<]+?)<\/span>/is', $db[1], $rows, PREG_SET_ORDER);
        }
        // Try tabbed details (productDetails_db_sections or similar)
        elseif (preg_match('/<div[^>]*id="productDetails_db_sections"[^>]*>(.*?)<\/div>/is', $html, $pd)) {
            preg_match_all('/<div[^>]*class="a-section[^>]*>(.*?)(?=<div[^>]*class="a-section|<\/div>)/is', $pd[1], $sections, PREG_SET_ORDER);
            foreach ($sections as $section) {
                preg_match_all('/<span[^>]*class="a-text-bold"[^>]*>([^<:]+?):\s*<\/span>\s*<span[^>]*>([^<]+?)<\/span>/is', $section[1], $section_rows, PREG_SET_ORDER);
                $rows = array_merge($rows, $section_rows);
                preg_match_all('/<tr[^>]*>\s*<th[^>]*>(.*?)<\/th>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/is', $section[1], $table_rows, PREG_SET_ORDER);
                $rows = array_merge($rows, $table_rows);
            }
        }
        // Fallback: Try generic product details section
        elseif (preg_match('/<div[^>]*id="productDetails"[^>]*>(.*?)<\/div>/is', $html, $pd_fallback)) {
            preg_match_all('/<tr[^>]*>\s*<th[^>]*>(.*?)<\/th>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/is', $pd_fallback[1], $rows, PREG_SET_ORDER);
            if (empty($rows)) {
                preg_match_all('/<span[^>]*class="a-text-bold"[^>]*>([^<:]+?):\s*<\/span>\s*<span[^>]*>([^<]+?)<\/span>/is', $pd_fallback[1], $rows, PREG_SET_ORDER);
            }
        }

        if (!empty($rows)) {
            foreach ($rows as $r) {
                $label = esc_html(trim(strip_tags($r[1])));
                $value = esc_html(trim(strip_tags($r[2])));
                if (stripos($label, 'Customer Reviews') !== false || stripos($label, 'Best Sellers Rank') !== false || stripos($label, 'Amazon') !== false) {
                    continue;
                }
                if ($label && $value) {
                    $description .= "<tr><th>{$label}</th><td>{$value}</td></tr>";
                    // Add to attributes
                    $key = sanitize_title($label);
                    $taxonomy = 'pa_' . $key;
                    if (!taxonomy_exists($taxonomy)) {
                        register_taxonomy($taxonomy, 'product', [
                            'label' => ucfirst($label),
                            'public' => false,
                            'hierarchical' => false,
                            'show_ui' => false,
                            'query_var' => true,
                            'rewrite' => false,
                        ]);
                    }
                    $attributes[$taxonomy] = [
                        'name' => $taxonomy,
                        'value' => $value,
                        'position' => 0,
                        'is_visible' => 1,
                        'is_variation' => 0,
                        'is_taxonomy' => 1
                    ];
                }
            }
        }

        $description .= "<tr><th>Rating</th><td>{$rating} / 5</td></tr>";
        $description .= "<tr><th>Reviews</th><td>{$review_count} reviews</td></tr>";
        $description .= '</table>';

        // Extract images
        preg_match_all('/"hiRes":"(.*?)"/', $html, $hires_matches);
        $images = array_filter(array_unique($hires_matches[1] ?? []));

        if (empty($images)) {
            preg_match_all('/"large":"(.*?)"/', $html, $large_matches);
            $images = array_filter(array_unique($large_matches[1] ?? []));
        }

        if (empty($images) && preg_match('/"mainUrl":"(https:[^"]+)"/', $html, $mainimg)) {
            $images[] = $mainimg[1];
        }
    }

    $product = new WC_Product_External();
    $product->set_name($title);
    $product->set_regular_price($price);
    $product->set_price($price);

    $product_url = "https://www.amazon.$country/dp/$asin";
    if (!empty($affiliate)) {
        $product_url .= "?tag=" . urlencode($affiliate);
    }
    $product->set_product_url($product_url);
    $product->set_button_text('Buy on Amazon');

    if (!empty($short_description)) {
        $product->set_short_description($short_description);
    }

    $product->set_description($description);

    $image_ids = [];
    foreach (array_slice($images, 0, 5) as $url) {
        $id = ats_sideload_image(esc_url_raw($url));
        if ($id) {
            $image_ids[] = $id;
        }
    }
    if (!empty($image_ids)) {
        $product->set_image_id($image_ids[0]);
        if (count($image_ids) > 1) {
            $product->set_gallery_image_ids(array_slice($image_ids, 1));
        }
    }

    $product_id = $product->save();

    if ($product_id && $assign_category_id > 0) {
        wp_set_post_terms($product_id, [$assign_category_id], 'product_cat');
    }

    if ($product_id) {
        if (!empty($attributes) && is_array($attributes)) {
            foreach ($attributes as $taxonomy => $attr) {
                if (is_array($attr) && isset($attr['value'])) {
                    wp_set_object_terms($product_id, $attr['value'], $taxonomy, true);
                }
            }
            $wc_product = wc_get_product($product_id);
            $wc_product->set_attributes($attributes);
            $wc_product->save();
        }

        echo "<p style='color:green;'>✅ Imported Product #$product_id — $title</p>";

        if (!function_exists('ats_log_product')) {
            require_once plugin_dir_path(__FILE__) . 'ats-utils.php';
        }
        ats_log_product($product_id, $asin);

        return $product_id;
    }
    return false;
}

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

    $image_path = get_attached_file($id);
    $editor = wp_get_image_editor($image_path);
    if (!is_wp_error($editor)) {
        $editor->resize(800, 800, false);
        $editor->set_quality(75);
        $editor->save($image_path);
    }
    return $id;
}