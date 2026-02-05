<?php
/**
 * Plugin Name: GF Mailchimp Pro
 * Plugin URI: https://yoursite.com
 * Description: Gravity Forms to Mailchimp integration with full field mapping
 * Version: 2.1.0
 * Author: Bidview Marketing
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class GF_Mailchimp_Pro {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('gform_after_submission', array($this, 'send_to_mailchimp'), 10, 2);
        
        // Add form settings
        add_filter('gform_form_settings_menu', array($this, 'add_form_settings_menu'), 10, 2);
        add_action('gform_form_settings_page_gf_mailchimp', array($this, 'form_settings_page'));
        
        // AJAX handlers
        add_action('wp_ajax_gfmc_get_merge_fields', array($this, 'ajax_get_merge_fields'));
        add_action('wp_ajax_gfmc_get_lists', array($this, 'ajax_get_lists'));
        add_action('wp_ajax_gfmc_get_tags', array($this, 'ajax_get_tags'));
        
        // Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
    }
    
    public function admin_scripts($hook) {
        // Only on GF form edit pages
        if (strpos($hook, 'gf_edit_forms') !== false || strpos($hook, 'gf-edit-forms') !== false) {
            add_action('admin_footer', array($this, 'admin_footer_js'));
        }
    }
    
    public function admin_footer_js() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('GFMC: Script loaded');
            
            // When list changes, fetch merge fields
            $(document).on('change', '#gfmc_list_id', function() {
                console.log('GFMC: List changed');
                var listId = $(this).val();
                if (!listId) {
                    $('#gfmc-merge-fields-container').html('<p>Select an audience first</p>');
                    return;
                }
                
                $('#gfmc-merge-fields-container').html('<p>Loading merge fields...</p>');
                
                var postData = {
                    action: 'gfmc_get_merge_fields',
                    list_id: listId,
                    form_id: $('#gfmc_form_id').val(),
                    gfmc_nonce: $('#gfmc_nonce').val()
                };
                console.log('GFMC: Fetching merge fields', postData);
                
                $.post(ajaxurl, postData, function(response) {
                    console.log('GFMC: Response', response);
                    if (response.success) {
                        $('#gfmc-merge-fields-container').html(response.data.html);
                    } else {
                        $('#gfmc-merge-fields-container').html('<p style="color:red;">Error: ' + response.data + '</p>');
                    }
                }).fail(function(xhr, status, error) {
                    console.error('GFMC: AJAX failed', status, error, xhr.responseText);
                    $('#gfmc-merge-fields-container').html('<p style="color:red;">AJAX Error: ' + error + '</p>');
                });
            });
            
            // Trigger on page load if list is selected
            setTimeout(function() {
                if ($('#gfmc_list_id').length && $('#gfmc_list_id').val()) {
                    console.log('GFMC: Triggering initial load');
                    $('#gfmc_list_id').trigger('change');
                }
            }, 500);
        });
        </script>
        <?php
    }
    
    public function add_admin_menu() {
        add_options_page(
            'GF Mailchimp Pro',
            'GF Mailchimp',
            'manage_options',
            'gf-mailchimp',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('gfmc_settings', 'gfmc_api_key');
        register_setting('gfmc_settings', 'gfmc_default_list_id');
        register_setting('gfmc_settings', 'gfmc_double_optin');
        register_setting('gfmc_settings', 'gfmc_default_tags');
    }
    
    public function settings_page() {
        $api_key = get_option('gfmc_api_key');
        $lists = array();
        
        if (!empty($api_key)) {
            $lists = $this->get_mailchimp_lists();
        }
        ?>
        <div class="wrap">
            <h1>GF Mailchimp Pro</h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('gfmc_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th>Mailchimp API Key</th>
                        <td>
                            <input type="text" name="gfmc_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                            <p class="description">Get from Mailchimp → Account → API Keys</p>
                        </td>
                    </tr>
                    
                    <?php if (!empty($api_key)): ?>
                    <tr>
                        <th>Default Audience</th>
                        <td>
                            <?php if (!empty($lists)): ?>
                                <select name="gfmc_default_list_id" class="regular-text">
                                    <option value="">— Select Default Audience —</option>
                                    <?php foreach ($lists as $list): ?>
                                        <option value="<?php echo esc_attr($list['id']); ?>" <?php selected(get_option('gfmc_default_list_id'), $list['id']); ?>>
                                            <?php echo esc_html($list['name']); ?> (<?php echo number_format($list['stats']['member_count']); ?> members)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Can be overridden per form</p>
                            <?php else: ?>
                                <p style="color: red;">Could not fetch audiences. Check your API key.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Default Tags</th>
                        <td>
                            <input type="text" name="gfmc_default_tags" value="<?php echo esc_attr(get_option('gfmc_default_tags')); ?>" class="regular-text" placeholder="tag1, tag2">
                            <p class="description">Tag names (comma-separated) to apply to all subscribers</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Double Opt-in</th>
                        <td>
                            <label>
                                <input type="checkbox" name="gfmc_double_optin" value="1" <?php checked(get_option('gfmc_double_optin'), 1); ?>>
                                Require email confirmation (recommended)
                            </label>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <?php if (!empty($api_key) && !empty($lists)): ?>
            <hr>
            <h2>Your Mailchimp Audiences</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Audience Name</th>
                        <th>ID</th>
                        <th>Members</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lists as $list): ?>
                    <tr>
                        <td><?php echo esc_html($list['name']); ?></td>
                        <td><code><?php echo esc_html($list['id']); ?></code></td>
                        <td><?php echo number_format($list['stats']['member_count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <hr>
            <h2>How to Use</h2>
            <ol>
                <li>Enter your Mailchimp API key above and save</li>
                <li>Select a default audience (optional)</li>
                <li>Edit any Gravity Form → <strong>Settings → Mailchimp</strong></li>
                <li>Select an audience and map your form fields to Mailchimp merge fields</li>
            </ol>
        </div>
        <?php
    }
    
    // AJAX: Get lists
    public function ajax_get_lists() {
        check_ajax_referer('gfmc_form_settings', 'gfmc_nonce');
        
        $lists = $this->get_mailchimp_lists();
        if (empty($lists)) {
            wp_send_json_error('Could not fetch audiences');
        }
        
        wp_send_json_success($lists);
    }
    
    // AJAX: Get tags for a list
    public function ajax_get_tags() {
        check_ajax_referer('gfmc_form_settings', 'gfmc_nonce');
        
        $list_id = sanitize_text_field($_POST['list_id']);
        $selected_tags = isset($_POST['selected_tags']) ? sanitize_text_field($_POST['selected_tags']) : '';
        
        if (empty($list_id)) {
            wp_send_json_error('Missing list_id');
        }
        
        $tags = $this->get_mailchimp_tags($list_id);
        $selected_array = array_filter(array_map('trim', explode(',', $selected_tags)));
        
        // Build HTML for tag checkboxes
        $html = '';
        if (!empty($tags)) {
            $html .= '<div class="gfmc-tags-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #fafafa;">';
            foreach ($tags as $tag) {
                $checked = in_array($tag['name'], $selected_array) ? 'checked' : '';
                $html .= '<label style="display: block; margin-bottom: 8px; cursor: pointer;">';
                $html .= '<input type="checkbox" class="gfmc-tag-checkbox" value="' . esc_attr($tag['name']) . '" ' . $checked . '> ';
                $html .= esc_html($tag['name']) . ' <span style="color: #888; font-size: 12px;">(' . number_format($tag['member_count']) . ' members)</span>';
                $html .= '</label>';
            }
            $html .= '</div>';
            $html .= '<p class="description" style="margin-top: 8px;">Select tags to apply to subscribers from this form</p>';
        } else {
            $html .= '<p style="color: #666;">No tags found in this audience. You can create new ones below.</p>';
        }
        
        wp_send_json_success(array('html' => $html, 'tags' => $tags));
    }
    
    // AJAX: Get merge fields for a list
    public function ajax_get_merge_fields() {
        check_ajax_referer('gfmc_form_settings', 'gfmc_nonce');
        
        $list_id = sanitize_text_field($_POST['list_id']);
        $form_id = intval($_POST['form_id']);
        
        if (empty($list_id) || empty($form_id)) {
            wp_send_json_error('Missing list_id or form_id');
        }
        
        $merge_fields = $this->get_mailchimp_merge_fields($list_id);
        $form = GFAPI::get_form($form_id);
        $settings = isset($form['gfmc_settings']) ? $form['gfmc_settings'] : array();
        $field_map = isset($settings['field_map']) ? $settings['field_map'] : array();
        
        // Build HTML for field mapping
        $html = '<table class="form-table gfmc-field-map">';
        $html .= '<tr><th>Mailchimp Field</th><th>Gravity Form Field</th></tr>';
        
        // Email field (required)
        $html .= '<tr>';
        $html .= '<td><strong>Email Address *</strong> <span class="description">(required)</span></td>';
        $html .= '<td>' . $this->get_field_dropdown('email', $form, isset($field_map['email']) ? $field_map['email'] : '') . '</td>';
        $html .= '</tr>';
        
        // Merge fields from Mailchimp
        foreach ($merge_fields as $field) {
            $tag = $field['tag'];
            $required = $field['required'] ? ' *' : '';
            $html .= '<tr>';
            $html .= '<td><strong>' . esc_html($field['name']) . $required . '</strong> <code>' . esc_html($tag) . '</code></td>';
            $html .= '<td>' . $this->get_field_dropdown($tag, $form, isset($field_map[$tag]) ? $field_map[$tag] : '') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        wp_send_json_success(array('html' => $html, 'fields' => $merge_fields));
    }
    
    private function get_field_dropdown($merge_tag, $form, $selected_value) {
        $fields = $form['fields'];
        $name = 'gfmc_field_map[' . esc_attr($merge_tag) . ']';
        
        $html = '<select name="' . $name . '" class="regular-text">';
        $html .= '<option value="">— Do not map —</option>';
        
        foreach ($fields as $field) {
            // Handle Name fields with sub-inputs
            if ($field->type === 'name' && !empty($field->inputs)) {
                foreach ($field->inputs as $input) {
                    if (!empty($input['isHidden'])) continue;
                    $html .= '<option value="' . esc_attr($input['id']) . '" ' . selected($selected_value, $input['id'], false) . '>';
                    $html .= esc_html($field->label . ' - ' . $input['label']) . ' (ID: ' . $input['id'] . ')';
                    $html .= '</option>';
                }
            }
            // Handle Address fields with sub-inputs
            elseif ($field->type === 'address' && !empty($field->inputs)) {
                foreach ($field->inputs as $input) {
                    if (!empty($input['isHidden'])) continue;
                    $html .= '<option value="' . esc_attr($input['id']) . '" ' . selected($selected_value, $input['id'], false) . '>';
                    $html .= esc_html($field->label . ' - ' . $input['label']) . ' (ID: ' . $input['id'] . ')';
                    $html .= '</option>';
                }
            }
            // Regular fields
            else {
                $html .= '<option value="' . esc_attr($field->id) . '" ' . selected($selected_value, $field->id, false) . '>';
                $html .= esc_html($field->label) . ' (ID: ' . $field->id . ', Type: ' . $field->type . ')';
                $html .= '</option>';
            }
        }
        
        $html .= '</select>';
        return $html;
    }
    
    private function get_data_center($api_key) {
        $parts = explode('-', $api_key);
        return isset($parts[1]) ? $parts[1] : 'us1';
    }
    
    private function get_mailchimp_lists() {
        $api_key = get_option('gfmc_api_key');
        if (empty($api_key)) {
            return array();
        }
        
        $dc = $this->get_data_center($api_key);
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists?count=100";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('anystring:' . $api_key)
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['lists']) ? $body['lists'] : array();
    }
    
    private function get_mailchimp_merge_fields($list_id) {
        $api_key = get_option('gfmc_api_key');
        if (empty($api_key)) {
            return array();
        }
        
        $dc = $this->get_data_center($api_key);
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/merge-fields?count=100";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('anystring:' . $api_key)
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['merge_fields']) ? $body['merge_fields'] : array();
    }
    
    private function get_mailchimp_tags($list_id) {
        $api_key = get_option('gfmc_api_key');
        if (empty($api_key)) {
            return array();
        }
        
        $dc = $this->get_data_center($api_key);
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/segments?type=static&count=100";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('anystring:' . $api_key)
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['segments']) ? $body['segments'] : array();
    }
    
    // Add Mailchimp tab to form settings
    public function add_form_settings_menu($menu_items, $form_id) {
        $menu_items[] = array(
            'name' => 'gf_mailchimp',
            'label' => 'Mailchimp',
            'icon' => 'gform-icon--mail'
        );
        return $menu_items;
    }
    
    // Form-specific settings page
    public function form_settings_page() {
        $form_id = rgget('id');
        $form = GFAPI::get_form($form_id);
        
        // Save settings
        if (isset($_POST['gfmc_form_save']) && check_admin_referer('gfmc_form_settings', 'gfmc_nonce')) {
            $settings = array(
                'enabled' => isset($_POST['gfmc_enabled']) ? 1 : 0,
                'list_id' => sanitize_text_field($_POST['gfmc_list_id']),
                'field_map' => isset($_POST['gfmc_field_map']) ? array_map('sanitize_text_field', $_POST['gfmc_field_map']) : array(),
                'tags' => sanitize_text_field($_POST['gfmc_tags']),
                'double_optin' => isset($_POST['gfmc_double_optin']) ? 1 : 0,
                'update_existing' => isset($_POST['gfmc_update_existing']) ? 1 : 0,
            );
            
            $form['gfmc_settings'] = $settings;
            GFAPI::update_form($form);
            
            echo '<div class="notice notice-success"><p>Mailchimp settings saved!</p></div>';
            $form = GFAPI::get_form($form_id); // Refresh
        }
        
        $settings = isset($form['gfmc_settings']) ? $form['gfmc_settings'] : array();
        $settings = wp_parse_args($settings, array(
            'enabled' => 0,
            'list_id' => get_option('gfmc_default_list_id'),
            'field_map' => array(),
            'tags' => get_option('gfmc_default_tags'),
            'double_optin' => get_option('gfmc_double_optin'),
            'update_existing' => 1,
        ));
        
        $api_key = get_option('gfmc_api_key');
        $lists = array();
        
        if (!empty($api_key)) {
            $lists = $this->get_mailchimp_lists();
        }
        ?>
        <style>
            .gfmc-field-map { background: #fff; }
            .gfmc-field-map th, .gfmc-field-map td { padding: 12px; border-bottom: 1px solid #eee; }
            .gfmc-field-map code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
            .gfmc-card { background: #fff; border: 1px solid #ccd0d4; padding: 15px 20px; margin: 15px 0; }
            .gfmc-card h3 { margin-top: 0; }
        </style>
        
        <div class="gform-settings-panel">
            <header class="gform-settings-panel__header">
                <h4 class="gform-settings-panel__title">Mailchimp Integration</h4>
            </header>
            <div class="gform-settings-panel__content">
                
                <?php if (empty($api_key)): ?>
                    <div class="notice notice-error" style="margin: 10px 0;">
                        <p>Please configure your Mailchimp API key in <a href="<?php echo admin_url('options-general.php?page=gf-mailchimp'); ?>">Settings → GF Mailchimp</a> first.</p>
                    </div>
                <?php else: ?>
                
                <form method="post">
                    <?php wp_nonce_field('gfmc_form_settings', 'gfmc_nonce'); ?>
                    <input type="hidden" id="gfmc_form_id" value="<?php echo esc_attr($form_id); ?>">
                    
                    <div class="gfmc-card">
                        <h3>⚡ Enable Integration</h3>
                        <table class="form-table">
                            <tr>
                                <th>Enable Mailchimp</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="gfmc_enabled" value="1" <?php checked($settings['enabled'], 1); ?>>
                                        Send form submissions to Mailchimp
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="gfmc-card">
                        <h3>📋 Select Audience</h3>
                        <table class="form-table">
                            <tr>
                                <th>Mailchimp Audience</th>
                                <td>
                                    <select name="gfmc_list_id" id="gfmc_list_id" class="regular-text">
                                        <option value="">— Select Audience —</option>
                                        <?php foreach ($lists as $list): ?>
                                            <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($settings['list_id'], $list['id']); ?>>
                                                <?php echo esc_html($list['name']); ?> (<?php echo number_format($list['stats']['member_count']); ?> members)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="gfmc-card">
                        <h3>🔗 Field Mapping</h3>
                        <p>Map your form fields to Mailchimp merge fields:</p>
                        <div id="gfmc-merge-fields-container">
                            <?php if (!empty($settings['list_id'])): ?>
                                <p>Loading merge fields...</p>
                            <?php else: ?>
                                <p>Select an audience above to see available fields</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="gfmc-card">
                        <h3>🏷️ Tags & Options</h3>
                        <table class="form-table">
                            <tr>
                                <th>Existing Tags</th>
                                <td>
                                    <div id="gfmc-tags-container">
                                        <?php if (!empty($settings['list_id'])): ?>
                                            <p>Loading tags...</p>
                                        <?php else: ?>
                                            <p>Select an audience above to see available tags</p>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="gfmc_tags" id="gfmc_tags_hidden" value="<?php echo esc_attr($settings['tags']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th>Create New Tags</th>
                                <td>
                                    <input type="text" name="gfmc_new_tags" id="gfmc_new_tags" value="" class="regular-text" placeholder="new-tag-1, new-tag-2">
                                    <p class="description">Enter new tag names to create (comma-separated). These will be created in Mailchimp when a subscriber is added.</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Double Opt-in</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="gfmc_double_optin" value="1" <?php checked($settings['double_optin'], 1); ?>>
                                        Require email confirmation
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>Update Existing</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="gfmc_update_existing" value="1" <?php checked($settings['update_existing'], 1); ?>>
                                        Update subscriber if email already exists
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="gfmc_form_save" class="button button-primary" value="Save Mailchimp Settings">
                    </p>
                </form>
                
                <?php endif; ?>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('GFMC: Script loaded, form_id=<?php echo esc_js($form_id); ?>');
            
            // When list changes, fetch merge fields AND tags
            $('#gfmc_list_id').on('change', function() {
                console.log('GFMC: List changed to', $(this).val());
                var listId = $(this).val();
                if (!listId) {
                    $('#gfmc-merge-fields-container').html('<p>Select an audience first</p>');
                    $('#gfmc-tags-container').html('<p>Select an audience first</p>');
                    return;
                }
                
                $('#gfmc-merge-fields-container').html('<p>Loading merge fields...</p>');
                $('#gfmc-tags-container').html('<p>Loading tags...</p>');
                
                // Fetch tags
                $.post(ajaxurl, {
                    action: 'gfmc_get_tags',
                    list_id: listId,
                    selected_tags: $('#gfmc_tags_hidden').val(),
                    gfmc_nonce: $('#gfmc_nonce').val()
                }, function(response) {
                    console.log('GFMC: Tags response', response);
                    if (response.success) {
                        $('#gfmc-tags-container').html(response.data.html);
                    } else {
                        $('#gfmc-tags-container').html('<p style="color:red;">Error loading tags</p>');
                    }
                });
                
                var postData = {
                    action: 'gfmc_get_merge_fields',
                    list_id: listId,
                    form_id: '<?php echo esc_js($form_id); ?>',
                    gfmc_nonce: $('#gfmc_nonce').val()
                };
                console.log('GFMC: Posting', postData);
                
                $.post(ajaxurl, postData, function(response) {
                    console.log('GFMC: Response', response);
                    if (response.success) {
                        $('#gfmc-merge-fields-container').html(response.data.html);
                    } else {
                        $('#gfmc-merge-fields-container').html('<p style="color:red;">Error: ' + (response.data || 'Unknown error') + '</p>');
                    }
                }).fail(function(xhr, status, error) {
                    console.error('GFMC: AJAX failed', status, error, xhr.responseText);
                    $('#gfmc-merge-fields-container').html('<p style="color:red;">AJAX Error: ' + error + '<br>Response: ' + xhr.responseText.substring(0, 200) + '</p>');
                });
            });
            
            // Update hidden field when tag checkboxes change
            $(document).on('change', '.gfmc-tag-checkbox', function() {
                var selectedTags = [];
                $('.gfmc-tag-checkbox:checked').each(function() {
                    selectedTags.push($(this).val());
                });
                $('#gfmc_tags_hidden').val(selectedTags.join(', '));
                console.log('GFMC: Selected tags:', selectedTags);
            });
            
            // On form submit, combine existing tags with new tags
            $('form').on('submit', function() {
                var existingTags = $('#gfmc_tags_hidden').val();
                var newTags = $('#gfmc_new_tags').val();
                var allTags = [];
                
                if (existingTags) allTags.push(existingTags);
                if (newTags) allTags.push(newTags);
                
                $('#gfmc_tags_hidden').val(allTags.join(', '));
            });
            
            // Trigger on page load if list is selected
            <?php if (!empty($settings['list_id'])): ?>
            setTimeout(function() {
                console.log('GFMC: Triggering initial load for saved list');
                $('#gfmc_list_id').trigger('change');
            }, 300);
            <?php endif; ?>
        });
        </script>
        <?php
    }
    
    // Process form submission
    public function send_to_mailchimp($entry, $form) {
        $settings = isset($form['gfmc_settings']) ? $form['gfmc_settings'] : array();
        
        // Check if enabled
        if (empty($settings['enabled'])) {
            return;
        }
        
        $api_key = get_option('gfmc_api_key');
        $list_id = !empty($settings['list_id']) ? $settings['list_id'] : get_option('gfmc_default_list_id');
        
        if (empty($api_key) || empty($list_id)) {
            $this->log('Missing API key or List ID');
            return;
        }
        
        $field_map = isset($settings['field_map']) ? $settings['field_map'] : array();
        
        // Get email (required)
        $email = '';
        if (!empty($field_map['email'])) {
            $email = rgar($entry, $field_map['email']);
        }
        
        if (empty($email) || !is_email($email)) {
            $this->log('Invalid or missing email: ' . $email);
            return;
        }
        
        // Build merge fields
        $merge_fields = array();
        foreach ($field_map as $tag => $field_id) {
            if ($tag === 'email' || empty($field_id)) continue;
            
            $value = rgar($entry, $field_id);
            if (!empty($value)) {
                $merge_fields[$tag] = $value;
            }
        }
        
        // Build subscriber data
        $subscriber_data = array(
            'email_address' => $email,
            'status' => !empty($settings['double_optin']) ? 'pending' : 'subscribed',
        );
        
        if (!empty($merge_fields)) {
            $subscriber_data['merge_fields'] = $merge_fields;
        }
        
        // Send to Mailchimp
        $result = $this->add_subscriber($api_key, $list_id, $subscriber_data, !empty($settings['update_existing']));
        
        if (is_wp_error($result)) {
            $this->log('Mailchimp error: ' . $result->get_error_message());
        } else {
            $this->log('Successfully added: ' . $email);
            
            // Add tags if specified
            $tags = !empty($settings['tags']) ? $settings['tags'] : get_option('gfmc_default_tags');
            if (!empty($tags)) {
                $this->add_tags_to_subscriber($api_key, $list_id, $email, $tags);
            }
        }
    }
    
    private function add_subscriber($api_key, $list_id, $data, $update_existing = true) {
        $dc = $this->get_data_center($api_key);
        
        if ($update_existing) {
            // Use PUT to add or update
            $email_hash = md5(strtolower($data['email_address']));
            $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$email_hash}";
            
            $response = wp_remote_request($url, array(
                'method' => 'PUT',
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode('anystring:' . $api_key),
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array_merge($data, array(
                    'status_if_new' => $data['status']
                ))),
                'timeout' => 15
            ));
        } else {
            // Use POST to add only
            $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members";
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode('anystring:' . $api_key),
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => 15
            ));
        }
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code >= 400) {
            return new WP_Error('mailchimp_error', isset($body['detail']) ? $body['detail'] : 'Unknown error');
        }
        
        return $body;
    }
    
    private function add_tags_to_subscriber($api_key, $list_id, $email, $tags_string) {
        $dc = $this->get_data_center($api_key);
        $email_hash = md5(strtolower($email));
        
        // Parse tags
        $tag_names = array_map('trim', explode(',', $tags_string));
        $tags = array();
        foreach ($tag_names as $name) {
            if (!empty($name)) {
                $tags[] = array('name' => $name, 'status' => 'active');
            }
        }
        
        if (empty($tags)) {
            return;
        }
        
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$email_hash}/tags";
        
        wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('anystring:' . $api_key),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array('tags' => $tags)),
            'timeout' => 15
        ));
    }
    
    private function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GF Mailchimp Pro] ' . $message);
        }
    }
}

// Initialize
function gfmc_init() {
    if (class_exists('GFAPI')) {
        GF_Mailchimp_Pro::instance();
    }
}
add_action('plugins_loaded', 'gfmc_init');

// Show notice if Gravity Forms not active
function gfmc_admin_notice() {
    if (!class_exists('GFAPI')) {
        echo '<div class="notice notice-error"><p><strong>GF Mailchimp Pro</strong> requires Gravity Forms to be installed and activated.</p></div>';
    }
}
add_action('admin_notices', 'gfmc_admin_notice');
