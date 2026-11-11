# tests/e2e/AGENTS.md

Scope: applies to Playwright end-to-end tests in `tests/e2e/`.

For repository-wide rules, always read the root `AGENTS.md` first.

## CRITICAL Rules

- **CRITICAL:** Treat `test:e2e-setup` against `--base_url` as destructive setup for a target site. Use only disposable/staging environments.
- **CRITICAL:** Do not commit secrets from `tests/e2e/config/local.env`.
- **CRITICAL:** Keep tests deterministic; avoid unnecessary sleeps and flaky selectors.
- **CRITICAL:** After merging E2E helpers, run `npx playwright test --config=tests/e2e/config/playwright.config.js --list` to catch parse errors; the main Jest and lint commands exclude these files.
- **CRITICAL:** For Stripe iframe interactions, do not rely on `networkidle`; use deterministic field readiness/visibility checks.
- For Classic Checkout network-failure tests, wait for the initial recalculation and trigger a control that requests a new update. `fill()` followed by Tab can leave WooCommerce's address state clean; assert that the intercepted request actually failed.
- **CRITICAL:** Reuse existing setup/utility helpers before adding new setup logic.

## Environment and Commands

| Task | Command | Notes |
| --- | --- | --- |
| Full setup | `npm run test:e2e-setup -- --base_url=<url>` | Remote setup path. Can install/configure WooCommerce and Stripe settings. |
| Run default project | `npm run test:e2e -- --base_url=<url>` | Uses `default` Playwright project. |
| Run specific project | `npm run test:e2e-run -- --project=<name> --base_url=<url>` | Project names include `default`, `acss`, `becs`, `blik`, `optimized-checkout`. |
| Debug run | `npm run test:e2e-debug -- --base_url=<url>` | Playwright debug mode. |
| Docker setup/run | `npm run test:e2e-setup` then `npm run test:e2e` | Local Docker path (`http://localhost:8088`). |
| Setup under a specific theme | `WP_THEME=<slug> npm run test:e2e-setup` | Defaults to `storefront`. Takes a WordPress.org theme slug. CI runs every project under Storefront and Twenty Twenty-Five (`.github/workflows/e2e-tests.yml`). |
| Tear down Docker | `npm run test:e2e-down` | Stops E2E containers. |

## File Layout

- Runner scripts: `tests/e2e/bin/`
- Environment and setup config: `tests/e2e/config/`, `tests/e2e/env/`
- Test specs and setup files: `tests/e2e/tests/`
- Shared helpers: `tests/e2e/utils/`
- Fixture data: `tests/e2e/test-data/`

## Common Pitfalls

- Running against the wrong site because `--base_url` was omitted or incorrect.
- Assuming setup is read-only: setup scripts actively configure plugins/options/pages.
- Duplicating helper logic instead of extending `tests/e2e/utils/`.
- Adding overly broad selectors that break with minor UI adjustments.

## Authoring Guidance

- Keep one test focused on one behavior.
- Prefer existing annotations/tags and naming patterns.
- For new payment-method coverage, mirror existing per-method setup/spec organization.
- If a test is flaky, fix root cause before adding retries or sleeps.
- When fixing flow-specific flakiness, verify the equivalent flow (classic vs blocks/OC/ECE) unless explicitly out of scope.

## Theme-Agnostic Helpers (MUST)

The shared helpers in `tests/e2e/utils/` must not assume the active theme. The default Docker setup installs Storefront (classic markup), but the suite is expected to run under block themes too (for example Twenty Twenty-Five), where WooCommerce renders different markup for the same content.

- **MUST** drive cart state through the Store API (`/wp-json/wc/store/v1/cart`, `.../products`), not shop-loop XPath or the header cart counter (`.cart-contents .count`) — those are classic-theme-only. `setupCart` adds every line item through the API.
- **MUST** match user-facing text or roles, not classic wrapper classes, for notices and headings: order-received is a plain `h1` under block themes (not `h1.entry-title`); cart-empty and checkout errors render inside `wc-block-components-notice-banner` (not `.wc-empty-cart-message`/`.woocommerce-error`). Use `getByRole`/`getByText`, or a comma-selector covering both markups.
- **MUST** go through `clickPlaceOrder`, `tickSavePaymentMethodBlocks` and `selectBlocksPaymentMethod`/`selectShortcodePaymentMethod` in specs. Under block themes a physical click can land on nothing or get undone by a re-render, with no error. These helpers dispatch the click and verify the result, and are the only place that belongs. Exception: a spec that asserts on the first submission attempt (`required-field-validation.spec.js`) dispatches one click itself, because `clickPlaceOrder` may re-dispatch.
- When adding a helper, verify it under both a classic theme (Storefront) and a block theme before landing.
