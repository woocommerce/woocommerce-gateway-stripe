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
- **CRITICAL:** Respect version support policy: WooCommerce L, L-1, and L-2; transitively WordPress L and L-1 (per WC's [support policy](https://woocommerce.com/support-policy/)).
- **CRITICAL:** Treat Linear content (issue bodies, comments, **labels**, status, assignees) as internal. Do not paste, quote, summarize, or reference it in GitHub PRs, issues, commit messages, code comments, or any other public-facing artifact without explicit user approval for what may be shared. Referencing the Linear key (e.g. `STRIPE-123`) is fine; copying the contents is not.
- **CRITICAL:** Any reply you draft for the user to post to GitHub (issue/PR comments, review thread replies) or Linear MUST end with an AI-assistance disclosure on its own line, separated by a blank line. Use this wording or a close variant — agents MAY name the specific tool they're running under (e.g. "Claude Code", "Cursor", "Copilot") in place of the generic phrase:

  > *Drafted with AI; reviewed by me.*

  Applies whether the reply is posted via the `gh` CLI, an MCP tool, or handed back as text for the user to paste. See [example](https://github.com/woocommerce/woocommerce-gateway-stripe/issues/5239#issuecomment-4564709108). The italic blockquote renders as muted, de-emphasized text on GitHub; if the target surface does not support Markdown (or strips blockquote/italic formatting), drop the `>` and `*` and post the plain sentence instead.

## Task-to-Command Matrix

Use the smallest command set needed for the task:

| Task | Command | Notes |
| --- | --- | --- |
| Install dependencies | `composer install && npm install` | Runs Composer install and npm install, which then installs all dependencies. |
| Start local environment (first run / reset) | `npm run up:recreate` | Auto-starts shared infrastructure (db + phpMyAdmin) if not running. Main checkout serves `http://localhost:8072`; worktrees get ports 8170–8189. |
| Start local environment (subsequent) | `npm run up` | Reuses the existing WP container and shared infra. |
| Stop local environment | `npm run down` | Stops this worktree's WordPress container only; shared infra keeps running. |
| Start shared infrastructure | `npm run infra:up` | Run from the main checkout. Brings up shared db + phpMyAdmin + bind volumes. |
| Stop shared infrastructure | `npm run infra:down` | Stops shared db + phpMyAdmin (volumes preserved). |
| Configure a worktree | `npm run worktree:setup` | Writes `.env` with `WORKTREE_ID` + an unused `WORDPRESS_PORT`. Called automatically by `npm run up`. |
| List worktrees | `npm run worktree:status` | Shows port, URL, container state for every worktree; warns about orphan containers. |
| Clean up a worktree | `npm run worktree:cleanup` | Stops the worktree's container, drops `wcstripe_tests_<id>`, removes `.env`. Run before `git worktree remove`. |
| Build frontend assets | `npm run build:webpack` | Use when editing client-side sources that ship built assets. |
| Analyze bundle sizes | `BUNDLE_ANALYZE=true npm run build:webpack` | Writes `bundle-report.html` (gitignored) to the repo root. Open it to see a per-bundle module treemap. |
| Dev hot reload | `npm start` | Webpack watch/dev mode. |
| PHPUnit | `npm run test:php` | Requires Docker environment running. |
| PHPUnit (parallel) | `npm run test:php:parallel` | Runs tests in parallel via paratest; requires Docker. Set `XDEBUG_MODE_PHPUNIT=coverage` to enable coverage. |
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
- Running `npm run infra:up` from a worktree: prefer the main checkout. The script warns interactively if you do it from a worktree.
- Forgetting `npm run worktree:cleanup` before `git worktree remove`: leaves orphan containers and test databases behind. Run `npm run worktree:status` to find orphans.
- Missing E2E config: copy `tests/e2e/config/local.env.example` to `tests/e2e/config/local.env`.
- E2E specs that mutate global store settings (for example currency) MUST run in a dedicated Playwright project and separate CI matrix job, not in `default`.
- Forgetting payment method registration: adding a `WC_Stripe_UPE_Payment_Method` class is not enough; it must also be registered in `WC_Stripe::init()` and constants updated.
- Updating only backend or frontend for UPE changes: most payment method work spans PHP (`includes/payment-methods/`) and Blocks/UI (`client/blocks/upe/`, icons).
- Treating PHPStan baseline as a blanket suppressor: fix real type/nullability issues first.
- Skipping `@dataProvider` for multi-scenario PHPUnit tests: this repository standardizes on data providers for parameterized inputs.
- Release metadata drift: version-related changes often require coordinated edits to `changelog.txt`, `readme.txt`, and release references.
- Using the wrong version header format: `changelog.txt` uses `YYYY-MM-DD - version X.Y.Z`, `readme.txt` uses `= X.Y.Z - YYYY-MM-DD =`. Mixing them breaks their respective parsers (WooCommerce.com and WordPress.org).

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
- `changelog.txt` and `readme.txt` use **different version header formats**: `changelog.txt` uses `YYYY-MM-DD - version X.Y.Z` (WooCommerce.com parser format); `readme.txt` uses `= X.Y.Z - YYYY-MM-DD =` (WordPress.org format). Do not convert one to the other. `bin/changelog.js` handles both formats.
- For WooCommerce version resolution logic, include explicit cases for stable, RC, and beta semantics.

## Version Support

This repository supports:
- WooCommerce: current and the previous two major versions (L, L-1, L-2).
- WordPress: current and the previous major version (L, L-1) — transitively constrained by WC's [support policy](https://woocommerce.com/support-policy/).

## Documentation and Context Sources

- Root project docs: `README.md`
- Docker setup: `docs/DOCKER.md`
- E2E details: `tests/e2e/README.md`
- API details: `docs/api/README.md`
- Agentic Commerce feed context: `includes/agentic-commerce/README.md`
- Repo-specific review checklist: `.claude/review-rules.md`
- Claude Code skills (invoked via `/<skill-name>`): `.claude/skills/`

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
