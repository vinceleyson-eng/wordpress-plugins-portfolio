<?php
if (!defined('ABSPATH')) exit;

// Get membership ID
$membership_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$membership_id) {
    echo '<div class="wrap"><h1>Invalid Membership</h1><p>Membership not found.</p></div>';
    return;
}

// Get membership
global $wpdb;
$membership = $wpdb->get_row($wpdb->prepare(
    "SELECT m.*, u.user_email, u.display_name, p.post_title as store_name
     FROM {$wpdb->prefix}ham_memberships m
     LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
     LEFT JOIN {$wpdb->posts} p ON m.store_id = p.ID
     WHERE m.id = %d",
    $membership_id
));

if (!$membership) {
    echo '<div class="wrap"><h1>Membership Not Found</h1></div>';
    return;
}

// Handle form submission
if (isset($_POST['ham_update_membership'])) {
    check_admin_referer('ham_edit_membership_' . $membership_id);
    
    $updates = array(
        'membership_type' => sanitize_text_field($_POST['membership_type']),
        'billing_cycle' => sanitize_text_field($_POST['billing_cycle']),
        'price' => floatval($_POST['price']),
        'status' => sanitize_text_field($_POST['status']),
        'auto_renew' => isset($_POST['auto_renew']) ? 1 : 0,
        'next_billing_date' => sanitize_text_field($_POST['next_billing_date']),
        'stripe_subscription_id' => sanitize_text_field($_POST['stripe_subscription_id'])
    );
    
    $updated = $wpdb->update(
        $wpdb->prefix . 'ham_memberships',
        $updates,
        array('id' => $membership_id),
        array('%s', '%s', '%f', '%s', '%d', '%s', '%s'),
        array('%d')
    );
    
    if ($updated !== false) {
        // Update ACF field
        $type_map = array(
            'preferred' => 'Preferred Provider',
            'verified' => 'Verified Provider',
            'unverified' => 'Non Verified'
        );
        
        $display_type = $type_map[$updates['membership_type']] ?? 'Non Verified';
        
        if (function_exists('update_field')) {
            update_field('member_type', $display_type, $membership->store_id);
        } else {
            update_post_meta($membership->store_id, 'member_type', $display_type);
        }
        
        echo '<div class="notice notice-success"><p>Membership updated successfully!</p></div>';
        
        // Reload membership
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, u.user_email, u.display_name, p.post_title as store_name
             FROM {$wpdb->prefix}ham_memberships m
             LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
             LEFT JOIN {$wpdb->posts} p ON m.store_id = p.ID
             WHERE m.id = %d",
            $membership_id
        ));
    } else {
        echo '<div class="notice notice-error"><p>Failed to update membership.</p></div>';
    }
}
?>

