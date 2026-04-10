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
