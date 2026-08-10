@alanryan @kingsley.unuigbe — thanks, the customer's answers narrowed this down well. Confirming root cause and a fix.

**Root cause.** As suspected, the affected orders are missing the stored Stripe charge ID (`_transaction_id`). For **card** payments this is unrecoverable today, because the charge ID is written only during the synchronous checkout request — there's no webhook that back-fills it afterwards (`charge.succeeded` is skipped for cards, and `payment_intent.succeeded` only acts on `pending`/`failed` orders, so it no-ops on an already-paid order). The payment intent, by contrast, is saved earlier in checkout, which is exactly why these orders still show the intent in their notes but have no charge ID. That asymmetry points to the charge-ID write being lost/overwritten during checkout — the same Store API + HPOS concurrent-save class of issue as STRIPE-1076 / STRIPE-1113. The multisite "Could not find order" warnings are expected noise (each site receives every event on a shared account) and not the cause; iDEAL/Klarna (10.6.0) and Split Orders are ruled out per the customer's answers.

**Two consequences, both fixed:**
- wp-admin refunds fail because `process_refund()` returns early when the charge ID is missing.
- Dashboard refunds don't sync because the order can't be matched by refund ID or charge ID.

**Fix:** woocommerce/woocommerce-gateway-stripe#5479 (tracking issue #5478). (1) `process_refund()` now recovers the charge ID from the order's stored payment intent and persists it back, so the order becomes refundable and self-heals. (2) The Dashboard refund webhook now falls back to matching the order via its payment-intent ID and back-fills the charge. This restores refunds on already-affected orders without needing a re-charge.

**One confirmation that would help:** a full meta dump for an affected order (e.g. UK 2835379) showing `_stripe_intent_id` present while `_transaction_id` is empty, and the order in `processing`/`completed`. That confirms the recovery path will work for their existing orders. Also useful: whether these orders cluster around high-traffic windows (renewal batches / drops), which fits the race hypothesis.

Separately, we should treat the underlying lost-write during checkout as the longer-term fix (tracked via STRIPE-1076/1113); the above makes refunds work and recovers the existing orders in the meantime.
