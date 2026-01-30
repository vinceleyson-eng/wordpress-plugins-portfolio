<?php
if (!defined('ABSPATH')) exit;

class WCSI_Cart_Display {
    
    private $enabled;
    private $min_amount;
    private $max_amount;
    private $payment_interval;
    
    public function __construct() {
        // Get settings from gateway
        $settings = get_option('woocommerce_stripe_installments_settings', array());
        
        $this->enabled = isset($settings['enabled']) ? $settings['enabled'] : 'no';
        $this->min_amount = isset($settings['min_amount']) ? floatval($settings['min_amount']) : 50;
        $this->max_amount = isset($settings['max_amount']) ? floatval($settings['max_amount']) : 100000;
        $this->payment_interval = isset($settings['payment_interval']) ? intval($settings['payment_interval']) : 14;
        
        if ($this->enabled !== 'yes') return;
        
        // Add to cart page via footer script (safest method)
        add_action('wp_footer', array($this, 'cart_display_script'), 99);
    }
    
    public function cart_display_script() {
        // Only on cart page
        if (!function_exists('is_cart') || !is_cart()) return;
        if (!function_exists('WC') || !WC()->cart) return;
        
        $total = floatval(WC()->cart->get_total('edit'));
        
        if ($total < $this->min_amount || $total > $this->max_amount) return;
        
        $installment = floor($total / 4); // Round down to full dollar
        $last_installment = $total - ($installment * 3); // Last payment gets remainder
        $interval = $this->payment_interval;
        
        $today = current_time('timestamp');
        $dates = array(
            __('Today', 'wc-stripe-installments'),
            date_i18n('M j', strtotime("+{$interval} days", $today)),
            date_i18n('M j', strtotime("+" . ($interval * 2) . " days", $today)),
            date_i18n('M j', strtotime("+" . ($interval * 3) . " days", $today)),
        );
        
        $amounts = array($installment, $installment, $installment, $last_installment);
        $formatted_amounts = array();
        foreach ($amounts as $amt) {
            $formatted_amounts[] = html_entity_decode(strip_tags(wc_price($amt)));
        }
        ?>
        <script type="text/javascript">
        (function() {
            // Wait for DOM
            function wcsiInit() {
                // Don't duplicate
                if (document.getElementById('wcsi-pay4-box')) return;
                
                var dates = <?php echo wp_json_encode($dates); ?>;
                var amounts = <?php echo wp_json_encode($formatted_amounts); ?>;
                var interval = <?php echo intval($interval); ?>;
                
                var html = '<div id="wcsi-pay4-box" style="background:linear-gradient(135deg,#f8fbff,#eef5ff);border:2px solid #0073aa;border-radius:12px;padding:20px;margin:20px 0;max-width:100%;">' +
                    '<div style="color:#0073aa;font-size:16px;margin-bottom:15px;display:flex;align-items:center;gap:10px;">' +
                    '<span style="font-size:24px;">💳</span>' +
                    '<strong>Pay in 4 interest-free installments</strong>' +
                    '</div>' +
                    '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">';
                
                for (var i = 0; i < 4; i++) {
                    var isFirst = i === 0;
                    html += '<div style="background:' + (isFirst ? '#f0fff4' : '#fff') + ';border:1px solid ' + (isFirst ? '#28a745' : '#e0e0e0') + ';border-radius:8px;padding:12px;text-align:center;">' +
                        '<div style="font-size:11px;color:' + (isFirst ? '#28a745' : '#666') + ';text-transform:uppercase;font-weight:' + (isFirst ? '600' : '400') + ';">' + dates[i] + '</div>' +
                        '<div style="font-size:16px;font-weight:700;color:#333;margin-top:4px;">' + amounts[i] + '</div>' +
                        '</div>';
                }
                
                html += '</div>' +
                    '<div style="margin-top:15px;padding-top:12px;border-top:1px dashed #ccc;text-align:center;font-size:13px;color:#28a745;font-weight:500;">' +
                    'Every ' + interval + ' days • 0% interest • No fees' +
                    '</div></div>';
                
                // Find cart totals and insert after
                var targets = [
                    '.cart_totals',
                    '.wc-block-cart__totals', 
                    '.wp-block-woocommerce-cart-totals-block',
                    '.cart-collaterals'
                ];
                
                for (var t = 0; t < targets.length; t++) {
                    var el = document.querySelector(targets[t]);
                    if (el) {
                        el.insertAdjacentHTML('afterend', html);
                        return;
                    }
                }
            }
            
            // Try multiple times for dynamic content
            if (document.readyState === 'complete') {
                wcsiInit();
            } else {
                window.addEventListener('load', wcsiInit);
            }
            setTimeout(wcsiInit, 1500);
            setTimeout(wcsiInit, 3000);
        })();
        </script>
        <?php
    }
}
