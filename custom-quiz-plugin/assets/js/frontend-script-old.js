jQuery(document).ready(function($) {
    
    var currentQuestionIndex = 0;
    var totalQuestions = 0;
    
    // Initialize quiz
    $('.custom-quiz-container').each(function() {
        var container = $(this);
        totalQuestions = container.find('.quiz-question').length;
        container.attr('data-total-questions', totalQuestions);
        updateProgress(container);
        updateNavigation(container);
    });
    
    // Clear error message when answer is selected
    $('.quiz-question input[type="radio"]').on('change', function() {
        var container = $(this).closest('.custom-quiz-container');
        container.find('.quiz-message').hide();
    });
    
    // Next button
    $('.quiz-next-btn').on('click', function(e) {
        e.preventDefault();
        var container = $(this).closest('.custom-quiz-container');
        var currentQuestion = container.find('.quiz-question').eq(currentQuestionIndex);
        var message = container.find('.quiz-message');
        
        // Check if current question is answered
        var isAnswered = currentQuestion.find('input[type="radio"]:checked').length > 0;
        
        if (!isAnswered) {
            message.removeClass('quiz-success').addClass('quiz-error')
                .html('Please select an answer before continuing.').show();
            return false;
        }
        
        message.hide();
        
        // Hide current question
        currentQuestion.fadeOut(300, function() {
            currentQuestionIndex++;
            
            // Show next question
            var nextQuestion = container.find('.quiz-question').eq(currentQuestionIndex);
            nextQuestion.fadeIn(300);
            
            updateNavigation(container);
            updateProgress(container);
            
            // Scroll to top of quiz
            $('html, body').animate({
                scrollTop: container.offset().top - 50
            }, 300);
        });
        
        return false;
    });
    
    // Previous button
    $('.quiz-prev-btn').on('click', function(e) {
        e.preventDefault();
        var container = $(this).closest('.custom-quiz-container');
        var currentQuestion = container.find('.quiz-question').eq(currentQuestionIndex);
        var message = container.find('.quiz-message');
        
        message.hide();
        
        // Hide current question
        currentQuestion.fadeOut(300, function() {
            currentQuestionIndex--;
            
            // Show previous question
            var prevQuestion = container.find('.quiz-question').eq(currentQuestionIndex);
            prevQuestion.fadeIn(300);
            
            updateNavigation(container);
            updateProgress(container);
            
            // Scroll to top of quiz
            $('html, body').animate({
                scrollTop: container.offset().top - 50
            }, 300);
        });
        
        return false;
    });
    
    // Update navigation buttons visibility
    function updateNavigation(container) {
        var prevBtn = container.find('.quiz-prev-btn');
        var nextBtn = container.find('.quiz-next-btn');
        var submitBtn = container.find('.quiz-submit-btn');
        
        totalQuestions = container.find('.quiz-question').length;
        
        // Show/hide previous button
        if (currentQuestionIndex === 0) {
            prevBtn.hide();
        } else {
            prevBtn.show();
        }
        
        // Show/hide next/submit buttons
        if (currentQuestionIndex === totalQuestions - 1) {
            nextBtn.hide();
            submitBtn.show();
        } else {
            nextBtn.show();
            submitBtn.hide();
        }
    }
    
    // Update progress bar
    function updateProgress(container) {
        totalQuestions = container.find('.quiz-question').length;
        var progress = ((currentQuestionIndex + 1) / totalQuestions) * 100;
        container.find('.quiz-progress-fill').css('width', progress + '%');
        container.find('.current-question').text(currentQuestionIndex + 1);
    }
    
    // Form submission
    $('.quiz-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var quizContainer = form.closest('.custom-quiz-container');
        var submitBtn = form.find('.quiz-submit-btn');
        var loading = form.find('.quiz-loading');
        var message = quizContainer.find('.quiz-message');
        
        // Validate all questions are answered
        var allAnswered = true;
        form.find('.quiz-question').each(function() {
            var questionId = $(this).data('question-id');
            var answered = form.find('input[name="question_' + questionId + '"]:checked').length > 0;
            
            if (!answered) {
                allAnswered = false;
            }
        });
        
        if (!allAnswered) {
            message.removeClass('quiz-success').addClass('quiz-error')
                .html('Please answer all questions before submitting.').show();
            return false;
        }
        
        // Disable submit button and show loading
        submitBtn.prop('disabled', true);
        loading.show();
        message.hide();
        
        // Prepare form data
        var formData = form.serialize();
        
        // Submit via AJAX
        $.ajax({
            url: quizAjax.ajaxurl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    message.removeClass('quiz-error').addClass('quiz-success')
                        .html(response.data.message).show();
                    
                    // Redirect to result page
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1000);
                } else {
                    message.removeClass('quiz-success').addClass('quiz-error')
                        .html(response.data.message || 'An error occurred. Please try again.').show();
                    submitBtn.prop('disabled', false);
                    loading.hide();
                }
            },
            error: function(xhr, status, error) {
                message.removeClass('quiz-success').addClass('quiz-error')
                    .html('An error occurred. Please try again.').show();
                submitBtn.prop('disabled', false);
                loading.hide();
            }
        });
        
        return false;
    });
    
});
