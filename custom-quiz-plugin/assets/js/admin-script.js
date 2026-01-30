jQuery(document).ready(function($) {
    
    // Add quiz option
    $('#add-quiz-option').on('click', function() {
        var container = $('.quiz-options-container');
        var index = container.find('.quiz-option-row').length;
        var letter = String.fromCharCode(65 + index);
        
        var html = '<div class="quiz-option-row" data-index="' + index + '">' +
            '<label>Option ' + letter + ':</label>' +
            '<input type="text" name="quiz_option_text[]" value="" placeholder="Enter option text" style="width: 60%;" />' +
            '<input type="text" name="quiz_option_value[]" value="' + letter + '" placeholder="Value" style="width: 15%;" />' +
            '<button type="button" class="button remove-option">Remove</button>' +
            '</div>';
        
        container.append(html);
    });
    
    // Remove quiz option
    $(document).on('click', '.remove-option', function() {
        var row = $(this).closest('.quiz-option-row');
        row.remove();
        
        // Re-index remaining options
        $('.quiz-option-row').each(function(index) {
            var letter = String.fromCharCode(65 + index);
            $(this).attr('data-index', index);
            $(this).find('label').text('Option ' + letter + ':');
        });
    });
    
    // Generate score options HTML
    function generateScoreOptions(totalQuestions, selectedValue) {
        var html = '';
        for (var i = 0; i <= totalQuestions; i++) {
            var selected = (i == selectedValue) ? ' selected' : '';
            html += '<option value="' + i + '"' + selected + '>' + i + '</option>';
        }
        return html;
    }
    
    // Add result mapping
    $('#add-result-mapping').on('click', function() {
        var container = $('.result-mappings-container');
        var index = container.find('.result-mapping-row').length;
        var totalQuestions = parseInt($('#total-questions-count').val()) || 10;
        
        // Get page options from the template
        var pageOptions = '';
        var template = $('#page-options-template');
        
        if (template.length > 0) {
            template.find('option').each(function() {
                pageOptions += '<option value="' + $(this).val() + '">' + $(this).text() + '</option>';
            });
        } else {
            pageOptions = '<option value="">Select a page...</option>';
        }
        
        var html = '<div class="result-mapping-row" data-index="' + index + '">' +
            '<div class="mapping-row-inner">' +
                '<div class="mapping-field">' +
                    '<label>Rule Name:</label>' +
                    '<input type="text" name="result_rule_name[]" value="" placeholder="e.g., Perfect Score" />' +
                '</div>' +
                '<div class="mapping-field">' +
                    '<label>Condition:</label>' +
                    '<select name="result_condition_type[]" class="condition-type-select">' +
                        '<option value="correct_answers">Correct Answers (Score)</option>' +
                        '<option value="most_common">Most Common Answer</option>' +
                        '<option value="contains">Contains Value</option>' +
                    '</select>' +
                '</div>' +
                '<div class="mapping-field score-range-fields">' +
                    '<label>Score from:</label>' +
                    '<select name="result_min_score[]" class="min-score-select">' +
                        generateScoreOptions(totalQuestions, 0) +
                    '</select>' +
                    '<span style="margin: 0 5px;">to</span>' +
                    '<select name="result_max_score[]" class="max-score-select">' +
                        generateScoreOptions(totalQuestions, totalQuestions) +
                    '</select>' +
                    '<span style="margin-left: 5px; color: #666;">correct</span>' +
                '</div>' +
                '<div class="mapping-field text-value-field" style="display:none;">' +
                    '<label>Value:</label>' +
                    '<input type="text" name="result_condition_value_text[]" value="" placeholder="e.g., A" />' +
                '</div>' +
                '<input type="hidden" name="result_condition_value[]" class="condition-value-hidden" value="0-' + totalQuestions + '" />' +
                '<div class="mapping-field">' +
                    '<label>Result Page:</label>' +
                    '<select name="result_page_id[]">' +
                        pageOptions +
                    '</select>' +
                '</div>' +
                '<div class="mapping-field">' +
                    '<button type="button" class="button remove-mapping">Remove</button>' +
                '</div>' +
            '</div>' +
        '</div>';
        
        container.append(html);
        
        // Update the hidden value for the new row
        var newRow = container.find('.result-mapping-row').last();
        updateHiddenValue(newRow);
    });
    
    // Remove result mapping
    $(document).on('click', '.remove-mapping', function() {
        $(this).closest('.result-mapping-row').remove();
    });
    
    // Handle condition type change
    $(document).on('change', '.condition-type-select', function() {
        var row = $(this).closest('.result-mapping-row');
        var conditionType = $(this).val();
        
        if (conditionType === 'correct_answers') {
            row.find('.score-range-fields').show();
            row.find('.text-value-field').hide();
        } else {
            row.find('.score-range-fields').hide();
            row.find('.text-value-field').show();
        }
        
        updateHiddenValue(row);
    });
    
    // Update hidden value when score dropdowns change
    $(document).on('change', '.min-score-select, .max-score-select', function() {
        var row = $(this).closest('.result-mapping-row');
        updateHiddenValue(row);
    });
    
    // Update hidden value when text field changes
    $(document).on('change keyup', 'input[name="result_condition_value_text[]"]', function() {
        var row = $(this).closest('.result-mapping-row');
        updateHiddenValue(row);
    });
    
    // Function to update the hidden value field
    function updateHiddenValue(row) {
        var conditionType = row.find('.condition-type-select').val();
        var hiddenField = row.find('.condition-value-hidden');
        
        if (conditionType === 'correct_answers') {
            var minScore = row.find('.min-score-select').val();
            var maxScore = row.find('.max-score-select').val();
            hiddenField.val(minScore + '-' + maxScore);
        } else {
            var textValue = row.find('input[name="result_condition_value_text[]"]').val();
            hiddenField.val(textValue);
        }
    }
    
    // Initialize all existing rows
    $('.result-mapping-row').each(function() {
        var row = $(this);
        var conditionType = row.find('.condition-type-select').val();
        
        if (conditionType === 'correct_answers') {
            row.find('.score-range-fields').show();
            row.find('.text-value-field').hide();
        } else {
            row.find('.score-range-fields').hide();
            row.find('.text-value-field').show();
        }
    });
    
});
