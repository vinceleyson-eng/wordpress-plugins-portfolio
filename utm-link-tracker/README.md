# UTM Link Tracker

Automatically appends UTM parameters to all internal links for consistent tracking across your site.

## How It Works

1. Visitor arrives with UTMs: `yoursite.com/?utm_source=google&utm_medium=cpc`
2. Plugin automatically appends those UTMs to **all internal links** on the page
3. When visitor clicks any link, UTMs follow them throughout their session
4. Your analytics/CRM captures accurate attribution data

## Features

- **Standard UTM tracking** — utm_source, utm_medium, utm_campaign, utm_id, utm_term, utm_content
- **Custom parameters** — Add gclid, fbclid, ref, or any custom param
- **Exclude links** — Skip certain links by CSS selector
- **Session storage** — Remember UTMs across page loads (optional)
- **Dynamic link support** — MutationObserver catches AJAX-loaded links
- **Smart filtering** — Skips mailto:, tel:, javascript:, and external links

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **Settings → UTM Tracker** to configure

## Settings

| Setting | Description |
|---------|-------------|
| Enable Tracking | Turn the feature on/off |
| UTM Parameters | Standard UTMs to track |
| Custom Parameters | Additional params (gclid, fbclid, etc.) |
| Exclude Links | CSS selectors for links to skip |
| Session Storage | Remember UTMs even without URL params |

## Use Cases

- **Google Ads** — Persist gclid across pages
- **Facebook Ads** — Keep fbclid through the funnel
- **Email campaigns** — Track UTMs through multi-page journeys
- **Affiliate tracking** — Maintain ref codes site-wide

## License

GPL v2 or later
