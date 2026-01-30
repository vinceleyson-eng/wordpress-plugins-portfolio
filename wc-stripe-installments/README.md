# 💳 WC Stripe Installments

**Enterprise Payment Gateway for WooCommerce**

Transform your e-commerce store with a complete "Pay in 4" installment system. This plugin provides the same functionality as Klarna, Afterpay, or Sezzle - allowing customers to split payments into 4 interest-free installments.

## 🚀 Key Features

### **Complete Payment Processing**
- ✅ 4-installment payment splitting
- ✅ Automated recurring charges via Stripe
- ✅ Smart payment scheduling (configurable intervals)
- ✅ Full refund and partial refund support
- ✅ Failed payment handling and retries

### **Professional Admin Interface**
- ✅ Comprehensive settings panel
- ✅ Test/Live mode configuration
- ✅ Minimum/maximum amount controls
- ✅ Payment interval customization
- ✅ Real-time payment tracking dashboard

### **Database Management**
- ✅ Custom tables for installment tracking
- ✅ Payment history and status monitoring
- ✅ Customer payment method storage
- ✅ Automated cleanup and maintenance

### **Customer Experience**
- ✅ Smooth checkout integration
- ✅ Clear payment breakdown display
- ✅ Mobile-responsive payment forms
- ✅ Stripe Elements for security
- ✅ Order tracking and notifications

## 🛠️ Installation

1. **Install Stripe PHP Library:**
   ```bash
   composer require stripe/stripe-php
   ```
   Or download and place in `vendor/stripe/` directory

2. **Upload Plugin:** Copy to `/wp-content/plugins/wc-stripe-installments/`

3. **Activate:** Enable in WordPress Admin → Plugins

4. **Configure:** Go to WooCommerce → Settings → Payments → Stripe Installments

## ⚙️ Configuration

### **Stripe Keys**
- Test Publishable Key: `pk_test_...`
- Test Secret Key: `sk_test_...`
- Live Publishable Key: `pk_live_...`
- Live Secret Key: `sk_live_...`

### **Payment Settings**
- **Minimum Amount:** $2 (ensures each installment ≥ $0.50)
- **Maximum Amount:** Configurable (default $100,000)
- **Payment Interval:** Days between payments (default 14)

## 💼 Business Benefits

- **Higher conversion rates** with flexible payment options
- **Larger average order values** when customers can split payments
- **Reduced cart abandonment** from payment friction
- **No revenue sharing** with external BNPL providers

## 📊 Technical Details

### **Database Schema**
```sql
-- Installment tracking
wp_wcsi_installments (id, order_id, customer_id, total_amount, installments_paid, status)

-- Payment history  
wp_wcsi_payments (id, installment_id, payment_number, amount, scheduled_date, status)
```

### **Hooks & Filters**
```php
// Modify installment display
add_filter('wcsi_installment_text', function($text, $amount) {
    return "Pay just " . wc_price($amount) . " today!";
}, 10, 2);
```

## 🎯 Use Cases

- Fashion and retail stores
- Electronics and technology
- Home goods and furniture  
- Professional equipment
- Educational courses and software

**Perfect for stores wanting to compete with major retailers offering BNPL options.**

---

*Enterprise-level payment processing for modern e-commerce.*