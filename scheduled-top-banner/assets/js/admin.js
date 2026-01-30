/**
 * Scheduled Top Banner - Admin Scripts
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize color pickers
        $('.stb-color-picker').wpColorPicker({
            change: function(event, ui) {
                updatePreviewColors();
            }
        });
        
        // Initialize Select2 for multi-select fields
        $('.stb-select2').select2({
            placeholder: 'Select options...',
            allowClear: true,
            width: '100%'
        });

        // Preview color updates
        var $preview = $('#stb-preview .stb-preview-banner');

        function updatePreviewColors() {
            var bgColor = $('#stb_bg_color').val();
            var textColor = $('#stb_text_color').val();
            var fontSize = $('#stb_font_size').val();
            var padding = $('#stb_padding').val();

            $preview.css({
                'background-color': bgColor,
                'color': textColor,
                'font-size': fontSize + 'px',
                'padding': padding + 'px 40px'
            });
        }

        // Bind events for live preview (colors and sizing only)
        $('#stb_font_size, #stb_padding').on('change input', updatePreviewColors);

        // Also update on color picker input
        $('.stb-color-picker').on('input', function() {
            setTimeout(updatePreviewColors, 10);
        });

        // Schedule validation
        $('#stb_start_date, #stb_end_date').on('change', function() {
            var startDate = $('#stb_start_date').val();
            var endDate = $('#stb_end_date').val();

            if (startDate && endDate && startDate > endDate) {
                alert('Warning: End date is before start date. The banner may not display correctly.');
            }
        });
        
        // Display conditions toggle
        function toggleConditionsWrapper() {
            var displayMode = $('input[name="stb_settings[display_mode]"]:checked').val();
            var $wrapper = $('#stb-conditions-wrapper');
            
            if (displayMode === 'all') {
                $wrapper.slideUp(200);
            } else {
                $wrapper.slideDown(200);
            }
            
            // Update the label for context
            var $modeLabel = $('#stb-conditions-mode-label');
            if (displayMode === 'include') {
                $modeLabel.text('Banner will ONLY show on the selected pages/conditions below:');
            } else if (displayMode === 'exclude') {
                $modeLabel.text('Banner will show everywhere EXCEPT the selected pages/conditions below:');
            }
        }
        
        // Bind display mode change
        $('input[name="stb_settings[display_mode]"]').on('change', toggleConditionsWrapper);
        
        // Initial state
        toggleConditionsWrapper();
    });

})(jQuery);
