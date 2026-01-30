# AGENTS.md

This file provides guidance to AI coding assistants when working with code in this repository.

## Project Overview

WooCommerce Stripe Payment Gateway - Official plugin for accepting Stripe payments on WooCommerce stores. Supports 20+ payment methods including credit cards, Apple Pay, Google Pay, Klarna, Affirm, SEPA, ACH, and regional methods like Alipay and Boleto.

**Requirements:** PHP 7.4+, WordPress 6.7+, WooCommerce 9.9+, Node 20.18.1+, npm 10.2.3+

## Common Commands

```bash
# Setup & Build
npm install                  # Install all dependencies (runs composer install + playwright install)
npm run build:webpack        # Build frontend assets
npm start                    # Development mode with hot reload

# Docker Development Environment
npm run up                   # Start Docker environment
npm run down                 # Stop Docker
npm run listen               # Start Stripe webhook listener (forward to localhost:8072)
npm run xdebug:start         # Enable Xdebug in Docker
npm run xdebug:stop          # Disable Xdebug in Docker

# Testing
npm run test:php             # Run PHPUnit tests (requires Docker running)
npm run test:js              # Run Jest unit tests
npm run test:js -- --watch   # Jest in watch mode
npm run test:e2e             # Run E2E tests (default project)
npm run test:e2e-setup       # Setup E2E environment first

# Code Quality
npm run lint:php             # PHP CodeSniffer
npm run lint:php-fix         # Auto-fix PHP issues
npm run lint:js              # ESLint
npm run lint:js-fix          # Auto-fix ESLint issues
npm run phpstan              # PHPStan static analysis (Level 8)
npm run phpstan:baseline     # Update PHPStan baseline for new/changed errors
```

## Architecture

### Backend Structure (`includes/`)

- **Entry Point:** `woocommerce-gateway-stripe.php` → `WC_Stripe` singleton via `woocommerce_gateway_stripe()`
- **Core Services:**
  - `WC_Stripe_API` - Stripe API communication (singleton)
  - `WC_Stripe_Webhook_Handler` - Processes Stripe webhooks
  - `WC_Stripe_Intent_Controller` - Payment Intent lifecycle management
  - `WC_Stripe_Customer` - WooCommerce-Stripe customer linking

### Payment Gateway Hierarchy

```
WC_Payment_Gateway_CC (WooCommerce)
    └── WC_Stripe_Payment_Gateway (abstract)
            └── WC_Stripe_UPE_Payment_Gateway (main multi-method gateway)
                    └── Uses WC_Stripe_UPE_Payment_Method subclasses

WC_Stripe_UPE_Payment_Method (abstract)
    ├── WC_Stripe_UPE_Payment_Method_CC (credit cards)
    ├── WC_Stripe_UPE_Payment_Method_Klarna
    ├── WC_Stripe_UPE_Payment_Method_SEPA
    └── ... (20+ payment methods)
```

**Traits for subscriptions/pre-orders:**
- `WC_Stripe_Subscriptions_Trait` - Subscription payment handling
- `WC_Stripe_Pre_Orders_Trait` - Pre-order payment handling

### Frontend Structure (`client/`)

- **React Admin UI:** `client/settings/`, `client/entrypoints/`
- **WooCommerce Blocks:** `client/blocks/` (checkout integration)
- **State Management:** Redux-style stores in `client/data/` (settings, account, payment-gateway, account-keys)
- **Express Checkout:** `client/express-checkout/` (Apple Pay, Google Pay)

### Key Patterns

1. **Singleton Pattern:** `WC_Stripe::get_instance()`, `WC_Stripe_API::get_instance()`
2. **Abstract Base Classes:** Payment methods extend `WC_Stripe_UPE_Payment_Method`
3. **Settings Storage:** WordPress options `woocommerce_stripe_settings`, `woocommerce_stripe_{method}_settings`
4. **REST Controllers:** `includes/admin/` - Settings, payment gateways, account keys endpoints
5. **Database Caching:** `WC_Stripe_Database_Cache` provides TTL-based caching stored as WordPress options with in-memory per-request cache and async cleanup via Action Scheduler

### Adding New Payment Methods

1. Create class extending `WC_Stripe_UPE_Payment_Method` in `includes/payment-methods/`
2. Register in `WC_Stripe::init()`
3. Add to `WC_Stripe_Payment_Methods` constants class
4. Create icon in `client/payment-method-icons/`
5. Add Blocks component in `client/blocks/upe/`

## PHPStan Workflow

PHPStan runs at Level 8 on every PR. When you see errors:
- Fix legitimate issues (null checks, type mismatches)
- For errors that can be ignored, update the baseline: `npm run phpstan:baseline`
- Always commit baseline changes to avoid noise for other developers

## Test Organization

- **PHP Unit Tests:** `tests/phpunit/` - mirrors `includes/` structure
- **Jest Tests:** `tests/js/` - React component tests
- **E2E Tests:** `tests/e2e/` - Playwright with projects: default, legacy, acss, blik, becs, optimized-checkout

**PHPUnit Convention:** Use `@dataProvider` for parameterized tests. This is the standard pattern in this codebase for testing multiple input/output scenarios.

## Version Support

Follows L-2 policy: supports current and two previous major versions of WordPress (strict) and WooCommerce (loose).
