# AGENTS.md

This file provides guidance to coding agents working in this repository.

## Project Overview

WooCommerce Stripe Payment Gateway is the official plugin for accepting Stripe payments on WooCommerce stores. It supports 20+ payment methods, including cards, Apple Pay, Google Pay, Klarna, Affirm, SEPA, ACH, Alipay, and Boleto.

**Requirements:** PHP 7.4+, WordPress 6.7+, WooCommerce 9.9+, Node 20.18.1+, npm 10.2.3+

## CRITICAL Rules

- **CRITICAL:** Do not edit WordPress core or WooCommerce core files in `docker/wordpress/` or `docker/wordpress_xdebug/`. Only edit plugin source in this repository.
- **CRITICAL:** Do not commit credentials, API keys, webhook secrets, or `.env` values.
- **CRITICAL:** Keep changes scoped. Do not perform broad refactors unless explicitly requested.
- **CRITICAL:** If you change runtime behavior, run the smallest relevant test suite before claiming completion.
- **CRITICAL:** Bugfixes for fatals, checkout failures, and payment regressions MUST include or update targeted automated tests; code review alone is not enough.
- **CRITICAL:** If you update `phpstan-baseline.neon`, run `npm run phpstan` first, fix legitimate issues, then baseline only unavoidable items.
- **CRITICAL:** Do not mix broad feature work with PHPStan baseline churn in a single commit unless explicitly requested.
- **CRITICAL:** Changes to payment method availability/rendering MUST be validated across classic checkout, Blocks checkout, optimized checkout, and express checkout.
- **CRITICAL:** Respect version support policy (WordPress strict L-2, WooCommerce loose L-2).

## Task-to-Command Matrix

Use the smallest command set needed for the task:

| Task | Command | Notes |
| --- | --- | --- |
| Install dependencies | `composer install && npm install` | Runs Composer install and npm install, which then installs all dependencies. |
| Start local environment | `npm run up` | Docker-based site at `http://localhost:8072`. |
| Stop local environment | `npm run down` | Preserves local Docker state. |
| Build frontend assets | `npm run build:webpack` | Use when editing client-side sources that ship built assets. |
| Dev hot reload | `npm start` | Webpack watch/dev mode. |
| PHPUnit | `npm run test:php` | Requires Docker environment running. |
| Jest unit tests | `npm run test:js` | Use `npm run test:js:watch` during iteration. |
| E2E setup | `npm run test:e2e-setup -- --base_url=...` | Requires `tests/e2e/config/local.env`. |
| E2E run | `npm run test:e2e -- --base_url=...` | Supports Playwright CLI flags. |
| PHP lint | `npm run lint:php` | Use `npm run lint:php-fix` when appropriate. |
| JS lint | `npm run lint:js` | Use `npm run lint:js-fix` when appropriate. |
| PHP static analysis | `npm run phpstan` | Level 8 static analysis for PHP files. |
| Refresh PHP static analysis baseline | `npm run phpstan:baseline` | Only after triaging `npm run phpstan` results. |
| Stripe webhook listener | `npm run listen` | For local webhook forwarding. |

## Common Pitfalls

- Running PHP tests without Docker: `npm run test:php` fails unless containers are up.
- Missing E2E config: copy `tests/e2e/config/local.env.example` to `tests/e2e/config/local.env`.
- E2E specs that mutate global store settings (for example currency) MUST run in a dedicated Playwright project and separate CI matrix job, not in `default`.
- Forgetting payment method registration: adding a `WC_Stripe_UPE_Payment_Method` class is not enough; it must also be registered in `WC_Stripe::init()` and constants updated.
- Updating only backend or frontend for UPE changes: most payment method work spans PHP (`includes/payment-methods/`) and Blocks/UI (`client/blocks/upe/`, icons).
- Treating PHPStan baseline as a blanket suppressor: fix real type/nullability issues first.
- Skipping `@dataProvider` for multi-scenario PHPUnit tests: this repository standardizes on data providers for parameterized inputs.
- Release metadata drift: version-related changes often require coordinated edits to `changelog.txt`, `readme.txt`, and release references.

## Architecture

### Backend Structure (`includes/`)

