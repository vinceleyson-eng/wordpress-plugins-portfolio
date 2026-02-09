<?php
/**
 * Quiz REST API Extensions
 * Register custom meta fields for REST API access
 */

class Quiz_REST_API {
    
    public function __construct() {
        add_action('init', array($this, 'register_meta_fields'));
    }
    
    /**
     * Register meta fields for REST API
     */
    public function register_meta_fields() {
        // Register quiz question options
        register_post_meta('quiz_question', '_quiz_question_options', array(
            'show_in_rest' => array(
                'schema' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'text' => array('type' => 'string'),
                            'value' => array('type' => 'string')
                        )
                    )
                )
            ),
            'single' => true,
            'type' => 'array'
        ));
        
        // Register correct answer
        register_post_meta('quiz_question', '_quiz_correct_answer', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string'
        ));
        
        // Register quiz questions array
        register_post_meta('quiz', '_quiz_questions', array(
            'show_in_rest' => array(
                'schema' => array(
                    'type' => 'array',
                    'items' => array('type' => 'integer')
                )
            ),
            'single' => true,
            'type' => 'array'
        ));
        
        // Register quiz result mappings
        register_post_meta('quiz', '_quiz_result_mappings', array(
            'show_in_rest' => array(
                'schema' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object'
                    )
                )
            ),
            'single' => true,
            'type' => 'array'
        ));
    }
}
