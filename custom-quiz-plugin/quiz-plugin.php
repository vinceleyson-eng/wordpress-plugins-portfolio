<?php
/**
 * Plugin Name: Custom Quiz Plugin
 * Plugin URI: https://example.com
 * Description: Create quizzes with custom questions and route users to specific result pages based on their answers
 * Version: 1.4.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: custom-quiz
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CUSTOM_QUIZ_VERSION', '1.4.0');
define('CUSTOM_QUIZ_PATH', plugin_dir_path(__FILE__));
define('CUSTOM_QUIZ_URL', plugin_dir_url(__FILE__));

// Include required files
require_once CUSTOM_QUIZ_PATH . 'includes/class-quiz-post-types.php';
require_once CUSTOM_QUIZ_PATH . 'includes/class-quiz-meta-boxes.php';
require_once CUSTOM_QUIZ_PATH . 'includes/class-quiz-shortcode.php';
require_once CUSTOM_QUIZ_PATH . 'includes/class-quiz-ajax.php';
require_once CUSTOM_QUIZ_PATH . 'includes/class-quiz-import-export.php';

/**
 * Initialize the plugin
 */
function custom_quiz_init() {
    new Quiz_Post_Types();
    new Quiz_Meta_Boxes();
    new Quiz_Shortcode();
    new Quiz_Ajax();
    new Quiz_Import_Export();
}
add_action('plugins_loaded', 'custom_quiz_init');

/**
 * Activation hook
 */
function custom_quiz_activate() {
    // Register post types
    $post_types = new Quiz_Post_Types();
    $post_types->register_post_types();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'custom_quiz_activate');

/**
 * Deactivation hook
 */
function custom_quiz_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'custom_quiz_deactivate');

/**
 * Enqueue admin scripts and styles
 */
function custom_quiz_admin_scripts($hook) {
    global $post_type;
    
    if ($post_type === 'quiz_question' || $post_type === 'quiz') {
        wp_enqueue_style('custom-quiz-admin', CUSTOM_QUIZ_URL . 'assets/css/admin-style.css', array(), CUSTOM_QUIZ_VERSION);
        wp_enqueue_script('custom-quiz-admin', CUSTOM_QUIZ_URL . 'assets/js/admin-script.js', array('jquery'), CUSTOM_QUIZ_VERSION, true);
    }
}
add_action('admin_enqueue_scripts', 'custom_quiz_admin_scripts');

/**
 * Enqueue frontend scripts and styles
 */
function custom_quiz_frontend_scripts() {
    wp_enqueue_style('custom-quiz-frontend', CUSTOM_QUIZ_URL . 'assets/css/frontend-style.css', array(), CUSTOM_QUIZ_VERSION);
    wp_enqueue_script('custom-quiz-frontend', CUSTOM_QUIZ_URL . 'assets/js/frontend-script.js', array('jquery'), CUSTOM_QUIZ_VERSION, true);
    
    // Localize script for AJAX
    wp_localize_script('custom-quiz-frontend', 'quizAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('quiz_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'custom_quiz_frontend_scripts');
