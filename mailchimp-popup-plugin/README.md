# Mailchimp Popup Forms

A WordPress plugin to display Mailchimp signup forms in customizable popups with multiple trigger options.

## Features

### 🎯 Trigger Options
- **Time Delay** - Show after X seconds
- **Scroll Percentage** - Show when user scrolls X% of page
- **Exit Intent** - Show when mouse leaves viewport (desktop)
- **Immediate** - Show on page load

### 📧 Mailchimp Integration
- Direct API integration (recommended)
- Or use Mailchimp embed code
- Auto-fetches your audiences/lists
- Handles existing subscribers gracefully

### 📄 Display Rules
- Show on all pages, homepage, posts, or specific pages
- Exclude specific pages
- Frequency control:
  - Every page view
  - Once per session
  - Once per day
  - Once every X days
  - Once ever
- Mobile enable/disable

### 🎨 Customization
- Position: Center, Top, Bottom, Bottom-Right, Bottom-Left
- Animations: Fade, Slide Up, Slide Down, Zoom
- Colors: Background, text, button, overlay
- Show/hide close button
- Close on overlay click

## Installation

1. Upload the `mailchimp-popup` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to **MC Popup** in the admin menu
4. Add your Mailchimp API key and select your audience
5. Customize the content and appearance
6. Enable the popup

## Configuration

### Getting Your Mailchimp API Key

1. Log into Mailchimp
2. Go to Account → Extras → API keys
3. Create a new key or copy an existing one
4. Paste it in the plugin settings

### Using Embed Code Instead

If you prefer to use Mailchimp's embed code:

1. Check "Use Mailchimp embed code"
2. Get your embed code from Mailchimp (Audience → Signup forms → Embedded forms)
3. Paste the code in the Embed Code field

## Shortcode

Currently the popup displays automatically based on your settings. Shortcode support coming in a future version.

## Changelog

### 1.0.0
- Initial release
- Multiple trigger types
- Mailchimp API integration
- Embed code support
- Display rules and frequency control
- Customizable appearance

## License

GPL v2 or later

## Author

Built by Bidview Marketing
