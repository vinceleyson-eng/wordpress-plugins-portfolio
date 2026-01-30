<?php
/*
Plugin Name: Page Status Updater
Description: Simple API to update page status
Version: 1.0
*/

add_action('rest_api_init', function() {
    register_rest_route('page-status/v1', '/update', array(
        'methods' => 'POST',
        'callback' => 'update_page_status',
        'permission_callback' => '__return_true'
    ));
});

function update_page_status($request) {
    $page_id = $request->get_param('page_id');
    $status = $request->get_param('status');
    
    if (!$page_id || !$status) {
        return new WP_Error('missing_params', 'Missing page_id or status', array('status' => 400));
    }
    
    $result = wp_update_post(array(
        'ID' => $page_id,
        'post_status' => $status
    ));
    
    if (is_wp_error($result)) {
        return $result;
    }
    
    $page = get_post($page_id);
    
    return array(
        'success' => true,
        'page_id' => $page_id,
        'status' => $page->post_status,
        'view_url' => get_permalink($page_id)
    );
}
