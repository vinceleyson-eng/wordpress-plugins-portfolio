<?php
/**
 * Account Assignment Tool
 * Assign existing stores and audiologists to user accounts
 * Create missing membership records
 */

if (!defined('ABSPATH')) exit;

// Determine which tab to show after form submission
$active_tab = 'users';

// Handle account creation
if (isset($_POST['ham_create_account_for_user'])) {
    check_admin_referer('ham_create_account_' . $_POST['user_id']);
    $user_id = intval($_POST['user_id']);
    
    global $wpdb;
    
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}ham_memberships WHERE user_id = %d",
        $user_id
    ));
    
    if (!$existing) {
        $wpdb->insert(
            $wpdb->prefix . 'ham_memberships',
            array(
                'user_id' => $user_id,
                'membership_type' => 'unverified',
                'billing_cycle' => 'monthly',
                'price' => 0.00,
                'status' => 'active',
                'auto_renew' => 0,
                'start_date' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%f', '%s', '%d', '%s', '%s', '%s')
        );
        echo '<div class="notice notice-success is-dismissible"><p><strong>Free account created for user!</strong></p></div>';
    } else {
        echo '<div class="notice notice-warning is-dismissible"><p>This user already has a membership account.</p></div>';
    }
    $active_tab = 'users';
}

// Handle store assignment
if (isset($_POST['ham_assign_store'])) {
    check_admin_referer('ham_assign_store_' . $_POST['store_id']);
    $store_id = intval($_POST['store_id']);
    $user_id = intval($_POST['assign_user_id']);
    
    wp_update_post(array(
        'ID' => $store_id,
        'post_author' => $user_id
    ));
    
    global $wpdb;
    $membership = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE user_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));
    
    if ($membership && !$membership->store_id) {
        $wpdb->update(
            $wpdb->prefix . 'ham_memberships',
            array('store_id' => $store_id),
            array('id' => $membership->id),
            array('%d'),
            array('%d')
        );
    }
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>Store assigned to user!</strong></p></div>';
    $active_tab = 'stores';
}

// Handle audiologist assignment
if (isset($_POST['ham_assign_audiologist'])) {
    check_admin_referer('ham_assign_audio_' . $_POST['audio_id']);
    $audio_id = intval($_POST['audio_id']);
    $user_id = intval($_POST['assign_user_id']);
    
    wp_update_post(array(
        'ID' => $audio_id,
        'post_author' => $user_id
    ));
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>Audiologist assigned to user!</strong></p></div>';
    $active_tab = 'audiologists';
}

// Get all WordPress users
$all_users = get_users(array('orderby' => 'registered', 'order' => 'DESC'));

// Get all stores
$all_stores = get_posts(array(
    'post_type' => 'hearing-aid-store',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'orderby' => 'title',
    'order' => 'ASC'
));

// Get all audiologists
$all_audiologists = get_posts(array(
    'post_type' => 'audiologist',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'orderby' => 'title',
    'order' => 'ASC'
));

global $wpdb;

// Get users WITHOUT membership accounts
$users_without_accounts = array();
foreach ($all_users as $user) {
    $has_membership = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ham_memberships WHERE user_id = %d",
        $user->ID
    ));
    
    if (!$has_membership) {
        $users_without_accounts[] = $user;
    }
}
?>

