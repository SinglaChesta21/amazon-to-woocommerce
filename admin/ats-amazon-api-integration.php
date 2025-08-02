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

<h2>Amazon API Integration Settings</h2>
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
