<?php
if (!defined('ABSPATH')) exit;

$product_count = wp_count_posts('product')->publish;

$args = [
    'post_type'      => 'product',
    'posts_per_page' => 1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'fields'         => 'ids'
];
$latest_post = get_posts($args);
$last_imported_time = $latest_post ? get_the_date('U', $latest_post[0]) : null;
$last_imported_diff = $last_imported_time ? human_time_diff($last_imported_time, current_time('timestamp')) : 'N/A';

$last_synced_time = get_option('ats_last_sync_time');
$last_synced_diff = $last_synced_time ? human_time_diff($last_synced_time, current_time('timestamp')) : 'N/A';
?>

<style>
    body, input, select, textarea, button {
    font-family: 'Golos Text', sans-serif !important;
}
/* Page Title */
h1 {
    font-size: 26px;
    font-weight: 600;
    margin-bottom: 20px;
}
/* Section Headings */
h2 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 20px;
}

/* Table Headings */
.form-table th {
    font-size: 15px;
    font-weight: 500;
    padding: 12px 10px;
}

/* Table Data */
.form-table td {
    padding: 12px 10px;
}

/* Input Fields */
.form-table input[type="text"] {
    width: 100%;
    max-width: 400px;
    padding: 8px 12px;
    font-size: 14px;
    font-weight: 400;
    border-radius: 4px;
    border: 1px solid #ccc;
}

/* Checkbox */
.form-table input[type="checkbox"] {
    transform: scale(1.2);
    margin-right: 8px;
}

/* Label Text */
.form-table label {
    font-size: 14px;
    font-weight: 400;
    vertical-align: middle;
}

/* Primary Button */
.button-primary {
    background-color: #2e8ba6; /* Your theme color */
    border-color: #2e8ba6;
    font-size: 14px;
    font-weight: 500;
    padding: 10px 20px;
    border-radius: 4px;
    text-transform: uppercase;
}

/* Notification Box */
.updated.notice {
    border-left-color: #46b450;
}
.updated.notice p {
    font-weight: 500;
}
.dashboard-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    flex: 1;
    transition: 0.3s ease;
    border: 1px solid #e0e0e0;
}
.dashboard-card:hover {
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
}
.dashboard-card .icon-box {
    background: #F4F6F8;
    padding: 15px;
    border-radius: 8px;
    font-size: 24px;
    color: #2E8BA6;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 48px;
    height: 48px;
}
.dashboard-metrics {
    display: flex;
    gap: 20px;
    margin-top: 30px;
    margin-bottom: 20px;
}
.metric-text h4 {
    margin: 0;
    font-size: 16px;
    color: #666;
}
.metric-text p {
    margin: 5px 0 0;
    font-size: 22px;
    font-weight: bold;
    color: #333;
}
.recent-title {
    font-size: 18px;
    font-weight: 600;
    margin: 30px 0 10px;
    color: #2E8BA6;
}
.product-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}
.product-card {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 8px;
    width: 23%;
    padding: 10px;
    text-align: center;
    transition: 0.3s ease;
    border: 1px solid #e0e0e0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
.product-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.06);
}
.product-card img {
    width: 100%;
    height: 180px;
    object-fit: contain;
    border-radius: 6px;
    background: #fafafa;
}
.product-card p {
    margin: 10px 0 0;
    font-size: 14px;
    color: #333;
    font-weight: 500;
}
@media (max-width: 768px) {
    .dashboard-metrics { flex-direction: column; }
    .product-card { width: 100%; }
}
</style>

<div class="wrap">
    <h1><i class="fas fa-chart-line"></i> Dashboard</h1>

    <div class="dashboard-metrics">
        <div class="dashboard-card">
            <div class="icon-box"><i class="fas fa-box"></i></div>
            <div class="metric-text">
                <h4>Total Imported Products</h4>
                <p><?php echo esc_html($product_count); ?></p>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="icon-box"><i class="fas fa-download"></i></div>
            <div class="metric-text">
                <h4>Product Last Imported</h4>
                <p><?php echo esc_html($last_imported_diff); ?> ago</p>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="icon-box"><i class="fas fa-sync"></i></div>
            <div class="metric-text">
                <h4>Product Last Synced</h4>
                <p><?php echo esc_html($last_synced_diff); ?> ago</p>
            </div>
        </div>
    </div>

    <div class="recent-title">Recently Imported Products</div>
    <div class="product-grid">
        <?php
        $recent_products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => 6,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        foreach ($recent_products as $product) {
            $product_obj = wc_get_product($product->ID);
            $thumbnail = get_the_post_thumbnail_url($product->ID, 'medium');
            $title = $product_obj->get_name();
            $link = get_edit_post_link($product->ID);
            ?>
            <div class="product-card">
                <a href="<?php echo esc_url($link); ?>">
                    <img src="<?php echo esc_url($thumbnail ?: wc_placeholder_img_src()); ?>" alt="">
                    <p><?php echo esc_html(wp_trim_words($title, 10)); ?></p>
                </a>
            </div>
        <?php } ?>
    </div>
</div>
