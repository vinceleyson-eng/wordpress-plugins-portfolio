<?php
if (!defined('ABSPATH')) exit;

// Pricing table shortcode
function ham_pricing_shortcode($atts) {
    ob_start();
    include HAM_PLUGIN_DIR . 'templates/pricing-table.php';
    return ob_get_clean();
}
add_shortcode('ham_pricing', 'ham_pricing_shortcode');

// Dashboard shortcode
function ham_dashboard_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to view your dashboard.</p>';
    }
    ob_start();
    include HAM_PLUGIN_DIR . 'templates/dashboard.php';
    return ob_get_clean();
}
add_shortcode('ham_dashboard', 'ham_dashboard_shortcode');

// Membership status shortcode
function ham_status_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '';
    }
    $user_id = get_current_user_id();
    $membership = HA_Membership()->membership->get_user_membership($user_id);
    if ($membership) {
        return ham_get_membership_badge($membership->membership_type);
    }
    return ham_get_membership_badge('unverified');
}
add_shortcode('ham_status', 'ham_status_shortcode');

// Upgrade button shortcode
function ham_upgrade_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '';
    }
    $atts = shortcode_atts(array('plan' => 'verified'), $atts);
    return '<a href="' . home_url('/membership-checkout/?plan=' . $atts['plan']) . '" class="ham-upgrade-btn">Upgrade to ' . ucfirst($atts['plan']) . '</a>';
}
add_shortcode('ham_upgrade', 'ham_upgrade_shortcode');

// Checkout page shortcode
function ham_checkout_shortcode($atts) {
    ob_start();
    
    // Check if template exists
    $template_file = HAM_PLUGIN_DIR . 'templates/checkout/checkout-page.php';
    if (file_exists($template_file)) {
        include $template_file;
    } else {
        echo '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
        echo '<p><strong>Error:</strong> Checkout template not found.</p>';
        echo '<p>Please contact the site administrator.</p>';
        echo '</div>';
    }
    
    return ob_get_clean();
}
add_shortcode('ham_checkout', 'ham_checkout_shortcode');

// Success page shortcode
function ham_success_shortcode($atts) {
    ob_start();
    include HAM_PLUGIN_DIR . 'templates/checkout/success.php';
    return ob_get_clean();
}
add_shortcode('ham_success', 'ham_success_shortcode');

// Cancel page shortcode
function ham_cancel_shortcode($atts) {
    ob_start();
    include HAM_PLUGIN_DIR . 'templates/checkout/cancel.php';
    return ob_get_clean();
}
add_shortcode('ham_cancel', 'ham_cancel_shortcode');

// Store editor shortcode
function ham_store_editor_shortcode($atts) {
    ob_start();
    
    $template_file = HAM_PLUGIN_DIR . 'templates/store-editor.php';
    if (file_exists($template_file)) {
        include $template_file;
    } else {
        echo '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
        echo '<p><strong>Error:</strong> Store editor template not found.</p>';
        echo '</div>';
    }
    
    return ob_get_clean();
}
add_shortcode('ham_store_editor', 'ham_store_editor_shortcode');

// Account dashboard shortcode
function ham_account_dashboard_shortcode($atts) {
    ob_start();
    
    $template_file = HAM_PLUGIN_DIR . 'templates/account-dashboard.php';
    if (file_exists($template_file)) {
        include $template_file;
    } else {
        echo '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
        echo '<p><strong>Error:</strong> Account dashboard template not found.</p>';
        echo '</div>';
    }
    
    return ob_get_clean();
}
add_shortcode('ham_account', 'ham_account_dashboard_shortcode');

// Free signup shortcode
function ham_free_signup_shortcode($atts) {
    ob_start();
    
    $template_file = HAM_PLUGIN_DIR . 'templates/free-signup.php';
    if (file_exists($template_file)) {
        include $template_file;
    } else {
        echo '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
        echo '<p><strong>Error:</strong> Free signup template not found.</p>';
        echo '</div>';
    }
    
    return ob_get_clean();
}
add_shortcode('ham_free_signup', 'ham_free_signup_shortcode');

// Member login shortcode
function ham_member_login_shortcode($atts) {
    ob_start();
    
    $template_file = HAM_PLUGIN_DIR . 'templates/member-login.php';
    if (file_exists($template_file)) {
        include $template_file;
    } else {
        echo '<div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
        echo '<p><strong>Error:</strong> Member login template not found.</p>';
        echo '</div>';
    }
    
    return ob_get_clean();
}
add_shortcode('ham_member_login', 'ham_member_login_shortcode');
