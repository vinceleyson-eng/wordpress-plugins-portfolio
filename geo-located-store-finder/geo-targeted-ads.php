<?php
/**
 * Plugin Name: Geo-Located Store Finder
 * Plugin URI: https://yoursite.com
 * Description: Displays stores based on visitor's geolocation and proximity to store addresses
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GeoLocatedStores {
    
    private $plugin_url;
    private $plugin_path;
    
    public function __construct() {
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_get_nearby_stores', array($this, 'get_nearby_stores'));
        add_action('wp_ajax_nopriv_get_nearby_stores', array($this, 'get_nearby_stores'));
        add_action('wp_ajax_geocode_store_address', array($this, 'geocode_store_address'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_shortcode('nearby_stores', array($this, 'nearby_stores_shortcode'));
        
        // Add meta box for store addresses
        add_action('add_meta_boxes', array($this, 'add_store_meta_boxes'));
        add_action('save_post', array($this, 'save_store_meta'));
        
        // Add admin columns
        add_filter('manage_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_posts_custom_column', array($this, 'display_admin_columns'), 10, 2);
    }
    
    public function init() {
        // Create stores table on first load
        $this->maybe_create_stores_table();
    }
    
    private function maybe_create_stores_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'store_locations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                post_id bigint(20) NOT NULL,
                address text NOT NULL,
                latitude decimal(10, 6) NOT NULL,
                longitude decimal(11, 6) NOT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY post_id (post_id),
                KEY coordinates (latitude, longitude)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script(
            'geo-stores-script',
            $this->plugin_url . 'geo-stores.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('geo-stores-script', 'geoStores', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('geo_stores_nonce')
        ));
        
        wp_enqueue_style(
            'geo-stores-style',
            $this->plugin_url . 'geo-stores.css',
            array(),
            '1.0.0'
        );
    }
    
    public function get_nearby_stores() {
        check_ajax_referer('geo_stores_nonce', 'nonce');
        
        $lat = floatval($_POST['lat']);
        $lng = floatval($_POST['lng']);
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $radius = isset($_POST['radius']) ? floatval($_POST['radius']) : 25;
        
        if (!$lat || !$lng) {
            wp_die('Invalid coordinates');
        }
        
        // Get nearby stores
        $stores = $this->find_nearby_stores($lat, $lng, $radius, $limit);
        
        wp_send_json_success($stores);
    }
    
    public function geocode_store_address() {
        check_ajax_referer('geo_stores_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $address = sanitize_textarea_field($_POST['address']);
        
        if (!$post_id || !$address) {
            wp_die('Invalid parameters');
        }
        
        $coordinates = $this->geocode_address($address);
        
        if ($coordinates) {
            // Save coordinates to database
            $this->save_store_coordinates($post_id, $address, $coordinates['lat'], $coordinates['lng']);
            wp_send_json_success($coordinates);
        } else {
            wp_send_json_error('Failed to geocode address');
        }
    }
    
    private function find_nearby_stores($lat, $lng, $radius, $limit) {
        global $wpdb;
        
        $settings = get_option('geo_stores_settings', array());
        $post_type = isset($settings['post_type']) ? $settings['post_type'] : 'post';
        
        $stores_table = $wpdb->prefix . 'store_locations';
        $posts_table = $wpdb->posts;
        
        // Query to find nearby stores using Haversine formula
        $query = $wpdb->prepare("
            SELECT 
                p.ID,
                p.post_title,
                p.post_content,
                p.post_excerpt,
                s.address,
                s.latitude,
                s.longitude,
                (3959 * acos(
                    cos(radians(%f)) * 
                    cos(radians(s.latitude)) * 
                    cos(radians(s.longitude) - radians(%f)) + 
                    sin(radians(%f)) * 
                    sin(radians(s.latitude))
                )) AS distance
            FROM {$posts_table} p
            INNER JOIN {$stores_table} s ON p.ID = s.post_id
            WHERE p.post_type = %s 
            AND p.post_status = 'publish'
            AND (3959 * acos(
                cos(radians(%f)) * 
                cos(radians(s.latitude)) * 
                cos(radians(s.longitude) - radians(%f)) + 
                sin(radians(%f)) * 
                sin(radians(s.latitude))
            )) <= %f
            ORDER BY distance ASC
            LIMIT %d
        ", $lat, $lng, $lat, $post_type, $lat, $lng, $lat, $radius, $limit);
        
        $results = $wpdb->get_results($query);
        
        // Format results
        $stores = array();
        foreach ($results as $store) {
            $stores[] = array(
                'id' => $store->ID,
                'title' => $store->post_title,
                'content' => apply_filters('the_content', $store->post_content),
                'excerpt' => $store->post_excerpt,
                'address' => $store->address,
                'latitude' => floatval($store->latitude),
                'longitude' => floatval($store->longitude),
                'distance' => round($store->distance, 2),
                'permalink' => get_permalink($store->ID),
                'featured_image' => get_the_post_thumbnail_url($store->ID, 'medium'),
                'phone' => get_post_meta($store->ID, 'store_phone', true),
                'hours' => get_post_meta($store->ID, 'store_hours', true),
                'website' => get_post_meta($store->ID, 'store_website', true)
            );
        }
        
        return $stores;
    }
    
    private function geocode_address($address) {
        $settings = get_option('geo_stores_settings', array());
        $api_key = isset($settings['google_api_key']) ? $settings['google_api_key'] : '';
        
        // Try Google Maps Geocoding API first (most accurate)
        if ($api_key) {
            $coordinates = $this->geocode_with_google($address, $api_key);
            if ($coordinates) {
                return $coordinates;
            }
        }
        
        // Fallback to free services
        return $this->geocode_with_free_service($address);
    }
    
    private function geocode_with_google($address, $api_key) {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query(array(
            'address' => $address,
            'key' => $api_key
        ));
        
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['status'] === 'OK' && !empty($data['results'])) {
            $location = $data['results'][0]['geometry']['location'];
            return array(
                'lat' => $location['lat'],
                'lng' => $location['lng']
            );
        }
        
        return false;
    }
    
    private function geocode_with_free_service($address) {
        // Try Nominatim (OpenStreetMap)
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(array(
            'q' => $address,
            'format' => 'json',
            'limit' => 1
        ));
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'WordPress Store Locator Plugin'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && !empty($data)) {
            return array(
                'lat' => floatval($data[0]['lat']),
                'lng' => floatval($data[0]['lon'])
            );
        }
        
        return false;
    }
    
    private function save_store_coordinates($post_id, $address, $lat, $lng) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'store_locations';
        
        $wpdb->replace(
            $table_name,
            array(
                'post_id' => $post_id,
                'address' => $address,
                'latitude' => $lat,
                'longitude' => $lng,
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%f', '%f', '%s')
        );
    }
    
    public function nearby_stores_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'radius' => 25,
            'layout' => 'list',
            'show_image' => 'true',
            'show_distance' => 'true',
            'show_address' => 'true',
            'show_map' => 'false',
            'unit' => 'miles'
        ), $atts);
        
        ob_start();
        ?>
        <div id="nearby-stores-container" 
             data-limit="<?php echo esc_attr($atts['limit']); ?>"
             data-radius="<?php echo esc_attr($atts['radius']); ?>"
             data-layout="<?php echo esc_attr($atts['layout']); ?>"
             data-show-image="<?php echo esc_attr($atts['show_image']); ?>"
             data-show-distance="<?php echo esc_attr($atts['show_distance']); ?>"
             data-show-address="<?php echo esc_attr($atts['show_address']); ?>"
             data-show-map="<?php echo esc_attr($atts['show_map']); ?>"
             data-unit="<?php echo esc_attr($atts['unit']); ?>">
            <div class="geo-stores-loading">Finding stores near you...</div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Geo-Located Stores Settings',
            'Store Locator',
            'manage_options',
            'geo-stores-settings',
            array($this, 'admin_page')
        );
        
        $settings = get_option('geo_stores_settings', array());
        $post_type = isset($settings['post_type']) ? $settings['post_type'] : '';
        
        // Only add geocode submenu if post type is configured
        if ($post_type && post_type_exists($post_type)) {
            add_submenu_page(
                'edit.php?post_type=' . $post_type,
                'Geocode Stores',
                'Geocode Stores',
                'manage_options',
                'geocode-stores',
                array($this, 'geocode_page')
            );
        }
        
        // Also add under Tools menu for easy access
        add_management_page(
            'Geocode Stores',
            'Geocode Stores',
            'manage_options',
            'geocode-stores-tool',
            array($this, 'geocode_page')
        );
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('geo_stores_settings_save');
            $settings = array(
                'post_type' => sanitize_text_field($_POST['post_type']),
                'address_field' => sanitize_text_field($_POST['address_field']),
                'default_radius' => floatval($_POST['default_radius']),
                'google_api_key' => sanitize_text_field($_POST['google_api_key']),
                'units' => sanitize_text_field($_POST['units'])
            );
            update_option('geo_stores_settings', $settings);
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $settings = get_option('geo_stores_settings', array());
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="wrap">
            <h1>Store Locator Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field('geo_stores_settings_save'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Post Type for Stores</th>
                        <td>
                            <select name="post_type">
                                <?php foreach ($post_types as $post_type): ?>
                                    <option value="<?php echo $post_type->name; ?>" 
                                        <?php selected(isset($settings['post_type']) ? $settings['post_type'] : '', $post_type->name); ?>>
                                        <?php echo $post_type->labels->name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Store Address Field Name</th>
                        <td>
                            <input type="text" name="address_field" 
                                   value="<?php echo isset($settings['address_field']) ? esc_attr($settings['address_field']) : 'store_address'; ?>" />
                            <p class="description">The custom field name that stores the store address</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Search Radius</th>
                        <td>
                            <input type="number" name="default_radius" step="0.1"
                                   value="<?php echo isset($settings['default_radius']) ? esc_attr($settings['default_radius']) : '25'; ?>" />
                            <select name="units">
                                <option value="miles" <?php selected(isset($settings['units']) ? $settings['units'] : 'miles', 'miles'); ?>>Miles</option>
                                <option value="km" <?php selected(isset($settings['units']) ? $settings['units'] : 'miles', 'km'); ?>>Kilometers</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Google Maps API Key</th>
                        <td>
                            <input type="text" name="google_api_key" style="width: 400px;"
                                   value="<?php echo isset($settings['google_api_key']) ? esc_attr($settings['google_api_key']) : ''; ?>" />
                            <p class="description">
                                For accurate geocoding. Get your key from: 
                                <a href="https://developers.google.com/maps/documentation/geocoding/get-api-key" target="_blank">Google Maps Platform</a>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <h2>Usage</h2>
            <p>Use the shortcode <code>[nearby_stores]</code> to display nearby stores based on visitor location.</p>
            <p>Shortcode attributes:</p>
            <ul>
                <li><strong>limit</strong>: Number of stores to show (default: 10)</li>
                <li><strong>radius</strong>: Search radius (default: 25)</li>
                <li><strong>layout</strong>: Display layout - 'list', 'grid', or 'map' (default: list)</li>
                <li><strong>show_image</strong>: Show featured images - 'true' or 'false' (default: true)</li>
                <li><strong>show_distance</strong>: Show distance - 'true' or 'false' (default: true)</li>
                <li><strong>show_address</strong>: Show address - 'true' or 'false' (default: true)</li>
                <li><strong>show_map</strong>: Show interactive map - 'true' or 'false' (default: false)</li>
                <li><strong>unit</strong>: Distance units - 'miles' or 'km' (default: miles)</li>
            </ul>
            <p>Example: <code>[nearby_stores limit="5" radius="10" layout="grid"]</code></p>
        </div>
        <?php
    }
    
    public function geocode_page() {
        $settings = get_option('geo_stores_settings', array());
        $post_type = isset($settings['post_type']) ? $settings['post_type'] : 'post';
        $address_field = isset($settings['address_field']) ? $settings['address_field'] : 'store_address';
        
        // Get stores that need geocoding
        $stores = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => $address_field,
                    'value' => '',
                    'compare' => '!='
                )
            )
        ));
        ?>
        <div class="wrap">
            <h1>Geocode Store Addresses</h1>
            <p>This tool will geocode (find coordinates for) all store addresses. This is required for the location-based search to work.</p>
            
            <div id="geocode-progress" style="display: none;">
                <p>Geocoding stores... <span id="geocode-status">0/0</span></p>
                <div style="background: #f0f0f0; height: 20px; border-radius: 10px; overflow: hidden;">
                    <div id="geocode-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Store Name</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $store): 
                        $address = get_post_meta($store->ID, $address_field, true);
                        $has_coords = $this->store_has_coordinates($store->ID);
                    ?>
                    <tr data-post-id="<?php echo $store->ID; ?>">
                        <td><?php echo esc_html($store->post_title); ?></td>
                        <td><?php echo esc_html($address); ?></td>
                        <td class="geocode-status">
                            <?php echo $has_coords ? '<span style="color: green;">✓ Geocoded</span>' : '<span style="color: orange;">Pending</span>'; ?>
                        </td>
                        <td>
                            <button type="button" class="button geocode-single" data-post-id="<?php echo $store->ID; ?>" data-address="<?php echo esc_attr($address); ?>">
                                Geocode
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p>
                <button type="button" class="button button-primary" id="geocode-all">Geocode All Stores</button>
                <span class="description">This will geocode all stores that don't have coordinates yet.</span>
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#geocode-all, .geocode-single').on('click', function() {
                if ($(this).hasClass('geocode-single')) {
                    var postId = $(this).data('post-id');
                    var address = $(this).data('address');
                    geocodeStore(postId, address);
                } else {
                    geocodeAllStores();
                }
            });
            
            function geocodeAllStores() {
                var $rows = $('tr[data-post-id]');
                var $pendingRows = $rows.filter(function() {
                    return $(this).find('.geocode-status').text().indexOf('Pending') !== -1;
                });
                
                if ($pendingRows.length === 0) {
                    alert('All stores are already geocoded!');
                    return;
                }
                
                $('#geocode-progress').show();
                $('#geocode-status').text('0/' + $pendingRows.length);
                $('#geocode-bar').width('0%');
                
                var completed = 0;
                
                $pendingRows.each(function(index) {
                    var $row = $(this);
                    var postId = $row.data('post-id');
                    var address = $row.find('td:eq(1)').text();
                    
                    setTimeout(function() {
                        geocodeStore(postId, address, function() {
                            completed++;
                            $('#geocode-status').text(completed + '/' + $pendingRows.length);
                            $('#geocode-bar').width((completed / $pendingRows.length * 100) + '%');
                            
                            if (completed === $pendingRows.length) {
                                setTimeout(function() {
                                    $('#geocode-progress').hide();
                                    alert('All stores geocoded successfully!');
                                }, 1000);
                            }
                        });
                    }, index * 1000);
                });
            }
            
            function geocodeStore(postId, address, callback) {
                $.ajax({
                    url: geoStores.ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'geocode_store_address',
                        post_id: postId,
                        address: address,
                        nonce: geoStores.nonce
                    },
                    success: function(response) {
                        var $row = $('tr[data-post-id="' + postId + '"]');
                        if (response.success) {
                            $row.find('.geocode-status').html('<span style="color: green;">✓ Geocoded</span>');
                        } else {
                            $row.find('.geocode-status').html('<span style="color: red;">✗ Failed</span>');
                        }
                        if (callback) callback();
                    },
                    error: function() {
                        var $row = $('tr[data-post-id="' + postId + '"]');
                        $row.find('.geocode-status').html('<span style="color: red;">✗ Error</span>');
                        if (callback) callback();
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    private function store_has_coordinates($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'store_locations';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE post_id = %d",
            $post_id
        )) > 0;
    }
    
    public function add_store_meta_boxes() {
        $settings = get_option('geo_stores_settings', array());
        $post_type = isset($settings['post_type']) ? $settings['post_type'] : 'post';
        
        add_meta_box(
            'store_details',
            'Store Details',
            array($this, 'store_details_meta_box'),
            $post_type,
            'normal',
            'default'
        );
    }
    
    public function store_details_meta_box($post) {
        $settings = get_option('geo_stores_settings', array());
        $address_field = isset($settings['address_field']) ? $settings['address_field'] : 'store_address';
        
        $address = get_post_meta($post->ID, $address_field, true);
        $phone = get_post_meta($post->ID, 'store_phone', true);
        $website = get_post_meta($post->ID, 'store_website', true);
        $hours = get_post_meta($post->ID, 'store_hours', true);
        
        wp_nonce_field('store_details_nonce', 'store_details_nonce');
        ?>
        <table class="form-table">
            <tr>
                <th><label for="<?php echo $address_field; ?>">Store Address</label></th>
                <td>
                    <textarea id="<?php echo $address_field; ?>" name="<?php echo $address_field; ?>" 
                              rows="3" style="width: 100%;"><?php echo esc_textarea($address); ?></textarea>
                    <p class="description">Full address including city, state, and zip code</p>
                </td>
            </tr>
            <tr>
                <th><label for="store_phone">Phone Number</label></th>
                <td>
                    <input type="text" id="store_phone" name="store_phone" 
                           value="<?php echo esc_attr($phone); ?>" style="width: 100%;" />
                </td>
            </tr>
            <tr>
                <th><label for="store_website">Website</label></th>
                <td>
                    <input type="url" id="store_website" name="store_website" 
                           value="<?php echo esc_attr($website); ?>" style="width: 100%;" />
                </td>
            </tr>
            <tr>
                <th><label for="store_hours">Store Hours</label></th>
                <td>
                    <textarea id="store_hours" name="store_hours" 
                              rows="5" style="width: 100%;"><?php echo esc_textarea($hours); ?></textarea>
                    <p class="description">Store operating hours (e.g., Mon-Fri: 9am-6pm, Sat: 10am-4pm)</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function save_store_meta($post_id) {
        if (!isset($_POST['store_details_nonce']) || 
            !wp_verify_nonce($_POST['store_details_nonce'], 'store_details_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $settings = get_option('geo_stores_settings', array());
        $address_field = isset($settings['address_field']) ? $settings['address_field'] : 'store_address';
        
        // Save meta fields
        $fields = array($address_field, 'store_phone', 'store_website', 'store_hours');
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$field]));
            }
        }
        
        // If address was updated, trigger geocoding
        if (isset($_POST[$address_field]) && !empty($_POST[$address_field])) {
            $address = sanitize_textarea_field($_POST[$address_field]);
            $coordinates = $this->geocode_address($address);
            if ($coordinates) {
                $this->save_store_coordinates($post_id, $address, $coordinates['lat'], $coordinates['lng']);
            }
        }
    }
    
    public function add_admin_columns($columns) {
        $settings = get_option('geo_stores_settings', array());
        $post_type = isset($settings['post_type']) ? $settings['post_type'] : 'post';
        
        if (get_current_screen() && get_current_screen()->post_type === $post_type) {
            $columns['store_address'] = 'Address';
            $columns['store_coordinates'] = 'Geocoded';
        }
        
        return $columns;
    }
    
    public function display_admin_columns($column, $post_id) {
        $settings = get_option('geo_stores_settings', array());
        $address_field = isset($settings['address_field']) ? $settings['address_field'] : 'store_address';
        
        switch ($column) {
            case 'store_address':
                $address = get_post_meta($post_id, $address_field, true);
                echo esc_html($address);
                break;
            case 'store_coordinates':
                $has_coords = $this->store_has_coordinates($post_id);
                if ($has_coords) {
                    echo '<span style="color: green;">✓</span>';
                } else {
                    echo '<span style="color: red;">✗</span>';
                }
                break;
        }
    }
}

// Initialize the plugin
new GeoLocatedStores();

// Create table on activation
register_activation_hook(__FILE__, 'geo_stores_create_table');

function geo_stores_create_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'store_locations';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        address text NOT NULL,
        latitude decimal(10, 6) NOT NULL,
        longitude decimal(11, 6) NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY post_id (post_id),
        KEY coordinates (latitude, longitude)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}