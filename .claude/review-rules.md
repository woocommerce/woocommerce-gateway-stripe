# WC Stripe Gateway Review Rules

Review expectations specific to this codebase. Loaded by review tooling (local and pipeline) to provide repo-specific context on top of generic review practices.

These rules supplement general code review with WC Stripe Gateway conventions, known pitfalls, and lessons from past incidents.

**WooCommerce core context:** Many gateway issues only surface when you understand what WooCommerce does with our hooks. Always check WC core when reviewing code that changes order statuses, hooks into `admin_init` / `init`, or modifies checkout flow. WC core is available at `docker/wordpress/wp-content/plugins/woocommerce/`.

---

## Architecture Compliance

### Payment Gateway Hierarchy

```
WC_Payment_Gateway_CC (WooCommerce)
    └── WC_Stripe_Payment_Gateway (abstract)
            └── WC_Stripe_UPE_Payment_Gateway
                    └── Uses WC_Stripe_UPE_Payment_Method subclasses
```

- [ ] New gateway behavior extends `WC_Stripe_UPE_Payment_Gateway`, not `WC_Stripe_Payment_Gateway` directly
- [ ] Payment method classes extend `WC_Stripe_UPE_Payment_Method`
- [ ] Subscription / pre-order support added via `WC_Stripe_Subscriptions_Trait` / `WC_Stripe_Pre_Orders_Trait`, not by duplicating logic in subclasses

### Payment Method Registration (CRITICAL)

A new `WC_Stripe_UPE_Payment_Method` class is not enough. Adding a method requires all of:

- [ ] Class file under `includes/payment-methods/`
- [ ] Constant in `WC_Stripe_Payment_Methods`
- [ ] Registration in `WC_Stripe::init()` payment method classes array
- [ ] Icon under `client/payment-method-icons/`
- [ ] Blocks support under `client/blocks/upe/`

Skipping any of these → method silently missing on at least one checkout surface.

### Code Placement

- [ ] Backend code lives under `includes/`; admin REST controllers under `includes/admin/`
- [ ] Frontend admin UI under `client/settings/` and `client/entrypoints/`
- [ ] Blocks integration under `client/blocks/`
- [ ] Express checkout under `client/express-checkout/`
- [ ] Shared data stores under `client/data/`
- [ ] Agentic Commerce work under `includes/agentic-commerce/` and respects its `AGENTS.md`

### WordPress Hooks

- [ ] Hooks use the correct priority and hook name
- [ ] New filters and actions follow the `wc_stripe_*` prefix convention
- [ ] Action Scheduler hooks registered in init lifecycle, not in constructors
- [ ] Option keys, action names, and filter names are not silently renamed (CRITICAL — external code depends on these)

### Service Wiring

- [ ] Service access goes through existing singletons: `WC_Stripe::get_instance()`, `WC_Stripe_API::get_instance()`, `WC_Stripe_Webhook_Handler`, `WC_Stripe_Intent_Controller`, `WC_Stripe_Customer`
- [ ] No new global state — extend existing services or follow established singleton wiring
- [ ] No direct option access in feature code for credentials — read API keys via `WC_Stripe_API`

---

## Security

