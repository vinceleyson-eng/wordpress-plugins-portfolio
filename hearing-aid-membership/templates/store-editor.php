<?php
/**
 * Frontend Store Editor Dashboard
 * Allows users to edit their store information
 */

if (!defined('ABSPATH')) exit;

// Must be logged in
if (!is_user_logged_in()) {
    echo '<p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to edit your store.</p>';
    return;
}

$user_id = get_current_user_id();

// Get store ID from URL parameter or find user's first store
$store_id = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;

if (!$store_id) {
    // Get user's store
    $user_stores = get_posts(array(
        'post_type' => 'hearing-aid-store',
        'author' => $user_id,
        'posts_per_page' => 1,
        'post_status' => 'publish'
    ));

    if (empty($user_stores)) {
        echo '<div class="ham-message ham-message--warning">';
        echo '<h2>No Store Found</h2>';
        echo '<p>You don\'t have a store listing yet. Please contact the administrator to create one for you.</p>';
        echo '</div>';
        return;
    }

    $store = $user_stores[0];
    $store_id = $store->ID;
} else {
    $store = get_post($store_id);
    
    // Verify ownership
    if (!$store || $store->post_author != $user_id || $store->post_type !== 'hearing-aid-store') {
        echo '<div class="ham-message ham-message--error">';
        echo '<h2>Access Denied</h2>';
        echo '<p>You do not have permission to edit this store.</p>';
        echo '</div>';
        return;
    }
}

// Check if we're in dashboard mode (coming from account dashboard)
$from_dashboard = isset($_GET['edit_store']) || isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'ham_account') !== false;
$back_url = $from_dashboard ? add_query_arg(array(), remove_query_arg(array('edit_store', 'store_id'))) : home_url('/');

// Get all ACF fields
$store_name = get_field('store_name', $store_id);
$store_address = get_field('store_address', $store_id);
$store_address_google = get_field('store_address_on_google', $store_id);
$store_phone = get_field('store_phone_number', $store_id);
$store_website = get_field('store_website', $store_id);
$store_email = get_field('store_email', $store_id);
$video_1 = get_field('video_1', $store_id);
$video_2 = get_field('video_2', $store_id);

// Hours
$hours = array(
    'sunday' => get_field('store_hours_sunday', $store_id),
    'monday' => get_field('store_hours_monday', $store_id),
    'tuesday' => get_field('store_hours_tuesday', $store_id),
    'wednesday' => get_field('store_hours_wednesday', $store_id),
    'thursday' => get_field('store_hours_thursday', $store_id),
    'friday' => get_field('store_hours_friday', $store_id),
    'saturday' => get_field('store_hours_saturday', $store_id),
);
$special_hours = get_field('special_hours', $store_id);
$special_notes = get_field('special_notes', $store_id);

// Services
$featured_services = get_field('featured_services', $store_id);
$supported_brands = get_field('supported_hearing_aid_brands', $store_id);
$services_provided = get_field('services_provided', $store_id);
$hearing_aid_services = get_field('hearing_aid_services', $store_id);
$custom_ear_services = get_field('custom_ear_mold_services', $store_id);

// Get field choices for checkboxes
$featured_choices = array('Redux', 'Earigator', 'Real Ear Measurement');
$brand_choices = array('Lyric Hearing Aids', 'Oticon Hearing Aids', 'Phonak Hearing Aids', 'ReSound Hearing Aids', 'Signia Hearing Aids', 'Siemens Hearing Aids', 'Sonic Hearing Aids', 'Starkey Hearing Aids', 'Unitron Hearing Aids', 'Widex Hearing Aids');
$services_choices = array('Hearing Tests', 'Hearing Evaluations', 'Military and Veteran Exams', 'Industrial Hearing Screenings', 'Aural Rehabilitation', 'Ear Wax Removal', 'Central Auditory Processing Disorder Treatment', 'Tinnitus Treatment', 'Lenire Tinnitus Treatment', 'Vertigo Treatment', 'Meniere\'s Disease Treatment', 'Pediatric Audiology', 'Cognitive Screening', 'Custom Ear Molds');
$ha_services_choices = array('Hearing Aid Selection', 'Hearing Aid Fitting', 'Hearing Aid Repair', 'Hearing Aid Batteries', 'Assistive Listening Devices', 'Leasing Hearing Instruments', 'Bone Anchored Devices', 'Cochlear Implants', 'Real-ear measurement', 'Custom Ear Molds');
$ear_mold_choices = array('Custom Hearing Protection', 'Custom Earplugs for Swimming', 'Custom Musician Earplugs', 'Custom Musician In-Ear Monitors');

