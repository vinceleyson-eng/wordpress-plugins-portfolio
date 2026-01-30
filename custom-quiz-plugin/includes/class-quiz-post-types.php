<?php
/**
 * Quiz Post Types Class
 */

class Quiz_Post_Types {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
    }
    
    /**
     * Register custom post types
     */
    public function register_post_types() {
        // Register Quiz Question Post Type
        $question_labels = array(
            'name' => 'Quiz Questions',
            'singular_name' => 'Quiz Question',
            'menu_name' => 'Quiz Questions',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Question',
            'edit_item' => 'Edit Question',
            'new_item' => 'New Question',
            'view_item' => 'View Question',
            'search_items' => 'Search Questions',
            'not_found' => 'No questions found',
            'not_found_in_trash' => 'No questions found in trash'
        );
        
        $question_args = array(
            'labels' => $question_labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-editor-help',
            'supports' => array('title', 'editor'),
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'menu_position' => 20
        );
        
        register_post_type('quiz_question', $question_args);
        
        // Register Quiz Post Type
        $quiz_labels = array(
            'name' => 'Quizzes',
            'singular_name' => 'Quiz',
            'menu_name' => 'Quizzes',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Quiz',
            'edit_item' => 'Edit Quiz',
            'new_item' => 'New Quiz',
            'view_item' => 'View Quiz',
            'search_items' => 'Search Quizzes',
            'not_found' => 'No quizzes found',
            'not_found_in_trash' => 'No quizzes found in trash'
        );
        
        $quiz_args = array(
            'labels' => $quiz_labels,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => array('title', 'editor'),
            'has_archive' => true,
            'rewrite' => array('slug' => 'quiz'),
            'capability_type' => 'post',
            'menu_position' => 21
        );
        
        register_post_type('quiz', $quiz_args);
    }
}