- [ ] User input sanitized: `sanitize_text_field()`, `absint()`, `wp_kses_post()`, etc.
- [ ] Output escaped: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_json_encode()`
- [ ] SQL queries use `$wpdb->prepare()` — no raw string interpolation
- [ ] Nonce verification on form submissions and AJAX handlers
- [ ] Capability checks (`current_user_can()`) on admin REST endpoints and admin actions
- [ ] No sensitive data in logs (card numbers, tokens, secret keys, customer email when avoidable)
- [ ] Stripe webhook signatures verified via `WC_Stripe_Webhook_Handler` — never bypass signature checks under any circumstances
- [ ] Payment amounts validated server-side from the order or Stripe response — never trust client-provided values
- [ ] Webhook handlers idempotent — replays of the same event must not double-process

### Treating WP/WC return values as untrusted

Per `includes/AGENTS.md`, treat WordPress and WooCommerce hook-derived values as `null|false|mixed` until validated:

- [ ] `wc_get_order()` results checked with `instanceof WC_Order` (not bare truthiness — refund objects pass that)
- [ ] User lookups checked for `false` / `WP_User`
- [ ] Stripe SDK responses checked for the expected shape before property access
- [ ] Subscription / pre-order objects checked before passing to compat methods

---

## Performance

### Per-Request Hook Guard (CRITICAL)

Code that runs on `admin_init`, `init`, `wp_loaded`, `shutdown`, or other per-request hooks must NOT perform direct database queries or synchronous Stripe API calls. Use one of:

- An autoloaded `get_option()` value
- A transient with a defined TTL
- An Action Scheduler async job that caches the result
- The `WC_Stripe_Database_Cache` layer (options + memory + Action Scheduler cleanup)

When reviewing, trace any new code path back to its trigger hook. If it ultimately runs per-request, scrutinize for I/O.

### Critical Path

The checkout flow (classic, Blocks, OCS, ECE) is the critical path. Code added to checkout rendering or payment processing must not:

- [ ] Make synchronous Stripe API calls outside `WC_Stripe_API`'s established patterns
- [ ] Issue uncached `get_option()` calls in render paths
- [ ] Block on Action Scheduler queue dispatch
- [ ] Introduce N+1 query patterns (queries inside loops over orders, items, or tokens)

### Caching

- [ ] New caches reuse `WC_Stripe_Database_Cache` rather than rolling a new layer
- [ ] Database migrations and bulk operations don't lock large tables synchronously
- [ ] `update_option()` calls are not inside loops — accumulate and flush once

---

## Test Coverage

### Mandatory

- [ ] Bugfixes for fatals, checkout failures, and payment regressions include or update targeted automated tests (CRITICAL — code review alone is not enough)
- [ ] Behavior changes have corresponding PHPUnit coverage in `tests/phpunit/`
- [ ] Frontend behavior changes have Jest coverage under `tests/js/` or E2E coverage under `tests/e2e/`
- [ ] Payment method availability/rendering changes are validated across classic, Blocks, OCS, and ECE
- [ ] Recurring-payment-capable changes include subscription/pre-order regression coverage

### Conventions

- [ ] PHPUnit parameterized tests use `@dataProvider`
- [ ] PHPUnit test methods include a PHPDoc describing the contract being guarded
- [ ] E2E specs that mutate global store settings (currency, payment method availability) live in a dedicated Playwright project and CI matrix job, not in `default`
- [ ] Tests live nearest to the code they cover (`tests/phpunit/admin/`, `tests/phpunit/payment-methods/`, etc.)

---

## Compatibility

- [ ] Changes preserve WC L / L-1 / L-2 support (current major and previous two)
- [ ] Changes preserve WP L / L-1 support (transitively constrained by WC's support policy)
- [ ] PHP 7.4 syntax compatibility preserved (no PHP 8+ syntax in feature code)
- [ ] WordPress / WooCommerce core files in `docker/wordpress/` and `docker/wordpress_xdebug/` are NEVER edited

---

## Release Hygiene

- [ ] Version-impacting changes update `changelog.txt`, the `readme.txt` changelog section, and the plugin file header *together* (the `readme.txt` stable tag is bumped by woorelease at release time, not in feature PRs)
- [ ] `changelog.txt` uses `YYYY-MM-DD - version X.Y.Z`; `readme.txt` uses `= X.Y.Z - YYYY-MM-DD =` — different formats, do not convert
- [ ] Public-surface removals classified as `Update`, not `Dev`
- [ ] Major behavior changes (default flips, removed classes, version bumps) appear under `== Compatibility Notes ==` in `readme.txt`

---

## Process Rules

- [ ] PHPStan baseline updates are the *last* resort, not the first response. Run `npm run phpstan` and fix legitimate issues before baselining
- [ ] Feature work is not mixed with PHPStan baseline churn in a single commit (CRITICAL)
- [ ] Refactors stay scoped — no broad cleanups unless explicitly requested
- [ ] Commits don't include credentials, API keys, webhook secrets, or `.env` values

---

## Incident-Derived Rules

This section captures rules that emerged from real incidents. Add to it after a postmortem rather than from speculation.

- **Payment method instantiation registers hooks (CRITICAL — duplicate-charge incident):** never instantiate a `WC_Stripe_UPE_Payment_Method` subclass just to read metadata (title, label, capabilities). The constructor registers WP/WC hooks; instantiating a second time registers them twice. In 10.6.0 this caused subscription renewals to fire duplicate charges because `WC_Stripe_UPE_Payment_Method_OC` was instantiated to look up a display title. Use static methods on the payment method class, or read cached metadata, instead. Precedent: PR #5325 (10.6.0 → 10.6.1). **Corollary for new classes:** constructors should avoid side effects (hook registration, network calls, option writes) whenever possible — move them into an explicit `init()`/`register_hooks()` method called once from the bootstrap, so that classes remain safe to instantiate for metadata reads.
- **Don't repurpose `is_valid_optimized_checkout_page()` as a generic "is OCS in play" check:** `WC_Stripe_UPE_Payment_Gateway::is_valid_optimized_checkout_page()` only answers "is this request the OCS-rendered checkout page itself?" Using it elsewhere (order received, order details, etc.) caused those pages to display "Stripe" as the payment method instead of the actual one. If you need a broader "OCS enabled / OCS could be in play" check, use a separate predicate or the relevant settings option directly. Precedent: PR #5325.
- **Subscription renewals + Stripe Radar:** when Radar blocks a renewal, the WC Subscriptions retry queue must be cancelled rather than left pending — otherwise the next retry triggers the same Radar block (PR #5361).
- **Saved tokens + OCS:** Optimized Checkout Suite must not hide saved payment tokens from the saved-cards list. Verify token retrieval works with OCS enabled and disabled when touching token logic.
