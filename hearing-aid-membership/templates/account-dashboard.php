<?php
/**
 * Multi-Location & Audiologist Management Dashboard
 */

if (!defined('ABSPATH')) exit;

// Must be logged in
if (!is_user_logged_in()) {
    echo '<p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to manage your locations.</p>';
    return;
}

$user_id = get_current_user_id();

// Check if editing a specific store or audiologist
if (isset($_GET['edit_store'])) {
    $edit_store_id = intval($_GET['edit_store']);
    $edit_store = get_post($edit_store_id);
    
    // Verify ownership
    if ($edit_store && $edit_store->post_author == $user_id && $edit_store->post_type === 'hearing-aid-store') {
        // Load store editor
        $_GET['store_id'] = $edit_store_id;
        include HAM_PLUGIN_DIR . 'templates/store-editor.php';
        return;
    }
}

if (isset($_GET['edit_audiologist'])) {
    $edit_audio_id = intval($_GET['edit_audiologist']);
    $edit_audio = get_post($edit_audio_id);
    
    // Verify ownership
    if ($edit_audio && $edit_audio->post_author == $user_id && $edit_audio->post_type === 'audiologist') {
        // Load audiologist editor
        include HAM_PLUGIN_DIR . 'templates/audiologist-editor.php';
        return;
    }
}

// Get user's membership
global $wpdb;
$membership = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ham_memberships WHERE user_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
    $user_id
));

// Get location limit based on membership
if ($membership) {
    switch ($membership->membership_type) {
        case 'verified':
            $location_limit = 3;
            break;
        case 'preferred':
            $location_limit = 10;
            break;
        default:
            $location_limit = 1; // Free/unverified
    }
} else {
    $location_limit = 1; // No membership = free
}
$member_type = $membership ? $membership->membership_type : 'unverified';

// Get all user's stores
$user_stores = get_posts(array(
    'post_type' => 'hearing-aid-store',
    'author' => $user_id,
    'posts_per_page' => -1,
    'post_status' => array('publish', 'draft', 'pending'),
    'orderby' => 'title',
    'order' => 'ASC'
));

// Get all user's audiologists
$user_audiologists = get_posts(array(
    'post_type' => 'audiologist',
    'author' => $user_id,
    'posts_per_page' => -1,
    'post_status' => array('publish', 'draft', 'pending'),
    'orderby' => 'title',
    'order' => 'ASC'
));

$store_count = count($user_stores);
$audiologist_count = count($user_audiologists);
$can_add_location = $store_count < $location_limit;

?>