<style>
    .ham-tabs { display: flex; gap: 0; border-bottom: 1px solid #c3c4c7; margin: 20px 0 0 0; }
    .ham-tab { 
        padding: 12px 20px; 
        background: #f0f0f1; 
        border: 1px solid #c3c4c7; 
        border-bottom: none;
        margin-bottom: -1px;
        margin-right: -1px;
        text-decoration: none;
        color: #50575e;
        font-weight: 500;
        border-radius: 4px 4px 0 0;
        cursor: pointer;
    }
    .ham-tab:hover { background: #fff; color: #2271b1; }
    .ham-tab.active { 
        background: #fff; 
        border-bottom-color: #fff;
        color: #1d2327;
        font-weight: 600;
    }
    .ham-tab .count {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 12px;
        margin-left: 6px;
        font-weight: normal;
    }
    .ham-tab .count-users { background: #f0b429; color: white; }
    .ham-tab .count-stores { background: #0c5460; color: white; }
    .ham-tab .count-audio { background: #155724; color: white; }
    
    .ham-tab-content { 
        background: #fff; 
        border: 1px solid #c3c4c7; 
        border-top: none;
        padding: 20px;
        display: none;
    }
    .ham-tab-content.active { display: block; }
    
    .ham-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    .ham-stat-card { padding: 15px; border-radius: 6px; text-align: center; }
    .ham-stat-card .number { font-size: 28px; font-weight: bold; }
    .ham-stat-card .label { color: #666; font-size: 13px; margin-top: 4px; }
    
    .ham-table { width: 100%; border-collapse: collapse; }
    .ham-table th, .ham-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e1e1e1; vertical-align: middle; }
    .ham-table th { background: #f6f7f7; font-weight: 600; }
    .ham-table tr:hover { background: #f9f9f9; }
    .ham-table .col-name { width: 22%; }
    .ham-table .col-email { width: 22%; }
    .ham-table .col-owner { width: 22%; }
    .ham-table .col-status { width: 8%; white-space: nowrap; }
    .ham-table .col-owned { width: 12%; }
    .ham-table .col-assign { width: 36%; }
    .ham-table .col-action { width: 14%; white-space: nowrap; }
    
    .ham-table .assign-form { display: flex; gap: 8px; align-items: center; }
    .ham-table .assign-form select { flex: 1; min-width: 200px; }
    
    .ham-empty { text-align: center; padding: 40px; color: #666; }
</style>

<div class="wrap">
    <h1>Account Assignment Tool</h1>
    <p>Create membership accounts for existing users and assign stores/audiologists to their accounts.</p>
    
    <!-- Stats Summary -->
    <div class="ham-stats-grid">
        <div class="ham-stat-card" style="background: #fff3cd;">
            <div class="number" style="color: #f0b429;"><?php echo count($users_without_accounts); ?></div>
            <div class="label">Users Without Accounts</div>
        </div>
        <div class="ham-stat-card" style="background: #d1ecf1;">
            <div class="number" style="color: #0c5460;"><?php echo count($all_stores); ?></div>
            <div class="label">Total Stores</div>
        </div>
        <div class="ham-stat-card" style="background: #d4edda;">
            <div class="number" style="color: #155724;"><?php echo count($all_audiologists); ?></div>
            <div class="label">Total Audiologists</div>
        </div>
    </div>
    
    <!-- Tabs Navigation -->
    <div class="ham-tabs">
        <div class="ham-tab <?php echo $active_tab === 'users' ? 'active' : ''; ?>" data-tab="users">
            👤 Users Without Accounts
            <span class="count count-users"><?php echo count($users_without_accounts); ?></span>
        </div>
        <div class="ham-tab <?php echo $active_tab === 'stores' ? 'active' : ''; ?>" data-tab="stores">
            🏪 Assign Stores
            <span class="count count-stores"><?php echo count($all_stores); ?></span>
        </div>
        <div class="ham-tab <?php echo $active_tab === 'audiologists' ? 'active' : ''; ?>" data-tab="audiologists">
            👨‍⚕️ Assign Audiologists
            <span class="count count-audio"><?php echo count($all_audiologists); ?></span>
        </div>
    </div>
    
    <!-- Tab 1: Users Without Accounts -->
    <div class="ham-tab-content <?php echo $active_tab === 'users' ? 'active' : ''; ?>" id="tab-users">
        <?php if (empty($users_without_accounts)): ?>
            <div class="ham-empty">
                <p style="font-size: 18px;">✅ <strong>All users have membership accounts!</strong></p>
                <p>There are no users without accounts to display.</p>
            </div>
        <?php else: ?>
            <p style="margin-bottom: 15px;">These users exist in WordPress but don't have a membership account yet. Click "Create Free Account" to set them up.</p>
            <table class="ham-table">
                <thead>
                    <tr>
                        <th class="col-name">User</th>
                        <th class="col-email">Email</th>
                        <th>Registered</th>
                        <th class="col-owned">Stores</th>
                        <th class="col-owned">Audiologists</th>
                        <th class="col-action">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_without_accounts as $user): 
                        $user_stores = get_posts(array(
                            'post_type' => 'hearing-aid-store',
                            'author' => $user->ID,
                            'posts_per_page' => -1,
                            'post_status' => 'any'
                        ));
                        
                        $user_audios = get_posts(array(
                            'post_type' => 'audiologist',
                            'author' => $user->ID,
                            'posts_per_page' => -1,
                            'post_status' => 'any'
                        ));
                    ?>
                        <tr>
                            <td class="col-name">
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <div class="row-actions">
                                    <a href="<?php echo get_edit_user_link($user->ID); ?>">Edit User</a>
                                </div>
                            </td>
                            <td class="col-email"><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user->user_registered)); ?></td>
                            <td class="col-owned">
                                <?php if (empty($user_stores)): ?>
                                    <span style="color: #999;">0</span>
                                <?php else: ?>
                                    <strong><?php echo count($user_stores); ?></strong>
                                    <div style="font-size: 11px; color: #666;">
                                        <?php foreach (array_slice($user_stores, 0, 2) as $store): ?>
                                            <div>• <?php echo esc_html(wp_trim_words($store->post_title, 3)); ?></div>
                                        <?php endforeach; ?>
                                        <?php if (count($user_stores) > 2): ?>
                                            <div>+ <?php echo count($user_stores) - 2; ?> more</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="col-owned">
                                <?php if (empty($user_audios)): ?>
                                    <span style="color: #999;">0</span>
                                <?php else: ?>
                                    <strong><?php echo count($user_audios); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="col-action">
                                <form method="post" style="margin: 0;">
                                    <?php wp_nonce_field('ham_create_account_' . $user->ID); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                    <button type="submit" name="ham_create_account_for_user" class="button button-primary">
                                        Create Free Account
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Tab 2: Assign Stores -->
    <div class="ham-tab-content <?php echo $active_tab === 'stores' ? 'active' : ''; ?>" id="tab-stores">
        <p style="margin-bottom: 15px;">Reassign stores to different user accounts. Useful for fixing ownership or consolidating accounts.</p>
        
        <?php if (empty($all_stores)): ?>
            <div class="ham-empty">
                <p>No stores found.</p>
            </div>
        <?php else: ?>
            <table class="ham-table">
                <thead>
                    <tr>
                        <th class="col-name">Store Name</th>
                        <th class="col-owner">Current Owner</th>
                        <th class="col-status">Status</th>
                        <th class="col-assign">Assign To</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_stores as $store): 
                        $current_owner = get_userdata($store->post_author);
                    ?>
                        <tr>
                            <td class="col-name">
                                <strong><?php echo esc_html($store->post_title); ?></strong>
                                <div class="row-actions">
                                    <a href="<?php echo get_edit_post_link($store->ID); ?>">Edit</a> |
                                    <a href="<?php echo get_permalink($store->ID); ?>" target="_blank">View</a>
                                </div>
                            </td>
                            <td class="col-owner">
                                <?php if ($current_owner): ?>
                                    <?php echo esc_html($current_owner->display_name); ?><br>
                                    <small style="color: #666;"><?php echo esc_html($current_owner->user_email); ?></small>
                                <?php else: ?>
                                    <span style="color: #d63638;">No owner assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-status">
                                <?php $status_color = $store->post_status === 'publish' ? '#00a32a' : '#f0b429'; ?>
                                <span style="color: <?php echo $status_color; ?>; font-weight: 600;">
                                    <?php echo ucfirst($store->post_status); ?>
                                </span>
                            </td>
                            <td class="col-assign">
                                <form method="post" class="assign-form">
                                    <?php wp_nonce_field('ham_assign_store_' . $store->ID); ?>
                                    <input type="hidden" name="store_id" value="<?php echo $store->ID; ?>">
                                    <select name="assign_user_id" required>
                                        <option value="">-- Select User --</option>
                                        <?php foreach ($all_users as $user): ?>
                                            <option value="<?php echo $user->ID; ?>" <?php selected($store->post_author, $user->ID); ?>>
                                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="ham_assign_store" class="button button-primary">Assign</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Tab 3: Assign Audiologists -->
    <div class="ham-tab-content <?php echo $active_tab === 'audiologists' ? 'active' : ''; ?>" id="tab-audiologists">
        <p style="margin-bottom: 15px;">Reassign audiologist profiles to different user accounts.</p>
        
        <?php if (empty($all_audiologists)): ?>
            <div class="ham-empty">
                <p>No audiologists found.</p>
            </div>
        <?php else: ?>
            <table class="ham-table">
                <thead>
                    <tr>
                        <th class="col-name">Audiologist Name</th>
                        <th class="col-owner">Current Owner</th>
                        <th class="col-status">Status</th>
                        <th class="col-assign">Assign To</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_audiologists as $audio): 
                        $current_owner = get_userdata($audio->post_author);
                    ?>
                        <tr>
                            <td class="col-name">
                                <strong><?php echo esc_html($audio->post_title); ?></strong>
                                <?php if ($audio->post_excerpt): ?>
                                    <br><small style="color: #666;"><?php echo esc_html($audio->post_excerpt); ?></small>
                                <?php endif; ?>
                                <div class="row-actions">
                                    <a href="<?php echo get_edit_post_link($audio->ID); ?>">Edit</a>
                                </div>
                            </td>
                            <td class="col-owner">
                                <?php if ($current_owner): ?>
                                    <?php echo esc_html($current_owner->display_name); ?><br>
                                    <small style="color: #666;"><?php echo esc_html($current_owner->user_email); ?></small>
                                <?php else: ?>
                                    <span style="color: #d63638;">No owner assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-status">
                                <?php $status_color = $audio->post_status === 'publish' ? '#00a32a' : '#f0b429'; ?>
                                <span style="color: <?php echo $status_color; ?>; font-weight: 600;">
                                    <?php echo ucfirst($audio->post_status); ?>
                                </span>
                            </td>
                            <td class="col-assign">
                                <form method="post" class="assign-form">
                                    <?php wp_nonce_field('ham_assign_audio_' . $audio->ID); ?>
                                    <input type="hidden" name="audio_id" value="<?php echo $audio->ID; ?>">
                                    <select name="assign_user_id" required>
                                        <option value="">-- Select User --</option>
                                        <?php foreach ($all_users as $user): ?>
                                            <option value="<?php echo $user->ID; ?>" <?php selected($audio->post_author, $user->ID); ?>>
                                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="ham_assign_audiologist" class="button button-primary">Assign</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.ham-tab');
    const contents = document.querySelectorAll('.ham-tab-content');
    
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active from all tabs and contents
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Add active to clicked tab and corresponding content
            this.classList.add('active');
            document.getElementById('tab-' + targetTab).classList.add('active');
        });
    });
});
</script>
