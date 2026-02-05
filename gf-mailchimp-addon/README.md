# GF Mailchimp Pro

Free Gravity Forms to Mailchimp integration with full field mapping.

## Features

- **Per-form settings** — Each form can have different Mailchimp configuration
- **Audience dropdown** — Auto-populated from your Mailchimp account
- **Dynamic field mapping** — Fetches all merge fields from your selected audience
- **Smart field detection** — Handles Name sub-fields (First, Last), Address sub-fields, etc.
- **Tag support** — Add tags by name (comma-separated)
- **Double opt-in** — Toggle per form
- **Update existing** — Choose to update or skip existing subscribers

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **Settings → GF Mailchimp** and enter your Mailchimp API key
4. Edit any Gravity Form → **Settings → Mailchimp**
5. Select an audience, map your fields, and save

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Gravity Forms (any version)
- Mailchimp account with API key

## Getting Your Mailchimp API Key

1. Log into Mailchimp
2. Go to Account → Extras → API Keys
3. Create a new key and copy it

## Changelog

### 2.0.2
- Fixed JavaScript loading for field mapping AJAX
- Embedded script directly in settings page

### 2.0.1
- Fixed AJAX nonce mismatch

### 2.0.0
- Complete rewrite with dynamic field mapping
- Audience dropdown (no more typing IDs)
- Full merge field support
- Tag names instead of IDs

## License

GPL v2 or later
