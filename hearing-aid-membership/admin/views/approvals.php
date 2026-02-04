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
    
    $post = get_post($post_id);
    $user = get_userdata($post->post_author);
    
    $subject = 'Your Store Location Has Been Approved!';
    $message = "Hi " . $user->display_name . ",\n\n";
    $message .= "Great news! Your store location '" . $post->post_title . "' has been approved and is now live on the site.\n\n";
    $message .= "View it here: " . get_permalink($post_id) . "\n\n";
    $message .= "Thank you for being part of our directory!";
    
    wp_mail($user->user_email, $subject, $message);
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>Store approved!</strong> User has been notified.</p></div>';
    
    // Refresh list
    $pending_stores = get_posts(array('post_type' => 'hearing-aid-store', 'post_status' => 'pending', 'posts_per_page' => -1));
}

if (isset($_POST['ham_reject_store'])) {
    check_admin_referer('ham_approve_' . $_POST['post_id']);
    wp_trash_post(intval($_POST['post_id']));
    echo '<div class="notice notice-warning is-dismissible"><p><strong>Store rejected and moved to trash.</strong></p></div>';
    $pending_stores = get_posts(array('post_type' => 'hearing-aid-store', 'post_status' => 'pending', 'posts_per_page' => -1));
}

if (isset($_POST['ham_approve_audiologist'])) {
    check_admin_referer('ham_approve_audio_' . $_POST['post_id']);
    $post_id = intval($_POST['post_id']);
    
    wp_update_post(array(
        'ID' => $post_id,
        'post_status' => 'publish'
    ));
    
    $post = get_post($post_id);
    $user = get_userdata($post->post_author);
    
    $subject = 'Your Audiologist Profile Has Been Approved!';
    $message = "Hi " . $user->display_name . ",\n\n";
    $message .= "Great news! The audiologist profile for '" . $post->post_title . "' has been approved and is now live.\n\n";
    $message .= "View it here: " . get_permalink($post_id) . "\n\n";
    $message .= "Thank you!";
    
    wp_mail($user->user_email, $subject, $message);
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>Audiologist approved!</strong> User has been notified.</p></div>';
    $pending_audiologists = get_posts(array('post_type' => 'audiologist', 'post_status' => 'pending', 'posts_per_page' => -1));
}

if (isset($_POST['ham_reject_audiologist'])) {
    check_admin_referer('ham_approve_audio_' . $_POST['post_id']);
    wp_trash_post(intval($_POST['post_id']));
    echo '<div class="notice notice-warning is-dismissible"><p><strong>Audiologist profile rejected and moved to trash.</strong></p></div>';
    $pending_audiologists = get_posts(array('post_type' => 'audiologist', 'post_status' => 'pending', 'posts_per_page' => -1));
}

$total_pending = count($pending_stores) + count($pending_audiologists);
?>

