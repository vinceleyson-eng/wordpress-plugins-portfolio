<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('ham_settings');
        wp_nonce_field('ham_settings');
        ?>
        
        <h2 class="nav-tab-wrapper">
            <a href="#stripe" class="nav-tab nav-tab-active">Stripe Settings</a>
            <a href="#pricing" class="nav-tab">Pricing</a>
            <a href="#general" class="nav-tab">General</a>
        </h2>
        
        <div id="stripe" class="tab-content" style="display:block;">
            <h2>Stripe Configuration</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Test Mode</th>
                    <td>
                        <label>
                            <input type="checkbox" name="ham_stripe_test_mode" value="1" <?php checked(get_option('ham_stripe_test_mode', 1), 1); ?>>
                            Enable Test Mode (use test API keys)
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Test Secret Key</th>
                    <td>
                        <input type="text" name="ham_stripe_test_secret_key" value="<?php echo esc_attr(get_option('ham_stripe_test_secret_key', '')); ?>" class="regular-text" placeholder="sk_test_...">
                        <p class="description">Get from <a href="https://dashboard.stripe.com/test/apikeys" target="_blank">Stripe Dashboard → API Keys</a></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Test Publishable Key</th>
                    <td>
                        <input type="text" name="ham_stripe_test_publishable_key" value="<?php echo esc_attr(get_option('ham_stripe_test_publishable_key', '')); ?>" class="regular-text" placeholder="pk_test_...">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Live Secret Key</th>
                    <td>
                        <input type="text" name="ham_stripe_live_secret_key" value="<?php echo esc_attr(get_option('ham_stripe_live_secret_key', '')); ?>" class="regular-text" placeholder="sk_live_...">
                        <p class="description">Only use after testing thoroughly!</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Live Publishable Key</th>
                    <td>
                        <input type="text" name="ham_stripe_live_publishable_key" value="<?php echo esc_attr(get_option('ham_stripe_live_publishable_key', '')); ?>" class="regular-text" placeholder="pk_live_...">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Webhook Secret</th>
                    <td>
                        <input type="text" name="ham_stripe_webhook_secret" value="<?php echo esc_attr(get_option('ham_stripe_webhook_secret', '')); ?>" class="regular-text" placeholder="whsec_...">
                        <p class="description">Webhook URL: <code><?php echo home_url('/wp-json/ham/v1/webhook'); ?></code></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="pricing" class="tab-content" style="display:none;">
            <h2>Membership Pricing</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><h3>Verified Provider</h3></th>
                    <td></td>
                </tr>
                <tr>
                    <th scope="row">Monthly Price</th>
                    <td>
                        $<input type="number" name="ham_verified_monthly_price" value="<?php echo esc_attr(get_option('ham_verified_monthly_price', 49.00)); ?>" step="0.01" min="0" style="width:100px;">
                        <p class="description">Default: $49.00</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Yearly Price</th>
                    <td>
                        $<input type="number" name="ham_verified_yearly_price" value="<?php echo esc_attr(get_option('ham_verified_yearly_price', 490.00)); ?>" step="0.01" min="0" style="width:100px;">
                        <p class="description">Default: $490.00 (save $98)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stripe Price ID (Monthly)</th>
                    <td>
                        <input type="text" name="ham_stripe_price_verified_monthly" value="<?php echo esc_attr(get_option('ham_stripe_price_verified_monthly', '')); ?>" class="regular-text" placeholder="price_...">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stripe Price ID (Yearly)</th>
                    <td>
                        <input type="text" name="ham_stripe_price_verified_yearly" value="<?php echo esc_attr(get_option('ham_stripe_price_verified_yearly', '')); ?>" class="regular-text" placeholder="price_...">
                    </td>
                </tr>
                
                <tr><td colspan="2"><hr></td></tr>
                
                <tr>
                    <th scope="row"><h3>Preferred Provider</h3></th>
                    <td></td>
                </tr>
                <tr>
                    <th scope="row">Monthly Price</th>
                    <td>
                        $<input type="number" name="ham_preferred_monthly_price" value="<?php echo esc_attr(get_option('ham_preferred_monthly_price', 99.00)); ?>" step="0.01" min="0" style="width:100px;">
                        <p class="description">Default: $99.00</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Yearly Price</th>
                    <td>
                        $<input type="number" name="ham_preferred_yearly_price" value="<?php echo esc_attr(get_option('ham_preferred_yearly_price', 990.00)); ?>" step="0.01" min="0" style="width:100px;">
                        <p class="description">Default: $990.00 (save $198)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stripe Price ID (Monthly)</th>
                    <td>
                        <input type="text" name="ham_stripe_price_preferred_monthly" value="<?php echo esc_attr(get_option('ham_stripe_price_preferred_monthly', '')); ?>" class="regular-text" placeholder="price_...">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stripe Price ID (Yearly)</th>
                    <td>
                        <input type="text" name="ham_stripe_price_preferred_yearly" value="<?php echo esc_attr(get_option('ham_stripe_price_preferred_yearly', '')); ?>" class="regular-text" placeholder="price_...">
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="general" class="tab-content" style="display:none;">
            <h2>General Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Post Type</th>
                    <td>
                        <p><strong>store</strong></p>
                        <p class="description">Your custom post type for hearing aid stores</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Currency</th>
                    <td>
                        <select name="ham_currency">
                            <option value="USD" <?php selected(get_option('ham_currency', 'USD'), 'USD'); ?>>USD ($)</option>
                            <option value="EUR" <?php selected(get_option('ham_currency', 'USD'), 'EUR'); ?>>EUR (€)</option>
                            <option value="GBP" <?php selected(get_option('ham_currency', 'USD'), 'GBP'); ?>>GBP (£)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Failed Payment Policy</th>
                    <td>
                        <label>
                            <input type="number" name="ham_payment_retry_count" value="<?php echo esc_attr(get_option('ham_payment_retry_count', 3)); ?>" min="1" max="10" style="width: 60px;">
                            failed payment attempts before downgrading to Non Verified
                        </label>
                        <p class="description">Stripe will automatically retry failed payments. After this many attempts, the membership will be cancelled and the store downgraded to Non Verified status.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Auto-Downgrade</th>
                    <td>
                        <label>
                            <input type="checkbox" name="ham_auto_downgrade" value="1" <?php checked(get_option('ham_auto_downgrade', 1), 1); ?>>
                            Automatically downgrade to Non Verified when payment fails
                        </label>
                        <p class="description">When enabled, stores will automatically lose their Preferred/Verified status after failed payment attempts.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button('Save Settings'); ?>
    </form>
    
    <hr>
    
    <div class="card">
        <h2>Quick Setup Guide</h2>
        <ol>
            <li><strong>Get Stripe API Keys:</strong> Go to <a href="https://dashboard.stripe.com/test/apikeys" target="_blank">Stripe Dashboard</a> and copy your test keys</li>
            <li><strong>Create Stripe Products:</strong> In Stripe, create 4 products (Verified Monthly/Yearly, Preferred Monthly/Yearly)</li>
            <li><strong>Copy Price IDs:</strong> From each Stripe product, copy the Price ID (starts with price_...)</li>
            <li><strong>Set Webhook:</strong> In Stripe, add webhook endpoint: <code><?php echo home_url('/wp-json/ham/v1/webhook'); ?></code></li>
            <li><strong>Test:</strong> Use test card 4242 4242 4242 4242 with any future date</li>
        </ol>
    </div>
</div>

<style>
.nav-tab-wrapper { margin-bottom: 20px; }
.tab-content { padding: 20px; background: white; border: 1px solid #ccd0d4; border-top: none; }
.card { background: white; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4; max-width: 800px; }
</style>

<script>
jQuery(document).ready(function($) {
    $('.nav-tab').click(function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').hide();
        $($(this).attr('href')).show();
    });
});
</script>
