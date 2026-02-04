<?php
/**
 * Quiz Import/Export Class
 * Handles importing and exporting quizzes with questions
 */

class Quiz_Import_Export {
    
    public function __construct() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_import_export_menu'));
        
        // Handle export
        add_action('admin_init', array($this, 'handle_export'));
        
        // Handle import
        add_action('admin_init', array($this, 'handle_import'));
        
        // Add export link to quiz list
        add_filter('post_row_actions', array($this, 'add_export_link'), 10, 2);
    }
    
    /**
     * Add submenu under Quizzes
     */
    public function add_import_export_menu() {
        add_submenu_page(
            'edit.php?post_type=quiz',
            'Import/Export Quizzes',
            'Import/Export',
            'manage_options',
            'quiz-import-export',
            array($this, 'render_import_export_page')
        );
    }
    
    /**
     * Add export link to quiz row actions
     */
    public function add_export_link($actions, $post) {
        if ($post->post_type === 'quiz') {
            $export_url = add_query_arg(array(
                'action' => 'export_quiz',
                'quiz_id' => $post->ID,
                '_wpnonce' => wp_create_nonce('export_quiz_' . $post->ID)
            ), admin_url('admin.php'));
            
            $actions['export'] = '<a href="' . esc_url($export_url) . '">Export</a>';
        }
        return $actions;
    }
    
    /**
     * Handle quiz export
     */
    public function handle_export() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'export_quiz') {
            return;
        }
        
        if (!isset($_GET['quiz_id']) || !isset($_GET['_wpnonce'])) {
            return;
        }
        
        $quiz_id = intval($_GET['quiz_id']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'export_quiz_' . $quiz_id)) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $export_data = $this->generate_export_data($quiz_id);
        
        if (!$export_data) {
            wp_die('Quiz not found');
        }
        
        // Send JSON file
        $filename = sanitize_title($export_data['quiz']['title']) . '-quiz-export-' . date('Y-m-d') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Generate export data for a quiz
     */
    public function generate_export_data($quiz_id) {
        $quiz = get_post($quiz_id);
        
        if (!$quiz || $quiz->post_type !== 'quiz') {
            return false;
        }
        
        // Get quiz meta
        $question_ids = get_post_meta($quiz_id, '_quiz_questions', true);
        $result_mappings = get_post_meta($quiz_id, '_quiz_result_mappings', true);
        
        if (!is_array($question_ids)) {
            $question_ids = array();
        }
        
        if (!is_array($result_mappings)) {
            $result_mappings = array();
        }
        
        // Build questions data
        $questions = array();
        foreach ($question_ids as $question_id) {
            $question = get_post($question_id);
            if ($question) {
                $options = get_post_meta($question_id, '_quiz_question_options', true);
                $correct_answer = get_post_meta($question_id, '_quiz_correct_answer', true);
                
                $questions[] = array(
                    'title' => $question->post_title,
                    'content' => $question->post_content,
                    'options' => is_array($options) ? $options : array(),
                    'correct_answer' => $correct_answer ?: ''
                );
            }
        }
        
        // Build result mappings (convert page IDs to page titles for portability)
        $mappings_export = array();
        foreach ($result_mappings as $mapping) {
            $page = get_post($mapping['page_id']);
            $mappings_export[] = array(
                'name' => $mapping['name'],
                'condition_type' => $mapping['condition_type'],
                'condition_value' => $mapping['condition_value'],
                'page_title' => $page ? $page->post_title : '',
                'page_id' => $mapping['page_id'] // Keep for reference
            );
        }
        
        return array(
            'export_version' => '1.0',
            'export_date' => current_time('mysql'),
            'plugin_version' => CUSTOM_QUIZ_VERSION,
            'quiz' => array(
                'title' => $quiz->post_title,
                'content' => $quiz->post_content,
                'status' => $quiz->post_status
            ),
            'questions' => $questions,
            'result_mappings' => $mappings_export
        );
    }
    
    /**
     * Handle quiz import
     */
    public function handle_import() {
        if (!isset($_POST['quiz_import_submit'])) {
            return;
        }
        
        if (!isset($_POST['quiz_import_nonce']) || !wp_verify_nonce($_POST['quiz_import_nonce'], 'quiz_import')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        if (!isset($_FILES['quiz_import_file']) || $_FILES['quiz_import_file']['error'] !== UPLOAD_ERR_OK) {
            add_settings_error('quiz_import', 'upload_error', 'Please select a valid JSON file to import.', 'error');
            return;
        }
        
        $file_content = file_get_contents($_FILES['quiz_import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error('quiz_import', 'json_error', 'Invalid JSON file. Please check the file format.', 'error');
            return;
        }
        
        if (!isset($import_data['quiz']) || !isset($import_data['questions'])) {
            add_settings_error('quiz_import', 'format_error', 'Invalid export file format. Missing quiz or questions data.', 'error');
            return;
        }
        
        // Perform import
        $result = $this->import_quiz($import_data);
        
        if ($result['success']) {
            add_settings_error(
                'quiz_import', 
                'import_success', 
                sprintf(
                    'Quiz imported successfully! Created %d question(s). <a href="%s">Edit Quiz</a>',
                    $result['questions_created'],
                    get_edit_post_link($result['quiz_id'])
                ), 
                'success'
            );
        } else {
            add_settings_error('quiz_import', 'import_error', 'Import failed: ' . $result['message'], 'error');
        }
    }
    
    /**
     * Import quiz from data
     */
    public function import_quiz($data) {
        $quiz_data = $data['quiz'];
        $questions_data = $data['questions'];
        $mappings_data = isset($data['result_mappings']) ? $data['result_mappings'] : array();
        
        // Create questions first
        $question_ids = array();
        $questions_created = 0;
        
        foreach ($questions_data as $q) {
            $question_id = wp_insert_post(array(
                'post_title' => $q['title'],
                'post_content' => $q['content'],
                'post_type' => 'quiz_question',
                'post_status' => 'publish'
            ));
            
            if ($question_id && !is_wp_error($question_id)) {
                $question_ids[] = $question_id;
                $questions_created++;
                
                // Save question meta
                if (!empty($q['options'])) {
                    update_post_meta($question_id, '_quiz_question_options', $q['options']);
                }
                if (!empty($q['correct_answer'])) {
                    update_post_meta($question_id, '_quiz_correct_answer', $q['correct_answer']);
                }
            }
        }
        
        // Create quiz
        $quiz_id = wp_insert_post(array(
            'post_title' => $quiz_data['title'] . ' (Imported)',
            'post_content' => $quiz_data['content'],
            'post_type' => 'quiz',
            'post_status' => 'draft' // Import as draft for review
        ));
        
        if (!$quiz_id || is_wp_error($quiz_id)) {
            return array(
                'success' => false,
                'message' => 'Failed to create quiz post'
            );
        }
        
        // Save quiz questions
        update_post_meta($quiz_id, '_quiz_questions', $question_ids);
        
        // Process result mappings
        $result_mappings = array();
        foreach ($mappings_data as $mapping) {
            $page_id = 0;
            
            // Try to find page by title
            if (!empty($mapping['page_title'])) {
                $page = get_page_by_title($mapping['page_title']);
                if ($page) {
                    $page_id = $page->ID;
                }
            }
            
            $result_mappings[] = array(
                'name' => $mapping['name'],
                'condition_type' => $mapping['condition_type'],
                'condition_value' => $mapping['condition_value'],
                'page_id' => $page_id // Will be 0 if page not found - user needs to map manually
            );
        }
        
        update_post_meta($quiz_id, '_quiz_result_mappings', $result_mappings);
        
        return array(
            'success' => true,
            'quiz_id' => $quiz_id,
            'questions_created' => $questions_created
        );
    }
    
    /**
     * Render import/export page
     */
    public function render_import_export_page() {
        // Get all quizzes for bulk export
        $quizzes = get_posts(array(
            'post_type' => 'quiz',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft'),
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <div class="wrap">
            <h1>Quiz Import/Export</h1>
            
            <?php settings_errors('quiz_import'); ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                
                <!-- Export Section -->
                <div class="card" style="padding: 20px; max-width: none;">
                    <h2>📤 Export Quiz</h2>
                    <p>Export a quiz with all its questions to a JSON file. You can import this file on another site.</p>
                    
                    <?php if (empty($quizzes)): ?>
                        <p style="color: #666;"><em>No quizzes found to export.</em></p>
                    <?php else: ?>
                        <table class="widefat" style="margin-top: 15px;">
                            <thead>
                                <tr>
                                    <th>Quiz Name</th>
                                    <th>Questions</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quizzes as $quiz): 
                                    $question_ids = get_post_meta($quiz->ID, '_quiz_questions', true);
                                    $question_count = is_array($question_ids) ? count($question_ids) : 0;
                                    $export_url = add_query_arg(array(
                                        'action' => 'export_quiz',
                                        'quiz_id' => $quiz->ID,
                                        '_wpnonce' => wp_create_nonce('export_quiz_' . $quiz->ID)
                                    ), admin_url('admin.php'));
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($quiz->post_title); ?></strong></td>
                                        <td><?php echo $question_count; ?> question(s)</td>
                                        <td>
                                            <span style="color: <?php echo $quiz->post_status === 'publish' ? '#00a32a' : '#f0b429'; ?>;">
                                                <?php echo ucfirst($quiz->post_status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url($export_url); ?>" class="button button-small">
                                                Download JSON
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Import Section -->
                <div class="card" style="padding: 20px; max-width: none;">
                    <h2>📥 Import Quiz</h2>
                    <p>Import a quiz from a JSON export file. Questions will be created automatically.</p>
                    
                    <form method="post" enctype="multipart/form-data" style="margin-top: 15px;">
                        <?php wp_nonce_field('quiz_import', 'quiz_import_nonce'); ?>
                        
                        <div style="padding: 20px; background: #f0f0f1; border: 2px dashed #c3c4c7; border-radius: 4px; text-align: center;">
                            <p style="margin: 0 0 10px 0;"><strong>Select JSON file to import:</strong></p>
                            <input type="file" name="quiz_import_file" accept=".json" required style="margin-bottom: 15px;" />
                            <br>
                            <button type="submit" name="quiz_import_submit" class="button button-primary button-large">
                                Import Quiz
                            </button>
                        </div>
                        
                        <div style="margin-top: 15px; padding: 12px; background: #fff8e5; border-left: 4px solid #f0b429;">
                            <strong>Note:</strong>
                            <ul style="margin: 5px 0 0 20px;">
                                <li>Imported quizzes are saved as <strong>Draft</strong> for review</li>
                                <li>Questions are created fresh (no duplicates check)</li>
                                <li>Result page mappings may need manual adjustment if pages don't exist</li>
                            </ul>
                        </div>
                    </form>
                </div>
                
            </div>
            
            <!-- Export Format Info -->
            <div class="card" style="padding: 20px; margin-top: 20px; max-width: none;">
                <h2>📋 Export Format</h2>
                <p>The JSON export file includes:</p>
                <ul style="margin-left: 20px;">
                    <li><strong>Quiz:</strong> Title, content, status</li>
                    <li><strong>Questions:</strong> Title, content/explanation, answer options, correct answer</li>
                    <li><strong>Result Mappings:</strong> Condition rules and page references</li>
                </ul>
                <p><strong>Example structure:</strong></p>
                <pre style="background: #f6f7f7; padding: 15px; overflow: auto; max-height: 200px;">{
  "export_version": "1.0",
  "quiz": {
    "title": "My Quiz",
    "content": "Quiz description...",
    "status": "publish"
  },
  "questions": [
    {
      "title": "Question 1",
      "content": "Explanation shown after answer",
      "options": [
        {"text": "Option A", "value": "A"},
        {"text": "Option B", "value": "B"}
      ],
      "correct_answer": "A"
    }
  ],
  "result_mappings": [
    {
      "name": "High Score",
      "condition_type": "correct_answers",
      "condition_value": "3-5",
      "page_title": "Results - High Score"
    }
  ]
}</pre>
            </div>
            
        </div>
        <?php
    }
}