?>

<div class="ham-store-editor">
    
    <div class="ham-header">
        <div>
            <h1 class="ham-header__title">Edit My Store</h1>
            <?php if ($from_dashboard): ?>
                <p class="ham-header__back">
                    <a href="<?php echo esc_url($back_url); ?>" class="ham-back-link">
                        ← Back to Dashboard
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <a href="<?php echo get_permalink($store_id); ?>" class="button" target="_blank">View Store Page</a>
    </div>
    
    <div id="save-message" class="ham-save-message"></div>
    
    <form id="store-edit-form" class="ham-form">
        
        <!-- Basic Information -->
        <div class="ham-form-section">
            <h2 class="ham-form-section__title">Basic Information</h2>
            
            <div class="ham-form-row">
                <label class="ham-label">Store Title *</label>
                <input type="text" name="post_title" value="<?php echo esc_attr($store->post_title); ?>" required
                       class="ham-input ham-input--large">
                <p class="ham-help-text">This is your main business name</p>
            </div>
            
            <div class="ham-form-row">
                <label class="ham-label">Store Description</label>
                <textarea name="post_content" rows="5" class="ham-input ham-input--large"><?php echo esc_textarea($store->post_content); ?></textarea>
                <p class="ham-help-text">Tell customers about your store</p>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="ham-form-section">
            <h2 class="ham-form-section__title">Contact Information</h2>
            
            <div class="ham-form-row">
                <label class="ham-label">Store Address</label>
                <input type="text" name="store_address" value="<?php echo esc_attr($store_address); ?>"
                       class="ham-input">
                <p class="ham-help-text">Street address (without store name)</p>
            </div>
            
            <div class="ham-form-row">
                <label class="ham-label">Phone Number</label>
                <input type="tel" name="store_phone_number" value="<?php echo esc_attr($store_phone); ?>" placeholder="(333) 444-5555"
                       class="ham-input">
            </div>
            
            <div class="ham-form-row">
                <label class="ham-label">Website</label>
                <input type="url" name="store_website" value="<?php echo esc_attr($store_website); ?>" placeholder="https://yourwebsite.com"
                       class="ham-input">
            </div>
            
            <div class="ham-form-row">
                <label class="ham-label">Email</label>
                <input type="email" name="store_email" value="<?php echo esc_attr($store_email); ?>"
                       class="ham-input">
            </div>
        </div>
        
        <!-- Store Hours -->
        <div class="ham-form-section">
            <h2 class="ham-form-section__title">Store Hours</h2>
            <p class="ham-section-description">Format: 9:00am - 5:00pm or "Closed"</p>
            
            <?php
            $days = array(
                'sunday' => 'Sunday',
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday'
            );
            
            foreach ($days as $key => $label):
            ?>
                <div class="ham-hours-row">
                    <label class="ham-hours-label"><?php echo $label; ?>:</label>
                    <input type="text" name="store_hours_<?php echo $key; ?>" value="<?php echo esc_attr($hours[$key]); ?>" placeholder="9:00am - 5:00pm"
                           class="ham-input--small">
                </div>
            <?php endforeach; ?>
            
            <div class="ham-form-row ham-form-row--mt">
                <label class="ham-label">Special Hours</label>
                <input type="text" name="special_hours" value="<?php echo esc_attr($special_hours); ?>" placeholder="e.g., Closed on Memorial Day"
                       class="ham-input">
            </div>
            
            <div class="ham-form-row ham-form-row--mt-sm">
                <label class="ham-label">Special Notes</label>
                <input type="text" name="special_notes" value="<?php echo esc_attr($special_notes); ?>"
                       class="ham-input">
            </div>
        </div>
        
        <!-- Videos -->
        <div class="ham-form-section">
            <h2 class="ham-form-section__title">Videos</h2>
            
            <div class="ham-form-row">
                <label class="ham-label">Video 1 URL</label>
                <input type="url" name="video_1" value="<?php echo esc_attr($video_1); ?>" placeholder="https://youtube.com/watch?v=..."
                       class="ham-input">
                <p class="ham-help-text">YouTube, Vimeo, or other video URL</p>
            </div>
            
            <div class="ham-form-row">
                <label class="ham-label">Video 2 URL</label>
                <input type="url" name="video_2" value="<?php echo esc_attr($video_2); ?>" placeholder="https://youtube.com/watch?v=..."
                       class="ham-input">
            </div>
        </div>
        
        <!-- Featured Services -->
        <div class="ham-form-section">
            <h2 class="ham-form-section__title">Featured Services</h2>
            <p class="ham-section-description">Select up to 3 featured services</p>
            
            <?php foreach ($featured_choices as $choice): ?>
                <label class="ham-checkbox-label">
                    <input type="checkbox" name="featured_services[]" value="<?php echo esc_attr($choice); ?>" 
                           <?php echo is_array($featured_services) && in_array($choice, $featured_services) ? 'checked' : ''; ?>
                           class="ham-checkbox">
                    <?php echo esc_html($choice); ?>
                </label>
            <?php endforeach; ?>
        </div>
        
        <!-- Hearing Aid Brands -->
        <div class="ham-form-section">
            <h2 class="ham-form-section__title">Supported Hearing Aid Brands</h2>
            
            <div class = "grid-22">
                <?php foreach ($brand_choices as $choice): ?>
                    <label class="ham-checkbox-label ham-checkbox-label--grid">
                        <input type="checkbox" name="supported_hearing_aid_brands[]" value="<?php echo esc_attr($choice); ?>" 
                               <?php echo is_array($supported_brands) && in_array($choice, $supported_brands) ? 'checked' : ''; ?>
                               class="ham-checkbox ham-checkbox--grid">
                        <?php echo esc_html($choice); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Services Provided -->
        <div class="ham-form-section">
            <h2 class="ham-form-section__title">Services Provided</h2>
            
            <div class = "grid-22">
                <?php foreach ($services_choices as $choice): ?>
                    <label class="ham-checkbox-label ham-checkbox-label--grid">
                        <input type="checkbox" name="services_provided[]" value="<?php echo esc_attr($choice); ?>" 
                               <?php echo is_array($services_provided) && in_array($choice, $services_provided) ? 'checked' : ''; ?>
                               class="ham-checkbox ham-checkbox--grid">
                        <?php echo esc_html($choice); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Hearing Aid Services -->
        <div class="ham-form-section">
            <h2 class="ham-form-section__title">Hearing Aid Services</h2>
            
            <div class = "grid-22">
                <?php foreach ($ha_services_choices as $choice): ?>
                    <label class="ham-checkbox-label ham-checkbox-label--grid">
                        <input type="checkbox" name="hearing_aid_services[]" value="<?php echo esc_attr($choice); ?>" 
                               <?php echo is_array($hearing_aid_services) && in_array($choice, $hearing_aid_services) ? 'checked' : ''; ?>
                               class="ham-checkbox ham-checkbox--grid">
                        <?php echo esc_html($choice); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Custom Ear Mold Services -->
        <div class="ham-form-section ham-form-section--last">
            <h2 class="ham-form-section__title">Custom Ear Mold Services</h2>
            
            <?php foreach ($ear_mold_choices as $choice): ?>
                <label class="ham-checkbox-label">
                    <input type="checkbox" name="custom_ear_mold_services[]" value="<?php echo esc_attr($choice); ?>" 
                           <?php echo is_array($custom_ear_services) && in_array($choice, $custom_ear_services) ? 'checked' : ''; ?>
                           class="ham-checkbox">
                    <?php echo esc_html($choice); ?>
                </label>
            <?php endforeach; ?>
        </div>
        
        <!-- Audiologists at This Location -->
        <div class="ham-form-section">
            <h2 class="ham-form-section__title">Audiologists at This Location</h2>
            <p class="ham-section-description">Add your audiologists to this location or remove those currently assigned</p>
            
            <?php
            // Get all user's audiologists (that they manage)
            $user_audiologists = get_posts(array(
                'post_type' => 'audiologist',
                'author' => $user_id,
                'posts_per_page' => -1,
                'post_status' => 'any',
                'orderby' => 'title',
                'order' => 'ASC'
            ));

            // Get audiologists linked via linked_store_id meta (frontend method)
            $meta_linked_audiologists = get_posts(array(
                'post_type' => 'audiologist',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'meta_query' => array(
                    array(
                        'key' => 'linked_store_id',
                        'value' => $store_id,
                        'compare' => '=',
                        'type' => 'NUMERIC'
                    )
                )
            ));

            // Get audiologists linked via ACF relationship field (backend method)
            $acf_linked_raw = get_field('associated_audiologist', $store_id);
            $acf_linked_audiologists = array();
            if (!empty($acf_linked_raw) && is_array($acf_linked_raw)) {
                // ACF can return post objects or IDs depending on field settings
                $first_item = reset($acf_linked_raw);
                if (is_object($first_item)) {
                    // Already post objects
                    $acf_linked_audiologists = $acf_linked_raw;
                } else {
                    // IDs - need to fetch posts
                    $acf_linked_audiologists = get_posts(array(
                        'post_type' => 'audiologist',
                        'posts_per_page' => -1,
                        'post_status' => 'any',
                        'post__in' => array_map('intval', $acf_linked_raw),
                        'orderby' => 'post__in'
                    ));
                }
            }

            // Merge both sources (remove duplicates by ID)
            $linked_ids = array();
            $linked_audiologists = array();

            foreach ($meta_linked_audiologists as $audio) {
                if (!in_array($audio->ID, $linked_ids)) {
                    $linked_ids[] = $audio->ID;
                    $linked_audiologists[] = $audio;
                }
            }

            foreach ($acf_linked_audiologists as $audio) {
                if (!in_array($audio->ID, $linked_ids)) {
                    $linked_ids[] = $audio->ID;
                    $linked_audiologists[] = $audio;
                }
            }

            $user_owned_ids = array_map(function($audio) { return $audio->ID; }, $user_audiologists);
            ?>
            
            <!-- Currently Assigned Audiologists -->
            <?php if (!empty($linked_audiologists)): ?>
                <div class="ham-audiologists-section">
                    <h3 class="ham-audiologists-section__title">Currently Assigned</h3>
                    <div id="assigned-audiologists-list" class="ham-audiologists-list">
                        <?php foreach ($linked_audiologists as $audiologist): 
                            $is_owned = in_array($audiologist->ID, $user_owned_ids);
                            $owner = get_userdata($audiologist->post_author);
                            $status = get_post_status($audiologist->ID);
                            $status_class = $status === 'publish' ? 'ham-status--active' : 'ham-status--pending';
                            $status_text = $status === 'publish' ? 'Active' : 'Pending';
                        ?>
                            <div class="ham-audiologist-item ham-audiologist-item--assigned" data-audio-id="<?php echo $audiologist->ID; ?>">
                                <div class="ham-audiologist-item__content">
                                    <div class="ham-audiologist-item__name">
                                        ✓ <?php echo esc_html($audiologist->post_title); ?>
                                        <span class="ham-status <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                    <?php if ($audiologist->post_excerpt): ?>
                                        <div class="ham-audiologist-item__excerpt"><?php echo esc_html($audiologist->post_excerpt); ?></div>
                                    <?php endif; ?>
                                    <?php if (!$is_owned): ?>
                                        <div class="ham-audiologist-item__admin-note">
                                            Added by admin - Managed by <?php echo esc_html($owner->display_name); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="ham-audiologist-item__actions">
                                    <button type="button" class="button remove-audiologist-btn ham-btn--remove" data-audio-id="<?php echo $audiologist->ID; ?>">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Your Audiologists (Available to Add) -->
            <?php if (empty($user_audiologists)): ?>
                <div class="ham-empty-state">
                    <p class="ham-empty-state__text">You haven't added any audiologists yet.</p>
                    <p class="ham-empty-state__action">
                        <a href="<?php echo home_url('/my-account/'); ?>" class="button">Go to Dashboard to Add Audiologists</a>
                    </p>
                </div>
            <?php else: ?>
                <div>
                    <h3 class="ham-audiologists-section__title">Your Audiologists (Add to Location)</h3>
                    <div id="audiologists-list" class="ham-audiologists-list">
                    <?php 
                    $available_count = 0;
                    foreach ($user_audiologists as $audiologist): 
                        $is_linked = in_array($audiologist->ID, $linked_ids);
                        
                        // Skip if already linked (shown in "Currently Assigned" section above)
                        if ($is_linked) continue;
                        
                        $available_count++;
                        $status = get_post_status($audiologist->ID);
                        $status_class = $status === 'publish' ? 'ham-status--active' : 'ham-status--pending';
                        $status_text = $status === 'publish' ? 'Active' : 'Pending';
                    ?>
                        <div class="ham-audiologist-item ham-audiologist-item--available" data-audio-id="<?php echo $audiologist->ID; ?>">
                            <div class="ham-audiologist-item__content">
                                <div class="ham-audiologist-item__name">
                                    <?php echo esc_html($audiologist->post_title); ?>
                                    <span class="ham-status <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </div>
                                <?php if ($audiologist->post_excerpt): ?>
                                    <div class="ham-audiologist-item__excerpt"><?php echo esc_html($audiologist->post_excerpt); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="ham-audiologist-item__actions">
                                <button type="button" class="button button-primary add-audiologist-btn" data-audio-id="<?php echo $audiologist->ID; ?>">
                                    + Add to Location
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($available_count === 0): ?>
                        <div class="ham-all-assigned">
                            <p class="ham-all-assigned__text">All your audiologists are already assigned to this location. ✓</p>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Save Button -->
        <div class="ham-save-area">
            <button type="submit" id="save-btn" class="button button-primary button-large ham-save-btn">
                Save Changes
            </button>
            <div id="saving-indicator" class="ham-saving-indicator">
                <span class="ham-saving-indicator__text">💾 Saving...</span>
            </div>
        </div>
        
        <input type="hidden" name="store_id" value="<?php echo $store_id; ?>">
        <input type="hidden" name="action" value="ham_save_store">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ham_save_store_' . $store_id); ?>">
        
    </form>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('store-edit-form');
    const saveBtn = document.getElementById('save-btn');
    const savingIndicator = document.getElementById('saving-indicator');
    const saveMessage = document.getElementById('save-message');
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Disable button
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
        savingIndicator.style.display = 'block';
        saveMessage.style.display = 'none';
        
        try {
            // Get form data
            const formData = new FormData(form);
            
            // Send to server
            const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                saveMessage.style.display = 'block';
                saveMessage.className = 'ham-save-message ham-save-message--success';
                saveMessage.innerHTML = '<strong>✓ Success!</strong> Your store information has been updated.';
                
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                throw new Error(result.data.message || 'Save failed');
            }
            
        } catch (error) {
            saveMessage.style.display = 'block';
            saveMessage.className = 'ham-save-message ham-save-message--error';
            saveMessage.innerHTML = '<strong>✗ Error:</strong> ' + error.message;
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Re-enable button
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Changes';
        savingIndicator.style.display = 'none';
    });
    
    // Handle Add/Remove Audiologist
    document.querySelectorAll('.add-audiologist-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const audioId = this.getAttribute('data-audio-id');
            const storeId = <?php echo $store_id; ?>;
            
            if (!confirm('Add this audiologist to this location?')) {
                return;
            }
            
            this.disabled = true;
            this.textContent = 'Adding...';
            
            try {
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'ham_link_audiologist_to_store',
                        audiologist_id: audioId,
                        store_id: storeId,
                        link: '1',
                        nonce: '<?php echo wp_create_nonce('ham_link_audiologist'); ?>'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload(); // Reload to show updated list
                } else {
                    alert('Error: ' + (result.data?.message || 'Failed to add audiologist'));
                    this.disabled = false;
                    this.textContent = 'Add to Location';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                this.disabled = false;
                this.textContent = 'Add to Location';
            }
        });
    });
    
    document.querySelectorAll('.remove-audiologist-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const audioId = this.getAttribute('data-audio-id');
            
            if (!confirm('Remove this audiologist from this location?')) {
                return;
            }
            
            this.disabled = true;
            this.textContent = 'Removing...';
            
            try {
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'ham_link_audiologist_to_store',
                        audiologist_id: audioId,
                        store_id: 0,
                        link: '0',
                        nonce: '<?php echo wp_create_nonce('ham_link_audiologist'); ?>'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload(); // Reload to show updated list
                } else {
                    alert('Error: ' + (result.data?.message || 'Failed to remove audiologist'));
                    this.disabled = false;
                    this.textContent = 'Remove';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                this.disabled = false;
                this.textContent = 'Remove';
            }
        });
    });
});
</script>

