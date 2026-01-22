# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

WooCommerce Stripe Payment Gateway - Official WordPress plugin for accepting Stripe payments in WooCommerce stores. Supports 135+ currencies, Apple Pay, Google Pay, Klarna, Affirm, ACH, and other payment methods.

**Key Constants (defined in `woocommerce-gateway-stripe.php`):**
- `WC_STRIPE_VERSION` - Current plugin version
- `WC_STRIPE_MIN_PHP_VER` - Minimum PHP version: 7.4
- `WC_STRIPE_MIN_WC_VER` - Minimum WooCommerce version: 9.9
- `WC_STRIPE_PLUGIN_PATH` - Absolute path to plugin directory
- `WC_STRIPE_PLUGIN_URL` - Plugin URL

## Development Environment

### Initial Setup
```bash
npm install
composer install
npm run build:webpack
```

**Node/NPM Requirements:**
- Node: >=20.18.1
- NPM: >=10.2.3

If `npm install` fails, use `nvm install` followed by `nvm use` then retry.

### Docker Environment (Recommended)
```bash
npm run up          # Start containers and setup WordPress
npm run down        # Stop containers
```

Access site at: http://localhost:8072

**State Persistence:** Environment state persists in `docker/wordpress` and `docker/data`. To start fresh, delete these folders before running `npm run up`.

**Xdebug:**
```bash
npm run xdebug:start    # Enable Xdebug
npm run xdebug:stop     # Disable Xdebug
```

