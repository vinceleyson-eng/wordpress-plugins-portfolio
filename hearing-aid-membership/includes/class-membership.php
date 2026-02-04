<?php
/**
 * Membership Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class HAM_Membership {
    
    private $db;
    
    public function __construct() {
        // Delay initialization to avoid circular reference
        add_action('init', array($this, 'init_db'), 1);
    }
    
    public function init_db() {
        if (function_exists('HA_Membership')) {
            $this->db = HA_Membership()->db;
        }
    }
    
    /**
     * Get user's active membership
     */
    public function get_user_membership($user_id) {
        if (!isset($this->db)) {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE user_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
                $user_id
            ));
        }
        return $this->db->get_active_membership($user_id);
    }
    
    /**
     * Check if user has active membership
     */
    public function has_active_membership($user_id) {
        $membership = $this->get_user_membership($user_id);
        return !empty($membership) && $membership->status === 'active';
    }
    
    /**
     * Get membership type
     */
    public function get_membership_type($user_id) {
        $membership = $this->get_user_membership($user_id);
        return $membership ? $membership->membership_type : 'unverified';
    }
    
    /**
     * Create new membership
     */
    public function create_membership($user_id, $store_id, $plan_type, $billing_cycle, $stripe_data = array()) {
        // Get pricing
        $price = $this->get_plan_price($plan_type, $billing_cycle);
        
        // Calculate dates
        $start_date = current_time('mysql');
        $end_date = $billing_cycle === 'yearly' 
            ? date('Y-m-d H:i:s', strtotime('+1 year'))
            : date('Y-m-d H:i:s', strtotime('+1 month'));
        
        // Prepare data
        $data = array(
            'user_id' => $user_id,
            'store_id' => $store_id,
            'membership_type' => $plan_type,
            'billing_cycle' => $billing_cycle,
            'price' => $price,
            'status' => 'active',
            'start_date' => $start_date,
            'end_date' => $end_date,
            'next_billing_date' => $end_date,
            'auto_renew' => 1,
            'stripe_customer_id' => isset($stripe_data['customer_id']) ? $stripe_data['customer_id'] : null,
            'stripe_subscription_id' => isset($stripe_data['subscription_id']) ? $stripe_data['subscription_id'] : null,
        );
        
        // Create membership
        if (!isset($this->db)) {
            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . 'ham_memberships',
                $data,
                array('%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
            );
            
            if ($result) {
                $membership_id = $wpdb->insert_id;
                
                // Update WordPress post meta
                $this->update_store_member_type($store_id, $plan_type);
                
                // Send welcome email
                $this->send_welcome_email($user_id, $plan_type);
                
                // Log activity
                $this->log_activity($user_id, 'membership_created', "Created $plan_type membership");
                
                return array('success' => true, 'membership_id' => $membership_id);
            }
            
            return array('success' => false, 'message' => 'Failed to create membership');
        }
        
        $created = $this->db->create_membership($data);
        
        if ($created) {
            // Update WordPress post meta
            $this->update_store_member_type($store_id, $plan_type);
            
            // Send welcome email
            $this->send_welcome_email($user_id, $plan_type);
            
            // Log activity
            $this->log_activity($user_id, 'membership_created', "Created $plan_type membership");
            
            return array('success' => true, 'membership_id' => $this->db->wpdb->insert_id);
        }
        
        return array('success' => false, 'message' => 'Failed to create membership');
    }
    
    /**
     * Upgrade membership
     */
    public function upgrade_membership($user_id, $new_plan, $billing_cycle) {
        $current = $this->get_user_membership($user_id);
        
        if (!$current) {
            return array('success' => false, 'message' => 'No active membership found');
        }
        
        $new_price = $this->get_plan_price($new_plan, $billing_cycle);
        
        // Update membership
        if (!isset($this->db)) {
            global $wpdb;
            $updated = $wpdb->update(
                $wpdb->prefix . 'ham_memberships',
                array(
                    'membership_type' => $new_plan,
                    'billing_cycle' => $billing_cycle,
                    'price' => $new_price
                ),
                array('id' => $current->id),
                array('%s', '%s', '%f'),
                array('%d')
            );
        } else {
            $updated = $this->db->update_membership($current->id, array(
                'membership_type' => $new_plan,
                'billing_cycle' => $billing_cycle,
                'price' => $new_price
            ));
        }
        
        if ($updated !== false) {
            // Update WordPress post meta
            $this->update_store_member_type($current->store_id, $new_plan);
            
            // Send email
            $this->send_upgrade_email($user_id, $new_plan);
            
            // Log activity
            $this->log_activity($user_id, 'membership_upgraded', "Upgraded to $new_plan");
            
            return array('success' => true, 'message' => 'Membership upgraded successfully');
        }
        
        return array('success' => false, 'message' => 'Failed to upgrade membership');
    }
    
    /**
     * Cancel membership
     */
    public function cancel_membership($user_id, $immediate = false) {
        $membership = $this->get_user_membership($user_id);
        
        if (!$membership) {
            return array('success' => false, 'message' => 'No active membership found');
        }
        
        if ($immediate) {
            // Cancel immediately
            if (!isset($this->db)) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'ham_memberships',
                    array(
                        'status' => 'cancelled',
                        'end_date' => current_time('mysql'),
                        'auto_renew' => 0
                    ),
                    array('id' => $membership->id),
                    array('%s', '%s', '%d'),
                    array('%d')
                );
            } else {
                $this->db->update_membership($membership->id, array(
                    'status' => 'cancelled',
                    'end_date' => current_time('mysql'),
                    'auto_renew' => 0
                ));
            }
            
            // Downgrade to unverified
            $this->update_store_member_type($membership->store_id, 'unverified');
            
            $message = 'Membership cancelled immediately';
        } else {
            // Cancel at end of period
            if (!isset($this->db)) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'ham_memberships',
                    array('auto_renew' => 0),
                    array('id' => $membership->id),
                    array('%d'),
                    array('%d')
                );
            } else {
                $this->db->update_membership($membership->id, array(
                    'auto_renew' => 0
                ));
            }
            
            $message = 'Membership will cancel at end of billing period';
        }
        
        // Send email
        $this->send_cancellation_email($user_id, $immediate);
        
        // Log activity
        $this->log_activity($user_id, 'membership_cancelled', $message);
        
        return array('success' => true, 'message' => $message);
    }
    
    /**
     * Renew membership (called by Stripe webhook)
     */
    public function renew_membership($membership_id) {
        if (!isset($this->db)) {
            global $wpdb;
            $membership = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE id = %d",
                $membership_id
            ));
        } else {
            $membership = $this->db->get_membership($membership_id);
        }
        
        if (!$membership) {
            return false;
        }
        
        // Calculate next billing date
        $next_billing = $membership->billing_cycle === 'yearly'
            ? date('Y-m-d H:i:s', strtotime($membership->next_billing_date . ' +1 year'))
            : date('Y-m-d H:i:s', strtotime($membership->next_billing_date . ' +1 month'));
        
        // Update membership
        if (!isset($this->db)) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'ham_memberships',
                array(
                    'last_payment_date' => current_time('mysql'),
                    'next_billing_date' => $next_billing,
                    'status' => 'active'
                ),
                array('id' => $membership_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $this->db->update_membership($membership_id, array(
                'last_payment_date' => current_time('mysql'),
                'next_billing_date' => $next_billing,
                'status' => 'active'
            ));
        }
        
        // Send email
        $this->send_renewal_email($membership->user_id);
        
        return true;
    }
    
    /**
     * Handle failed payment
     */
    public function handle_failed_payment($membership_id) {
        if (!isset($this->db)) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'ham_memberships',
                array('status' => 'past_due'),
                array('id' => $membership_id),
                array('%s'),
                array('%d')
            );
            
            $membership = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE id = %d",
                $membership_id
            ));
        } else {
            $this->db->update_membership($membership_id, array(
                'status' => 'past_due'
            ));
            
            $membership = $this->db->get_membership($membership_id);
        }
        
        $this->send_payment_failed_email($membership->user_id);
    }
    
    /**
     * Update WordPress post meta (member_type)
     */
    private function update_store_member_type($store_id, $member_type) {
        // Map to display names
        $type_map = array(
            'preferred' => 'Preferred Provider',
            'verified' => 'Verified Provider',
            'unverified' => 'Non Verified'
        );
        
        $display_type = isset($type_map[$member_type]) ? $type_map[$member_type] : 'Non Verified';
        
        if (function_exists('update_field')) {
            update_field('member_type', $display_type, $store_id);
        } else {
            update_post_meta($store_id, 'member_type', $display_type);
        }
        
        return true;
    }
    
    /**
     * Get plan price
     */
    public function get_plan_price($plan_type, $billing_cycle) {
        $option_key = 'ham_' . $plan_type . '_' . $billing_cycle . '_price';
        return floatval(get_option($option_key, 0));
    }
    
    /**
     * Get all plan options
     */
    public function get_plans() {
        return array(
            'verified' => array(
                'name' => 'Verified Provider',
                'monthly' => floatval(get_option('ham_verified_monthly_price', 49.00)),
                'yearly' => floatval(get_option('ham_verified_yearly_price', 490.00)),
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
                'monthly' => floatval(get_option('ham_preferred_monthly_price', 99.00)),
                'yearly' => floatval(get_option('ham_preferred_yearly_price', 990.00)),
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
    }
    
    /**
     * Email functions
     */
    private function send_welcome_email($user_id, $plan) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = 'Welcome to ' . get_bloginfo('name') . ' - ' . ucfirst($plan) . ' Membership';
        $message = '<h2>Welcome!</h2><p>Hi ' . $user->display_name . ',</p><p>Your ' . ucfirst($plan) . ' membership is now active.</p>';
        
        wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    private function send_upgrade_email($user_id, $plan) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = 'Membership Upgraded to ' . ucfirst($plan);
        $message = '<h2>Membership Upgraded</h2><p>Hi ' . $user->display_name . ',</p><p>Your membership has been upgraded to ' . ucfirst($plan) . '.</p>';
        
        wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    private function send_cancellation_email($user_id, $immediate) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = 'Membership Cancellation Confirmation';
        $when = $immediate ? 'immediately' : 'at the end of your billing period';
        $message = '<h2>Membership Cancelled</h2><p>Hi ' . $user->display_name . ',</p><p>Your membership has been cancelled ' . $when . '.</p>';
        
        wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    private function send_renewal_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = 'Membership Renewed Successfully';
        $message = '<h2>Membership Renewed</h2><p>Hi ' . $user->display_name . ',</p><p>Your membership has been renewed successfully.</p>';
        
        wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    private function send_payment_failed_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = 'Payment Failed - Action Required';
        $message = '<h2>Payment Failed</h2><p>Hi ' . $user->display_name . ',</p><p>We were unable to process your membership payment. Please update your payment method.</p>';
        
        wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Log activity
     */
    private function log_activity($user_id, $action, $description) {
        do_action('ham_log_activity', $user_id, $action, $description);
    }
    
    /**
     * Get user's stores (hearing-aid-store posts)
     */
    public function get_user_stores($user_id) {
        $args = array(
            'post_type' => 'hearing-aid-store',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        return get_posts($args);
    }
}