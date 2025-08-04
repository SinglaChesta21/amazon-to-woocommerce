<?php
if (!defined('ABSPATH')) exit;
ats_render_campaign_import_page();

function ats_render_campaign_import_page() {
    if (!wp_next_scheduled('ats_campaigns_cron')) {
    echo "<div class='notice notice-error'><p>❌ Campaign cron is NOT scheduled.</p></div>";
} else {
    echo "<div class='notice notice-success'><p>✅ Campaign cron is scheduled.</p></div>";
}

    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }

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

    // Handle actions with validation
    if (isset($_GET['stop_campaign'])) {
        $index = intval($_GET['stop_campaign']);
        $campaigns = get_option('ats_campaigns', []);
        if (isset($campaigns[$index])) {
            $campaigns[$index]['active'] = false;
            update_option('ats_campaigns', $campaigns);
            echo "<div class='notice notice-warning'><p>⏹️ Campaign stopped.</p></div>";
        }
    }

    if (isset($_GET['start_campaign'])) {
        $index = intval($_GET['start_campaign']);
        $campaigns = get_option('ats_campaigns', []);
        if (isset($campaigns[$index])) {
            $campaigns[$index]['active'] = true;
            update_option('ats_campaigns', $campaigns);
            echo "<div class='notice notice-success'><p>▶ Campaign restarted.</p></div>";
        }
    }

    if (isset($_GET['delete_campaign'])) {
        $index = intval($_GET['delete_campaign']);
        $campaigns = get_option('ats_campaigns', []);
        if (isset($campaigns[$index])) {
            unset($campaigns[$index]);
            update_option('ats_campaigns', array_values($campaigns));
            delete_option("ats_campaign_log_$index");
            delete_option("ats_campaign_imported_$index");
            echo "<div class='notice notice-error'><p>🗑️ Campaign deleted.</p></div>";
        }
    }

    if (isset($_GET['clear_log'])) {
        $index = intval($_GET['clear_log']);
        delete_option("ats_campaign_log_$index");
        echo "<div class='notice notice-info'><p>🧹 Logs cleared for campaign #$index.</p></div>";
    }

    // Handle form submission securely
    if (!empty($_POST['ats_campaign_submit']) && isset($_POST['ats_campaign_nonce']) && wp_verify_nonce($_POST['ats_campaign_nonce'], 'ats_campaign_create')) {
        $campaigns = get_option('ats_campaigns', []);
        $campaigns[] = [
            'name'     => sanitize_text_field($_POST['ats_campaign_name']),
            'keyword'  => sanitize_text_field($_POST['ats_keyword']),
            'category' => sanitize_text_field($_POST['ats_category']),
            'wc_category' => intval($_POST['ats_wc_category']),
            'rate'     => min(max((int) $_POST['ats_rate'], 1), 10),
            'active'   => true,
            'created'  => current_time('mysql')
        ];
        update_option('ats_campaigns', $campaigns);
        echo "<div class='notice notice-success'><p>✅ Campaign created and started!</p></div>";
    }

    ?>
    <div class="wrap">
        <h1  style="font-size: 26px; font-weight: 600;margin-bottom: 20px;">Create Campaign</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Campaign Name</th>
                    <td><input type="text" name="ats_campaign_name" required></td>
                </tr>
                <tr>
                    <th>Keyword</th>
                    <td><input type="text" name="ats_keyword" required></td>
                </tr>
                <tr>
                    <th>Category</th>
                    <td>
                        <select name="ats_category">
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Import Rate (per hour)</th>
                    <td>
                        <select name="ats_rate">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>WooCommerce Category</th>
                    <td>
                        <select name="ats_wc_category" required>
                            <option value="">Select WooCommerce Category</option>
                            <?php
                            $wc_categories = get_terms([
                                'taxonomy' => 'product_cat',
                                'hide_empty' => false,
                            ]);
                            foreach ($wc_categories as $wc_cat) {
                                echo '<option value="' . esc_attr($wc_cat->term_id) . '">' . esc_html($wc_cat->name) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>

            </table>
            <?php wp_nonce_field('ats_campaign_create', 'ats_campaign_nonce'); ?>
            <input type="hidden" name="ats_campaign_submit" value="1">
            <?php submit_button('Start Campaign'); ?>
        </form>

        <hr>
        <h2>Current Campaigns</h2>
        <?php
        $campaigns = get_option('ats_campaigns', []);
        if (!empty($campaigns)) {
            echo "<table class='widefat'><thead><tr><th>Name</th><th>Keyword</th><th>Category</th><th>Rate</th><th>Status</th><th>Actions</th></tr></thead><tbody>";
            foreach ($campaigns as $index => $c) {
                echo "<tr>";
                echo "<td>" . esc_html($c['name']) . "</td>";
                echo "<td>" . esc_html($c['keyword']) . "</td>";
                echo "<td>" . esc_html($c['category']) . "</td>";
                echo "<td>" . intval($c['rate']) . " / hr</td>";
                echo "<td>" . ($c['active'] ? "<span style='color:green;'>Running</span>" : "<span style='color:red;'>Stopped</span>") . "</td>";
                echo "<td>";
                if ($c['active']) {
                    echo "<a href='?page=amazon-sync&tab=import&method=campaign&stop_campaign=$index' class='button'>⏹ Stop</a> ";
                } else {
                    echo "<a href='?page=amazon-sync&tab=import&method=campaign&start_campaign=$index' class='button button-primary'>▶ Start</a> ";
                }
                echo "<a href='?page=amazon-sync&tab=import&method=campaign&view_log=$index' class='button'>📄 Log</a> ";
                echo "<a href='?page=amazon-sync&tab=import&method=campaign&clear_log=$index' class='button'>🧹 Clear Log</a> ";
                echo "<a href='?page=amazon-sync&tab=import&method=campaign&delete_campaign=$index' class='button' onclick='return confirm(\"Are you sure?\")'>🗑 Delete</a>";
                echo "</td></tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>No campaigns yet.</p>";
        }

        if (isset($_GET['view_log'])) {
            $log_index = intval($_GET['view_log']);
            $log = get_option("ats_campaign_log_$log_index", []);
            echo "<hr><h2>Log for Campaign #" . ($log_index + 1) . "</h2>";
            if (!empty($log)) {
                echo "<table class='widefat'><thead><tr><th>Time</th><th>ASIN</th></tr></thead><tbody>";
                foreach (array_reverse($log) as $entry) {
                    $time = esc_html($entry['time']);
                    $asin = esc_html($entry['asin']);
                    echo "<tr><td>$time</td><td>$asin</td></tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<p>No logs yet.</p>";
            }
        }
        ?>
    </div>
    <?php
}

// Trigger campaigns via URL
add_action('init', function () {
    if (isset($_GET['run_cron']) && $_GET['run_cron'] === '1') {
        ats_run_campaigns_cron_job();
        echo 'Cron job manually triggered.';
        exit;
    }
});

?>
<style>
    /* General label styling */
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


