# STRIPE-139: Mandate Updated Webhook Handler

Handle `mandate.updated` Stripe webhooks to update order/subscription status when a customer cancels, pauses, or reactivates a mandate for recurring payments.

## Context

The plugin already stores mandate IDs (`_stripe_mandate_id` order meta) and creates mandates for Indian recurring payments and SEPA debits. However, there is no handler for `mandate.updated` webhook events. When a customer cancels or pauses a mandate (e.g., via SMS notification for Indian recurring payments), the merchant has no visibility into what happened.

**Linear issue:** [STRIPE-139](https://linear.app/a8c/issue/STRIPE-139)
**Stripe docs:** [India Recurring Payments](https://stripe.com/docs/india-recurring-payments)

## Design Decisions

1. **Update both order AND subscription status** — cancel/pause/reactivate propagate to the WooCommerce Subscription object.
2. **Most recent active order only** — when multiple orders share a mandate ID, only update the most recent order that is not in a terminal status (`completed`, `refunded`). Orders already `cancelled` or `failed` are also skipped.
3. **Auto-reactivate with safety guard** — when a mandate becomes `active` again, only reactivate the subscription if its current status is `on-hold` (from a prior mandate pause). Do NOT reactivate subscriptions that were cancelled for other reasons (admin action, payment failure, etc.).
4. **Notes for all states, actions for actionable states** — add order notes on every mandate state change, but only take status-change actions on cancel and pause/reactivate.
5. **Approach: dedicated handler in webhook handler** — follows the established pattern of all other webhook event handlers.

## Stripe Mandate Statuses

Stripe's Mandate object has three top-level `status` values: `pending`, `active`, and `inactive`. There is no `revoked` status at the top level. When a customer cancels a mandate, the status transitions to `inactive`. To distinguish cancellation from pause, inspect the `payment_method_details.{type}` sub-object for fields like `revocation_reason` or `url` (for BACS/ACSS). If no distinguishing field is available, treat all `inactive` transitions as paused (on-hold) — the safer default.

## Mandate State to Action Mapping

| Mandate Status | Revocation Detected? | Order Status | Subscription Status | Order Note |
| --- | --- | --- | --- | --- |
| `inactive` | Yes (revocation_reason present) | `cancelled` | `cancelled` | "Stripe mandate {id} was revoked by the customer (via webhook). Reason: {reason}" |
| `inactive` | No | `on-hold` | `on-hold` | "Stripe mandate {id} is now inactive (via webhook)" |
| `active` | N/A | No change | `active` (only if currently `on-hold`) | "Stripe mandate {id} is now active (via webhook)" |
| `pending` | N/A | No change | No change | "Stripe mandate {id} status updated to pending (via webhook)" |

All order note strings use `__()` and `sprintf()` for translation, consistent with existing webhook handler notes.

## Implementation

### 1. New helper: `WC_Stripe_Order_Helper::get_order_by_mandate_id()`

- Location: `includes/class-wc-stripe-order-helper.php` (where `META_STRIPE_MANDATE_ID` constant is already defined)
- Instance method, called via `WC_Stripe_Order_Helper::get_instance()->get_order_by_mandate_id( $mandate_id )` (consistent with `WC_Stripe_Order_Helper`'s instance method pattern)
- Queries orders by `_stripe_mandate_id` meta key using the existing `META_STRIPE_MANDATE_ID` constant
- Returns `null` if no matching order found

**Query specification:**
```php
wc_get_orders( [
    'meta_key'   => self::META_STRIPE_MANDATE_ID,
    'meta_value' => $mandate_id,
    'status'     => [ OrderStatus::PENDING, OrderStatus::PROCESSING, OrderStatus::ON_HOLD ],
    'orderby'    => 'date',
    'order'      => 'DESC',
    'limit'      => 1,
] );
```
This excludes terminal statuses (`OrderStatus::COMPLETED`, `OrderStatus::REFUNDED`, `OrderStatus::CANCELLED`, `OrderStatus::FAILED`) by querying only for actionable statuses. Uses `OrderStatus` enum constants per codebase convention. Works with both HPOS and legacy postmeta via the `wc_get_orders()` abstraction.

### 2. Webhook dispatcher case

- Location: `includes/class-wc-stripe-webhook-handler.php`, `process_webhook()` method
- Add `case 'mandate.updated':` with explicit `break;` calling `$this->process_webhook_mandate_updated( $notification )`

### 3. Handler method: `process_webhook_mandate_updated()`

- Location: `includes/class-wc-stripe-webhook-handler.php`

**Flow:**
1. Extract mandate object from `$notification->data->object`
2. Get mandate ID and status; return early if either is missing (malformed payload)
3. Call `WC_Stripe_Order_Helper::get_instance()->get_order_by_mandate_id()` to find the most recent active order
4. If no order found, log via `WC_Stripe_Logger` and return early
5. Set `$this->resolved_order = $order` (required for `wc_stripe_webhook_received` action)
6. Determine if this is a revocation by checking for `revocation_reason` in `payment_method_details.{type}` (where `{type}` is the payment method type, e.g., `au_becs_debit`, `acss_debit`, `card` — access dynamically via `$mandate->payment_method_details->{$mandate->payment_method_type}->revocation_reason` or iterate sub-objects). Log the payment method type for debugging.
7. Compute the specific target status for this event: `cancelled` (revoked inactive), `on-hold` (paused inactive), or no change (active/other). Skip if order is already in this specific target status (idempotency guard). Note: an order `on-hold` from a prior pause must NOT be skipped when a revocation arrives — the target is `cancelled`, which differs from the current `on-hold`.
8. Add order note (for every status change, using `__()` and `sprintf()`)
9. For actionable states:
   - `inactive` with revocation: set order to `cancelled`
   - `inactive` without revocation: set order to `on-hold`
   - `active`: no order status change
10. If `WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled()` AND `function_exists( 'wcs_get_subscriptions_for_order' )`:
    - Find parent subscription via `wcs_get_subscriptions_for_order()`; if empty array returned, skip subscription steps silently (standalone order)
    - For `inactive` with revocation: set subscription to `cancelled`
    - For `inactive` without revocation: set subscription to `on-hold`
    - For `active`: set subscription to `active` **only if currently `on-hold`**
11. Log state transitions via `WC_Stripe_Logger`

### 4. Tests

- Location: `tests/phpunit/class-wc-stripe-webhook-handler-test.php`
- Uses `@dataProvider` pattern per project convention

**Test cases:**
1. Mandate revoked (inactive + revocation_reason) → order `cancelled`, subscription `cancelled`, order note added
2. Mandate paused (inactive, no revocation) → order `on-hold`, subscription `on-hold`, order note added
3. Mandate reactivated (active) with subscription on-hold → subscription `active`, order note added
4. Mandate reactivated (active) with subscription cancelled → subscription stays `cancelled` (safety guard)
5. Other mandate state (pending) → no status changes, order note added
6. No matching order → logs warning, no error
7. Order already in target status (duplicate webhook) → no duplicate status change or note
8. Order on-hold from prior pause, then revocation arrives → escalates to `cancelled` (idempotency guard does not block)
9. Subscriptions plugin not active → order updates work, subscription logic skipped
10. Multiple orders share mandate ID → only most recent non-terminal order updated
11. Standalone order (no subscription) with subscriptions plugin active → order updated, no subscription error
12. Malformed webhook payload (missing mandate ID or status) → returns early, no error

## Files Changed

| File | Change |
| --- | --- |
| `includes/class-wc-stripe-order-helper.php` | Add `get_order_by_mandate_id()` |
| `includes/class-wc-stripe-webhook-handler.php` | Add `mandate.updated` case + `process_webhook_mandate_updated()` |
| `tests/phpunit/class-wc-stripe-webhook-handler-test.php` | Add mandate webhook test cases with data provider |
