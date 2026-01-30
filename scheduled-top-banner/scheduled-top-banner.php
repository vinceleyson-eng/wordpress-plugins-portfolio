<?php
/**
 * Plugin Name: Scheduled Top Banner
 * Plugin URI: https://yourwebsite.com
 * Description: Display a customizable announcement banner above your header with scheduling capabilities. Perfect for promotions, announcements, and time-limited offers.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: scheduled-top-banner
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('STB_VERSION', '1.0.0');
define('STB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STB_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class Scheduled_Top_Banner {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Plugin options
     */
    private $options;
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->options = get_option('stb_settings', $this->get_default_options());
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Frontend hooks
        add_action('wp_head', array($this, 'output_banner_styles'), 1);
        add_action('wp_body_open', array($this, 'display_banner'), 1);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // AJAX hooks
        add_action('wp_ajax_stb_dismiss_banner', array($this, 'dismiss_banner'));
        add_action('wp_ajax_nopriv_stb_dismiss_banner', array($this, 'dismiss_banner'));
        
        // Analytics AJAX hooks
        add_action('wp_ajax_stb_track_click', array($this, 'track_click'));
        add_action('wp_ajax_nopriv_stb_track_click', array($this, 'track_click'));
    }
    
    /**
     * Create analytics table
     */
    public static function create_analytics_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stb_analytics';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            click_date date NOT NULL,
            click_count int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY click_date (click_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Track banner link click
     */
    public function track_click() {
        check_ajax_referer('stb_click_nonce', 'nonce');
        
        if (empty($this->options['track_clicks'])) {
            wp_send_json_error('Tracking disabled');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'stb_analytics';
        $today = current_time('Y-m-d');
        
        // Try to update existing row for today
        $updated = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_name (click_date, click_count) VALUES (%s, 1)
             ON DUPLICATE KEY UPDATE click_count = click_count + 1",
            $today
        ));
        
        wp_send_json_success();
    }
    
    /**
     * Get analytics data
     */
    public function get_analytics_data($period = 'month') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stb_analytics';
        
        switch ($period) {
            case 'today':
                $start_date = current_time('Y-m-d');
                break;
            case 'week':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'year':
                $start_date = date('Y-m-d', strtotime('-365 days'));
                break;
            case 'all':
                $start_date = '1970-01-01';
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT click_date, click_count FROM $table_name WHERE click_date >= %s ORDER BY click_date DESC",
            $start_date
        ));
        
        $total_clicks = 0;
        $daily_data = array();
        
        foreach ($results as $row) {
            $total_clicks += $row->click_count;
            $daily_data[$row->click_date] = $row->click_count;
        }
        
        return array(
            'total' => $total_clicks,
            'daily' => $daily_data
        );
    }
    
    /**
     * Get default options
     */
    private function get_default_options() {
        return array(
            'enabled' => 0,
            'banner_text' => '🎉 Check out our latest offer! <a href="#">Learn More</a>',
            'link_new_tab' => 1,
            'start_date' => '',
            'start_time' => '00:00',
            'end_date' => '',
            'end_time' => '23:59',
            'bg_color' => '#1a73e8',
            'text_color' => '#ffffff',
            'link_color' => '#ffffff',
            // Tablet colors (max-width: 1024px)
            'tablet_bg_color' => '',
            'tablet_text_color' => '',
            'tablet_link_color' => '',
            // Mobile colors (max-width: 767px)
            'mobile_bg_color' => '',
            'mobile_text_color' => '',
            'mobile_link_color' => '',
            'font_size' => '14',
            'padding' => '12',
            'dismissible' => 1,
            'dismiss_duration' => '24',
            'show_on_mobile' => 1,
            'sticky' => 0,
            'track_clicks' => 1,
            // Display conditions
            'display_mode' => 'all', // 'all', 'include', 'exclude'
            'show_on_homepage' => 1,
            'show_on_blog' => 1,
            'show_on_posts' => 1,
            'show_on_pages' => 1,
            'show_on_archives' => 1,
            'show_on_search' => 1,
            'show_on_404' => 0,
            'specific_pages' => array(),
            'specific_posts' => array(),
            'specific_post_types' => array(),
            'specific_taxonomies' => array(),
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Scheduled Top Banner', 'scheduled-top-banner'),
            __('Top Banner', 'scheduled-top-banner'),
            'manage_options',
            'scheduled-top-banner',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'stb_settings_group',
            'stb_settings',
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['enabled'] = isset($input['enabled']) ? 1 : 0;
        
        // Allow safe HTML in banner text (links, bold, italic, etc.)
        $allowed_html = array(
            'a' => array(
                'href' => array(),
                'title' => array(),
                'target' => array(),
                'rel' => array(),
                'class' => array(),
            ),
            'strong' => array(),
            'b' => array(),
            'em' => array(),
            'i' => array(),
            'span' => array(
                'class' => array(),
                'style' => array(),
            ),
            'br' => array(),
        );
        $sanitized['banner_text'] = wp_kses($input['banner_text'], $allowed_html);
        
        $sanitized['link_new_tab'] = isset($input['link_new_tab']) ? 1 : 0;
        $sanitized['start_date'] = sanitize_text_field($input['start_date']);
        $sanitized['start_time'] = sanitize_text_field($input['start_time']);
        $sanitized['end_date'] = sanitize_text_field($input['end_date']);
        $sanitized['end_time'] = sanitize_text_field($input['end_time']);
        $sanitized['bg_color'] = sanitize_hex_color($input['bg_color']);
        $sanitized['text_color'] = sanitize_hex_color($input['text_color']);
        $sanitized['link_color'] = sanitize_hex_color($input['link_color']);
        // Tablet colors
        $sanitized['tablet_bg_color'] = !empty($input['tablet_bg_color']) ? sanitize_hex_color($input['tablet_bg_color']) : '';
        $sanitized['tablet_text_color'] = !empty($input['tablet_text_color']) ? sanitize_hex_color($input['tablet_text_color']) : '';
        $sanitized['tablet_link_color'] = !empty($input['tablet_link_color']) ? sanitize_hex_color($input['tablet_link_color']) : '';
        // Mobile colors
        $sanitized['mobile_bg_color'] = !empty($input['mobile_bg_color']) ? sanitize_hex_color($input['mobile_bg_color']) : '';
        $sanitized['mobile_text_color'] = !empty($input['mobile_text_color']) ? sanitize_hex_color($input['mobile_text_color']) : '';
        $sanitized['mobile_link_color'] = !empty($input['mobile_link_color']) ? sanitize_hex_color($input['mobile_link_color']) : '';
        $sanitized['font_size'] = absint($input['font_size']);
        $sanitized['padding'] = absint($input['padding']);
        $sanitized['dismissible'] = isset($input['dismissible']) ? 1 : 0;
        $sanitized['dismiss_duration'] = absint($input['dismiss_duration']);
        $sanitized['show_on_mobile'] = isset($input['show_on_mobile']) ? 1 : 0;
        $sanitized['sticky'] = isset($input['sticky']) ? 1 : 0;
        $sanitized['track_clicks'] = isset($input['track_clicks']) ? 1 : 0;
        
        // Display conditions
        $sanitized['display_mode'] = in_array($input['display_mode'], array('all', 'include', 'exclude')) ? $input['display_mode'] : 'all';
        $sanitized['show_on_homepage'] = isset($input['show_on_homepage']) ? 1 : 0;
        $sanitized['show_on_blog'] = isset($input['show_on_blog']) ? 1 : 0;
        $sanitized['show_on_posts'] = isset($input['show_on_posts']) ? 1 : 0;
        $sanitized['show_on_pages'] = isset($input['show_on_pages']) ? 1 : 0;
        $sanitized['show_on_archives'] = isset($input['show_on_archives']) ? 1 : 0;
        $sanitized['show_on_search'] = isset($input['show_on_search']) ? 1 : 0;
        $sanitized['show_on_404'] = isset($input['show_on_404']) ? 1 : 0;
        
        // Specific pages (array of IDs)
        $sanitized['specific_pages'] = array();
        if (!empty($input['specific_pages'])) {
            if (is_array($input['specific_pages'])) {
                $sanitized['specific_pages'] = array_map('absint', $input['specific_pages']);
            } else {
                $ids = array_map('trim', explode(',', $input['specific_pages']));
                $sanitized['specific_pages'] = array_filter(array_map('absint', $ids));
            }
        }
        
        // Specific posts (array of IDs)
        $sanitized['specific_posts'] = array();
        if (!empty($input['specific_posts'])) {
            if (is_array($input['specific_posts'])) {
                $sanitized['specific_posts'] = array_map('absint', $input['specific_posts']);
            } else {
                $ids = array_map('trim', explode(',', $input['specific_posts']));
                $sanitized['specific_posts'] = array_filter(array_map('absint', $ids));
            }
        }
        
        // Specific post types
        $sanitized['specific_post_types'] = array();
        if (!empty($input['specific_post_types']) && is_array($input['specific_post_types'])) {
            $sanitized['specific_post_types'] = array_map('sanitize_key', $input['specific_post_types']);
        }
        
        // Specific taxonomies (term IDs)
        $sanitized['specific_taxonomies'] = array();
        if (!empty($input['specific_taxonomies'])) {
            if (is_array($input['specific_taxonomies'])) {
                $sanitized['specific_taxonomies'] = array_map('absint', $input['specific_taxonomies']);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_scheduled-top-banner' !== $hook) {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Select2 for searchable dropdowns
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            array(),
            '4.1.0'
        );
        
        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            array('jquery'),
            '4.1.0',
            true
        );
        
        wp_enqueue_style(
            'stb-admin-style',
            STB_PLUGIN_URL . 'assets/css/admin.css',
            array('select2'),
            STB_VERSION
        );
        
        wp_enqueue_script(
            'stb-admin-script',
            STB_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker', 'select2'),
            STB_VERSION,
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script('stb-admin-script', 'stb_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('stb_admin_nonce'),
        ));
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        if (!$this->should_display_banner()) {
            return;
        }
        
        wp_enqueue_script(
            'stb-frontend-script',
            STB_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            STB_VERSION,
            true
        );
        
        wp_localize_script('stb-frontend-script', 'stb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('stb_dismiss_nonce'),
            'click_nonce' => wp_create_nonce('stb_click_nonce'),
            'dismissible' => $this->options['dismissible'],
            'track_clicks' => $this->options['track_clicks'],
        ));
    }
    
    /**
     * Check if banner should be displayed based on schedule
     */
    private function is_within_schedule() {
        $start_date = $this->options['start_date'];
        $start_time = $this->options['start_time'];
        $end_date = $this->options['end_date'];
        $end_time = $this->options['end_time'];
        
        // If no dates set, always show
        if (empty($start_date) && empty($end_date)) {
            return true;
        }
        
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        
        // Check start date/time
        if (!empty($start_date)) {
            $start_datetime = DateTime::createFromFormat('Y-m-d H:i', $start_date . ' ' . $start_time, $timezone);
            if ($start_datetime && $now < $start_datetime) {
                return false;
            }
        }
        
        // Check end date/time
        if (!empty($end_date)) {
            $end_datetime = DateTime::createFromFormat('Y-m-d H:i', $end_date . ' ' . $end_time, $timezone);
            if ($end_datetime && $now > $end_datetime) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if banner was dismissed
     */
    private function is_dismissed() {
        if (!$this->options['dismissible']) {
            return false;
        }
        
        $cookie_name = 'stb_dismissed';
        return isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] === '1';
    }
    
    /**
     * Check if current page matches display conditions
     */
    private function matches_display_conditions() {
        $display_mode = $this->options['display_mode'];
        
        // If display mode is 'all', show everywhere
        if ($display_mode === 'all') {
            return true;
        }
        
        $matches = false;
        
        // Check homepage
        if ($this->options['show_on_homepage'] && (is_front_page() || is_home())) {
            $matches = true;
        }
        
        // Check blog page (posts page)
        if ($this->options['show_on_blog'] && is_home() && !is_front_page()) {
            $matches = true;
        }
        
        // Check single posts
        if ($this->options['show_on_posts'] && is_singular('post')) {
            $matches = true;
        }
        
        // Check pages
        if ($this->options['show_on_pages'] && is_page()) {
            $matches = true;
        }
        
        // Check archives
        if ($this->options['show_on_archives'] && is_archive()) {
            $matches = true;
        }
        
        // Check search results
        if ($this->options['show_on_search'] && is_search()) {
            $matches = true;
        }
        
        // Check 404 page
        if ($this->options['show_on_404'] && is_404()) {
            $matches = true;
        }
        
        // Check specific pages
        if (!empty($this->options['specific_pages']) && is_page()) {
            $current_page_id = get_queried_object_id();
            if (in_array($current_page_id, $this->options['specific_pages'])) {
                $matches = true;
            }
        }
        
        // Check specific posts
        if (!empty($this->options['specific_posts']) && is_singular('post')) {
            $current_post_id = get_queried_object_id();
            if (in_array($current_post_id, $this->options['specific_posts'])) {
                $matches = true;
            }
        }
        
        // Check specific custom post types
        if (!empty($this->options['specific_post_types'])) {
            foreach ($this->options['specific_post_types'] as $post_type) {
                if (is_singular($post_type) || is_post_type_archive($post_type)) {
                    $matches = true;
                    break;
                }
            }
        }
        
        // Check specific taxonomy terms (categories, tags, custom taxonomies)
        if (!empty($this->options['specific_taxonomies'])) {
            // Check if on a taxonomy archive
            if (is_category() || is_tag() || is_tax()) {
                $current_term_id = get_queried_object_id();
                if (in_array($current_term_id, $this->options['specific_taxonomies'])) {
                    $matches = true;
                }
            }
            
            // Check if single post/page has any of the specified terms
            if (is_singular()) {
                $post_id = get_queried_object_id();
                $taxonomies = get_object_taxonomies(get_post_type($post_id));
                
                foreach ($taxonomies as $taxonomy) {
                    $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'ids'));
                    if (!is_wp_error($terms)) {
                        $intersect = array_intersect($terms, $this->options['specific_taxonomies']);
                        if (!empty($intersect)) {
                            $matches = true;
                            break;
                        }
                    }
                }
            }
        }
        
        // Apply the display mode logic
        if ($display_mode === 'include') {
            return $matches;
        } elseif ($display_mode === 'exclude') {
            return !$matches;
        }
        
        return true;
    }
    
    /**
     * Check if banner should be displayed
     */
    private function should_display_banner() {
        // Check if enabled
        if (empty($this->options['enabled'])) {
            return false;
        }
        
        // Check schedule
        if (!$this->is_within_schedule()) {
            return false;
        }
        
        // Check if dismissed
        if ($this->is_dismissed()) {
            return false;
        }
        
        // Check mobile visibility
        if (!$this->options['show_on_mobile'] && wp_is_mobile()) {
            return false;
        }
        
        // Check display conditions
        if (!$this->matches_display_conditions()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Output banner styles
     */
    public function output_banner_styles() {
        if (!$this->should_display_banner()) {
            return;
        }
        
        $bg_color = esc_attr($this->options['bg_color']);
        $text_color = esc_attr($this->options['text_color']);
        $link_color = esc_attr($this->options['link_color']);
        $font_size = absint($this->options['font_size']);
        $padding = absint($this->options['padding']);
        $sticky = !empty($this->options['sticky']);
        
        // Tablet colors (fallback to desktop)
        $tablet_bg_color = !empty($this->options['tablet_bg_color']) ? esc_attr($this->options['tablet_bg_color']) : $bg_color;
        $tablet_text_color = !empty($this->options['tablet_text_color']) ? esc_attr($this->options['tablet_text_color']) : $text_color;
        $tablet_link_color = !empty($this->options['tablet_link_color']) ? esc_attr($this->options['tablet_link_color']) : $link_color;
        
        // Mobile colors (fallback to tablet, then desktop)
        $mobile_bg_color = !empty($this->options['mobile_bg_color']) ? esc_attr($this->options['mobile_bg_color']) : $tablet_bg_color;
        $mobile_text_color = !empty($this->options['mobile_text_color']) ? esc_attr($this->options['mobile_text_color']) : $tablet_text_color;
        $mobile_link_color = !empty($this->options['mobile_link_color']) ? esc_attr($this->options['mobile_link_color']) : $tablet_link_color;
        
        ?>
        <style id="stb-banner-styles">
            .stb-top-banner {
                position: <?php echo $sticky ? 'fixed' : 'relative'; ?>;
                <?php if ($sticky) : ?>
                top: 0;
                left: 0;
                right: 0;
                <?php endif; ?>
                width: 100%;
                background-color: <?php echo $bg_color; ?> !important;
                color: <?php echo $text_color; ?> !important;
                font-size: <?php echo $font_size; ?>px;
                padding: <?php echo $padding; ?>px 40px;
                text-align: center;
                box-sizing: border-box;
                z-index: 999999;
                line-height: 1.5;
            }
            
            <?php if ($sticky) : ?>
            /* Add padding to body when sticky banner is active */
            body.stb-banner-active {
                padding-top: calc(<?php echo $font_size; ?>px * 1.5 + <?php echo $padding * 2; ?>px) !important;
            }
            
            /* Adjust Elementor sticky header if present */
            body.stb-banner-active .elementor-sticky--active {
                top: calc(<?php echo $font_size; ?>px * 1.5 + <?php echo $padding * 2; ?>px) !important;
            }
            <?php endif; ?>
            
            .stb-top-banner * {
                box-sizing: border-box;
            }
            
            .stb-banner-content {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .stb-banner-text {
                margin: 0;
            }
            
            .stb-banner-text a,
            .stb-banner-text a:visited,
            .stb-banner-text a:active {
                color: <?php echo $link_color; ?> !important;
                text-decoration: underline !important;
                font-weight: 600 !important;
                transition: opacity 0.2s ease;
            }
            
            .stb-banner-text a:hover,
            .stb-banner-text a:focus {
                color: <?php echo $link_color; ?> !important;
                opacity: 0.85;
            }
            
            .stb-banner-link,
            .stb-banner-link:visited,
            .stb-banner-link:active {
                color: <?php echo $link_color; ?> !important;
                text-decoration: underline !important;
                font-weight: 600 !important;
                transition: opacity 0.2s ease;
            }
            
            .stb-banner-link:hover,
            .stb-banner-link:focus {
                color: <?php echo $link_color; ?> !important;
                opacity: 0.85;
            }
            
            .stb-dismiss-btn {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: transparent;
                border: none;
                color: <?php echo $text_color; ?> !important;
                font-size: 20px;
                cursor: pointer;
                padding: 5px 10px;
                line-height: 1;
                opacity: 0.7;
                transition: opacity 0.2s ease;
            }
            
            .stb-dismiss-btn:hover {
                opacity: 1;
            }
            
            /* Tablet styles (max-width: 1024px) */
            @media (max-width: 1024px) {
                .stb-top-banner {
                    background-color: <?php echo $tablet_bg_color; ?> !important;
                    color: <?php echo $tablet_text_color; ?> !important;
                }
                
                .stb-banner-text a,
                .stb-banner-text a:visited,
                .stb-banner-text a:active,
                .stb-banner-text a:hover,
                .stb-banner-text a:focus,
                .stb-banner-link,
                .stb-banner-link:visited,
                .stb-banner-link:active,
                .stb-banner-link:hover,
                .stb-banner-link:focus {
                    color: <?php echo $tablet_link_color; ?> !important;
                }
                
                .stb-dismiss-btn {
                    color: <?php echo $tablet_text_color; ?> !important;
                }
            }
            
            /* Mobile styles (max-width: 767px) */
            @media (max-width: 767px) {
                .stb-top-banner {
                    background-color: <?php echo $mobile_bg_color; ?> !important;
                    color: <?php echo $mobile_text_color; ?> !important;
                    padding: <?php echo $padding; ?>px 35px <?php echo $padding; ?>px 15px;
                }
                
                .stb-banner-text a,
                .stb-banner-text a:visited,
                .stb-banner-text a:active,
                .stb-banner-text a:hover,
                .stb-banner-text a:focus,
                .stb-banner-link,
                .stb-banner-link:visited,
                .stb-banner-link:active,
                .stb-banner-link:hover,
                .stb-banner-link:focus {
                    color: <?php echo $mobile_link_color; ?> !important;
                }
                
                .stb-dismiss-btn {
                    color: <?php echo $mobile_text_color; ?> !important;
                }
                
                <?php if (!$this->options['show_on_mobile']) : ?>
                .stb-top-banner {
                    display: none !important;
                }
                <?php endif; ?>
            }
        </style>
        <?php
    }
    
    /**
     * Display the banner
     */
    public function display_banner() {
        if (!$this->should_display_banner()) {
            return;
        }
        
        $banner_text = $this->options['banner_text'];
        $link_new_tab = $this->options['link_new_tab'];
        $dismissible = $this->options['dismissible'];
        
        // Process banner text to add target="_blank" to links if option is enabled
        if ($link_new_tab) {
            $banner_text = preg_replace_callback(
                '/<a\s+([^>]*)>/i',
                function($matches) {
                    $attrs = $matches[1];
                    // Check if target already exists
                    if (stripos($attrs, 'target=') === false) {
                        $attrs .= ' target="_blank" rel="noopener noreferrer"';
                    }
                    return '<a ' . $attrs . '>';
                },
                $banner_text
            );
        }
        
        // Allowed HTML for output
        $allowed_html = array(
            'a' => array(
                'href' => array(),
                'title' => array(),
                'target' => array(),
                'rel' => array(),
                'class' => array(),
            ),
            'strong' => array(),
            'b' => array(),
            'em' => array(),
            'i' => array(),
            'span' => array(
                'class' => array(),
                'style' => array(),
            ),
            'br' => array(),
        );
        
        ?>
        <div id="stb-top-banner" class="stb-top-banner">
            <div class="stb-banner-content">
                <span class="stb-banner-text"><?php echo wp_kses($banner_text, $allowed_html); ?></span>
            </div>
            <?php if ($dismissible) : ?>
                <button type="button" class="stb-dismiss-btn" aria-label="<?php esc_attr_e('Dismiss banner', 'scheduled-top-banner'); ?>">&times;</button>
            <?php endif; ?>
        </div>
        <script>document.body.classList.add('stb-banner-active');</script>
        <?php
    }
    
    /**
     * Handle banner dismissal via AJAX
     */
    public function dismiss_banner() {
        check_ajax_referer('stb_dismiss_nonce', 'nonce');
        
        $duration = absint($this->options['dismiss_duration']);
        $expiry = $duration > 0 ? time() + ($duration * HOUR_IN_SECONDS) : time() + (365 * DAY_IN_SECONDS);
        
        setcookie('stb_dismissed', '1', $expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        
        wp_send_json_success();
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $options = $this->options;
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        
        ?>
        <div class="wrap stb-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="stb-admin-container">
                <form method="post" action="options.php" class="stb-settings-form">
                    <?php settings_fields('stb_settings_group'); ?>
                    
                    <!-- Enable Banner -->
                    <div class="stb-section">
                        <h2><?php _e('Banner Status', 'scheduled-top-banner'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Enable Banner', 'scheduled-top-banner'); ?></th>
                                <td>
                                    <label class="stb-toggle">
                                        <input type="checkbox" name="stb_settings[enabled]" value="1" <?php checked($options['enabled'], 1); ?>>
                                        <span class="stb-toggle-slider"></span>
                                    </label>
                                    <p class="description"><?php _e('Turn the banner on or off.', 'scheduled-top-banner'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Content -->
                    <div class="stb-section">
                        <h2><?php _e('Banner Content', 'scheduled-top-banner'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="stb_banner_text"><?php _e('Banner Text', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <?php
                                    wp_editor(
                                        $options['banner_text'],
                                        'stb_banner_text',
                                        array(
                                            'textarea_name' => 'stb_settings[banner_text]',
                                            'textarea_rows' => 3,
                                            'media_buttons' => false,
                                            'teeny' => true,
                                            'quicktags' => array('buttons' => 'strong,em,link'),
                                            'tinymce' => array(
                                                'toolbar1' => 'bold,italic,link,unlink,undo,redo',
                                                'toolbar2' => '',
                                                'toolbar3' => '',
                                            ),
                                        )
                                    );
                                    ?>
                                    <p class="description"><?php _e('You can add links and basic formatting. Keep it short for best results.', 'scheduled-top-banner'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Open Links in New Tab', 'scheduled-top-banner'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="stb_settings[link_new_tab]" value="1" <?php checked($options['link_new_tab'], 1); ?>>
                                        <?php _e('Open all banner links in a new browser tab', 'scheduled-top-banner'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Schedule -->
                    <div class="stb-section">
                        <h2><?php _e('Schedule', 'scheduled-top-banner'); ?></h2>
                        <p class="description"><?php printf(__('Current server time: %s (Timezone: %s)', 'scheduled-top-banner'), $now->format('Y-m-d H:i:s'), $timezone->getName()); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="stb_start_date"><?php _e('Start Date & Time', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="date" id="stb_start_date" name="stb_settings[start_date]" value="<?php echo esc_attr($options['start_date']); ?>">
                                    <input type="time" id="stb_start_time" name="stb_settings[start_time]" value="<?php echo esc_attr($options['start_time']); ?>">
                                    <p class="description"><?php _e('Leave empty to show immediately when enabled.', 'scheduled-top-banner'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stb_end_date"><?php _e('End Date & Time', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="date" id="stb_end_date" name="stb_settings[end_date]" value="<?php echo esc_attr($options['end_date']); ?>">
                                    <input type="time" id="stb_end_time" name="stb_settings[end_time]" value="<?php echo esc_attr($options['end_time']); ?>">
                                    <p class="description"><?php _e('Leave empty to show indefinitely.', 'scheduled-top-banner'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Appearance -->
                    <div class="stb-section">
                        <h2><?php _e('Appearance - Desktop', 'scheduled-top-banner'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="stb_bg_color"><?php _e('Background Color', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="text" id="stb_bg_color" name="stb_settings[bg_color]" value="<?php echo esc_attr($options['bg_color']); ?>" class="stb-color-picker">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stb_text_color"><?php _e('Text Color', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="text" id="stb_text_color" name="stb_settings[text_color]" value="<?php echo esc_attr($options['text_color']); ?>" class="stb-color-picker">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stb_link_color"><?php _e('Link Color', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="text" id="stb_link_color" name="stb_settings[link_color]" value="<?php echo esc_attr($options['link_color']); ?>" class="stb-color-picker">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stb_font_size"><?php _e('Font Size (px)', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="number" id="stb_font_size" name="stb_settings[font_size]" value="<?php echo esc_attr($options['font_size']); ?>" min="10" max="24" class="small-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stb_padding"><?php _e('Padding (px)', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="number" id="stb_padding" name="stb_settings[padding]" value="<?php echo esc_attr($options['padding']); ?>" min="5" max="30" class="small-text">
                                </td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('Tablet Colors', 'scheduled-top-banner'); ?> <small>(max-width: 1024px)</small></h3>
                        <p class="description"><?php _e('Leave empty to use desktop colors.', 'scheduled-top-banner'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="stb_tablet_bg_color"><?php _e('Background Color', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="text" id="stb_tablet_bg_color" name="stb_settings[tablet_bg_color]" value="<?php echo esc_attr($options['tablet_bg_color']); ?>" class="stb-color-picker" data-default-color="">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stb_tablet_text_color"><?php _e('Text Color', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="text" id="stb_tablet_text_color" name="stb_settings[tablet_text_color]" value="<?php echo esc_attr($options['tablet_text_color']); ?>" class="stb-color-picker" data-default-color="">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stb_tablet_link_color"><?php _e('Link Color', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="text" id="stb_tablet_link_color" name="stb_settings[tablet_link_color]" value="<?php echo esc_attr($options['tablet_link_color']); ?>" class="stb-color-picker" data-default-color="">
                                </td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('Mobile Colors', 'scheduled-top-banner'); ?> <small>(max-width: 767px)</small></h3>
                        <p class="description"><?php _e('Leave empty to use tablet colors (or desktop if tablet is empty).', 'scheduled-top-banner'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="stb_mobile_bg_color"><?php _e('Background Color', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="text" id="stb_mobile_bg_color" name="stb_settings[mobile_bg_color]" value="<?php echo esc_attr($options['mobile_bg_color']); ?>" class="stb-color-picker" data-default-color="">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stb_mobile_text_color"><?php _e('Text Color', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="text" id="stb_mobile_text_color" name="stb_settings[mobile_text_color]" value="<?php echo esc_attr($options['mobile_text_color']); ?>" class="stb-color-picker" data-default-color="">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stb_mobile_link_color"><?php _e('Link Color', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="text" id="stb_mobile_link_color" name="stb_settings[mobile_link_color]" value="<?php echo esc_attr($options['mobile_link_color']); ?>" class="stb-color-picker" data-default-color="">
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Behavior -->
                    <div class="stb-section">
                        <h2><?php _e('Behavior', 'scheduled-top-banner'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Sticky Banner', 'scheduled-top-banner'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="stb_settings[sticky]" value="1" <?php checked($options['sticky'], 1); ?>>
                                        <?php _e('Keep banner fixed at the top when scrolling', 'scheduled-top-banner'); ?>
                                    </label>
                                    <p class="description"><?php _e('The banner will stay visible at the top of the screen as users scroll down.', 'scheduled-top-banner'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Dismissible', 'scheduled-top-banner'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="stb_settings[dismissible]" value="1" <?php checked($options['dismissible'], 1); ?>>
                                        <?php _e('Allow visitors to dismiss the banner', 'scheduled-top-banner'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stb_dismiss_duration"><?php _e('Dismiss Duration (hours)', 'scheduled-top-banner'); ?></label></th>
                                <td>
                                    <input type="number" id="stb_dismiss_duration" name="stb_settings[dismiss_duration]" value="<?php echo esc_attr($options['dismiss_duration']); ?>" min="1" max="720" class="small-text">
                                    <p class="description"><?php _e('How long the banner stays hidden after dismissal.', 'scheduled-top-banner'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Show on Mobile', 'scheduled-top-banner'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="stb_settings[show_on_mobile]" value="1" <?php checked($options['show_on_mobile'], 1); ?>>
                                        <?php _e('Display banner on mobile devices', 'scheduled-top-banner'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Track Clicks', 'scheduled-top-banner'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="stb_settings[track_clicks]" value="1" <?php checked($options['track_clicks'], 1); ?>>
                                        <?php _e('Track link clicks for analytics', 'scheduled-top-banner'); ?>
                                    </label>
                                    <p class="description"><?php _e('Enable to track how many times banner links are clicked.', 'scheduled-top-banner'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Analytics -->
                    <div class="stb-section stb-analytics-section">
                        <h2><?php _e('Click Analytics', 'scheduled-top-banner'); ?></h2>
                        <?php
                        $analytics_today = $this->get_analytics_data('today');
                        $analytics_week = $this->get_analytics_data('week');
                        $analytics_month = $this->get_analytics_data('month');
                        $analytics_year = $this->get_analytics_data('year');
                        $analytics_all = $this->get_analytics_data('all');
                        ?>
                        <div class="stb-analytics-grid">
                            <div class="stb-analytics-card">
                                <span class="stb-analytics-number"><?php echo number_format($analytics_today['total']); ?></span>
                                <span class="stb-analytics-label"><?php _e('Today', 'scheduled-top-banner'); ?></span>
                            </div>
                            <div class="stb-analytics-card">
                                <span class="stb-analytics-number"><?php echo number_format($analytics_week['total']); ?></span>
                                <span class="stb-analytics-label"><?php _e('Last 7 Days', 'scheduled-top-banner'); ?></span>
                            </div>
                            <div class="stb-analytics-card">
                                <span class="stb-analytics-number"><?php echo number_format($analytics_month['total']); ?></span>
                                <span class="stb-analytics-label"><?php _e('Last 30 Days', 'scheduled-top-banner'); ?></span>
                            </div>
                            <div class="stb-analytics-card">
                                <span class="stb-analytics-number"><?php echo number_format($analytics_year['total']); ?></span>
                                <span class="stb-analytics-label"><?php _e('Last 365 Days', 'scheduled-top-banner'); ?></span>
                            </div>
                            <div class="stb-analytics-card stb-analytics-card-total">
                                <span class="stb-analytics-number"><?php echo number_format($analytics_all['total']); ?></span>
                                <span class="stb-analytics-label"><?php _e('All Time', 'scheduled-top-banner'); ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($analytics_month['daily'])) : ?>
                        <h3><?php _e('Daily Clicks (Last 30 Days)', 'scheduled-top-banner'); ?></h3>
                        <table class="widefat stb-analytics-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Date', 'scheduled-top-banner'); ?></th>
                                    <th><?php _e('Clicks', 'scheduled-top-banner'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analytics_month['daily'] as $date => $clicks) : ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($date))); ?></td>
                                    <td><?php echo number_format($clicks); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else : ?>
                        <p class="description"><?php _e('No click data recorded yet. Clicks will appear here once visitors start clicking banner links.', 'scheduled-top-banner'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Display Conditions -->
                    <div class="stb-section">
                        <h2><?php _e('Display Conditions', 'scheduled-top-banner'); ?></h2>
                        <p class="description"><?php _e('Control where the banner appears on your site.', 'scheduled-top-banner'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Display Mode', 'scheduled-top-banner'); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="radio" name="stb_settings[display_mode]" value="all" <?php checked($options['display_mode'], 'all'); ?>>
                                            <?php _e('Show on all pages', 'scheduled-top-banner'); ?>
                                        </label><br>
                                        <label>
                                            <input type="radio" name="stb_settings[display_mode]" value="include" <?php checked($options['display_mode'], 'include'); ?>>
                                            <?php _e('Show only on selected pages (Include)', 'scheduled-top-banner'); ?>
                                        </label><br>
                                        <label>
                                            <input type="radio" name="stb_settings[display_mode]" value="exclude" <?php checked($options['display_mode'], 'exclude'); ?>>
                                            <?php _e('Show everywhere except selected pages (Exclude)', 'scheduled-top-banner'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                        
                        <div id="stb-conditions-wrapper" class="stb-conditions-wrapper" style="<?php echo $options['display_mode'] === 'all' ? 'display:none;' : ''; ?>">
                            <h3><?php _e('Page Types', 'scheduled-top-banner'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('General Pages', 'scheduled-top-banner'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="stb_settings[show_on_homepage]" value="1" <?php checked($options['show_on_homepage'], 1); ?>>
                                                <?php _e('Homepage / Front Page', 'scheduled-top-banner'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="stb_settings[show_on_blog]" value="1" <?php checked($options['show_on_blog'], 1); ?>>
                                                <?php _e('Blog Page (Posts Page)', 'scheduled-top-banner'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="stb_settings[show_on_posts]" value="1" <?php checked($options['show_on_posts'], 1); ?>>
                                                <?php _e('All Blog Posts', 'scheduled-top-banner'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="stb_settings[show_on_pages]" value="1" <?php checked($options['show_on_pages'], 1); ?>>
                                                <?php _e('All Pages', 'scheduled-top-banner'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="stb_settings[show_on_archives]" value="1" <?php checked($options['show_on_archives'], 1); ?>>
                                                <?php _e('Archive Pages (Categories, Tags, Date)', 'scheduled-top-banner'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="stb_settings[show_on_search]" value="1" <?php checked($options['show_on_search'], 1); ?>>
                                                <?php _e('Search Results', 'scheduled-top-banner'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="stb_settings[show_on_404]" value="1" <?php checked($options['show_on_404'], 1); ?>>
                                                <?php _e('404 Error Page', 'scheduled-top-banner'); ?>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                            </table>
                            
                            <h3><?php _e('Specific Content', 'scheduled-top-banner'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="stb_specific_pages"><?php _e('Specific Pages', 'scheduled-top-banner'); ?></label></th>
                                    <td>
                                        <?php
                                        $pages = get_pages(array('post_status' => 'publish', 'number' => 100));
                                        ?>
                                        <select id="stb_specific_pages" name="stb_settings[specific_pages][]" multiple="multiple" class="stb-select2" style="width: 100%; max-width: 400px;">
                                            <?php foreach ($pages as $page) : ?>
                                                <option value="<?php echo esc_attr($page->ID); ?>" <?php echo in_array($page->ID, (array)$options['specific_pages']) ? 'selected' : ''; ?>>
                                                    <?php echo esc_html($page->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description"><?php _e('Select specific pages to include/exclude.', 'scheduled-top-banner'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="stb_specific_posts"><?php _e('Specific Posts', 'scheduled-top-banner'); ?></label></th>
                                    <td>
                                        <?php
                                        $posts = get_posts(array('post_status' => 'publish', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC'));
                                        ?>
                                        <select id="stb_specific_posts" name="stb_settings[specific_posts][]" multiple="multiple" class="stb-select2" style="width: 100%; max-width: 400px;">
                                            <?php foreach ($posts as $post) : ?>
                                                <option value="<?php echo esc_attr($post->ID); ?>" <?php echo in_array($post->ID, (array)$options['specific_posts']) ? 'selected' : ''; ?>>
                                                    <?php echo esc_html($post->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description"><?php _e('Select specific blog posts to include/exclude.', 'scheduled-top-banner'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="stb_specific_post_types"><?php _e('Custom Post Types', 'scheduled-top-banner'); ?></label></th>
                                    <td>
                                        <?php
                                        $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
                                        ?>
                                        <?php if (!empty($post_types)) : ?>
                                            <select id="stb_specific_post_types" name="stb_settings[specific_post_types][]" multiple="multiple" class="stb-select2" style="width: 100%; max-width: 400px;">
                                                <?php foreach ($post_types as $post_type) : ?>
                                                    <option value="<?php echo esc_attr($post_type->name); ?>" <?php echo in_array($post_type->name, (array)$options['specific_post_types']) ? 'selected' : ''; ?>>
                                                        <?php echo esc_html($post_type->labels->name); ?> (<?php echo esc_html($post_type->name); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description"><?php _e('Select custom post types to include/exclude (applies to single posts and archives).', 'scheduled-top-banner'); ?></p>
                                        <?php else : ?>
                                            <p class="description"><?php _e('No custom post types found.', 'scheduled-top-banner'); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="stb_specific_taxonomies"><?php _e('Categories & Tags', 'scheduled-top-banner'); ?></label></th>
                                    <td>
                                        <?php
                                        $categories = get_categories(array('hide_empty' => false));
                                        $tags = get_tags(array('hide_empty' => false));
                                        ?>
                                        <select id="stb_specific_taxonomies" name="stb_settings[specific_taxonomies][]" multiple="multiple" class="stb-select2" style="width: 100%; max-width: 400px;">
                                            <?php if (!empty($categories)) : ?>
                                                <optgroup label="<?php _e('Categories', 'scheduled-top-banner'); ?>">
                                                    <?php foreach ($categories as $category) : ?>
                                                        <option value="<?php echo esc_attr($category->term_id); ?>" <?php echo in_array($category->term_id, (array)$options['specific_taxonomies']) ? 'selected' : ''; ?>>
                                                            <?php echo esc_html($category->name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                            <?php if (!empty($tags)) : ?>
                                                <optgroup label="<?php _e('Tags', 'scheduled-top-banner'); ?>">
                                                    <?php foreach ($tags as $tag) : ?>
                                                        <option value="<?php echo esc_attr($tag->term_id); ?>" <?php echo in_array($tag->term_id, (array)$options['specific_taxonomies']) ? 'selected' : ''; ?>>
                                                            <?php echo esc_html($tag->name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                        </select>
                                        <p class="description"><?php _e('Show/hide on posts with these categories or tags, and on category/tag archive pages.', 'scheduled-top-banner'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php submit_button(__('Save Settings', 'scheduled-top-banner')); ?>
                </form>
                
                <!-- Preview -->
                <div class="stb-preview-section">
                    <h2><?php _e('Preview', 'scheduled-top-banner'); ?></h2>
                    <p class="description"><?php _e('Note: Preview updates when you save settings.', 'scheduled-top-banner'); ?></p>
                    <div id="stb-preview" class="stb-preview-container">
                        <div class="stb-preview-banner" style="background-color: <?php echo esc_attr($options['bg_color']); ?>; color: <?php echo esc_attr($options['text_color']); ?>; font-size: <?php echo esc_attr($options['font_size']); ?>px; padding: <?php echo esc_attr($options['padding']); ?>px 40px;">
                            <div class="stb-preview-content">
                                <span class="stb-preview-text"><?php echo wp_kses_post($options['banner_text']); ?></span>
                            </div>
                            <?php if ($options['dismissible']) : ?>
                                <button type="button" class="stb-preview-dismiss">&times;</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize plugin
function stb_init() {
    return Scheduled_Top_Banner::get_instance();
}
add_action('plugins_loaded', 'stb_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create analytics table
    Scheduled_Top_Banner::create_analytics_table();
    
    // Set default options on activation
    if (!get_option('stb_settings')) {
        add_option('stb_settings', array(
            'enabled' => 0,
            'banner_text' => '🎉 Check out our latest offer! <a href="#">Learn More</a>',
            'link_new_tab' => 1,
            'start_date' => '',
            'start_time' => '00:00',
            'end_date' => '',
            'end_time' => '23:59',
            'bg_color' => '#1a73e8',
            'text_color' => '#ffffff',
            'link_color' => '#ffffff',
            'tablet_bg_color' => '',
            'tablet_text_color' => '',
            'tablet_link_color' => '',
            'mobile_bg_color' => '',
            'mobile_text_color' => '',
            'mobile_link_color' => '',
            'font_size' => '14',
            'padding' => '12',
            'dismissible' => 1,
            'dismiss_duration' => '24',
            'show_on_mobile' => 1,
            'sticky' => 0,
            'track_clicks' => 1,
            // Display conditions
            'display_mode' => 'all',
            'show_on_homepage' => 1,
            'show_on_blog' => 1,
            'show_on_posts' => 1,
            'show_on_pages' => 1,
            'show_on_archives' => 1,
            'show_on_search' => 1,
            'show_on_404' => 0,
            'specific_pages' => array(),
            'specific_posts' => array(),
            'specific_post_types' => array(),
            'specific_taxonomies' => array(),
        ));
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Optionally clean up options
    // delete_option('stb_settings');
});
