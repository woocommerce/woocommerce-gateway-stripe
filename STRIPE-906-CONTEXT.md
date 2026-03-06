# STRIPE-906: Tax Calculation via Checkout Customization Hook

## Status

Implementation complete, all tests/lint/phpstan pass. Not yet manually tested (feature not gated for account).

## What Was Built

Stripe sends a `v1.delegated_checkout.customize_checkout` webhook event when it needs tax calculations during agentic checkout. We handle it in the existing `WC_Stripe_Webhook_Handler`, calculate taxes using WooCommerce's tax engine, and return a synchronous JSON response with tax rate percentages.

### New Files

| File | Purpose |
|------|---------|
| `includes/agentic-commerce/class-wc-stripe-agentic-customize-checkout-event.php` | Typed wrapper for the customize_checkout event (getters for ID, currency, automatic_tax, line items, addresses, tax address with shipping→billing fallback) |
| `includes/agentic-commerce/class-wc-stripe-agentic-customize-checkout-line-item.php` | Typed wrapper for line items (sku_id, unit_amount, quantity, name, amounts, tax_rates) |
| `includes/agentic-commerce/class-wc-stripe-agentic-commerce-tax-calculator.php` | Tax engine: resolves products by SKU, calls `WC_Tax::find_rates()`, returns Stripe `rate_data` format (percentage, display_name, inclusive) |
| `tests/phpunit/AgenticCommerce/WC_Stripe_Agentic_Customize_Checkout_Event_Test.php` | 17 tests for event wrapper |
| `tests/phpunit/AgenticCommerce/WC_Stripe_Agentic_Commerce_Tax_Calculator_Test.php` | 11 tests for tax calculator |

### Modified Files

| File | Change |
|------|--------|
| `includes/class-wc-stripe-webhook-handler.php` | Added `v1.delegated_checkout.customize_checkout` case in `process_webhook()` switch + `process_customize_checkout()` method that returns JSON via `wp_send_json()` |
| `includes/class-wc-stripe.php` | Added `require_once` for the 3 new classes (~line 235) |

## Request/Response Contract

**Stripe sends** (POST to webhook URL):
```json
{
  "type": "v1.delegated_checkout.customize_checkout",
  "id": "evt_xxx",
  "data": {
    "automatic_tax": { "enabled": false },
    "currency": "usd",
    "line_item_details": [
      { "id": "li_xxx", "sku_id": "SKU-123", "unit_amount": 2500, "quantity": 1, "name": "Product", "tax_rates": [] }
    ],
    "shipping_details": { "address": { "country": "US", "state": "CA", "postal_code": "90210", "city": "Beverly Hills" } },
    "billing_details": { "address": { ... } }
  }
}
```

**We return**:
```json
{
  "line_items": [
    {
      "id": "li_xxx",
      "tax_rates": [
        { "rate_data": { "display_name": "CA State Tax", "inclusive": false, "percentage": 7.25 } }
      ]
    }
  ]
}
```

## Key Design Decisions

- **Routed through webhook handler** (not a separate REST endpoint) — reuses existing auth/signature verification.
- **Synchronous response** via `wp_send_json()` — Stripe expects JSON back within 4 seconds.
- **Product lookup by SKU** (`wc_get_product_id_by_sku()`) — the customize_checkout event uses `sku_id`, not `external_reference`.
- **Returns rate percentages** (not calculated amounts) — Stripe applies the rates itself.
- **`WC_Tax::find_rates()`** (not `get_rates()`) — we pass explicit location data, not customer session.
- **Skips unknown products** gracefully (logs warning, omits from response).
- **`automatic_tax.enabled = true`** → returns empty response (Stripe Tax handles it).
- **Inclusive flag** from `wc_prices_include_tax()`.
- **Filterable product lookup** via `wc_stripe_agentic_tax_product_by_sku`.

## What's NOT Included (Out of Scope)

- Shipping options in the customize_checkout response (separate issue).
- `v1.delegated_checkout.finalize_checkout` (pre-confirmation approval hook).
- Caching of tax calculations.
- Any authentication changes.

## Manual Testing Instructions

1. `wp option update _wcstripe_feature_agentic_commerce yes`
2. Configure the customize_checkout webhook endpoint in Stripe Dashboard → Settings → Agentic Commerce.
3. Ensure WooCommerce tax rates are configured (WooCommerce → Settings → Tax).
4. Ensure products have SKUs matching what's in the Stripe catalog.
5. Trigger an agentic checkout flow — Stripe should call the webhook with `v1.delegated_checkout.customize_checkout`.
6. Check WooCommerce logs for `Agentic customize_checkout` entries.
7. Verify the AI agent shows correct tax amounts during checkout.

## Stripe Docs Reference

- https://docs.stripe.com/agentic-commerce/enable-in-context-selling-on-ai-agents?order-monitoring=webhooks
- Toggle "Custom tax rates" in Agentic Commerce Settings to enable the hook.
- 4-second timeout on the hook response.

## Linear Issue

- STRIPE-906: https://linear.app/a8c/issue/STRIPE-906
- Parent branch: `stripe-902-agentic-commerce-order-processing-map-checkoutsession-to` (PR #5032)
