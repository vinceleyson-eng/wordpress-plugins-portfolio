# Stripe PHP Library

The Stripe PHP library should be installed via Composer in production:

```bash
composer require stripe/stripe-php
```

This ensures you get the latest security updates and proper dependency management.

The plugin code references the Stripe library and expects it to be available at:
```php
require_once WCSI_PLUGIN_DIR . 'vendor/stripe/init.php';
```

For production deployment, either:
1. Install via Composer (recommended)
2. Download the official Stripe PHP library and place in the vendor/ directory