<div class="ham-account-dashboard">
    
    <!-- Header -->
    <div class="ham-dashboard-header">
        <div>
            <h1 class="ham-dashboard-header__title">My Account Dashboard</h1>
            <p class="ham-dashboard-header__membership">
                Membership: <strong class="ham-membership-type ham-membership-type--<?php echo esc_attr($member_type); ?>">
                    <?php echo ucfirst($member_type); ?>
                </strong>
            </p>
        </div>
        <div>
            <?php if ($membership && $membership->status === 'active'): ?>
                <span class="ham-badge ham-badge--active">
                    ✓ Active
                </span>
            <?php else: ?>
                <a href="<?php echo home_url('/pricing/'); ?>" class="button button-primary">Upgrade Membership</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Success Message -->
    <div id="success-message" class="ham-success-message"></div>
    
    <!-- Stats Grid -->
    <div class="ham-stats-grid">
        
        <div class="ham-stat-card ham-stat-card--blue">
            <div class="ham-stat-card__number ham-stat-card__number--blue"><?php echo $store_count; ?></div>
            <div class="ham-stat-card__label">Store Location<?php echo $store_count !== 1 ? 's' : ''; ?></div>
            <div class="ham-stat-card__sublabel">Limit: <?php echo $location_limit; ?></div>
        </div>
        
        <div class="ham-stat-card ham-stat-card--green">
            <div class="ham-stat-card__number ham-stat-card__number--green"><?php echo $audiologist_count; ?></div>
            <div class="ham-stat-card__label">Audiologist<?php echo $audiologist_count !== 1 ? 's' : ''; ?></div>
            <div class="ham-stat-card__sublabel">Unlimited</div>
        </div>
        
        <?php if ($membership): ?>
        <div class="ham-stat-card ham-stat-card--purple">
            <div class="ham-stat-card__number ham-stat-card__number--purple ham-stat-card__number--small">$<?php echo number_format($membership->price, 0); ?></div>
            <div class="ham-stat-card__label"><?php echo ucfirst($membership->billing_cycle); ?> Plan</div>
            <div class="ham-stat-card__sublabel">
                Next: <?php echo date('M j, Y', strtotime($membership->next_billing_date)); ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Store Locations Section -->
    <div class="ham-account-dashboard-location ham-section-card">
        
        <div class="ham-section-header">
            <h2 class="ham-section-header__title">My Store Locations</h2>
            <?php if ($can_add_location): ?>
                <button onclick="showAddLocationForm()" class="button button-primary">
                    + Add New Location
                </button>
            <?php else: ?>
                <div class="ham-limit-reached">
                    <p class="ham-limit-reached__text">
                        Location limit reached (<?php echo $location_limit; ?>)
                    </p>
                    <a href="<?php echo home_url('/pricing/'); ?>" class="button">Upgrade for More</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Add Location Form (Hidden) -->
        <div id="add-location-form" class="ham-add-form">
            <h3 class="ham-add-form__title">Add New Location</h3>
            <form id="new-location-form">
                <div class="ham-form-field">
                    <label class="ham-form-field__label">Location Name *</label>
                    <input type="text" name="location_name" required placeholder="e.g., Downtown Office"
                           class="ham-form-field__input">
                </div>
                <div class="ham-form-field">
                    <label class="ham-form-field__label">Street Address</label>
                    <input type="text" name="location_address" placeholder="123 Main St"
                           class="ham-form-field__input">
                </div>
                <div class="ham-form-grid">
                    <div>
                        <label class="ham-form-field__label">Phone</label>
                        <input type="tel" name="location_phone" placeholder="(555) 123-4567"
                               class="ham-form-field__input">
                    </div>
                    <div>
                        <label class="ham-form-field__label">Email</label>
                        <input type="email" name="location_email" placeholder="contact@example.com"
                               class="ham-form-field__input">
                    </div>
                </div>
                <div class="ham-form-actions">
                    <button type="submit" class="button button-primary">Create Location</button>
                    <button type="button" onclick="hideAddLocationForm()" class="button">Cancel</button>
                </div>
            </form>
        </div>
        
        <!-- Locations List -->
        <?php if (empty($user_stores)): ?>
            <div class="ham-empty-state">
                <p class="ham-empty-state__icon">📍</p>
                <p class="ham-empty-state__text">You don't have any store locations yet.</p>
                <?php if ($can_add_location): ?>
                    <button onclick="showAddLocationForm()" class="button button-primary">Add Your First Location</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="ham-list">
                <?php foreach ($user_stores as $store): 
                    $status = $store->post_status;
                    $status_class = $status === 'publish' ? 'ham-status--live' : ($status === 'pending' ? 'ham-status--pending' : 'ham-status--draft');
                    $status_text = $status === 'publish' ? 'Live' : ($status === 'pending' ? 'Pending Approval' : 'Draft');
                    
                    $store_phone = get_field('store_phone_number', $store->ID);
                    $store_address = get_field('store_address', $store->ID);
                    $member_type_field = get_field('member_type', $store->ID);
                ?>
                    <div class="ham-list-item <?php echo $status_class; ?>">
                        <div class="ham-list-item__row">
                            <div class="ham-list-item__content">
                                <h3 class="ham-list-item__title">
                                    <?php echo esc_html($store->post_title); ?>
                                    <span class="ham-status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </h3>
                                
                                <div class="ham-list-item__details">
                                    <?php if ($store_address): ?>
                                        <div>📍 <?php echo esc_html($store_address); ?></div>
                                    <?php endif; ?>
                                    <?php if ($store_phone): ?>
                                        <div>📞 <?php echo esc_html($store_phone); ?></div>
                                    <?php endif; ?>
                                    <?php if ($member_type_field): ?>
                                        <div class="ham-list-item__member-type">
                                            <span class="ham-member-badge">
                                                <?php echo esc_html($member_type_field); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="ham-list-item__actions">
                                <a href="?edit_store=<?php echo $store->ID; ?>" class="button">Edit</a>
                                <?php if ($status === 'publish'): ?>
                                    <a href="<?php echo get_permalink($store->ID); ?>" class="button" target="_blank">View</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Audiologists Section -->
    <div class="ham-account-dashboard-audiologists ham-section-card">
        
        <div class="ham-section-header">
            <h2 class="ham-section-header__title">My Audiologists</h2>
            <button onclick="showAddAudiologistForm()" class="button button-primary">
                + Add Audiologist
            </button>
        </div>
        
        <!-- Add Audiologist Form (Hidden) -->
        <div id="add-audiologist-form" class="ham-add-form">
            <h3 class="ham-add-form__title">Add New Audiologist</h3>
            <form id="new-audiologist-form">
                <div class="ham-form-field">
                    <label class="ham-form-field__label">Audiologist Name *</label>
                    <input type="text" name="audiologist_name" required placeholder="Dr. Jane Smith"
                           class="ham-form-field__input">
                </div>
                <div class="ham-form-field">
                    <label class="ham-form-field__label">Credentials</label>
                    <input type="text" name="audiologist_credentials" placeholder="Au.D., CCC-A"
                           class="ham-form-field__input">
                </div>
                <div class="ham-form-field">
                    <label class="ham-form-field__label">Bio</label>
                    <textarea name="audiologist_bio" rows="4" placeholder="Brief professional biography..."
                              class="ham-form-field__input ham-form-field__textarea"></textarea>
                </div>
                <div class="ham-form-field">
                    <label class="ham-form-field__label">Link to Store</label>
                    <select name="linked_store" class="ham-form-field__input ham-form-field__select">
                        <option value="">-- Optional --</option>
                        <?php foreach ($user_stores as $store): ?>
                            <option value="<?php echo $store->ID; ?>"><?php echo esc_html($store->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ham-form-field__help">Which location does this audiologist work at?</p>
                </div>
                <div class="ham-form-actions">
                    <button type="submit" class="button button-primary">Add Audiologist</button>
                    <button type="button" onclick="hideAddAudiologistForm()" class="button">Cancel</button>
                </div>
            </form>
        </div>
        
        <!-- Audiologists List -->
        <?php if (empty($user_audiologists)): ?>
            <div class="ham-empty-state">
                <p class="ham-empty-state__icon">👨‍⚕️</p>
                <p class="ham-empty-state__text">You haven't added any audiologists yet.</p>
                <button onclick="showAddAudiologistForm()" class="button button-primary">Add Your First Audiologist</button>
            </div>
        <?php else: ?>
            <div class="ham-list">
                <?php foreach ($user_audiologists as $audiologist): 
                    $status = $audiologist->post_status;
                    $status_class = $status === 'publish' ? 'ham-status--live' : ($status === 'pending' ? 'ham-status--pending' : 'ham-status--draft');
                    $status_text = $status === 'publish' ? 'Live' : ($status === 'pending' ? 'Pending Approval' : 'Draft');
                    
                    // Get linked store (assuming there's a field for this)
                    $linked_store_id = get_post_meta($audiologist->ID, 'linked_store_id', true);
                    $linked_store_name = $linked_store_id ? get_the_title($linked_store_id) : '';
                ?>
                    <div class="ham-list-item <?php echo $status_class; ?>">
                        <div class="ham-list-item__row">
                            <div class="ham-list-item__content">
                                <h3 class="ham-list-item__title">
                                    <?php echo esc_html($audiologist->post_title); ?>
                                    <span class="ham-status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </h3>
                                
                                <div class="ham-list-item__details">
                                    <?php if ($linked_store_name): ?>
                                        <div>📍 Works at: <strong><?php echo esc_html($linked_store_name); ?></strong></div>
                                    <?php endif; ?>
                                    <?php if ($audiologist->post_excerpt): ?>
                                        <div class="ham-list-item__excerpt">
                                            <?php echo esc_html(wp_trim_words($audiologist->post_excerpt, 20)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="ham-list-item__actions">
                                <a href="?edit_audiologist=<?php echo $audiologist->ID; ?>" class="button">Edit</a>
                                <?php if ($status === 'publish'): ?>
                                    <a href="<?php echo get_permalink($audiologist->ID); ?>" class="button" target="_blank">View</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
    
</div>

<script>
function showAddLocationForm() {
    document.getElementById('add-location-form').style.display = 'block';
}

function hideAddLocationForm() {
    document.getElementById('add-location-form').style.display = 'none';
    document.getElementById('new-location-form').reset();
}

function showAddAudiologistForm() {
    document.getElementById('add-audiologist-form').style.display = 'block';
}

function hideAddAudiologistForm() {
    document.getElementById('add-audiologist-form').style.display = 'none';
    document.getElementById('new-audiologist-form').reset();
}

// Handle new location submission
document.getElementById('new-location-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'ham_create_location');
    formData.append('nonce', '<?php echo wp_create_nonce('ham_create_location'); ?>');
    
    try {
        const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload(); // Refresh page to show new location
        } else {
            alert('Error: ' + result.data.message);
        }
    } catch (error) {
        alert('Error creating location: ' + error.message);
    }
});

