# Scheduled Top Banner - WordPress Plugin

A lightweight WordPress plugin to display a customizable announcement banner above your header with scheduling capabilities. Perfect for promotions, announcements, and time-limited offers. Works seamlessly with Elementor and other page builders.

## Features

- **Scheduling**: Set start and end dates/times for your banner
- **Customizable Content**: Edit banner text and call-to-action link
- **Styling Options**: Customize colors, font size, and padding
- **Dismissible**: Allow visitors to close the banner (with configurable duration)
- **Mobile Control**: Option to show/hide on mobile devices
- **Live Preview**: See changes in real-time in the admin panel
- **Elementor Compatible**: Displays above Elementor headers
- **Lightweight**: Minimal impact on page load times

## Installation

1. Download the `scheduled-top-banner` folder
2. Upload it to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Settings > Top Banner** to configure

## Configuration

### Banner Status
- **Enable Banner**: Toggle the banner on/off globally

### Banner Content
- **Banner Text**: The main message displayed in the banner
- **Link Text**: The clickable call-to-action text (e.g., "Learn More", "Shop Now")
- **Link URL**: The destination URL for the link
- **Open in New Tab**: Choose whether the link opens in a new browser tab

### Schedule
- **Start Date & Time**: When the banner should start displaying
- **End Date & Time**: When the banner should stop displaying
- Leave dates empty for no time restrictions

### Appearance
- **Background Color**: Banner background color
- **Text Color**: Main text color
- **Link Color**: Call-to-action link color
- **Font Size**: Text size in pixels (10-24px)
- **Padding**: Vertical padding in pixels (5-30px)

### Behavior
- **Dismissible**: Allow visitors to close the banner with an X button
- **Dismiss Duration**: How many hours the banner stays hidden after dismissal
- **Show on Mobile**: Display the banner on mobile devices

## Usage with Elementor

The banner automatically displays above all content, including Elementor headers. It uses a high z-index (999999) to ensure it stays on top.

If you're using Elementor's sticky header feature, the banner will appear above the sticky header.

## Hooks & Filters

### Actions
- `stb_before_banner` - Fires before the banner is rendered
- `stb_after_banner` - Fires after the banner is rendered

### Filters
- `stb_banner_text` - Filter the banner text
- `stb_should_display` - Filter whether the banner should display

## Frequently Asked Questions

**Q: The banner isn't showing up. What should I check?**
1. Make sure the banner is enabled in settings
2. Check if the current date/time is within the scheduled period
3. Clear any caching plugins
4. Check if you previously dismissed the banner (clear cookies)

**Q: How do I make the banner always visible?**
Leave both the start date and end date fields empty.

**Q: Can I use HTML in the banner text?**
For security reasons, HTML is not allowed in the banner text. Use the styling options provided.

**Q: How do I reset a dismissed banner for testing?**
Clear your browser cookies or use an incognito/private window.

## Changelog

### 1.0.0
- Initial release

## License

GPL v2 or later

## Support

For support, please contact [your-email@example.com] or create an issue on GitHub.
