/**
 * Mailchimp Popup - Admin Scripts v1.2.0
 */
jQuery(document).ready(function($) {
    
    // Initialize color pickers
    $('.mcp-color-picker').wpColorPicker();
    
    // Function to sync Select2 to hidden input
    function syncSelect2Values() {
        var showValues = $('#mcp_show_on_pages_select').val();
        $('#mcp_show_on_pages').val(showValues ? showValues.join(',') : '');
        
        var excludeValues = $('#mcp_exclude_pages_select').val();
        $('#mcp_exclude_pages').val(excludeValues ? excludeValues.join(',') : '');
    }
    
    // Initialize Select2 for page/post selectors
    if ($.fn.select2) {
        $('.mcp-select2').select2({
            placeholder: 'Search and select pages or posts...',
            allowClear: true,
            width: '100%'
        });
        
        // Sync Select2 values to hidden inputs on change
        $('#mcp_show_on_pages_select, #mcp_exclude_pages_select').on('change', function() {
            syncSelect2Values();
        });
        
        // Sync on page load (in case values were pre-selected)
        syncSelect2Values();
    }
    
    // Sync before form submit to ensure values are saved
    $('form').on('submit', function() {
        syncSelect2Values();
    });
    
    // Handle trigger type changes
    var $triggerType = $('#mcp_trigger_type');
    var $timeRow = $('.mcp-time-row');
    var $scrollRow = $('.mcp-scroll-row');
    
    function updateTriggerFields() {
        var type = $triggerType.val();
        
        if (type === 'time_delay') {
            $timeRow.show();
            $scrollRow.hide();
        } else if (type === 'scroll') {
            $timeRow.hide();
            $scrollRow.show();
        } else {
            $timeRow.hide();
            $scrollRow.hide();
        }
    }
    
    $triggerType.on('change', updateTriggerFields);
    updateTriggerFields();
    
    // Handle show on changes
    var $showOn = $('#mcp_show_on');
    var $specificRow = $('.mcp-specific-pages-row');
    
    function updateShowOnFields() {
        var showOn = $showOn.val();
        
        if (showOn === 'specific') {
            $specificRow.show();
        } else {
            $specificRow.hide();
        }
    }
    
    $showOn.on('change', updateShowOnFields);
    updateShowOnFields();
    
    // Handle blur checkbox
    var $blurCheckbox = $('input[name="mcp_blur_background"]');
    var $blurRow = $('.mcp-blur-row');
    
    function updateBlurFields() {
        if ($blurCheckbox.is(':checked')) {
            $blurRow.show();
        } else {
            $blurRow.hide();
        }
    }
    
    $blurCheckbox.on('change', updateBlurFields);
    updateBlurFields();
    
    // Live preview for custom CSS (if we add preview panel later)
    var $customCss = $('#mcp_custom_css');
    if ($customCss.length) {
        // Add syntax highlighting placeholder
        $customCss.attr('spellcheck', 'false');
    }
    
    // Handle form type toggle
    var $formType = $('#mcp_form_type');
    var $mailchimpSettings = $('#mcp-mailchimp-settings');
    var $shortcodeSettings = $('#mcp-shortcode-settings');
    
    function updateFormTypeFields() {
        var type = $formType.val();
        if (type === 'shortcode') {
            $mailchimpSettings.hide();
            $shortcodeSettings.show();
        } else {
            $mailchimpSettings.show();
            $shortcodeSettings.hide();
        }
    }
    
    $formType.on('change', updateFormTypeFields);
    updateFormTypeFields();
    
    // Form validation
    $('form').on('submit', function(e) {
        var $formType = $('#mcp_form_type');
        var $formAction = $('input[name="mcp_form_action_url"]');
        var $formShortcode = $('input[name="mcp_form_shortcode"]');
        var $enabled = $('input[name="mcp_enabled"]');
        
        if ($enabled.is(':checked')) {
            if ($formType.val() === 'mailchimp' && !$formAction.val()) {
                alert('Please enter a Mailchimp Form Action URL before enabling the popup.');
                e.preventDefault();
                $formAction.focus();
                return false;
            }
            if ($formType.val() === 'shortcode' && !$formShortcode.val()) {
                alert('Please enter a Form Shortcode before enabling the popup.');
                e.preventDefault();
                $formShortcode.focus();
                return false;
            }
        }
    });
    
    // Copy CSS selectors helper
    $('.mcp-card code').each(function() {
        $(this).css('cursor', 'pointer');
        $(this).attr('title', 'Click to copy');
    }).on('click', function() {
        var text = $(this).text();
        navigator.clipboard.writeText(text).then(function() {
            // Show brief feedback
            var $el = $(this);
            var originalText = $el.text();
            $el.text('Copied!');
            setTimeout(function() {
                $el.text(originalText);
            }, 1000);
        }.bind(this));
    });
    
});
