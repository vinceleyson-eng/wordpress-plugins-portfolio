# Mailchimp Popup Forms

Display Mailchimp signup forms in popups — users must submit to close (no X button).

## Features

- **Two form types:**
  - Built-in Mailchimp form (just paste your form action URL)
  - Shortcode support (Gravity Forms, WPForms, Contact Form 7, etc.)
- **Forced submission** — No close button, users must subscribe
- **Multiple triggers:** Time delay, scroll percentage, or immediate
- **Display rules:** All pages, homepage only, posts only, pages only, or specific pages/posts
- **Exclusion list** — Never show on certain pages
- **Frequency controls:** Per session, per day, every X days, or once ever
- **Test mode** — Ignores cookies for easy testing
- **Full styling:**
  - Colors (background, text, button, overlay)
  - Fonts (with Elementor detection)
  - Background blur
  - Border controls
  - Custom CSS box
- **Mobile toggle** — Show/hide on mobile devices
- **Redirect after submission** — Optional URL with configurable delay
- **AJAX form detection** — Auto-closes on Gravity Forms confirmation

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **MC Popup** in the admin menu
4. Configure your form and display settings

## For Gravity Forms

Use AJAX mode for best experience:
```
[gravityform id="1" title="false" description="false" ajax="true"]
```

The popup will automatically close when the form confirmation appears.

## Changelog

### 1.4.3
- Added page refresh detection for non-AJAX forms
- Sets cookie on submit click to prevent re-showing after page reload

### 1.4.2
- Enhanced GF confirmation detection with polling fallback
- Multiple detection methods for different GF configurations

### 1.4.1
- Added shortcode form support
- Custom CSS box
- Select2 dropdowns for page selection

### 1.2.0
- Added blur background option
- Font family selection
- Border controls
- Popup width settings

## License

GPL v2 or later
