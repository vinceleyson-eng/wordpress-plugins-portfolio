(function($) {
    'use strict';
    
    if (typeof wcsi_params === 'undefined' || !wcsi_params.publishable_key) {
        return;
    }
    
    var stripe = null;
    var elements = null;
    var cardElement = null;
    
    // Initialize Stripe once
    try {
        stripe = Stripe(wcsi_params.publishable_key);
        elements = stripe.elements();
        
        // Create card element ONCE here
        cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    '::placeholder': { color: '#aab7c4' }
                },
                invalid: { color: '#dc3545' }
            },
            hidePostalCode: true
        });
        
        console.log('WCSI: Stripe and card element initialized');
    } catch (e) {
        console.error('WCSI: Init failed:', e);
        return;
    }
    
    function mountCard() {
        var container = document.getElementById('wcsi-card-element');
        
        if (!container) {
            return;
        }
        
        // Already mounted - check for iframe
        if (container.querySelector('iframe')) {
            return;
        }
        
        // Clear loading text
        container.innerHTML = '';
        
        try {
            cardElement.mount('#wcsi-card-element');
            console.log('WCSI: Card mounted');
            
            cardElement.on('change', function(event) {
                var errorDiv = document.getElementById('wcsi-card-errors');
                if (errorDiv) {
                    errorDiv.textContent = event.error ? event.error.message : '';
                }
            });
        } catch (e) {
            // Already mounted somewhere, try unmount first
            try {
                cardElement.unmount();
                cardElement.mount('#wcsi-card-element');
                console.log('WCSI: Card remounted');
            } catch (e2) {
                console.log('WCSI: Mount error:', e2.message);
            }
        }
    }
    
    function isOurPaymentSelected() {
        var checked = $('input[name="payment_method"]:checked').val();
        if (!checked) {
            checked = $('input[name="radio-control-wc-payment-method-options"]:checked').val();
        }
        return checked === 'stripe_installments';
    }
    
    function handleSubmit(e) {
        if (!isOurPaymentSelected()) {
            return true;
        }
        
        if ($('#wcsi_payment_method').val()) {
            return true;
        }
        
        if (!cardElement) {
            alert('Payment form not loaded. Please refresh the page.');
            return false;
        }
        
        e.preventDefault();
        e.stopPropagation();
        
        var $form = $('form.checkout, form.wc-block-checkout__form').first();
        $form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
        
        var name = ($('#billing_first_name').val() || '') + ' ' + ($('#billing_last_name').val() || '');
        var email = $('#billing_email').val() || '';
        
        stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
            billing_details: { name: name.trim() || 'Customer', email: email || undefined }
        }).then(function(result) {
            $form.unblock();
            
            if (result.error) {
                $('#wcsi-card-errors').text(result.error.message);
            } else {
                $('#wcsi_payment_method').val(result.paymentMethod.id);
                $form.submit();
            }
        });
        
        return false;
    }
    
    // Mount attempts
    $(document).ready(function() {
        mountCard();
        setTimeout(mountCard, 1000);
        setTimeout(mountCard, 3000);
    });
    
    // Remount on checkout update
    $(document.body).on('updated_checkout', function() {
        setTimeout(mountCard, 300);
    });
    
    // Mount when our payment selected
    $(document.body).on('change', 'input[name="payment_method"], input[name="radio-control-wc-payment-method-options"]', function() {
        if ($(this).val() === 'stripe_installments') {
            setTimeout(mountCard, 300);
        }
    });
    
    // Handle form submission
    $('form.checkout').on('checkout_place_order_stripe_installments', handleSubmit);
    
    $(document).on('click', '.wc-block-components-checkout-place-order-button', function(e) {
        if (isOurPaymentSelected() && !$('#wcsi_payment_method').val()) {
            return handleSubmit(e);
        }
    });
    
    // Watch for container
    var observer = new MutationObserver(function() {
        var container = document.getElementById('wcsi-card-element');
        if (container && !container.querySelector('iframe')) {
            mountCard();
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
    
})(jQuery);
