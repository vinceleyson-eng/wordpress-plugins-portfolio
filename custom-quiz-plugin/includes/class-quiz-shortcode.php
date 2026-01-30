<?php
/**
 * Quiz Shortcode Class
 */

class Quiz_Shortcode {
    
    public function __construct() {
        add_shortcode('quiz', array($this, 'render_quiz'));
    }
    
    /**
     * Render quiz shortcode
     * Usage: [quiz id="123"]
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts);
        
        $quiz_id = intval($atts['id']);
        
        if (!$quiz_id) {
            return '<p>Please provide a quiz ID.</p>';
        }
        
        $quiz = get_post($quiz_id);
        
        if (!$quiz || $quiz->post_type !== 'quiz') {
            return '<p>Quiz not found.</p>';
        }
        
        $question_ids = get_post_meta($quiz_id, '_quiz_questions', true);
        
        if (empty($question_ids)) {
            return '<p>This quiz has no questions yet.</p>';
        }
        
        $total_questions = count($question_ids);
        
        ob_start();
        ?>
        <div class="custom-quiz-container" data-quiz-id="<?php echo $quiz_id; ?>" data-total-questions="<?php echo $total_questions; ?>">
            <div class="quiz-header">
                <h2><?php echo esc_html($quiz->post_title); ?></h2>
                <?php if ($quiz->post_content): ?>
                <div class="quiz-description">
                    <?php echo wpautop($quiz->post_content); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Progress Bar -->
            <div class="quiz-progress-container">
                <div class="quiz-progress-bar">
                    <div class="quiz-progress-fill" style="width: 0%;"></div>
                </div>
                <div class="quiz-progress-text">Question <span class="current-question">1</span> of <?php echo $total_questions; ?></div>
            </div>
            
            <form class="quiz-form" id="quiz-form-<?php echo $quiz_id; ?>">
                <?php foreach ($question_ids as $index => $question_id): 
                    $question = get_post($question_id);
                    if (!$question) continue;
                    
                    $options = get_post_meta($question_id, '_quiz_question_options', true);
                    if (!is_array($options)) continue;
                    
                    $correct_answer = get_post_meta($question_id, '_quiz_correct_answer', true);
                    $answer_description = $question->post_content;
                    
                    // Find the correct answer text
                    $correct_answer_text = '';
                    foreach ($options as $option) {
                        if ($option['value'] === $correct_answer) {
                            $correct_answer_text = $option['text'];
                            break;
                        }
                    }
                ?>
                
                <div class="quiz-question" 
                     data-question-id="<?php echo $question_id; ?>" 
                     data-question-index="<?php echo $index; ?>" 
                     data-correct-answer="<?php echo esc_attr($correct_answer); ?>"
                     data-correct-answer-text="<?php echo esc_attr($correct_answer_text); ?>"
                     data-answer-description="<?php echo esc_attr($answer_description); ?>"
                     style="<?php echo $index === 0 ? '' : 'display: none;'; ?>">
                    <h3 class="question-title">
                        <span class="question-number"><?php echo ($index + 1); ?>.</span>
                        <?php echo esc_html($question->post_title); ?>
                    </h3>
                    
                    <div class="quiz-options">
                        <?php foreach ($options as $option_index => $option): ?>
                        <div class="quiz-option-wrapper">
                            <label class="quiz-option">
                                <input 
                                    type="radio" 
                                    name="question_<?php echo $question_id; ?>" 
                                    value="<?php echo esc_attr($option['value']); ?>"
                                    id="q<?php echo $question_id; ?>_opt<?php echo $option_index; ?>"
                                />
                                <span class="option-text"><?php echo esc_html($option['text']); ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Answer Reveal Section (hidden by default) -->
                    <div class="quiz-answer-reveal" style="display: none;">
                        <div class="quiz-answer-box">
                            <div class="quiz-answer-label">Answer:</div>
                            <div class="quiz-answer-text"><?php echo esc_html($correct_answer_text); ?></div>
                            <?php if (!empty($answer_description)): ?>
                            <div class="quiz-answer-description"><?php echo wpautop($answer_description); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php endforeach; ?>
                
                <!-- Navigation Buttons -->
                <div class="quiz-navigation">
                    <button type="button" class="quiz-nav-btn quiz-prev-btn" style="display: none;">
                        ← Previous
                    </button>
                    <button type="button" class="quiz-nav-btn quiz-next-btn">
                        Next →
                    </button>
                    <button type="submit" class="quiz-submit-btn" style="display: none;">
                        Submit Quiz
                    </button>
                    <div class="quiz-loading" style="display: none;">Processing...</div>
                </div>
                
                <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>" />
                <input type="hidden" name="action" value="submit_quiz" />
                <?php wp_nonce_field('quiz_nonce', 'quiz_nonce_field'); ?>
            </form>
            
            <div class="quiz-message" style="display: none;"></div>
        </div>
        <?php
        
        return ob_get_clean();
    }
}
