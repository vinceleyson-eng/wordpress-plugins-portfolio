<?php
if (!defined('ABSPATH')) exit;

// Get membership badge HTML
function ham_get_membership_badge($type) {
    $badges = array(
        'preferred' => '<span class="ham-badge ham-badge-preferred">Preferred Provider</span>',
        'verified' => '<span class="ham-badge ham-badge-verified">Verified Provider</span>',
        'unverified' => '<span class="ham-badge ham-badge-unverified">Unverified</span>'
    );
    return isset($badges[$type]) ? $badges[$type] : $badges['unverified'];
}

// Format price
function ham_format_price($amount) {
    return '$' . number_format($amount, 2);
}

// Check if checkout page
function ham_is_checkout_page() {
    return is_page('membership-checkout') || (isset($_GET['ham_checkout']) && $_GET['ham_checkout'] == '1');
}

// Get user's store
function ham_get_user_store($user_id) {
    $args = array(
        'post_type' => 'hearing-aid-store',
        'author' => $user_id,
        'posts_per_page' => 1,
        'post_status' => 'publish'
    );
    $posts = get_posts($args);
    return !empty($posts) ? $posts[0] : null;
}

// Get Stripe price ID
function ham_get_stripe_price_id($plan_type, $billing_cycle) {
    $key = 'ham_stripe_price_' . $plan_type . '_' . $billing_cycle;
    return get_option($key, '');
}
