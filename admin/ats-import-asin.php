<?php
if (!defined('ABSPATH')) exit;
ats_render_asin_import_page();

function ats_render_asin_import_page() {
    $countries = [
        'in' => '🇮🇳 India (.in)',
        'com' => '🇺🇸 USA (.com)',
        'co.uk' => '🇬🇧 UK (.co.uk)',
        'ca' => '🇨🇦 Canada (.ca)',
        'de' => '🇩🇪 Germany (.de)',
        'fr' => '🇫🇷 France (.fr)',
        'it' => '🇮🇹 Italy (.it)',
        'es' => '🇪🇸 Spain (.es)',
        'com.mx' => '🇲🇽 Mexico (.com.mx)',
        'co.jp' => '🇯🇵 Japan (.co.jp)',
    ];

    $selected_country = isset($_POST['ats_asin_country']) ? sanitize_text_field($_POST['ats_asin_country']) : 'com';

    // ✅ Fetch WooCommerce product categories
    $woo_categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);
    ?>
    <div class="wrap">
        <h1>📦 Import Amazon Product by ASIN</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">Enter ASIN</th>
                    <td>
                        <input type="text" name="ats_asin" required placeholder="e.g., B0XXXXXXX" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Select Country</th>
                    <td>
                        <select name="ats_asin_country" required>
                            <?php foreach ($countries as $code => $label): ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($selected_country, $code); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Select WooCommerce Category</th>
                    <td>
                        <select name="ats_asin_category" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($woo_categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>">
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php
            wp_nonce_field('ats_asin_import_nonce', 'ats_asin_import_nonce_field');
            submit_button('Import Product');
            ?>
        </form>

        <?php
        // ✅ Handle submission
        if (
            !empty($_POST['ats_asin']) &&
            !empty($_POST['ats_asin_category']) &&
            isset($_POST['ats_asin_import_nonce_field']) &&
            wp_verify_nonce($_POST['ats_asin_import_nonce_field'], 'ats_asin_import_nonce')
        ) {
            $asin = sanitize_text_field($_POST['ats_asin']);
            $country = sanitize_text_field($_POST['ats_asin_country']);
            $category_id = intval($_POST['ats_asin_category']);

            require_once plugin_dir_path(__FILE__) . '../includes/ats-fetch-product.php';
            if (!function_exists('ats_log_product')) {
                require_once plugin_dir_path(__FILE__) . '../includes/ats-utils.php';
            }

            $product_id = ats_fetch_amazon_product($asin, $country, $category_id);

            if ($product_id) {
                wp_set_object_terms($product_id, [$category_id], 'product_cat');
                ats_log_product($product_id, $asin);
                echo "<div class='updated'><p>✅ Product imported and assigned to selected category.</p></div>";
            } else {
                echo "<div class='error'><p>❌ Failed to import product. Please check the ASIN or API response.</p></div>";
            }
        }
        ?>
    </div>
    <?php
}
