<?php
/**
 * Plugin Name: Mailchimp Popup Forms
 * Plugin URI: https://yoursite.com
 * Description: Display Mailchimp signup forms in customizable popups with exit intent, time delay, and scroll triggers
 * Version: 1.0.0
 * Author: Bidview Marketing
 * Text Domain: mc-popup
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MCP_VERSION', '1.0.0');
define('MCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MCP_PLUGIN_FILE', __FILE__);

/**
 * Main Plugin Class
 */
class Mailchimp_Popup {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Load files
        $this->load_files();
        
        // Initialize
        add_action('init', array($this, 'init'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        add_action('wp_footer', array($this, 'render_popup'));
        
        // AJAX
        add_action('wp_ajax_mcp_subscribe', array($this, 'ajax_subscribe'));
        add_action('wp_ajax_nopriv_mcp_subscribe', array($this, 'ajax_subscribe'));
        add_action('wp_ajax_mcp_dismiss', array($this, 'ajax_dismiss'));
        add_action('wp_ajax_nopriv_mcp_dismiss', array($this, 'ajax_dismiss'));
        
        // Activation
        register_activation_hook(MCP_PLUGIN_FILE, array($this, 'activate'));
    }
    
    private function load_files() {
        require_once MCP_PLUGIN_DIR . 'includes/class-mailchimp-api.php';
    }
    
    public function init() {
        load_plugin_textdomain('mc-popup', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
        // Set default options
        $defaults = array(
            'enabled' => 0,
            'mailchimp_api_key' => '',
            'mailchimp_list_id' => '',
            'use_embed_code' => 0,
            'embed_code' => '',
            'popup_title' => 'Subscribe to Our Newsletter',
            'popup_description' => 'Get the latest updates delivered straight to your inbox.',
            'submit_button_text' => 'Subscribe',
            'success_message' => 'Thanks for subscribing!',
            'trigger_type' => 'time_delay',
            'time_delay' => 5,
            'scroll_percentage' => 50,
            'exit_intent' => 0,
            'show_on' => 'all',
            'show_on_pages' => '',
            'exclude_pages' => '',
            'display_frequency' => 'once_per_session',
            'days_between' => 7,
            'bg_color' => '#ffffff',
            'text_color' => '#333333',
            'button_bg_color' => '#0073aa',
            'button_text_color' => '#ffffff',
            'overlay_color' => 'rgba(0,0,0,0.6)',
            'position' => 'center',
            'animation' => 'fade',
            'show_close_button' => 1,
            'close_on_overlay' => 1,
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
            'enabled', 'mailchimp_api_key', 'mailchimp_list_id', 'use_embed_code', 'embed_code',
            'popup_title', 'popup_description', 'submit_button_text', 'success_message',
            'trigger_type', 'time_delay', 'scroll_percentage', 'exit_intent',
            'show_on', 'show_on_pages', 'exclude_pages',
            'display_frequency', 'days_between',
            'bg_color', 'text_color', 'button_bg_color', 'button_text_color', 'overlay_color',
            'position', 'animation', 'show_close_button', 'close_on_overlay', 'mobile_enabled'
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
        
        // Check if should show on mobile
        if (!get_option('mcp_mobile_enabled') && wp_is_mobile()) {
            return;
        }
        
        // Check page rules
        if (!$this->should_show_popup()) {
            return;
        }
        
        wp_enqueue_style('mcp-frontend', MCP_PLUGIN_URL . 'assets/css/frontend.css', array(), MCP_VERSION);
        wp_enqueue_script('mcp-frontend', MCP_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), MCP_VERSION, true);
        
        wp_localize_script('mcp-frontend', 'mcpData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mcp_nonce'),
            'triggerType' => get_option('mcp_trigger_type', 'time_delay'),
            'timeDelay' => intval(get_option('mcp_time_delay', 5)) * 1000,
            'scrollPercentage' => intval(get_option('mcp_scroll_percentage', 50)),
            'exitIntent' => get_option('mcp_exit_intent', 0),
            'displayFrequency' => get_option('mcp_display_frequency', 'once_per_session'),
            'daysBetween' => intval(get_option('mcp_days_between', 7)),
            'animation' => get_option('mcp_animation', 'fade'),
            'closeOnOverlay' => get_option('mcp_close_on_overlay', 1),
            'position' => get_option('mcp_position', 'center')
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
        
        $use_embed = get_option('mcp_use_embed_code');
        $position = get_option('mcp_position', 'center');
        
        // Inline styles from settings
        $styles = sprintf(
            '--mcp-bg: %s; --mcp-text: %s; --mcp-btn-bg: %s; --mcp-btn-text: %s; --mcp-overlay: %s;',
            esc_attr(get_option('mcp_bg_color', '#ffffff')),
            esc_attr(get_option('mcp_text_color', '#333333')),
            esc_attr(get_option('mcp_button_bg_color', '#0073aa')),
            esc_attr(get_option('mcp_button_text_color', '#ffffff')),
            esc_attr(get_option('mcp_overlay_color', 'rgba(0,0,0,0.6)'))
        );
        
        ?>
        <div id="mcp-overlay" class="mcp-overlay" style="<?php echo $styles; ?>">
            <div id="mcp-popup" class="mcp-popup mcp-position-<?php echo esc_attr($position); ?>">
                <?php if (get_option('mcp_show_close_button', 1)): ?>
                    <button type="button" class="mcp-close" aria-label="Close">&times;</button>
                <?php endif; ?>
                
                <div class="mcp-content">
                    <h2 class="mcp-title"><?php echo esc_html(get_option('mcp_popup_title')); ?></h2>
                    <p class="mcp-description"><?php echo esc_html(get_option('mcp_popup_description')); ?></p>
                    
                    <?php if ($use_embed && get_option('mcp_embed_code')): ?>
                        <div class="mcp-embed-form">
                            <?php echo get_option('mcp_embed_code'); ?>
                        </div>
                        <div class="mcp-no-thanks">
                            <a href="#" class="mcp-dismiss-link">No thanks, close this</a>
                        </div>
                    <?php else: ?>
                        <form id="mcp-form" class="mcp-form">
                            <div class="mcp-form-group">
                                <input type="email" name="email" placeholder="Enter your email" required class="mcp-input">
                            </div>
                            <button type="submit" class="mcp-button">
                                <?php echo esc_html(get_option('mcp_submit_button_text', 'Subscribe')); ?>
                            </button>
                        </form>
                        <div id="mcp-message" class="mcp-message" style="display: none;"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_subscribe() {
        check_ajax_referer('mcp_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }
        
        $api_key = get_option('mcp_mailchimp_api_key');
        $list_id = get_option('mcp_mailchimp_list_id');
        
        if (empty($api_key) || empty($list_id)) {
            wp_send_json_error(array('message' => 'Mailchimp is not configured. Please contact the site administrator.'));
        }
        
        $mailchimp = new MCP_Mailchimp_API($api_key);
        $result = $mailchimp->subscribe($list_id, $email);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => get_option('mcp_success_message', 'Thanks for subscribing!')
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    public function ajax_dismiss() {
        check_ajax_referer('mcp_nonce', 'nonce');
        wp_send_json_success();
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Test API connection if requested
        $api_test_result = null;
        if (isset($_GET['test_api']) && $_GET['test_api'] === '1') {
            $api_key = get_option('mcp_mailchimp_api_key');
            if ($api_key) {
                $mailchimp = new MCP_Mailchimp_API($api_key);
                $api_test_result = $mailchimp->test_connection();
            }
        }
        
        // Get lists if API key is set
        $lists = array();
        $api_key = get_option('mcp_mailchimp_api_key');
        if ($api_key) {
            $mailchimp = new MCP_Mailchimp_API($api_key);
            $lists = $mailchimp->get_lists();
        }
        
        include MCP_PLUGIN_DIR . 'includes/settings-page.php';
    }
}

// Initialize
function MCP() {
    return Mailchimp_Popup::instance();
}
MCP();
