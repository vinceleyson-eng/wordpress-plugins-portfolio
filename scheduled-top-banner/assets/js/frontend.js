/**
 * Scheduled Top Banner - Frontend Scripts
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $banner = $('#stb-top-banner');
        var $dismissBtn = $banner.find('.stb-dismiss-btn');
        var $body = $('body');

        if (!$banner.length) {
            return;
        }

        // Handle dismiss button click
        $dismissBtn.on('click', function(e) {
            e.preventDefault();
            
            // Animate banner out
            $banner.slideUp(300, function() {
                $banner.remove();
                $body.removeClass('stb-banner-active');
                // Reset body padding if it was set for sticky
                $body.css('padding-top', '');
            });

            // Send AJAX request to set cookie
            if (typeof stb_ajax !== 'undefined') {
                $.ajax({
                    url: stb_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'stb_dismiss_banner',
                        nonce: stb_ajax.nonce
                    }
                });
            }
        });

        // Track link clicks
        if (typeof stb_ajax !== 'undefined' && stb_ajax.track_clicks) {
            $banner.on('click', 'a', function() {
                $.ajax({
                    url: stb_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'stb_track_click',
                        nonce: stb_ajax.click_nonce
                    }
                });
                // Don't prevent default - let the link work normally
            });
        }

        // Keyboard accessibility
        $dismissBtn.on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });
    });

})(jQuery);
