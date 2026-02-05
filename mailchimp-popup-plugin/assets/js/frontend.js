/**
 * Mailchimp Popup Frontend Script v1.4.2
 * No close button - must submit to close
 * Enhanced GF confirmation detection
 */

(function($) {
    'use strict';
    
    var MCP = {
        
        overlay: null,
        popup: null,
        form: null,
        hasShown: false,
        hasSubmitted: false,
        testMode: false,
        confirmationPoller: null,
        
        init: function() {
            this.overlay = $('#mcp-overlay');
            this.popup = $('#mcp-popup');
            this.form = $('#mcp-form');
            this.testMode = mcpData.testMode == 1;
            
            if (!this.overlay.length) {
                return;
            }
            
            // In test mode, skip all cookie checks
            if (!this.testMode) {
                // Check if should show based on frequency
                if (!this.shouldShowBasedOnFrequency()) {
                    return;
                }
                
                // Check if already subscribed
                if (this.hasSubscribed()) {
                    return;
                }
                
                // Check if form was just submitted (page refresh scenario)
                if (this.wasJustSubmitted()) {
                    console.log('MCP: Form was just submitted (page refresh), marking as subscribed');
                    this.setCookie('mcp_subscribed', '1', 365);
                    // Clean up temp flags
                    this.setCookie('mcp_form_submitting', '', -1);
                    sessionStorage.removeItem('mcp_form_submitting');
                    return;
                }
            } else {
                console.log('MCP: Test mode enabled - ignoring cookies');
            }
            
            // Bind form submit
            this.bindEvents();
            
            // Setup triggers
            this.setupTriggers();
        },
        
        hasSubscribed: function() {
            return this.getCookie('mcp_subscribed') === '1';
        },
        
        // Check if form was just submitted and page refreshed
        wasJustSubmitted: function() {
            // Check for our temp submission flag
            var hasTempFlag = this.getCookie('mcp_form_submitting') === '1' || 
                              sessionStorage.getItem('mcp_form_submitting') === '1';
            
            if (hasTempFlag) {
                console.log('MCP: Temp submission flag found');
                return true;
            }
            
            // Also check if GF confirmation is visible on page load
            // This catches the case where they submitted and page reloaded to show confirmation
            var confirmationSelectors = [
                '.gform_confirmation_message',
                '.gform_confirmation_wrapper', 
                '.gforms_confirmation_message',
                '[id*="gform_confirmation_message"]',
                '.gf_confirmation'
            ];
            
            for (var i = 0; i < confirmationSelectors.length; i++) {
                if ($(confirmationSelectors[i]).length && $(confirmationSelectors[i]).is(':visible')) {
                    console.log('MCP: GF confirmation visible on page load');
                    return true;
                }
            }
            
            return false;
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
            // Don't mark in test mode
            if (this.testMode) return;
            
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
            
            // Check form type
            var formType = mcpData.formType || 'mailchimp';
            
            if (formType === 'shortcode') {
                // For shortcode forms (Gravity Forms, etc.), listen for their submission
                
                // ========== GRAVITY FORMS DETECTION ==========
                
                // Method 1: Official GF confirmation loaded event
                $(document).on('gform_confirmation_loaded', function(e, formId) {
                    console.log('MCP: gform_confirmation_loaded event fired');
                    self.onFormSubmit();
                });
                
                // Method 2: GF page render (check for confirmation)
                $(document).on('gform_page_loaded gform_post_render', function(e, formId, currentPage) {
                    console.log('MCP: gform_post_render/gform_page_loaded event');
                    if (self.isGFConfirmationVisible()) {
                        self.onFormSubmit();
                    }
                });
                
                // Method 3: Watch for AJAX completion with confirmation
                $(document).ajaxComplete(function(e, xhr, settings) {
                    if (settings.url && settings.url.indexOf('admin-ajax.php') > -1) {
                        try {
                            var response = xhr.responseText;
                            if (response && (
                                response.indexOf('gform_confirmation') > -1 || 
                                response.indexOf('confirmation_message') > -1 ||
                                response.indexOf('gforms_confirmation_message') > -1
                            )) {
                                console.log('MCP: AJAX response contains confirmation');
                                setTimeout(function() {
                                    self.onFormSubmit();
                                }, 300);
                            }
                        } catch(err) {}
                    }
                });
                
                // Method 4: MutationObserver for DOM changes
                var shortcodeForm = document.getElementById('mcp-shortcode-form');
                if (shortcodeForm) {
                    var observer = new MutationObserver(function(mutations) {
                        if (self.isGFConfirmationVisible()) {
                            console.log('MCP: MutationObserver detected confirmation');
                            self.onFormSubmit();
                        }
                    });
                    observer.observe(shortcodeForm, { 
                        childList: true, 
                        subtree: true,
                        characterData: true 
                    });
                }
                
                // Method 5: POLLING FALLBACK - Check every 500ms after form interaction
                $('#mcp-shortcode-form').on('click', 'input[type="submit"], button[type="submit"], .gform_button', function() {
                    console.log('MCP: Submit button clicked, starting confirmation polling');
                    
                    // Set a temporary cookie IMMEDIATELY in case page refreshes
                    // This prevents popup from showing again after page reload
                    self.setCookie('mcp_form_submitting', '1', 1); // 1 day expiry
                    sessionStorage.setItem('mcp_form_submitting', '1');
                    
                    self.startConfirmationPolling();
                });
                
                // Also start polling on GF native submit event
                $(document).on('gform_pre_submission gform_pre_submit', function(e, formId) {
                    console.log('MCP: GF pre-submission event, starting polling');
                    self.startConfirmationPolling();
                });
                
                // ========== OTHER FORM PLUGINS ==========
                
                // WPForms success
                $(document).on('wpformsAjaxSubmitSuccess', function() {
                    console.log('MCP: WPForms success');
                    self.onFormSubmit();
                });
                
                // Contact Form 7 success
                $(document).on('wpcf7mailsent', function() {
                    console.log('MCP: CF7 success');
                    self.onFormSubmit();
                });
                
                // Formidable Forms
                $(document).on('frmFormComplete', function() {
                    console.log('MCP: Formidable success');
                    self.onFormSubmit();
                });
                
                // Generic form submit fallback (non-AJAX forms)
                $('#mcp-shortcode-form').on('submit', 'form', function() {
                    console.log('MCP: Generic form submit detected');
                    self.startConfirmationPolling();
                });
                
            } else {
                // Built-in Mailchimp form submission
                this.form.on('submit', function(e) {
                    // Don't prevent default - let form submit to Mailchimp in new tab
                    self.onFormSubmit();
                });
            }
            
            // Prevent closing by clicking overlay or pressing ESC
            // Users MUST submit to close
        },
        
        // Check if GF confirmation is visible
        isGFConfirmationVisible: function() {
            var confirmationSelectors = [
                '.gform_confirmation_message',
                '.gform_confirmation_wrapper',
                '.gforms_confirmation_message',
                '[id*="gform_confirmation_message"]',
                '[id*="gforms_confirmation_message"]',
                '.gf_confirmation'
            ];
            
            for (var i = 0; i < confirmationSelectors.length; i++) {
                var $el = $('#mcp-shortcode-form').find(confirmationSelectors[i]);
                if ($el.length && $el.is(':visible')) {
                    console.log('MCP: Found confirmation via selector: ' + confirmationSelectors[i]);
                    return true;
                }
            }
            
            // Also check if form is hidden but confirmation text exists
            var $wrapper = $('#mcp-shortcode-form');
            if ($wrapper.find('.gform_wrapper form').is(':hidden') || 
                $wrapper.find('.gform_wrapper form').length === 0) {
                // Form is hidden or removed - likely showing confirmation
                var text = $wrapper.text().toLowerCase();
                if (text.indexOf('thank') > -1 || text.indexOf('success') > -1 || text.indexOf('submitted') > -1) {
                    console.log('MCP: Form hidden + thank you text detected');
                    return true;
                }
            }
            
            return false;
        },
        
        // Start polling for confirmation (fallback method)
        startConfirmationPolling: function() {
            var self = this;
            
            // Don't start if already submitted
            if (this.hasSubmitted) return;
            
            // Clear any existing poller
            if (this.confirmationPoller) {
                clearInterval(this.confirmationPoller);
            }
            
            var pollCount = 0;
            var maxPolls = 30; // Poll for up to 15 seconds (30 * 500ms)
            
            this.confirmationPoller = setInterval(function() {
                pollCount++;
                
                if (self.isGFConfirmationVisible()) {
                    console.log('MCP: Polling found confirmation after ' + pollCount + ' checks');
                    clearInterval(self.confirmationPoller);
                    self.onFormSubmit();
                    return;
                }
                
                if (pollCount >= maxPolls) {
                    console.log('MCP: Polling timeout - no confirmation found');
                    clearInterval(self.confirmationPoller);
                }
            }, 500);
        },
        
        onFormSubmit: function() {
            var self = this;
            
            // Prevent multiple triggers
            if (this.hasSubmitted) {
                console.log('MCP: Already submitted, ignoring');
                return;
            }
            this.hasSubmitted = true;
            
            // Clear polling
            if (this.confirmationPoller) {
                clearInterval(this.confirmationPoller);
            }
            
            console.log('MCP: Form submitted, processing close...');
            
            // Don't set cookies in test mode
            if (!this.testMode) {
                // Mark as subscribed
                this.setCookie('mcp_subscribed', '1', 365);
            }
            
            // For shortcode forms, we don't control the success message
            // Just close/redirect after delay
            var formType = mcpData.formType || 'mailchimp';
            var redirectUrl = mcpData.redirectUrl;
            var redirectDelay = (mcpData.redirectDelay || 2) * 1000;
            
            if (formType === 'mailchimp') {
                // Hide form, show success for built-in form
                this.form.hide();
                $('#mcp-success').show();
            }
            
            // Redirect or close
            if (redirectUrl && redirectUrl.length > 0) {
                console.log('MCP: Redirecting to ' + redirectUrl + ' in ' + redirectDelay + 'ms');
                setTimeout(function() {
                    window.location.href = redirectUrl;
                }, redirectDelay);
            } else {
                console.log('MCP: Closing popup in 2 seconds');
                setTimeout(function() {
                    self.closePopup();
                }, 2000);
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
            
            // Show popup with correct class
            this.overlay.addClass('active');
            
            // Prevent body scroll
            $('body').css('overflow', 'hidden');
            
            // Focus on input
            setTimeout(function() {
                $('#mcp-form input[type="email"], #mcp-shortcode-form input[type="email"]').first().focus();
            }, 300);
        },
        
        closePopup: function() {
            console.log('MCP: Closing popup now');
            this.overlay.removeClass('active');
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