<div class="wrap">
    <h1>Edit Membership #<?php echo $membership_id; ?></h1>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
        
        <!-- Left Column: Edit Form -->
        <div class="card" style="max-width: 100%;">
            <h2>Membership Details</h2>
            
            <form method="post">
                <?php wp_nonce_field('ham_edit_membership_' . $membership_id); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Member</th>
                        <td>
                            <strong><?php echo esc_html($membership->display_name); ?></strong><br>
                            <?php echo esc_html($membership->user_email); ?><br>
                            <a href="<?php echo get_edit_user_link($membership->user_id); ?>">Edit User</a>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Store</th>
                        <td>
                            <?php if ($membership->store_id): ?>
                                <strong><?php echo esc_html($membership->store_name); ?></strong><br>
                                <a href="<?php echo get_edit_post_link($membership->store_id); ?>" target="_blank">Edit Store</a>
                            <?php else: ?>
                                <em>No store linked</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="membership_type">Membership Type</label></th>
                        <td>
                            <select name="membership_type" id="membership_type" class="regular-text">
                                <option value="preferred" <?php selected($membership->membership_type, 'preferred'); ?>>Preferred Provider</option>
                                <option value="verified" <?php selected($membership->membership_type, 'verified'); ?>>Verified Provider</option>
                                <option value="unverified" <?php selected($membership->membership_type, 'unverified'); ?>>Unverified</option>
                            </select>
                            <p class="description">This will update the ACF member_type field automatically</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="billing_cycle">Billing Cycle</label></th>
                        <td>
                            <select name="billing_cycle" id="billing_cycle">
                                <option value="monthly" <?php selected($membership->billing_cycle, 'monthly'); ?>>Monthly</option>
                                <option value="yearly" <?php selected($membership->billing_cycle, 'yearly'); ?>>Yearly</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="price">Price</label></th>
                        <td>
                            $<input type="number" name="price" id="price" value="<?php echo esc_attr($membership->price); ?>" step="0.01" min="0" style="width: 100px;"> per <?php echo $membership->billing_cycle; ?>
                            <p class="description">Set to 0 for free membership</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="status">Status</label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="active" <?php selected($membership->status, 'active'); ?>>Active</option>
                                <option value="past_due" <?php selected($membership->status, 'past_due'); ?>>Past Due</option>
                                <option value="cancelled" <?php selected($membership->status, 'cancelled'); ?>>Cancelled</option>
                                <option value="expired" <?php selected($membership->status, 'expired'); ?>>Expired</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Auto Renew</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_renew" value="1" <?php checked($membership->auto_renew, 1); ?>>
                                Automatically renew subscription
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="next_billing_date">Next Billing Date</label></th>
                        <td>
                            <input type="datetime-local" name="next_billing_date" id="next_billing_date" 
                                   value="<?php echo $membership->next_billing_date ? date('Y-m-d\TH:i', strtotime($membership->next_billing_date)) : ''; ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="stripe_subscription_id">Stripe Subscription ID</label></th>
                        <td>
                            <input type="text" name="stripe_subscription_id" id="stripe_subscription_id" 
                                   value="<?php echo esc_attr($membership->stripe_subscription_id); ?>" 
                                   class="regular-text" placeholder="sub_...">
                            <p class="description">Leave empty for manual/free memberships</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="ham_update_membership" class="button button-primary button-large">
                        Update Membership
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=ha-membership'); ?>" class="button button-large">
                        Cancel
                    </a>
                </p>
            </form>
        </div>
        
        <!-- Right Column: Info -->
        <div>
            <div class="card">
                <h3>Membership Info</h3>
                <table class="widefat">
                    <tr>
                        <td><strong>Created:</strong></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($membership->created_at)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Updated:</strong></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($membership->updated_at)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Start Date:</strong></td>
                        <td><?php echo date('M j, Y', strtotime($membership->start_date)); ?></td>
                    </tr>
                    <?php if ($membership->end_date): ?>
                    <tr>
                        <td><strong>End Date:</strong></td>
                        <td><?php echo date('M j, Y', strtotime($membership->end_date)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($membership->last_payment_date): ?>
                    <tr>
                        <td><strong>Last Payment:</strong></td>
                        <td><?php echo date('M j, Y', strtotime($membership->last_payment_date)); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <?php if ($membership->stripe_customer_id || $membership->stripe_subscription_id): ?>
            <div class="card" style="margin-top: 20px;">
                <h3>Stripe Info</h3>
                <table class="widefat">
                    <?php if ($membership->stripe_customer_id): ?>
                    <tr>
                        <td><strong>Customer ID:</strong></td>
                        <td><code><?php echo esc_html($membership->stripe_customer_id); ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($membership->stripe_subscription_id): ?>
                    <tr>
                        <td><strong>Subscription ID:</strong></td>
                        <td><code><?php echo esc_html($membership->stripe_subscription_id); ?></code></td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php if ($membership->stripe_subscription_id): ?>
                <p style="margin-top: 10px;">
                    <a href="https://dashboard.stripe.com/<?php echo get_option('ham_stripe_test_mode') ? 'test/' : ''; ?>subscriptions/<?php echo esc_attr($membership->stripe_subscription_id); ?>" 
                       target="_blank" class="button">
                        View in Stripe →
                    </a>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="card" style="margin-top: 20px;">
                <h3>Quick Actions</h3>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=ham-transactions&user_id=' . $membership->user_id); ?>" class="button">
                        View Transactions
                    </a>
                </p>
                <p>
                    <a href="mailto:<?php echo esc_attr($membership->user_email); ?>" class="button">
                        Email Member
                    </a>
                </p>
            </div>
        </div>
        
    </div>
</div>
