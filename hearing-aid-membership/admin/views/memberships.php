<?php
if (!defined('ABSPATH')) exit;

// Get statistics
$stats = isset($stats) ? $stats : array('memberships' => array('total' => 0, 'preferred' => 0, 'verified' => 0, 'unverified' => 0));
$memberships = isset($memberships) ? $memberships : array();
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="ham-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
        <div class="ham-stat-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; text-align: center;">
            <h3 style="margin: 0; font-size: 32px; color: #2271b1;"><?php echo isset($stats['memberships']['total']) ? $stats['memberships']['total'] : 0; ?></h3>
            <p style="margin: 5px 0 0 0; color: #666;">Total Active</p>
        </div>
        <div class="ham-stat-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; text-align: center;">
            <h3 style="margin: 0; font-size: 32px; color: #2c5f5d;"><?php echo isset($stats['memberships']['preferred']) ? $stats['memberships']['preferred'] : 0; ?></h3>
            <p style="margin: 5px 0 0 0; color: #666;">Preferred</p>
        </div>
        <div class="ham-stat-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; text-align: center;">
            <h3 style="margin: 0; font-size: 32px; color: #00a32a;"><?php echo isset($stats['memberships']['verified']) ? $stats['memberships']['verified'] : 0; ?></h3>
            <p style="margin: 5px 0 0 0; color: #666;">Verified</p>
        </div>
        <div class="ham-stat-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; text-align: center;">
            <h3 style="margin: 0; font-size: 32px; color: #dba617;"><?php echo isset($stats['total_mrr']) ? '$' . number_format($stats['total_mrr'], 2) : '$0.00'; ?></h3>
            <p style="margin: 5px 0 0 0; color: #666;">MRR</p>
        </div>
    </div>
    
    <div class="card" style="margin-top: 20px;max-width: 100%;">
        <h2>All Memberships</h2>
        
        <?php if (empty($memberships)): ?>
            <p>No memberships found. Members will appear here once they purchase a membership plan.</p>
            <p><a href="<?php echo admin_url('admin.php?page=ham-settings'); ?>" class="button button-primary">Configure Settings</a></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Store</th>
                        <th>Type</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Next Billing</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($memberships as $membership): ?>
                        <tr>
                            <td><?php echo esc_html($membership->display_name); ?><br><small><?php echo esc_html($membership->user_email); ?></small></td>
                            <td><?php echo esc_html($membership->store_name); ?></td>
                            <td>
                                <?php 
                                $badge_colors = array(
                                    'preferred' => '#2c5f5d',
                                    'verified' => '#00a32a',
                                    'unverified' => '#999'
                                );
                                $color = isset($badge_colors[$membership->membership_type]) ? $badge_colors[$membership->membership_type] : '#999';
                                ?>
                                <span style="background: <?php echo $color; ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px;">
                                    <?php echo strtoupper($membership->membership_type); ?>
                                </span>
                            </td>
                            <td>$<?php echo number_format($membership->price, 2); ?>/<?php echo $membership->billing_cycle; ?></td>
                            <td><?php echo ucfirst($membership->status); ?></td>
                            <td><?php echo $membership->next_billing_date ? date('M d, Y', strtotime($membership->next_billing_date)) : 'N/A'; ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=ham-edit-membership&id=' . $membership->id); ?>" class="button button-small">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
