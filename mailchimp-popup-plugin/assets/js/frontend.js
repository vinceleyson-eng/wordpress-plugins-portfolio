/**
 * Mailchimp Popup Frontend Script
 * No close button - must submit to close
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
            
            // Check if already subscribed
            if (this.hasSubscribed()) {
                return;
            }
            
            // Bind form submit
            this.bindEvents();
            
            // Setup triggers
            this.setupTriggers();
        },
        
        hasSubscribed: function() {
            return this.getCookie('mcp_subscribed') === '1';
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
            
            // Form submission
            this.form.on('submit', function(e) {
                // Don't prevent default - let form submit to Mailchimp in new tab
                self.onFormSubmit();
            });
            
            // Prevent closing by clicking overlay or pressing ESC
            // Users MUST submit to close
        },
        
        onFormSubmit: function() {
            var self = this;
            
            // Mark as subscribed
            this.setCookie('mcp_subscribed', '1', 365);
            
            // Hide form, show success
            this.form.hide();
            $('#mcp-success').show();
            
            // Close popup after delay
            setTimeout(function() {
                self.closePopup();
            }, 3000);
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
        
        showPopup: function() {
            if (this.hasShown) return;
            
            this.hasShown = true;
            this.markAsShown();
            
            // Show popup
            this.overlay.addClass('mcp-active');
            
            // Prevent body scroll
            $('body').css('overflow', 'hidden');
            
            // Focus on input
            setTimeout(function() {
                $('#mcp-form input[type="email"]').focus();
            }, 300);
        },
        
        closePopup: function() {
            this.overlay.removeClass('mcp-active');
            $('body').css('overflow', '');
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
    
    $(document).ready(function() {
        MCP.init();
    });
    
})(jQuery);
