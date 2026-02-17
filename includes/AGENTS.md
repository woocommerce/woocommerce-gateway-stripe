# includes/AGENTS.md

Scope: applies to all PHP code under `includes/`.

For repository-wide rules, always read the root `AGENTS.md` first.

## CRITICAL Rules

- **CRITICAL:** Keep WooCommerce and WordPress L-2 compatibility in mind for new PHP features.
- **CRITICAL:** Do not silently change option keys, action names, or filter names used externally.
- **CRITICAL:** If behavior changes, update or add PHPUnit coverage in `tests/phpunit/`.
- **CRITICAL:** Treat WordPress/WooCommerce and hook-derived values as untrusted (`null|false|mixed`) until validated.
- **CRITICAL:** Prefer focused changes in existing classes over large cross-cutting refactors.

## Structure and Ownership

Core bootstrap and service wiring:

- `includes/class-wc-stripe.php`
- `includes/class-wc-stripe-api.php`
- `includes/class-wc-stripe-intent-controller.php`
- `includes/class-wc-stripe-webhook-handler.php`

Admin REST and settings:

- `includes/admin/`

Payment method implementations:

- `includes/payment-methods/`
- `includes/payment-tokens/`
- `includes/constants/class-wc-stripe-payment-methods.php`

Compatibility layers (subscriptions, pre-orders, other integrations):

- `includes/compat/`

Agentic Commerce feed integration:

- `includes/agentic-commerce/`

## Task-to-Command Matrix

| Task | Command |
| --- | --- |
| Run backend tests | `npm run test:php` |
| Run PHP lint | `npm run lint:php` |
| Auto-fix PHPCS issues | `npm run lint:php-fix` |
| Run static analysis | `npm run phpstan` |
| Refresh baseline after triage | `npm run phpstan:baseline` |

## Backend Conventions

- Follow existing class naming/file naming conventions (`class-wc-stripe-*.php`, `trait-wc-stripe-*.php`).
- Use strict typing patterns that already exist in touched files.
- Keep translation wrappers and escaping patterns consistent with neighboring code.
- Keep hooks and side effects explicit. If adding hooks, ensure lifecycle placement is intentional.
- Use data providers for parameterized PHPUnit coverage.
- Define failure contracts for token/intent creation paths and handle failure explicitly at call sites.
- For amount conversion paths, round before integer casting and test edge precision cases.

## Common Pitfalls

- Adding a payment method class without updating method registration/constants.
- Updating only one layer of a flow (for example gateway logic without webhook/intent handling).
- Treating PHPStan baseline updates as the first option instead of the last option.
- Changing compatibility behavior (subscriptions/pre-orders) without targeted tests.
- Calling methods on potential `null`/`false` token, order, or payment objects.
- In recurring-payment capable methods, missing subscriptions/pre-orders initialization or regression coverage.

## Test Mapping

- General backend tests: `tests/phpunit/`
- Admin controller tests: `tests/phpunit/Admin/`
- Payment method tests: `tests/phpunit/PaymentMethods/`
- Payment token tests: `tests/phpunit/PaymentTokens/`

When adding a new backend class, add or update the closest mirrored test file.
