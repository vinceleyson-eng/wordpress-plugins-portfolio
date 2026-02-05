<?php
/**
 * Plugin Name: Mailchimp Popup Forms
 * Plugin URI: https://yoursite.com
 * Description: Display Mailchimp signup forms in popups - users must submit to close
 * Version: 1.1.0
 * Author: Bidview Marketing
 * Text Domain: mc-popup
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MCP_VERSION', '1.1.0');
define('MCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MCP_PLUGIN_URL', plugin_dir_url(__FILE__));

class Mailchimp_Popup {
    
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
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        add_action('wp_footer', array($this, 'render_popup'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function activate() {
        $defaults = array(
            'enabled' => 0,
            'form_action_url' => '',
            'hidden_tags' => '',
            'popup_title' => 'Subscribe to Our Newsletter',
            'popup_description' => 'Get the latest updates delivered straight to your inbox.',
            'submit_button_text' => 'Subscribe',
            'email_placeholder' => 'Enter your email address',
            'success_message' => 'Thanks for subscribing! Check your email to confirm.',
            'trigger_type' => 'time_delay',
            'time_delay' => 5,
            'scroll_percentage' => 50,
            'show_on' => 'all',
            'show_on_pages' => '',
            'exclude_pages' => '',
            'display_frequency' => 'once_per_session',
            'days_between' => 7,
            'bg_color' => '#ffffff',
            'text_color' => '#333333',
            'button_bg_color' => '#0073aa',
            'button_text_color' => '#ffffff',
            'overlay_color' => 'rgba(0,0,0,0.85)',
            'mobile_enabled' => 1
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('mcp_' . $key) === false) {
                add_option('mcp_' . $key, $value);
            }
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Mailchimp Popup',
            'MC Popup',
            'manage_options',
            'mailchimp-popup',
            array($this, 'render_settings_page'),
            'dashicons-email-alt',
            80
        );
    }
    
    public function register_settings() {
        $settings = array(
            'enabled', 'form_action_url', 'hidden_tags',
            'popup_title', 'popup_description', 'submit_button_text', 'email_placeholder', 'success_message',
            'trigger_type', 'time_delay', 'scroll_percentage',
            'show_on', 'show_on_pages', 'exclude_pages',
            'display_frequency', 'days_between',
            'bg_color', 'text_color', 'button_bg_color', 'button_text_color', 'overlay_color',
            'mobile_enabled'
        );
        
        foreach ($settings as $setting) {
            register_setting('mcp_settings', 'mcp_' . $setting);
        }
    }
    
    public function admin_scripts($hook) {
        if ($hook !== 'toplevel_page_mailchimp-popup') {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('mcp-admin', MCP_PLUGIN_URL . 'assets/css/admin.css', array(), MCP_VERSION);
        wp_enqueue_script('mcp-admin', MCP_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-color-picker'), MCP_VERSION, true);
    }
    
    public function frontend_scripts() {
        if (!get_option('mcp_enabled')) {
            return;
        }
        
        if (!get_option('mcp_mobile_enabled') && wp_is_mobile()) {
            return;
        }
        
        if (!$this->should_show_popup()) {
            return;
        }
        
        wp_enqueue_style('mcp-frontend', MCP_PLUGIN_URL . 'assets/css/frontend.css', array(), MCP_VERSION);
        wp_enqueue_script('mcp-frontend', MCP_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), MCP_VERSION, true);
        
        wp_localize_script('mcp-frontend', 'mcpData', array(
            'triggerType' => get_option('mcp_trigger_type', 'time_delay'),
            'timeDelay' => intval(get_option('mcp_time_delay', 5)) * 1000,
            'scrollPercentage' => intval(get_option('mcp_scroll_percentage', 50)),
            'displayFrequency' => get_option('mcp_display_frequency', 'once_per_session'),
            'daysBetween' => intval(get_option('mcp_days_between', 7)),
            'successMessage' => get_option('mcp_success_message', 'Thanks for subscribing!')
        ));
    }
    
    private function should_show_popup() {
        $show_on = get_option('mcp_show_on', 'all');
        $current_page_id = get_queried_object_id();
        
        // Exclude pages
        $exclude_pages = get_option('mcp_exclude_pages', '');
        if (!empty($exclude_pages)) {
            $excluded = array_map('trim', explode(',', $exclude_pages));
            if (in_array($current_page_id, $excluded)) {
                return false;
            }
        }
        
        if ($show_on === 'all') {
            return true;
        }
        
        if ($show_on === 'specific') {
            $show_pages = get_option('mcp_show_on_pages', '');
            if (!empty($show_pages)) {
                $pages = array_map('trim', explode(',', $show_pages));
                return in_array($current_page_id, $pages);
            }
            return false;
        }
        
        if ($show_on === 'homepage') {
            return is_front_page() || is_home();
        }
        
        if ($show_on === 'posts') {
            return is_single();
        }
        
        if ($show_on === 'pages') {
            return is_page();
        }
        
        return true;
    }
    
    public function render_popup() {
        if (!get_option('mcp_enabled')) {
            return;
        }
        
        if (!get_option('mcp_mobile_enabled') && wp_is_mobile()) {
            return;
        }
        
        if (!$this->should_show_popup()) {
            return;
        }
        
        $form_action = get_option('mcp_form_action_url');
        if (empty($form_action)) {
            return;
        }
        
        $hidden_tags = get_option('mcp_hidden_tags', '');
        
        // Inline styles from settings
        $styles = sprintf(
            '--mcp-bg: %s; --mcp-text: %s; --mcp-btn-bg: %s; --mcp-btn-text: %s; --mcp-overlay: %s;',
            esc_attr(get_option('mcp_bg_color', '#ffffff')),
            esc_attr(get_option('mcp_text_color', '#333333')),
            esc_attr(get_option('mcp_button_bg_color', '#0073aa')),
            esc_attr(get_option('mcp_button_text_color', '#ffffff')),
            esc_attr(get_option('mcp_overlay_color', 'rgba(0,0,0,0.85)'))
        );
        
        ?>
        <div id="mcp-overlay" class="mcp-overlay" style="<?php echo $styles; ?>">
            <div id="mcp-popup" class="mcp-popup">
                <div class="mcp-content">
                    <h2 class="mcp-title"><?php echo esc_html(get_option('mcp_popup_title')); ?></h2>
                    <p class="mcp-description"><?php echo esc_html(get_option('mcp_popup_description')); ?></p>
                    
                    <form id="mcp-form" class="mcp-form" action="<?php echo esc_url($form_action); ?>" method="post" target="_blank">
                        <div class="mcp-form-group">
                            <input type="email" name="EMAIL" placeholder="<?php echo esc_attr(get_option('mcp_email_placeholder', 'Enter your email address')); ?>" required class="mcp-input">
                        </div>
                        
                        <?php if (!empty($hidden_tags)): ?>
                            <input type="hidden" name="tags" value="<?php echo esc_attr($hidden_tags); ?>">
                        <?php endif; ?>
                        
                        <!-- Honeypot field -->
                        <div style="position: absolute; left: -5000px;" aria-hidden="true">
                            <input type="text" name="b_<?php echo esc_attr($this->extract_u_id($form_action)); ?>_<?php echo esc_attr($this->extract_list_id($form_action)); ?>" tabindex="-1" value="">
                        </div>
                        
                        <button type="submit" class="mcp-button">
                            <?php echo esc_html(get_option('mcp_submit_button_text', 'Subscribe')); ?>
                        </button>
                    </form>
                    
                    <div id="mcp-success" class="mcp-success" style="display: none;">
                        <div class="mcp-success-icon">✓</div>
                        <p><?php echo esc_html(get_option('mcp_success_message')); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function extract_u_id($url) {
        if (preg_match('/u=([a-f0-9]+)/', $url, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    private function extract_list_id($url) {
        if (preg_match('/id=([a-f0-9]+)/', $url, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap mcp-settings">
            <h1>📧 Mailchimp Popup Settings</h1>
            
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
                
                <!-- Mailchimp Form Settings -->
                <div class="mcp-card">
                    <h2>📝 Mailchimp Form</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Form Action URL *</th>
                            <td>
                                <input type="url" name="mcp_form_action_url" value="<?php echo esc_attr(get_option('mcp_form_action_url')); ?>" class="large-text" placeholder="https://xxxxx.us14.list-manage.com/subscribe/post?u=xxxxx&id=xxxxx">
                                <p class="description">
                                    Get this from Mailchimp: Audience → Signup forms → Embedded forms → Copy the <code>action=""</code> URL from the form
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Hidden Tags (optional)</th>
                            <td>
                                <input type="text" name="mcp_hidden_tags" value="<?php echo esc_attr(get_option('mcp_hidden_tags')); ?>" class="regular-text" placeholder="40202219">
                                <p class="description">Tag ID to automatically tag subscribers (find in your Mailchimp embed code: <code>name="tags" value="XXXXX"</code>)</p>
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
                        <tr>
                            <th scope="row">Email Placeholder</th>
                            <td>
                                <input type="text" name="mcp_email_placeholder" value="<?php echo esc_attr(get_option('mcp_email_placeholder')); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Button Text</th>
                            <td>
                                <input type="text" name="mcp_submit_button_text" value="<?php echo esc_attr(get_option('mcp_submit_button_text')); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Success Message</th>
                            <td>
                                <input type="text" name="mcp_success_message" value="<?php echo esc_attr(get_option('mcp_success_message')); ?>" class="large-text">
                                <p class="description">Shown after form submission (popup closes automatically)</p>
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
                                    <option value="immediate" <?php selected(get_option('mcp_trigger_type'), 'immediate'); ?>>Immediate (on page load)</option>
                                </select>
                            </td>
                        </tr>
                        <tr class="mcp-time-row">
                            <th scope="row">Time Delay</th>
                            <td>
                                <input type="number" name="mcp_time_delay" value="<?php echo esc_attr(get_option('mcp_time_delay', 5)); ?>" min="1" max="120" class="small-text"> seconds
                            </td>
                        </tr>
                        <tr class="mcp-scroll-row" style="display:none;">
                            <th scope="row">Scroll Percentage</th>
                            <td>
                                <input type="number" name="mcp_scroll_percentage" value="<?php echo esc_attr(get_option('mcp_scroll_percentage', 50)); ?>" min="10" max="100" class="small-text"> %
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
                                <p class="description">Comma-separated page IDs (only when "Specific Pages" selected)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Exclude Page IDs</th>
                            <td>
                                <input type="text" name="mcp_exclude_pages" value="<?php echo esc_attr(get_option('mcp_exclude_pages')); ?>" class="regular-text" placeholder="e.g., 5, 10">
                                <p class="description">Comma-separated page IDs to never show popup</p>
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
                                    <option value="once_ever" <?php selected(get_option('mcp_display_frequency'), 'once_ever'); ?>>Once ever (until subscribed or cookies cleared)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Days Between</th>
                            <td>
                                <input type="number" name="mcp_days_between" value="<?php echo esc_attr(get_option('mcp_days_between', 7)); ?>" min="1" max="365" class="small-text"> days
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
                            <th scope="row">Button Text</th>
                            <td>
                                <input type="text" name="mcp_button_text_color" value="<?php echo esc_attr(get_option('mcp_button_text_color', '#ffffff')); ?>" class="mcp-color-picker">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Overlay Color</th>
                            <td>
                                <input type="text" name="mcp_overlay_color" value="<?php echo esc_attr(get_option('mcp_overlay_color', 'rgba(0,0,0,0.85)')); ?>" class="regular-text" placeholder="rgba(0,0,0,0.85)">
                                <p class="description">Use rgba for transparency, e.g., rgba(0,0,0,0.85)</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <div class="mcp-card">
                <h2>ℹ️ How It Works</h2>
                <ul>
                    <li>✅ Popup appears based on your trigger settings</li>
                    <li>✅ <strong>No close button</strong> - users must submit to close</li>
                    <li>✅ After submission, success message shows and popup closes</li>
                    <li>✅ Form opens in new tab (Mailchimp confirmation), popup closes on current page</li>
                </ul>
            </div>
        </div>
        
        <style>
            .mcp-settings { max-width: 800px; }
            .mcp-card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .mcp-card h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .mcp-toggle { position: relative; display: inline-block; width: 50px; height: 26px; }
            .mcp-toggle input { opacity: 0; width: 0; height: 0; }
            .mcp-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 26px; }
            .mcp-toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
            .mcp-toggle input:checked + .mcp-toggle-slider { background-color: #00a32a; }
            .mcp-toggle input:checked + .mcp-toggle-slider:before { transform: translateX(24px); }
            .mcp-card ul { margin-left: 20px; }
            .mcp-card li { margin-bottom: 8px; }
        </style>
        <?php
    }
}

function MCP() {
    return Mailchimp_Popup::instance();
}
MCP();