Install [Xdebug Helper browser extension](https://xdebug.org/docs/remote) to enable on demand.

## Build Commands

### JavaScript/TypeScript
```bash
npm start                # Development mode with auto-reload
npm run build:webpack    # Production build
```

### CSS/SCSS
```bash
npm run sass             # Compile SCSS to CSS
npm run watchsass        # Watch and auto-compile
```

### Full Build
```bash
npm run build            # Complete build: webpack, i18n, uglify, sass, release zip
```

## Testing

### PHP Tests
```bash
npm run test:php         # Run PHPUnit tests
./vendor/bin/phpunit     # Direct PHPUnit execution
```

### JavaScript Tests
```bash
npm run test:js          # Run Jest tests
npm run test:js:watch    # Watch mode
```

### E2E Tests
```bash
npm run test:e2e-setup   # One-time setup
npm run test:e2e-up      # Start E2E environment
npm run test:e2e         # Run default E2E tests
npm run test:e2e-debug   # Run with Playwright inspector
npm run test:e2e-down    # Stop E2E environment
npm run test:e2e-cleanup # Full cleanup
```

**Specific E2E Test Suites:**
- `npm run test:e2e-legacy` - Legacy checkout tests
- `npm run test:e2e-oc` - Optimized checkout tests
- `npm run test:e2e-lpm-acss` - ACSS payment method tests
- `npm run test:e2e-lpm-blik` - BLIK payment method tests
- `npm run test:e2e-lpm-becs` - BECS payment method tests

## Linting & Code Quality

### PHP
```bash
npm run lint:php         # Run PHPCS (only git-tracked files)
npm run lint:php-fix     # Auto-fix with PHPCBF
./vendor/bin/phpcs       # Direct PHPCS execution
./vendor/bin/phpcbf      # Direct PHPCBF execution
```

**Standards:** Uses `phpcs.xml.dist` - WooCommerce-Core and WordPress-Core standards with some exclusions.

### JavaScript/TypeScript
```bash
npm run lint:js          # Run ESLint
npm run lint:js-fix      # Auto-fix
npm run format:js        # Format with Prettier
```

### CSS
```bash
npm run lint:css         # Run Stylelint
```

### PHPStan (Static Analysis)
```bash
npm run phpstan          # Run analysis
npm run phpstan:baseline # Update baseline file
```

**CRITICAL:** When your PR triggers PHPStan errors:
1. Most errors don't need to be fixed immediately
2. **MUST** update baseline file using `npm run phpstan:baseline`
3. This prevents noise in `develop` branch and other developers' PRs

## Architecture

### Plugin Entry Point
- **Main File:** `woocommerce-gateway-stripe.php` - Defines constants, checks dependencies
- **Core Class:** `includes/class-wc-stripe.php` - Singleton pattern, loads all components via `init()` method

### Directory Structure

**`/includes/`** - PHP backend code
- Core gateway implementations: `class-wc-gateway-stripe.php`, `class-wc-stripe-payment-gateway.php`
- API clients: `class-wc-stripe-api.php`, `class-wc-stripe-connect-api.php`
- Feature modules: `/payment-methods/`, `/payment-tokens/`, `/admin/`, `/compat/`
- **NEW: `/agentic-commerce/`** - Agentic Commerce product feed generation (CSV feeds for 100k+ products)

**`/client/`** - JavaScript/React frontend code
- `/blocks/` - Gutenberg block integrations
- `/settings/` - Admin settings UI (React)
- `/express-checkout/` - Apple Pay, Google Pay implementations
- `/optimized-checkout/` - Optimized checkout experience
- `/stripe-utils/` - Stripe.js utilities
- Build entry points in `/entrypoints/`

**`/assets/`** - Compiled CSS/JS assets
- `/js/` - Minified JavaScript
- `/css/` - Compiled CSS from SCSS
- `/images/` - Images and icons

**`/templates/`** - PHP template files for frontend display

**`/tests/`**
- `/phpunit/` - PHP unit tests (PSR-4: `WooCommerce\Stripe\Tests\`)
- `/js/` - Jest tests
- `/e2e/` - Playwright E2E tests

**`/docker/`** - Local development Docker configuration

**`/build/`** - Webpack build output (git-ignored)

### Class Loading Pattern

The plugin uses manual `require_once` statements in `includes/class-wc-stripe.php` rather than autoloading. When adding new classes:

1. Create file in appropriate `/includes/` subdirectory
2. Add `require_once` in `WC_Stripe::init()` method (around line 114-210)
3. Follow pattern: Check file existence with `file_exists()` for optional components
4. Use conditional loading for WooCommerce version-dependent features

**Example:** See lines 209-213 in `includes/class-wc-stripe.php` for Agentic Commerce class loading.

### WooCommerce Integration Points

**Payment Gateway Registration:**
- Gateway classes extend `WC_Payment_Gateway` or `WC_Stripe_Payment_Gateway`
- Registered via `woocommerce_payment_gateways` filter in `WC_Stripe::add_gateways()`

**Blocks Integration:**
- Payment method blocks in `/client/blocks/`
- Server-side integration in `includes/class-wc-stripe-blocks-support.php`

**Settings Pages:**
- Admin settings use React components from `/client/settings/`
- PHP controllers in `/includes/admin/`

### Feature Flags

`includes/class-wc-stripe-feature-flags.php` - Controls feature availability (e.g., `is_amazon_pay_available()`)

### Logging

Use `WC_Stripe_Logger::log( $message )` for all logging. Logs appear in WooCommerce > Status > Logs.

### Agentic Commerce (New Feature)

**Location:** `/includes/agentic-commerce/`

**Purpose:** Generate CSV product feeds for Stripe's Agentic Commerce feature. Handles large catalogs (100k+ products, 200MB+ files) via streaming.

**Key Classes:**
- `WC_Stripe_Agentic_Commerce_Csv_Feed` - CSV feed implementation following WooCommerce's FeedInterface pattern

**Usage Pattern:**
```php
// Follows WooCommerce's FeedInterface pattern (similar to JsonFileFeed)
$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'stripe-agentic-commerce' );
$feed->set_columns( $headers );  // Configure CSV columns
$feed->start();  // Creates file in temp dir, writes CSV headers
foreach ( $products as $product ) {
    // All values must be scalars (string, int, float, bool) or null
    // Format complex data as strings per Stripe spec:
    // - Multiple values: comma-separated "url1.jpg,url2.jpg,url3.jpg"
    // - Structured data: colon-delimited "US:CA:Express:1-2:12.99 USD"
    $feed->add_entry( $product_data );  // Streams to file, no buffering
}
$feed->end();  // Closes file handle
$url = $feed->get_file_url();  // Moves from temp to uploads, returns public URL
```

**Important:** Arrays/objects are NOT supported. Format complex data as strings according to Stripe's specification (see https://docs.stripe.com/agentic-commerce/product-catalog).

**Storage:**
- Write: `get_temp_dir()` (fast, no permission issues)
- Final: `wp-content/uploads/stripe-agentic-commerce/product-feeds/`
- File naming: `{base-name}-{YYYY-MM-DD}-{hash}.csv` (uses `wp_hash()` for uniqueness)

**Security:** Uses `FilesystemUtil::mkdir_p_not_indexable()` to automatically create `.htaccess` and `index.html`; prevents directory listing.

## Coding Standards

### PHP
- WordPress Coding Standards (with WooCommerce extensions)
- PHP 7.4+ type hints where applicable (especially new code)
- Void return types for methods that throw exceptions (matches WooCommerce 10.5.0+ interfaces)
- Short array syntax `[]` is allowed
- PHPCS config: `phpcs.xml.dist`

### JavaScript
- ESLint with `@woocommerce/eslint-plugin` and `@wordpress/eslint-plugin`
- Prettier for formatting
- React functional components preferred

### File Naming
- PHP Classes: `class-wc-stripe-feature-name.php`
- Interfaces: `interface-wc-stripe-feature-name.php`
- Follows PSR-4 for test classes only

## Version Support Policy

**L-2 Policy:** Plugin supports WordPress L-2 versions (strictly) and WooCommerce L-2 versions (loosely).

See: `docs/version-support-policy.md`

When adding features, ensure compatibility with:
- WordPress: 6.7+ (tested up to 6.9)
- WooCommerce: 10.1+ (tested up to 10.4)
- PHP: 7.4+

CI workflow checks compatibility automatically.

## Internationalization (i18n)

```bash
npm run i18n:makepot     # Generate .pot file
npm run i18n:merge       # Merge references
```

Text domain: `woocommerce-gateway-stripe`

Use WordPress i18n functions: `__()`, `_e()`, `esc_html__()`, etc.

## Composer Dependencies

When running `composer install/update`, you may be prompted for a GitHub OAuth token to fetch `subscriptions` and `pre-orders` extensions from private repos.

## Stripe CLI Integration

```bash
npm run listen           # Forward webhooks to local environment
```

Forwards to: `http://localhost:8072/?wc-api=wc_stripe`

## WordPress CLI

```bash
npm run wp -- [command]  # Run WP-CLI commands in Docker container
```

Example: `npm run wp -- plugin list`

## Main Branch

Default branch: `develop` (not `main`)

Use `develop` as base branch for PRs and merges.