- **Entry point:** `woocommerce-gateway-stripe.php` loads `WC_Stripe` via `woocommerce_gateway_stripe()`.
- **Core service:** `WC_Stripe_API` for Stripe API communication (singleton).
- **Core service:** `WC_Stripe_Webhook_Handler` for webhook processing.
- **Core service:** `WC_Stripe_Intent_Controller` for payment intent lifecycle.
- **Core service:** `WC_Stripe_Customer` for WooCommerce-Stripe customer linking.

### Payment Gateway Hierarchy

```
WC_Payment_Gateway_CC (WooCommerce)
    └── WC_Stripe_Payment_Gateway (abstract)
            └── WC_Stripe_UPE_Payment_Gateway
                    └── Uses WC_Stripe_UPE_Payment_Method subclasses

WC_Stripe_UPE_Payment_Method (abstract)
    ├── WC_Stripe_UPE_Payment_Method_CC
    ├── WC_Stripe_UPE_Payment_Method_Klarna
    ├── WC_Stripe_UPE_Payment_Method_SEPA
    └── ... (20+ methods)
```

Traits:
- `WC_Stripe_Subscriptions_Trait` for subscription flows.
- `WC_Stripe_Pre_Orders_Trait` for pre-order flows.

### Frontend Structure (`client/`)

- React admin UI: `client/settings/`, `client/entrypoints/`.
- WooCommerce Blocks integration: `client/blocks/`.
- Data stores: `client/data/` (`settings`, `account`, `payment-gateway`, `account-keys`).
- Express checkout flows: `client/express-checkout/`.

### Key Patterns

1. Singleton services (`WC_Stripe::get_instance()`, `WC_Stripe_API::get_instance()`).
2. Payment methods inherit from `WC_Stripe_UPE_Payment_Method`.
3. Settings stored in `woocommerce_stripe_settings` and `woocommerce_stripe_{method}_settings`.
4. Admin REST controllers live in `includes/admin/`.
5. `WC_Stripe_Database_Cache` uses WordPress options + in-memory cache + Action Scheduler cleanup.

### Adding a New Payment Method

1. Add class in `includes/payment-methods/` extending `WC_Stripe_UPE_Payment_Method`.
2. Register method in `WC_Stripe::init()`.
3. Add constants to `WC_Stripe_Payment_Methods`.
4. Add icon in `client/payment-method-icons/`.
5. Add Blocks support in `client/blocks/upe/`.

## Testing Conventions

- PHPUnit tests live in `tests/phpunit/` (mirrors `includes/`).
- Jest tests live in `tests/js/`.
- E2E tests live in `tests/e2e/` (multiple Playwright projects).
- Use `@dataProvider` for PHPUnit parameterized scenarios.
- For behavior changes, prefer adding/updating tests nearest to touched code.
- For checkout behavior or payment-method availability changes, verify at least one classic path and one Blocks/OC/ECE path.

## Release Hygiene

- For version/release changes, update `changelog.txt`, `readme.txt` stable tag, and related version references together.
- For WooCommerce version resolution logic, include explicit cases for stable, RC, and beta semantics.

## Version Support

This repository follows the L-2 policy:
- WordPress: strict current and previous two major versions.
- WooCommerce: loose current and previous two major versions.

## Documentation and Context Sources

- Root project docs: `README.md`
- Docker setup: `docs/DOCKER.md`
- E2E details: `tests/e2e/README.md`
- API details: `docs/api/README.md`
- Agentic Commerce feed context: `includes/agentic-commerce/README.md`

## Directory-Specific Instructions

Read these files when working in each area:

- `includes/AGENTS.md` for backend/PHP changes.
- `client/AGENTS.md` for frontend/Blocks/settings changes.
- `tests/e2e/AGENTS.md` for Playwright E2E work.
- `includes/agentic-commerce/AGENTS.md` for product feed integration work.

## Agent Instruction Maintenance (MUST)

Update this file when any of these occur:

- An agent made an avoidable mistake due to missing project context.
- A reviewer had to correct an assumption about architecture, commands, or conventions.
- A new recurring pitfall appears in two or more PRs.
- Build/test/lint workflows changed.

When adding guidance, prefer concise, imperative rules with explicit priority words like **MUST** and **CRITICAL**.
