<?php
/**
 * Quiz Meta Boxes Class
 */

class Quiz_Meta_Boxes {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_question_meta'));
        add_action('save_post', array($this, 'save_quiz_meta'));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        // Question meta boxes
        add_meta_box(
            'quiz_question_options',
            'Question Options',
            array($this, 'render_question_options_meta_box'),
            'quiz_question',
            'normal',
            'high'
        );
        
        // Quiz meta boxes
        add_meta_box(
            'quiz_questions',
            'Quiz Questions',
            array($this, 'render_quiz_questions_meta_box'),
            'quiz',
            'normal',
            'high'
        );
        
        add_meta_box(
            'quiz_result_mapping',
            'Result Page Mapping',
            array($this, 'render_result_mapping_meta_box'),
            'quiz',
            'normal',
            'high'
        );
    }
    
    /**
     * Render question options meta box
     */
    public function render_question_options_meta_box($post) {
        wp_nonce_field('quiz_question_meta', 'quiz_question_nonce');
        
        $options = get_post_meta($post->ID, '_quiz_question_options', true);
        if (!is_array($options)) {
            $options = array(
                array('text' => '', 'value' => 'A'),
                array('text' => '', 'value' => 'B')
            );
        }
        
        $correct_answer = get_post_meta($post->ID, '_quiz_correct_answer', true);
        
        ?>
        <div id="quiz-question-options">
            <p><strong>Add answer options for this question:</strong></p>
            <div class="quiz-options-container">
                <?php foreach ($options as $index => $option): ?>
                <div class="quiz-option-row" data-index="<?php echo $index; ?>">
                    <label>Option <?php echo chr(65 + $index); ?>:</label>
                    <input type="text" name="quiz_option_text[]" value="<?php echo esc_attr($option['text']); ?>" placeholder="Enter option text" style="width: 60%;" />
                    <input type="text" name="quiz_option_value[]" value="<?php echo esc_attr($option['value']); ?>" placeholder="Value (e.g., A)" style="width: 15%;" />
                    <button type="button" class="button remove-option">Remove</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="add-quiz-option">Add Option</button>
            
            <hr style="margin: 20px 0;" />
            
            <p><strong>Correct Answer (for showing after submission):</strong></p>
            <select name="quiz_correct_answer" style="width: 200px;">
                <option value="">-- Select correct answer --</option>
                <?php foreach ($options as $index => $option): ?>
                <option value="<?php echo esc_attr($option['value']); ?>" <?php selected($correct_answer, $option['value']); ?>>
                    Option <?php echo chr(65 + $index); ?> (<?php echo esc_attr($option['value']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <p style="font-size: 12px; color: #666; margin-top: 5px;">
                <strong>Tip:</strong> The question's main content (editor above) will be shown as the answer description/explanation after submission.
            </p>
        </div>
        <style>
            .quiz-option-row {
                margin-bottom: 10px;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #ddd;
            }
            .quiz-option-row label {
                display: inline-block;
                width: 80px;
                font-weight: bold;
            }
        </style>
        <?php
    }
    
    /**
     * Render quiz questions meta box
     */
    public function render_quiz_questions_meta_box($post) {
        wp_nonce_field('quiz_meta', 'quiz_nonce');
        
        $selected_questions = get_post_meta($post->ID, '_quiz_questions', true);
        if (!is_array($selected_questions)) {
            $selected_questions = array();
        }
        
        $questions = get_posts(array(
            'post_type' => 'quiz_question',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <div id="quiz-questions-selector">
            <p><strong>Select questions to include in this quiz:</strong></p>
            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                <?php if ($questions): ?>
                    <?php foreach ($questions as $question): ?>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" name="quiz_questions[]" value="<?php echo $question->ID; ?>" 
                            <?php checked(in_array($question->ID, $selected_questions)); ?> />
                        <?php echo esc_html($question->post_title); ?>
                    </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No questions found. <a href="<?php echo admin_url('post-new.php?post_type=quiz_question'); ?>">Create a question</a> first.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render result mapping meta box
     */
    public function render_result_mapping_meta_box($post) {
        $result_mappings = get_post_meta($post->ID, '_quiz_result_mappings', true);
        if (!is_array($result_mappings)) {
            $result_mappings = array();
        }
        
        // Get selected questions count
        $selected_questions = get_post_meta($post->ID, '_quiz_questions', true);
        $total_questions = is_array($selected_questions) ? count($selected_questions) : 0;
        
        // Get all published pages (including drafts for flexibility)
        $pages = get_pages(array(
            'post_status' => array('publish', 'draft'),
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
            'hierarchical' => 0,
            'number' => 0 // Get all pages
        ));
        
        // Fallback: try getting pages another way if the above returns empty
        if (empty($pages)) {
            $pages = get_posts(array(
                'post_type' => 'page',
                'post_status' => array('publish', 'draft'),
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ));
        }
        
        ?>
        <div id="quiz-result-mappings">
            <p><strong>Map scores to result pages:</strong></p>
            
            <div style="padding: 12px; background: #e7f3ff; border: 1px solid #b3d7ff; border-radius: 4px; margin-bottom: 15px;">
                <p style="margin: 0 0 8px 0; font-weight: bold; color: #0056b3;">📊 Score-Based Routing</p>
                <p style="margin: 0; font-size: 13px; color: #333;">
                    This quiz has <strong><?php echo $total_questions; ?> question(s)</strong>. 
                    Users are scored based on how many <strong>correct answers</strong> they get.
                </p>
            </div>
            
            <?php if (empty($pages)): ?>
            <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 15px;">
                <p style="margin: 0; color: #856404;">
                    <strong>⚠️ No pages found!</strong> You need to create result pages first. 
                    <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" target="_blank">Create a new page</a>
                </p>
            </div>
            <?php else: ?>
            <div style="padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; margin-bottom: 15px; font-size: 12px;">
                ℹ️ Found <?php echo count($pages); ?> page(s) available for results
            </div>
            <?php endif; ?>
            
            <div class="result-mappings-container">
                <?php foreach ($result_mappings as $index => $mapping): 
                    // Parse existing value for min/max
                    $condition_value = $mapping['condition_value'] ?? '';
                    $min_score = 0;
                    $max_score = $total_questions;
                    if (strpos($condition_value, '-') !== false) {
                        $parts = explode('-', $condition_value);
                        $min_score = intval($parts[0]);
                        $max_score = isset($parts[1]) ? intval($parts[1]) : $total_questions;
                    }
                ?>
                <div class="result-mapping-row" data-index="<?php echo $index; ?>">
                    <div class="mapping-row-inner">
                        <div class="mapping-field">
                            <label>Rule Name:</label>
                            <input type="text" name="result_rule_name[]" value="<?php echo esc_attr($mapping['name'] ?? ''); ?>" placeholder="e.g., Perfect Score" />
                        </div>
                        
                        <div class="mapping-field">
                            <label>Condition:</label>
                            <select name="result_condition_type[]" class="condition-type-select">
                                <option value="correct_answers" <?php selected($mapping['condition_type'] ?? '', 'correct_answers'); ?>>Correct Answers (Score)</option>
                                <option value="most_common" <?php selected($mapping['condition_type'] ?? '', 'most_common'); ?>>Most Common Answer</option>
                                <option value="contains" <?php selected($mapping['condition_type'] ?? '', 'contains'); ?>>Contains Value</option>
                            </select>
                        </div>
                        
                        <!-- Score Range Dropdowns (shown for correct_answers) -->
                        <div class="mapping-field score-range-fields" style="<?php echo ($mapping['condition_type'] ?? 'correct_answers') === 'correct_answers' ? '' : 'display:none;'; ?>">
                            <label>Score from:</label>
                            <select name="result_min_score[]" class="min-score-select">
                                <?php for ($i = 0; $i <= $total_questions; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($min_score, $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span style="margin: 0 5px;">to</span>
                            <select name="result_max_score[]" class="max-score-select">
                                <?php for ($i = 0; $i <= $total_questions; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($max_score, $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span style="margin-left: 5px; color: #666;">correct</span>
                        </div>
                        
                        <!-- Text input (shown for other conditions) -->
                        <div class="mapping-field text-value-field" style="<?php echo ($mapping['condition_type'] ?? 'correct_answers') !== 'correct_answers' ? '' : 'display:none;'; ?>">
                            <label>Value:</label>
                            <input type="text" name="result_condition_value_text[]" value="<?php echo esc_attr($condition_value); ?>" placeholder="e.g., A" />
                        </div>
                        
                        <!-- Hidden field to store the actual value -->
                        <input type="hidden" name="result_condition_value[]" class="condition-value-hidden" value="<?php echo esc_attr($condition_value); ?>" />
                        
                        <div class="mapping-field">
                            <label>Result Page:</label>
                            <select name="result_page_id[]">
                                <option value="">Select a page...</option>
                                <?php foreach ($pages as $page): ?>
                                <option value="<?php echo $page->ID; ?>" <?php selected($mapping['page_id'] ?? '', $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mapping-field">
                            <button type="button" class="button remove-mapping">Remove</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" class="button button-primary" id="add-result-mapping">+ Add Result Mapping</button>
            
            <!-- Hidden template for page options (used by JavaScript) -->
            <select id="page-options-template" style="display: none;">
                <option value="">Select a page...</option>
                <?php foreach ($pages as $page): ?>
                <option value="<?php echo $page->ID; ?>">
                    <?php echo esc_html($page->post_title); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <input type="hidden" id="total-questions-count" value="<?php echo $total_questions; ?>" />
        </div>
        
        <style>
            .result-mapping-row {
                margin-bottom: 15px;
                padding: 15px;
                background: #f9f9f9;
                border: 1px solid #ddd;
            }
            .result-mapping-row label {
                display: inline-block;
                margin-right: 5px;
                margin-left: 15px;
                font-weight: bold;
            }
            .result-mapping-row label:first-child {
                margin-left: 0;
            }
        </style>
        <?php
    }
    
    /**
     * Save question meta
     */
    public function save_question_meta($post_id) {
        if (!isset($_POST['quiz_question_nonce']) || !wp_verify_nonce($_POST['quiz_question_nonce'], 'quiz_question_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (get_post_type($post_id) !== 'quiz_question') {
            return;
        }
        
        // Save question options
        if (isset($_POST['quiz_option_text']) && isset($_POST['quiz_option_value'])) {
            $options = array();
            $texts = $_POST['quiz_option_text'];
            $values = $_POST['quiz_option_value'];
            
            foreach ($texts as $index => $text) {
                if (!empty($text)) {
                    $options[] = array(
                        'text' => sanitize_text_field($text),
                        'value' => sanitize_text_field($values[$index])
                    );
                }
            }
            
            update_post_meta($post_id, '_quiz_question_options', $options);
        }
        
        // Save correct answer
        if (isset($_POST['quiz_correct_answer'])) {
            update_post_meta($post_id, '_quiz_correct_answer', sanitize_text_field($_POST['quiz_correct_answer']));
        }
    }
    
    /**
     * Save quiz meta
     */
    public function save_quiz_meta($post_id) {
        if (!isset($_POST['quiz_nonce']) || !wp_verify_nonce($_POST['quiz_nonce'], 'quiz_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (get_post_type($post_id) !== 'quiz') {
            return;
        }
        
        // Save selected questions
        $questions = isset($_POST['quiz_questions']) ? array_map('intval', $_POST['quiz_questions']) : array();
        update_post_meta($post_id, '_quiz_questions', $questions);
        
        // Save result mappings
        if (isset($_POST['result_rule_name'])) {
            $mappings = array();
            $names = $_POST['result_rule_name'];
            $condition_types = $_POST['result_condition_type'];
            $condition_values = $_POST['result_condition_value'];
            $page_ids = $_POST['result_page_id'];
            
            foreach ($names as $index => $name) {
                if (!empty($name) && !empty($page_ids[$index])) {
                    $mappings[] = array(
                        'name' => sanitize_text_field($name),
                        'condition_type' => sanitize_text_field($condition_types[$index]),
                        'condition_value' => sanitize_text_field($condition_values[$index]),
                        'page_id' => intval($page_ids[$index])
                    );
                }
            }
            
            update_post_meta($post_id, '_quiz_result_mappings', $mappings);
        }
    }
}
