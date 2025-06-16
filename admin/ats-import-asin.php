<?php
if (!defined('ABSPATH')) exit;

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

    // ✅ Set default or previously selected country (default: com)
    $selected_country = isset($_POST['ats_asin_country']) ? sanitize_text_field($_POST['ats_asin_country']) : 'com';
    ?>
    <div class="wrap">
        <h1>Import Amazon Product by ASIN</h1>
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
            </table>
            <?php
            // ✅ Security nonce field
            wp_nonce_field('ats_asin_import_nonce', 'ats_asin_import_nonce_field');
            submit_button('Import Product');
            ?>
        </form>

        <?php
        // ✅ Handle submission
        if (
            !empty($_POST['ats_asin']) &&
            isset($_POST['ats_asin_import_nonce_field']) &&
            wp_verify_nonce($_POST['ats_asin_import_nonce_field'], 'ats_asin_import_nonce')
        ) {
            $asin = sanitize_text_field($_POST['ats_asin']);
            $country = sanitize_text_field($_POST['ats_asin_country']);

            require_once plugin_dir_path(__FILE__) . '../includes/ats-fetch-product.php';
            if (!function_exists('ats_log_product')) {
                require_once plugin_dir_path(__FILE__) . '../includes/ats-utils.php';
            }

            // ✅ Fetch and log
            $product_id = ats_fetch_amazon_product($asin, $country);
            if ($product_id) {
                ats_log_product($product_id, $asin);
            }
        }
        ?>
    </div>
    <?php
}
