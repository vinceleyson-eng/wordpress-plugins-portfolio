<?php
/**
 * Plugin Name: ACF Relationship Display for Elementor
 * Plugin URI: https://yoursite.com
 * Description: Display ACF relationships with custom fields and dynamic titles in Elementor
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACF_Relationship_Display {
    
    public function __construct() {
        add_action('elementor/widgets/widgets_registered', array($this, 'register_elementor_widgets'));
        add_action('elementor/dynamic_tags/register_tags', array($this, 'register_dynamic_tags'));
        add_shortcode('show_audiologist_locations', array($this, 'show_audiologist_locations_shortcode'));
        add_shortcode('show_store_audiologists', array($this, 'show_store_audiologists_shortcode'));
    }
    
    /**
     * Register Elementor widgets
     */
    public function register_elementor_widgets() {
        if (defined('ELEMENTOR_VERSION')) {
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new Audiologist_Locations_Widget());
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new Store_Audiologists_Widget());
        }
    }
    
    /**
     * Register Dynamic Tags
     */
    public function register_dynamic_tags($dynamic_tags) {
        $dynamic_tags->register_tag(new ACF_Relationship_Count_Tag());
        $dynamic_tags->register_tag(new ACF_Relationship_Names_Tag());
    }
    
    /**
     * Get all connected locations for an audiologist
     */
    public function get_audiologist_locations($audiologist_id) {
        $locations = array();
        
        // Check the main store_locations field first (newer field)
        $store_locations = get_field('store_locations', $audiologist_id);
        if ($store_locations && is_array($store_locations)) {
            $locations = array_merge($locations, $store_locations);
        }
        
        // Check individual location fields (legacy fields)
        for ($i = 1; $i <= 6; $i++) {
            $location = get_field('connected_locations_' . $i, $audiologist_id);
            if ($location && is_array($location) && !empty($location)) {
                $location_obj = $location[0]; // These fields have max 1
                if ($location_obj) {
                    $locations[] = $location_obj;
                }
            }
        }
        
        // Remove duplicates based on post ID
        $unique_locations = array();
        $seen_ids = array();
        
        foreach ($locations as $location) {
            $location_id = is_object($location) ? $location->ID : $location;
            if (!in_array($location_id, $seen_ids)) {
                $unique_locations[] = $location;
                $seen_ids[] = $location_id;
            }
        }
        
        return $unique_locations;
    }
    
    /**
     * Get all connected audiologists for a store
     */
    public function get_store_audiologists($store_id) {
        $audiologists = array();
        
        // Check the main associated_audiologist field first (newer field)
        $associated_audiologists = get_field('associated_audiologist', $store_id);
        if ($associated_audiologists && is_array($associated_audiologists)) {
            $audiologists = array_merge($audiologists, $associated_audiologists);
        }
        
        // Check individual URL fields for additional audiologists
        for ($i = 1; $i <= 10; $i++) {
            $audiologist_url = get_field('associated_audiologist_url_' . $i, $store_id);
            if ($audiologist_url) {
                $audiologist_id = url_to_postid($audiologist_url);
                if ($audiologist_id) {
                    $audiologist_obj = get_post($audiologist_id);
                    if ($audiologist_obj) {
                        $audiologists[] = $audiologist_obj;
                    }
                }
            }
        }
        
        // Remove duplicates based on post ID
        $unique_audiologists = array();
        $seen_ids = array();
        
        foreach ($audiologists as $audiologist) {
            $audiologist_id = is_object($audiologist) ? $audiologist->ID : $audiologist;
            if (!in_array($audiologist_id, $seen_ids)) {
                $unique_audiologists[] = $audiologist;
                $seen_ids[] = $audiologist_id;
            }
        }
        
        return $unique_audiologists;
    }
    
    /**
     * Format ACF field values for display
     */
    public function format_field_value($value, $field_object = null) {
        if (!$field_object) {
            return esc_html($value);
        }
        
        $field_type = $field_object['type'];
        
        switch ($field_type) {
            case 'image':
                if (is_array($value)) {
                    return '<img src="' . esc_url($value['sizes']['thumbnail']) . '" alt="' . esc_attr($value['alt']) . '">';
                }
                break;
                
            case 'file':
                if (is_array($value)) {
                    return '<a href="' . esc_url($value['url']) . '" target="_blank">' . esc_html($value['title']) . '</a>';
                }
                break;
                
            case 'url':
                return '<a href="' . esc_url($value) . '" target="_blank">' . esc_html($value) . '</a>';
                
            case 'email':
                return '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
                
            case 'textarea':
                return wpautop(esc_html($value));
                
            case 'select':
            case 'checkbox':
            case 'radio':
                if (is_array($value)) {
                    return esc_html(implode(', ', $value));
                }
                return esc_html($value);
                
            case 'true_false':
                return $value ? 'Yes' : 'No';
                
            case 'date_picker':
                return date_i18n(get_option('date_format'), strtotime($value));
                
            case 'date_time_picker':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($value));
                
            default:
                return esc_html($value);
        }
        
        return esc_html($value);
    }
    
    /**
     * Shortcode to display audiologist locations
     */
    public function show_audiologist_locations_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'fields' => ''
        ), $atts);
        
        $locations = $this->get_audiologist_locations($atts['post_id']);
        
        if (empty($locations)) {
            return '<p>No practice locations found.</p>';
        }
        
        $fields = $atts['fields'] ? explode(',', $atts['fields']) : array();
        
        $output = '<div class="audiologist-locations">';
        foreach ($locations as $location) {
            $location_id = is_object($location) ? $location->ID : $location;
            $output .= '<div class="location-item">';
            $output .= '<h4><a href="' . get_permalink($location_id) . '">' . get_the_title($location_id) . '</a></h4>';
            
            foreach ($fields as $field_name) {
                $field_name = trim($field_name);
                $field_value = get_field($field_name, $location_id);
                if ($field_value) {
                    $field_object = get_field_object($field_name, $location_id);
                    $field_label = $field_object ? $field_object['label'] : ucfirst(str_replace('_', ' ', $field_name));
                    $output .= '<p><strong>' . esc_html($field_label) . ':</strong> ' . $this->format_field_value($field_value, $field_object) . '</p>';
                }
            }
            
            $output .= '</div>';
        }
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Shortcode to display store audiologists
     */
    public function show_store_audiologists_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'fields' => ''
        ), $atts);
        
        $audiologists = $this->get_store_audiologists($atts['post_id']);
        
        if (empty($audiologists)) {
            return '<p>No audiologists found for this store.</p>';
        }
        
        $fields = $atts['fields'] ? explode(',', $atts['fields']) : array();
        
        $output = '<div class="store-audiologists">';
        foreach ($audiologists as $audiologist) {
            $audiologist_id = is_object($audiologist) ? $audiologist->ID : $audiologist;
            $output .= '<div class="audiologist-item">';
            $output .= '<h4><a href="' . get_permalink($audiologist_id) . '">' . get_the_title($audiologist_id) . '</a></h4>';
            
            foreach ($fields as $field_name) {
                $field_name = trim($field_name);
                $field_value = get_field($field_name, $audiologist_id);
                if ($field_value) {
                    $field_object = get_field_object($field_name, $audiologist_id);
                    $field_label = $field_object ? $field_object['label'] : ucfirst(str_replace('_', ' ', $field_name));
                    $output .= '<p><strong>' . esc_html($field_label) . ':</strong> ' . $this->format_field_value($field_value, $field_object) . '</p>';
                }
            }
            
            $output .= '</div>';
        }
        $output .= '</div>';
        
        return $output;
    }
}

