<?php
if (!defined('ABSPATH')) exit;
ats_render_imported_products_page();



function ats_render_imported_products_page() {
    echo '<div class="wrap">';
    echo '<h1><i class="fas fa-boxes"></i> Imported Products</h1>';

    // Update price button
    if (isset($_POST['ats_update_prices'])) {
        ats_bulk_price_update();
        echo '<div class="notice notice-success is-dismissible"><p>✅ Prices updated successfully.</p></div>';
    }

    // Clear logs
    if (isset($_POST['ats_clear_logs'])) {
        delete_option('ats_imported_products_log');
        echo '<div class="notice notice-warning is-dismissible"><p>🗑️ All logs cleared.</p></div>';
    }

    $logs = get_option('ats_imported_products_log', []);
    if (!is_array($logs)) $logs = [];

    // Sort by latest first
    $logs = array_reverse($logs);

    echo '<form method="post" style="margin-bottom:15px;">
        <input type="submit" name="ats_update_prices" class="button button-primary" value="Update Prices" />
        <input type="submit" name="ats_clear_logs" class="button button-secondary" value="Clear Logs" onclick="return confirm(\'Are you sure?\')" />
    </form>';

    if (empty($logs)) {
        echo '<p>No imported products yet.</p>';
        echo '</div>';
        return;
    }

    echo '<style>
        .ats-products-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        .ats-products-table th, .ats-products-table td { border: 1px solid #ddd; padding: 10px; vertical-align: middle; }
        .ats-products-table th { background-color: #f8f9fa; text-align: left; }
        .ats-products-table img { width: 60px; height: auto; border-radius: 5px; }
        .ats-action-btns a { margin-right: 8px; text-decoration: none; }
        .ats-action-btns i { font-size: 15px; color: #444; transition: 0.3s; }
        .ats-action-btns i:hover { color: #0073aa; }
        .ats-status-published { color: green; font-weight: bold; }
        .ats-status-draft { color: orange; font-weight: bold; }
        .ats-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .ats-header .total-count { font-size: 16px; font-weight: bold; }
        .ats-search-box input { padding: 6px; width: 250px; }
    </style>';

    echo '<div class="ats-header">';
    echo '<div class="total-count">Total Products: ' . count($logs) . '</div>';
    echo '<div class="ats-search-box"><input type="text" placeholder="Search title..." onkeyup="atsFilterTable(this)" /></div>';
    echo '</div>';

    echo '<table class="ats-products-table" id="ats-product-table">';
    echo '<thead><tr>
        <th>ID</th>
        <th>Image</th>
        <th>Title</th>
        <th>Price</th>
        <th>Date Imported</th>
        <th>Last Synced</th>
        <th>Status</th>
        <th>Action</th>
    </tr></thead><tbody>';

    foreach ($logs as $log) {
        $product_id = $log['product_id'];
        $product = wc_get_product($product_id);
        if (!$product) continue;

        $image = get_the_post_thumbnail_url($product_id, 'thumbnail');
        $image_tag = $image ? "<img src='$image'>" : '-';
        $price = wc_price($product->get_price());
        $title = esc_html($product->get_name());
        $imported_on = date('F j, Y', strtotime($log['imported_on']));
        $synced = human_time_diff(get_post_modified_time('U', false, $product_id), current_time('timestamp')) . ' ago';
        $status_raw = $product->get_status();
        $status = $status_raw === 'publish' ? '<span class="ats-status-published">Published</span>' : '<span class="ats-status-draft">' . ucfirst($status_raw) . '</span>';

        $product_url = $product->get_product_url();
        $permalink = get_permalink($product_id);
        $edit_link = get_edit_post_link($product_id);
        $delete_link = get_delete_post_link($product_id, '', true);

        echo "<tr>
            <td>$product_id</td>
            <td>$image_tag</td>
            <td>$title</td>
            <td>$price</td>
            <td>$imported_on</td>
            <td>$synced</td>
            <td>$status</td>
            <td class='ats-action-btns'>
                <a href='$permalink' target='_blank' title='View'><i class='fas fa-eye'></i></a>
                <a href='$product_url' target='_blank' title='Amazon'><i class='fab fa-amazon'></i></a>
                <a href='$edit_link' title='Edit'><i class='fas fa-edit'></i></a>
                <a href='$delete_link' onclick='return confirm(\"Are you sure?\")' title='Delete'><i class='fas fa-trash-alt'></i></a>
            </td>
        </tr>";
    }

    echo '</tbody></table>';
    echo '</div>';

    echo '<script>
        function atsFilterTable(input) {
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll("#ats-product-table tbody tr");

            rows.forEach(row => {
                const titleCell = row.querySelector("td:nth-child(3)");
                if (titleCell) {
                    const text = titleCell.textContent || titleCell.innerText;
                    row.style.display = text.toLowerCase().includes(filter) ? "" : "none";
                }
            });
        }
    </script>';
}
