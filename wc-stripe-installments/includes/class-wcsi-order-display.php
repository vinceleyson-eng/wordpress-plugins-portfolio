<?php
if (!defined('ABSPATH')) exit;

class WCSI_Order_Display {
    
    public function __construct() {
        // Thank you page - show once at the top
        add_action('woocommerce_thankyou_stripe_installments', array($this, 'thankyou_page'), 5, 1);
        
        // Order details page (customer account) - only on view-order endpoint
        add_action('woocommerce_order_details_after_order_table', array($this, 'order_details_page'), 10, 1);
        
        // Admin order page
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'admin_order_display'), 10, 1);
        
        // Email
        add_action('woocommerce_email_after_order_table', array($this, 'email_installment_info'), 10, 4);
    }
    
    /**
     * Display on thank you page
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'stripe_installments') {
            return;
        }
        
        $this->display_installment_schedule($order, 'thankyou');
    }
    
    /**
     * Display on order details page (My Account)
     */
    public function order_details_page($order) {
        if (!$order || $order->get_payment_method() !== 'stripe_installments') {
            return;
        }
        
        // Don't show on thank you page - it has its own display
        if (is_checkout()) {
            return;
        }
        
        $this->display_installment_schedule($order, 'account');
    }
    
    /**
     * Display on admin order page
     */
    public function admin_order_display($order) {
        if (!$order || $order->get_payment_method() !== 'stripe_installments') {
            return;
        }
        
        $this->display_installment_schedule($order, 'admin');
    }
    
    /**
     * Add installment info to emails
     */
    public function email_installment_info($order, $sent_to_admin, $plain_text, $email) {
        if (!$order || $order->get_payment_method() !== 'stripe_installments') {
            return;
        }
        
        $this->display_installment_schedule($order, $plain_text ? 'email_plain' : 'email');
    }
    
    /**
     * Modify order totals to show installment breakdown
     * Only on order details page in My Account, not on thank you page
     */
    public function modify_order_totals($total_rows, $order, $tax_display) {
        if (!$order || $order->get_payment_method() !== 'stripe_installments') {
            return $total_rows;
        }
        
        // Don't modify on thank you page - we show a separate box there
        if (is_checkout() && is_wc_endpoint_url('order-received')) {
            return $total_rows;
        }
        
        // Don't add if we're on order details page - we show separate box
        if (is_wc_endpoint_url('view-order')) {
            return $total_rows;
        }
        
        // Get payment schedule from database
        global $wpdb;
        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcsi_payments WHERE order_id = %d ORDER BY payment_number ASC",
            $order->get_id()
        ));
        
        if (empty($payments)) {
            return $total_rows;
        }
        
        // Add installment rows before the total
        $new_rows = array();
        
        foreach ($total_rows as $key => $row) {
            if ($key === 'order_total') {
                // Add installment breakdown before total
                $new_rows['installment_header'] = array(
                    'label' => '<strong>' . __('Payment Plan (4 Installments):', 'wc-stripe-installments') . '</strong>',
                    'value' => ''
                );
                
                foreach ($payments as $payment) {
                    $status_label = '';
                    if ($payment->status === 'completed') {
                        $status_label = ' <span style="color: #28a745;">✓ Paid</span>';
                    } elseif ($payment->status === 'scheduled') {
                        $status_label = ' <span style="color: #666;">(Scheduled)</span>';
                    }
                    
                    $date = $payment->status === 'completed' && $payment->paid_date 
                        ? date_i18n(get_option('date_format'), strtotime($payment->paid_date))
                        : date_i18n(get_option('date_format'), strtotime($payment->scheduled_date));
                    
                    $new_rows['installment_' . $payment->payment_number] = array(
                        'label' => sprintf(__('Payment %d (%s):', 'wc-stripe-installments'), $payment->payment_number, $date),
                        'value' => wc_price($payment->amount) . $status_label
                    );
                }
            }
            $new_rows[$key] = $row;
        }
        
        return $new_rows;
    }
    
    /**
     * Display installment schedule
     */
    private function display_installment_schedule($order, $context = 'thankyou') {
        global $wpdb;
        
        $order_id = $order->get_id();
        
        // Get payments from database
        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcsi_payments WHERE order_id = %d ORDER BY payment_number ASC",
            $order_id
        ));
        
        if (empty($payments)) {
            return;
        }
        
        // Get installment record
        $installment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcsi_installments WHERE order_id = %d",
            $order_id
        ));
        
        // Plain text email format
        if ($context === 'email_plain') {
            echo "\n\n";
            echo "===========================================\n";
            echo "PAYMENT PLAN - PAY IN 4 INSTALLMENTS\n";
            echo "===========================================\n\n";
            
            foreach ($payments as $payment) {
                $status = $payment->status === 'completed' ? '[PAID]' : '[SCHEDULED]';
                $date = $payment->status === 'completed' && $payment->paid_date 
                    ? date_i18n('M j, Y', strtotime($payment->paid_date))
                    : date_i18n('M j, Y', strtotime($payment->scheduled_date));
                
                echo "Payment {$payment->payment_number}: " . strip_tags(wc_price($payment->amount)) . " - {$date} {$status}\n";
            }
            
            echo "\nTotal: " . strip_tags(wc_price($order->get_total())) . " (0% interest)\n";
            return;
        }
        
        // HTML format
        $paid_count = 0;
        foreach ($payments as $p) {
            if ($p->status === 'completed') $paid_count++;
        }
        
        ?>
        <div class="wcsi-order-schedule" style="margin: 30px 0; padding: 20px; background: linear-gradient(135deg, #f8fbff 0%, #f0f7ff 100%); border: 2px solid #0073aa; border-radius: 12px;">
            <h3 style="margin: 0 0 15px 0; color: #0073aa; font-size: 18px;">
                💳 Payment Plan - Pay in 4 Installments
            </h3>
            
            <p style="margin: 0 0 15px 0; color: #666;">
                <?php echo $paid_count; ?> of 4 payments completed
            </p>
            
            <div class="wcsi-progress-bar" style="background: #e0e0e0; border-radius: 10px; height: 10px; margin-bottom: 20px; overflow: hidden;">
                <div style="background: #28a745; height: 100%; width: <?php echo ($paid_count / 4) * 100; ?>%; transition: width 0.3s;"></div>
            </div>
            
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #dee2e6;">
                        <th style="text-align: left; padding: 10px 5px; color: #333;">Payment</th>
                        <th style="text-align: left; padding: 10px 5px; color: #333;">Date</th>
                        <th style="text-align: right; padding: 10px 5px; color: #333;">Amount</th>
                        <th style="text-align: center; padding: 10px 5px; color: #333;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment) : 
                        $is_paid = $payment->status === 'completed';
                        $date = $is_paid && $payment->paid_date 
                            ? date_i18n('M j, Y', strtotime($payment->paid_date))
                            : date_i18n('M j, Y', strtotime($payment->scheduled_date));
                    ?>
                    <tr style="border-bottom: 1px solid #eee; <?php echo $is_paid ? 'background: #f8fff8;' : ''; ?>">
                        <td style="padding: 12px 5px;">
                            <strong><?php printf(__('Payment %d', 'wc-stripe-installments'), $payment->payment_number); ?></strong>
                            <?php if ($payment->payment_number === 1) : ?>
                                <span style="font-size: 11px; color: #666;">(Today)</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px 5px; color: #666;"><?php echo esc_html($date); ?></td>
                        <td style="padding: 12px 5px; text-align: right; font-weight: 600;"><?php echo wc_price($payment->amount); ?></td>
                        <td style="padding: 12px 5px; text-align: center;">
                            <?php if ($is_paid) : ?>
                                <span style="background: #28a745; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 12px;">✓ Paid</span>
                            <?php else : ?>
                                <span style="background: #ffc107; color: #333; padding: 4px 10px; border-radius: 20px; font-size: 12px;">Scheduled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid #dee2e6;">
                        <td colspan="2" style="padding: 12px 5px;"><strong>Total</strong></td>
                        <td style="padding: 12px 5px; text-align: right; font-weight: 700; font-size: 18px;"><?php echo wc_price($order->get_total()); ?></td>
                        <td style="padding: 12px 5px; text-align: center;">
                            <span style="color: #28a745; font-size: 12px;">0% Interest</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
            
            <?php if ($context === 'thankyou') : ?>
            <p style="margin: 20px 0 0 0; padding: 15px; background: #fff; border-radius: 8px; color: #666; font-size: 14px;">
                <strong>What's next?</strong><br>
                Your first payment of <?php echo wc_price($payments[0]->amount); ?> has been charged today. 
                The remaining payments will be automatically charged to your card on the scheduled dates.
                You'll receive an email reminder before each payment.
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