// Handle new audiologist submission
document.getElementById('new-audiologist-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'ham_create_audiologist');
    formData.append('nonce', '<?php echo wp_create_nonce('ham_create_audiologist'); ?>');
    
    try {
        const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload(); // Refresh page to show new audiologist
        } else {
            alert('Error: ' + result.data.message);
        }
    } catch (error) {
        alert('Error creating audiologist: ' + error.message);
    }
});
</script>

<style>
/* ==========================================================================
   ACCOUNT DASHBOARD - BASE STYLES
   ========================================================================== */

/* Main Container */
.ham-account-dashboard {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

/* Dashboard Header */
.ham-dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.ham-dashboard-header__title {
    margin: 0 0 5px 0;
}

.ham-dashboard-header__membership {
    margin: 0;
    color: #666;
}

/* Membership Type Colors */
.ham-membership-type {
    font-weight: bold;
}

.ham-membership-type--preferred {
    color: #2c5f5d;
}

.ham-membership-type--verified {
    color: #2271b1;
}

.ham-membership-type--unverified {
    color: #666;
}

/* Active Badge */
.ham-badge {
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
}

.ham-badge--active {
    background: #00a32a;
    color: white;
}

/* Success Message */
.ham-success-message {
    display: none;
    padding: 15px;
    margin-bottom: 20px;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    border-radius: 4px;
}

/* Stats Grid */
.ham-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

/* Stat Cards */
.ham-stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-left: 4px solid;
}

