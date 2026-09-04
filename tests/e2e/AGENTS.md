# tests/e2e/AGENTS.md

Scope: applies to Playwright end-to-end tests in `tests/e2e/`.

For repository-wide rules, always read the root `AGENTS.md` first.

## CRITICAL Rules

- **CRITICAL:** Treat `test:e2e-setup` against `--base_url` as destructive setup for a target site. Use only disposable/staging environments.
- **CRITICAL:** Do not commit secrets from `tests/e2e/config/local.env`.
- **CRITICAL:** Keep tests deterministic; avoid unnecessary sleeps and flaky selectors.
- **CRITICAL:** For Stripe iframe interactions, do not rely on `networkidle`; use deterministic field readiness/visibility checks.
- **CRITICAL:** Reuse existing setup/utility helpers before adding new setup logic.

## Environment and Commands

| Task | Command | Notes |
| --- | --- | --- |
| Full setup | `npm run test:e2e-setup -- --base_url=<url>` | Remote setup path. Can install/configure WooCommerce and Stripe settings. |
| Run default project | `npm run test:e2e -- --base_url=<url>` | Uses `default` Playwright project. |
| Run specific project | `npm run test:e2e-run -- --project=<name> --base_url=<url>` | Project names include `default`, `acss`, `becs`, `blik`, `optimized-checkout`. |
| Debug run | `npm run test:e2e-debug -- --base_url=<url>` | Playwright debug mode. |
| Docker setup/run | `npm run test:e2e-setup` then `npm run test:e2e` | Local Docker path (`http://localhost:8088`). |
| Second Docker stack | `E2E_PROJECT=<name> E2E_WP_PORT=<port> E2E_DB_PORT=<port> npm run test:e2e-setup` | The default project name and ports (8088/6789) are machine-global; override all three (on every `test:e2e*` command, including `test:e2e-down`) to run side-by-side with another checkout's stack. |
| Setup under a specific theme | `WP_THEME=<slug> npm run test:e2e-setup` | Defaults to `storefront` (classic). For a WordPress.org slug that is enough; for a block theme not on WordPress.org (e.g. `purple` from the woo-themes repo) also set `WP_THEME_TARBALL_URL=<git-tarball-url>` whose top level contains a `<slug>/` directory. CI runs the theme-sensitive projects under both a classic and a block theme via these vars — see `.github/workflows/e2e-tests.yml`. |
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

The shared helpers in `tests/e2e/utils/` must not assume the active theme. The default Docker setup installs Storefront (classic markup), but the suite is expected to run under block/FSE themes too, where WooCommerce renders different markup for the same content.

- **MUST** drive cart state through the Store API (`/wp-json/wc/store/v1/cart`, `.../products`), not shop-loop XPath or the header cart counter (`.cart-contents .count`) — those are classic-theme-only. `setupCart`/`clickAddToCartButton` resolve product pages and verify adds via the API.
- **MUST** match user-facing text or roles, not classic wrapper classes, for notices and headings: order-received is a plain `h1` under block themes (not `h1.entry-title`); cart-empty and checkout errors render inside `wc-block-components-notice-banner` (not `.wc-empty-cart-message`/`.woocommerce-error`). Use `getByRole`/`getByText`, or a comma-selector covering both markups.
- **MUST** re-verify add-to-cart against the cart after clicking: block themes add via the Interactivity API and silently drop a click that lands before hydration. `clickAddToCartButton` re-clicks (guarded so a slow add cannot double-add) until the count moves.
- **MUST** click Place Order via `clickPlaceOrder`/`dispatchEvent('click')`, never a physical `.click()`. Tall block-theme checkouts trigger a keep-in-view counter-scroll after card entry that moves the button out from under a physically-aimed click; the dispatched event is immune.
- When adding a helper, verify it under both a classic theme (Storefront) and a block theme before landing.