<style>
/* Store Editor Container */
.ham-store-editor {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

/* Messages */
.ham-message {
    max-width: 700px;
    margin: 40px auto;
    padding: 30px;
    border-radius: 8px;
    text-align: center;
    border-width: 2px;
    border-style: solid;
}

.ham-message--warning {
    background: #fff3cd;
    border-color: #ffc107;
}

.ham-message--error {
    background: #f8d7da;
    border-color: #d63638;
}

/* Header */
.ham-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.ham-header__title {
    margin: 0;
}

.ham-header__back {
    margin: 5px 0 0 0;
}

.ham-back-link {
    color: #2271b1;
    text-decoration: none;
}

/* Save Message */
.ham-save-message {
    display: none;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.ham-save-message--success {
    background: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.ham-save-message--error {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

/* Form */
.ham-form {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Form Sections */
.ham-form-section {
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 2px solid #f0f0f0;
}

.ham-form-section--last {
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: none;
}

.ham-form-section__title {
    margin: 0 0 20px 0;
    color: #2271b1;
}

/* Form Rows */
.ham-form-row {
    margin-bottom: 20px;
}

.ham-form-row--mt {
    margin-top: 20px;
}

.ham-form-row--mt-sm {
    margin-top: 15px;
}

/* Labels */
.ham-label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}

/* Inputs */
.ham-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ham-input--large {
    font-size: 16px;
}

.ham-input--small {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

/* Help Text */
.ham-help-text {
    margin: 5px 0 0 0;
    font-size: 13px;
    color: #666;
}

/* Section Description */
.ham-section-description {
    margin: 0 0 15px 0;
    color: #666;
    font-size: 14px;
}

/* Hours Row */
.ham-hours-row {
    margin-bottom: 15px;
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 15px;
    align-items: center;
}

.ham-hours-label {
    font-weight: bold;
}

/* Checkbox Labels */
.ham-checkbox-label {
    display: block;
    padding: 10px;
    margin-bottom: 8px;
    background: #f9f9f9;
    border-radius: 4px;
    cursor: pointer;
}

.ham-checkbox-label--grid {
    margin-bottom: 0;
}

.ham-checkbox-label:hover {
    background: #e9ecef !important;
}

.ham-checkbox {
    margin-right: 10px;
}

.ham-checkbox--grid {
    margin-right: 8px;
}

.ham-checkbox:checked + span {
    font-weight: bold;
}

/* Audiologists Sections */
.ham-audiologists-section {
    margin-bottom: 30px;
}

.ham-audiologists-section__title {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #2c5f5d;
}

.ham-audiologists-list {
    display: grid;
    gap: 12px;
}

/* Audiologist Item */
.ham-audiologist-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-radius: 8px;
}

.ham-audiologist-item--assigned {
    background: #d4edda;
    border-left: 4px solid #00a32a;
}

.ham-audiologist-item--available {
    background: #f9f9f9;
    border-left: 4px solid #2271b1;
}

.ham-audiologist-item__content {
    flex: 1;
}

.ham-audiologist-item__name {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 5px;
}

.ham-audiologist-item__excerpt {
    color: #666;
    font-size: 14px;
    margin-bottom: 3px;
}

.ham-audiologist-item__admin-note {
    color: #856404;
    font-size: 12px;
    font-style: italic;
}

.ham-audiologist-item__actions {
    margin-left: 15px;
}

/* Status Badges */
.ham-status {
    display: inline-block;
    padding: 2px 8px;
    color: white;
    font-size: 11px;
    border-radius: 10px;
    margin-left: 8px;
}

.ham-status--active {
    background: #00a32a;
}

.ham-status--pending {
    background: #f0b429;
}

/* Button Variants */
.ham-btn--remove {
    background: #d63638;
    color: white;
    border-color: #d63638;
}

/* Empty States */
.ham-empty-state {
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    text-align: center;
}

.ham-empty-state__text {
    margin: 0;
    color: #666;
}

.ham-empty-state__action {
    margin: 10px 0 0 0;
}

.ham-all-assigned {
    padding: 20px;
    background: #e7f5fe;
    border-radius: 8px;
    text-align: center;
}

.ham-all-assigned__text {
    margin: 0;
    color: #666;
}

/* Save Area */
.ham-save-area {
    text-align: center;
    padding-top: 30px;
    border-top: 2px solid #f0f0f0;
}

.ham-save-btn {
    padding: 15px 50px;
    font-size: 18px;
}

.ham-saving-indicator {
    display: none;
    margin-top: 15px;
}

.ham-saving-indicator__text {
    color: #2271b1;
}

/* ==========================================================================
   TABLET RESPONSIVE (max-width: 768px)
   ========================================================================== */
@media screen and (max-width: 768px) {
    /* Container */
    .ham-store-editor {
        margin: 20px auto;
        padding: 0 15px;
    }

    /* Header - stack vertically */
    .ham-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    /* Form */
    .ham-form {
        padding: 20px;
    }

    /* Form Sections */
    .ham-form-section {
        margin-bottom: 30px;
        padding-bottom: 20px;
    }

    /* Hours Row - stack on tablet */
    .ham-hours-row {
        grid-template-columns: 120px 1fr;
        gap: 10px;
    }

    /* Audiologist Item - stack vertically */
    .ham-audiologist-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .ham-audiologist-item__actions {
        margin-left: 0;
        width: 100%;
    }

    .ham-audiologist-item__actions .button {
        width: 100%;
        text-align: center;
    }

    /* Save Button */
    .ham-save-btn {
        padding: 12px 40px;
        font-size: 16px;
        width: 100%;
    }

    /* Messages */
    .ham-message {
        margin: 20px auto;
        padding: 20px;
    }
}

/* ==========================================================================
   MOBILE RESPONSIVE (max-width: 480px)
   ========================================================================== */
@media screen and (max-width: 480px) {
    /* Container */
	.grid-22 {
    display: block !important;

}
    .ham-store-editor {
        margin: 15px auto;
        padding: 0 10px;
    }

    /* Header */
    .ham-header {
        margin-bottom: 20px;
    }

    .ham-header__title {
        font-size: 24px;
    }

    /* Form */
    .ham-form {
        padding: 15px;
        border-radius: 6px;
    }

    /* Form Sections */
    .ham-form-section {
        margin-bottom: 25px;
        padding-bottom: 15px;
    }

    .ham-form-section__title {
        font-size: 18px;
        margin-bottom: 15px;
    }

    /* Form Rows */
    .ham-form-row {
        margin-bottom: 15px;
    }

    /* Inputs */
    .ham-input {
        padding: 12px 10px;
        font-size: 16px; /* Prevents zoom on iOS */
    }

    /* Hours Row - full stack on mobile */
    .ham-hours-row {
        grid-template-columns: 1fr;
        gap: 5px;
    }

    .ham-hours-label {
        margin-bottom: 2px;
    }

    .ham-input--small {
        width: 100%;
        padding: 10px;
        font-size: 16px;
    }

    /* Checkbox Labels */
    .ham-checkbox-label {
        padding: 12px;
        font-size: 14px;
    }

    /* Audiologist Item */
    .ham-audiologist-item {
        padding: 12px;
    }

    .ham-audiologist-item__name {
        font-size: 15px;
        line-height: 1.4;
    }

    .ham-audiologist-item__excerpt {
        font-size: 13px;
    }

    /* Status Badge - move to new line on very small screens */
    .ham-status {
        display: block;
        margin-left: 0;
        margin-top: 5px;
        width: fit-content;
    }

    /* Save Area */
    .ham-save-area {
        padding-top: 20px;
    }

    .ham-save-btn {
        padding: 14px 20px;
        font-size: 16px;
        width: 100%;
    }

    /* Messages */
    .ham-message {
        margin: 15px auto;
        padding: 15px;
    }

    .ham-message h2 {
        font-size: 20px;
    }

    /* Save Message */
    .ham-save-message {
        padding: 12px;
        font-size: 14px;
    }

    /* Empty States */
    .ham-empty-state,
    .ham-all-assigned {
        padding: 15px;
    }

    /* Help Text */
    .ham-help-text {
        font-size: 12px;
    }

    /* Section Description */
    .ham-section-description {
        font-size: 13px;
    }
}
</style>