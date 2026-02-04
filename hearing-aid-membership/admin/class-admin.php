<?php
if (!defined('ABSPATH')) exit;

class HAM_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'HA Membership',
            'HA Membership',
            'manage_options',
            'ha-membership',
            array($this, 'admin_page'),
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'ha-membership',
            'All Memberships',
            'All Memberships',
            'manage_options',
            'ha-membership',
            array($this, 'admin_page')
        );
        
        // Get pending accounts count
        global $wpdb;
        $pending_accounts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ham_memberships WHERE status = 'pending_approval'");
        
        $accounts_title = 'All Accounts';
        if ($pending_accounts > 0) {
            $accounts_title .= ' <span class="update-plugins count-' . $pending_accounts . '"><span class="awaiting-mod">' . $pending_accounts . '</span></span>';
        }
        
        add_submenu_page(
            'ha-membership',
            'All Accounts',
            $accounts_title,
            'manage_options',
            'ham-accounts',
            array($this, 'accounts_page')
        );
        
        add_submenu_page(
            'ha-membership',
            'Transactions',
            'Transactions',
            'manage_options',
            'ham-transactions',
            array($this, 'transactions_page')
        );
        
        // Get pending count for notification badge
        $pending_stores = wp_count_posts('hearing-aid-store');
        $pending_audio = wp_count_posts('audiologist');
        $total_pending = ($pending_stores->pending ?? 0) + ($pending_audio->pending ?? 0);
        
        $approvals_title = 'Pending Approvals';
        if ($total_pending > 0) {
            $approvals_title .= ' <span class="update-plugins count-' . $total_pending . '"><span class="awaiting-mod">' . $total_pending . '</span></span>';
        }
        
        add_submenu_page(
            'ha-membership',
            'Pending Approvals',
            $approvals_title,
            'manage_options',
            'ham-approvals',
            array($this, 'approvals_page')
        );
        
        add_submenu_page(
            'ha-membership',
            'Account Assignment',
            'Account Assignment',
            'manage_options',
            'ham-account-assignment',
            array($this, 'account_assignment_page')
        );
        
        add_submenu_page(
            'ha-membership',
            'Revenue Reports',
            'Revenue Reports',
            'manage_options',
            'ham-revenue',
            array($this, 'revenue_page')
        );
        
        add_submenu_page(
            'ha-membership',
            'Settings',
            'Settings',
            'manage_options',
            'ham-settings',
            array($this, 'settings_page')
        );
        
        // Hidden edit page
        add_submenu_page(
            null, // No parent = hidden from menu
            'Edit Membership',
            'Edit Membership',
            'manage_options',
            'ham-edit-membership',
            array($this, 'edit_membership_page')
        );
    }
    
    public function edit_membership_page() {
        include HAM_PLUGIN_DIR . 'admin/views/edit-membership.php';
    }
    
    public function approvals_page() {
        include HAM_PLUGIN_DIR . 'admin/views/approvals.php';
    }
    
    public function accounts_page() {
        include HAM_PLUGIN_DIR . 'admin/views/accounts.php';
    }
    
    public function account_assignment_page() {
        include HAM_PLUGIN_DIR . 'admin/views/account-assignment.php';
    }
    
    public function register_settings() {
        register_setting('ham_settings', 'ham_stripe_test_mode');
        register_setting('ham_settings', 'ham_stripe_test_secret_key');
        register_setting('ham_settings', 'ham_stripe_test_publishable_key');
        register_setting('ham_settings', 'ham_stripe_live_secret_key');
        register_setting('ham_settings', 'ham_stripe_live_publishable_key');
        register_setting('ham_settings', 'ham_stripe_webhook_secret');
        register_setting('ham_settings', 'ham_verified_monthly_price');
        register_setting('ham_settings', 'ham_verified_yearly_price');
        register_setting('ham_settings', 'ham_preferred_monthly_price');
        register_setting('ham_settings', 'ham_preferred_yearly_price');
        register_setting('ham_settings', 'ham_stripe_price_verified_monthly');
        register_setting('ham_settings', 'ham_stripe_price_verified_yearly');
        register_setting('ham_settings', 'ham_stripe_price_preferred_monthly');
        register_setting('ham_settings', 'ham_stripe_price_preferred_yearly');
        register_setting('ham_settings', 'ham_currency');
        register_setting('ham_settings', 'ham_payment_retry_count');
        register_setting('ham_settings', 'ham_auto_downgrade');
    }
    
    public function admin_page() {
        $stats = HA_Membership()->db->get_statistics();
        $memberships = HA_Membership()->db->get_all_memberships('active', 50, 0);
        include HAM_PLUGIN_DIR . 'admin/views/memberships.php';
    }
    
    public function transactions_page() {
        global $wpdb;
        $transactions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ham_transactions ORDER BY created_at DESC LIMIT 100");
        include HAM_PLUGIN_DIR . 'admin/views/transactions.php';
    }
    
    public function revenue_page() {
        $stats = HA_Membership()->db->get_statistics();
        include HAM_PLUGIN_DIR . 'admin/views/revenue.php';
    }
    
    public function settings_page() {
        if (isset($_POST['ham_save_settings'])) {
            check_admin_referer('ham_settings');
            // Settings auto-saved by Settings API
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        include HAM_PLUGIN_DIR . 'admin/views/settings.php';
    }
}

new HAM_Admin();
