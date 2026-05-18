---
name: payment-method-add
description: Use when adding a new payment method to the Stripe gateway. Triggers include "add payment method", "new UPE method", "support <method-name> on checkout", "register a payment method", or any work that introduces a new `WC_Stripe_UPE_Payment_Method` subclass. A new method spans PHP and frontend plus constants and registration; this skill walks the full sequence so nothing is silently missing on checkout.
---

# Add a Stripe Payment Method

Adding a payment method touches at least five places across PHP and frontend. The most common pitfall in this codebase is missing one of them — the method either doesn't appear on checkout at all, or appears on one surface but not another.

## Required changes (in order)

### 1. PHP class

Create `includes/payment-methods/class-wc-stripe-upe-payment-method-<id>.php` extending `WC_Stripe_UPE_Payment_Method`.

Required overrides at minimum:

- `get_id()` — return the Stripe payment method type (e.g., `klarna`, `affirm`, `sepa_debit`).
- `get_label()` — translated method name shown to merchants in admin.
- `get_icon()` — return the icon URL registered in step 4.
- Capability flags — `is_subscription_supported()`, `is_pre_order_supported()`, currency/country constraints.

If the method supports recurring payments, also include `WC_Stripe_Subscriptions_Trait` and ensure subscription/pre-order initialization runs in the gateway constructor. Reference an existing recurring-capable method (Klarna, Card, SEPA) for the trait wiring pattern.

### 2. Constants

Add the method ID to `includes/constants/class-wc-stripe-payment-methods.php` using the existing `STRIPE_<METHOD>` constant convention. The constant is referenced from webhook handling, payment processing, and admin REST endpoints — leaving it out is a silent breakage.

### 3. Registration

Add the new class to the payment method classes array initialized in `WC_Stripe::init()`. The order in this array influences display order on checkout — match the design's intended position rather than appending blindly.

### 4. Icon

Add the icon asset under `client/payment-method-icons/` (SVG preferred). Register it in `client/payment-method-icons/index.js` so `get_icon()` resolves correctly.

### 5. Blocks support

Add the method config under `client/blocks/upe/`. Blocks integration is a separate registration from classic checkout; without this entry, the method is invisible in Blocks checkout even when classic works.

## Validation matrix (CRITICAL)

Per the project's CRITICAL rule, any change to payment-method availability or rendering must be validated across all four checkout surfaces. A new method must be verified on:

| Surface | Where to test |
|---------|---------------|
| Classic checkout | `/checkout` with Blocks disabled |
| Blocks checkout | `/checkout` with Blocks enabled (default for new sites) |
| Optimized Checkout (OCS) | `/checkout` with OCS enabled in plugin settings |
| Express checkout (ECE) | Verify the Apple Pay / Google Pay / Link / Amazon Pay buttons on each ECE surface: product page, cart, checkout, and pay-for-order |

If the method intentionally does not support a surface, verifying that it's *cleanly hidden* — not erroring — is still a tested outcome.

## Tests

- **PHPUnit:** add `tests/phpunit/payment-methods/class-wc-stripe-upe-payment-method-<id>-test.php`. Use `@dataProvider` for capability matrix scenarios (subscriptions on/off, pre-orders on/off, currency, country).
- **Jest:** if the Blocks config or icon registration has logic beyond static configuration, add a Jest test in a `__tests__/` folder alongside the code (e.g., `client/blocks/upe/__tests__/`).
- **E2E:** at minimum one shopper happy-path spec under `tests/e2e/specs/<method>/`. Several methods have dedicated Playwright projects — match the existing convention rather than dropping into `default`.

## Recurring-payment regressions

If the method supports subscriptions or pre-orders, add coverage that exercises:

- The subscription renewal path (`WC_Stripe_Subscriptions_Trait::renew_subscription` flow)
- The pre-order release path (`WC_Stripe_Pre_Orders_Trait`)
- Webhook handling for the relevant `payment_intent.*` and `setup_intent.*` events

Recurring-payment regressions are explicitly called out as a "common pitfall" in `includes/AGENTS.md`.

## Common mistakes

- Adding the class but forgetting `WC_Stripe::init()` registration → method silently absent on checkout.
- Forgetting the constant in `WC_Stripe_Payment_Methods` → webhook handler can't classify charges; admin REST endpoints break.
- Forgetting Blocks support → method works on classic but disappears in Blocks.
- Forgetting icon registration → broken-image placeholder on checkout.
- Validating only one checkout surface → regression on the other three.
- Missing subscription/pre-order trait wiring on a recurring-capable method → renewals fall back to manual.
