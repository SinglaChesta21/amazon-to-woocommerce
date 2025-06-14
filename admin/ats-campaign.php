<?php
if (!defined('ABSPATH')) exit;

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
            'rate'     => min(max((int) $_POST['ats_rate'], 1), 10),
            'active'   => true,
            'created'  => current_time('mysql')
        ];
        update_option('ats_campaigns', $campaigns);
        echo "<div class='notice notice-success'><p>✅ Campaign created and started!</p></div>";
    }

    ?>
    <div class="wrap">
        <h1>Create Campaign</h1>
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
                    echo "<a href='?page=ats-campaign-import&stop_campaign=$index' class='button'>⏹ Stop</a> ";
                } else {
                    echo "<a href='?page=ats-campaign-import&start_campaign=$index' class='button button-primary'>▶ Start</a> ";
                }
                echo "<a href='?page=ats-campaign-import&view_log=$index' class='button'>📄 Log</a> ";
                echo "<a href='?page=ats-campaign-import&clear_log=$index' class='button'>🧹 Clear Log</a> ";
                echo "<a href='?page=ats-campaign-import&delete_campaign=$index' class='button' onclick='return confirm(\"Are you sure?\")'>🗑 Delete</a>";
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
