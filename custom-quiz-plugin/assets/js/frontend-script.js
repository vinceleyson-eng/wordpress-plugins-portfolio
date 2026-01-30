jQuery(document).ready(function($) {
    
    var currentQuestionIndex = 0;
    var totalQuestions = 0;
    var answerRevealed = false; // Track if answer is currently shown
    
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
        
        // If answer not yet revealed, show the answer first
        if (!answerRevealed) {
            revealAnswer(currentQuestion);
            answerRevealed = true;
            
            // Scroll to see the answer
            $('html, body').animate({
                scrollTop: currentQuestion.find('.quiz-answer-reveal').offset().top - 100
            }, 300);
            
            return false;
        }
        
        // Answer was already revealed, move to next question
        answerRevealed = false;
        
        // Hide answer reveal and reset question state
        currentQuestion.find('.quiz-answer-reveal').hide();
        resetQuestionState(currentQuestion);
        
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
        
        // Reset current question state if answer was revealed
        if (answerRevealed) {
            currentQuestion.find('.quiz-answer-reveal').hide();
            resetQuestionState(currentQuestion);
            answerRevealed = false;
        }
        
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
    
    // Reveal answer for a question
    function revealAnswer(question) {
        // Disable radio buttons
        question.find('input[type="radio"]').prop('disabled', true);
        
        // Mark correct/incorrect answers
        var correctAnswer = question.data('correct-answer');
        var userAnswer = question.find('input[type="radio"]:checked').val();
        
        question.find('.quiz-option').each(function() {
            var optionValue = $(this).find('input[type="radio"]').val();
            
            if (optionValue === correctAnswer) {
                $(this).addClass('correct-answer');
            }
            
            if (optionValue === userAnswer && userAnswer !== correctAnswer) {
                $(this).addClass('wrong-answer');
            }
        });
        
        // Show answer reveal section
        question.find('.quiz-answer-reveal').slideDown(300);
    }
    
    // Reset question state (for going back)
    function resetQuestionState(question) {
        // Re-enable radio buttons
        question.find('input[type="radio"]').prop('disabled', false);
        
        // Remove correct/wrong classes
        question.find('.quiz-option').removeClass('correct-answer wrong-answer');
    }
    
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
    
    // Get UTM parameters from current URL
    function getUtmParams() {
        var params = new URLSearchParams(window.location.search);
        var utmParams = [];
        params.forEach(function(value, key) {
            if (key.toLowerCase().startsWith('utm_')) {
                utmParams.push(key + '=' + encodeURIComponent(value));
            }
        });
        return utmParams;
    }
    
    // Append UTM parameters to URL
    function appendUtmParams(url) {
        var utmParams = getUtmParams();
        if (utmParams.length > 0) {
            var separator = url.indexOf('?') !== -1 ? '&' : '?';
            return url + separator + utmParams.join('&');
        }
        return url;
    }
    
    // Form submission (for last question)
    $('.quiz-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var quizContainer = form.closest('.custom-quiz-container');
        var submitBtn = form.find('.quiz-submit-btn');
        var loading = form.find('.quiz-loading');
        var message = quizContainer.find('.quiz-message');
        var currentQuestion = quizContainer.find('.quiz-question').eq(currentQuestionIndex);
        
        // Check if current question is answered
        var isAnswered = currentQuestion.find('input[type="radio"]:checked').length > 0;
        
        if (!isAnswered) {
            message.removeClass('quiz-success').addClass('quiz-error')
                .html('Please select an answer before submitting.').show();
            return false;
        }
        
        message.hide();
        
        // If answer not yet revealed for last question, show it first
        if (!answerRevealed) {
            revealAnswer(currentQuestion);
            answerRevealed = true;
            
            // Change submit button to "See Results"
            submitBtn.text('See Results');
            submitBtn.addClass('quiz-see-results-btn');
            
            // Scroll to see the answer
            $('html, body').animate({
                scrollTop: currentQuestion.find('.quiz-answer-reveal').offset().top - 100
            }, 300);
            
            return false;
        }
        
        // Answer was revealed, now submit the quiz
        submitBtn.prop('disabled', true);
        loading.show();
        
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
                    // Redirect to result page with UTM parameters preserved
                    var redirectUrl = appendUtmParams(response.data.redirect_url);
                    window.location.href = redirectUrl;
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
