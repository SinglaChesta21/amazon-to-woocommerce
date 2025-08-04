<?php
if (!defined('ABSPATH')) exit;

ats_render_keyword_import_page();

function ats_render_keyword_import_page() {
    $categories = [
    'All' => 'All Departments',
    'AmazonVideo' => 'Prime Video',
    'Apparel' => 'Clothing & Accessories',
    'Appliances' => 'Appliances',
    'ArtsAndCrafts' => 'Arts, Crafts & Sewing',
    'Automotive' => 'Automotive Parts & Accessories',
    'Baby' => 'Baby',
    'Beauty' => 'Beauty & Personal Care',
    'Books' => 'Books',
    'Classical' => 'Classical',
    'Collectibles' => 'Collectibles & Fine Art',
    'Computers' => 'Computers',
    'DigitalMusic' => 'Digital Music',
    'DigitalEducationalResources' => 'Digital Educational Resources',
    'Electronics' => 'Electronics',
    'EverythingElse' => 'Everything Else',
    'Fashion' => 'Clothing, Shoes & Jewelry',
    'FashionBaby' => 'Clothing, Shoes & Jewelry Baby',
    'FashionBoys' => 'Clothing, Shoes & Jewelry Boys',
    'FashionGirls' => 'Clothing, Shoes & Jewelry Girls',
    'FashionMen' => 'Clothing, Shoes & Jewelry Men',
    'FashionWomen' => 'Clothing, Shoes & Jewelry Women',
    'GardenAndOutdoor' => 'Garden & Outdoor',
    'GiftCards' => 'Gift Cards',
    'GroceryAndGourmetFood' => 'Grocery & Gourmet Food',
    'Handmade' => 'Handmade',
    'HealthPersonalCare' => 'Health, Household & Baby Care',
    'HomeAndKitchen' => 'Home & Kitchen',
    'Industrial' => 'Industrial & Scientific',
    'Jewelry' => 'Jewelry',
    'KindleStore' => 'Kindle Store',
    'LocalServices' => 'Home & Business Services',
    'Luggage' => 'Luggage & Travel Gear',
    'LuxuryBeauty' => 'Luxury Beauty',
    'Magazines' => 'Magazine Subscriptions',
    'MobileAndAccessories' => 'Cell Phones & Accessories',
    'MobileApps' => 'Apps & Games',
    'MoviesAndTV' => 'Movies & TV',
    'Music' => 'CDs & Vinyl',
    'MusicalInstruments' => 'Musical Instruments',
    'OfficeProducts' => 'Office Products',
    'PetSupplies' => 'Pet Supplies',
    'Photo' => 'Camera & Photo',
    'Shoes' => 'Shoes',
    'Software' => 'Software',
    'SportsAndOutdoors' => 'Sports & Outdoors',
    'ToolsAndHomeImprovement' => 'Tools & Home Improvement',
    'ToysAndGames' => 'Toys & Games',
    'VHS' => 'VHS',
    'VideoGames' => 'Video Games',
    'Watches' => 'Watches'
];


    $country = get_option('ats_amazon_country', 'in');
    $use_amazon_api = get_option('ats_use_amazon_api');
    $api_key = get_option('ats_amazon_api_key'); // Your real API key from settings

    if (!$use_amazon_api || empty($api_key)) {
        // Use fallback scraper key
    $api_key = '77f345657c94fb130865a9f21b63a935';
}

function ats_search_amazon_api($keyword, $country, $category) {
    $access_key = get_option('ats_amazon_access_key', '');
    $secret_key = get_option('ats_amazon_secret_key', '');
    $associate_tag = get_option('ats_amazon_associate_tag', '');

    if (empty($access_key) || empty($secret_key) || empty($associate_tag)) {
        error_log('❌ Missing Amazon API credentials for search.');
        return false;
    }

    $region = ['com' => 'us', 'in' => 'in', 'co.uk' => 'gb', 'de' => 'de', 'ca' => 'ca'][$country] ?? 'us';
    $host = "webservices.amazon.{$country}";
    $uri = "/paapi5/searchitems";
    $endpoint = "https://{$host}{$uri}";

    $params = [
        'Keywords' => $keyword,
        'SearchIndex' => $category,
        'Resources' => ['ItemInfo.Title', 'Offers.Listings.Price', 'Images.Primary.Large'],
        'PartnerTag' => $associate_tag,
        'PartnerType' => 'Associates',
        'Marketplace' => "www.amazon.{$country}",
        'ItemCount' => 10,
    ];

    $payload = json_encode($params, JSON_UNESCAPED_SLASHES);
    $amz_date = gmdate('Ymd\THis\Z');
    $date_stamp = gmdate('Ymd');
    $service = 'ProductAdvertisingAPI';
    $algorithm = 'AWS4-HMAC-SHA256';

    $canonical_uri = $uri;
    $canonical_headers = "content-encoding:amz-1.0\ncontent-type:application/json; charset=utf-8\nhost:$host\nx-amz-date:$amz_date\n";
    $signed_headers = 'content-encoding;content-type;host;x-amz-date';
    $payload_hash = hash('sha256', $payload);

    $canonical_request = "POST\n$canonical_uri\n\n$canonical_headers\n$signed_headers\n$payload_hash";
    $credential_scope = "$date_stamp/$region/$service/aws4_request";
    $string_to_sign = "$algorithm\n$amz_date\n$credential_scope\n" . hash('sha256', $canonical_request);

    function getSignatureKey($key, $dateStamp, $regionName, $serviceName) {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $kDate, $regionName, true);
        $kService = hash_hmac('sha256', $kRegion, $serviceName, true);
        $kSigning = hash_hmac('sha256', $kService, 'aws4_request', true);
        return $kSigning;
    }

    $signing_key = getSignatureKey($secret_key, $date_stamp, $region, $service);
    $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

    $authorization_header = "$algorithm Credential=$access_key/$credential_scope, SignedHeaders=$signed_headers, Signature=$signature";

    $headers = [
        'Content-Encoding: amz-1.0',
        'Content-Type: application/json; charset=utf-8',
        'Host: ' . $host,
        'X-Amz-Date: ' . $amz_date,
        'Authorization: ' . $authorization_header,
    ];

    $response = wp_remote_post($endpoint, ['headers' => $headers, 'body' => $payload, 'timeout' => 30]);
    if (is_wp_error($response)) {
        error_log('❌ Amazon API search failed: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['Errors'])) {
        error_log('❌ Amazon API Search Error: ' . print_r($data['Errors'], true));
        return false;
    }

    $asins = [];
    $images = [];
    $ratings = [];
    $reviews = [];
    foreach ($data['SearchResult']['Items'] ?? [] as $item) {
        $asins[] = $item['ASIN'];
        $images[] = $item['Images']['Primary']['Large']['URL'] ?? '';
        $ratings[] = 'N/A'; // PAAPI5 se ratings/reviews nahi milte, existing scraper logic se handle hoga
        $reviews[] = 'N/A';
    }
    return ['asins' => $asins, 'images' => $images, 'ratings' => $ratings, 'reviews' => $reviews];
}

    $asins    = get_transient('ats_keyword_asins') ?: [];
    $images   = get_transient('ats_keyword_images') ?: [];
    $keyword  = get_transient('ats_keyword_keyword');
    $category = get_transient('ats_keyword_category');
    $imported = get_transient('ats_keyword_imported') ?: [];
    $ratings = get_transient('ats_keyword_ratings') ?: [];
    $reviews = get_transient('ats_keyword_reviews') ?: [];

    $woo_categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);

    if (!empty($_POST['ats_clear_search'])) {
        delete_transient('ats_keyword_html');
        delete_transient('ats_keyword_asins');
        delete_transient('ats_keyword_images');
        delete_transient('ats_keyword_keyword');
        delete_transient('ats_keyword_category');
        delete_transient('ats_keyword_imported');
        delete_transient('ats_keyword_ratings');
        delete_transient('ats_keyword_reviews');
        echo "<div class='notice notice-info'><p>🔄 Results cleared.</p></div>";
    }

    if (!empty($_POST['ats_keyword']) && isset($_POST['ats_import_keyword_nonce_field']) &&
        wp_verify_nonce($_POST['ats_import_keyword_nonce_field'], 'ats_import_keyword_nonce')) {

            // 🔄 Clear old data before searching new
            delete_transient('ats_keyword_html');
            delete_transient('ats_keyword_asins');
            delete_transient('ats_keyword_images');
            delete_transient('ats_keyword_ratings');
            delete_transient('ats_keyword_reviews');
            delete_transient('ats_keyword_imported');

        $keyword = sanitize_text_field($_POST['ats_keyword']);
        $category = sanitize_text_field($_POST['ats_category']);

        $search_url = "https://www.amazon.$country/s?k=" . urlencode($keyword) . "&i=" . urlencode($category);
        $scraper_url = "http://api.scraperapi.com?api_key=$api_key&render=true&country_code=$country&url=" . urlencode($search_url);
        $html = ats_get_remote_content($scraper_url);
        if ($use_amazon_api && !empty($api_key) && $api_key === get_option('ats_amazon_api_key')) {
    echo "<div class='notice notice-info'><p style='color:blue;'>ℹ️ Fetched using <strong>Amazon API</strong></p></div>";
} else {
    echo "<div class='notice notice-warning'><p style='color:orange;'>⚠️ Fetched using <strong>Default Scraper API</strong></p></div>";
}


        $asins = [];
        $images = [];
        $ratings = [];
        $reviews = [];

preg_match_all('/<div[^>]+data-asin="(B0[0-9A-Z]{8})"[^>]*class="[^"]*s-result-item[^"]*"[^>]*>(.*?)<\/div><\/div><\/div>/is', $html, $blocks, PREG_SET_ORDER);

        foreach ($blocks as $block) {
            $asin = $block[1];
            $html_block = $block[2];

            if (!empty($asin)) {
                $asins[] = $asin;

                // Image
                if (preg_match('/<img[^>]+class="[^"]*s-image[^"]*"[^>]+(src|data-src)="([^"]+)"/i', $html_block, $img)) {
                    $images[] = trim($img[2]);
                } else {
                    $images[] = wc_placeholder_img_src();
                }

        // ✅ Rating
        if (preg_match('/class="a-icon-alt"[^>]*>([0-5](?:\.\d)?) out of 5 stars<\/span>/i', $html_block, $alt_rating)) {
            $rating = trim($alt_rating[1]);
        } elseif (preg_match('/aria-label="([0-5](?:\.\d)?) out of 5 stars"/i', $html_block, $aria_rating)) {
            $rating = trim($aria_rating[1]);
        } elseif (preg_match('/([0-5](?:\.\d)?) out of 5 stars/i', $html_block, $plain_rating)) {
            $rating = trim($plain_rating[1]);
        } else {
            $rating = 'N/A';
        }

        // ✅ Review count
        // ✅ Try different review count patterns
        if (preg_match('/([\d,]+)\s+(?:ratings?|reviews?)/i', $html_block, $match_reviews)) {
            $review_count = str_replace(',', '', $match_reviews[1]);
        } elseif (preg_match('/class="a-size-base".*?>([\d,]+)</i', $html_block, $alt_review)) {
            $review_count = str_replace(',', '', $alt_review[1]);
        } elseif (preg_match('/([0-9,]+)\s*out of 5 stars/i', $html_block, $inside_review)) {
            $review_count = str_replace(',', '', $inside_review[1]);
        } else {
            $review_count = 'N/A';
        }

        $ratings[] = $rating;
        $reviews[] = $review_count;


            }
        }
        

        set_transient('ats_keyword_html', $html, HOUR_IN_SECONDS);
        set_transient('ats_keyword_asins', $asins, HOUR_IN_SECONDS);
        set_transient('ats_keyword_images', $images, HOUR_IN_SECONDS);
        set_transient('ats_keyword_keyword', $keyword, HOUR_IN_SECONDS);
        set_transient('ats_keyword_category', $category, HOUR_IN_SECONDS);
        set_transient('ats_keyword_imported', [], HOUR_IN_SECONDS);
        set_transient('ats_keyword_ratings', $ratings, HOUR_IN_SECONDS);
        set_transient('ats_keyword_reviews', $reviews, HOUR_IN_SECONDS);

        wp_redirect(admin_url('admin.php?page=amazon-sync&tab=import&method=keyword'));
        exit;
    }

    if (!empty($_POST['ats_keyword_import']) && !empty($_POST['selected_asins']) &&
        isset($_POST['ats_import_keyword_nonce_field']) &&
        wp_verify_nonce($_POST['ats_import_keyword_nonce_field'], 'ats_import_keyword_nonce')) {

        require_once plugin_dir_path(__FILE__) . '../includes/ats-fetch-product.php';
        echo "<div class='updated'><p>⏳ Importing products...</p></div>";
        $selected_category_id = intval($_POST['woocommerce_category']);

        foreach ($_POST['selected_asins'] as $asin) {
            $clean = sanitize_text_field($asin);
            $product_id = ats_fetch_amazon_product($clean, $country, $selected_category_id);
            if ($product_id) {
                wp_set_object_terms($product_id, [$selected_category_id], 'product_cat');
                $imported[] = $clean;
            }
        }

        set_transient('ats_keyword_imported', array_unique($imported), HOUR_IN_SECONDS);
    }
