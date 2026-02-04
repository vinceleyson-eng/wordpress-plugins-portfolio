<?php
/**
 * Payment Success Page
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

$user_id = get_current_user_id();
$membership = HA_Membership()->membership->get_user_membership($user_id);

get_header();
?>

<div class="ham-success-wrapper" style="max-width: 700px; margin: 60px auto; padding: 0 20px; text-align: center;">
    
    <div style="font-size: 64px; color: #00a32a; margin-bottom: 20px;">✓</div>
    
    <h1 style="color: #00a32a; margin-bottom: 10px;">Welcome to <?php echo $membership ? ucfirst($membership->membership_type) : ''; ?> Membership!</h1>
    
    <p style="font-size: 18px; color: #666; margin-bottom: 40px;">Your payment was successful and your membership is now active.</p>
    
    <?php if ($membership): ?>
        <div style="background: #f9f9f9; padding: 30px; border-radius: 8px; margin: 30px 0; text-align: left;">
            <h3 style="margin-top: 0;">Membership Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px 0; font-weight: bold;">Plan:</td>
                    <td style="padding: 12px 0;"><?php echo ucfirst($membership->membership_type); ?> Provider</td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px 0; font-weight: bold;">Price:</td>
                    <td style="padding: 12px 0;">$<?php echo number_format($membership->price, 2); ?> / <?php echo $membership->billing_cycle; ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px 0; font-weight: bold;">Next Billing:</td>
                    <td style="padding: 12px 0;"><?php echo date('F j, Y', strtotime($membership->next_billing_date)); ?></td>
                </tr>
                <tr>
                    <td style="padding: 12px 0; font-weight: bold;">Status:</td>
                    <td style="padding: 12px 0;"><span style="color: #00a32a; font-weight: bold;">Active</span></td>
                </tr>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="background: #e7f5fe; padding: 20px; border-radius: 8px; margin: 30px 0; text-align: left;">
        <h4 style="margin-top: 0; color: #2271b1;">What's Next?</h4>
        <ul style="margin: 0; padding-left: 20px;">
            <li style="margin: 10px 0;">Your store listing has been upgraded to <?php echo $membership ? ucfirst($membership->membership_type) : ''; ?> status</li>
            <li style="margin: 10px 0;">Your map pin is now displayed with priority</li>
            <li style="margin: 10px 0;">You'll receive email confirmations and receipts</li>
            <li style="margin: 10px 0;">Manage your membership anytime in your dashboard</li>
        </ul>
    </div>
    
    <div style="margin-top: 40px;">
        <a href="<?php echo home_url('/my-membership/'); ?>" class="button button-primary button-large" style="margin: 0 10px 10px 10px;">
            View My Dashboard
        </a>
        <a href="<?php echo home_url(); ?>" class="button button-large" style="margin: 0 10px 10px 10px;">
            Back to Home
        </a>
    </div>
    
    <p style="margin-top: 40px; color: #666; font-size: 14px;">
        Questions? <a href="/contact/">Contact our support team</a>
    </p>
    
</div>

<?php
get_footer();
