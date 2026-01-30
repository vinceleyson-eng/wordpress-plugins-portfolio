<?php
if (!defined('ABSPATH')) exit;

class WCSI_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'stripe_installments';
        $this->method_title = 'Stripe Installments (Pay in 4)';
        $this->method_description = 'Pay in 4 interest-free installments';
        $this->has_fields = true;
        $this->supports = array('products', 'refunds');
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title', 'Pay in 4 Installments');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled', 'no');
        $this->testmode = $this->get_option('testmode', 'yes') === 'yes';
        $this->secret_key = $this->testmode ? $this->get_option('test_secret_key') : $this->get_option('live_secret_key');
        $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('live_publishable_key');
        $this->min_amount = floatval($this->get_option('min_amount', 2));
        $this->max_amount = floatval($this->get_option('max_amount', 100000));
        $this->payment_interval = intval($this->get_option('payment_interval', 14));
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', array($this, 'enqueue_scripts'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array('title' => 'Enable', 'type' => 'checkbox', 'default' => 'no'),
            'title' => array('title' => 'Title', 'type' => 'text', 'default' => 'Pay in 4 Installments'),
            'description' => array('title' => 'Description', 'type' => 'textarea', 'default' => 'Split into 4 interest-free payments.'),
            'testmode' => array('title' => 'Test Mode', 'type' => 'checkbox', 'default' => 'yes', 'label' => 'Enable test mode'),
            'test_publishable_key' => array('title' => 'Test Publishable Key', 'type' => 'text'),
            'test_secret_key' => array('title' => 'Test Secret Key', 'type' => 'password'),
            'live_publishable_key' => array('title' => 'Live Publishable Key', 'type' => 'text'),
            'live_secret_key' => array('title' => 'Live Secret Key', 'type' => 'password'),
            'min_amount' => array('title' => 'Minimum Amount', 'type' => 'number', 'default' => '2', 'description' => 'Minimum $2 (each installment must be at least $0.50)'),
            'max_amount' => array('title' => 'Maximum Amount', 'type' => 'number', 'default' => '100000'),
            'payment_interval' => array('title' => 'Days Between Payments', 'type' => 'number', 'default' => '14'),
        );
    }
    
    public function is_available() {
        if ($this->enabled !== 'yes') return false;
        if (empty($this->secret_key) || empty($this->publishable_key)) return false;
        
        if (WC()->cart) {
            $total = floatval(WC()->cart->get_total('edit'));
            if ($total < $this->min_amount || $total > $this->max_amount) return false;
        }
        return true;
    }
    
    public function enqueue_scripts() {
        if (!is_checkout() && !has_block('woocommerce/checkout')) return;
        if ($this->enabled !== 'yes' || empty($this->publishable_key)) return;
        
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
        wp_enqueue_script('wcsi-checkout', WCSI_PLUGIN_URL . 'assets/js/checkout.js', array('jquery', 'stripe-js'), WCSI_VERSION . '.' . time(), true);
        wp_localize_script('wcsi-checkout', 'wcsi_params', array('publishable_key' => $this->publishable_key));
    }
    
    public function payment_fields() {
        if (!WC()->cart) return;
        
        $total = floatval(WC()->cart->get_total('edit'));
        $installment = floor($total / 4); // Round down to full dollar
        $last_installment = $total - ($installment * 3); // Last payment gets remainder
        $interval = $this->payment_interval;
        
        echo '<p>' . esc_html($this->description) . '</p>';
        echo '<div style="background:#f5f5f5;padding:15px;border-radius:8px;margin:10px 0;">';
        echo '<strong>Payment Schedule:</strong><br>';
        echo '1st Payment (Today): ' . wc_price($installment) . '<br>';
        echo '2nd Payment (' . date_i18n('M j', strtotime("+{$interval} days")) . '): ' . wc_price($installment) . '<br>';
        echo '3rd Payment (' . date_i18n('M j', strtotime("+" . ($interval*2) . " days")) . '): ' . wc_price($installment) . '<br>';
        echo '4th Payment (' . date_i18n('M j', strtotime("+" . ($interval*3) . " days")) . '): ' . wc_price($last_installment) . '<br>';
        echo '<strong>Total: ' . wc_price($total) . '</strong><br>';
        echo '<small style="color:green;">0% interest • No fees</small>';
        echo '</div>';
        
        echo '<div style="margin:15px 0;">';
        echo '<label style="display:block;margin-bottom:8px;font-weight:600;">Card Details</label>';
        echo '<div id="wcsi-card-element" style="padding:12px;border:1px solid #d4d4d4;border-radius:6px;background:#fff;min-height:24px;">';
        echo '<span class="wcsi-card-loading">Loading payment form...</span>';
        echo '</div>';
        echo '<div id="wcsi-card-errors" style="color:#dc3545;margin-top:8px;font-size:14px;"></div>';
        echo '</div>';
        
        echo '<input type="hidden" name="wcsi_payment_method" id="wcsi_payment_method" value="">';
    }
    
    public function validate_fields() {
        if (empty($_POST['wcsi_payment_method'])) {
            wc_add_notice('Please enter card details.', 'error');
            return false;
        }
        return true;
    }
    
    public function process_payment($order_id) {
        global $wpdb;
        $order = wc_get_order($order_id);
        
        // Check if Stripe library is available
        if (!class_exists('\Stripe\Stripe')) {
            wc_add_notice('Payment system not configured correctly. Please contact support.', 'error');
            $order->add_order_note('ERROR: Stripe PHP library not loaded.');
            return array('result' => 'fail');
        }
        
        // Get payment method from POST
        $pm_id = isset($_POST['wcsi_payment_method']) ? sanitize_text_field($_POST['wcsi_payment_method']) : '';
        
        if (empty($pm_id)) {
            wc_add_notice('Payment method not provided. Please try again.', 'error');
            return array('result' => 'fail');
        }
        
        try {
            // Set Stripe API key
            \Stripe\Stripe::setApiKey($this->secret_key);
            
            $total = floatval($order->get_total());
            $installment = floor($total / 4); // Round down to full dollar
            $last_installment = $total - ($installment * 3); // Last payment gets the remainder
            
            // Ensure minimum charge amount (Stripe requires min $0.50)
            if ($installment < 0.50) {
                wc_add_notice('Order total too low for installment payments. Minimum total is $2.00.', 'error');
                return array('result' => 'fail');
            }
            
            // Create Stripe customer
            $customer = \Stripe\Customer::create([
                'email' => $order->get_billing_email(),
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'metadata' => ['order_id' => $order_id]
            ]);
            
            // Attach payment method to customer
            $payment_method = \Stripe\PaymentMethod::retrieve($pm_id);
            $payment_method->attach(['customer' => $customer->id]);
            
            // Set as default payment method for future charges
            \Stripe\Customer::update($customer->id, [
                'invoice_settings' => ['default_payment_method' => $pm_id]
            ]);
            
            // Create and confirm payment intent for first installment
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => intval($installment * 100), // Convert to cents
                'currency' => strtolower($order->get_currency()),
                'customer' => $customer->id,
                'payment_method' => $pm_id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'description' => sprintf('Order #%s - Installment 1 of 4', $order->get_order_number()),
                'metadata' => [
                    'order_id' => $order_id,
                    'installment' => '1 of 4',
                    'total_order' => $total
                ],
                'return_url' => $this->get_return_url($order),
            ]);
            
            // Handle 3D Secure / authentication required
            if ($payment_intent->status === 'requires_action' && $payment_intent->next_action) {
                return array(
                    'result' => 'success',
                    'redirect' => $payment_intent->next_action->redirect_to_url->url
                );
            }
            
            // Check if payment succeeded
            if ($payment_intent->status !== 'succeeded') {
                throw new Exception('Payment failed. Status: ' . $payment_intent->status);
            }
            
            // Payment successful - save installment plan
            $table = $wpdb->prefix . 'wcsi_installments';
            $wpdb->insert($table, array(
                'order_id' => $order_id,
                'customer_id' => $order->get_customer_id() ?: 0,
                'stripe_customer_id' => $customer->id,
                'stripe_payment_method' => $pm_id,
                'total_amount' => $total,
                'installment_amount' => $installment,
                'installments_paid' => 1,
                'total_installments' => 4,
                'next_payment_date' => date('Y-m-d H:i:s', strtotime('+' . $this->payment_interval . ' days')),
                'status' => 'active',
            ));
            
            $installment_id = $wpdb->insert_id;
            
            // Record payments
            $payments_table = $wpdb->prefix . 'wcsi_payments';
            
            // First payment (completed)
            $wpdb->insert($payments_table, array(
                'installment_id' => $installment_id,
                'order_id' => $order_id,
                'payment_number' => 1,
                'amount' => $installment,
                'stripe_payment_intent' => $payment_intent->id,
                'status' => 'completed',
                'scheduled_date' => current_time('mysql'),
                'paid_date' => current_time('mysql'),
            ));
            
            // Schedule remaining 3 payments
            $interval = $this->payment_interval;
            for ($i = 2; $i <= 4; $i++) {
                $amount = ($i == 4) ? $last_installment : $installment;
                $scheduled = date('Y-m-d H:i:s', strtotime('+' . ($interval * ($i - 1)) . ' days'));
                
                $wpdb->insert($payments_table, array(
                    'installment_id' => $installment_id,
                    'order_id' => $order_id,
                    'payment_number' => $i,
                    'amount' => $amount,
                    'status' => 'scheduled',
                    'scheduled_date' => $scheduled,
                ));
            }
            
            // Update order meta
            $order->update_meta_data('_wcsi_installment_id', $installment_id);
            $order->update_meta_data('_wcsi_stripe_customer_id', $customer->id);
            $order->update_meta_data('_wcsi_payment_method_id', $pm_id);
            $order->update_meta_data('_wcsi_installments_paid', '1 of 4');
            $order->update_meta_data('_wcsi_next_payment', date('Y-m-d', strtotime('+' . $interval . ' days')));
            
            // Add detailed order note
            $note = sprintf(
                "✅ INSTALLMENT PAYMENT PLAN STARTED\n\n" .
                "Payment 1 of 4: %s - PAID\n" .
                "Payment 2 of 4: %s - Due %s\n" .
                "Payment 3 of 4: %s - Due %s\n" .
                "Payment 4 of 4: %s - Due %s\n\n" .
                "Total: %s\n" .
                "Stripe Customer: %s\n" .
                "Payment Intent: %s",
                wc_price($installment),
                wc_price($installment), date_i18n('M j, Y', strtotime('+' . $interval . ' days')),
                wc_price($installment), date_i18n('M j, Y', strtotime('+' . ($interval * 2) . ' days')),
                wc_price($last_installment), date_i18n('M j, Y', strtotime('+' . ($interval * 3) . ' days')),
                wc_price($total),
                $customer->id,
                $payment_intent->id
            );
            $order->add_order_note($note);
            
            // Set order status
            $order->set_status('processing', 'First installment received. 3 payments remaining.');
            $order->save();
            
            // Clear cart
            WC()->cart->empty_cart();
            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
            
        } catch (\Stripe\Exception\CardException $e) {
            $error = $e->getError();
            $message = isset($error->message) ? $error->message : $e->getMessage();
            wc_add_notice('Card error: ' . $message, 'error');
            $order->add_order_note('Payment failed: ' . $message);
            return array('result' => 'fail');
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $message = $e->getMessage();
            wc_add_notice('Payment error: ' . $message, 'error');
            $order->add_order_note('Stripe API error: ' . $message);
            return array('result' => 'fail');
            
        } catch (Exception $e) {
            $message = $e->getMessage();
            wc_add_notice('Error: ' . $message, 'error');
            $order->add_order_note('Payment error: ' . $message);
            return array('result' => 'fail');
        }
    }
    
    public function process_refund($order_id, $amount = null, $reason = '') {
        global $wpdb;
        $order = wc_get_order($order_id);
        
        try {
            \Stripe\Stripe::setApiKey($this->secret_key);
            
            $payments = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcsi_payments WHERE order_id = %d AND status = 'completed'", $order_id
            ));
            
            if (empty($payments)) {
                return new WP_Error('error', 'No completed payments to refund.');
            }
            
            $refunded = 0;
            $target = $amount ?: $order->get_total();
            
            foreach ($payments as $p) {
                if ($refunded >= $target) break;
                $ref = min($p->amount, $target - $refunded);
                \Stripe\Refund::create(['payment_intent' => $p->stripe_payment_intent, 'amount' => intval($ref * 100)]);
                $refunded += $ref;
            }
            
            // Cancel remaining scheduled payments
            $wpdb->update($wpdb->prefix . 'wcsi_installments', ['status' => 'cancelled'], ['order_id' => $order_id]);
            $wpdb->update($wpdb->prefix . 'wcsi_payments', ['status' => 'cancelled'], ['order_id' => $order_id, 'status' => 'scheduled']);
            
            $order->add_order_note(sprintf('Refunded %s. Remaining installments cancelled.', wc_price($refunded)));
            
            return true;
        } catch (Exception $e) {
            return new WP_Error('error', $e->getMessage());
        }
    }
}
