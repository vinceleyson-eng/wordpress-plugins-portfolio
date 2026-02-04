<?php
/**
 * Custom Member Login Page
 * Frontend login that redirects to member dashboard (not WordPress admin)
 */

if (!defined('ABSPATH')) exit;

// If already logged in, redirect to member dashboard
if (is_user_logged_in()) {
    wp_redirect(home_url('/my-account/'));
    exit;
}

// Handle login form submission
$login_error = '';
$login_success = false;

if (isset($_POST['ham_member_login'])) {
    // Verify nonce
    if (!wp_verify_nonce($_POST['login_nonce'], 'ham_member_login')) {
        $login_error = 'Security verification failed.';
    } else {
        $username = sanitize_user($_POST['log']);
        $password = $_POST['pwd'];
        $remember = isset($_POST['rememberme']);
        
        // Attempt login
        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember
        );
        
        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            $login_error = $user->get_error_message();
        } else {
            // Successful login - redirect to member dashboard
            wp_redirect(home_url('/my-account/'));
            exit;
        }
    }
}

// Handle password reset
$reset_sent = false;
$reset_error = '';

if (isset($_POST['ham_reset_password'])) {
    if (!wp_verify_nonce($_POST['reset_nonce'], 'ham_reset_password')) {
        $reset_error = 'Security verification failed.';
    } else {
        $user_login = sanitize_text_field($_POST['user_login']);
        
        if (empty($user_login)) {
            $reset_error = 'Please enter your email address.';
        } else {
            // Use WordPress password reset
            $user = get_user_by('email', $user_login);
            if (!$user) {
                $user = get_user_by('login', $user_login);
            }
            
            if ($user) {
                $reset_key = get_password_reset_key($user);
                
                if (!is_wp_error($reset_key)) {
                    // Send reset email
                    $reset_url = home_url('/member-login/?action=rp&key=' . $reset_key . '&login=' . rawurlencode($user->user_login));
                    
                    $subject = 'Password Reset Request';
                    $message = "Hi " . $user->display_name . ",\n\n";
                    $message .= "You requested a password reset. Click the link below to reset your password:\n\n";
                    $message .= $reset_url . "\n\n";
                    $message .= "If you didn't request this, please ignore this email.\n\n";
                    $message .= "This link will expire in 24 hours.";
                    
                    wp_mail($user->user_email, $subject, $message);
                    $reset_sent = true;
                } else {
                    $reset_error = 'Could not generate reset link.';
                }
            } else {
                // Don't reveal if email exists or not (security)
                $reset_sent = true;
            }
        }
    }
}

// Handle password reset form (when coming from email)
$show_reset_form = false;
$reset_complete = false;

if (isset($_GET['action']) && $_GET['action'] === 'rp' && isset($_GET['key']) && isset($_GET['login'])) {
    $show_reset_form = true;
    
    if (isset($_POST['ham_new_password'])) {
        if (!wp_verify_nonce($_POST['new_password_nonce'], 'ham_new_password')) {
            $reset_error = 'Security verification failed.';
        } else {
            $user = check_password_reset_key($_GET['key'], $_GET['login']);
            
            if (!is_wp_error($user)) {
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($new_password) || strlen($new_password) < 6) {
                    $reset_error = 'Password must be at least 6 characters.';
                } elseif ($new_password !== $confirm_password) {
                    $reset_error = 'Passwords do not match.';
                } else {
                    reset_password($user, $new_password);
                    $reset_complete = true;
                }
            } else {
                $reset_error = 'This password reset link is invalid or has expired.';
            }
        }
    }
}

?>

