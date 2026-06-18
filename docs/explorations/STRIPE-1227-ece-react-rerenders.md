# Exploration — Audit unnecessary React re-renders in Express Checkout (STRIPE-1227)

> **Status: EXPLORATION / AUDIT — no production code changes.**
> This is a re-render audit of the ECE Blocks React tree with a profiling plan and
> recommended targeted fixes. Implementation is deferred to a follow-up PR with
> explicit sign-off. All findings below were read at `file:line` on this branch.
>
> Scope boundary: this is the **React component render path** only. It is *not* the
> Stripe `elements()` re-init driven by classic-page jQuery events (STRIPE-1224) nor
> the Stripe SDK request volume (STRIPE-956).

## How the ECE Blocks tree renders

The express method is registered once (`client/blocks/express-checkout/index.js:74`)
with a static `content` element:

```
content = <ExpressCheckoutContainer api stripe expressPaymentMethod />   // index.js:78
```

`billing`, `shippingData`, `onClick`, `onClose`, `setExpressPaymentError` are **not**
in that element — WooCommerce Blocks injects them via `cloneElement` when its own
express-payment wrapper renders. That wrapper subscribes to the cart/checkout data
store, so **every cart/totals/shipping tick re-renders it and passes fresh
`billing`/`shippingData` object references** down into our tree.

Render chain on each cart tick:

```
WC Blocks express wrapper (subscribes to cart store)
  └─ ExpressCheckoutContainer            (not memoized)        container.js:12
       new `options` object every render → <Elements options>  container.js:15,36
       └─ ExpressCheckoutComponent       (not memoized)        component.js:64
            new inline `options` object  → <ExpressCheckoutElement>  component.js:107
            3 new inline handlers          (onReady/onShipping*)     component.js:84,87,90
            └─ useExpressCheckout(...)                          hooks.js:21
                 buttonOptions: fresh object every render       hooks.js:33
                 onConfirm / onCancel: fresh fn every render    hooks.js:45,161
                 onButtonClick: useCallback, but deps churn      hooks.js:67,150
```

The static-`content` registration means the tree itself is not re-created, but
nothing below `ExpressCheckoutContainer` is insulated from the parent's re-render.

## The necessary-vs-unnecessary distinction (read before "fixing")

Not every re-render here is waste. When the **cart total changes, the new `amount`
genuinely must reach Stripe** (`<Elements options.amount>` / the button total).
STRIPE-1228 and STRIPE-1224 both depend on that amount staying live. So the goal is
**not** "stop re-rendering" — it is "only do work when a value Stripe actually
consumes changes (amount, currency, minorUnit, needsShipping, the payment-method
set), and stop the churn that fires regardless."

A blanket `React.memo` on the container is therefore **insufficient on its own**:
Blocks passes a new `billing` object every tick, so the default shallow prop compare
never matches. Memoization has to key on the **primitive values** we extract from
`billing`/`shippingData`, not on the objects themselves.

## Findings (ranked by impact)

Impact = how often it fires. **High** = every cart/store tick; **Med** = every
parent render; **Low** = mount-ish.