?>

<div class="wrap">
    <h1 style="font-size: 26px; font-weight: 600;margin-bottom: 20px;">📦 Import Products by Keyword</h1>

    <form method="post">
        <input type="hidden" name="page" value="amazon-sync" />
        <input type="hidden" name="tab" value="import" />
        <input type="hidden" name="method" value="keyword" />
        <?php wp_nonce_field('ats_import_keyword_nonce', 'ats_import_keyword_nonce_field'); ?>
        <table class="form-table">
            <tr>
                <th>Keyword</th>
                <td><input type="text" name="ats_keyword" placeholder="e.g., bluetooth speaker" required /></td>
            </tr>
            <tr>
                <th>Category</th>
                <td>
                    <select name="ats_category">
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($category, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button('Search Amazon'); ?>
    </form>

    <form method="post" style="margin-top:-15px;">
        <input type="hidden" name="ats_clear_search" value="1" />
        <input type="hidden" name="page" value="amazon-sync" />
        <input type="hidden" name="tab" value="import" />
        <input type="hidden" name="method" value="keyword" />
        <?php submit_button('Clear Results', 'secondary', 'clear_button'); ?>
    </form>

    <?php if (!empty($asins)) : ?>
        <form method="post">
            <input type="hidden" name="ats_keyword_import" value="1" />
            <input type="hidden" name="page" value="amazon-sync" />
            <input type="hidden" name="tab" value="import" />
            <input type="hidden" name="method" value="keyword" />
            <?php wp_nonce_field('ats_import_keyword_nonce', 'ats_import_keyword_nonce_field'); ?>

            <h2>Results for "<em><?php echo esc_html($keyword); ?></em>"</h2>

            <p><strong>Select WooCommerce Category:</strong></p>
            <select name="woocommerce_category" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($woo_categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->term_id); ?>">
                        <?php echo esc_html($cat->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <table class="widefat">
                <thead>
                    <tr>
                        <th>✔</th>
                        <th>ASIN</th>
                        <th>Thumbnail</th>
                        <th>Rating</th>
                        <th>Reviews</th>
                        <th>Status</th>
                        <th>Preview</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $loop_limit = min(10, count($asins), count($images), count($ratings), count($reviews));
                for ($i = 0; $i < $loop_limit; $i++) :
                    $asin = $asins[$i];
                    $img_url = isset($images[$i]) && !empty($images[$i]) ? $images[$i] : wc_placeholder_img_src();
                ?>
                <tr>
                    <td><?php echo in_array($asin, $imported) ? '—' : "<input type='checkbox' name='selected_asins[]' value='$asin' />"; ?></td>
                    <td><code><?php echo esc_html($asin); ?></code></td>
                    <td><img src="<?php echo esc_url($img_url); ?>" height="80" /></td>
                    <td><?php echo esc_html($ratings[$i] ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($reviews[$i] ?? 'N/A'); ?></td>
                    <td><?php echo in_array($asin, $imported) ? '✅ Imported' : 'Not Imported'; ?></td>
                    <td><a href="https://www.amazon.<?php echo esc_attr($country); ?>/dp/<?php echo esc_attr($asin); ?>" target="_blank">View</a></td>
                </tr>
                <?php endfor; ?>
                </tbody>
            </table>
            <br>
            <?php submit_button('Import Selected Products'); ?>
        </form>
    <?php endif; ?>
</div>
<?php }?>
<style>

    #clear_button {
        margin-top: 10px;
        background-color: #f1f9fb;
        color: #2E8BA6;
        border: 1px solid #2E8BA6;
    }
    .form-table th{
    font-weight: 500;
    color: #333;
    font-family: "Golos Text", sans-serif;
    font-size: 16px;

}


    /* Input fields (text + select) styling */
.wrap input[type="text"],
.wrap select {
    font-family: "Golos Text", sans-serif !important;
    font-size: 14px;
    padding: 3px 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-sizing: border-box;
    width: 300px;
    max-width: 100%;
    height: 40px;
}

/* Match button height with input */
    .wrap form input.button {
    font-family: "Golos Text", sans-serif !important;
    font-weight: 600;
    font-size: 14px;
    height: 40px;
    line-height: 1;
    padding: 0px 20px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-top: 10px;
    margin-right: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.wrap input.button-primary {
    background-color: #2E8BA6;
    color: #fff;
    border: 1px solid #2E8BA6;
}
.wrap input.button-primary:hover {
    background-color: #256f88;
}

.wrap input.button-secondary {
    background-color: #fff;
    color: #2E8BA6;
    border: 1px solid #2E8BA6;
}
.wrap input.button-secondary:hover {
    background-color: #f1f9fb;
}


</style>


