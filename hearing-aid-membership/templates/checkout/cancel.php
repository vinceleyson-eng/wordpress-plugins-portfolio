<?php
/**
 * Payment Cancelled Page
 */

if (!defined('ABSPATH')) exit;

get_header();
?>

<div class="ham-cancel-wrapper" style="max-width: 600px; margin: 60px auto; padding: 0 20px; text-align: center;">
    
    <div style="font-size: 64px; color: #d63638; margin-bottom: 20px;">✕</div>
    
    <h1 style="margin-bottom: 10px;">Payment Cancelled</h1>
    
    <p style="font-size: 18px; color: #666; margin-bottom: 40px;">
        Your payment was not completed. No charges have been made to your card.
    </p>
    
    <div style="background: #f9f9f9; padding: 30px; border-radius: 8px; margin: 30px 0; text-align: left;">
        <h3 style="margin-top: 0;">What happened?</h3>
        <p>You cancelled the checkout process before completing payment. This is completely fine - you can try again whenever you're ready.</p>
        
        <h4>Need help deciding?</h4>
        <ul style="padding-left: 20px;">
            <li style="margin: 8px 0;">Review plan features on our pricing page</li>
            <li style="margin: 8px 0;">Contact us with any questions</li>
            <li style="margin: 8px 0;">No obligation to sign up</li>
        </ul>
    </div>
    
    <div style="margin-top: 40px;">
        <a href="<?php echo home_url('/pricing/'); ?>" class="button button-primary button-large" style="margin: 0 10px 10px 10px;">
            View Plans Again
        </a>
        <a href="<?php echo home_url(); ?>" class="button button-large" style="margin: 0 10px 10px 10px;">
            Back to Home
        </a>
    </div>
    
</div>

<?php
get_footer();
