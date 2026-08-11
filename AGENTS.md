# AGENTS.md

This file provides guidance to coding agents working in this repository.

## Project Overview

WooCommerce Stripe Payment Gateway is the official plugin for accepting Stripe payments on WooCommerce stores. It supports 20+ payment methods, including cards, Apple Pay, Google Pay, Klarna, Affirm, SEPA, ACH, Alipay, and Boleto.

**Requirements:** PHP 7.4+, WordPress 6.7+, WooCommerce 9.9+, Node 20.18.1+, npm 10.2.3+

## CRITICAL Rules

- **CRITICAL:** Do not edit WordPress core or WooCommerce core files in `docker/wordpress/` or `docker/wordpress_xdebug/`. Only edit plugin source in this repository.
- **CRITICAL:** Do not commit credentials, API keys, webhook secrets, or `.env` values.
- **CRITICAL:** Keep changes scoped. Do not perform broad refactors unless explicitly requested.
- **CRITICAL:** Code comments MUST explain *why the code is the way it is* for someone reading it cold — the non-obvious constraint, race, or edge case the code guards against — not *what* the code does. Do NOT narrate the change history, the conversation that produced it, or what the diff did. Keep ticket keys (e.g. `STRIPE-123`) out of code comments; put them in the commit message or PR instead. See [Code Comment Conventions](#code-comment-conventions) for the full guidance.
- **CRITICAL:** If you change runtime behavior, run the smallest relevant test suite before claiming completion.
- **CRITICAL:** Bugfixes for fatals, checkout failures, and payment regressions MUST include or update targeted automated tests; code review alone is not enough.
- **CRITICAL:** If you update `phpstan-baseline.neon`, run `npm run phpstan` first, fix legitimate issues, then baseline only unavoidable items.
- **CRITICAL:** Do not mix broad feature work with PHPStan baseline churn in a single commit unless explicitly requested.
- **CRITICAL:** Changes to payment method availability/rendering MUST be validated across classic checkout, Blocks checkout, optimized checkout, and express checkout.
- **CRITICAL:** Respect version support policy: WooCommerce L, L-1, and L-2; transitively WordPress L and L-1 (per WC's [support policy](https://woocommerce.com/support-policy/)).
- **CRITICAL:** Always open pull requests as **drafts** (`gh pr create --draft`). Leave the PR in draft until the human author has reviewed it and explicitly asks to mark it ready — do not mark it ready for review yourself. This keeps agent-authored work out of reviewers' queues until a person has signed off.
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

## Code Comment Conventions

Good comments explain intent; they do not restate the code. The CRITICAL rule above is the gate — this section is the full guidance.

**Do:**

- **Explain WHY, not WHAT.** Comment on intent, constraints, edge cases, and non-obvious decisions — the reason a line exists, not a paraphrase of it.
- **Explain genuinely complex logic.** When the approach is non-trivial (a race, an ordering constraint, a workaround for upstream behavior), describe the approach and the constraints it satisfies, inline or in a docblock.
- **Document limitations, assumptions, and edge-case handling** — what the code deliberately does *not* cover, what it assumes about its inputs or callers, and why an edge case is handled the way it is.
- **Prefer descriptive names over comments.** A well-named function or variable that removes the need for a comment is better than the comment.
- **Keep it concise, relevant, and professional.** Write for the next developer (including future you) trying to understand intent.

**Don't:**

- **Don't document the obvious.** No comments that restate what the code plainly says (`// increment counter`).
- **Don't comment unchanged code.** Only add or revise comments for code you are actually touching; do not annotate lines a diff leaves alone. Unless your change makes an existing comment inaccurate — in that case update that comment.".
- **Don't over-engineer documentation.** No ceremonial docblocks on self-explanatory helpers, no narrating the change history or the conversation that produced the code.

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

## Backward Compatibility

This plugin has backward-compatibility obligations in **both directions**. State the BC impact of any risky change in the PR description.

**As a producer of public API.** This plugin exposes a large public surface that extensions, themes, and other plugins consume: `do_action`/`apply_filters` hooks, public gateway and `WC_Stripe_UPE_Payment_Method` classes, REST routes, and `woocommerce_stripe_*` option keys. Any change to a public or externally exposed class, interface, function, or method signature is **high-risk**. Adding a required method to an interface external code can implement is backward-incompatible — existing implementers fatal on load. Prefer a non-breaking alternative: add the method to a concrete class, introduce a separate new interface, or provide a default via an abstract base class.

**Deprecate, don't rename.** Never rename or remove an existing public symbol (class, interface, method, constant, hook, option key) in place. Mark the old one `@deprecated`, add the replacement alongside it, and keep both working through a deprecation window so consumers can migrate.

**As a consumer of upstream WooCommerce contracts.** This plugin implements upstream WooCommerce interfaces — notably `Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface` (see `includes/agentic-commerce/`). The `Internal` namespace is **not** a stability guarantee: WooCommerce can change these contracts, and doing so is exactly what broke this plugin when WC 10.9.0 added a required `get_entry_count()` to `FeedInterface` and older Stripe versions fataled on load. When implementing an upstream interface, keep the implementation compatible with the supported WC range (L, L-1, L-2) and guard against upstream contract changes rather than assuming the interface is frozen. See `includes/agentic-commerce/AGENTS.md`.

### The compatibility surface is wider than PHP signatures

WordPress exposes more contracts than class and function signatures. The following are equally binding: a change to any of them is **high-risk** and requires the same backward-compatibility impact statement in the PR description.

**Hooks and filters are public contracts.** Every `do_action` and `apply_filters` call in this plugin — the `wc_stripe_*` and `woocommerce_stripe_*` families — is an interface third-party callbacks depend on. Removing a hook, renaming it, or removing/reordering its arguments breaks every attached callback. Changing *when* or *whether* a hook fires breaks consumers just as surely: a filter that still fires on classic checkout but no longer fires on the Blocks path is a silent breakage for every store on that path. Additive is the safe path — append new arguments at the end, never remove or reorder existing ones. To retire a hook, fire it through `do_action_deprecated()` / `apply_filters_deprecated()` for a deprecation window instead of deleting it. For example, `WC_Stripe_API::request()` preserves the old `woocommerce_stripe_*` request filters while pointing to `wc_stripe_*` replacements, and `WC_Stripe_Webhook_Handler::process_webhook_payment()` preserves an old `wc_gateway_stripe_*` action while pointing to its replacement.

**Do not assume global state.** This plugin's code runs in admin, REST, CLI, cron/Action Scheduler, webhook, and front-end checkout contexts, and not all of them set the globals a front-end request does (`$post`, `$wp_query`, an initialized session, cart, or customer). Webhook and Action Scheduler paths are the trap here: `WC_Stripe_Webhook_Handler` processes Stripe events with no cart and no session, so a newly introduced `WC()->cart` or `WC()->session` read on a path reachable from there is a fatal or a silent misbehavior. Guard the exact dependency explicitly: `function_exists`/`class_exists` for symbols, `isset` for variables, `did_action` for lifecycle state, and verify that `WC()` and the required component are initialized before dereferencing `WC()->…`.

**Do not assume single-site.** Multisite changes where data lives: site-scoped vs network-scoped options (`get_option` vs `get_site_option`), per-site tables, user roles and capabilities, and upload paths all differ. This plugin's settings (`woocommerce_stripe_settings`, `woocommerce_stripe_{method}_settings`) and its account cache are site-scoped — each site in a network has its own Stripe account and keys, and no change may leak one site's credentials or cached account data into another. A change that reads or writes site state must state in its PR whether it behaves correctly under multisite — and if it was not tested there, say so explicitly.

**Do not assume install layout.** WordPress could be configured to run in a subdirectory, with relocated `wp-content`, and behind reverse proxies. Never build paths or URLs by concatenation from the domain root; derive them (`plugins_url()`, `plugin_dir_path()`, `wp_upload_dir()`, and mind the `home_url()` vs `site_url()` distinction). This governs payment method icons and built assets, and the webhook endpoint URL registered with Stripe — an endpoint that resolves on a root install and 404s on a subdirectory install silently breaks webhook delivery for that store.

### Before changing any public or externally exposed surface (agent checklist)

1. Identify the contract you are touching: signature, hook, global/scope expectation, site topology, or install layout.
2. Assume unseen consumers. You cannot enumerate third-party code; if the surface is reachable from outside this plugin, someone consumes it.
3. Prefer the additive path (new optional method, appended hook argument, new symbol + deprecation) over changing what exists.
4. State the impact in the PR description: what changed, who could consume it, and why it is safe or what the deprecation path is.
5. If you cannot establish the impact, stop and flag it to the user as needing review.

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
