# Spike sketch — cart/checkout bootstrap (STRIPE-1228)

> **ILLUSTRATIVE ONLY.** Not wired into the build, not tested, not for merge.
> Shows the shape of the change so the approach in the exploration doc is concrete
> and reviewable. Line numbers are anchors against this branch, not a patch.

## 1. PHP — localise an initial `cart` payload (parity with `product`)

In `WC_Stripe_Express_Checkout_Element::javascript_params()`
(`includes/payment-methods/class-wc-stripe-express-checkout-element.php`, alongside
the existing `'product' => $this->express_checkout_helper->get_product_data()`):

```php
// Render-time cart snapshot so the cart/checkout button can paint without the
// initial GET /wc/store/v1/cart round-trip. Reuses the same helpers the AJAX
// handler uses, so the payload matches what the fetch would have returned.
'cart' => $this->express_checkout_helper->get_cart_render_data(),
```

New helper on `WC_Stripe_Express_Checkout_Helper` — pure reuse, no new math:

```php
/**
 * Render-time snapshot used to bootstrap the cart/checkout ECE button so the
 * first paint does not depend on GET /wc/store/v1/cart. Returns null when the
 * button should not render (no cart, empty cart, or zero total), so the client
 * falls back to its existing hide/skip behaviour.
 *
 * @return array|null
 */
public function get_cart_render_data() {
    if ( ! $this->is_cart() && ! $this->is_checkout() ) {
        return null;
    }
    if ( is_null( WC()->cart ) || WC()->cart->is_empty() ) {
        return null;
    }

    $checkout = $this->get_checkout_data();
    $items    = $this->build_display_items(); // displayItems + total (cents)

    // Mirror index.js:611 — hide on zero total unless a free trial is present.
    if ( 0 === (int) $items['total']['amount'] && ! $this->has_free_trial_in_cart() ) {
        return null;
    }

    return [
        'total'           => $items['total']['amount'],
        'currency'        => $checkout['currency_code'],
        'requestShipping' => 'yes' === $checkout['needs_shipping'],
        'requestPhone'    => $checkout['needs_payer_phone'],
        'displayItems'    => $items['displayItems'],
    ];
}
```

## 2. JS — render from the snapshot, skip the initial fetch

In `client/entrypoints/express-checkout/index.js`, the cart/checkout `else` branch
(currently `index.js:602–630`):

```js
} else {
    // Cart and Checkout page initialization.
    const bootstrapped = getExpressCheckoutData( 'cart' );

    if ( bootstrapped ) {
        // First paint: render synchronously from the localised snapshot —
        // no GET /wc/store/v1/cart on the critical path.
        wcStripeECE.startExpressCheckout( {
            total: bootstrapped.total,
            currency: bootstrapped.currency,
            requestShipping: bootstrapped.requestShipping,
            requestPhone: bootstrapped.requestPhone,
            displayItems: transformLabeledDisplayItems(
                bootstrapped.displayItems ?? []
            ),
        } );
    } else {
        // Fallback (and the reconciliation path on updated_cart_totals /
        // updated_checkout) keeps the existing AJAX behaviour unchanged.
        api.expressCheckoutGetCartDetails().then( ( cart ) => {
            /* ...unchanged existing body... */
        } );
    }
}
```

## Open questions for the implementation PR

- Does `init()` re-run on `updated_cart_totals` read the *stale* localised `cart`
  on the second pass? It must NOT — the snapshot is first-paint only. Either clear
  it after first use, or gate the synchronous branch on a "first init" flag so
  subsequent re-inits always take the AJAX reconciliation path.
- `transformLabeledDisplayItems` vs legacy `displayItems` shape: confirm the
  bootstrapped `displayItems` matches the non-legacy transformer expectation
  (see `index.js:597–599` for the product-page precedent).
- Confirm `has_free_trial_in_cart()` exists / find the real predicate behind
  `getExpressCheckoutData('has_free_trial')` and reuse it server-side.
- Test parity: add a PHPUnit case for `get_cart_render_data()` (zero-total →
  null, shipping flag, currency) and a Jest case for the synchronous render branch.
