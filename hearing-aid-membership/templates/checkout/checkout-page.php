<?php
/**
 * Minimal Safe Checkout Page
 */

if (!defined('ABSPATH')) {
    echo '<p>Direct access not allowed.</p>';
    return;
}

// Check if logged in
if (!is_user_logged_in()) {
    echo '<div style="max-width: 600px; margin: 60px auto; padding: 20px; text-align: center; border: 2px solid #ddd; border-radius: 8px;">';
    echo '<h2>Login Required</h2>';
    echo '<p>Please login to purchase a membership.</p>';
    echo '<p><a href="' . wp_login_url(get_permalink()) . '" class="button button-primary">Login</a></p>';
    echo '</div>';
    return;
}

$user_id = get_current_user_id();
$user = wp_get_current_user();

// Check existing membership
global $wpdb;
$existing = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ham_memberships WHERE user_id = %d AND status = 'active'",
    $user_id
));

if ($existing > 0) {
    echo '<div style="max-width: 600px; margin: 60px auto; padding: 20px; text-align: center; border: 2px solid #00a32a; border-radius: 8px;">';
    echo '<h2>Already Active</h2>';
    echo '<p>You already have an active membership!</p>';
    echo '<p><a href="' . home_url('/my-membership/') . '" class="button button-primary">View Dashboard</a></p>';
    echo '</div>';
    return;
}

// Get plan details
$plan = isset($_GET['plan']) ? sanitize_text_field($_GET['plan']) : 'verified';
$cycle = isset($_GET['cycle']) ? sanitize_text_field($_GET['cycle']) : 'monthly';

// Validate
if (!in_array($plan, array('verified', 'preferred'))) {
    $plan = 'verified';
}
if (!in_array($cycle, array('monthly', 'yearly'))) {
    $cycle = 'monthly';
}

// Get price
$price_option = 'ham_' . $plan . '_' . $cycle . '_price';
$price = floatval(get_option($price_option, 0));

if ($price <= 0) {
    echo '<div style="max-width: 600px; margin: 60px auto; padding: 20px; text-align: center; border: 2px solid #d63638; border-radius: 8px;">';
    echo '<h2>Configuration Error</h2>';
    echo '<p>Pricing is not configured. Please contact the administrator.</p>';
    echo '</div>';
    return;
}

// Get Stripe key
$test_mode = get_option('ham_stripe_test_mode', 1);
$stripe_key = $test_mode 
    ? get_option('ham_stripe_test_publishable_key', '')
    : get_option('ham_stripe_live_publishable_key', '');

if (empty($stripe_key)) {
    echo '<div style="max-width: 600px; margin: 60px auto; padding: 20px; text-align: center; border: 2px solid #d63638; border-radius: 8px;">';
    echo '<h2>Payment System Not Ready</h2>';
    echo '<p>Payment processing is not configured. Please contact the administrator.</p>';
    echo '</div>';
    return;
}

// Get store
$user_stores = get_posts(array(
    'post_type' => 'hearing-aid-store',
    'author' => $user_id,
    'posts_per_page' => 1,
    'post_status' => 'publish'
));
$store = !empty($user_stores) ? $user_stores[0] : null;

// Plan names
$plan_names = array(
    'verified' => 'Verified Provider',
    'preferred' => 'Preferred Provider'
);

?>