<style>
    .ham-card { 
        background: #fff; 
        border: 1px solid #c3c4c7; 
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 0;
        margin-top: 20px;
        max-width: none !important;
    }
    .ham-card-header {
        padding: 15px 20px;
        border-bottom: 1px solid #e1e1e1;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .ham-card-header h2 { margin: 0; font-size: 16px; }
    .ham-count-badge {
        background: #f0b429;
        color: white;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
    }
    
    .ham-table { width: 100%; border-collapse: collapse; }
    .ham-table th, .ham-table td { 
        padding: 15px 20px; 
        text-align: left; 
        border-bottom: 1px solid #f0f0f1; 
        vertical-align: top;
    }
    .ham-table th { 
        background: #f6f7f7; 
        font-weight: 600; 
        font-size: 13px;
        color: #50575e;
    }
    .ham-table tr:hover { background: #f9f9f9; }
    .ham-table tr:last-child td { border-bottom: none; }
    
    .ham-store-name { font-size: 15px; font-weight: 600; color: #1d2327; margin-bottom: 5px; }
    .ham-user-info { font-size: 13px; }
    .ham-user-info strong { color: #1d2327; }
    .ham-user-info small { color: #666; word-break: break-all; }
    .ham-membership-badge {
        display: inline-block;
        margin-top: 6px;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 500;
        color: white;
    }
    
    .ham-contact-info { font-size: 13px; line-height: 1.8; }
    .ham-contact-info div { display: flex; align-items: flex-start; gap: 6px; }
    
    .ham-time { color: #666; font-size: 13px; }
    
    .ham-actions { white-space: nowrap; }
    .ham-actions form { display: inline-block; margin-right: 5px; }
    .ham-actions .button { padding: 4px 12px; }
    
    .ham-empty { 
        padding: 40px; 
        text-align: center; 
        background: #f0f6fc;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        margin-top: 20px;
    }
    .ham-empty p { margin: 0; font-size: 15px; color: #1d2327; }
    
    .ham-recent { padding: 15px 20px; }
    .ham-recent-item { 
        padding: 10px 0; 
        border-bottom: 1px solid #f0f0f1; 
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .ham-recent-item:last-child { border-bottom: none; }
    .ham-check { color: #00a32a; font-size: 18px; }
</style>

<div class="wrap">
    <h1>
        Pending Approvals
        <?php if ($total_pending > 0): ?>
            <span class="ham-count-badge" style="margin-left: 10px;"><?php echo $total_pending; ?> pending</span>
        <?php endif; ?>
    </h1>
    
    <?php if ($total_pending === 0): ?>
        <div class="ham-empty">
            <p>✅ <strong>All caught up!</strong> No pending submissions at this time.</p>
        </div>
    <?php endif; ?>
    
    <!-- Pending Store Locations -->
    <?php if (!empty($pending_stores)): ?>
        <div class="ham-card">
            <div class="ham-card-header">
                <h2>🏪 Store Locations Pending Approval</h2>
                <span class="ham-count-badge"><?php echo count($pending_stores); ?></span>
            </div>
            
            <table class="ham-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Store Name</th>
                        <th style="width: 20%;">Submitted By</th>
                        <th style="width: 25%;">Contact Info</th>
                        <th style="width: 12%;">Submitted</th>
                        <th style="width: 18%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_stores as $store): 
                        $user = get_userdata($store->post_author);
                        $store_phone = function_exists('get_field') ? get_field('store_phone_number', $store->ID) : get_post_meta($store->ID, 'store_phone_number', true);
                        $store_address = function_exists('get_field') ? get_field('store_address', $store->ID) : get_post_meta($store->ID, 'store_address', true);
                        $store_email = function_exists('get_field') ? get_field('store_email', $store->ID) : get_post_meta($store->ID, 'store_email', true);
                        
                        global $wpdb;
                        $membership = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE user_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
                            $user->ID
                        ));
                        
                        $badge_colors = array('preferred' => '#2c5f5d', 'verified' => '#00a32a', 'unverified' => '#999');
                    ?>
                        <tr>
                            <td>
                                <div class="ham-store-name"><?php echo esc_html($store->post_title); ?></div>
                                <a href="<?php echo get_edit_post_link($store->ID); ?>" style="font-size: 12px;">Edit Full Details →</a>
                            </td>
                            <td>
                                <div class="ham-user-info">
                                    <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                    <small><?php echo esc_html($user->user_email); ?></small>
                                    <?php if ($membership): ?>
                                        <br><span class="ham-membership-badge" style="background: <?php echo $badge_colors[$membership->membership_type] ?? '#999'; ?>;">
                                            <?php echo ucfirst($membership->membership_type); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="ham-contact-info">
                                    <?php if ($store_address): ?>
                                        <div>📍 <?php echo esc_html($store_address); ?></div>
                                    <?php endif; ?>
                                    <?php if ($store_phone): ?>
                                        <div>📞 <?php echo esc_html($store_phone); ?></div>
                                    <?php endif; ?>
                                    <?php if ($store_email): ?>
                                        <div>✉️ <?php echo esc_html($store_email); ?></div>
                                    <?php endif; ?>
                                    <?php if (!$store_address && !$store_phone && !$store_email): ?>
                                        <span style="color: #999;">No contact info</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="ham-time"><?php echo human_time_diff(strtotime($store->post_date), current_time('timestamp')); ?> ago</span>
                            </td>
                            <td class="ham-actions">
                                <form method="post">
                                    <?php wp_nonce_field('ham_approve_' . $store->ID); ?>
                                    <input type="hidden" name="post_id" value="<?php echo $store->ID; ?>">
                                    <button type="submit" name="ham_approve_store" class="button button-primary" onclick="return confirm('Approve this store location?');">
                                        ✓ Approve
                                    </button>
                                </form>
                                <form method="post">
                                    <?php wp_nonce_field('ham_approve_' . $store->ID); ?>
                                    <input type="hidden" name="post_id" value="<?php echo $store->ID; ?>">
                                    <button type="submit" name="ham_reject_store" class="button" onclick="return confirm('Reject and trash this store?');">
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
        <div class="ham-card">
            <div class="ham-card-header">
                <h2>👨‍⚕️ Audiologist Profiles Pending Approval</h2>
                <span class="ham-count-badge"><?php echo count($pending_audiologists); ?></span>
            </div>
            
            <table class="ham-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Audiologist</th>
                        <th style="width: 20%;">Submitted By</th>
                        <th style="width: 25%;">Details</th>
                        <th style="width: 12%;">Submitted</th>
                        <th style="width: 18%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_audiologists as $audiologist): 
                        $user = get_userdata($audiologist->post_author);
                        $linked_store_id = get_post_meta($audiologist->ID, 'linked_store_id', true);
                        $linked_store = $linked_store_id ? get_post($linked_store_id) : null;
                        $bio = function_exists('get_field') ? get_field('audiologists_bio', $audiologist->ID) : '';
                    ?>
                        <tr>
                            <td>
                                <div class="ham-store-name"><?php echo esc_html($audiologist->post_title); ?></div>
                                <?php if ($audiologist->post_excerpt): ?>
                                    <div style="color: #666; font-size: 13px; margin-bottom: 5px;"><?php echo esc_html($audiologist->post_excerpt); ?></div>
                                <?php endif; ?>
                                <a href="<?php echo get_edit_post_link($audiologist->ID); ?>" style="font-size: 12px;">Edit Full Details →</a>
                            </td>
                            <td>
                                <div class="ham-user-info">
                                    <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                    <small><?php echo esc_html($user->user_email); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="ham-contact-info">
                                    <?php if ($linked_store): ?>
                                        <div>📍 Works at: <strong><?php echo esc_html($linked_store->post_title); ?></strong></div>
                                    <?php endif; ?>
                                    <?php if ($bio): ?>
                                        <div style="margin-top: 5px; color: #666; font-style: italic;">
                                            <?php echo esc_html(wp_trim_words(strip_tags($bio), 15)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!$linked_store && !$bio): ?>
                                        <span style="color: #999;">No additional details</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="ham-time"><?php echo human_time_diff(strtotime($audiologist->post_date), current_time('timestamp')); ?> ago</span>
                            </td>
                            <td class="ham-actions">
                                <form method="post">
                                    <?php wp_nonce_field('ham_approve_audio_' . $audiologist->ID); ?>
                                    <input type="hidden" name="post_id" value="<?php echo $audiologist->ID; ?>">
                                    <button type="submit" name="ham_approve_audiologist" class="button button-primary" onclick="return confirm('Approve this audiologist profile?');">
                                        ✓ Approve
                                    </button>
                                </form>
                                <form method="post">
                                    <?php wp_nonce_field('ham_approve_audio_' . $audiologist->ID); ?>
                                    <input type="hidden" name="post_id" value="<?php echo $audiologist->ID; ?>">
                                    <button type="submit" name="ham_reject_audiologist" class="button" onclick="return confirm('Reject and trash this profile?');">
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
    $recently_approved = get_posts(array(
        'post_type' => array('hearing-aid-store', 'audiologist'),
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'orderby' => 'modified',
        'order' => 'DESC',
        'date_query' => array(
            array(
                'column' => 'post_modified',
                'after' => '7 days ago'
            )
        )
    ));
    
    if (!empty($recently_approved)):
    ?>
        <div class="ham-card">
            <div class="ham-card-header">
                <h2>✅ Recently Approved (Last 7 Days)</h2>
            </div>
            <div class="ham-recent">
                <?php foreach ($recently_approved as $item): ?>
                    <div class="ham-recent-item">
                        <span class="ham-check">✓</span>
                        <div style="flex: 1;">
                            <strong><?php echo esc_html($item->post_title); ?></strong>
                            <span style="color: #666; margin-left: 8px; font-size: 13px;">
                                (<?php echo $item->post_type === 'hearing-aid-store' ? 'Store' : 'Audiologist'; ?>)
                            </span>
                            <span style="color: #999; margin-left: 8px; font-size: 12px;">
                                <?php echo human_time_diff(strtotime($item->post_modified), current_time('timestamp')); ?> ago
                            </span>
                        </div>
                        <a href="<?php echo get_permalink($item->ID); ?>" target="_blank" class="button button-small">View</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
</div>