// Initialize the plugin
new ACF_Relationship_Display();

/**
 * Dynamic Tag: Relationship Count
 */
class ACF_Relationship_Count_Tag extends \Elementor\Core\DynamicTags\Tag {
    
    public function get_name() {
        return 'acf-relationship-count';
    }
    
    public function get_title() {
        return 'ACF Relationship Count';
    }
    
    public function get_group() {
        return 'post';
    }
    
    public function get_categories() {
        return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY];
    }
    
    protected function _register_controls() {
        $this->add_control(
            'relationship_type',
            [
                'label' => 'Relationship Type',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'locations' => 'Audiologist Locations',
                    'audiologists' => 'Store Audiologists',
                ],
                'default' => 'locations',
            ]
        );
    }
    
    public function render() {
        $settings = $this->get_settings();
        $plugin = new ACF_Relationship_Display();
        
        if ($settings['relationship_type'] === 'locations') {
            $items = $plugin->get_audiologist_locations(get_the_ID());
            echo count($items) . ' Location' . (count($items) !== 1 ? 's' : '');
        } else {
            $items = $plugin->get_store_audiologists(get_the_ID());
            echo count($items) . ' Audiologist' . (count($items) !== 1 ? 's' : '');
        }
    }
}

/**
 * Dynamic Tag: Relationship Names
 */
