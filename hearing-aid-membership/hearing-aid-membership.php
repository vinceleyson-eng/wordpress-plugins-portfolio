<?php
/**
 * Plugin Name: Hearing Aid Provider Membership
 * Plugin URI: https://yoursite.com
 * Description: Complete membership management system for hearing aid providers with Stripe integration
 * Version: 1.0.1
 * Author: Bidview Marketing
 * Text Domain: ha-membership
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HAM_VERSION', '1.0.1');
define('HAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HAM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HAM_PLUGIN_FILE', __FILE__);

/**
 * Main Plugin Class
 */
class HA_Membership {
    
    private static $instance = null;
    public $db;
    public $membership;
    public $stripe;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Load files
        add_action('plugins_loaded', array($this, 'load_files'), 1);
        
        // Activation/Deactivation hooks
        register_activation_hook(HAM_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(HAM_PLUGIN_FILE, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin files
     */
    public function load_files() {
        // Include core files
        $core_files = array(
            'includes/class-database.php',
            'includes/class-membership.php',
            'includes/class-stripe.php',
            'includes/functions.php',
            'includes/shortcodes.php'
        );
        
        foreach ($core_files as $file) {
            $filepath = HAM_PLUGIN_DIR . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            }
        }
        
        // Initialize after files loaded
        $this->init_classes();
        
        // Include admin files
        if (is_admin()) {
            $admin_files = array(
                'admin/class-admin.php',
                'admin/class-settings.php',
                'admin/import-tool.php'
            );
            
            foreach ($admin_files as $file) {
                $filepath = HAM_PLUGIN_DIR . $file;
                if (file_exists($filepath)) {
                    require_once $filepath;
                }
            }
        }
        
        // Initialize
        add_action('init', array($this, 'init'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_ham_upgrade_membership', array($this, 'ajax_upgrade_membership'));
        add_action('wp_ajax_ham_cancel_membership', array($this, 'ajax_cancel_membership'));
        add_action('wp_ajax_ham_update_payment_method', array($this, 'ajax_update_payment_method'));
        add_action('wp_ajax_ham_create_subscription', array($this, 'ajax_create_subscription'));
        add_action('wp_ajax_ham_save_store', array($this, 'ajax_save_store'));
        add_action('wp_ajax_ham_create_location', array($this, 'ajax_create_location'));
        add_action('wp_ajax_ham_create_audiologist', array($this, 'ajax_create_audiologist'));
        add_action('wp_ajax_ham_save_audiologist', array($this, 'ajax_save_audiologist'));
        add_action('wp_ajax_ham_link_audiologist_to_store', array($this, 'ajax_link_audiologist_to_store'));
        
        // Hide WordPress admin for non-admins
        add_action('admin_init', array($this, 'redirect_non_admins'));
        add_action('after_setup_theme', array($this, 'remove_admin_bar'));
        
        // Redirect wp-login.php to custom login
        add_action('login_init', array($this, 'redirect_to_custom_login'));
        
        // Filter ACF relationship field to show only user's audiologists
        add_filter('acf/fields/relationship/query', array($this, 'filter_audiologist_relationship'), 10, 3);
    }
    
    /**
     * Redirect non-admin users away from WordPress admin
     */
    public function redirect_non_admins() {
        if (!current_user_can('manage_options') && !wp_doing_ajax()) {
            wp_redirect(home_url('/my-account/'));
            exit;
        }
    }
    
    /**
     * Remove admin bar for non-admins
     */
    public function remove_admin_bar() {
        if (!current_user_can('manage_options')) {
            show_admin_bar(false);
        }
    }
    
    /**
     * Redirect wp-login.php to custom member login
     * Only redirects members, not admins
     */
    public function redirect_to_custom_login() {
        // Allow logout to work
        if (isset($_GET['action']) && $_GET['action'] === 'logout') {
            return;
        }
        
        // Allow password reset
        if (isset($_GET['action']) && in_array($_GET['action'], array('lostpassword', 'rp', 'resetpass', 'register'))) {
            return;
        }
        
        // Don't redirect during AJAX
        if (wp_doing_ajax()) {
            return;
        }
        
        // Don't redirect during login POST (actual login attempt)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return;
        }
        
        // Don't redirect if already logged in
        if (is_user_logged_in()) {
            return;
        }
        
        // Allow admin access via /wp-login.php?admin or /wp-admin/ redirect
        if (isset($_GET['admin']) || isset($_GET['redirect_to']) && strpos($_GET['redirect_to'], 'wp-admin') !== false) {
            return;
        }
        
        // Check if coming from wp-admin (trying to access backend)
        $referer = wp_get_referer();
        if ($referer && strpos($referer, 'wp-admin') !== false) {
            return;
        }
        
        // Only redirect to member login for regular front-end login attempts
        // This allows admins to still use wp-login.php directly
        // Members are encouraged to use /member-login/ instead
        if (isset($_GET['member']) || (isset($_GET['redirect_to']) && strpos($_GET['redirect_to'], 'my-account') !== false)) {
            wp_redirect(home_url('/member-login/'));
            exit;
        }
        
        // Default: Don't redirect - allow normal wp-login.php access
        // This ensures admins can always log in
        return;
    }
    
    /**
     * Filter ACF relationship field to show only current user's audiologists
     */
    public function filter_audiologist_relationship($args, $field, $post_id) {
        // Only filter if it's the audiologist relationship field on hearing-aid-store posts
        if ($field['name'] === 'associated_audiologist' || $field['post_type'] === 'audiologist') {
            // Check if we're in admin and not an admin user
            if (is_admin() && !current_user_can('manage_options')) {
                // Get current user ID
                $user_id = get_current_user_id();
                
                // Filter to only show audiologists owned by current user
                $args['author'] = $user_id;
            }
        }
        
        return $args;
    }
    
    /**
     * AJAX: Save audiologist information
     */
    public function ajax_save_audiologist() {
        $audio_id = intval($_POST['audiologist_id']);
        
        // Verify nonce
        check_ajax_referer('ham_save_audiologist_' . $audio_id, 'nonce');
        
        // Check if user owns this audiologist
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in'));
        }
        
        $post = get_post($audio_id);
        if (!$post || $post->post_author != get_current_user_id() || $post->post_type !== 'audiologist') {
            wp_send_json_error(array('message' => 'You do not have permission to edit this profile'));
        }
        
        // Update post
        wp_update_post(array(
            'ID' => $audio_id,
            'post_title' => sanitize_text_field($_POST['post_title']),
            'post_excerpt' => sanitize_text_field($_POST['post_excerpt'])
        ));
        
        // Update bio ACF field
        if (function_exists('update_field') && isset($_POST['audiologists_bio'])) {
            update_field('audiologists_bio', wp_kses_post($_POST['audiologists_bio']), $audio_id);
            
            // Migration: Clear old post_content if it has data (one-time migration)
            if (!empty($post->post_content)) {
                wp_update_post(array(
                    'ID' => $audio_id,
                    'post_content' => '' // Clear old content since we're using ACF now
                ));
            }
        }
        
        // Update linked store
        if (isset($_POST['linked_store_id'])) {
            $store_id = intval($_POST['linked_store_id']);
            if ($store_id > 0) {
                // Verify user owns the store
                $store = get_post($store_id);
                if ($store && $store->post_author == get_current_user_id()) {
                    update_post_meta($audio_id, 'linked_store_id', $store_id);
                }
            } else {
                delete_post_meta($audio_id, 'linked_store_id');
            }
        }
        
        wp_send_json_success(array('message' => 'Audiologist profile updated successfully'));
    }
    
    /**
     * AJAX: Link/Unlink audiologist to/from store
     */
    public function ajax_link_audiologist_to_store() {
        check_ajax_referer('ham_link_audiologist', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in'));
        }

        $user_id = get_current_user_id();
        $audio_id = intval($_POST['audiologist_id']);
        $store_id = intval($_POST['store_id']);
        $link = $_POST['link'] === '1'; // true to link, false to unlink

        $audiologist = get_post($audio_id);
        if (!$audiologist || $audiologist->post_type !== 'audiologist') {
            wp_send_json_error(array('message' => 'Invalid audiologist'));
        }

        // For ADDING: User must own the audiologist
        if ($link && $store_id > 0) {
            // Verify user owns the audiologist they're trying to add
            if ($audiologist->post_author != $user_id) {
                wp_send_json_error(array('message' => 'You can only add audiologists you manage'));
            }

            // Verify user owns the store
            $store = get_post($store_id);
            if (!$store || $store->post_author != $user_id || $store->post_type !== 'hearing-aid-store') {
                wp_send_json_error(array('message' => 'You do not have permission to manage this store'));
            }

            // Link audiologist to store (update both data sources)
            update_post_meta($audio_id, 'linked_store_id', $store_id);

            // Also update ACF relationship field on the store
            if (function_exists('get_field') && function_exists('update_field')) {
                $current_audiologists = get_field('associated_audiologist', $store_id);
                if (!is_array($current_audiologists)) {
                    $current_audiologists = array();
                }
                // ACF can return objects or IDs - normalize to IDs
                $current_ids = array_map(function($item) {
                    return is_object($item) ? $item->ID : intval($item);
                }, $current_audiologists);

                if (!in_array($audio_id, $current_ids)) {
                    $current_ids[] = $audio_id;
                    update_field('associated_audiologist', $current_ids, $store_id);
                }
            }

            wp_send_json_success(array('message' => 'Audiologist added to location'));
        } else {
            // For REMOVING: Need to find which store the audiologist is linked to
            // Check both linked_store_id meta and ACF relationship
            $current_store_id = get_post_meta($audio_id, 'linked_store_id', true);
            $found_store_id = null;

            if ($current_store_id) {
                $found_store_id = intval($current_store_id);
            }

            // If not found via meta, check if audiologist is in any store's ACF relationship that user owns
            if (!$found_store_id && function_exists('get_field')) {
                $user_stores = get_posts(array(
                    'post_type' => 'hearing-aid-store',
                    'author' => $user_id,
                    'posts_per_page' => -1,
                    'post_status' => 'any'
                ));

                foreach ($user_stores as $user_store) {
                    $store_audiologists = get_field('associated_audiologist', $user_store->ID);
                    if (is_array($store_audiologists)) {
                        // ACF can return objects or IDs - normalize to IDs
                        $store_audio_ids = array_map(function($item) {
                            return is_object($item) ? $item->ID : intval($item);
                        }, $store_audiologists);

                        if (in_array($audio_id, $store_audio_ids)) {
                            $found_store_id = $user_store->ID;
                            break;
                        }
                    }
                }
            }

            if ($found_store_id) {
                $store = get_post($found_store_id);
                if (!$store || $store->post_author != $user_id) {
                    wp_send_json_error(array('message' => 'You can only remove audiologists from stores you manage'));
                }

                // Unlink from both data sources
                delete_post_meta($audio_id, 'linked_store_id');

                // Also remove from ACF relationship field
                if (function_exists('get_field') && function_exists('update_field')) {
                    $current_audiologists = get_field('associated_audiologist', $found_store_id);
                    if (is_array($current_audiologists)) {
                        // ACF can return objects or IDs - normalize to IDs and filter out the one to remove
                        $updated_ids = array();
                        foreach ($current_audiologists as $item) {
                            $item_id = is_object($item) ? $item->ID : intval($item);
                            if ($item_id !== intval($audio_id)) {
                                $updated_ids[] = $item_id;
                            }
                        }
                        update_field('associated_audiologist', $updated_ids, $found_store_id);
                    }
                }
            }

            wp_send_json_success(array('message' => 'Audiologist removed from location'));
        }
    }
    
    /**
     * AJAX: Create new location
     */
    public function ajax_create_location() {
        check_ajax_referer('ham_create_location', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in'));
        }
        
        $user_id = get_current_user_id();
        
        // Check location limit
        $user_stores = get_posts(array(
            'post_type' => 'hearing-aid-store',
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        // Check location limit based on membership type
        global $wpdb;
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE user_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
        
        // Set location limit based on membership type
        if ($membership) {
            switch ($membership->membership_type) {
                case 'verified':
                    $location_limit = 3;
                    break;
                case 'preferred':
                    $location_limit = 10;
                    break;
                default:
                    $location_limit = 1; // Free/unverified
            }
        } else {
            $location_limit = 1; // No membership = free
        }
        
        if (count($user_stores) >= $location_limit) {
            wp_send_json_error(array('message' => 'You have reached your location limit (' . $location_limit . '). Please upgrade your plan.'));
        }
        
        // Create new store post as DRAFT
        $post_data = array(
            'post_title' => sanitize_text_field($_POST['location_name']),
            'post_type' => 'hearing-aid-store',
            'post_status' => 'pending', // Pending admin approval
            'post_author' => $user_id
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id) {
            // Set default member type to match user's membership (already fetched above)
            $type_map = array(
                'preferred' => 'Preferred Provider',
                'verified' => 'Verified Provider',
                'unverified' => 'Non Verified'
            );
            
            $member_type = $membership ? ($type_map[$membership->membership_type] ?? 'Non Verified') : 'Non Verified';
            
            if (function_exists('update_field')) {
                update_field('member_type', $member_type, $post_id);
                
                // Add basic info
                if (!empty($_POST['location_address'])) {
                    update_field('store_address', sanitize_text_field($_POST['location_address']), $post_id);
                }
                if (!empty($_POST['location_phone'])) {
                    update_field('store_phone_number', sanitize_text_field($_POST['location_phone']), $post_id);
                }
                if (!empty($_POST['location_email'])) {
                    update_field('store_email', sanitize_email($_POST['location_email']), $post_id);
                }
            }
            
            // Update membership store_id if this is the first store
            if ($membership && empty($membership->store_id)) {
                $wpdb->update(
                    $wpdb->prefix . 'ham_memberships',
                    array('store_id' => $post_id),
                    array('id' => $membership->id),
                    array('%d'),
                    array('%d')
                );
            }
            
            // Send admin notification
            $admin_email = get_option('admin_email');
            $subject = 'New Store Location Pending Approval';
            $message = "A new store location has been submitted and requires approval.\n\n";
            $message .= "Store: " . get_the_title($post_id) . "\n";
            $message .= "User: " . wp_get_current_user()->display_name . "\n";
            $message .= "Edit: " . admin_url('post.php?post=' . $post_id . '&action=edit');
            
            wp_mail($admin_email, $subject, $message);
            
            wp_send_json_success(array(
                'message' => 'Location created! It will be live once approved by admin.',
                'post_id' => $post_id
            ));
        }
        
        wp_send_json_error(array('message' => 'Failed to create location'));
    }
    
    /**
     * AJAX: Create new audiologist
     */
    public function ajax_create_audiologist() {
        check_ajax_referer('ham_create_audiologist', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in'));
        }
        
        $user_id = get_current_user_id();
        
        // Create new audiologist post as PENDING
        $post_data = array(
            'post_title' => sanitize_text_field($_POST['audiologist_name']),
            'post_type' => 'audiologist',
            'post_status' => 'pending', // Pending admin approval
            'post_author' => $user_id,
            'post_excerpt' => sanitize_text_field($_POST['audiologist_credentials'])
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id) {
            // Save bio to ACF field
            if (function_exists('update_field') && !empty($_POST['audiologist_bio'])) {
                update_field('audiologists_bio', wp_kses_post($_POST['audiologist_bio']), $post_id);
            }
            
            // Link to store if selected
            if (!empty($_POST['linked_store'])) {
                $store_id = intval($_POST['linked_store']);
                
                // Verify user owns this store
                $store = get_post($store_id);
                if ($store && $store->post_author == $user_id) {
                    update_post_meta($post_id, 'linked_store_id', $store_id);
                }
            }
            
            // Send admin notification
            $admin_email = get_option('admin_email');
            $subject = 'New Audiologist Profile Pending Approval';
            $message = "A new audiologist profile has been submitted and requires approval.\n\n";
            $message .= "Name: " . get_the_title($post_id) . "\n";
            $message .= "User: " . wp_get_current_user()->display_name . "\n";
            $message .= "Edit: " . admin_url('post.php?post=' . $post_id . '&action=edit');
            
            wp_mail($admin_email, $subject, $message);
            
            wp_send_json_success(array(
                'message' => 'Audiologist added! Profile will be live once approved by admin.',
                'post_id' => $post_id
            ));
        }
        
        wp_send_json_error(array('message' => 'Failed to create audiologist profile'));
    }
    
    /**
     * AJAX: Save store information
     */
    public function ajax_save_store() {
        $store_id = intval($_POST['store_id']);
        
        // Verify nonce
        check_ajax_referer('ham_save_store_' . $store_id, 'nonce');
        
        // Check if user owns this store
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in'));
        }
        
        $post = get_post($store_id);
        if (!$post || $post->post_author != get_current_user_id()) {
            wp_send_json_error(array('message' => 'You do not have permission to edit this store'));
        }
        
        // Update post title and content
        wp_update_post(array(
            'ID' => $store_id,
            'post_title' => sanitize_text_field($_POST['post_title']),
            'post_content' => wp_kses_post($_POST['post_content'])
        ));
        
        // Update ACF fields
        $text_fields = array(
            'store_address', 'store_phone_number', 'store_website', 'store_email',
            'video_1', 'video_2',
            'store_hours_sunday', 'store_hours_monday', 'store_hours_tuesday', 
            'store_hours_wednesday', 'store_hours_thursday', 'store_hours_friday', 
            'store_hours_saturday', 'special_hours', 'special_notes'
        );
        
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                update_field($field, sanitize_text_field($_POST[$field]), $store_id);
            }
        }
        
        // Update checkbox fields
        $checkbox_fields = array(
            'featured_services',
            'supported_hearing_aid_brands',
            'services_provided',
            'hearing_aid_services',
            'custom_ear_mold_services'
        );
        
        foreach ($checkbox_fields as $field) {
            $value = isset($_POST[$field]) ? array_map('sanitize_text_field', $_POST[$field]) : array();
            update_field($field, $value, $store_id);
        }
        
        wp_send_json_success(array('message' => 'Store updated successfully'));
    }
    
