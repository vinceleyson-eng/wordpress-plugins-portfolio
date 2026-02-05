/**
 * Mailchimp Popup Admin Script
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Initialize color pickers
        $('.mcp-color-picker').wpColorPicker();
        
        // Toggle embed code vs API fields
        $('#mcp_use_embed').on('change', function() {
            if ($(this).is(':checked')) {
                $('.mcp-embed-row').show();
                $('.mcp-api-row').hide();
            } else {
                $('.mcp-embed-row').hide();
                $('.mcp-api-row').show();
            }
        });
        
        // Toggle trigger-specific fields
        $('#mcp_trigger_type').on('change', function() {
            var value = $(this).val();
            
            $('.mcp-time-row, .mcp-scroll-row').hide();
            
            if (value === 'time_delay') {
                $('.mcp-time-row').show();
            } else if (value === 'scroll') {
                $('.mcp-scroll-row').show();
            }
        }).trigger('change');
        
    });
    
})(jQuery);
