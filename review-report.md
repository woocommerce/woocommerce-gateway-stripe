# Review Report

**Branch:** stripe-900-agentic-commerce-order-processing-create-webhook-handler-for
**Date:** 2026-03-05
**Base:** develop

## Overall Verdict

**FLAG FOR HUMAN**

The PR is well-structured and follows existing patterns. There are a few issues worth fixing before merge — a duplicate expand parameter, an outdated `@since` version in the order mapper file docblock, and a couple of test gaps — but nothing critical.

---

## Architecture Compliance Review

**Verdict:** FLAG

### Findings

- **[FLAG] Duplicate expand parameter in API URL** (`class-wc-stripe-webhook-handler.php:1644-1646`): `build_checkout_session_retrieve_url()` already includes `payment_intent.agent_details` in its base expand array (line 1750), but the call site also passes it as `$additional_expand`. The URL ends up with `expand[]=payment_intent.agent_details` twice. Stripe deduplicates, but this is a logic error — the call should pass an empty array `[]`.

- **[FLAG] `@since` version inconsistency** (`class-wc-stripe-agentic-commerce-order-mapper.php`): The file-level docblock (line 8) still says `@since 10.5.0` while the class-level and method-level docblocks say `@since 10.6.0`.

- **[PASS]** Webhook handler follows the same dispatch pattern as other handlers in `process_webhook()`.
- **[PASS]** Feature flag gating is correct — early return at the top of `process_checkout_session_completed()`.
- **[PASS]** `checkout.session.completed` registered in `WC_Stripe_Account::WEBHOOK_EVENTS`.
- **[PASS]** Order mapper stub is in the correct location (`includes/agentic-commerce/`).
- **[PASS]** Registration in `WC_Stripe::init()` is correct.
- **[PASS]** No layer violations. Flow is: Webhook Handler → API Client → Order Mapper.
- **[PASS]** Database cache locking uses `try/finally` for cleanup.

---

## Security Review

**Verdict:** FLAG

### Findings

- **[Medium] TOCTOU race condition in concurrency lock** (`class-wc-stripe-webhook-handler.php:1602-1612`): The lock uses `get()` then `set()` as separate operations backed by `wp_options`. Two concurrent webhooks arriving within milliseconds could both read `null`. The secondary idempotency check via `get_order_by_intent_id()` mitigates this significantly, but has the same TOCTOU window if neither thread has committed the order yet.

- **[Low] Empty session ID creates degenerate lock key** (line 1604): If `$session_id` is empty, the lock key becomes `agentic_lock_`. An early return on empty session ID would be defensive hardening.

- **[Low] API version override scope** (lines 1639-1731): The `wc_stripe_request_headers` filter override wraps the entire `handle_agentic_checkout_session`, including the `wc_stripe_agentic_order_created` action. Any Stripe API calls in hook listeners would use the overridden version. Consider narrowing the scope to wrap only the `WC_Stripe_API::retrieve()` call.

- **[PASS]** No sensitive data in logs (only session IDs, intent IDs, order IDs).
- **[PASS]** Webhook signature verification covers the new event type.
- **[PASS]** Output escaping correct (`esc_html()` in mapper exception).
- **[PASS]** No SQL injection, command injection, or XSS.
- **[PASS]** No hardcoded secrets.

---

## Performance Review

**Verdict:** PASS

### Findings

- **[PASS] Zero overhead for non-agentic paths**: Feature flag check is the first thing in `process_checkout_session_completed()`, backed by autoloaded options.
- **[PASS] One additional Stripe API call per agentic webhook**: Necessary to detect agentic sessions. Only runs when feature flag is on AND event type matches.
- **[PASS] Lock TTL is appropriate**: 5 minutes is reasonable for webhook processing.
- **[PASS] API version override properly scoped** in `try/finally`.
- **[Info] Duplicate expand parameter**: `payment_intent.agent_details` appears twice in URL. Functionally harmless, Stripe deduplicates.
- **[Info] Script loading optimization**: `should_skip_full_payment_scripts()` is a performance win for product/cart pages.

---

## Test Coverage Review

**Verdict:** FLAG

### Coverage Assessment
- PHP: 4 files with new logic, 3 have tests
- JS: 4 modified files, 1 has tests (iDEAL label update)

### Findings

- **[FLAG] No happy-path test for successful order creation**: All agentic webhook tests result in a skip, error, or duplicate. The `wc_stripe_agentic_order_created` action path (lines 1691-1711) is untested. This is understandable since the mapper is a stub, but worth documenting.

- **[FLAG] `WC_Stripe_Account::get_webhooks_api_version()` with agentic flag**: No test verifies that the webhook API version switches to `AGENTIC_COMMERCE_API_VERSION` when the feature flag is enabled.

- **[PASS]** 14 tests cover feature flag gating, concurrency lock, lock cleanup, non-agentic skip, empty network_business_profile, missing payment intent ID, API error handling, duplicate idempotency, mapper exception, API version override, `process_webhook` dispatch, and URL building.
- **[PASS]** Data providers used correctly for URL builder tests.
- **[PASS]** `set_up()` / `tear_down()` naming follows conventions.
- **[PASS]** Filter cleanup in `tear_down()`.

---

## Summary of Required Changes

1. **Fix duplicate expand parameter**: Change the call at line 1646 from `['payment_intent.agent_details']` to `[]` since `build_checkout_session_retrieve_url` already includes it.
2. **Fix `@since` in order mapper file docblock**: Line 8 of `class-wc-stripe-agentic-commerce-order-mapper.php` still says `10.5.0`, should be `10.6.0`.

## Suggestions (Non-blocking)

- Add an early return in `process_checkout_session_completed` if `$session_id` is empty.
- Narrow the API version override scope to wrap only the `WC_Stripe_API::retrieve()` call.
- Add a test for `get_webhooks_api_version()` with agentic flag enabled.
- Document that happy-path order creation test will be added with STRIPE-902 (mapper implementation).