class ACF_Relationship_Names_Tag extends \Elementor\Core\DynamicTags\Tag {
    
    public function get_name() {
        return 'acf-relationship-names';
    }
    
    public function get_title() {
        return 'ACF Relationship Names';
    }
    
    public function get_group() {
        return 'post';
    }
    
    public function get_categories() {
        return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY];
    }
    
    protected function _register_controls() {
        $this->add_control(
            'relationship_type',
            [
                'label' => 'Relationship Type',
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'locations' => 'Audiologist Locations',
                    'audiologists' => 'Store Audiologists',
                ],
                'default' => 'locations',
            ]
        );
        
        $this->add_control(
            'separator',
            [
                'label' => 'Separator',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => ', ',
            ]
        );
    }
    
    public function render() {
        $settings = $this->get_settings();
        $plugin = new ACF_Relationship_Display();
        
        if ($settings['relationship_type'] === 'locations') {
            $items = $plugin->get_audiologist_locations(get_the_ID());
        } else {
            $items = $plugin->get_store_audiologists(get_the_ID());
        }
        
        $names = array();
        foreach ($items as $item) {
            $item_id = is_object($item) ? $item->ID : $item;
            $names[] = get_the_title($item_id);
        }
        
        echo implode($settings['separator'], $names);
    }
}

/**
 * Elementor Widget: Audiologist Locations
 */
