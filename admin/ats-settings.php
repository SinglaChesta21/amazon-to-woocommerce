<?php
if (!defined('ABSPATH')) exit;
ats_render_settings_page();
function ats_render_settings_page() {
    $available_countries = [
        'in' => 'India (.in)',
        'com' => 'USA (.com)',
        'co.uk' => 'UK (.co.uk)',
        'ca' => 'Canada (.ca)',
        'de' => 'Germany (.de)'
    ];

    $affiliates = get_option('ats_affiliates_data', []);
    $selected_country = get_option('ats_amazon_country', 'in');

    // Save new or update affiliate ID
    if (isset($_POST['ats_affiliate_save'])) {
        $code = sanitize_text_field($_POST['ats_country_code']);
        $id = sanitize_text_field($_POST['ats_affiliate_id']);
        $affiliates[$code] = $id;
        update_option('ats_affiliates_data', $affiliates);
        echo '<div class="updated"><p>✅ Affiliate ID saved for ' . esc_html($code) . '.</p></div>';
    }

    // Set selected country
    if (isset($_POST['ats_select_country'])) {
        $selected_country = sanitize_text_field($_POST['selected_country']);
        update_option('ats_amazon_country', $selected_country);
        echo '<div class="updated"><p>🌐 Selected country set to ' . esc_html($selected_country) . '.</p></div>';
    }

    // Delete affiliate ID
    if (!empty($_GET['delete_affiliate'])) {
        $del_code = sanitize_text_field($_GET['delete_affiliate']);
        unset($affiliates[$del_code]);
        update_option('ats_affiliates_data', $affiliates);
        echo '<div class="notice notice-error"><p>🗑️ Affiliate ID deleted for ' . esc_html($del_code) . '.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1 style="font-size: 26px;font-weight: 600;margin-bottom: 20px;">Amazon Sync Settings</h1>

        <form method="post">
            <h2>Add or Update Affiliate ID</h2>
            <table class="form-table">
                <tr>
                    <th>Country</th>
                    <td>
                        <select name="ats_country_code" required>
                            <?php foreach ($available_countries as $code => $label): ?>
                                <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Affiliate ID</th>
                    <td>
                        <input type="text" name="ats_affiliate_id" placeholder="e.g., yourid-21" required />
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Affiliate ID', 'primary', 'ats_affiliate_save'); ?>
        </form>

        <hr>

        <form method="post">
            <h2>All Affiliate IDs</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Country</th>
                        <th>Affiliate ID</th>
                        <th>Use This</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($affiliates)): ?>
                        <?php foreach ($affiliates as $code => $id): ?>
                            <tr>
                                <td><?php echo esc_html($code); ?></td>
                                <td><?php echo esc_html($id); ?></td>
                                <td>
                                    <input type="radio" name="selected_country" value="<?php echo esc_attr($code); ?>" <?php checked($selected_country, $code); ?> />
                                </td>
                                <td>
                                    <a href="?page=amazon-sync&delete_affiliate=<?php echo esc_attr($code); ?>" class="button" onclick="return confirm('Are you sure you want to delete this affiliate ID?')">❌ Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No affiliate IDs added yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <br>
            <?php submit_button('Set Selected Country', 'secondary', 'ats_select_country'); ?>
        </form>
    </div>
    <?php
}
// Register Amazon API Integration Settings
function ats_register_amazon_api_settings() {
    register_setting('ats_amazon_api_integration', 'ats_use_amazon_api');
    register_setting('ats_amazon_api_integration', 'ats_amazon_access_key');
    register_setting('ats_amazon_api_integration', 'ats_amazon_secret_key');
    register_setting('ats_amazon_api_integration', 'ats_amazon_associate_tag');
}
add_action('admin_init', 'ats_register_amazon_api_settings');
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

