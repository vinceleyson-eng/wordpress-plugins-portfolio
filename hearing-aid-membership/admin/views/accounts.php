<?php
/**
 * Accounts Management Page
 * Shows all user accounts with their locations and audiologists
 */

if (!defined('ABSPATH')) exit;

// Handle account approval
if (isset($_POST['ham_approve_account'])) {
    check_admin_referer('ham_approve_account_' . $_POST['user_id']);
    $user_id = intval($_POST['user_id']);
    
    global $wpdb;
    
    // Update membership status from pending_approval to active
    $wpdb->update(
        $wpdb->prefix . 'ham_memberships',
        array('status' => 'active'),
        array('user_id' => $user_id, 'status' => 'pending_approval'),
        array('%s'),
        array('%d', '%s')
    );
    
    // Publish all pending stores for this user
    $user_stores = get_posts(array(
        'post_type' => 'hearing-aid-store',
        'author' => $user_id,
        'post_status' => 'pending',
        'posts_per_page' => -1
    ));
    
    foreach ($user_stores as $store) {
        wp_update_post(array(
            'ID' => $store->ID,
            'post_status' => 'publish'
        ));
    }
    
    // Send approval email
    $user = get_userdata($user_id);
    $subject = 'Your Account Has Been Approved!';
    $message = "Hi " . $user->display_name . ",\n\n";
    $message .= "Great news! Your account has been approved and your listing is now live on our site.\n\n";
    $message .= "Login to manage your account: " . home_url('/member-login/') . "\n";
    $message .= "View your dashboard: " . home_url('/my-account/') . "\n\n";
    $message .= "Thank you for joining our directory!";
    
    wp_mail($user->user_email, $subject, $message);
    
    echo '<div class="notice notice-success"><p><strong>Account approved!</strong> User has been notified.</p></div>';
}

// Get all users with memberships
global $wpdb;
$accounts = $wpdb->get_results("
    SELECT m.*, u.user_email, u.display_name, u.user_registered
    FROM {$wpdb->prefix}ham_memberships m
    LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
    ORDER BY m.created_at DESC
");

// Get pending approval count
$pending_count = $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->prefix}ham_memberships 
    WHERE status = 'pending_approval'
");

?>

