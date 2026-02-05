<?php
/**
 * Plugin Name: Mailchimp Popup Forms
 * Plugin URI: https://yoursite.com
 * Description: Display Mailchimp signup forms in popups - users must submit to close
 * Version: 1.4.3
 * Author: Bidview Marketing
 * Text Domain: mc-popup
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MCP_VERSION', '1.4.3');
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
        add_action('wp_ajax_mcp_get_posts', array($this, 'ajax_get_posts'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function activate() {
        $defaults = array(
            'enabled' => 0,
            'form_type' => 'mailchimp',
            'form_shortcode' => '',
            'form_action_url' => '',
            'hidden_tags' => '',
            'popup_title' => 'Subscribe to Our Newsletter',
            'popup_description' => 'Get the latest updates delivered straight to your inbox.',
            'submit_button_text' => 'Subscribe',
            'email_placeholder' => 'Enter your email address',
            'success_message' => 'Thanks for subscribing! Check your email to confirm.',
            'redirect_url' => '',
            'redirect_delay' => 2,
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
            'mobile_enabled' => 1,
            'test_mode' => 0,
            // New settings v1.2.0
            'popup_width' => 500,
            'popup_max_width' => 90,
            'blur_background' => 0,
            'blur_amount' => 5,
            'font_family' => '',
            'title_font_size' => 28,
            'description_font_size' => 16,
            'custom_css' => '',
            'border_color' => '',
            'border_width' => 0,
            'border_radius' => 8,
            'button_border_radius' => 4,
            'input_border_radius' => 4
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
            'enabled', 'form_type', 'form_shortcode', 'form_action_url', 'hidden_tags',
            'popup_title', 'popup_description', 'submit_button_text', 'email_placeholder', 'success_message', 'redirect_url', 'redirect_delay',
            'trigger_type', 'time_delay', 'scroll_percentage',
            'show_on', 'show_on_pages', 'exclude_pages',
            'display_frequency', 'days_between',
            'bg_color', 'text_color', 'button_bg_color', 'button_text_color', 'overlay_color',
            'mobile_enabled', 'test_mode',
            // New settings v1.2.0
            'popup_width', 'popup_max_width', 'blur_background', 'blur_amount',
            'font_family', 'title_font_size', 'description_font_size',
            'custom_css', 'border_color', 'border_width', 'border_radius',
            'button_border_radius', 'input_border_radius'
        );
        
        foreach ($settings as $setting) {
            register_setting('mcp_settings', 'mcp_' . $setting);
        }
    }
    
    public function admin_scripts($hook) {
        if ($hook !== 'toplevel_page_mailchimp-popup') {
            return;
        }
        
        // Enqueue Select2 for multi-select dropdowns
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('mcp-admin', MCP_PLUGIN_URL . 'assets/css/admin.css', array(), MCP_VERSION);
        wp_enqueue_script('mcp-admin', MCP_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-color-picker', 'select2'), MCP_VERSION, true);
        
        wp_localize_script('mcp-admin', 'mcpAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mcp_admin_nonce')
        ));
    }
    
    public function ajax_get_posts() {
        check_ajax_referer('mcp_admin_nonce', 'nonce');
        
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'page';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $posts = get_posts($args);
        $results = array();
        
        foreach ($posts as $post) {
            $results[] = array(
                'id' => $post->ID,
                'text' => $post->post_title . ' (ID: ' . $post->ID . ')'
            );
        }
        
        wp_send_json_success($results);
    }
    
    private function get_elementor_fonts() {
        // Try to get Elementor kit fonts
        $fonts = array();
        
        // Check if Elementor is active
        if (defined('ELEMENTOR_VERSION')) {
            // Get active kit settings
            $kit_id = get_option('elementor_active_kit');
            if ($kit_id) {
                $kit_settings = get_post_meta($kit_id, '_elementor_page_settings', true);
                if (!empty($kit_settings)) {
                    // Primary font
                    if (!empty($kit_settings['system_typography_1_typography_font_family'])) {
                        $fonts['primary'] = $kit_settings['system_typography_1_typography_font_family'];
                    }
                    // Secondary font
                    if (!empty($kit_settings['system_typography_2_typography_font_family'])) {
                        $fonts['secondary'] = $kit_settings['system_typography_2_typography_font_family'];
                    }
                    // Body font
                    if (!empty($kit_settings['body_typography_font_family'])) {
                        $fonts['body'] = $kit_settings['body_typography_font_family'];
                    }
                }
            }
            
            // Get from global fonts too
            $global_fonts = get_option('elementor_fonts_manager', array());
            if (!empty($global_fonts)) {
                foreach ($global_fonts as $font) {
                    if (!empty($font['font_family'])) {
                        $fonts[] = $font['font_family'];
                    }
                }
            }
        }
        
        // Add common web fonts as fallback
        $common_fonts = array(
            'System Default' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            'Georgia (Serif)' => 'Georgia, "Times New Roman", Times, serif',
            'Playfair Display' => '"Playfair Display", Georgia, serif',
            'Merriweather' => 'Merriweather, Georgia, serif',
            'Lora' => 'Lora, Georgia, serif',
            'Montserrat' => 'Montserrat, Arial, sans-serif',
            'Open Sans' => '"Open Sans", Arial, sans-serif',
            'Roboto' => 'Roboto, Arial, sans-serif',
            'Poppins' => 'Poppins, Arial, sans-serif',
            'Lato' => 'Lato, Arial, sans-serif'
        );
        
        return array_merge($fonts, $common_fonts);
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
        
        // Add custom font if using Google Font
        $font_family = get_option('mcp_font_family', '');
        if (!empty($font_family) && strpos($font_family, 'Playfair') !== false) {
            wp_enqueue_style('mcp-google-fonts', 'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&display=swap', array(), null);
        }
        
        wp_localize_script('mcp-frontend', 'mcpData', array(
            'triggerType' => get_option('mcp_trigger_type', 'time_delay'),
            'timeDelay' => intval(get_option('mcp_time_delay', 5)) * 1000,
            'scrollPercentage' => intval(get_option('mcp_scroll_percentage', 50)),
            'displayFrequency' => get_option('mcp_display_frequency', 'once_per_session'),
            'daysBetween' => intval(get_option('mcp_days_between', 7)),
            'successMessage' => get_option('mcp_success_message', 'Thanks for subscribing!'),
            'blurBackground' => get_option('mcp_blur_background', 0),
            'testMode' => get_option('mcp_test_mode', 0),
            'redirectUrl' => get_option('mcp_redirect_url', ''),
            'redirectDelay' => intval(get_option('mcp_redirect_delay', 2)),
            'formType' => get_option('mcp_form_type', 'mailchimp')
        ));
    }
    
    private function should_show_popup() {
        $show_on = get_option('mcp_show_on', 'all');
        $current_page_id = get_queried_object_id();
        
        // Debug logging (remove in production)
        // error_log('MCP Debug: show_on=' . $show_on . ', current_page_id=' . $current_page_id);
        
        // Exclude pages - compare as integers
        $exclude_pages = get_option('mcp_exclude_pages', '');
        if (!empty($exclude_pages)) {
            $excluded = array_map('intval', array_filter(array_map('trim', explode(',', $exclude_pages))));
            if (in_array((int)$current_page_id, $excluded, true)) {
                return false;
            }
        }
        
        if ($show_on === 'all') {
            return true;
        }
        
        if ($show_on === 'specific') {
            $show_pages = get_option('mcp_show_on_pages', '');
            // error_log('MCP Debug: show_pages=' . $show_pages);
            if (!empty($show_pages)) {
                $pages = array_map('intval', array_filter(array_map('trim', explode(',', $show_pages))));
                // error_log('MCP Debug: pages array=' . print_r($pages, true));
                return in_array((int)$current_page_id, $pages, true);
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
        
        $form_type = get_option('mcp_form_type', 'mailchimp');
        $form_shortcode = get_option('mcp_form_shortcode', '');
        $form_action = get_option('mcp_form_action_url');
        
        // Validate based on form type
        if ($form_type === 'mailchimp' && empty($form_action)) {
            return;
        }
        if ($form_type === 'shortcode' && empty($form_shortcode)) {
            return;
        }
        
        $hidden_tags = get_option('mcp_hidden_tags', '');
        $blur_bg = get_option('mcp_blur_background', 0);
        $blur_amount = get_option('mcp_blur_amount', 5);
        $popup_width = get_option('mcp_popup_width', 500);
        $popup_max_width = get_option('mcp_popup_max_width', 90);
        $font_family = get_option('mcp_font_family', '');
        $title_font_size = get_option('mcp_title_font_size', 28);
        $desc_font_size = get_option('mcp_description_font_size', 16);
        $border_color = get_option('mcp_border_color', '');
        $border_width = get_option('mcp_border_width', 0);
        $border_radius = get_option('mcp_border_radius', 8);
        $button_radius = get_option('mcp_button_border_radius', 4);
        $input_radius = get_option('mcp_input_border_radius', 4);
        $custom_css = get_option('mcp_custom_css', '');
        
        // Build inline styles
        $styles = sprintf(
            '--mcp-bg: %s; --mcp-text: %s; --mcp-btn-bg: %s; --mcp-btn-text: %s; --mcp-overlay: %s; --mcp-width: %spx; --mcp-max-width: %s%%; --mcp-border-radius: %spx; --mcp-btn-radius: %spx; --mcp-input-radius: %spx; --mcp-title-size: %spx; --mcp-desc-size: %spx;',
            esc_attr(get_option('mcp_bg_color', '#ffffff')),
            esc_attr(get_option('mcp_text_color', '#333333')),
            esc_attr(get_option('mcp_button_bg_color', '#0073aa')),
            esc_attr(get_option('mcp_button_text_color', '#ffffff')),
            esc_attr(get_option('mcp_overlay_color', 'rgba(0,0,0,0.85)')),
            esc_attr($popup_width),
            esc_attr($popup_max_width),
            esc_attr($border_radius),
            esc_attr($button_radius),
            esc_attr($input_radius),
            esc_attr($title_font_size),
            esc_attr($desc_font_size)
        );
        
        if (!empty($border_color) && $border_width > 0) {
            $styles .= sprintf(' --mcp-border: %spx solid %s;', esc_attr($border_width), esc_attr($border_color));
        }
        
        if (!empty($font_family)) {
            $styles .= sprintf(' --mcp-font: %s;', esc_attr($font_family));
        }
        
        if ($blur_bg) {
            $styles .= sprintf(' --mcp-blur: %spx;', esc_attr($blur_amount));
        }
        
        ?>
        <div id="mcp-overlay" class="mcp-overlay<?php echo $blur_bg ? ' mcp-blur-bg' : ''; ?>" style="<?php echo $styles; ?>" data-blur="<?php echo $blur_bg ? '1' : '0'; ?>">
            <div id="mcp-popup" class="mcp-popup">
                <div class="mcp-content">
                    <h2 class="mcp-title"><?php echo esc_html(get_option('mcp_popup_title')); ?></h2>
                    <p class="mcp-description"><?php echo esc_html(get_option('mcp_popup_description')); ?></p>
                    
                    <?php if ($form_type === 'shortcode' && !empty($form_shortcode)): ?>
                        <!-- Shortcode Form (Gravity Forms, WPForms, etc.) -->
                        <div id="mcp-shortcode-form" class="mcp-shortcode-form">
                            <?php echo do_shortcode($form_shortcode); ?>
                        </div>
                    <?php else: ?>
                        <!-- Built-in Mailchimp Form -->
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($custom_css)): ?>
        <style id="mcp-custom-css">
            <?php echo wp_strip_all_tags($custom_css); ?>
        </style>
        <?php endif; ?>
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
    
    private function get_all_pages_posts() {
        $items = array(
            'pages' => array(),
            'posts' => array()
        );
        
        // Get pages
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        foreach ($pages as $page) {
            $items['pages'][$page->ID] = $page->post_title;
        }
        
        // Get posts
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        foreach ($posts as $post) {
            $items['posts'][$post->ID] = $post->post_title;
        }
        
        return $items;
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $all_items = $this->get_all_pages_posts();
        $fonts = $this->get_elementor_fonts();
        $current_show_pages = array_filter(array_map('trim', explode(',', get_option('mcp_show_on_pages', ''))));
        $current_exclude_pages = array_filter(array_map('trim', explode(',', get_option('mcp_exclude_pages', ''))));
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
                        <tr>
                            <th scope="row">🧪 Test Mode</th>
                            <td>
                                <label class="mcp-toggle">
                                    <input type="checkbox" name="mcp_test_mode" value="1" <?php checked(get_option('mcp_test_mode'), 1); ?>>
                                    <span class="mcp-toggle-slider mcp-toggle-test"></span>
                                </label>
                                <p class="description"><strong>Ignores all cookies</strong> - popup shows every time for testing. <span style="color:#d63638;">Turn off for production!</span></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Form Settings -->
                <div class="mcp-card">
                    <h2>📝 Form Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Form Type</th>
                            <td>
                                <select name="mcp_form_type" id="mcp_form_type">
                                    <option value="mailchimp" <?php selected(get_option('mcp_form_type', 'mailchimp'), 'mailchimp'); ?>>Mailchimp (built-in form)</option>
                                    <option value="shortcode" <?php selected(get_option('mcp_form_type'), 'shortcode'); ?>>Shortcode (Gravity Forms, WPForms, etc.)</option>
                                </select>
                                <p class="description">Choose built-in Mailchimp form or embed any form via shortcode</p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Shortcode Settings -->
                    <div id="mcp-shortcode-settings" style="<?php echo get_option('mcp_form_type') !== 'shortcode' ? 'display:none;' : ''; ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Form Shortcode *</th>
                                <td>
                                    <input type="text" name="mcp_form_shortcode" value="<?php echo esc_attr(get_option('mcp_form_shortcode')); ?>" class="large-text" placeholder='[gravityform id="1" title="false" description="false" ajax="true"]'>
                                    <p class="description">
                                        <strong>Gravity Forms:</strong> <code>[gravityform id="1" title="false" description="false" ajax="true"]</code><br>
                                        <strong>WPForms:</strong> <code>[wpforms id="123"]</code><br>
                                        <strong>Contact Form 7:</strong> <code>[contact-form-7 id="123"]</code>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Mailchimp Settings -->
                    <div id="mcp-mailchimp-settings" style="<?php echo get_option('mcp_form_type') === 'shortcode' ? 'display:none;' : ''; ?>">
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
                                <p class="description">Shown after form submission</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Redirect URL (optional)</th>
                            <td>
                                <input type="url" name="mcp_redirect_url" value="<?php echo esc_attr(get_option('mcp_redirect_url')); ?>" class="large-text" placeholder="https://example.com/thank-you/">
                                <p class="description">Redirect to this URL after submission. Leave empty to just close the popup.</p>
                            </td>
                        </tr>
                        <tr class="mcp-redirect-row">
                            <th scope="row">Redirect Delay</th>
                            <td>
                                <input type="number" name="mcp_redirect_delay" value="<?php echo esc_attr(get_option('mcp_redirect_delay', 2)); ?>" min="0" max="10" class="small-text"> seconds
                                <p class="description">How long to show success message before redirecting (0 = immediate)</p>
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
                                <select name="mcp_show_on" id="mcp_show_on">
                                    <option value="all" <?php selected(get_option('mcp_show_on'), 'all'); ?>>All Pages</option>
                                    <option value="homepage" <?php selected(get_option('mcp_show_on'), 'homepage'); ?>>Homepage Only</option>
                                    <option value="posts" <?php selected(get_option('mcp_show_on'), 'posts'); ?>>Blog Posts Only</option>
                                    <option value="pages" <?php selected(get_option('mcp_show_on'), 'pages'); ?>>Pages Only</option>
                                    <option value="specific" <?php selected(get_option('mcp_show_on'), 'specific'); ?>>Specific Pages/Posts</option>
                                </select>
                            </td>
                        </tr>
                        <tr class="mcp-specific-pages-row">
                            <th scope="row">Select Pages/Posts to Show</th>
                            <td>
                                <select name="mcp_show_on_pages_select[]" id="mcp_show_on_pages_select" class="mcp-select2" multiple="multiple" style="width: 100%;">
                                    <optgroup label="📄 Pages">
                                        <?php foreach ($all_items['pages'] as $id => $title): ?>
                                            <option value="<?php echo esc_attr($id); ?>" <?php echo in_array($id, $current_show_pages) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($title); ?> (ID: <?php echo $id; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="📰 Posts">
                                        <?php foreach ($all_items['posts'] as $id => $title): ?>
                                            <option value="<?php echo esc_attr($id); ?>" <?php echo in_array($id, $current_show_pages) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($title); ?> (ID: <?php echo $id; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                                <p class="description" style="margin-top:5px;">Search and select from dropdown above <strong>OR</strong> type IDs manually below:</p>
                                <input type="text" name="mcp_show_on_pages" id="mcp_show_on_pages" value="<?php echo esc_attr(get_option('mcp_show_on_pages')); ?>" class="regular-text" placeholder="e.g., 8798, 8799, 8800" style="margin-top:8px;">
                                <p class="description">Comma-separated page/post IDs (manual entry syncs with dropdown)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Exclude Pages/Posts</th>
                            <td>
                                <select name="mcp_exclude_pages_select[]" id="mcp_exclude_pages_select" class="mcp-select2" multiple="multiple" style="width: 100%;">
                                    <optgroup label="📄 Pages">
                                        <?php foreach ($all_items['pages'] as $id => $title): ?>
                                            <option value="<?php echo esc_attr($id); ?>" <?php echo in_array($id, $current_exclude_pages) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($title); ?> (ID: <?php echo $id; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="📰 Posts">
                                        <?php foreach ($all_items['posts'] as $id => $title): ?>
                                            <option value="<?php echo esc_attr($id); ?>" <?php echo in_array($id, $current_exclude_pages) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($title); ?> (ID: <?php echo $id; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                                <p class="description" style="margin-top:5px;">Select from dropdown <strong>OR</strong> type IDs manually:</p>
                                <input type="text" name="mcp_exclude_pages" id="mcp_exclude_pages" value="<?php echo esc_attr(get_option('mcp_exclude_pages')); ?>" class="regular-text" placeholder="e.g., 5, 10" style="margin-top:8px;">
                                <p class="description">Pages/posts to NEVER show the popup on</p>
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
                            <th scope="row">Popup Width</th>
                            <td>
                                <input type="number" name="mcp_popup_width" value="<?php echo esc_attr(get_option('mcp_popup_width', 500)); ?>" min="300" max="1200" class="small-text"> px
                                <p class="description">Width of the popup (default: 500px)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Max Width (Mobile)</th>
                            <td>
                                <input type="number" name="mcp_popup_max_width" value="<?php echo esc_attr(get_option('mcp_popup_max_width', 90)); ?>" min="50" max="100" class="small-text"> %
                                <p class="description">Maximum width as percentage of screen (for responsive)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Blur Background</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="mcp_blur_background" value="1" <?php checked(get_option('mcp_blur_background'), 1); ?>>
                                    Blur page content behind overlay (hides text)
                                </label>
                            </td>
                        </tr>
                        <tr class="mcp-blur-row">
                            <th scope="row">Blur Amount</th>
                            <td>
                                <input type="number" name="mcp_blur_amount" value="<?php echo esc_attr(get_option('mcp_blur_amount', 5)); ?>" min="1" max="20" class="small-text"> px
                                <p class="description">How much to blur (5-10px is usually good)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Font Family</th>
                            <td>
                                <select name="mcp_font_family" id="mcp_font_family">
                                    <option value="">— Use Site Default —</option>
                                    <?php 
                                    $current_font = get_option('mcp_font_family', '');
                                    foreach ($fonts as $name => $value): 
                                        if (is_numeric($name)) {
                                            $name = $value;
                                        }
                                    ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($current_font, $value); ?>>
                                            <?php echo esc_html($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (defined('ELEMENTOR_VERSION')): ?>
                                    <p class="description">✅ Elementor detected - your global fonts are included above</p>
                                <?php else: ?>
                                    <p class="description">Select a font for the popup (install Elementor to auto-detect site fonts)</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Title Font Size</th>
                            <td>
                                <input type="number" name="mcp_title_font_size" value="<?php echo esc_attr(get_option('mcp_title_font_size', 28)); ?>" min="14" max="72" class="small-text"> px
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Description Font Size</th>
                            <td>
                                <input type="number" name="mcp_description_font_size" value="<?php echo esc_attr(get_option('mcp_description_font_size', 16)); ?>" min="12" max="36" class="small-text"> px
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
                            <th scope="row">Button Text</th>
                            <td>
                                <input type="text" name="mcp_button_text_color" value="<?php echo esc_attr(get_option('mcp_button_text_color', '#ffffff')); ?>" class="mcp-color-picker">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Border Color</th>
                            <td>
                                <input type="text" name="mcp_border_color" value="<?php echo esc_attr(get_option('mcp_border_color', '')); ?>" class="mcp-color-picker">
                                <p class="description">Leave empty for no border</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Border Width</th>
                            <td>
                                <input type="number" name="mcp_border_width" value="<?php echo esc_attr(get_option('mcp_border_width', 0)); ?>" min="0" max="20" class="small-text"> px
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Popup Corner Radius</th>
                            <td>
                                <input type="number" name="mcp_border_radius" value="<?php echo esc_attr(get_option('mcp_border_radius', 8)); ?>" min="0" max="50" class="small-text"> px
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Button Corner Radius</th>
                            <td>
                                <input type="number" name="mcp_button_border_radius" value="<?php echo esc_attr(get_option('mcp_button_border_radius', 4)); ?>" min="0" max="50" class="small-text"> px
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Input Corner Radius</th>
                            <td>
                                <input type="number" name="mcp_input_border_radius" value="<?php echo esc_attr(get_option('mcp_input_border_radius', 4)); ?>" min="0" max="50" class="small-text"> px
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
                
                <!-- Custom CSS -->
                <div class="mcp-card">
                    <h2>🖌️ Custom CSS</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Custom CSS</th>
                            <td>
                                <textarea name="mcp_custom_css" id="mcp_custom_css" rows="12" class="large-text code" placeholder="/* Add your custom CSS here */
