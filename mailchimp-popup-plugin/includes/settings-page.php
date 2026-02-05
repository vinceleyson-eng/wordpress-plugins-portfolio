<?php
/**
 * Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap mcp-settings">
    <h1>📧 Mailchimp Popup Settings</h1>
    
    <?php if ($api_test_result): ?>
        <div class="notice notice-<?php echo $api_test_result['success'] ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($api_test_result['message']); ?></p>
        </div>
    <?php endif; ?>
    
    <?php settings_errors(); ?>
    
    <form method="post" action="options.php">
        <?php settings_fields('mcp_settings'); ?>
        
        <!-- Enable/Disable -->
        <div class="mcp-card">
            <h2>⚡ Quick Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Popup</th>
                    <td>
                        <label class="mcp-toggle">
                            <input type="checkbox" name="mcp_enabled" value="1" <?php checked(get_option('mcp_enabled'), 1); ?>>
                            <span class="mcp-toggle-slider"></span>
                        </label>
                        <p class="description">Turn the popup on or off</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Mailchimp Settings -->
        <div class="mcp-card">
            <h2>🔗 Mailchimp Connection</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Use Embed Code</th>
                    <td>
                        <label>
                            <input type="checkbox" name="mcp_use_embed_code" value="1" <?php checked(get_option('mcp_use_embed_code'), 1); ?> id="mcp_use_embed">
                            Use Mailchimp embed code instead of API
                        </label>
                        <p class="description">If checked, paste your Mailchimp form embed code below</p>
                    </td>
                </tr>
                <tr class="mcp-embed-row" style="<?php echo get_option('mcp_use_embed_code') ? '' : 'display:none;'; ?>">
                    <th scope="row">Embed Code</th>
                    <td>
                        <textarea name="mcp_embed_code" rows="6" class="large-text code"><?php echo esc_textarea(get_option('mcp_embed_code')); ?></textarea>
                        <p class="description">Paste your Mailchimp embedded form code here</p>
                    </td>
                </tr>
                <tr class="mcp-api-row" style="<?php echo get_option('mcp_use_embed_code') ? 'display:none;' : ''; ?>">
                    <th scope="row">API Key</th>
                    <td>
                        <input type="text" name="mcp_mailchimp_api_key" value="<?php echo esc_attr(get_option('mcp_mailchimp_api_key')); ?>" class="regular-text" placeholder="xxxxxxxxxxxxxxxx-us19">
                        <a href="<?php echo admin_url('admin.php?page=mailchimp-popup&test_api=1'); ?>" class="button">Test Connection</a>
                        <p class="description">Find your API key in Mailchimp → Account → Extras → API keys</p>
                    </td>
                </tr>
                <tr class="mcp-api-row" style="<?php echo get_option('mcp_use_embed_code') ? 'display:none;' : ''; ?>">
                    <th scope="row">Audience/List</th>
                    <td>
                        <?php if (!empty($lists)): ?>
                            <select name="mcp_mailchimp_list_id">
                                <option value="">Select an audience...</option>
                                <?php foreach ($lists as $list): ?>
                                    <option value="<?php echo esc_attr($list['id']); ?>" <?php selected(get_option('mcp_mailchimp_list_id'), $list['id']); ?>>
                                        <?php echo esc_html($list['name']); ?> (<?php echo number_format($list['stats']['member_count']); ?> subscribers)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="mcp_mailchimp_list_id" value="<?php echo esc_attr(get_option('mcp_mailchimp_list_id')); ?>" class="regular-text" placeholder="Enter List ID or add API key first">
                        <?php endif; ?>
                        <p class="description">Select the audience to add subscribers to</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Content Settings -->
        <div class="mcp-card">
            <h2>✏️ Popup Content</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Title</th>
                    <td>
                        <input type="text" name="mcp_popup_title" value="<?php echo esc_attr(get_option('mcp_popup_title')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Description</th>
                    <td>
                        <textarea name="mcp_popup_description" rows="3" class="large-text"><?php echo esc_textarea(get_option('mcp_popup_description')); ?></textarea>
                    </td>
                </tr>
                <tr class="mcp-api-row" style="<?php echo get_option('mcp_use_embed_code') ? 'display:none;' : ''; ?>">
                    <th scope="row">Button Text</th>
                    <td>
                        <input type="text" name="mcp_submit_button_text" value="<?php echo esc_attr(get_option('mcp_submit_button_text')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr class="mcp-api-row" style="<?php echo get_option('mcp_use_embed_code') ? 'display:none;' : ''; ?>">
                    <th scope="row">Success Message</th>
                    <td>
                        <input type="text" name="mcp_success_message" value="<?php echo esc_attr(get_option('mcp_success_message')); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Trigger Settings -->
        <div class="mcp-card">
            <h2>🎯 Trigger Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Trigger Type</th>
                    <td>
                        <select name="mcp_trigger_type" id="mcp_trigger_type">
                            <option value="time_delay" <?php selected(get_option('mcp_trigger_type'), 'time_delay'); ?>>Time Delay</option>
                            <option value="scroll" <?php selected(get_option('mcp_trigger_type'), 'scroll'); ?>>Scroll Percentage</option>
                            <option value="exit_intent" <?php selected(get_option('mcp_trigger_type'), 'exit_intent'); ?>>Exit Intent Only</option>
                            <option value="immediate" <?php selected(get_option('mcp_trigger_type'), 'immediate'); ?>>Immediate (on page load)</option>
                        </select>
                    </td>
                </tr>
                <tr class="mcp-time-row">
                    <th scope="row">Time Delay (seconds)</th>
                    <td>
                        <input type="number" name="mcp_time_delay" value="<?php echo esc_attr(get_option('mcp_time_delay', 5)); ?>" min="0" max="120" class="small-text"> seconds
                    </td>
                </tr>
                <tr class="mcp-scroll-row" style="display:none;">
                    <th scope="row">Scroll Percentage</th>
                    <td>
                        <input type="number" name="mcp_scroll_percentage" value="<?php echo esc_attr(get_option('mcp_scroll_percentage', 50)); ?>" min="0" max="100" class="small-text"> %
                    </td>
                </tr>
                <tr>
                    <th scope="row">Exit Intent</th>
                    <td>
                        <label>
                            <input type="checkbox" name="mcp_exit_intent" value="1" <?php checked(get_option('mcp_exit_intent'), 1); ?>>
                            Also trigger on exit intent (mouse leaves viewport)
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Display Rules -->
        <div class="mcp-card">
            <h2>📄 Display Rules</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Show On</th>
                    <td>
                        <select name="mcp_show_on">
                            <option value="all" <?php selected(get_option('mcp_show_on'), 'all'); ?>>All Pages</option>
                            <option value="homepage" <?php selected(get_option('mcp_show_on'), 'homepage'); ?>>Homepage Only</option>
                            <option value="posts" <?php selected(get_option('mcp_show_on'), 'posts'); ?>>Blog Posts Only</option>
                            <option value="pages" <?php selected(get_option('mcp_show_on'), 'pages'); ?>>Pages Only</option>
                            <option value="specific" <?php selected(get_option('mcp_show_on'), 'specific'); ?>>Specific Pages (by ID)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Specific Page IDs</th>
                    <td>
                        <input type="text" name="mcp_show_on_pages" value="<?php echo esc_attr(get_option('mcp_show_on_pages')); ?>" class="regular-text" placeholder="e.g., 1, 15, 234">
                        <p class="description">Comma-separated page IDs (only used when "Specific Pages" is selected)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Exclude Page IDs</th>
                    <td>
                        <input type="text" name="mcp_exclude_pages" value="<?php echo esc_attr(get_option('mcp_exclude_pages')); ?>" class="regular-text" placeholder="e.g., 5, 10">
                        <p class="description">Comma-separated page IDs to exclude</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Display Frequency</th>
                    <td>
                        <select name="mcp_display_frequency">
                            <option value="always" <?php selected(get_option('mcp_display_frequency'), 'always'); ?>>Every page view</option>
                            <option value="once_per_session" <?php selected(get_option('mcp_display_frequency'), 'once_per_session'); ?>>Once per session</option>
                            <option value="once_per_day" <?php selected(get_option('mcp_display_frequency'), 'once_per_day'); ?>>Once per day</option>
                            <option value="once_per_x_days" <?php selected(get_option('mcp_display_frequency'), 'once_per_x_days'); ?>>Once every X days</option>
                            <option value="once_ever" <?php selected(get_option('mcp_display_frequency'), 'once_ever'); ?>>Once ever (until cookies cleared)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Days Between</th>
                    <td>
                        <input type="number" name="mcp_days_between" value="<?php echo esc_attr(get_option('mcp_days_between', 7)); ?>" min="1" max="365" class="small-text"> days
                        <p class="description">Only used when "Once every X days" is selected</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Mobile</th>
                    <td>
                        <label>
                            <input type="checkbox" name="mcp_mobile_enabled" value="1" <?php checked(get_option('mcp_mobile_enabled'), 1); ?>>
                            Show on mobile devices
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Style Settings -->
        <div class="mcp-card">
            <h2>🎨 Appearance</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Position</th>
                    <td>
                        <select name="mcp_position">
                            <option value="center" <?php selected(get_option('mcp_position'), 'center'); ?>>Center</option>
                            <option value="top" <?php selected(get_option('mcp_position'), 'top'); ?>>Top</option>
                            <option value="bottom" <?php selected(get_option('mcp_position'), 'bottom'); ?>>Bottom</option>
                            <option value="bottom-right" <?php selected(get_option('mcp_position'), 'bottom-right'); ?>>Bottom Right</option>
                            <option value="bottom-left" <?php selected(get_option('mcp_position'), 'bottom-left'); ?>>Bottom Left</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Animation</th>
                    <td>
                        <select name="mcp_animation">
                            <option value="fade" <?php selected(get_option('mcp_animation'), 'fade'); ?>>Fade In</option>
                            <option value="slide-up" <?php selected(get_option('mcp_animation'), 'slide-up'); ?>>Slide Up</option>
                            <option value="slide-down" <?php selected(get_option('mcp_animation'), 'slide-down'); ?>>Slide Down</option>
                            <option value="zoom" <?php selected(get_option('mcp_animation'), 'zoom'); ?>>Zoom In</option>
                            <option value="none" <?php selected(get_option('mcp_animation'), 'none'); ?>>None</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Background Color</th>
                    <td>
                        <input type="text" name="mcp_bg_color" value="<?php echo esc_attr(get_option('mcp_bg_color', '#ffffff')); ?>" class="mcp-color-picker">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Text Color</th>
                    <td>
                        <input type="text" name="mcp_text_color" value="<?php echo esc_attr(get_option('mcp_text_color', '#333333')); ?>" class="mcp-color-picker">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Button Background</th>
                    <td>
                        <input type="text" name="mcp_button_bg_color" value="<?php echo esc_attr(get_option('mcp_button_bg_color', '#0073aa')); ?>" class="mcp-color-picker">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Button Text Color</th>
                    <td>
                        <input type="text" name="mcp_button_text_color" value="<?php echo esc_attr(get_option('mcp_button_text_color', '#ffffff')); ?>" class="mcp-color-picker">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Overlay Color</th>
                    <td>
                        <input type="text" name="mcp_overlay_color" value="<?php echo esc_attr(get_option('mcp_overlay_color', 'rgba(0,0,0,0.6)')); ?>" class="regular-text" placeholder="rgba(0,0,0,0.6)">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Close Button</th>
                    <td>
                        <label>
                            <input type="checkbox" name="mcp_show_close_button" value="1" <?php checked(get_option('mcp_show_close_button'), 1); ?>>
                            Show close (×) button
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Close on Overlay Click</th>
                    <td>
                        <label>
                            <input type="checkbox" name="mcp_close_on_overlay" value="1" <?php checked(get_option('mcp_close_on_overlay'), 1); ?>>
                            Close popup when clicking outside
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button('Save Settings'); ?>
    </form>
    
    <!-- Preview -->
    <div class="mcp-card">
        <h2>👁️ Preview</h2>
        <p>Save your settings, then <a href="<?php echo home_url(); ?>" target="_blank">visit your site</a> to see the popup in action.</p>
        <p>Tip: Open an incognito window to test the popup without cookie restrictions.</p>
    </div>
</div>

<style>
.mcp-settings { max-width: 900px; }
.mcp-card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
.mcp-card h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
.mcp-toggle { position: relative; display: inline-block; width: 50px; height: 26px; }
.mcp-toggle input { opacity: 0; width: 0; height: 0; }
.mcp-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 26px; }
.mcp-toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
.mcp-toggle input:checked + .mcp-toggle-slider { background-color: #00a32a; }
.mcp-toggle input:checked + .mcp-toggle-slider:before { transform: translateX(24px); }
</style>