    /**
     * AJAX: Create subscription (checkout)
     */
    public function ajax_create_subscription() {
        check_ajax_referer('ham_checkout', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in'));
        }
        
        $user_id = get_current_user_id();
        $payment_method_id = sanitize_text_field($_POST['payment_method_id']);
        $plan_type = sanitize_text_field($_POST['plan_type']);
        $billing_cycle = sanitize_text_field($_POST['billing_cycle']);
        $price_id = sanitize_text_field($_POST['price_id']);
        $store_id = intval($_POST['store_id']);
        
        // Validate
        if (!in_array($plan_type, array('verified', 'preferred'))) {
            wp_send_json_error(array('message' => 'Invalid plan type'));
        }
        
        if (!in_array($billing_cycle, array('monthly', 'yearly'))) {
            wp_send_json_error(array('message' => 'Invalid billing cycle'));
        }
        
        if (empty($price_id)) {
            wp_send_json_error(array('message' => 'Price ID not configured. Please contact support.'));
        }
        
        // Check if user already has active membership
        $existing = $this->membership->get_user_membership($user_id);
        if ($existing && $existing->status === 'active') {
            wp_send_json_error(array('message' => 'You already have an active membership'));
        }
        
        // Create Stripe subscription
        $result = $this->stripe->create_subscription($user_id, $price_id, $payment_method_id);
        
        if (!$result['success']) {
            wp_send_json_error(array('message' => $result['message']));
        }
        
        // Get price
        $price = $this->membership->get_plan_price($plan_type, $billing_cycle);
        
        // Create membership in database
        $membership_result = $this->membership->create_membership(
            $user_id,
            $store_id,
            $plan_type,
            $billing_cycle,
            array(
                'customer_id' => $result['customer_id'],
                'subscription_id' => $result['subscription_id']
            )
        );
        
        if (!$membership_result['success']) {
            wp_send_json_error(array('message' => 'Payment successful but membership creation failed. Please contact support.'));
        }
        
        // Success! Redirect to success page
        wp_send_json_success(array(
            'redirect_url' => home_url('/membership-success/?membership_id=' . $membership_result['membership_id'])
        ));
    }
    
