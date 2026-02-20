# client/AGENTS.md

Scope: applies to frontend code under `client/`.

For repository-wide rules, always read the root `AGENTS.md` first.

## CRITICAL Rules

- **CRITICAL:** Keep checkout behavior consistent across shortcode and Blocks flows unless the change is explicitly flow-specific.
- **CRITICAL:** If you change user-facing behavior, add or update tests (Jest and/or E2E as appropriate).
- **CRITICAL:** Keep payment method availability, labels, and icons aligned across UI surfaces.
- **CRITICAL:** Payment method availability rules must come from a single source of truth shared across PHP config and frontend rendering.
- **CRITICAL:** Use shared amount/minor-unit normalization utilities; do not compute Stripe-facing or user-facing amounts ad hoc.
- **CRITICAL:** Prefer incremental updates in existing modules over broad rewrites.

## Structure and Ownership

- Blocks integration: `client/blocks/`
- Settings/admin UI: `client/settings/`, `client/entrypoints/`
- Express checkout: `client/express-checkout/`
- Shared data/state: `client/data/`
- Shared utility logic: `client/utils/`, `client/stripe-utils/`
- Payment method visuals: `client/payment-method-icons/`

## Task-to-Command Matrix

| Task | Command |
| --- | --- |
| Build frontend assets | `npm run build:webpack` |
| Run dev watcher | `npm start` |
| Run JS tests | `npm run test:js` |
| Run JS lint | `npm run lint:js` |
| Auto-fix JS lint | `npm run lint:js-fix` |
| Run CSS/SCSS lint | `npm run lint:css` |

## Frontend Conventions

- Reuse existing shared utilities before adding new helpers.
- Keep feature flags and payment-method gating logic centralized and explicit.
- Preserve existing naming patterns and folder placement used by nearby files.
- Keep dependencies minimal; avoid adding libraries for simple logic.

## Common Pitfalls

- Updating a payment method in one place only (for example settings but not blocks/icon map).
- Introducing checkout-flow divergence accidentally between classic and blocks.
- Changing state shape in `client/data/` without updating all consumers.
- Forgetting to rebuild assets when source changes require refreshed build output.
- Mixing display amounts and API minor-unit amounts in per-feature logic.

## Test Mapping

- JS unit tests and config: `tests/js/`
- End-to-end checkout verification: `tests/e2e/`

For behavior changes affecting checkout, pair unit-level checks with at least one relevant E2E path.
