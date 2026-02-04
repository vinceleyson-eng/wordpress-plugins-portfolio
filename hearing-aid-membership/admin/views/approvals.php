<?php
/**
 * Approval Dashboard for Admin
 * Shows pending store locations and audiologists
 */

if (!defined('ABSPATH')) exit;

// Get pending stores
$pending_stores = get_posts(array(
    'post_type' => 'hearing-aid-store',
    'post_status' => 'pending',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC'
));

// Get pending audiologists
$pending_audiologists = get_posts(array(
    'post_type' => 'audiologist',
    'post_status' => 'pending',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC'
));

$total_pending = count($pending_stores) + count($pending_audiologists);

// Handle approval/rejection
if (isset($_POST['ham_approve_store'])) {
    check_admin_referer('ham_approve_' . $_POST['post_id']);
    $post_id = intval($_POST['post_id']);
    
    wp_update_post(array(
        'ID' => $post_id,
        'post_status' => 'publish'
    ));
    
    // Send notification to user
    $post = get_post($post_id);
    $user = get_userdata($post->post_author);
    
    $subject = 'Your Store Location Has Been Approved!';
    $message = "Hi " . $user->display_name . ",\n\n";
    $message .= "Great news! Your store location '" . $post->post_title . "' has been approved and is now live on the site.\n\n";
    $message .= "View it here: " . get_permalink($post_id) . "\n\n";
    $message .= "Thank you for being part of our directory!";
    
    wp_mail($user->user_email, $subject, $message);
    
    echo '<div class="notice notice-success"><p><strong>Store approved!</strong> User has been notified.</p></div>';
}

if (isset($_POST['ham_reject_store'])) {
    check_admin_referer('ham_approve_' . $_POST['post_id']);
    $post_id = intval($_POST['post_id']);
    
    wp_trash_post($post_id);
    
    echo '<div class="notice notice-success"><p><strong>Store rejected and moved to trash.</strong></p></div>';
}

if (isset($_POST['ham_approve_audiologist'])) {
    check_admin_referer('ham_approve_audio_' . $_POST['post_id']);
    $post_id = intval($_POST['post_id']);
    
    wp_update_post(array(
        'ID' => $post_id,
        'post_status' => 'publish'
    ));
    
    // Send notification to user
    $post = get_post($post_id);
    $user = get_userdata($post->post_author);
    
    $subject = 'Your Audiologist Profile Has Been Approved!';
    $message = "Hi " . $user->display_name . ",\n\n";
    $message .= "Great news! The audiologist profile for '" . $post->post_title . "' has been approved and is now live.\n\n";
    $message .= "View it here: " . get_permalink($post_id) . "\n\n";
    $message .= "Thank you!";
    
    wp_mail($user->user_email, $subject, $message);
    
    echo '<div class="notice notice-success"><p><strong>Audiologist approved!</strong> User has been notified.</p></div>';
}

if (isset($_POST['ham_reject_audiologist'])) {
    check_admin_referer('ham_approve_audio_' . $_POST['post_id']);
    $post_id = intval($_POST['post_id']);
    
    wp_trash_post($post_id);
    
    echo '<div class="notice notice-success"><p><strong>Audiologist profile rejected and moved to trash.</strong></p></div>';
}

?>