<div class="ham-checkout" style="max-width: 900px; margin: 40px auto; padding: 20px;">
    
    <h1>Complete Your Membership</h1>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
        
        <!-- Left: Plan Details -->
        <div style="background: #f9f9f9; padding: 30px; border-radius: 8px;">
            <h2>Order Summary</h2>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: <?php echo $plan === 'preferred' ? '#2c5f5d' : '#2271b1'; ?>; margin: 0 0 10px 0;">
                    <?php echo esc_html($plan_names[$plan]); ?>
                </h3>
                
                <div style="font-size: 36px; font-weight: bold; margin: 15px 0;">
                    $<?php echo number_format($price, 2); ?>
                    <span style="font-size: 18px; color: #666; font-weight: normal;">
                        / <?php echo $cycle; ?>
                    </span>
                </div>
                
                <?php if ($cycle === 'yearly'): ?>
                    <p style="color: #00a32a; font-weight: bold; margin: 10px 0;">
                        ✓ Save <?php echo $plan === 'preferred' ? '$198' : '$98'; ?> per year
                    </p>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; background: white; padding: 15px; border-radius: 8px;">
                <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">Change billing:</p>
                <a href="?plan=<?php echo $plan; ?>&cycle=monthly" 
                   class="button <?php echo $cycle === 'monthly' ? 'button-primary' : ''; ?>" 
                   style="margin: 0 5px;">Monthly</a>
                <a href="?plan=<?php echo $plan; ?>&cycle=yearly" 
                   class="button <?php echo $cycle === 'yearly' ? 'button-primary' : ''; ?>" 
                   style="margin: 0 5px;">Yearly</a>
            </div>
            
            <?php if ($store): ?>
                <div style="background: white; padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <p style="margin: 0; font-size: 12px; color: #666;">Applied to:</p>
                    <p style="margin: 5px 0 0 0; font-weight: bold;"><?php echo esc_html($store->post_title); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Right: Payment Form -->
        <div>
            <h2>Payment Details</h2>
            
            <form id="payment-form" style="background: white; padding: 30px; border-radius: 8px;">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Email</label>
                    <input type="email" value="<?php echo esc_attr($user->user_email); ?>" readonly 
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Name on Card</label>
                    <input type="text" id="card-name" value="<?php echo esc_attr($user->display_name); ?>" 
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 10px;">Card Information</label>
                    <div id="card-element" style="padding: 12px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                        <!-- Stripe card element -->
                    </div>
                    <div id="card-errors" style="color: #d63638; margin-top: 10px; font-size: 14px;"></div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="terms" required style="margin-right: 8px;">
                        <span>I agree to recurring charges</span>
                    </label>
                </div>
                
                <button type="submit" id="submit-btn" class="button button-primary button-large" 
                        style="width: 100%; padding: 15px; font-size: 18px;">
                    Subscribe Now - $<?php echo number_format($price, 2); ?>
                </button>
                
                <div id="processing" style="display: none; text-align: center; margin-top: 20px;">
                    <p>Processing payment...</p>
                </div>
                
                <p style="text-align: center; margin-top: 15px; color: #666; font-size: 13px;">
                    🔒 Secure payment by Stripe
                </p>
            </form>
            
            <?php if ($test_mode): ?>
                <div style="background: #e7f5fe; padding: 15px; border-radius: 8px; margin-top: 15px; border: 1px solid #2271b1;">
                    <p style="margin: 0; font-size: 13px;"><strong>Test Mode:</strong> Use card 4242 4242 4242 4242</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
    
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
    // Initialize Stripe
    const stripe = Stripe('<?php echo esc_js($stripe_key); ?>');
    const elements = stripe.elements();
    
    // Create card element
    const cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#32325d',
                '::placeholder': { color: '#aab7c4' }
            },
            invalid: { color: '#d63638' }
        }
    });
    
    cardElement.mount('#card-element');
    
    // Handle errors
    cardElement.on('change', function(event) {
        const displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
        } else {
            displayError.textContent = '';
        }
    });
    
    // Handle form submission
    const form = document.getElementById('payment-form');
    const submitBtn = document.getElementById('submit-btn');
    const processing = document.getElementById('processing');
    
    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        
        // Check terms
        if (!document.getElementById('terms').checked) {
            alert('Please agree to recurring charges');
            return;
        }
        
        // Disable button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';
        processing.style.display = 'block';
        
        try {
            // Create payment method
            const {paymentMethod, error} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {
                    name: document.getElementById('card-name').value,
                    email: '<?php echo esc_js($user->user_email); ?>'
                }
            });
            
            if (error) {
                throw error;
            }
            
            // Send to server
            const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ham_create_subscription',
                    nonce: '<?php echo wp_create_nonce('ham_checkout'); ?>',
                    payment_method_id: paymentMethod.id,
                    plan_type: '<?php echo $plan; ?>',
                    billing_cycle: '<?php echo $cycle; ?>',
                    price_id: '<?php echo get_option('ham_stripe_price_' . $plan . '_' . $cycle, ''); ?>',
                    store_id: '<?php echo $store ? $store->ID : 0; ?>'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.location.href = result.data.redirect_url || '<?php echo home_url('/membership-success/'); ?>';
            } else {
                throw new Error(result.data.message || 'Payment failed');
            }
            
        } catch (error) {
            document.getElementById('card-errors').textContent = error.message;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Subscribe Now - $<?php echo number_format($price, 2); ?>';
            processing.style.display = 'none';
        }
    });
})();
</script>
