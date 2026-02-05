<?php
/**
 * Plugin Name: Skapik Meta Description Bulk Updater v2
 * Description: Bulk update Yoast meta descriptions for posts and pages via REST API
 * Version: 2.0
 * Author: Vince L
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('skapik/v1', '/bulk-update-meta', array(
        'methods' => 'POST',
        'callback' => 'skapik_bulk_update_meta_descriptions',
        'permission_callback' => '__return_true', // Public access
    ));
});

function skapik_bulk_update_meta_descriptions($request) {
    $pages = $request->get_param('pages');
    
    if (empty($pages) || !is_array($pages)) {
        return new WP_Error('invalid_data', 'Pages array is required', array('status' => 400));
    }

    $results = array(
        'success' => array(),
        'failed' => array(),
    );

    foreach ($pages as $page_data) {
        $url = isset($page_data['url']) ? $page_data['url'] : '';
        $meta_description = isset($page_data['meta_description']) ? $page_data['meta_description'] : '';

        if (empty($url) || empty($meta_description)) {
            $results['failed'][] = array(
                'url' => $url,
                'error' => 'URL and meta_description are required'
            );
            continue;
        }

        // Get post/page ID from URL
        $post_id = url_to_postid($url);
        
        if (!$post_id) {
            $results['failed'][] = array(
                'url' => $url,
                'error' => 'Could not find post/page with this URL'
            );
            continue;
        }

        // Update Yoast meta description
        $updated = update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);

        if ($updated !== false) {
            $results['success'][] = array(
                'url' => $url,
                'post_id' => $post_id,
                'meta_description' => $meta_description
            );
        } else {
            $results['failed'][] = array(
                'url' => $url,
                'post_id' => $post_id,
                'error' => 'Failed to update meta description'
            );
        }
    }

    return rest_ensure_response(array(
        'total' => count($pages),
        'success_count' => count($results['success']),
        'failed_count' => count($results['failed']),
        'results' => $results
    ));
}
