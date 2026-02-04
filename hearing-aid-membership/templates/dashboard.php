<?php
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$membership = HA_Membership()->membership->get_user_membership($user_id);
?>

<div class="ham-dashboard" style="max-width: 1000px; margin: 40px auto; padding: 0 20px;">
    
    <h2>My Membership Dashboard</h2>
    
    <?php if ($membership): ?>
        <div class="ham-status-card" style="background: white; border: 2px solid #2c5f5d; border-radius: 8px; padding: 30px; margin: 20px 0;">
            <h3 style="margin: 0 0 20px 0;">Current Membership</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <strong>Type:</strong><br>
                    <span style="font-size: 20px; color: #2c5f5d;"><?php echo ucfirst($membership->membership_type); ?></span>
                </div>
                <div>
                    <strong>Price:</strong><br>
                    <span style="font-size: 20px;">$<?php echo number_format($membership->price, 2); ?>/<?php echo $membership->billing_cycle; ?></span>
                </div>
                <div>
                    <strong>Status:</strong><br>
                    <span style="font-size: 20px; color: #00a32a;"><?php echo ucfirst($membership->status); ?></span>
                </div>
                <div>
                    <strong>Next Billing:</strong><br>
                    <span style="font-size: 16px;"><?php echo $membership->next_billing_date ? date('M d, Y', strtotime($membership->next_billing_date)) : 'N/A'; ?></span>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                <a href="#" class="button">Manage Subscription</a>
                <a href="#" class="button">Update Payment Method</a>
            </div>
        </div>
    <?php else: ?>
        <div class="ham-no-membership" style="background: #f0f0f0; border-radius: 8px; padding: 40px; text-align: center;">
            <h3>No Active Membership</h3>
            <p>You don't have an active membership yet.</p>
            <a href="<?php echo home_url('/pricing/'); ?>" class="button button-primary button-large">View Plans</a>
        </div>
    <?php endif; ?>
    
</div>
