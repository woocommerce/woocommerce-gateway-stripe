# Exploration — Bootstrap ECE page data instead of AJAX round-trips (STRIPE-1228)

> **Status: EXPLORATION / SPIKE — do not merge as a feature.**
> This document is an inventory + eligibility analysis with a recommended approach
> and a spike sketch. It contains **no production code changes**. Implementation is
> deferred to a follow-up PR with explicit sign-off.
>
> Scope boundary: this is about **our own** ECE AJAX round-trips. It is *not* the
> Stripe SDK request volume (STRIPE-956) nor the `/elements/sessions` re-fires from
> the un-torn-down `elements()` group (STRIPE-1224). Those are tracked separately
> and are out of scope here.

## TL;DR

The single request on the critical path to **first button render** that is a good
bootstrap candidate is the **cart-details fetch on the cart and checkout pages**:

- `client/entrypoints/express-checkout/index.js:604` calls
  `api.expressCheckoutGetCartDetails()` → `GET /wc/store/v1/cart`
  (`client/api/index.js:505`) and only calls `startExpressCheckout()` **inside the
  `.then()`** — so the button waits on a network round-trip before it can render.
- The **product page** already does the opposite (`index.js:580–601`): it renders
  synchronously from bootstrapped `getExpressCheckoutData('product')`, no AJAX.

The cart/checkout render payload (`total`, `currency`, `requestShipping`,
`requestPhone`, `displayItems`) is **already produced server-side at render time**
by existing helpers — so the round-trip is historical asymmetry, not a
data-availability constraint:

- `WC_Stripe_Express_Checkout_Helper::build_display_items()`
  (`includes/payment-methods/class-wc-stripe-express-checkout-helper.php:1686`)
  computes `displayItems` + `total` from `WC()->cart`.
- `WC_Stripe_Express_Checkout_Helper::get_checkout_data()` (`:398`) already inlines
  `currency_code`, `needs_shipping`, `needs_payer_phone`.

**Recommendation:** localise an initial cart payload (mirroring the product-page
`product` key) and render the button from it on first paint, keeping the AJAX
fetch only as the reconciliation path for client-side cart changes (qty/coupon
updates, `updated_cart_totals` / `updated_checkout`). Net effect: **one fewer
blocking request before first button render on cart + checkout**, with no change to
the live-update behaviour.

## Inventory of ECE-initiated requests

Surfaces: **P** = product page, **C** = cart, **CO** = classic checkout,
**B** = Blocks/OC checkout. All file:line refs verified against this branch.

### A. Before first button render (critical path)

| # | Call site | Endpoint | Fires on | Surfaces | Bootstrap-eligible? |
|---|---|---|---|---|---|
| A1 | `index.js:604` `expressCheckoutGetCartDetails()` | `GET /wc/store/v1/cart` (`api/index.js:505`); legacy `wc-ajax=wc_stripe_get_cart_details` (`api/index.js:520`) | page load, and re-init on `updated_cart_totals`/`updated_checkout` | C, CO, B | **Yes — initial payload.** Data exists server-side at render. Keep AJAX for the live-update re-init only. |
| A2 | `index.js:580–601` product render | none (reads `getExpressCheckoutData('product')`) | page load | P | **Already bootstrapped** — reference implementation for A1. |

> Note on A1's double duty: the same `init()` block runs both at first paint **and**
> on every `updated_cart_totals` / `updated_checkout`. Bootstrapping addresses only
> the *first-paint* fetch. The redundant re-init firing (and its missing teardown)
> is STRIPE-1224, not this issue — but the two interact: a clean bootstrap makes the
> first-paint path independent of AJAX, which is a prerequisite for tidying the
> re-init path later.

### B. On shopper interaction / submit (NOT bootstrap-eligible)

These depend on post-load input and must stay dynamic:

| # | Call site | Endpoint | Fires on | Why it stays |
|---|---|---|---|---|
| B1 | `index.js:708` `getSelectedProductData()` | `wc-ajax=wc_stripe_get_selected_product_data` | variation select / qty change (debounced) | depends on shopper selection |
| B2 | `index.js` add-to-cart | `POST /wc/store/v1/cart/add-item` (+ legacy) | ECE button click on product page | depends on click + selection |
| B3 | `index.js` empty-cart | `POST .../cart/remove-item` (+ legacy `wc_stripe_clear_cart`) | before add-to-cart | depends on click |
| B4 | `event-handler.js:35` `updateCustomer()` | `POST /wc/store/v1/cart/update-customer` | shipping address change in ECE sheet | shopper address |
| B5 | `event-handler.js:76` `selectShippingRate()` | `POST /wc/store/v1/cart/select-shipping-rate` | shipping rate change in ECE sheet | shopper selection |
| B6 | `payment-flow.js:75` `normalizeAddress()` | `wc-ajax=wc_stripe_normalize_address` | before order create | shopper address |
| B7 | `payment-flow.js:94` `ECECreateOrder()` | `POST /wc/store/v1/checkout` | ECE confirm | full order data |
| B8 | `payment-flow.js:87` `ECEPayForOrder()` | `POST /wc/store/v1/checkout/{id}` | ECE confirm on order-pay | shopper data |
| B9 | `api/index.js:323` `confirmIntent()` | `wc-ajax=wc_stripe_update_order_status` | 3DS/auth required | intent status |