.ham-stat-card--blue {
    border-left-color: #2271b1;
}

.ham-stat-card--green {
    border-left-color: #00a32a;
}

.ham-stat-card--purple {
    border-left-color: #9b59b6;
}

.ham-stat-card__number {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 5px;
}

.ham-stat-card__number--blue {
    color: #2271b1;
}

.ham-stat-card__number--green {
    color: #00a32a;
}

.ham-stat-card__number--purple {
    color: #9b59b6;
}

.ham-stat-card__number--small {
    font-size: 24px;
}

.ham-stat-card__label {
    color: #666;
    font-size: 14px;
}

.ham-stat-card__sublabel {
    font-size: 12px;
    color: #999;
    margin-top: 5px;
}

/* Section Card */
.ham-section-card {
    border: 1px solid #E9ECEF;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

/* Section Header */
.ham-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.ham-section-header__title {
    margin: 0;
}

/* Limit Reached */
.ham-limit-reached {
    text-align: right;
}

.ham-limit-reached__text {
    margin: 0 0 10px 0;
    color: #d63638;
    font-size: 14px;
}

/* Add Form */
.ham-add-form {
    display: none;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    margin-bottom: 20px;
}

.ham-add-form__title {
    margin: 0 0 15px 0;
}

/* Form Fields */
.ham-form-field {
    margin-bottom: 15px;
}

.ham-form-field__label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}

.ham-form-field__input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
    box-sizing: border-box;
}

.ham-form-field__textarea {
    resize: vertical;
    min-height: 100px;
}

.ham-form-field__select {
    appearance: auto;
}

.ham-form-field__help {
    margin: 5px 0 0 0;
    font-size: 13px;
    color: #666;
}

/* Form Grid (2 columns) */
.ham-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

/* Form Actions */
.ham-form-actions {
    display: flex;
    gap: 10px;
}

/* Empty State */
.ham-empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
}

.ham-empty-state__icon {
    font-size: 48px;
    margin: 0;
}

.ham-empty-state__text {
    margin: 10px 0;
}

/* List */
.ham-list {
    display: grid;
    gap: 15px;
}

/* List Item */
.ham-list-item {
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    border-left: 4px solid;
}

