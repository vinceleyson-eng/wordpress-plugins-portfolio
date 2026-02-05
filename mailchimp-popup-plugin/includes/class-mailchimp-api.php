<?php
/**
 * Mailchimp API Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCP_Mailchimp_API {
    
    private $api_key;
    private $data_center;
    private $api_url;
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
        
        // Extract data center from API key
        $key_parts = explode('-', $api_key);
        $this->data_center = isset($key_parts[1]) ? $key_parts[1] : 'us1';
        $this->api_url = 'https://' . $this->data_center . '.api.mailchimp.com/3.0/';
    }
    
    /**
     * Make API request
     */
    private function request($endpoint, $method = 'GET', $data = array()) {
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $this->api_key),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        
        return array(
            'success' => $code >= 200 && $code < 300,
            'code' => $code,
            'data' => $body
        );
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $result = $this->request('ping');
        
        if ($result['success']) {
            return array(
                'success' => true,
                'message' => 'API connection successful!'
            );
        }
        
        return array(
            'success' => false,
            'message' => isset($result['data']['detail']) ? $result['data']['detail'] : 'Connection failed'
        );
    }
    
    /**
     * Get all lists
     */
    public function get_lists() {
        $result = $this->request('lists?count=100');
        
        if ($result['success'] && isset($result['data']['lists'])) {
            return $result['data']['lists'];
        }
        
        return array();
    }
    
    /**
     * Subscribe email to list
     */
    public function subscribe($list_id, $email, $merge_fields = array(), $tags = array()) {
        $subscriber_hash = md5(strtolower($email));
        
        $data = array(
            'email_address' => $email,
            'status' => 'subscribed'
        );
        
        if (!empty($merge_fields)) {
            $data['merge_fields'] = $merge_fields;
        }
        
        if (!empty($tags)) {
            $data['tags'] = $tags;
        }
        
        // Use PUT to add or update
        $result = $this->request('lists/' . $list_id . '/members/' . $subscriber_hash, 'PUT', $data);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'message' => 'Successfully subscribed!'
            );
        }
        
        // Handle specific errors
        $error_message = 'Subscription failed. Please try again.';
        
        if (isset($result['data']['detail'])) {
            $detail = $result['data']['detail'];
            
            if (strpos($detail, 'already a list member') !== false) {
                return array(
                    'success' => true,
                    'message' => 'You\'re already subscribed!'
                );
            }
            
            if (strpos($detail, 'compliance') !== false || strpos($detail, 'fake') !== false) {
                $error_message = 'This email cannot be subscribed. Please use a different email.';
            }
        }
        
        return array(
            'success' => false,
            'message' => $error_message
        );
    }
    
    /**
     * Get member info
     */
    public function get_member($list_id, $email) {
        $subscriber_hash = md5(strtolower($email));
        $result = $this->request('lists/' . $list_id . '/members/' . $subscriber_hash);
        
        if ($result['success']) {
            return $result['data'];
        }
        
        return null;
    }
    
    /**
     * Update member tags
     */
    public function update_tags($list_id, $email, $tags) {
        $subscriber_hash = md5(strtolower($email));
        
        $tag_data = array_map(function($tag) {
            return array(
                'name' => $tag,
                'status' => 'active'
            );
        }, $tags);
        
        $result = $this->request(
            'lists/' . $list_id . '/members/' . $subscriber_hash . '/tags',
            'POST',
            array('tags' => $tag_data)
        );
        
        return $result['success'];
    }
}
