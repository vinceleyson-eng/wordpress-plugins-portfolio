# Hearing Aid Provider Membership

A complete membership management system for hearing aid providers with Stripe integration, built for WordPress.

## Features

### 🔐 Membership Tiers
- **Unverified (Free)** - Basic listing, 1 location
- **Verified ($49/mo)** - Enhanced features, 3 locations
- **Preferred ($99/mo)** - Premium placement, 10 locations

### 💳 Stripe Integration
- Secure payment processing
- Subscription management
- Automatic billing and renewals
- Customer portal integration

### 📍 Location Management
- Multiple store locations per membership
- Location-based member type badges
- Pending approval workflow for new locations
- ACF field integration for store details

### 👨‍⚕️ Audiologist Profiles
- Create and manage audiologist profiles
- Link audiologists to store locations
- Admin approval workflow
- Bio and credentials management

### 🔒 Member Portal
- Custom login page (bypasses wp-login.php)
- Account dashboard
- Store editor for members
- Subscription management

### ⚙️ Admin Features
- Membership overview with statistics
- Revenue tracking and MRR display
- Pending approvals management
- Account assignment tool (tabbed interface)
- Transaction history
- Bulk store/audiologist assignment

## Installation

1. Upload the `hearing-aid-membership` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to **HA Membership → Settings** to configure Stripe API keys
4. Set up pricing for each membership tier

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Stripe account for payment processing
- ACF Pro (recommended for extended fields)

## Database Tables

The plugin creates custom tables:
- `{prefix}_ham_memberships` - Member subscriptions
- `{prefix}_ham_transactions` - Payment history
- `{prefix}_ham_claims` - Store claim requests

## Shortcodes

- `[ham_pricing_table]` - Display pricing options
- `[ham_member_login]` - Custom login form
- `[ham_account_dashboard]` - Member dashboard
- `[ham_free_signup]` - Free tier registration

## Custom Post Types

- `hearing-aid-store` - Store locations
- `audiologist` - Audiologist profiles

## Hooks & Filters

```php
// Modify membership types
add_filter('ham_membership_types', function($types) {
    return $types;
});

// After membership created
add_action('ham_membership_created', function($membership_id, $user_id) {
    // Custom logic
}, 10, 2);
```

## Screenshots

### Admin Dashboard
- Membership statistics at a glance
- MRR and subscriber counts
- Quick access to all management tools

### Account Assignment Tool
- Tabbed interface for easy navigation
- Bulk user account creation
- Store and audiologist assignment

## Changelog

### 1.0.1
- Added tabbed interface for Account Assignment
- Fixed admin table styling issues
- Improved date handling for billing dates
- Added store/audiologist management to Edit Membership page

### 1.0.0
- Initial release
- Stripe subscription integration
- Member portal with custom login
- Admin management tools

## License

GPL v2 or later

## Author

Built by [Bidview Marketing](https://bidviewmarketing.com)
