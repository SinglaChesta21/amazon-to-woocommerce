<?php
if (!defined('ABSPATH')) exit;
function ats_render_imported_products_page() {
    $logs = get_option('ats_imported_products_log', []);
    echo "<div class='wrap'><h1>Imported Products</h1>";

    // ✅ Buttons for Update + Clear Logs
    echo "<form method='post' style='display:flex; gap:10px;'>";

    submit_button('🔄 Update All Prices', 'primary', 'update_prices', false);
    submit_button('🗑️ Clear Logs', 'delete', 'clear_logs', false);

    echo "</form><br>";

    // ✅ Product Table
    echo "<table class='widefat'><thead><tr><th>Product</th><th>ASIN</th><th>Price</th><th>Status</th><th>Imported On</th></tr></thead><tbody>";

    foreach ($logs as $entry) {
        $product = wc_get_product($entry['product_id']);
        if (!$product) continue;
        $price = $product->get_price();
        $status = $product->get_stock_status();

        echo "<tr>
            <td>" . esc_html($product->get_name()) . "</td>
            <td>" . esc_html($entry['asin']) . "</td>
            <td>₹" . esc_html($price) . "</td>
            <td>" . esc_html($status) . "</td>
            <td>" . esc_html($entry['imported_on']) . "</td>
        </tr>";
    }

    echo "</tbody></table></div>";

    // ✅ Handle Price Update
    if (!empty($_POST['update_prices'])) {
        ats_bulk_price_update();
        echo "<div class='notice notice-success'><p>✅ Price update completed.</p></div>";
    }

    // ✅ Handle Log Clear
    if (!empty($_POST['clear_logs'])) {
        delete_option('ats_imported_products_log');
        echo "<div class='notice notice-warning'><p>🗑️ All import logs cleared.</p></div>";
    }
}
