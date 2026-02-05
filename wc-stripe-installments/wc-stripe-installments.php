<?php
/**
 * Plugin Name: WC Stripe Installments
 * Description: Split WooCommerce payments into 4 installments using Stripe
 * Version: 1.0.0
 * Author: Vince L
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WCSI_VERSION', '1.0.0');
define('WCSI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCSI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Only load if WooCommerce is active
add_action('plugins_loaded', 'wcsi_init', 20);

function wcsi_init() {
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Load Stripe library - use our bundled version
    if (!class_exists('\Stripe\Stripe')) {
        $stripe_file = WCSI_PLUGIN_DIR . 'vendor/stripe/init.php';
        if (file_exists($stripe_file)) {
            require_once $stripe_file;
        }
    }
    
    // Verify Stripe loaded
    if (!class_exists('\Stripe\Stripe')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>WC Stripe Installments:</strong> Stripe library failed to load.</p></div>';
        });
        return;
    }
    
    require_once WCSI_PLUGIN_DIR . 'includes/class-wcsi-gateway.php';
    require_once WCSI_PLUGIN_DIR . 'includes/class-wcsi-cart-display.php';
    require_once WCSI_PLUGIN_DIR . 'includes/class-wcsi-order-display.php';
    
    add_filter('woocommerce_payment_gateways', 'wcsi_add_gateway');
    
    // Initialize cart display
    new WCSI_Cart_Display();
    
    // Initialize order display
    new WCSI_Order_Display();
}

function wcsi_add_gateway($gateways) {
    $gateways[] = 'WCSI_Gateway';
    return $gateways;
}

// Create tables on activation
register_activation_hook(__FILE__, 'wcsi_activate');

function wcsi_activate() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    
    $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcsi_installments (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        customer_id bigint(20) NOT NULL,
        stripe_customer_id varchar(255) NOT NULL,
        stripe_payment_method varchar(255) NOT NULL,
        total_amount decimal(10,2) NOT NULL,
        installment_amount decimal(10,2) NOT NULL,
        installments_paid int(11) DEFAULT 0,
        total_installments int(11) DEFAULT 4,
        next_payment_date datetime DEFAULT NULL,
        status varchar(50) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";
    
    $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcsi_payments (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        installment_id bigint(20) NOT NULL,
        order_id bigint(20) NOT NULL,
        payment_number int(11) NOT NULL,
        amount decimal(10,2) NOT NULL,
        stripe_payment_intent varchar(255) DEFAULT NULL,
        status varchar(50) DEFAULT 'pending',
        scheduled_date datetime NOT NULL,
        paid_date datetime DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    
    if (!wp_next_scheduled('wcsi_process_payments')) {
        wp_schedule_event(time(), 'hourly', 'wcsi_process_payments');
    }
}

register_deactivation_hook(__FILE__, 'wcsi_deactivate');

function wcsi_deactivate() {
    wp_clear_scheduled_hook('wcsi_process_payments');
}
