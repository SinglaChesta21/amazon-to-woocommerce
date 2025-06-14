<?php
if (!defined('ABSPATH')) exit;

function ats_render_asin_import_page() {
    ?>
    <div class="wrap">
        <h1>Import Amazon Product by ASIN</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">Enter ASIN</th>
                    <td><input type="text" name="ats_asin" required placeholder="e.g., B0XXXXXXX" /></td>
                </tr>
            </table>
            <?php
            // ✅ Nonce field for security
            wp_nonce_field('ats_asin_import_nonce', 'ats_asin_import_nonce_field');
            submit_button('Import Product');
            ?>
        </form>

        <?php
        if (
            !empty($_POST['ats_asin']) &&
            isset($_POST['ats_asin_import_nonce_field']) &&
            wp_verify_nonce($_POST['ats_asin_import_nonce_field'], 'ats_asin_import_nonce')
        ) {
            $asin = sanitize_text_field($_POST['ats_asin']);
            $country = get_option('ats_amazon_country', 'in');

            require_once plugin_dir_path(__FILE__) . '../includes/ats-fetch-product.php';
            if (!function_exists('ats_log_product')) {
                require_once plugin_dir_path(__FILE__) . '../includes/ats-utils.php';
            }

            // ✅ Fetch and log the imported product
            $product_id = ats_fetch_amazon_product($asin, $country);
            if ($product_id) {
                ats_log_product($product_id, $asin);
            }
        }
        ?>
    </div>
    <?php
}