| # | Culprit | file:line | Impact | Verified note |
|---|---------|-----------|--------|---------------|
| 1 | `ExpressCheckoutContainer` not memoized; rebuilds `options` (incl. `amount`) every render and passes to `<Elements>` | container.js:12,15,36 | High | Re-renders on every injected-`billing` ref change. `amount` change is legit; the rest of `options` is rebuilt needlessly. |
| 2 | `<ExpressCheckoutElement options>` is an inline object spread rebuilt every render | component.js:107 | High | New ref every render → react-stripe-js runs `element.update()` each time even when values are identical. |
| 3 | `getExpressCheckoutButtonStyleSettings()` → `buttonOptions` rebuilt every render (not memoized) | hooks.js:33; utils/index.js:126 | High | Feeds #2. Fresh object each render. |
| 4 | `getPaymentMethodTypesForExpressMethod()` returns a **new array** every call; used in `options.paymentMethodTypes` | container.js:28; utils/index.js:336 | High | Pure function of `expressPaymentMethod` + enabled flags — safely cacheable/memoizable. |
| 5 | `getExpressCheckoutButtonAppearance()` returns a **new object** every call; used in `options.appearance` | container.js:30; utils/index.js:109 | High | Depends only on a localized button-radius setting — stable per page load. |
| 6 | `onConfirm`, `onCancel` recreated every render | hooks.js:45,161 | High | **Correction to first-pass audit:** these are NOT memoized. Passed to `<ExpressCheckoutElement>`. |
| 7 | `onShippingAddressChange`, `onShippingRateChange`, `onElementsReady` inline arrows every render | component.js:84,87,90 | High | Not wrapped in `useCallback`; only depend on `elements` (stable from `useElements()`) + `expressPaymentMethod`. |
| 8 | `onButtonClick` `useCallback` deps include unstable refs `billing.cartTotalItems`, `shippingData.shippingRates` | hooks.js:152,157 | High | Memoized but invalidated every tick because the deps are fresh array refs from the store. |
| 9 | `ExpressCheckoutComponent` not memoized | component.js:64 | Med | Same caveat as #1: needs value-based memo, not shallow. |

### Already correct / not a problem (avoid false positives)

- `transformAmountForStripe` / `parseAndTransformAmount` are properly `useCallback`-memoized on `billing.currency.minorUnit` (hooks.js:35,40). 
- `adjustButtonHeights()` (component.js:38) mutates its argument, but `buttonOptions`
  is a **fresh** object each render (#3), so there is no runaway-accumulation bug —
  only the instability of #3. (If #3 is later memoized, `adjustButtonHeights` must
  stop mutating, or it will corrupt the memoized value across renders. Flag for the
  implementation PR.)
- `canMakePayment` / `supports` (index.js:86,106) run in Blocks' availability phase,
  not on the hot render path.

## Recommended targeted fixes (for the follow-up PR)

Mapped to the findings; each must preserve live `amount` updates.

1. **Stabilise the pure helpers (#4, #5).** Memoize `getPaymentMethodTypesForExpressMethod` and `getExpressCheckoutButtonAppearance` by input (module-level cache or `useMemo` at the call site). They don't depend on cart state.
2. **`useMemo` the two `options` objects (#1, #2, #3)** keyed on the primitives Stripe consumes — `billing.cartTotal.value`, `billing.currency.code`, `billing.currency.minorUnit`, `expressPaymentMethod`, `shippingData.needsShipping`, button settings — not on the `billing`/`shippingData` objects.
3. **`useCallback` the handlers (#6, #7)** with `elements` + `expressPaymentMethod` deps.
4. **Narrow `onButtonClick` deps (#8):** depend on a derived primitive (e.g. a stable hash/length of line items and a serialised shipping-rate id list) instead of the raw array refs, or read those values lazily inside the callback.
5. **`React.memo` the container/component (#1, #9) with a value-based comparator** (or split a presentational child that receives only primitives), since shallow compare can't see through Blocks' fresh `billing` ref.
6. If #3 is memoized, make `adjustButtonHeights` return a copy instead of mutating.

## How to measure (acceptance criteria)

Per surface — product, cart, classic checkout, Blocks checkout, OC, ECE button:

1. React DevTools **Profiler** → record, then trigger a cart change (qty/coupon/shipping). Count commits/renders of `ExpressCheckoutContainer` + `ExpressCheckoutComponent` and note "why did this render".
2. Optionally add a temporary render counter / `why-did-you-render` in dev build for hard numbers.
3. Target: each ECE component renders **only** when a Stripe-consumed value changed; baseline vs. after, with the render-count delta recorded.
4. Regression: button still renders and a test payment completes on classic, Blocks, OC, ECE; live cart updates still update the button total (amount stays live).

## Out of scope (do not fold in)

- STRIPE-1224 — `elements()` re-init flooding via classic-page jQuery events.
- STRIPE-956 — Stripe SDK request volume.
- The vanilla-JS classic entrypoint (`client/entrypoints/express-checkout/index.js`) — not a React render path.