    /**
     * Initialize classes
     */
    public function init_classes() {
        if (class_exists('HAM_Database')) {
            $this->db = new HAM_Database();
        }
        
        if (class_exists('HAM_Membership')) {
            $this->membership = new HAM_Membership();
        }
        
        if (class_exists('HAM_Stripe')) {
            $this->stripe = new HAM_Stripe();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        try {
            // Load database class if not loaded
            if (!class_exists('HAM_Database')) {
                require_once HAM_PLUGIN_DIR . 'includes/class-database.php';
            }
            
            // Create database tables
            $db = new HAM_Database();
            $db->create_tables();
            $db->create_claims_table(); // Add claims table
            
            // Set default options
            $this->set_default_options();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Set activation flag
            update_option('ham_activated', true);
            
        } catch (Exception $e) {
            error_log('HA Membership Activation Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
        delete_option('ham_activated');
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'ham_verified_monthly_price' => 49.00,
            'ham_verified_yearly_price' => 490.00,
            'ham_preferred_monthly_price' => 99.00,
            'ham_preferred_yearly_price' => 990.00,
            'ham_stripe_test_mode' => 1,
            'ham_currency' => 'USD',
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('ha-membership', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Only on relevant pages
        if (is_page() || is_singular('hearing-aid-store')) {
            wp_enqueue_style(
                'ham-styles',
                HAM_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                HAM_VERSION
            );
            
            wp_enqueue_script(
                'ham-scripts',
                HAM_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                HAM_VERSION,
                true
            );
            
            wp_localize_script('ham-scripts', 'hamData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ham_nonce'),
            ));
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'ha-membership') !== false || strpos($hook, 'ham-') !== false) {
            wp_enqueue_style(
                'ham-admin-styles',
                HAM_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                HAM_VERSION
            );
            
            wp_enqueue_script(
                'ham-admin-scripts',
                HAM_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                HAM_VERSION,
                true
            );
        }
    }
    
    /**
     * AJAX: Upgrade membership
     */
    public function ajax_upgrade_membership() {
        check_ajax_referer('ham_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        wp_send_json_success(array('message' => 'Upgrade functionality coming soon'));
    }
    
    /**
     * AJAX: Cancel membership
     */
    public function ajax_cancel_membership() {
        check_ajax_referer('ham_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        wp_send_json_success(array('message' => 'Cancel functionality coming soon'));
    }
    
    /**
     * AJAX: Update payment method
     */
    public function ajax_update_payment_method() {
        check_ajax_referer('ham_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        wp_send_json_success(array('message' => 'Update payment functionality coming soon'));
    }
}

/**
 * Initialize plugin
 */
function HA_Membership() {
    return HA_Membership::instance();
}

// Start the plugin
HA_Membership();

// Add activation notice
add_action('admin_notices', 'ham_activation_notice');
function ham_activation_notice() {
    if (get_option('ham_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>HA Membership Plugin Activated!</strong></p>
            <p>Go to <a href="<?php echo admin_url('admin.php?page=ham-settings'); ?>">Settings → HA Membership</a> to configure Stripe and pricing.</p>
        </div>
        <?php
        delete_option('ham_activated');
    }
}