.mcp-popup {
    /* your styles */
}
.mcp-title {
    /* title styles */
}
.mcp-button {
    /* button styles */
}"><?php echo esc_textarea(get_option('mcp_custom_css', '')); ?></textarea>
                                <p class="description">
                                    <strong>Available CSS selectors:</strong><br>
                                    <code>.mcp-overlay</code> - Full screen overlay<br>
                                    <code>.mcp-popup</code> - Popup container<br>
                                    <code>.mcp-content</code> - Content wrapper<br>
                                    <code>.mcp-title</code> - Heading<br>
                                    <code>.mcp-description</code> - Description text<br>
                                    <code>.mcp-input</code> - Email input field<br>
                                    <code>.mcp-button</code> - Submit button
                                </p>
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
            .mcp-settings { max-width: 900px; }
            .mcp-card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .mcp-card h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .mcp-toggle { position: relative; display: inline-block; width: 50px; height: 26px; }
            .mcp-toggle input { opacity: 0; width: 0; height: 0; }
            .mcp-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 26px; }
            .mcp-toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
            .mcp-toggle input:checked + .mcp-toggle-slider { background-color: #00a32a; }
            .mcp-toggle input:checked + .mcp-toggle-slider:before { transform: translateX(24px); }
            .mcp-toggle input:checked + .mcp-toggle-slider.mcp-toggle-test { background-color: #dba617; }
            .mcp-card ul { margin-left: 20px; }
            .mcp-card li { margin-bottom: 8px; }
            textarea.code { font-family: monospace; font-size: 13px; }
            .select2-container { margin-top: 5px; }
            .select2-container--default .select2-selection--multiple { border-color: #8c8f94; min-height: 40px; }
        </style>
        <?php
    }
}

function MCP() {
    return Mailchimp_Popup::instance();
}
MCP();
