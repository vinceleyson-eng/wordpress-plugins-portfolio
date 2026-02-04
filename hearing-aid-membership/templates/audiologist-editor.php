<?php
/**
 * Audiologist Profile Editor
 */

if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    echo '<p>Please login to edit this profile.</p>';
    return;
}

$user_id = get_current_user_id();
$audio_id = isset($_GET['edit_audiologist']) ? intval($_GET['edit_audiologist']) : 0;

if (!$audio_id) {
    echo '<p>Invalid audiologist ID.</p>';
    return;
}

$audiologist = get_post($audio_id);

// Verify ownership
if (!$audiologist || $audiologist->post_author != $user_id || $audiologist->post_type !== 'audiologist') {
    echo '<div style="padding: 30px; background: #f8d7da; border: 2px solid #d63638; border-radius: 8px; text-align: center;">';
    echo '<h2>Access Denied</h2>';
    echo '<p>You do not have permission to edit this audiologist profile.</p>';
    echo '</div>';
    return;
}

// Get user's stores for linking
$user_stores = get_posts(array(
    'post_type' => 'hearing-aid-store',
    'author' => $user_id,
    'posts_per_page' => -1,
    'post_status' => array('publish', 'pending', 'draft')
));

$linked_store_id = get_post_meta($audio_id, 'linked_store_id', true);
$linked_store = $linked_store_id ? get_post($linked_store_id) : null;

// Try to get bio from ACF field first, fallback to post_content for old data
$audiologist_bio = get_field('audiologists_bio', $audio_id);
if (empty($audiologist_bio)) {
    // Fallback to post_content for audiologists created before ACF field was implemented
    $audiologist_bio = $audiologist->post_content;
}

$back_url = remove_query_arg('edit_audiologist');

?>

<div class="ham-audiologist-editor" style="max-width: 900px; margin: 40px auto; padding: 0 20px;">
    
    <div style="margin-bottom: 30px;">
        <h1 style="margin: 0 0 5px 0;">Edit Audiologist Profile</h1>
        <p style="margin: 0;">
            <a href="<?php echo esc_url($back_url); ?>" style="color: #2271b1; text-decoration: none;">
                ← Back to Dashboard
            </a>
        </p>
    </div>
    
    <div id="save-message" style="display: none; padding: 15px; margin-bottom: 20px; border-radius: 4px;"></div>
    
    <form id="audiologist-edit-form" style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        
        <!-- Basic Information -->
        <div class="form-section" style="margin-bottom: 30px;">
            <h2 style="margin: 0 0 20px 0; color: #2271b1;">Basic Information</h2>
            
            <div class="form-row" style="margin-bottom: 20px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Name *</label>
                <input type="text" name="post_title" value="<?php echo esc_attr($audiologist->post_title); ?>" required
                       placeholder="Dr. Jane Smith"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
            </div>
            
            <div class="form-row" style="margin-bottom: 20px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Credentials</label>
                <input type="text" name="post_excerpt" value="<?php echo esc_attr($audiologist->post_excerpt); ?>"
                       placeholder="Au.D., CCC-A"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">Professional certifications and degrees</p>
            </div>
            
            <div class="form-row" style="margin-bottom: 20px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Professional Bio</label>
                <?php 
                // Use WordPress visual editor (TinyMCE)
                $editor_settings = array(
                    'textarea_name' => 'audiologists_bio',
                    'textarea_rows' => 10,
                    'media_buttons' => false, // No media upload button
                    'teeny' => false, // Full editor
                    'quicktags' => true, // Show text/visual tabs
                    'tinymce' => array(
                        'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,blockquote,alignleft,aligncenter,alignright,undo,redo',
                        'toolbar2' => '',
                        'content_css' => false
                    )
                );
                wp_editor($audiologist_bio, 'audiologists_bio_editor', $editor_settings);
                ?>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">Education, experience, specialties, etc. Use the editor to format your bio.</p>
            </div>
            
            <div class="form-row" style="margin-bottom: 20px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Works At</label>
                <select name="linked_store_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">-- No Location --</option>
                    <?php foreach ($user_stores as $store): ?>
                        <option value="<?php echo $store->ID; ?>" <?php selected($linked_store_id, $store->ID); ?>>
                            <?php echo esc_html($store->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">Which store location does this audiologist work at?</p>
            </div>
        </div>
        
        <?php if ($audiologist->post_status === 'pending'): ?>
            <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 20px;">
                <p style="margin: 0;"><strong>⏳ Pending Approval:</strong> This profile is waiting for admin approval before it goes live.</p>
            </div>
        <?php endif; ?>
        
        <!-- Save Button -->
        <div style="text-align: center; padding-top: 20px; border-top: 2px solid #f0f0f0;">
            <button type="submit" id="save-btn" class="button button-primary button-large" style="padding: 15px 50px; font-size: 18px;">
                Save Changes
            </button>
            <div id="saving-indicator" style="display: none; margin-top: 15px;">
                <span style="color: #2271b1;">💾 Saving...</span>
            </div>
        </div>
        
        <input type="hidden" name="audiologist_id" value="<?php echo $audio_id; ?>">
        <input type="hidden" name="action" value="ham_save_audiologist">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ham_save_audiologist_' . $audio_id); ?>">
        
    </form>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('audiologist-edit-form');
    const saveBtn = document.getElementById('save-btn');
    const savingIndicator = document.getElementById('saving-indicator');
    const saveMessage = document.getElementById('save-message');
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
        savingIndicator.style.display = 'block';
        saveMessage.style.display = 'none';
        
        try {
            // Get TinyMCE content before submitting
            if (typeof tinyMCE !== 'undefined') {
                tinyMCE.triggerSave();
            }
            
            const formData = new FormData(form);
            
            const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                saveMessage.style.display = 'block';
                saveMessage.style.background = '#d4edda';
                saveMessage.style.borderColor = '#c3e6cb';
                saveMessage.style.color = '#155724';
                saveMessage.innerHTML = '<strong>✓ Success!</strong> Profile has been updated.';
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                throw new Error(result.data.message || 'Save failed');
            }
            
        } catch (error) {
            saveMessage.style.display = 'block';
            saveMessage.style.background = '#f8d7da';
            saveMessage.style.borderColor = '#f5c6cb';
            saveMessage.style.color = '#721c24';
            saveMessage.innerHTML = '<strong>✗ Error:</strong> ' + error.message;
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Changes';
        savingIndicator.style.display = 'none';
    });
});
</script>
