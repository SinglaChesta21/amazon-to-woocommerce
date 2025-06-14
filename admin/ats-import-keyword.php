<?php
if (!defined('ABSPATH')) exit;

function ats_render_keyword_import_page() {
    $categories = [
        'aps' => 'All Categories',
        'alexaskills' => 'Alexa Skills',
        'amazondevices' => 'Amazon Devices',
        'amazonfashion' => 'Amazon Fashion',
        'amazonfresh' => 'Amazon Fresh',
        'amazonpharmacy' => 'Amazon Pharmacy',
        'appliances' => 'Appliances',
        'appsandgames' => 'Apps & Games',
        'audiobooks' => 'Audible Audiobooks',
        'baby' => 'Baby',
        'beauty' => 'Beauty',
        'books' => 'Books',
        'carandmotorbike' => 'Car & Motorbike',
        'clothindandaccesories' => 'Clothing & Accessories',
        'collectibles' => 'Collectibles',
        'computersandaccesories' => 'Computers & Accessories',
        'deals' => 'Deals',
        'electronics' => 'Electronics',
        'furniture' => 'Furniture',
        'gardenandoutdoor' => 'Garden & Outdoor',
        'giftcards' => 'Gift Cards',
        'groceryandgourmetfood' => 'Grocery & Gourmet Food',
        'healthandpersonalcare' => 'Health & Personal Care',
        'homeandkitchen' => 'Home & Kitchen',
        'industrialandscientific' => 'Industrial & Scientific',
        'jewelry' => 'Jewelry',
        'kindlestore' => 'Kindle Store',
        'luggageandbags' => 'Luggage & Bags',
        'luxurybeauty' => 'Luxury Beauty',
        'moviesandtv' => 'Movies & TV Shows',
        'musicalinstruments' => 'Musical Instruments',
        'officeproducts' => 'Office Products',
        'petssupplies' => 'Pet Supplies',
        'shoesandhandbags' => 'Shoes & Handbags',
        'software' => 'Software',
        'sportsfitnessandoutdoors' => 'Sports, Fitness & Outdoors',
        'toolsandhomeimprovement' => 'Tools & Home Improvement',
        'toysandgames' => 'Toys & Games',
        'videogames' => 'Video Games',
        'watches' => 'Watches'

    ];

    $country = get_option('ats_amazon_country', 'in');
    $api_key = '90268f5fcbdd46de8d88db4489589048';

    // 🔄 Clear previous results
    if (!empty($_POST['ats_clear_search'])) {
        delete_transient('ats_keyword_html');
        delete_transient('ats_keyword_asins');
        delete_transient('ats_keyword_images');
        delete_transient('ats_keyword_keyword');
        delete_transient('ats_keyword_category');
        delete_transient('ats_keyword_imported');
        echo "<div class='notice notice-info'><p>🔄 Results cleared.</p></div>";
    }

    ?>
    <div class="wrap">
        <h1>Import Amazon Products by Keyword</h1>

        <!-- Search Form -->
        <form method="post">
            <?php wp_nonce_field('ats_import_keyword_nonce', 'ats_import_keyword_nonce_field'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Search Keyword</th>
                    <td><input type="text" name="ats_keyword" required placeholder="e.g., bluetooth headphones" /></td>
                </tr>
                <tr>
                    <th scope="row">Amazon Category</th>
                    <td>
                        <select name="ats_category">
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Search Amazon'); ?>
        </form>

        <!-- Clear Button -->
        <form method="post" style="margin-top:-15px;">
            <input type="hidden" name="ats_clear_search" value="1" />
            <?php submit_button('🧹 Clear Results', 'secondary'); ?>
        </form>

        <form method="post">
        <?php
        // ✅ New Search
        if (!empty($_POST['ats_keyword']) && empty($_POST['ats_keyword_import'])) {
            $keyword = sanitize_text_field($_POST['ats_keyword']);
            $category = sanitize_text_field($_POST['ats_category']);
            $search_url = "https://www.amazon.$country/s?k=" . urlencode($keyword) . "&i=" . urlencode($category);
            $scraper_url = "http://api.scraperapi.com?api_key=$api_key&render=true&country_code=$country&url=" . urlencode($search_url);
            $html = ats_get_remote_content($scraper_url);

            preg_match_all('/data-asin="(B0[0-9A-Z]{8})"/i', $html, $asin_matches);
            preg_match_all('/<img[^>]+class="[^"]*s-image[^"]*"[^>]+src="([^"]+)"/i', $html, $img_matches);

            set_transient('ats_keyword_html', $html, HOUR_IN_SECONDS);
            set_transient('ats_keyword_asins', array_unique($asin_matches[1]), HOUR_IN_SECONDS);
            set_transient('ats_keyword_images', array_map('trim', $img_matches[1]), HOUR_IN_SECONDS);
            set_transient('ats_keyword_keyword', $keyword, HOUR_IN_SECONDS);
            set_transient('ats_keyword_category', $category, HOUR_IN_SECONDS);
            set_transient('ats_keyword_imported', [], HOUR_IN_SECONDS);
        }

        // ✅ Import Selected Products
        if (
            !empty($_POST['ats_keyword_import']) &&
            !empty($_POST['selected_asins']) &&
            isset($_POST['ats_import_keyword_nonce_field']) &&
            wp_verify_nonce($_POST['ats_import_keyword_nonce_field'], 'ats_import_keyword_nonce')
        ) {
            require_once plugin_dir_path(__FILE__) . '../includes/ats-fetch-product.php';

            echo "<h2>Importing Products...</h2>";
            $imported = get_transient('ats_keyword_imported') ?: [];

            foreach ($_POST['selected_asins'] as $asin) {
                $clean = sanitize_text_field($asin);
                $product_id = ats_fetch_amazon_product($clean, $country);
                if ($product_id) {
                    $imported[] = $clean; // only track ASIN if successfully imported
                }
            }

            $imported = array_unique($imported);
            set_transient('ats_keyword_imported', $imported, HOUR_IN_SECONDS);
        }

        // ✅ Show Products
        $asins    = get_transient('ats_keyword_asins');
        $images   = get_transient('ats_keyword_images');
        $keyword  = get_transient('ats_keyword_keyword');
        $category = get_transient('ats_keyword_category');
        $imported = get_transient('ats_keyword_imported') ?: [];

        if (!empty($asins)) {
            echo "<h2>Search Results for '<em>" . esc_html($keyword) . "</em>'</h2>";
            echo "<table class='widefat'><thead><tr><th>✔</th><th>ASIN</th><th>Thumbnail</th><th>Status</th><th>Preview</th></tr></thead><tbody>";

            for ($i = 0; $i < min(10, count($asins)); $i++) {
                $asin = $asins[$i];
                $img  = isset($images[$i]) ? esc_url($images[$i]) : '';
                $link = "https://www.amazon.$country/dp/$asin";
                $is_imported = in_array($asin, $imported);

                echo "<tr>";
                echo "<td>";
                if (!$is_imported) {
                    echo "<input type='checkbox' name='selected_asins[]' value='$asin'>";
                } else {
                    echo "—";
                }
                echo "</td>";
                echo "<td><code>$asin</code></td>";
                echo "<td>" . ($img ? "<img src='$img' style='height:100px;border:1px solid #ccc;padding:2px;' />" : "<em>No Image</em>") . "</td>";
                echo "<td>" . ($is_imported ? "✅ Imported" : "Not Imported") . "</td>";
                echo "<td><a href='$link' target='_blank'>View</a></td>";
                echo "</tr>";
            }

            echo "</tbody></table><br>";
            echo "<input type='hidden' name='ats_keyword_import' value='1'>";
            wp_nonce_field('ats_import_keyword_nonce', 'ats_import_keyword_nonce_field');
            submit_button('Import Selected Products');
        }
        ?>
        </form>
    </div>
    <?php
}
