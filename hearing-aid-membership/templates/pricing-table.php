<?php
if (!defined('ABSPATH')) exit;

$plans = array(
    'verified' => array(
        'name' => 'Verified Provider',
        'monthly' => get_option('ham_verified_monthly_price', 49.00),
        'yearly' => get_option('ham_verified_yearly_price', 490.00),
        'features' => array(
            'Red map pin',
            'Verified Provider badge',
            'Featured services (3)',
            'Priority in search results',
            'Enhanced card display'
        )
    ),
    'preferred' => array(
        'name' => 'Preferred Provider',
        'monthly' => get_option('ham_preferred_monthly_price', 99.00),
        'yearly' => get_option('ham_preferred_yearly_price', 990.00),
        'features' => array(
            'Red map pin',
            'Preferred Provider badge with icon',
            'Featured services (3)',
            'Highest priority in search',
            'Premium card display',
            'Highlighted in teal theme',
            'Priority support'
        )
    )
);
?>

<div class="ham-pricing-wrapper" style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
    
    <div class="ham-billing-toggle" style="text-align: center; margin-bottom: 40px;">
        <label style="display: inline-flex; align-items: center; gap: 15px; font-size: 16px;">
            <span>Monthly</span>
            <input type="checkbox" id="ham-billing-toggle" style="width: 50px; height: 26px;">
            <span>Yearly <span style="background: #00a32a; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">Save 17%</span></span>
        </label>
    </div>
    
    <div class="ham-pricing-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
        
        <!-- Verified Plan -->
        <div class="ham-pricing-card" style="background: white; border: 2px solid #ddd; border-radius: 8px; padding: 30px; text-align: center; position: relative;">
            <h3 style="margin: 0 0 10px 0; font-size: 24px;"><?php echo esc_html($plans['verified']['name']); ?></h3>
            
            <div class="ham-price">
                <span class="ham-price-amount" data-monthly="<?php echo $plans['verified']['monthly']; ?>" data-yearly="<?php echo $plans['verified']['yearly']; ?>" style="font-size: 48px; font-weight: bold; color: #2271b1;">
                    $<?php echo number_format($plans['verified']['monthly'], 0); ?>
                </span>
                <span class="ham-price-period" style="font-size: 16px; color: #666;">/month</span>
            </div>
            
            <ul style="list-style: none; padding: 20px 0; margin: 0; text-align: left;">
                <?php foreach ($plans['verified']['features'] as $feature): ?>
                    <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                        <span style="color: #00a32a; margin-right: 8px;">✓</span> <?php echo esc_html($feature); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <a href="<?php echo is_user_logged_in() ? esc_url(home_url('/membership-checkout/?plan=verified&cycle=monthly')) : esc_url(wp_login_url(home_url('/membership-checkout/?plan=verified'))); ?>" class="button button-primary ham-get-started-btn" data-plan="verified" style="display: inline-block; padding: 12px 30px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px;">
                Get Started
            </a>
        </div>
        
        <!-- Preferred Plan -->
        <div class="ham-pricing-card" style="background: white; border: 3px solid #2c5f5d; border-radius: 8px; padding: 30px; text-align: center; position: relative; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <div style="position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: #2c5f5d; color: white; padding: 5px 20px; border-radius: 20px; font-size: 12px; font-weight: bold;">
                POPULAR
            </div>
            
            <h3 style="margin: 0 0 10px 0; font-size: 24px; color: #2c5f5d;"><?php echo esc_html($plans['preferred']['name']); ?></h3>
            
            <div class="ham-price">
                <span class="ham-price-amount" data-monthly="<?php echo $plans['preferred']['monthly']; ?>" data-yearly="<?php echo $plans['preferred']['yearly']; ?>" style="font-size: 48px; font-weight: bold; color: #2c5f5d;">
                    $<?php echo number_format($plans['preferred']['monthly'], 0); ?>
                </span>
                <span class="ham-price-period" style="font-size: 16px; color: #666;">/month</span>
            </div>
            
            <ul style="list-style: none; padding: 20px 0; margin: 0; text-align: left;">
                <?php foreach ($plans['preferred']['features'] as $feature): ?>
                    <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                        <span style="color: #2c5f5d; margin-right: 8px;">✓</span> <?php echo esc_html($feature); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <a href="<?php echo is_user_logged_in() ? esc_url(home_url('/membership-checkout/?plan=preferred&cycle=monthly')) : esc_url(wp_login_url(home_url('/membership-checkout/?plan=preferred'))); ?>" class="button button-primary ham-get-started-btn" data-plan="preferred" style="display: inline-block; padding: 12px 30px; background: #2c5f5d; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px;">
                Get Started
            </a>
        </div>
        
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('ham-billing-toggle');
    const prices = document.querySelectorAll('.ham-price-amount');
    const periods = document.querySelectorAll('.ham-price-period');
    
    if (toggle) {
        toggle.addEventListener('change', function() {
            const isYearly = this.checked;
            
            prices.forEach(function(price) {
                const monthly = parseFloat(price.dataset.monthly);
                const yearly = parseFloat(price.dataset.yearly);
                const amount = isYearly ? yearly : monthly;
                price.textContent = '$' + Math.round(amount);
            });
            
            periods.forEach(function(period) {
                period.textContent = isYearly ? '/year' : '/month';
            });
            
            // Update button URLs with cycle parameter
            document.querySelectorAll('.ham-get-started-btn').forEach(function(btn) {
                const plan = btn.dataset.plan;
                const cycle = isYearly ? 'yearly' : 'monthly';
                const currentUrl = new URL(btn.href);
                const params = new URLSearchParams(currentUrl.search);
                params.set('cycle', cycle);
                btn.href = currentUrl.origin + currentUrl.pathname + '?' + params.toString();
            });
        });
    }
});
</script>
