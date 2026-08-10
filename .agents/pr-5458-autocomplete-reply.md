Thanks for the report! (I think the screenshots didn't make it — could you re-attach? They'd help a lot here.)

A couple of things would let us pin this down quickly:

**1. During or after the autocomplete completes?** WooCommerce core debounces `update_checkout` (~1s) and aborts the in-flight `update_order_review` request when a new one fires, so even an autocomplete that writes several address fields normally collapses to a single settled update shortly after selection. If your integration instead drives its own update logic per field/keystroke and bypasses that debounce, that's the case we'd want to reproduce. Worth a look: WooCommerce core recently added a supported [address autocomplete provider API](https://developer.woocommerce.com/docs/features/address-autocomplete/#step-2-register-the-provider) — registering a provider gives you one controlled address-set + a single `update_checkout` after selection, which removes the burst at the source rather than hooking Google Maps ad hoc.

**2. Is Optimized Checkout + Adaptive Pricing enabled on this store?** This is the key fork for us:
- **Without Adaptive Pricing:** on `updated_checkout` the plugin only re-mounts the Payment Element (and it's guarded against double-mounting), and the PaymentIntent is created at submit, not on address changes. Address churn shouldn't translate into payment failures here.
- **With Adaptive Pricing:** each `updated_checkout` triggers a server-side Stripe Checkout Session update, so a burst of updates means a burst of overlapping session updates — that's the one path where rapid autocomplete churn could plausibly race the order submission.

**3. What does the 30–50% actually measure?** This is what I'm having trouble squaring — the shopper still has to choose a shipping method, enter card details, and click Place Order, which gives the debounce time to settle, so a 30–50% *payment* failure rate from autocomplete would be surprising. Could you clarify whether that figure is Stripe payment-intent declines, checkout abandonment (analytics), or client-side "couldn't complete" errors? The cleanest objective signal would be the **payment-intent error rate in the Stripe Dashboard** plus one failed PaymentIntent ID — if Stripe shows a normal decline rate, the issue is likely in the custom checkout/analytics rather than the payment step itself.

Also: is this the classic (shortcode) checkout or Blocks? The `update_checkout` wording suggests classic, just want to confirm.
