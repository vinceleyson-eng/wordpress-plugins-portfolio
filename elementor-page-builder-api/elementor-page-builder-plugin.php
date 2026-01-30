<?php
/**
 * Plugin Name: Elementor Page Builder API
 * Description: API endpoint to insert Elementor page data programmatically
 * Version: 1.0
 */

add_action('rest_api_init', function () {
    register_rest_route('elementor-builder/v1', '/create-page', array(
        'methods' => 'POST',
        'callback' => 'elementor_create_page_callback',
        'permission_callback' => '__return_true' // Public endpoint for now
    ));
});

function elementor_create_page_callback($request) {
    $params = $request->get_json_params();
    
    if (!isset($params['page_id']) || !isset($params['elementor_data'])) {
        return new WP_Error('missing_params', 'Missing page_id or elementor_data', array('status' => 400));
    }
    
    $page_id = intval($params['page_id']);
    $elementor_data = $params['elementor_data'];
    $template = isset($params['template']) ? $params['template'] : 'elementor_canvas';
    
    // Check if page exists
    $page = get_post($page_id);
    if (!$page) {
        return new WP_Error('page_not_found', 'Page not found', array('status' => 404));
    }
    
    // Set page template
    update_post_meta($page_id, '_wp_page_template', $template);
    
    // Set Elementor meta fields
    update_post_meta($page_id, '_elementor_edit_mode', 'builder');
    update_post_meta($page_id, '_elementor_template_type', 'wp-page');
    update_post_meta($page_id, '_elementor_version', '3.16.0');
    
    // Insert Elementor data
    if (is_string($elementor_data)) {
        $elementor_data = json_decode($elementor_data, true);
    }
    
    update_post_meta($page_id, '_elementor_data', wp_slash(json_encode($elementor_data)));
    
    // Clear Elementor cache
    if (class_exists('\Elementor\Plugin')) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    
    return array(
        'success' => true,
        'page_id' => $page_id,
        'edit_url' => admin_url('post.php?post=' . $page_id . '&action=elementor'),
        'view_url' => get_permalink($page_id),
        'message' => 'Elementor page structure created successfully'
    );
}