<div class="wrap">
    <h1>
        All Accounts
        <?php if ($pending_count > 0): ?>
            <span class="update-plugins count-<?php echo $pending_count; ?>">
                <span class="update-count"><?php echo $pending_count; ?></span>
            </span>
        <?php endif; ?>
    </h1>
    
    <div class="card" style="margin-top: 20px;">
        <div style="padding: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div style="background: #f0f0f0; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo count($accounts); ?></div>
                    <div style="color: #666;">Total Accounts</div>
                </div>
                <div style="background: #fff3cd; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #f0b429;"><?php echo $pending_count; ?></div>
                    <div style="color: #666;">Pending Approval</div>
                </div>
                <div style="background: #d4edda; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #00a32a;">
                        <?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ham_memberships WHERE status = 'active'"); ?>
                    </div>
                    <div style="color: #666;">Active Accounts</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <ul class="subsubsub" style="margin: 20px 0;">
        <li><a href="#" class="current">All <span class="count">(<?php echo count($accounts); ?>)</span></a> |</li>
        <li><a href="#pending">Pending <span class="count">(<?php echo $pending_count; ?>)</span></a> |</li>
        <li><a href="#active">Active <span class="count">(<?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ham_memberships WHERE status = 'active'"); ?>)</span></a></li>
    </ul>
    
    <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
        <thead>
            <tr>
                <th style="width: 25%;">Account Holder</th>
                <th style="width: 15%;">Membership</th>
                <th style="width: 20%;">Locations</th>
                <th style="width: 15%;">Audiologists</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 10%;">Joined</th>
                <th style="width: 10%;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($accounts)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                        No accounts found. Users will appear here after signing up.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($accounts as $account): 
                    // Get user's stores
                    $user_stores = get_posts(array(
                        'post_type' => 'hearing-aid-store',
                        'author' => $account->user_id,
                        'posts_per_page' => -1,
                        'post_status' => 'any'
                    ));
                    
                    // Get user's audiologists
                    $user_audiologists = get_posts(array(
                        'post_type' => 'audiologist',
                        'author' => $account->user_id,
                        'posts_per_page' => -1,
                        'post_status' => 'any'
                    ));
                    
                    $status_color = $account->status === 'active' ? '#00a32a' : 
                                   ($account->status === 'pending_approval' ? '#f0b429' : '#666');
                    $status_text = $account->status === 'active' ? 'Active' :
                                  ($account->status === 'pending_approval' ? 'Pending Approval' : ucfirst($account->status));
                ?>
                    <tr data-status="<?php echo $account->status; ?>">
                        <td>
                            <strong style="font-size: 15px;"><?php echo esc_html($account->display_name); ?></strong><br>
                            <small><?php echo esc_html($account->user_email); ?></small>
                            <div class="row-actions">
                                <a href="<?php echo get_edit_user_link($account->user_id); ?>">Edit User</a>
                            </div>
                        </td>
                        <td>
                            <span style="display: inline-block; padding: 5px 10px; background: <?php 
                                echo $account->membership_type === 'preferred' ? '#2c5f5d' : 
                                    ($account->membership_type === 'verified' ? '#2271b1' : '#999'); 
                            ?>; color: white; font-size: 12px; border-radius: 12px;">
                                <?php echo ucfirst($account->membership_type); ?>
                            </span>
                            <?php if ($account->price > 0): ?>
                                <br><small>$<?php echo number_format($account->price, 0); ?>/<?php echo $account->billing_cycle; ?></small>
                            <?php else: ?>
                                <br><small style="color: #666;">Free</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($user_stores)): ?>
                                <span style="color: #999;">No locations</span>
                            <?php else: ?>
                                <strong><?php echo count($user_stores); ?></strong> location<?php echo count($user_stores) !== 1 ? 's' : ''; ?>
                                <div style="margin-top: 5px;">
                                    <?php foreach ($user_stores as $store): 
                                        $store_status = get_post_status($store->ID);
                                        $store_status_icon = $store_status === 'publish' ? '✓' : '⏳';
                                    ?>
                                        <div style="font-size: 13px; margin: 3px 0;">
                                            <?php echo $store_status_icon; ?> <?php echo esc_html($store->post_title); ?>
                                            <a href="<?php echo get_edit_post_link($store->ID); ?>" style="font-size: 11px;">(edit)</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($user_audiologists)): ?>
                                <span style="color: #999;">None</span>
                            <?php else: ?>
                                <strong><?php echo count($user_audiologists); ?></strong> audiologist<?php echo count($user_audiologists) !== 1 ? 's' : ''; ?>
                                <div style="margin-top: 5px;">
                                    <?php foreach ($user_audiologists as $audio): 
                                        $audio_status = get_post_status($audio->ID);
                                        $audio_status_icon = $audio_status === 'publish' ? '✓' : '⏳';
                                    ?>
                                        <div style="font-size: 13px; margin: 3px 0;">
                                            <?php echo $audio_status_icon; ?> <?php echo esc_html($audio->post_title); ?>
                                            <a href="<?php echo get_edit_post_link($audio->ID); ?>" style="font-size: 11px;">(edit)</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color: <?php echo $status_color; ?>; font-weight: bold;">
                                <?php echo $status_text; ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date('M j, Y', strtotime($account->user_registered)); ?>
                        </td>
                        <td>
                            <?php if ($account->status === 'pending_approval'): ?>
                                <form method="post" style="margin: 0;">
                                    <?php wp_nonce_field('ham_approve_account_' . $account->user_id); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $account->user_id; ?>">
                                    <button type="submit" name="ham_approve_account" class="button button-small button-primary"
                                            onclick="return confirm('Approve this account?');">
                                        Approve
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="<?php echo admin_url('admin.php?page=ham-edit-membership&id=' . $account->id); ?>" 
                                   class="button button-small">Manage</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Simple filtering
document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll('.subsubsub a');
    const rows = document.querySelectorAll('tbody tr[data-status]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active link
            links.forEach(l => l.classList.remove('current'));
            this.classList.add('current');
            
            // Filter rows
            const filter = this.getAttribute('href').substring(1);
            
            rows.forEach(row => {
                if (filter === '' || row.dataset.status.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
});
</script>
