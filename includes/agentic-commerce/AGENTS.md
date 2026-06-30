# includes/agentic-commerce/AGENTS.md

Scope: applies to Agentic Commerce integration code in `includes/agentic-commerce/`.

For repository-wide rules, always read the root `AGENTS.md` first.

## CRITICAL Rules

- **CRITICAL:** Preserve streaming feed behavior. Do not introduce full-catalog in-memory processing.
- **CRITICAL:** Keep `FeedInterface`/integration contracts compatible with WooCommerce Product Feed interfaces.
- **CRITICAL:** Preserve scalar/null input constraints for CSV rows and existing normalization behavior.
- **CRITICAL:** Keep scheduled sync lifecycle safe: schedule on activate, unschedule on deactivate.

## Core Components

- Feed writer: `class-wc-stripe-agentic-commerce-csv-feed.php`
- Integration orchestration: `class-wc-stripe-agentic-commerce-integration.php`
- Product mapping: `class-wc-stripe-agentic-commerce-product-mapper.php`
- Feed validation: `class-wc-stripe-agentic-commerce-feed-validator.php`
- Files API delivery: `class-wc-stripe-agentic-commerce-files-api-delivery.php`
- Schema definition: `class-wc-stripe-agentic-commerce-feed-schema.php`
- CLI hooks: `class-wc-stripe-agentic-commerce-cli.php`

Read `README.md` in this directory for domain-specific feed requirements.

## Behavior Invariants

- Call sequence for feed generation: `set_columns()` -> `start()` -> `add_entry()` -> `end()` -> `get_file_url()`.
- CSV entries must match header count.
- Entry values must remain scalar or `null`; complex values must be serialized before `add_entry()`.
- Keep logging and cleanup behavior on error paths intact.

## Merchant Configuration Surface

See README.md "Merchant configuration cookbook" for the merchant-facing recipes. When changing this surface, keep all of it in sync:

- **Filters** use the shareable `woocommerce_agentic_commerce_*` prefix. The Stripe-prefixed `wc_stripe_agentic_commerce_*` twins are deprecated via `apply_filters_deprecated` (seed the new default) — do not drop them. Canonical names: `woocommerce_agentic_commerce_should_sync_product`, `woocommerce_agentic_commerce_disable_checkout`, `woocommerce_agentic_commerce_addon_detection_meta_keys`.
- **Add-on detection** (`WC_Stripe_Agentic_Commerce_Product_Mapper::product_has_addons()`) is per-product postmeta, resolved on the parent for variations. MUST NOT gate on `class_exists()` of configurator plugins (over-matches the whole catalog).
- **Opt-in toggles** are defaults, not overrides: a custom filter always wins. Both default off, so an upgrade with no new options set MUST behave identically.
- New merchant options/filters/helpers are public surface — add PHPUnit (and JS for UI) coverage, and document them in the cookbook.

## Feed Contract Checklist (MUST)

- Keep schema/header alignment intact when adding or removing fields.
- Preserve unit normalization rules (for example prices, weights, dimensions) and update tests when changing format logic.
- Preserve inventory and variant mapping consistency between mapper output and schema expectations.
- Validate registration and delivery setup failure paths explicitly (including useful logs).

## Task-to-Command Matrix

| Task | Command |
| --- | --- |
| Run PHP tests | `npm run test:php` |
| Run static analysis | `npm run phpstan` |
| Run PHP lint | `npm run lint:php` |

## Test Mapping

Primary coverage is in:
- `tests/phpunit/WC_Stripe_Agentic_Commerce_Csv_Feed_Test.php`
- `tests/phpunit/WC_Stripe_Agentic_Commerce_Feed_Schema_Test.php`
- `tests/phpunit/WC_Stripe_Agentic_Commerce_Feed_Validator_Test.php`
- `tests/phpunit/WC_Stripe_Agentic_Commerce_Files_Api_Delivery_Test.php`
- `tests/phpunit/WC_Stripe_Agentic_Commerce_Integration_Test.php`
- `tests/phpunit/WC_Stripe_Agentic_Commerce_Product_Mapper_Test.php`

## Common Pitfalls

- Breaking feed column/schema alignment when adding fields.
- Using array/object values directly in `add_entry()`.
- Updating schedule constants/hook names without corresponding lifecycle/test updates.
- Modifying delivery behavior without validating setup checks and failure logging.
