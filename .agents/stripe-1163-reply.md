The attached logs let me confirm the root cause — and it's **not** the Checkout Sessions / Adaptive Pricing path that was initially suspected.

**What the logs show**
- The plugin debug logs contain **zero `checkout/sessions` calls**. The failing Klarna payments run through the regular `process_upe_redirect_payment → process_order_for_confirmed_intent` flow, and the PaymentIntents carry full order-based metadata (`order_id`, `order_key`, `signature`, `is_oc_enabled: yes`). So these are direct order PaymentIntents from the **confirmation token (Optimized Checkout)** flow, not Checkout Sessions — which means **Adaptive Pricing is not required** for this bug (answering the earlier question about AP not being enabled).
- The SSR confirms the failing site was on Stripe gateway **10.6.1**, store language `fi`, OCS on.
- PI evidence: failed 10.6.x payments (orders 10927, 10932) → `payment_method_options.klarna.preferred_locale: null`; the successful 10.5.3 payment (order 10940) → `fi-FI`. The `null` vs `fi-FI` split is just which flow created the PI (confirmation token → null; payment method/legacy → fi-FI), not a Stripe browser-locale fallback.

**Root cause**
Klarna's `preferred_locale` is only set in the payment method flow (`prepare_payment_information_for_payment_method()` → `get_payment_method_options()`). The confirmation token flow used by Optimized Checkout (`prepare_payment_information_for_confirmation_token()`) never set it, so Stripe fell back to the account country (it-IT) and Finnish customers were routed through Italian identity verification they can't complete. Regression since 10.6.0 (when OCS started using the confirmation token flow).

**Fix:** woocommerce/woocommerce-gateway-stripe#5484 (issue #5483) sets `payment_method_options[klarna][preferred_locale]` in the confirmation token flow too, derived from store locale + order billing country. This should resolve it for any merchant where account country ≠ billing country with OCS enabled.

To confirm once they can test: on 10.7.0 + the fix, a Finnish customer paying with Klarna should produce a PaymentIntent with `preferred_locale: fi-FI` and pass identity verification.

**Unrelated note:** the 05-11 debug log also shows repeated "Unable to update Payment Method Configuration" errors caused by a **test-mode PMC id being used with a live key (and vice versa)**. That's a separate test/live key-mismatch config issue on their store, not part of the Klarna bug, but worth tidying up.
