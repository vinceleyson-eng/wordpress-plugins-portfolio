<?php
/**
 * Stripe Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class HAM_Stripe {
    
    private $secret_key;
    private $publishable_key;
    private $test_mode;
    
    public function __construct() {
        $this->test_mode = get_option('ham_stripe_test_mode', 1);
        
        if ($this->test_mode) {
            $this->secret_key = get_option('ham_stripe_test_secret_key', '');
            $this->publishable_key = get_option('ham_stripe_test_publishable_key', '');
        } else {
            $this->secret_key = get_option('ham_stripe_live_secret_key', '');
            $this->publishable_key = get_option('ham_stripe_live_publishable_key', '');
        }
        
        // Register webhook handler
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }
    
    /**
     * Get publishable key
     */
    public function get_publishable_key() {
        return $this->publishable_key;
    }
    
    /**
     * Create Stripe customer
     */
    public function create_customer($user_id) {
        $user = get_userdata($user_id);
        
        $response = $this->stripe_request('customers', 'POST', array(
            'email' => $user->user_email,
            'name' => $user->display_name,
            'metadata' => array(
                'wordpress_user_id' => $user_id
            )
        ));
        
        if (isset($response['id'])) {
            update_user_meta($user_id, 'stripe_customer_id', $response['id']);
            return $response['id'];
        }
        
        return false;
    }
    
    /**
     * Get or create customer
     */
    public function get_customer_id($user_id) {
        $customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
        
        if (empty($customer_id)) {
            $customer_id = $this->create_customer($user_id);
        }
        
        return $customer_id;
    }
    
    /**
     * Create subscription
     */
    public function create_subscription($user_id, $price_id, $payment_method_id = null) {
        $customer_id = $this->get_customer_id($user_id);
        
        if (!$customer_id) {
            return array('success' => false, 'message' => 'Failed to create customer');
        }
        
        $data = array(
            'customer' => $customer_id,
            'items' => array(
                array('price' => $price_id)
            ),
            'metadata' => array(
                'wordpress_user_id' => $user_id
            ),
            'payment_behavior' => 'default_incomplete',
            'expand' => array('latest_invoice.payment_intent')
        );
        
        if ($payment_method_id) {
            $data['default_payment_method'] = $payment_method_id;
        }
        
        $response = $this->stripe_request('subscriptions', 'POST', $data);
        
        if (isset($response['id'])) {
            return array(
                'success' => true,
                'subscription_id' => $response['id'],
                'customer_id' => $customer_id,
                'client_secret' => $response['latest_invoice']['payment_intent']['client_secret']
            );
        }
        
        return array('success' => false, 'message' => 'Failed to create subscription');
    }
    
    /**
     * Create checkout session
     */
    public function create_checkout_session($user_id, $price_id, $plan_type, $store_id) {
        $customer_id = $this->get_customer_id($user_id);
        
        $success_url = add_query_arg(array(
            'ham_payment' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}'
        ), home_url('/membership-success/'));
        
        $cancel_url = add_query_arg(array(
            'ham_payment' => 'cancelled'
        ), home_url('/pricing/'));
        
        $data = array(
            'customer' => $customer_id,
            'line_items' => array(
                array(
                    'price' => $price_id,
                    'quantity' => 1
                )
            ),
            'mode' => 'subscription',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'metadata' => array(
                'wordpress_user_id' => $user_id,
                'plan_type' => $plan_type,
                'store_id' => $store_id
            )
        );
        
        $response = $this->stripe_request('checkout/sessions', 'POST', $data);
        
        if (isset($response['id'])) {
            return array(
                'success' => true,
                'session_id' => $response['id'],
                'url' => $response['url']
            );
        }
        
        return array('success' => false, 'message' => 'Failed to create checkout session');
    }
    
    /**
     * Cancel subscription
     */
    public function cancel_subscription($subscription_id, $immediate = false) {
        $endpoint = 'subscriptions/' . $subscription_id;
        
        if ($immediate) {
            $response = $this->stripe_request($endpoint, 'DELETE');
        } else {
            $response = $this->stripe_request($endpoint, 'POST', array(
                'cancel_at_period_end' => true
            ));
        }
        
        return isset($response['id']);
    }
    
    /**
     * Update payment method
     */
    public function update_payment_method($user_id, $payment_method_id) {
        $customer_id = get_user_meta($user_id, 'stripe_customer_id', true);
        
        if (!$customer_id) {
            return array('success' => false, 'message' => 'No customer ID found');
        }
        
        // Attach payment method to customer
        $this->stripe_request('payment_methods/' . $payment_method_id . '/attach', 'POST', array(
            'customer' => $customer_id
        ));
        
        // Set as default
        $response = $this->stripe_request('customers/' . $customer_id, 'POST', array(
            'invoice_settings' => array(
                'default_payment_method' => $payment_method_id
            )
        ));
        
        if (isset($response['id'])) {
            return array('success' => true, 'message' => 'Payment method updated');
        }
        
        return array('success' => false, 'message' => 'Failed to update payment method');
    }
    
    /**
     * Make Stripe API request
     */
    private function stripe_request($endpoint, $method = 'GET', $data = array()) {
        $url = 'https://api.stripe.com/v1/' . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 30
        );
        
        if (!empty($data)) {
            $args['body'] = http_build_query($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route('ham/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_webhook($request) {
        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe_signature');
        $webhook_secret = get_option('ham_stripe_webhook_secret', '');
        
        try {
            // Verify webhook signature
            if (!empty($webhook_secret)) {
                // In production, verify signature here
                // For now, we'll process the event
            }
            
            $event = json_decode($payload, true);
            
            // Handle different event types
            switch ($event['type']) {
                case 'checkout.session.completed':
                    $this->handle_checkout_completed($event['data']['object']);
                    break;
                    
                case 'invoice.payment_succeeded':
                    $this->handle_payment_succeeded($event['data']['object']);
                    break;
                    
                case 'invoice.payment_failed':
                    $this->handle_payment_failed($event['data']['object']);
                    break;
                    
                case 'customer.subscription.updated':
                    $this->handle_subscription_updated($event['data']['object']);
                    break;
                    
                case 'customer.subscription.deleted':
                    $this->handle_subscription_deleted($event['data']['object']);
                    break;
            }
            
            return new WP_REST_Response(array('success' => true), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array('error' => $e->getMessage()), 400);
        }
    }
    
    /**
     * Handle checkout completed
     */
    private function handle_checkout_completed($session) {
        $user_id = $session['metadata']['wordpress_user_id'];
        $plan_type = $session['metadata']['plan_type'];
        $store_id = $session['metadata']['store_id'];
        $subscription_id = $session['subscription'];
        $customer_id = $session['customer'];
        
        // Get billing cycle from subscription
        $subscription = $this->stripe_request('subscriptions/' . $subscription_id, 'GET');
        $interval = $subscription['items']['data'][0]['price']['recurring']['interval'];
        $billing_cycle = $interval === 'year' ? 'yearly' : 'monthly';
        
        // Create membership
        $membership = HA_Membership()->membership;
        $membership->create_membership($user_id, $store_id, $plan_type, $billing_cycle, array(
            'customer_id' => $customer_id,
            'subscription_id' => $subscription_id
        ));
    }
    
    /**
     * Handle payment succeeded
     */
    private function handle_payment_succeeded($invoice) {
        if (!isset($invoice['subscription'])) {
            return;
        }
        
        $subscription_id = $invoice['subscription'];
        
        // Find membership by subscription ID
        global $wpdb;
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE stripe_subscription_id = %s",
            $subscription_id
        ));
        
        if ($membership) {
            // Renew membership
            HA_Membership()->membership->renew_membership($membership->id);
        }
    }
    
    /**
     * Handle payment failed
     */
    private function handle_payment_failed($invoice) {
        if (!isset($invoice['subscription'])) {
            return;
        }
        
        $subscription_id = $invoice['subscription'];
        
        global $wpdb;
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE stripe_subscription_id = %s",
            $subscription_id
        ));
        
        if ($membership) {
            // Check if auto-downgrade is enabled
            $auto_downgrade = get_option('ham_auto_downgrade', 1);
            
            if (!$auto_downgrade) {
                // Just mark as past_due, don't downgrade
                $wpdb->update(
                    $wpdb->prefix . 'ham_memberships',
                    array('status' => 'past_due'),
                    array('id' => $membership->id)
                );
                return;
            }
            
            // Get attempt count
            $attempt_count = isset($invoice['attempt_count']) ? $invoice['attempt_count'] : 1;
            
            // Get max retry count from settings (default 3)
            $max_retries = get_option('ham_payment_retry_count', 3);
            
            // After max failed attempts, downgrade to Non Verified
            if ($attempt_count >= $max_retries) {
                // Update membership to cancelled
                $wpdb->update(
                    $wpdb->prefix . 'ham_memberships',
                    array(
                        'status' => 'cancelled',
                        'end_date' => current_time('mysql'),
                        'auto_renew' => 0
                    ),
                    array('id' => $membership->id)
                );
                
                // Downgrade store to Non Verified
                if (function_exists('update_field')) {
                    update_field('member_type', 'Non Verified', $membership->store_id);
                } else {
                    update_post_meta($membership->store_id, 'member_type', 'Non Verified');
                }
                
                // Send cancellation email
                $user = get_userdata($membership->user_id);
                if ($user) {
                    $this->send_membership_cancelled_email($user->user_email, $user->display_name, 'payment_failure');
                }
                
                // Log activity
                $this->log_payment_failure($membership->user_id, "Membership cancelled and downgraded to Non Verified after {$attempt_count} failed payment attempts");
                
            } else {
                // Mark as past_due on failure
                $wpdb->update(
                    $wpdb->prefix . 'ham_memberships',
                    array('status' => 'past_due'),
                    array('id' => $membership->id)
                );
                
                // Send payment failed warning email
                $user = get_userdata($membership->user_id);
                if ($user) {
                    $this->send_payment_failed_warning_email($user->user_email, $user->display_name, $attempt_count, $max_retries);
                }
                
                // Log the failure
                $this->log_payment_failure($membership->user_id, "Payment attempt {$attempt_count}/{$max_retries} failed");
            }
        }
    }
    
    /**
     * Handle subscription updated
     */
    private function handle_subscription_updated($subscription) {
        // Handle plan changes, etc.
    }
    
    /**
     * Handle subscription deleted
     */
    private function handle_subscription_deleted($subscription) {
        $subscription_id = $subscription['id'];
        
        global $wpdb;
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE stripe_subscription_id = %s",
            $subscription_id
        ));
        
        if ($membership) {
            // Cancel membership
            $wpdb->update(
                $wpdb->prefix . 'ham_memberships',
                array(
                    'status' => 'cancelled',
                    'end_date' => current_time('mysql'),
                    'auto_renew' => 0
                ),
                array('id' => $membership->id)
            );
            
            // Downgrade to Non Verified
            if (function_exists('update_field')) {
                update_field('member_type', 'Non Verified', $membership->store_id);
            } else {
                update_post_meta($membership->store_id, 'member_type', 'Non Verified');
            }
        }
    }
    
    /**
     * Send payment failed warning email
     */
    private function send_payment_failed_warning_email($email, $name, $attempt, $max_retries) {
        $subject = 'Payment Failed - Action Required';
        $message = "
            <h2>Payment Failed</h2>
            <p>Hi {$name},</p>
            <p>We were unable to process your membership payment (Attempt {$attempt}/{$max_retries}).</p>
            <p><strong>What happens next:</strong></p>
            <ul>
                <li>Stripe will retry the payment automatically</li>
                <li>After {$max_retries} failed attempts, your membership will be cancelled</li>
                <li>Your store will be downgraded to Non Verified status</li>
                <li>You will lose all premium listing benefits</li>
            </ul>
            <p><strong>To prevent this:</strong></p>
            <p>Please update your payment method or contact your bank to ensure the payment can be processed.</p>
            <p><a href='" . home_url('/my-membership/') . "' style='background: #d63638; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 0;'>Update Payment Method Now</a></p>
            <p>If you have questions or need assistance, please contact us immediately.</p>
            <p style='color: #666; font-size: 12px; margin-top: 20px;'>This is attempt {$attempt} of {$max_retries}. Act now to keep your premium status!</p>
        ";
        
        wp_mail($email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Send membership cancelled email
     */
    private function send_membership_cancelled_email($email, $name, $reason) {
        $subject = 'Membership Cancelled';
        $reason_text = $reason === 'payment_failure' 
            ? 'due to multiple failed payment attempts' 
            : 'at your request';
            
        $message = "
            <h2>Membership Cancelled</h2>
            <p>Hi {$name},</p>
            <p>Your membership has been cancelled {$reason_text}.</p>
            <p><strong>What this means:</strong></p>
            <ul>
                <li>Your store has been changed to Non Verified status</li>
                <li>Premium listing benefits are no longer active</li>
                <li>No further charges will be made</li>
            </ul>
            <p><strong>Want to reactivate?</strong></p>
            <p>You can restart your membership at any time by visiting our pricing page.</p>
            <p><a href='" . home_url('/pricing/') . "' style='background: #2271b1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;'>View Plans</a></p>
            <p>Thank you for being part of our network.</p>
        ";
        
        wp_mail($email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Log payment failure
     */
    private function log_payment_failure($user_id, $message) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'ham_transactions',
            array(
                'user_id' => $user_id,
                'amount' => 0,
                'transaction_type' => 'payment_failed',
                'status' => 'failed',
                'description' => $message,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%f', '%s', '%s', '%s', '%s')
        );
    }
}
