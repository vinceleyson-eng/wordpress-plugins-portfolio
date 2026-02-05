<?php
/**
 * Plugin Name: UTM Link Tracker
 * Plugin URI: https://yoursite.com
 * Description: Automatically appends UTM parameters to all internal links for consistent tracking across your site
 * Version: 1.0.0
 * Author: Vince L
 * Text Domain: utm-link-tracker
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class UTM_Link_Tracker {
    
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
        add_action('wp_footer', array($this, 'output_script'), 100);
        
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function activate() {
        // Default settings
        $defaults = array(
            'enabled' => 1,
            'utm_params' => 'utm_source, utm_medium, utm_campaign, utm_id, utm_term, utm_content',
            'custom_params' => '',
            'exclude_selectors' => '',
            'store_in_session' => 0,
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('utmlt_' . $key) === false) {
                add_option('utmlt_' . $key, $value);
            }
        }
    }
    
    public function add_admin_menu() {
        add_options_page(
            'UTM Link Tracker',
            'UTM Tracker',
            'manage_options',
            'utm-link-tracker',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('utmlt_settings', 'utmlt_enabled');
        register_setting('utmlt_settings', 'utmlt_utm_params');
        register_setting('utmlt_settings', 'utmlt_custom_params');
        register_setting('utmlt_settings', 'utmlt_exclude_selectors');
        register_setting('utmlt_settings', 'utmlt_store_in_session');
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>🔗 UTM Link Tracker</h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('utmlt_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th>Enable Tracking</th>
                        <td>
                            <label>
                                <input type="checkbox" name="utmlt_enabled" value="1" <?php checked(get_option('utmlt_enabled', 1), 1); ?>>
                                Append UTM parameters to internal links
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>UTM Parameters</th>
                        <td>
                            <input type="text" name="utmlt_utm_params" value="<?php echo esc_attr(get_option('utmlt_utm_params', 'utm_source, utm_medium, utm_campaign, utm_id, utm_term, utm_content')); ?>" class="large-text">
                            <p class="description">Standard UTM parameters to track (comma-separated)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Custom Parameters</th>
                        <td>
                            <input type="text" name="utmlt_custom_params" value="<?php echo esc_attr(get_option('utmlt_custom_params', '')); ?>" class="regular-text" placeholder="e.g., gclid, fbclid, ref">
                            <p class="description">Additional parameters to persist (comma-separated). Useful for gclid, fbclid, etc.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Exclude Links</th>
                        <td>
                            <input type="text" name="utmlt_exclude_selectors" value="<?php echo esc_attr(get_option('utmlt_exclude_selectors', '')); ?>" class="regular-text" placeholder="e.g., .no-track, #menu a">
                            <p class="description">CSS selectors for links to exclude (comma-separated)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Session Storage</th>
                        <td>
                            <label>
                                <input type="checkbox" name="utmlt_store_in_session" value="1" <?php checked(get_option('utmlt_store_in_session'), 1); ?>>
                                Remember UTMs across page loads (even if URL doesn't have them)
                            </label>
                            <p class="description">Uses sessionStorage to persist UTMs for the entire browsing session</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            <h2>How It Works</h2>
            <ol>
                <li>Visitor arrives with UTM parameters: <code>yoursite.com/?utm_source=google&utm_medium=cpc</code></li>
                <li>Plugin automatically appends these UTMs to all internal links on the page</li>
                <li>When visitor clicks any link, UTMs follow them throughout their session</li>
                <li>Your analytics/CRM captures accurate attribution data</li>
            </ol>
            
            <h2>Test It</h2>
            <p>Add UTMs to any page URL and inspect the links — they should all have the UTMs appended:</p>
            <code><?php echo home_url('/?utm_source=test&utm_medium=test&utm_campaign=test'); ?></code>
        </div>
        <?php
    }
    
    public function output_script() {
        // Only on frontend, not admin
        if (is_admin()) {
            return;
        }
        
        // Check if enabled
        if (!get_option('utmlt_enabled', 1)) {
            return;
        }
        
        // Get parameters to track
        $utm_params = get_option('utmlt_utm_params', 'utm_source, utm_medium, utm_campaign, utm_id, utm_term, utm_content');
        $custom_params = get_option('utmlt_custom_params', '');
        $exclude_selectors = get_option('utmlt_exclude_selectors', '');
        $store_in_session = get_option('utmlt_store_in_session', 0);
        
        // Combine all params
        $all_params = array_filter(array_map('trim', explode(',', $utm_params . ',' . $custom_params)));
        $all_params = array_unique($all_params);
        
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';
            
            var trackedParams = <?php echo json_encode(array_values($all_params)); ?>;
            var excludeSelectors = <?php echo json_encode($exclude_selectors); ?>;
            var useSessionStorage = <?php echo $store_in_session ? 'true' : 'false'; ?>;
            var currentDomain = window.location.hostname;
            
            // Get params from URL or session storage
            function getTrackedParams() {
                var urlParams = new URLSearchParams(window.location.search);
                var params = {};
                
                trackedParams.forEach(function(param) {
                    var value = urlParams.get(param);
                    if (value) {
                        params[param] = value;
                    }
                });
                
                // Check session storage for previously stored params
                if (useSessionStorage) {
                    try {
                        var stored = sessionStorage.getItem('utmlt_params');
                        if (stored) {
                            var storedParams = JSON.parse(stored);
                            // URL params take precedence over stored
                            for (var key in storedParams) {
                                if (!params[key]) {
                                    params[key] = storedParams[key];
                                }
                            }
                        }
                        // Store current params
                        if (Object.keys(params).length > 0) {
                            sessionStorage.setItem('utmlt_params', JSON.stringify(params));
                        }
                    } catch (e) {}
                }
                
                return params;
            }
            
            // Check if link should be excluded
            function isExcluded(link) {
                if (!excludeSelectors) return false;
                var selectors = excludeSelectors.split(',').map(function(s) { return s.trim(); });
                for (var i = 0; i < selectors.length; i++) {
                    if (selectors[i] && link.matches(selectors[i])) {
                        return true;
                    }
                }
                return false;
            }
            
            // Update all links
            function updateLinks() {
                var params = getTrackedParams();
                
                // No params to add
                if (Object.keys(params).length === 0) {
                    return;
                }
                
                document.querySelectorAll('a').forEach(function(link) {
                    try {
                        // Skip excluded links
                        if (isExcluded(link)) return;
                        
                        var url = new URL(link.href);
                        
                        // Only modify internal links (same domain)
                        if (url.hostname !== currentDomain) return;
                        
                        // Skip anchors, mailto, tel, javascript
                        if (url.protocol === 'mailto:' || url.protocol === 'tel:' || url.protocol === 'javascript:') return;
                        
                        // Add params
                        for (var param in params) {
                            if (!url.searchParams.has(param)) {
                                url.searchParams.set(param, params[param]);
                            }
                        }
                        
                        link.href = url.toString();
                    } catch (e) {
                        // Skip invalid URLs
                    }
                });
            }
            
            // Run on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', updateLinks);
            } else {
                updateLinks();
            }
            
            // Also observe DOM for dynamically added links (SPA support)
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            updateLinks();
                        }
                    });
                });
                observer.observe(document.body, { childList: true, subtree: true });
            }
        })();
        </script>
        <?php
    }
}

// Initialize
UTM_Link_Tracker::instance();