<div class="wrap">
    <h1>
        Pending Approvals
        <?php if ($total_pending > 0): ?>
            <span class="update-plugins count-<?php echo $total_pending; ?>"><span class="update-count"><?php echo $total_pending; ?></span></span>
        <?php endif; ?>
    </h1>
    
    <?php if ($total_pending === 0): ?>
        <div class="notice notice-info" style="padding: 20px;">
            <p style="font-size: 16px; margin: 0;">✅ <strong>All caught up!</strong> No pending submissions at this time.</p>
        </div>
    <?php endif; ?>
    
    <!-- Pending Store Locations -->
    <?php if (!empty($pending_stores)): ?>
        <div class="card" style="margin-top: 20px;">
            <h2 style="margin: 20px; padding-bottom: 10px; border-bottom: 2px solid #ddd;">
                Store Locations Pending Approval
                <span style="background: #f0b429; color: white; padding: 3px 10px; border-radius: 12px; font-size: 14px; margin-left: 10px;">
                    <?php echo count($pending_stores); ?>
                </span>
            </h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30%;">Store Name</th>
                        <th style="width: 20%;">Submitted By</th>
                        <th style="width: 20%;">Contact Info</th>
                        <th style="width: 15%;">Submitted</th>
                        <th style="width: 15%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_stores as $store): 
                        $user = get_userdata($store->post_author);
                        $store_phone = get_field('store_phone_number', $store->ID);
                        $store_address = get_field('store_address', $store->ID);
                        $store_email = get_field('store_email', $store->ID);
                        
                        // Get user's membership
                        global $wpdb;
                        $membership = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE user_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
                            $user->ID
                        ));
                    ?>
                        <tr>
                            <td>
                                <strong style="font-size: 16px;"><?php echo esc_html($store->post_title); ?></strong>
                                <div class="row-actions">
                                    <a href="<?php echo get_edit_post_link($store->ID); ?>">Edit Full Details</a>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                <small><?php echo esc_html($user->user_email); ?></small>
                                <?php if ($membership): ?>
                                    <br><span style="display: inline-block; margin-top: 5px; padding: 3px 8px; background: <?php echo $membership->membership_type === 'preferred' ? '#2c5f5d' : '#2271b1'; ?>; color: white; font-size: 11px; border-radius: 10px;">
                                        <?php echo ucfirst($membership->membership_type); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($store_address): ?>
                                    <div>📍 <?php echo esc_html($store_address); ?></div>
                                <?php endif; ?>
                                <?php if ($store_phone): ?>
                                    <div>📞 <?php echo esc_html($store_phone); ?></div>
                                <?php endif; ?>
                                <?php if ($store_email): ?>
                                    <div>✉️ <?php echo esc_html($store_email); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo human_time_diff(strtotime($store->post_date), current_time('timestamp')) . ' ago'; ?>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('ham_approve_' . $store->ID); ?>
                                    <input type="hidden" name="post_id" value="<?php echo $store->ID; ?>">
                                    <button type="submit" name="ham_approve_store" class="button button-primary" 
                                            onclick="return confirm('Approve this store location?');">
                                        ✓ Approve
                                    </button>
                                </form>
                                
                                <form method="post" style="display: inline; margin-left: 5px;">
                                    <?php wp_nonce_field('ham_approve_' . $store->ID); ?>
                                    <input type="hidden" name="post_id" value="<?php echo $store->ID; ?>">
                                    <button type="submit" name="ham_reject_store" class="button" 
                                            onclick="return confirm('Reject and trash this store?');">
                                        ✗ Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Pending Audiologists -->
    <?php if (!empty($pending_audiologists)): ?>
        <div class="card" style="margin-top: 20px;">
            <h2 style="margin: 20px; padding-bottom: 10px; border-bottom: 2px solid #ddd;">
                Audiologist Profiles Pending Approval
                <span style="background: #f0b429; color: white; padding: 3px 10px; border-radius: 12px; font-size: 14px; margin-left: 10px;">
                    <?php echo count($pending_audiologists); ?>
                </span>
            </h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30%;">Audiologist Name</th>
                        <th style="width: 20%;">Submitted By</th>
                        <th style="width: 25%;">Details</th>
                        <th style="width: 15%;">Submitted</th>
                        <th style="width: 15%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_audiologists as $audiologist): 
                        $user = get_userdata($audiologist->post_author);
                        $linked_store_id = get_post_meta($audiologist->ID, 'linked_store_id', true);
                        $linked_store = $linked_store_id ? get_post($linked_store_id) : null;
                    ?>
                        <tr>
                            <td>
                                <strong style="font-size: 16px;"><?php echo esc_html($audiologist->post_title); ?></strong>
                                <?php if ($audiologist->post_excerpt): ?>
                                    <br><small style="color: #666;"><?php echo esc_html($audiologist->post_excerpt); ?></small>
                                <?php endif; ?>
                                <div class="row-actions">
                                    <a href="<?php echo get_edit_post_link($audiologist->ID); ?>">Edit Full Details</a>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                <small><?php echo esc_html($user->user_email); ?></small>
                            </td>
                            <td>
                                <?php if ($linked_store): ?>
                                    <div>📍 Works at: <strong><?php echo esc_html($linked_store->post_title); ?></strong></div>
                                <?php endif; ?>
                                <?php if ($audiologist->post_content): ?>
                                    <div style="margin-top: 5px; color: #666; font-style: italic;">
                                        <?php echo esc_html(wp_trim_words($audiologist->post_content, 15)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo human_time_diff(strtotime($audiologist->post_date), current_time('timestamp')) . ' ago'; ?>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('ham_approve_audio_' . $audiologist->ID); ?>
                                    <input type="hidden" name="post_id" value="<?php echo $audiologist->ID; ?>">
                                    <button type="submit" name="ham_approve_audiologist" class="button button-primary"
                                            onclick="return confirm('Approve this audiologist profile?');">
                                        ✓ Approve
                                    </button>
                                </form>
                                
                                <form method="post" style="display: inline; margin-left: 5px;">
                                    <?php wp_nonce_field('ham_approve_audio_' . $audiologist->ID); ?>
                                    <input type="hidden" name="post_id" value="<?php echo $audiologist->ID; ?>">
                                    <button type="submit" name="ham_reject_audiologist" class="button"
                                            onclick="return confirm('Reject and trash this profile?');">
                                        ✗ Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Recently Approved -->
    <?php
    $recently_approved_stores = get_posts(array(
        'post_type' => 'hearing-aid-store',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'orderby' => 'modified',
        'order' => 'DESC',
        'date_query' => array(
            array(
                'column' => 'post_modified',
                'after' => '24 hours ago'
            )
        )
    ));
    
    if (!empty($recently_approved_stores)):
    ?>
        <div class="card" style="margin-top: 20px;">
            <h2 style="margin: 20px; padding-bottom: 10px; border-bottom: 2px solid #ddd;">
                Recently Approved (Last 24 Hours)
            </h2>
            
            <table class="wp-list-table widefat">
                <tbody>
                    <?php foreach ($recently_approved_stores as $store): ?>
                        <tr>
                            <td style="padding: 10px;">
                                <span style="color: #00a32a; font-size: 18px; margin-right: 10px;">✓</span>
                                <strong><?php echo esc_html($store->post_title); ?></strong>
                                <span style="color: #666; margin-left: 10px;">
                                    - Approved <?php echo human_time_diff(strtotime($store->post_modified), current_time('timestamp')) . ' ago'; ?>
                                </span>
                                <div class="row-actions">
                                    <a href="<?php echo get_permalink($store->ID); ?>" target="_blank">View</a> |
                                    <a href="<?php echo get_edit_post_link($store->ID); ?>">Edit</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
</div>

<style>
.update-plugins {
    display: inline-block;
    vertical-align: top;
    box-sizing: border-box;
    margin: 1px 0 -1px 2px;
    padding: 0 5px;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    background-color: #d63638;
    color: #fff;
    font-size: 11px;
    line-height: 1.6;
    text-align: center;
}
</style>