.ham-list-item.ham-status--live {
    border-left-color: #00a32a;
}

.ham-list-item.ham-status--pending {
    border-left-color: #f0b429;
}

.ham-list-item.ham-status--draft {
    border-left-color: #666;
}

.ham-list-item__row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.ham-list-item__content {
    flex: 1;
}

.ham-list-item__title {
    margin: 0 0 10px 0;
}

.ham-list-item__details {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
}

.ham-list-item__member-type {
    margin-top: 5px;
}

.ham-list-item__excerpt {
    margin-top: 8px;
    color: #888;
    font-style: italic;
}

.ham-list-item__actions {
    display: flex;
    gap: 10px;
}

/* Status Badge */
.ham-status-badge {
    display: inline-block;
    padding: 3px 10px;
    color: white;
    font-size: 11px;
    border-radius: 12px;
    margin-left: 10px;
    line-height: 24px;
}

.ham-status-badge.ham-status--live {
    background: #00a32a;
}

.ham-status-badge.ham-status--pending {
    background: #f0b429;
}

.ham-status-badge.ham-status--draft {
    background: #666;
}

/* Member Badge */
.ham-member-badge {
    background: #e9ecef;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
}

/* ==========================================================================
   TABLET RESPONSIVE (max-width: 768px)
   ========================================================================== */
@media screen and (max-width: 768px) {
    /* Container */
    .ham-account-dashboard {
        margin: 20px auto;
        padding: 0 15px;
    }

    /* Dashboard Header - stack vertically */
    .ham-dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    /* Stats Grid */
    .ham-stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-bottom: 30px;
    }

    /* Section Card */
    .ham-section-card {
        padding: 20px;
    }

    /* Section Header - stack vertically */
    .ham-section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    /* Limit Reached */
    .ham-limit-reached {
        text-align: left;
    }

    /* Form Grid - stack on tablet */
    .ham-form-grid {
        grid-template-columns: 1fr;
    }

    /* List Item - stack vertically */
    .ham-list-item__row {
        flex-direction: column;
        gap: 15px;
    }

    .ham-list-item__actions {
        width: 100%;
    }

    .ham-list-item__actions .button {
        flex: 1;
        text-align: center;
    }

    /* Add Form */
    .ham-add-form {
        padding: 15px;
    }

    /* Form Actions - full width buttons */
    .ham-form-actions {
        flex-direction: column;
    }

    .ham-form-actions .button {
        width: 100%;
        text-align: center;
    }
}

/* ==========================================================================
   MOBILE RESPONSIVE (max-width: 480px)
   ========================================================================== */
@media screen and (max-width: 480px) {
    /* Container */
    .ham-account-dashboard {
        margin: 15px auto;
        padding: 0 10px;
    }

    /* Dashboard Header */
    .ham-dashboard-header__title {
        font-size: 24px;
    }

    /* Badge */
    .ham-badge {
        padding: 6px 12px;
        font-size: 13px;
    }

    /* Stats Grid - single column */
    .ham-stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 25px;
    }

    /* Stat Card */
    .ham-stat-card {
        padding: 15px;
    }

    .ham-stat-card__number {
        font-size: 28px;
    }

    .ham-stat-card__number--small {
        font-size: 22px;
    }

    /* Section Card */
    .ham-section-card {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 6px;
    }

    /* Section Header */
    .ham-section-header__title {
        font-size: 18px;
    }

    /* Empty State */
    .ham-empty-state {
        padding: 30px 15px;
    }

    .ham-empty-state__icon {
        font-size: 40px;
    }

    /* List Item */
    .ham-list-item {
        padding: 15px;
    }

    .ham-list-item__title {
        font-size: 16px;
        line-height: 1.4;
    }

    /* Status Badge - move to new line */
    .ham-status-badge {
        display: block;
        margin-left: 0;
        margin-top: 8px;
        width: fit-content;
    }

    .ham-list-item__details {
        font-size: 13px;
    }

    /* Form Fields */
    .ham-form-field__input {
        padding: 12px 10px;
        font-size: 16px; /* Prevents zoom on iOS */
    }

    /* Add Form */
    .ham-add-form {
        padding: 12px;
        margin-bottom: 15px;
    }

    .ham-add-form__title {
        font-size: 16px;
    }

    /* Success Message */
    .ham-success-message {
        padding: 12px;
        font-size: 14px;
    }
}
</style>