<?php
/**
 * Free Account Signup Form
 * Creates user account + first location (pending approval)
 */

if (!defined('ABSPATH')) exit;

// If already logged in, redirect to account
if (is_user_logged_in()) {
    $redirect = home_url('/my-account/');
    echo '<script>window.location.href = "' . $redirect . '";</script>';
    echo '<p>Redirecting to your account...</p>';
    return;
}

// Check if form was submitted
$signup_success = false;
$signup_error = '';

if (isset($_POST['ham_free_signup'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['signup_nonce'], 'ham_free_signup')) {
        $signup_error = 'Security verification failed. Please try again.';
    } else {
        // Validate inputs
        $email = sanitize_email($_POST['user_email']);
        $store_name = sanitize_text_field($_POST['store_name']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $phone = sanitize_text_field($_POST['phone']);
        $address = sanitize_text_field($_POST['address']);
        $password = $_POST['user_password'];
        
        // Check if email exists
        if (email_exists($email)) {
            $signup_error = 'An account with this email already exists. Please <a href="' . wp_login_url() . '">login</a> instead.';
        } elseif (empty($email) || empty($store_name) || empty($first_name) || empty($password)) {
            $signup_error = 'Please fill in all required fields.';
        } elseif (strlen($password) < 6) {
            $signup_error = 'Password must be at least 6 characters long.';
        } else {
            // Create user account
            $user_id = wp_create_user($email, $password, $email);
            
            if (is_wp_error($user_id)) {
                $signup_error = $user_id->get_error_message();
            } else {
                // Update user data
                wp_update_user(array(
                    'ID' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => $first_name . ' ' . $last_name,
                    'role' => 'subscriber'
                ));
                
                // Create store location as PENDING
                $store_id = wp_insert_post(array(
                    'post_title' => $store_name,
                    'post_type' => 'hearing-aid-store',
                    'post_status' => 'pending',
                    'post_author' => $user_id
                ));
                
                if ($store_id) {
                    // Add basic ACF fields
                    if (function_exists('update_field')) {
                        update_field('member_type', 'Non Verified', $store_id);
                        update_field('store_address', $address, $store_id);
                        update_field('store_phone_number', $phone, $store_id);
                    }
                    
                    // Create FREE membership record
                    global $wpdb;
                    $wpdb->insert(
                        $wpdb->prefix . 'ham_memberships',
                        array(
                            'user_id' => $user_id,
                            'store_id' => $store_id,
                            'membership_type' => 'unverified',
                            'billing_cycle' => 'monthly',
                            'price' => 0.00,
                            'status' => 'pending_approval', // Special status for new signups
                            'auto_renew' => 0,
                            'start_date' => current_time('mysql'),
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ),
                        array('%d', '%d', '%s', '%s', '%f', '%s', '%d', '%s', '%s', '%s')
                    );
                }
                
                // Send admin notification
                $admin_email = get_option('admin_email');
                $subject = 'New Free Account Signup - Pending Approval';
                $message = "A new free account has been created and requires approval.\n\n";
                $message .= "User: " . $first_name . " " . $last_name . "\n";
                $message .= "Email: " . $email . "\n";
                $message .= "Store: " . $store_name . "\n";
                $message .= "Phone: " . $phone . "\n\n";
                $message .= "Approve account: " . admin_url('admin.php?page=ham-accounts') . "\n";
                $message .= "Edit store: " . admin_url('post.php?post=' . $store_id . '&action=edit');
                
                wp_mail($admin_email, $subject, $message);
                
                // Send welcome email to user
                $user_subject = 'Welcome! Your Account is Pending Approval';
                $user_message = "Hi " . $first_name . ",\n\n";
                $user_message .= "Thank you for signing up! Your account has been created.\n\n";
                $user_message .= "Your store listing '" . $store_name . "' is currently pending approval by our team. ";
                $user_message .= "We'll review your information and notify you once it's approved.\n\n";
                $user_message .= "You can login anytime at: " . home_url('/member-login/') . "\n\n";
                $user_message .= "Login Details:\n";
                $user_message .= "Email: " . $email . "\n";
                $user_message .= "Password: (the one you chose)\n\n";
                $user_message .= "Thank you!";
                
                wp_mail($email, $user_subject, $user_message);
                
                $signup_success = true;
            }
        }
    }
}

?>

<div class="ham-free-signup" style="max-width: 700px; margin: 40px auto; padding: 0 20px;">
    
    <?php if ($signup_success): ?>
        
        <!-- Success Message -->
        <div style="background: #d4edda; border: 2px solid #c3e6cb; color: #155724; padding: 30px; border-radius: 8px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 15px;">✓</div>
            <h2 style="margin: 0 0 15px 0;">Account Created Successfully!</h2>
            <p style="margin: 0 0 20px 0; font-size: 16px;">
                Thank you for signing up! Your account has been created and your store listing is pending approval.
            </p>
            <p style="margin: 0 0 20px 0;">
                We'll review your information and notify you via email once approved.
            </p>
            <div style="margin-top: 25px;">
                <a href="<?php echo home_url('/member-login/'); ?>" class="button button-primary button-large">
                    Login to Your Account
                </a>
            </div>
        </div>
        
    <?php else: ?>
        
        <h1>Create Your Free Account</h1>
        <p style="font-size: 16px; color: #666; margin-bottom: 30px;">
            Get listed in our directory for free! Fill out the form below to create your account and add your first location.
        </p>
        
        <?php if ($signup_error): ?>
            <div style="background: #f8d7da; border: 2px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <strong>Error:</strong> <?php echo $signup_error; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" id="free-signup-form" style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            
            <!-- Account Information -->
            <div class="form-section" style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #f0f0f0;">
                <h2 style="margin: 0 0 20px 0; color: #2271b1;">Account Information</h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">First Name *</label>
                        <input type="text" name="first_name" required 
                               value="<?php echo isset($_POST['first_name']) ? esc_attr($_POST['first_name']) : ''; ?>"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Last Name *</label>
                        <input type="text" name="last_name" required 
                               value="<?php echo isset($_POST['last_name']) ? esc_attr($_POST['last_name']) : ''; ?>"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Email Address *</label>
                    <input type="email" name="user_email" required 
                           value="<?php echo isset($_POST['user_email']) ? esc_attr($_POST['user_email']) : ''; ?>"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">This will be your login username</p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Password *</label>
                    <input type="password" name="user_password" required minlength="6"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">Minimum 6 characters</p>
                </div>
            </div>
            
            <!-- Store Information -->
            <div class="form-section" style="margin-bottom: 30px;">
                <h2 style="margin: 0 0 20px 0; color: #2271b1;">Your First Location</h2>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Store/Business Name *</label>
                    <input type="text" name="store_name" required 
                           value="<?php echo isset($_POST['store_name']) ? esc_attr($_POST['store_name']) : ''; ?>"
                           placeholder="e.g., Smith Hearing Center"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Phone Number</label>
                    <input type="tel" name="phone" 
                           value="<?php echo isset($_POST['phone']) ? esc_attr($_POST['phone']) : ''; ?>"
                           placeholder="(555) 123-4567"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Street Address</label>
                    <input type="text" name="address" 
                           value="<?php echo isset($_POST['address']) ? esc_attr($_POST['address']) : ''; ?>"
                           placeholder="123 Main Street, City, State 12345"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            
            <!-- Terms -->
            <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                <label style="display: flex; align-items: start; cursor: pointer;">
                    <input type="checkbox" name="agree_terms" required style="margin-right: 10px; margin-top: 3px;">
                    <span>I agree to the <a href="/terms/" target="_blank">Terms of Service</a> and <a href="/privacy/" target="_blank">Privacy Policy</a>. I understand my listing will be reviewed before going live.</span>
                </label>
            </div>
            
            <!-- What Happens Next -->
            <div style="background: #e7f5fe; padding: 20px; border-radius: 4px; margin-bottom: 25px;">
                <h3 style="margin: 0 0 10px 0; color: #2271b1;">What Happens Next?</h3>
                <ol style="margin: 0; padding-left: 20px;">
                    <li style="margin: 5px 0;">Your account is created immediately</li>
                    <li style="margin: 5px 0;">Your store listing is submitted for review</li>
                    <li style="margin: 5px 0;">Our team reviews your information (usually 1-2 business days)</li>
                    <li style="margin: 5px 0;">You receive an email when approved</li>
                    <li style="margin: 5px 0;">Your listing goes live on the site!</li>
                </ol>
            </div>
            
            <!-- Submit Button -->
            <div style="text-align: center;">
                <button type="submit" name="ham_free_signup" class="button button-primary button-hero" 
                        style="padding: 15px 50px; font-size: 20px; cursor: pointer;">
                    Create Free Account
                </button>
            </div>
            
            <input type="hidden" name="signup_nonce" value="<?php echo wp_create_nonce('ham_free_signup'); ?>">
            
        </form>
        
        <p style="text-align: center; margin-top: 20px; color: #666;">
            Already have an account? <a href="<?php echo home_url('/member-login/'); ?>" style="color: #2271b1; font-weight: bold;">Login here</a>
        </p>
        
        <div style="margin-top: 40px; padding: 20px; background: #fff3cd; border-radius: 4px; border: 1px solid #ffc107;">
            <h3 style="margin: 0 0 10px 0;">Want Premium Features?</h3>
            <p style="margin: 0 0 15px 0;">Upgrade to a paid plan for priority placement, enhanced listings, and more!</p>
            <a href="<?php echo home_url('/pricing/'); ?>" class="button">View Paid Plans</a>
        </div>
        
    <?php endif; ?>
    
</div>

<style>
.ham-free-signup input:focus,
.ham-free-signup textarea:focus {
    outline: none;
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
}

@media (max-width: 768px) {
    .ham-free-signup [style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>
