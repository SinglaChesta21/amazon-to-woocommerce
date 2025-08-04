<?php
if (!defined('ABSPATH')) exit;

// ✅ Handle form submission manually
if (isset($_POST['ats_api_save'])) {
    update_option('ats_amazon_access_key', sanitize_text_field($_POST['ats_amazon_access_key']));
    update_option('ats_amazon_secret_key', sanitize_text_field($_POST['ats_amazon_secret_key']));
    update_option('ats_amazon_associate_tag', sanitize_text_field($_POST['ats_amazon_associate_tag']));
    update_option('ats_use_amazon_api', isset($_POST['ats_use_amazon_api']) ? 1 : 0);

    echo '<div class="updated notice is-dismissible"><p>✅ Amazon API settings saved successfully.</p></div>';
}
?>

<h1  style="font-size: 26px; font-weight: 600;margin-bottom: 20px;">Amazon API Integration Settings</h1>
<form method="post">
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Amazon Access Key</th>
            <td>
                <input type="text" name="ats_amazon_access_key"
                       value="<?php echo esc_attr(get_option('ats_amazon_access_key')); ?>" 
                       placeholder="Enter Access Key ID" class="regular-text" />
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">Amazon Secret Key</th>
            <td>
                <input type="text" name="ats_amazon_secret_key"
                       value="<?php echo esc_attr(get_option('ats_amazon_secret_key')); ?>" 
                       placeholder="Enter Secret Key" class="regular-text" />
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">Amazon Associate Tag</th>
            <td>
                <input type="text" name="ats_amazon_associate_tag"
                       value="<?php echo esc_attr(get_option('ats_amazon_associate_tag')); ?>" 
                       placeholder="yourtag-21" class="regular-text" />
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">Use Amazon API?</th>
            <td>
                <input type="checkbox" name="ats_use_amazon_api" value="1"
                    <?php checked(1, get_option('ats_use_amazon_api'), true); ?> />
                <label for="ats_use_amazon_api">Enable Amazon Product Advertising API</label>
            </td>
        </tr>
    </table>

    <?php submit_button('Save Settings', 'primary', 'ats_api_save'); ?>
</form>
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
    padding: 9px 12px;
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


