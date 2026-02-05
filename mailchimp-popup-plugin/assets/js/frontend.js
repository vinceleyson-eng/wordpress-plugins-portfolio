/**
 * Mailchimp Popup Frontend Script
 */

(function($) {
    'use strict';
    
    var MCP = {
        
        overlay: null,
        popup: null,
        form: null,
        hasShown: false,
        
        init: function() {
            this.overlay = $('#mcp-overlay');
            this.popup = $('#mcp-popup');
            this.form = $('#mcp-form');
            
            if (!this.overlay.length) {
                return;
            }
            
            // Check if should show based on frequency
            if (!this.shouldShowBasedOnFrequency()) {
                return;
            }
            
            // Bind events
            this.bindEvents();
            
            // Setup triggers
            this.setupTriggers();
        },
        
        shouldShowBasedOnFrequency: function() {
            var frequency = mcpData.displayFrequency;
            var cookieName = 'mcp_popup_shown';
            var cookie = this.getCookie(cookieName);
            
            switch (frequency) {
                case 'always':
                    return true;
                    
                case 'once_per_session':
                    return !sessionStorage.getItem(cookieName);
                    
                case 'once_per_day':
                    if (!cookie) return true;
                    var lastShown = parseInt(cookie);
                    var dayInMs = 24 * 60 * 60 * 1000;
                    return (Date.now() - lastShown) > dayInMs;
                    
                case 'once_per_x_days':
                    if (!cookie) return true;
                    var lastShown = parseInt(cookie);
                    var daysInMs = mcpData.daysBetween * 24 * 60 * 60 * 1000;
                    return (Date.now() - lastShown) > daysInMs;
                    
                case 'once_ever':
                    return !cookie;
                    
                default:
                    return true;
            }
        },
        
        markAsShown: function() {
            var frequency = mcpData.displayFrequency;
            var cookieName = 'mcp_popup_shown';
            
            switch (frequency) {
                case 'once_per_session':
                    sessionStorage.setItem(cookieName, '1');
                    break;
                    
                case 'once_per_day':
                case 'once_per_x_days':
                case 'once_ever':
                    var days = frequency === 'once_ever' ? 365 : mcpData.daysBetween;
                    this.setCookie(cookieName, Date.now(), days);
                    break;
            }
        },
        
        bindEvents: function() {
            var self = this;
            
            // Close button
            this.overlay.find('.mcp-close').on('click', function(e) {
                e.preventDefault();
                self.closePopup();
            });
            
            // Overlay click
            if (mcpData.closeOnOverlay == 1) {
                this.overlay.on('click', function(e) {
                    if (e.target === this) {
                        self.closePopup();
                    }
                });
            }
            
            // ESC key
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape' && self.overlay.hasClass('mcp-active')) {
                    self.closePopup();
                }
            });
            
            // Form submission
            if (this.form.length) {
                this.form.on('submit', function(e) {
                    e.preventDefault();
                    self.submitForm();
                });
            }
        },
        
        setupTriggers: function() {
            var self = this;
            var triggerType = mcpData.triggerType;
            
            switch (triggerType) {
                case 'immediate':
                    setTimeout(function() {
                        self.showPopup();
                    }, 500);
                    break;
                    
                case 'time_delay':
                    setTimeout(function() {
                        self.showPopup();
                    }, mcpData.timeDelay);
                    break;
                    
                case 'scroll':
                    this.setupScrollTrigger();
                    break;
                    
                case 'exit_intent':
                    this.setupExitIntent();
                    break;
            }
            
            // Additional exit intent (if enabled alongside other triggers)
            if (triggerType !== 'exit_intent' && mcpData.exitIntent == 1) {
                this.setupExitIntent();
            }
        },
        
        setupScrollTrigger: function() {
            var self = this;
            var triggered = false;
            var threshold = mcpData.scrollPercentage;
            
            $(window).on('scroll.mcp', function() {
                if (triggered || self.hasShown) return;
                
                var scrollTop = $(window).scrollTop();
                var docHeight = $(document).height() - $(window).height();
                var scrollPercent = (scrollTop / docHeight) * 100;
                
                if (scrollPercent >= threshold) {
                    triggered = true;
                    self.showPopup();
                    $(window).off('scroll.mcp');
                }
            });
        },
        
        setupExitIntent: function() {
            var self = this;
            
            // Desktop only - detect mouse leaving viewport
            if ('ontouchstart' in window) {
                return;
            }
            
            $(document).on('mouseleave.mcp', function(e) {
                if (self.hasShown) return;
                
                // Only trigger if mouse leaves from top
                if (e.clientY <= 0) {
                    self.showPopup();
                    $(document).off('mouseleave.mcp');
                }
            });
        },
        
        showPopup: function() {
            if (this.hasShown) return;
            
            this.hasShown = true;
            this.markAsShown();
            
            // Add animation class
            this.overlay.addClass('mcp-animation-' + mcpData.animation);
            
            // Show
            this.overlay.addClass('mcp-active');
            
            // Prevent body scroll
            $('body').css('overflow', 'hidden');
            
            // Focus on input
            setTimeout(function() {
                $('#mcp-form input[type="email"]').focus();
            }, 300);
        },
        
        closePopup: function() {
            var self = this;
            
            this.overlay.removeClass('mcp-active');
            $('body').css('overflow', '');
            
            // Track dismiss
            $.post(mcpData.ajaxUrl, {
                action: 'mcp_dismiss',
                nonce: mcpData.nonce
            });
        },
        
        submitForm: function() {
            var self = this;
            var $form = this.form;
            var $button = $form.find('.mcp-button');
            var $message = $('#mcp-message');
            var email = $form.find('input[name="email"]').val();
            
            // Validate
            if (!email || !this.isValidEmail(email)) {
                this.showMessage('Please enter a valid email address.', 'error');
                return;
            }
            
            // Loading state
            $form.addClass('mcp-loading');
            $button.prop('disabled', true);
            $message.hide();
            
            $.ajax({
                url: mcpData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mcp_subscribe',
                    nonce: mcpData.nonce,
                    email: email
                },
                success: function(response) {
                    $form.removeClass('mcp-loading');
                    $button.prop('disabled', false);
                    
                    if (response.success) {
                        self.showMessage(response.data.message, 'success');
                        $form.hide();
                        
                        // Auto close after success
                        setTimeout(function() {
                            self.closePopup();
                        }, 3000);
                    } else {
                        self.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    $form.removeClass('mcp-loading');
                    $button.prop('disabled', false);
                    self.showMessage('Something went wrong. Please try again.', 'error');
                }
            });
        },
        
        showMessage: function(text, type) {
            var $message = $('#mcp-message');
            $message
                .removeClass('mcp-success mcp-error')
                .addClass('mcp-' + type)
                .text(text)
                .show();
        },
        
        isValidEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        setCookie: function(name, value, days) {
            var expires = '';
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = name + '=' + value + expires + '; path=/';
        },
        
        getCookie: function(name) {
            var nameEQ = name + '=';
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length);
            }
            return null;
        }
    };
    
    // Initialize when DOM ready
    $(document).ready(function() {
        MCP.init();
    });
    
})(jQuery);
