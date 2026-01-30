<?php
/**
 * Plugin Name: Skapik Meta Description Bulk Updater v3
 * Description: Bulk update meta descriptions for Yoast, Rank Math, and All in One SEO via REST API
 * Version: 3.0
 * Author: Vince
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

/**
 * Detect which SEO plugin is active
 */
function skapik_detect_seo_plugin() {
    // Check Yoast SEO
    if (defined('WPSEO_VERSION')) {
        return 'yoast';
    }
    
    // Check Rank Math
    if (defined('RANK_MATH_VERSION')) {
        return 'rankmath';
    }
    
    // Check All in One SEO
    if (defined('AIOSEO_VERSION')) {
        return 'allinone';
    }
    
    return 'none';
}

/**
 * Get the correct meta key for the active SEO plugin
 */
function skapik_get_meta_key($plugin = null) {
    if (!$plugin) {
        $plugin = skapik_detect_seo_plugin();
    }
    
    $meta_keys = array(
        'yoast' => '_yoast_wpseo_metadesc',
        'rankmath' => 'rank_math_description',
        'allinone' => '_aioseo_description',
    );
    
    return isset($meta_keys[$plugin]) ? $meta_keys[$plugin] : null;
}

function skapik_bulk_update_meta_descriptions($request) {
    $pages = $request->get_param('pages');
    $force_plugin = $request->get_param('seo_plugin'); // Optional: yoast, rankmath, allinone
    
    if (empty($pages) || !is_array($pages)) {
        return new WP_Error('invalid_data', 'Pages array is required', array('status' => 400));
    }

    // Detect SEO plugin
    $detected_plugin = skapik_detect_seo_plugin();
    $active_plugin = $force_plugin ? $force_plugin : $detected_plugin;
    $meta_key = skapik_get_meta_key($active_plugin);
    
    if (!$meta_key) {
        return new WP_Error(
            'no_seo_plugin', 
            'No supported SEO plugin detected. Supported: Yoast SEO, Rank Math, All in One SEO', 
            array('status' => 400)
        );
    }

    $results = array(
        'seo_plugin' => $active_plugin,
        'meta_key' => $meta_key,
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

        // Special handling for All in One SEO (uses JSON format)
        if ($active_plugin === 'allinone') {
            $aioseo_data = get_post_meta($post_id, '_aioseo_description', true);
            
            // AIOSEO stores data as JSON or plain text
            if (is_array($aioseo_data)) {
                $aioseo_data['description'] = $meta_description;
                $updated = update_post_meta($post_id, '_aioseo_description', $aioseo_data);
            } else {
                // Simple text format
                $updated = update_post_meta($post_id, '_aioseo_description', $meta_description);
            }
        } else {
            // Yoast and Rank Math use simple text format
            $updated = update_post_meta($post_id, $meta_key, $meta_description);
        }

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
