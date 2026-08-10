Confirmed the mechanism and shipped a mitigation for the customer-visible damage.

**Root cause (validated in code).** Two paths call `payment_complete()` on the same order — the Store API (block) checkout handler and the Stripe webhook. On legacy/CPT storage (HPOS off, as here), WooCommerce rewrites `post_status` from the in-memory order object on essentially every full `save()` (because `date_modified` always changes). So if the webhook sets `processing` + `_date_paid`, and the checkout path's stale order object (still `pending` in memory) then does its post-checkout `save()`, that save reverts `post_status` to `pending` while the meta written by the webhook survives. That matches the observed state exactly (`_date_paid` set, `_stripe_charge_captured: yes`, status `pending`, `_transaction_id` empty) — and it's the same race that causes the missing `_transaction_id` in STRIPE-1099/1145.

The plugin's `lock_order_payment()` doesn't prevent this: it's a non-atomic check-then-set on separately-loaded order objects (TOCTOU), and the clobbering save happens after the lock is released anyway.

**Is it OCS-specific?** No — it's general to Store API / block checkout, between the checkout processing path and the webhook. OCS can shift timing but isn't the cause. (This merchant is on plain block checkout, gateway 10.5.3, HPOS off.)

**Fix shipped:** woocommerce/woocommerce-gateway-stripe#5486 (issue #5485). The plugin already hooks `woocommerce_cancel_unpaid_order`, but only bailed for orders *awaiting action*. The PR also bails when `$order->get_date_paid()` is set, so a paid Stripe order is never auto-cancelled as unpaid — regardless of the race. This is the merchant's suggested fix #2, implemented in-plugin (no custom code needed on their side).

**Still open (deeper fix):** the concurrent-save race itself (suggested fix #1 — idempotency/atomic locking around `payment_complete` + the post-checkout save). `payment_complete()` is WC core and the plugin's lock is non-atomic, so a real fix needs atomic locking or keeping the webhook from processing while checkout owns the order. That's broader and overlaps with #2536 / STRIPE-1113; worth tracking separately rather than blocking this mitigation.

Note: current `develop` already defers `payment_intent.succeeded` to Action Scheduler by default, which narrows (but doesn't close) the concurrency window vs. 10.5.3.
