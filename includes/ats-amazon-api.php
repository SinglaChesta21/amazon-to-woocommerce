<?php
if (!defined('ABSPATH')) exit;

// Original function for GetItems (ASIN import)
function ats_fetch_product_from_amazon_api($asin, $country) {
    $access_key = get_option('ats_amazon_access_key');
    $secret_key = trim(preg_replace('/\s+/', '', get_option('ats_amazon_secret_key')));
    $associate_tag = get_option('ats_amazon_associate_tag');
    $marketplace = "www.amazon.com"; // Force .com marketplace
    $region = "us-east-1"; // Correct region for US marketplace

    error_log("API Credentials Check: Access=" . ($access_key ? 'Set' : 'Not Set') . ", Secret=***, Tag=" . ($associate_tag ? 'Set' : 'Not Set') . ", Country=$country, Region=$region");

    if (!$access_key || !$secret_key || !$associate_tag) {
        error_log("Missing required API credentials for ASIN: $asin");
        return ['error' => 'Missing API credentials'];
    }

    $endpoint = "https://webservices.amazon.com/paapi5/getitems";
    $service = 'ProductAdvertisingAPI';
    $request_parameters = [
        "ItemIds" => [$asin],
        "Resources" => [
            "ItemInfo.Title",
            "Offers.Listings.Price",
            "Images.Primary.Large",
            "Images.Variants.Large",
            "ItemInfo.Features",
            "ItemInfo.TechnicalInfo",
            "ItemInfo.Classifications",
            "ItemInfo.ProductInfo",
            "ItemInfo.ByLineInfo",
        ],
        "PartnerTag" => $associate_tag,
        "PartnerType" => "Associates",
        "Marketplace" => $marketplace,
        "Operation" => "GetItems"
    ];

    $payload = json_encode($request_parameters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    error_log("Payload: $payload");
    $content_hash = hash('sha256', $payload);
    error_log("Content Hash: $content_hash");
    $amz_date = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    error_log("Amz Date: $amz_date, Date: $date");

    $credential_scope = "$date/$region/$service/aws4_request";
    $canonical_uri = '/paapi5/getitems';
    $canonical_querystring = '';
    $canonical_headers = "content-encoding:amz-1.0\n" .
                         "content-type:application/json; charset=utf-8\n" .
                         "host:webservices.amazon.com\n" .
                         "x-amz-date:$amz_date\n" .
                         "x-amz-target:com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems\n";
    error_log("Canonical Headers: $canonical_headers");
    $signed_headers = "content-encoding;content-type;host;x-amz-date;x-amz-target";
    $canonical_request = "POST\n$canonical_uri\n$canonical_querystring\n$canonical_headers\n$signed_headers\n$content_hash";
    error_log("Canonical Request: $canonical_request");
    $canonical_request_hash = hash('sha256', $canonical_request);
    error_log("Canonical Request Hash: $canonical_request_hash");
    $string_to_sign = "AWS4-HMAC-SHA256\n$amz_date\n$credential_scope\n$canonical_request_hash";
    error_log("String to Sign: $string_to_sign");

    $kSecret = 'AWS4' . $secret_key;
    $kDate = hash_hmac('sha256', $date, $kSecret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $string_to_sign, $kSigning);
    error_log("Signature: $signature");

    $authorization_header = "AWS4-HMAC-SHA256 Credential=$access_key/$credential_scope, SignedHeaders=$signed_headers, Signature=$signature";
    error_log("Authorization Header: $authorization_header");

    $args = [
        'headers' => [
            'Content-Encoding' => 'amz-1.0',
            'Content-Type' => 'application/json; charset=utf-8',
            'Host' => "webservices.amazon.com",
            'X-Amz-Date' => $amz_date,
            'X-Amz-Target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems',
            'Authorization' => $authorization_header
        ],
        'body' => $payload,
        'timeout' => 30,
    ];

    $max_retries = 3;
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $response = wp_remote_post($endpoint, $args);
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        error_log("Amazon API Response for ASIN $asin (Attempt $attempt, HTTP Code: $http_code): " . ($body ? substr($body, 0, 500) : 'Empty'));

        if (!is_wp_error($response) && $http_code == 200 && $body) {
            $data = json_decode($body, true);
            if (isset($data['ItemsResult']['Items'][0])) {
                $item = $data['ItemsResult']['Items'][0];

                // Log full ItemInfo for debugging
                error_log("ItemInfo for ASIN $asin: " . print_r($item['ItemInfo'] ?? 'Not available', true));

                // Extract title
                $title = $item['ItemInfo']['Title']['DisplayValue'] ?? 'No Title';

                // Extract price
                $price = $item['Offers']['Listings'][0]['Price']['Amount'] ?? 
                         $item['Offers']['Summaries'][0]['LowestPrice']['Amount'] ?? 0.00;

                // Extract images (primary and variants, up to 5)
                $images = [];
                if (isset($item['Images']['Primary']['Large']['URL'])) {
                    $images[] = $item['Images']['Primary']['Large']['URL'];
                }
                if (isset($item['Images']['Variants'])) {
                    foreach ($item['Images']['Variants'] as $variant) {
                        if (isset($variant['Large']['URL']) && count($images) < 5) {
                            $images[] = $variant['Large']['URL'];
                        }
                    }
                }
                $images = array_filter(array_unique($images));

                // Extract short description from features
                $short_desc = '';
                if (isset($item['ItemInfo']['Features']['DisplayValues'])) {
                    $features = $item['ItemInfo']['Features']['DisplayValues'];
                    $features_html = '<ul>';
                    foreach ($features as $feature) {
                        $features_html .= '<li>' . esc_html($feature) . '</li>';
                    }
                    $features_html .= '</ul>';
                    $short_desc = '<div class="about-this-item"><h3>About this item</h3>' . $features_html . '</div>';
                }

                // Extract product details for description
                $description_html = '<h3>Product Details</h3><table class="shop_attributes" cellspacing="0">';
                $attributes = [];
                if (isset($item['ItemInfo']['TechnicalInfo']['DisplayValues']) && isset($item['ItemInfo']['TechnicalInfo']['LabelDisplay'])) {
                    $labels = $item['ItemInfo']['TechnicalInfo']['LabelDisplay'];
                    $values = $item['ItemInfo']['TechnicalInfo']['DisplayValues'];
                    $max_count = max(count($labels), count($values));
                    for ($i = 0; $i < $max_count; $i++) {
                        $label = isset($labels[$i]) ? esc_html(trim($labels[$i])) : "Detail $i";
                        $value = isset($values[$i]) ? esc_html(trim($values[$i])) : '';
                        if ($label && $value && !in_array(strtolower($label), ['customer reviews', 'best sellers rank', 'amazon'])) {
                            $description_html .= "<tr><th>{$label}</th><td>{$value}</td></tr>";
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
                } else {
                    // Fallback to ProductInfo if TechnicalInfo is unavailable
                    if (isset($item['ItemInfo']['ProductInfo']['Features']['DisplayValues'])) {
                        $features = $item['ItemInfo']['ProductInfo']['Features']['DisplayValues'];
                        foreach ($features as $index => $value) {
                            $label = "Feature " . ($index + 1);
                            if ($value && !in_array(strtolower($label), ['customer reviews', 'best sellers rank', 'amazon'])) {
                                $description_html .= "<tr><th>{$label}</th><td>" . esc_html(trim($value)) . "</td></tr>";
                            }
                        }
                    }
                }

                // Add placeholder for rating and review count (not available in PAAPI 5)
                $rating = 'N/A';
                $review_count = 'N/A';
                $description_html .= "<tr><th>Rating</th><td>{$rating} / 5</td></tr>";
                $description_html .= "<tr><th>Reviews</th><td>{$review_count} reviews</td></tr>";
                $description_html .= '</table>';

                return [
                    'title' => $title,
                    'price' => $price,
                    'description' => $description_html,
                    'rating' => $rating,
                    'review_count' => $review_count,
                    'images' => $images,
                    'short_description' => $short_desc,
                    'attributes' => $attributes
                ];
            } else {
                error_log("No items found in API response for ASIN: $asin. Response: " . ($body ? substr($body, 0, 500) : 'Empty'));
            }
        } else {
            error_log("API request failed for ASIN: $asin. HTTP Code: $http_code, Response: " . ($body ? substr($body, 0, 500) : 'Empty'));
        }
        if ($http_code == 429) {
            sleep(5); // Longer delay for rate limit
        } else {
            sleep(2);
        }
    }

    $response_body = wp_remote_retrieve_body($response);
    error_log("API Failed $max_retries times for ASIN: $asin. Final Response: " . ($response_body ? substr($response_body, 0, 500) : 'Empty'));
    return [
        'error' => 'Failed to fetch product data after ' . $max_retries . ' attempts',
        'http_code' => $http_code,
        'response' => $response_body
    ];
}

// New function for SearchItems (campaign import)
function ats_search_products_from_amazon_api($keyword, $country, $category = 'All') {
    error_log("Starting Amazon API Search for Keyword: $keyword, Country: $country, Category: $category");
    
    $access_key = get_option('ats_amazon_access_key');
    $secret_key = trim(preg_replace('/\s+/', '', get_option('ats_amazon_secret_key')));
    $associate_tag = get_option('ats_amazon_associate_tag');
    $marketplace = "www.amazon.com"; // Force .com marketplace
    $region = "us-east-1";

    error_log("API Credentials: Access=" . ($access_key ? 'Set' : 'Not Set') . ", Secret=" . ($secret_key ? 'Set' : 'Not Set') . ", Tag=" . ($associate_tag ? 'Set' : 'Not Set'));

    if (!$access_key || !$secret_key || !$associate_tag) {
        error_log("Missing required API credentials for keyword: $keyword");
        return ['error' => 'Missing API credentials'];
    }

    $endpoint = "https://webservices.amazon.com/paapi5/searchitems";
    $service = 'ProductAdvertisingAPI';
    $request_parameters = [
        "Keywords" => $keyword,
        "Resources" => [
            "ItemInfo.Title",
            "Offers.Listings.Price",
            "Images.Primary.Large"
            ],
        "SearchIndex" => $category,
        "PartnerTag" => $associate_tag,
        "PartnerType" => "Associates",
        "Marketplace" => $marketplace,
        "Operation" => "SearchItems",
        "ItemCount" => 10
    ];

    $payload = json_encode($request_parameters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    error_log("Payload: $payload");
    $content_hash = hash('sha256', $payload);
    $amz_date = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');

    $credential_scope = "$date/$region/$service/aws4_request";
    $canonical_uri = '/paapi5/searchitems';
    $canonical_querystring = '';
    $canonical_headers = "content-encoding:amz-1.0\n" .
                         "content-type:application/json; charset=utf-8\n" .
                         "host:webservices.amazon.com\n" .
                         "x-amz-date:$amz_date\n" .
                         "x-amz-target:com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems\n";
    $signed_headers = "content-encoding;content-type;host;x-amz-date;x-amz-target";
    $canonical_request = "POST\n$canonical_uri\n$canonical_querystring\n$canonical_headers\n$signed_headers\n$content_hash";
    $canonical_request_hash = hash('sha256', $canonical_request);
    $string_to_sign = "AWS4-HMAC-SHA256\n$amz_date\n$credential_scope\n$canonical_request_hash";

    $kSecret = 'AWS4' . $secret_key;
    $kDate = hash_hmac('sha256', $date, $kSecret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $string_to_sign, $kSigning);

    $authorization_header = "AWS4-HMAC-SHA256 Credential=$access_key/$credential_scope, SignedHeaders=$signed_headers, Signature=$signature";

    $args = [
        'headers' => [
            'Content-Encoding' => 'amz-1.0',
            'Content-Type' => 'application/json; charset=utf-8',
            'Host' => "webservices.amazon.com",
            'X-Amz-Date' => $amz_date,
            'X-Amz-Target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems',
            'Authorization' => $authorization_header
        ],
        'body' => $payload,
        'timeout' => 30,
    ];

    $max_retries = 3;
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        try {
            $response = wp_remote_post($endpoint, $args);
            $body = wp_remote_retrieve_body($response);
            $http_code = wp_remote_retrieve_response_code($response);
            error_log("Amazon API Response for keyword $keyword (Attempt $attempt, HTTP Code: $http_code): " . ($body ? substr($body, 0, 500) : 'Empty'));

            if (is_wp_error($response)) {
                error_log("Amazon API Error for keyword $keyword: " . $response->get_error_message());
                if ($attempt < $max_retries) {
                    sleep($http_code == 429 ? 5 : 2);
                    continue;
                }
                return ['error' => 'WP Error: ' . $response->get_error_message(), 'response' => $body];
            }

            if ($http_code == 200 && $body) {
                $data = json_decode($body, true);
                error_log("Amazon API Parsed Response: " . print_r($data, true));
                if (isset($data['SearchResult']['Items'])) {
                    // Return just ASINs (array of ASIN strings)
                    $asins = [];
                if (!empty($data['SearchResult']['Items'])) {
                    foreach ($data['SearchResult']['Items'] as $item) {
                        if (!empty($item['ASIN'])) {
                            $asins[] = $item['ASIN'];
                        }
                    }
                }
                    return $asins;

                } else {
                    error_log("No search results found for keyword: $keyword. Response: " . ($body ? substr($body, 0, 500) : 'Empty'));
                }
            } else {
                error_log("API request failed for keyword: $keyword. HTTP Code: $http_code, Response: " . ($body ? substr($body, 0, 500) : 'Empty'));
            }

            if ($http_code == 429) {
                sleep(5);
            } else {
                sleep(2);
            }
        } catch (Exception $e) {
            error_log("Amazon API Exception for keyword $keyword: " . $e->getMessage());
            if ($attempt < $max_retries) {
                sleep(2);
                continue;
            }
            return ['error' => 'Exception: ' . $e->getMessage(), 'response' => ''];
        }
    }

    error_log("API Failed $max_retries times for keyword: $keyword. Final Response: " . ($body ? substr($body, 0, 500) : 'Empty'));
    return [
        'error' => 'Failed to fetch search results after ' . $max_retries . ' attempts',
        'http_code' => $http_code,
        'response' => $body
    ];
}
add_action('init', function () {
    if (isset($_GET['run_cron']) && $_GET['run_cron'] === '1') {
        ats_run_campaigns_cron_job();
        echo 'Cron job manually triggered.';
        exit;
    }
});
