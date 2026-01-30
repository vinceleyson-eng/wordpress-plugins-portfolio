<?php
/**
 * Quiz AJAX Handler Class
 */

class Quiz_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_submit_quiz', array($this, 'handle_quiz_submission'));
        add_action('wp_ajax_nopriv_submit_quiz', array($this, 'handle_quiz_submission'));
    }
    
    /**
     * Handle quiz submission via AJAX
     */
    public function handle_quiz_submission() {
        // Verify nonce
        if (!isset($_POST['quiz_nonce_field']) || !wp_verify_nonce($_POST['quiz_nonce_field'], 'quiz_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $quiz_id = intval($_POST['quiz_id']);
        
        if (!$quiz_id) {
            wp_send_json_error(array('message' => 'Invalid quiz ID.'));
        }
        
        // Get quiz questions
        $question_ids = get_post_meta($quiz_id, '_quiz_questions', true);
        
        if (empty($question_ids)) {
            wp_send_json_error(array('message' => 'No questions found for this quiz.'));
        }
        
        // Collect answers
        $answers = array();
        foreach ($question_ids as $question_id) {
            $answer_key = 'question_' . $question_id;
            if (isset($_POST[$answer_key])) {
                $answers[$question_id] = sanitize_text_field($_POST[$answer_key]);
            }
        }
        
        // Calculate correct answers score
        $correct_count = $this->calculate_correct_answers($answers);
        $total_questions = count($question_ids);
        
        // Get result mappings
        $result_mappings = get_post_meta($quiz_id, '_quiz_result_mappings', true);
        
        if (empty($result_mappings)) {
            wp_send_json_error(array('message' => 'No result mappings configured for this quiz.'));
        }
        
        // Calculate result
        $result_page_id = $this->calculate_result($answers, $result_mappings, $correct_count, $total_questions);
        
        if (!$result_page_id) {
            wp_send_json_error(array('message' => 'Could not determine result page.'));
        }
        
        $result_url = get_permalink($result_page_id);
        
        wp_send_json_success(array(
            'redirect_url' => $result_url,
            'message' => 'Quiz completed successfully!',
            'score' => $correct_count,
            'total' => $total_questions
        ));
    }
    
    /**
     * Calculate how many correct answers the user got
     */
    private function calculate_correct_answers($answers) {
        $correct_count = 0;
        
        foreach ($answers as $question_id => $user_answer) {
            $correct_answer = get_post_meta($question_id, '_quiz_correct_answer', true);
            
            if (!empty($correct_answer) && strtoupper($user_answer) === strtoupper($correct_answer)) {
                $correct_count++;
            }
        }
        
        return $correct_count;
    }
    
    /**
     * Calculate quiz result based on answers and mappings
     */
    private function calculate_result($answers, $result_mappings, $correct_count, $total_questions) {
        foreach ($result_mappings as $mapping) {
            $condition_type = $mapping['condition_type'];
            $condition_value = $mapping['condition_value'];
            $page_id = $mapping['page_id'];
            
            switch ($condition_type) {
                case 'correct_answers':
                    if ($this->check_correct_answers_range($correct_count, $condition_value, $total_questions)) {
                        return $page_id;
                    }
                    break;
                    
                case 'most_common':
                    if ($this->check_most_common($answers, $condition_value)) {
                        return $page_id;
                    }
                    break;
                    
                case 'score_range':
                    if ($this->check_score_range($answers, $condition_value)) {
                        return $page_id;
                    }
                    break;
                    
                case 'contains':
                    if ($this->check_contains($answers, $condition_value)) {
                        return $page_id;
                    }
                    break;
            }
        }
        
        // If no mapping matches, return the first result page as fallback
        return !empty($result_mappings[0]['page_id']) ? $result_mappings[0]['page_id'] : null;
    }
    
    /**
     * Check if correct answers count falls within a range
     * Format: "8-10" or "perfect" or "0-5"
     */
    private function check_correct_answers_range($correct_count, $range, $total_questions) {
        // Handle special keywords
        $range_lower = strtolower(trim($range));
        
        if ($range_lower === 'perfect' || $range_lower === 'all') {
            return $correct_count === $total_questions;
        }
        
        if ($range_lower === 'none' || $range_lower === 'zero') {
            return $correct_count === 0;
        }
        
        // Handle range format (e.g., "8-10")
        if (strpos($range, '-') !== false) {
            $parts = explode('-', $range);
            
            if (count($parts) === 2) {
                $min = intval(trim($parts[0]));
                $max = intval(trim($parts[1]));
                
                return $correct_count >= $min && $correct_count <= $max;
            }
        }
        
        // Handle single number (exact match)
        if (is_numeric($range)) {
            return $correct_count === intval($range);
        }
        
        return false;
    }
    
    /**
     * Check if a specific value is the most common answer
     */
    private function check_most_common($answers, $target_value) {
        if (empty($answers)) {
            return false;
        }
        
        $counts = array_count_values($answers);
        arsort($counts);
        
        $most_common = key($counts);
        
        return strtoupper($most_common) === strtoupper($target_value);
    }
    
    /**
     * Check if score falls within a range
     * Format: "0-50" or "51-100"
     */
    private function check_score_range($answers, $range) {
        $parts = explode('-', $range);
        
        if (count($parts) !== 2) {
            return false;
        }
        
        $min = intval($parts[0]);
        $max = intval($parts[1]);
        
        // Calculate score based on alphabetical values (A=1, B=2, etc.)
        $score = 0;
        foreach ($answers as $answer) {
            $value = ord(strtoupper($answer)) - ord('A') + 1;
            $score += $value;
        }
        
        return $score >= $min && $score <= $max;
    }
    
    /**
     * Check if answers contain a specific value
     */
    private function check_contains($answers, $target_value) {
        foreach ($answers as $answer) {
            if (strtoupper($answer) === strtoupper($target_value)) {
                return true;
            }
        }
        return false;
    }
}