<div class="ham-member-login" style="max-width: 450px; margin: 60px auto; padding: 0 20px;">
    
    <?php if ($reset_complete): ?>
        
        <!-- Password Reset Complete -->
        <div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 48px; color: #00a32a; margin-bottom: 20px;">✓</div>
            <h2 style="margin: 0 0 15px 0;">Password Reset Complete!</h2>
            <p style="color: #666; margin-bottom: 25px;">Your password has been successfully changed.</p>
            <a href="<?php echo home_url('/member-login/'); ?>" class="button button-primary button-large">
                Login Now
            </a>
        </div>
        
    <?php elseif ($show_reset_form): ?>
        
        <!-- New Password Form -->
        <div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h2 style="text-align: center; margin: 0 0 10px 0;">Reset Your Password</h2>
            <p style="text-align: center; color: #666; margin-bottom: 30px;">Enter your new password below.</p>
            
            <?php if ($reset_error): ?>
                <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                    <?php echo esc_html($reset_error); ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">New Password</label>
                    <input type="password" name="new_password" required minlength="6"
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                    <small style="color: #666;">Minimum 6 characters</small>
                </div>
                
                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Confirm Password</label>
                    <input type="password" name="confirm_password" required minlength="6"
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                </div>
                
                <button type="submit" name="ham_new_password" class="button button-primary button-large" 
                        style="width: 100%; padding: 15px; font-size: 16px; cursor: pointer;">
                    Reset Password
                </button>
                
                <input type="hidden" name="new_password_nonce" value="<?php echo wp_create_nonce('ham_new_password'); ?>">
            </form>
        </div>
        
    <?php elseif ($reset_sent): ?>
        
        <!-- Reset Email Sent -->
        <div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
            <div style="font-size: 48px; margin-bottom: 20px;">📧</div>
            <h2 style="margin: 0 0 15px 0;">Check Your Email</h2>
            <p style="color: #666; margin-bottom: 25px;">
                If an account exists with that email, we've sent password reset instructions.
            </p>
            <a href="<?php echo home_url('/member-login/'); ?>" class="button">
                Back to Login
            </a>
        </div>
        
    <?php else: ?>
        
        <!-- Login Form -->
        <div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            
            <h2 style="text-align: center; margin: 0 0 10px 0;">Member Login</h2>
            <p style="text-align: center; color: #666; margin-bottom: 30px;">Access your account dashboard</p>
            
            <?php if ($login_error): ?>
                <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                    <strong>Error:</strong> <?php echo esc_html($login_error); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" id="member-login-form">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Email Address</label>
                    <input type="text" name="log" required 
                           value="<?php echo isset($_POST['log']) ? esc_attr($_POST['log']) : ''; ?>"
                           placeholder="your@email.com"
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Password</label>
                    <input type="password" name="pwd" required
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="rememberme" value="1" style="margin-right: 8px;">
                        <span>Remember me</span>
                    </label>
                </div>
                
                <button type="submit" name="ham_member_login" class="button button-primary button-large" 
                        style="width: 100%; padding: 15px; font-size: 16px; cursor: pointer;">
                    Login
                </button>
                
                <input type="hidden" name="login_nonce" value="<?php echo wp_create_nonce('ham_member_login'); ?>">
                
            </form>
            
            <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <a href="#" onclick="showResetForm(); return false;" style="color: #2271b1; text-decoration: none;">
                    Forgot your password?
                </a>
            </div>
            
        </div>
        
        <!-- Password Reset Request Form (Hidden) -->
        <div id="reset-form" style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 20px; display: none;">
            
            <h2 style="text-align: center; margin: 0 0 10px 0;">Reset Password</h2>
            <p style="text-align: center; color: #666; margin-bottom: 30px;">Enter your email to receive reset instructions.</p>
            
            <?php if ($reset_error): ?>
                <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                    <?php echo esc_html($reset_error); ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Email Address</label>
                    <input type="email" name="user_login" required
                           placeholder="your@email.com"
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                </div>
                
                <button type="submit" name="ham_reset_password" class="button button-primary button-large" 
                        style="width: 100%; padding: 15px; font-size: 16px; cursor: pointer;">
                    Send Reset Link
                </button>
                
                <input type="hidden" name="reset_nonce" value="<?php echo wp_create_nonce('ham_reset_password'); ?>">
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="#" onclick="hideResetForm(); return false;" style="color: #666; text-decoration: none;">
                    ← Back to Login
                </a>
            </div>
            
        </div>
        
        <div style="text-align: center; margin-top: 25px;">
            <p style="color: #666;">
                Don't have an account? 
                <a href="<?php echo home_url('/free-signup/'); ?>" style="color: #2271b1; text-decoration: none; font-weight: bold;">
                    Sign up free
                </a>
            </p>
        </div>
        
    <?php endif; ?>
    
</div>

<script>
function showResetForm() {
    document.getElementById('member-login-form').parentElement.style.display = 'none';
    document.getElementById('reset-form').style.display = 'block';
}

function hideResetForm() {
    document.getElementById('member-login-form').parentElement.style.display = 'block';
    document.getElementById('reset-form').style.display = 'none';
}
</script>

<style>
.ham-member-login input:focus {
    outline: none;
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
}

.ham-member-login .button-primary {
    background: #2271b1;
    border: none;
    transition: background 0.3s;
}

.ham-member-login .button-primary:hover {
    background: #135e96;
}
</style>