class Audiologist_Locations_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'audiologist_locations';
    }

    public function get_title() {
        return __('Audiologist Locations', 'acf-relationship-display');
    }

    public function get_icon() {
        return 'eicon-google-maps';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'acf-relationship-display'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => __('Title', 'acf-relationship-display'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Practice Locations', 'acf-relationship-display'),
            ]
        );

        $this->add_control(
            'show_featured_image',
            [
                'label' => __('Show Featured Image', 'acf-relationship-display'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'image_size',
            [
                'label' => __('Image Size', 'acf-relationship-display'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'medium',
                'options' => [
                    'thumbnail' => 'Thumbnail',
                    'medium' => 'Medium',
                    'large' => 'Large',
                    'full' => 'Full',
                ],
                'condition' => [
                    'show_featured_image' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_excerpt',
            [
                'label' => __('Show Excerpt', 'acf-relationship-display'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'custom_fields',
            [
                'label' => __('Custom Fields to Display', 'acf-relationship-display'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'placeholder' => __('address, phone, email, opening_hours', 'acf-relationship-display'),
                'description' => __('Enter ACF field names separated by commas', 'acf-relationship-display'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $plugin = new ACF_Relationship_Display();
        $locations = $plugin->get_audiologist_locations(get_the_ID());
        
        if (!empty($settings['title'])) {
            echo '<h3>' . esc_html($settings['title']) . '</h3>';
        }
        
        if (empty($locations)) {
            echo '<p>No practice locations found.</p>';
            return;
        }
        
        $custom_fields = $settings['custom_fields'] ? array_map('trim', explode(',', $settings['custom_fields'])) : array();
        
        echo '<div class="audiologist-locations">';
        foreach ($locations as $location) {
            $location_id = is_object($location) ? $location->ID : $location;
            echo '<div class="location-item">';
            
            // Featured Image
            if ($settings['show_featured_image'] === 'yes' && has_post_thumbnail($location_id)) {
                echo '<div class="location-image">';
                echo get_the_post_thumbnail($location_id, $settings['image_size']);
                echo '</div>';
            }
            
            echo '<div class="location-content">';
            echo '<h4><a href="' . get_permalink($location_id) . '">' . get_the_title($location_id) . '</a></h4>';
            
            // Excerpt
            if ($settings['show_excerpt'] === 'yes') {
                $excerpt = get_the_excerpt($location_id);
                if ($excerpt) {
                    echo '<p class="location-excerpt">' . esc_html($excerpt) . '</p>';
                }
            }
            
            // Custom Fields
            foreach ($custom_fields as $field_name) {
                $field_value = get_field($field_name, $location_id);
                if ($field_value) {
                    $field_object = get_field_object($field_name, $location_id);
                    $field_label = $field_object ? $field_object['label'] : ucfirst(str_replace('_', ' ', $field_name));
                    echo '<div class="custom-field">';
                    echo '<span class="field-label">' . esc_html($field_label) . ':</span> ';
                    echo '<span class="field-value">' . $plugin->format_field_value($field_value, $field_object) . '</span>';
                    echo '</div>';
                }
            }
            
            echo '</div>'; // location-content
            echo '</div>'; // location-item
        }
        echo '</div>';
    }
}

/**
 * Elementor Widget: Store Audiologists
 */
class Store_Audiologists_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'store_audiologists';
    }

    public function get_title() {
        return __('Store Audiologists', 'acf-relationship-display');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'acf-relationship-display'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => __('Title', 'acf-relationship-display'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Our Audiologists', 'acf-relationship-display'),
            ]
        );

        $this->add_control(
            'show_featured_image',
            [
                'label' => __('Show Featured Image', 'acf-relationship-display'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'image_size',
            [
                'label' => __('Image Size', 'acf-relationship-display'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'medium',
                'options' => [
                    'thumbnail' => 'Thumbnail',
                    'medium' => 'Medium',
                    'large' => 'Large',
                    'full' => 'Full',
                ],
                'condition' => [
                    'show_featured_image' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_excerpt',
            [
                'label' => __('Show Excerpt', 'acf-relationship-display'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'custom_fields',
            [
                'label' => __('Custom Fields to Display', 'acf-relationship-display'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'placeholder' => __('specialty, years_experience, certifications, phone', 'acf-relationship-display'),
                'description' => __('Enter ACF field names separated by commas', 'acf-relationship-display'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $plugin = new ACF_Relationship_Display();
        $audiologists = $plugin->get_store_audiologists(get_the_ID());
        
        if (!empty($settings['title'])) {
            echo '<h3>' . esc_html($settings['title']) . '</h3>';
        }
        
        if (empty($audiologists)) {
            echo '<p>No audiologists found for this store.</p>';
            return;
        }
        
        $custom_fields = $settings['custom_fields'] ? array_map('trim', explode(',', $settings['custom_fields'])) : array();
        
        echo '<div class="store-audiologists">';
        foreach ($audiologists as $audiologist) {
            $audiologist_id = is_object($audiologist) ? $audiologist->ID : $audiologist;
            echo '<div class="audiologist-item">';
            
            // Featured Image
            if ($settings['show_featured_image'] === 'yes' && has_post_thumbnail($audiologist_id)) {
                echo '<div class="audiologist-image">';
                echo get_the_post_thumbnail($audiologist_id, $settings['image_size']);
                echo '</div>';
            }
            
            echo '<div class="audiologist-content">';
            echo '<h4><a href="' . get_permalink($audiologist_id) . '">' . get_the_title($audiologist_id) . '</a></h4>';
            
            // Excerpt
            if ($settings['show_excerpt'] === 'yes') {
                $excerpt = get_the_excerpt($audiologist_id);
                if ($excerpt) {
                    echo '<p class="audiologist-excerpt">' . esc_html($excerpt) . '</p>';
                }
            }
            
            // Custom Fields
            foreach ($custom_fields as $field_name) {
                $field_value = get_field($field_name, $audiologist_id);
                if ($field_value) {
                    $field_object = get_field_object($field_name, $audiologist_id);
                    $field_label = $field_object ? $field_object['label'] : ucfirst(str_replace('_', ' ', $field_name));
                    echo '<div class="custom-field">';
                    echo '<span class="field-label">' . esc_html($field_label) . ':</span> ';
                    echo '<span class="field-value">' . $plugin->format_field_value($field_value, $field_object) . '</span>';
                    echo '</div>';
                }
            }
            
            echo '</div>'; // audiologist-content
            echo '</div>'; // audiologist-item
        }
        echo '</div>';
    }
}

/**
 * Helper functions for theme development
 */
function get_audiologist_practice_locations($audiologist_id = null) {
    if (!$audiologist_id) {
        $audiologist_id = get_the_ID();
    }
    $plugin = new ACF_Relationship_Display();
    return $plugin->get_audiologist_locations($audiologist_id);
}

function get_store_audiologists($store_id = null) {
    if (!$store_id) {
        $store_id = get_the_ID();
    }
    $plugin = new ACF_Relationship_Display();
    return $plugin->get_store_audiologists($store_id);
}
?>