### C. Already bootstrapped at render (`wp_localize_script`)

Built in `WC_Stripe_Express_Checkout_Element::javascript_params()`
(`includes/payment-methods/class-wc-stripe-express-checkout-element.php:210`,
localised at `:547`). Relevant keys for render:

- `checkout` → `get_checkout_data()` (`helper:398`): `currency_code`,
  `country_code`, `needs_shipping`, `needs_payer_phone`, `default_shipping_option`.
- `product` → `get_product_data()` (`helper:283`): `total`, `currency`,
  `requestShipping`, `displayItems`, `validVariationSelected` — **product page only**.
- `button`, `is_cart_page`, `is_checkout_page`, `is_pay_for_order`, `has_block`,
  `login_confirmation`, nonces.
- Pay-for-order page also localises `wcStripeExpressCheckoutPayForOrderParams`
  (`element.php:391`) with `displayItems`/`total`/`orderDetails` — i.e. that surface
  is **already** fully bootstrapped and makes no pre-render cart fetch.

So the gap is narrow and specific: **cart + checkout** lack the bootstrapped
equivalent of the `product` key.

## Recommended approach (for the follow-up implementation PR)

1. Add a `cart` key to `javascript_params()`, populated only when
   `is_cart() || is_checkout()` and `WC()->cart` is non-empty, from the existing
   `build_display_items()` + `get_checkout_data()` outputs. No new computation —
   reuse the helpers the AJAX handler already uses.
2. In `index.js`, when `getExpressCheckoutData('cart')` is present, call
   `startExpressCheckout()` synchronously from it (same shape as the product
   branch), and **skip** the initial `expressCheckoutGetCartDetails()`.
3. Keep `expressCheckoutGetCartDetails()` as the reconciliation path: still fetch on
   `updated_cart_totals` / `updated_checkout` so client-side cart mutations stay
   correct. (Whether to debounce/teardown that path is STRIPE-1224.)

A standalone illustrative sketch lives in
[`stripe-1228-spike-cart-bootstrap.md`](./stripe-1228-spike-cart-bootstrap.md).

## Risks & caveats to resolve before implementing

- **Page caching / staleness.** A bootstrapped total baked into a cached cart or
  checkout page could be stale for a returning shopper. The product page already
  carries this exposure, but cart/checkout are more dynamic. Mitigation: cart and
  checkout are normally excluded from full-page caching (`DONOTCACHEPAGE`), and the
  AJAX reconciliation on `updated_*` corrects any drift — but this must be verified
  per the supported caching stack before shipping.
- **Empty cart / zero total.** A1 currently hides the button when `total === 0`
  (`index.js:611–617`). The bootstrap must reproduce that gate server-side (omit the
  `cart` key, or signal hide) so behaviour is unchanged.
- **Logged-out vs logged-in, multi-currency, taxes-based-on-billing.** The localised
  payload must match what the AJAX endpoint would have returned for the same request
  context. `get_checkout_data()` already accounts for currency/tax display; confirm
  parity for `taxes_based_on_billing` stores.
- **Nonce/freshness.** Bootstrapping render data does not remove the need for fresh
  Store API nonces on the interaction requests (B-series); those are unaffected.

## How to measure (acceptance criteria)

Before/after, on product / cart / classic checkout / Blocks-OC, capture:

- Count of ECE-initiated requests before first button paint (DevTools Network,
  filter `wc/store` + `wc-ajax`). Expect **−1** on cart and checkout; product
  unchanged (already 0); pay-for-order unchanged (already 0).
- Time from `DOMContentLoaded` to first ECE button visible.
- Regression sweep: button renders and a test payment completes on **all four**
  surfaces (classic, Blocks, OC, ECE), plus live cart updates (qty/coupon) still
  refresh the button total.

## Out of scope (do not fold in)

- STRIPE-1224 — `elements()` re-init flooding / missing teardown on `updated_*`.
- STRIPE-956 — Stripe SDK request volume.
- Any change to the B-series interaction requests.
