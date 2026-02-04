<?php
/**
 * Import existing stores into membership system
 * Add to admin menu under HA Membership
 */

if (!defined('ABSPATH')) exit;

// Add import page to admin menu
add_action('admin_menu', 'ham_add_import_page', 20);
function ham_add_import_page() {
    add_submenu_page(
        'ha-membership',
        'Import Existing Stores',
        'Import Stores',
        'manage_options',
        'ham-import',
        'ham_import_page'
    );
}

// Import page
function ham_import_page() {
    global $wpdb;
    
    // Handle import action
    if (isset($_POST['ham_import_stores'])) {
        check_admin_referer('ham_import_stores');
        $result = ham_import_existing_stores();
    }
    
    // Get existing stores with member_type
    $stores = ham_get_stores_with_membership();
    $imported = ham_get_imported_store_ids();
    
    ?>
    <div class="wrap">
        <h1>Import Existing Stores</h1>
        
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>About This Tool</h2>
            <p>This tool scans your existing hearing-aid-store posts and imports any that have "Preferred Provider" or "Verified Provider" set in the member_type field.</p>
            <p>It will create FREE membership records for them so they appear in the membership dashboard.</p>
            <p><strong>Note:</strong> This is a one-time import. After import, you can manually upgrade stores to paid memberships via Stripe.</p>
        </div>
        
        <?php if (isset($result)): ?>
            <div class="notice notice-<?php echo $result['success'] ? 'success' : 'error'; ?>">
                <p><strong><?php echo esc_html($result['message']); ?></strong></p>
                <?php if (!empty($result['details'])): ?>
                    <ul>
                        <?php foreach ($result['details'] as $detail): ?>
                            <li><?php echo esc_html($detail); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Existing Stores with Member Type</h2>
            
            <?php if (empty($stores)): ?>
                <p>No stores found with Preferred Provider or Verified Provider status.</p>
            <?php else: ?>
                <p>Found <strong><?php echo count($stores); ?></strong> store(s) with membership status.</p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Store Name</th>
                            <th>Author</th>
                            <th>Member Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stores as $store): ?>
                            <tr>
                                <td>
                                    <strong><a href="<?php echo get_edit_post_link($store->ID); ?>" target="_blank"><?php echo esc_html($store->post_title); ?></a></strong>
                                </td>
                                <td><?php echo get_the_author_meta('display_name', $store->post_author); ?></td>
                                <td>
                                    <?php 
                                    $member_type = get_field('member_type', $store->ID);
                                    $color = $member_type === 'Preferred Provider' ? '#2c5f5d' : '#00a32a';
                                    ?>
                                    <span style="background: <?php echo $color; ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px;">
                                        <?php echo esc_html($member_type); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (in_array($store->ID, $imported)): ?>
                                        <span style="color: #00a32a;">✓ Imported</span>
                                    <?php else: ?>
                                        <span style="color: #999;">Not imported</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('ham_import_stores'); ?>
                    <button type="submit" name="ham_import_stores" class="button button-primary button-large">
                        Import All Stores to Membership System
                    </button>
                    <p class="description">This will create FREE membership records for all stores with Preferred/Verified status.</p>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>After Import</h2>
            <ol>
                <li>Go to <a href="<?php echo admin_url('admin.php?page=ha-membership'); ?>">HA Membership → All Memberships</a> to see imported stores</li>
                <li>Edit any membership to set pricing, billing cycles, or Stripe subscription IDs</li>
                <li>Member types will stay synced with ACF field automatically</li>
            </ol>
        </div>
    </div>
    <?php
}

// Get stores with membership status
function ham_get_stores_with_membership() {
    $args = array(
        'post_type' => 'hearing-aid-store',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => 'member_type',
                'value' => array('Preferred Provider', 'Verified Provider'),
                'compare' => 'IN'
            )
        )
    );
    
    return get_posts($args);
}

// Get already imported store IDs
function ham_get_imported_store_ids() {
    global $wpdb;
    $table = $wpdb->prefix . 'ham_memberships';
    
    $results = $wpdb->get_col("SELECT store_id FROM $table");
    return array_map('intval', $results);
}

// Import existing stores
function ham_import_existing_stores() {
    global $wpdb;
    
    $stores = ham_get_stores_with_membership();
    $imported = ham_get_imported_store_ids();
    
    $created = 0;
    $skipped = 0;
    $details = array();
    
    foreach ($stores as $store) {
        // Skip if already imported
        if (in_array($store->ID, $imported)) {
            $skipped++;
            continue;
        }
        
        $member_type_display = get_field('member_type', $store->ID);
        
        // Map to internal type
        $member_type = 'unverified';
        if ($member_type_display === 'Preferred Provider') {
            $member_type = 'preferred';
        } elseif ($member_type_display === 'Verified Provider') {
            $member_type = 'verified';
        }
        
        // Create membership record
        $data = array(
            'user_id' => $store->post_author,
            'store_id' => $store->ID,
            'membership_type' => $member_type,
            'billing_cycle' => 'monthly',
            'price' => 0.00, // Free - admin can update later
            'status' => 'active',
            'start_date' => current_time('mysql'),
            'end_date' => NULL,
            'auto_renew' => 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ham_memberships',
            $data,
            array('%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result) {
            $created++;
            $details[] = "✓ Imported: {$store->post_title} ({$member_type_display})";
        }
    }
    
    return array(
        'success' => true,
        'message' => "Import complete! Created {$created} membership(s), skipped {$skipped} existing.",
        'details' => $details
    );
}

// Sync member_type when ACF field is updated
add_action('acf/save_post', 'ham_sync_member_type_on_save', 20);
function ham_sync_member_type_on_save($post_id) {
    // Only for hearing-aid-store posts
    if (get_post_type($post_id) !== 'hearing-aid-store') {
        return;
    }
    
    // Get member_type from ACF
    $member_type_display = get_field('member_type', $post_id);
    
    if (!$member_type_display) {
        return;
    }
    
    // Map to internal type
    $member_type = 'unverified';
    if ($member_type_display === 'Preferred Provider') {
        $member_type = 'preferred';
    } elseif ($member_type_display === 'Verified Provider') {
        $member_type = 'verified';
    }
    
    // Update membership record if exists
    global $wpdb;
    $table = $wpdb->prefix . 'ham_memberships';
    
    $membership = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table WHERE store_id = %d AND status = 'active' LIMIT 1",
        $post_id
    ));
    
    if ($membership) {
        $wpdb->update(
            $table,
            array('membership_type' => $member_type),
            array('id' => $membership->id),
            array('%s'),
            array('%d')
        );
    }
}
