# STRIPE-139: Mandate Updated Webhook Handler — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Handle `mandate.updated` Stripe webhooks to update order/subscription status when a customer cancels, pauses, or reactivates a mandate for Indian recurring payments.

**Architecture:** Add a `process_webhook_mandate_updated()` handler to the existing `WC_Stripe_Webhook_Handler` class, following the established dispatcher pattern. Add a `get_order_by_mandate_id()` query method to `WC_Stripe_Order_Helper` (where the mandate meta constant already lives). All changes use TDD with `@dataProvider` parameterized tests.

**Tech Stack:** PHP 7.4+, WordPress/WooCommerce, PHPUnit 9.6

**Spec:** `docs/superpowers/specs/2026-03-26-mandate-updated-webhook-design.md`

---

### Task 1: Add `get_order_by_mandate_id()` to `WC_Stripe_Order_Helper`

**Files:**
- Modify: `includes/class-wc-stripe-order-helper.php` (after `update_stripe_mandate_id()` at line ~653)

- [ ] **Step 1: Write the method**

Add the following method after the existing `update_stripe_mandate_id()` method in `includes/class-wc-stripe-order-helper.php`:

```php
/**
 * Gets the most recent non-terminal order by Stripe mandate ID.
 *
 * @since 10.2.0
 *
 * @param string $mandate_id The Stripe mandate ID.
 * @return WC_Order|null The order, or null if not found.
 */
public function get_order_by_mandate_id( string $mandate_id ): ?WC_Order {
	$orders = wc_get_orders(
		[
			'meta_key'   => self::META_STRIPE_MANDATE_ID,
			'meta_value' => $mandate_id,
			'status'     => [ OrderStatus::PENDING, OrderStatus::PROCESSING, OrderStatus::ON_HOLD ],
			'orderby'    => 'date',
			'order'      => 'DESC',
			'limit'      => 1,
		]
	);

	return ! empty( $orders ) ? $orders[0] : null;
}
```

Note: This file needs the `OrderStatus` import. Check if it's already present at the top. If not, add `use Automattic\WooCommerce\Enums\OrderStatus;` on line 3 (between `<?php` and the `if ( ! defined( 'ABSPATH' ) )` guard), matching the pattern used in `class-wc-stripe-webhook-handler.php`.

- [ ] **Step 2: Run PHPStan to verify no type errors**

Run: `npm run phpstan`
Expected: No new errors related to `get_order_by_mandate_id`

- [ ] **Step 3: Commit**

```bash
git add includes/class-wc-stripe-order-helper.php
git commit -m "feat(STRIPE-139): add get_order_by_mandate_id() to WC_Stripe_Order_Helper"
```

---

### Task 2: Add `mandate.updated` case to webhook dispatcher

**Files:**
- Modify: `includes/class-wc-stripe-webhook-handler.php` (in `process_webhook()` method, around line 1681)

- [ ] **Step 1: Add the case to the switch statement**

In `includes/class-wc-stripe-webhook-handler.php`, inside the `process_webhook()` method's switch block, add a new case before the closing `}` of the switch (after the `checkout.session.completed` case at line ~1681):

```php
		case 'checkout.session.completed':
			$this->process_checkout_session( $notification );
			break;

		case 'mandate.updated':
			$this->process_webhook_mandate_updated( $notification );
			break;
```

Note: This also adds the missing `break;` after `process_checkout_session` — a pre-existing bug. This fix is **mandatory** because without it, `checkout.session.completed` events would fall through into the new `mandate.updated` handler.

- [ ] **Step 2: Add the stub handler method**

Add the following stub method to `WC_Stripe_Webhook_Handler`. Place it after the `process_webhook_dispute_closed()` method (around line ~500), since it's a similar order-status-changing webhook:

```php
/**
 * Process webhook for mandate status updates.
 *
 * Handles mandate cancellation (revocation), pause (inactive), and
 * reactivation (active) for Indian recurring payments and other
 * mandate-based payment methods.
 *
 * @since 10.2.0
 * @param object $notification The webhook notification from Stripe.
 */
public function process_webhook_mandate_updated( $notification ) {
	// Will be implemented in the next task.
}
```

- [ ] **Step 3: Commit**

```bash
git add includes/class-wc-stripe-webhook-handler.php
git commit -m "feat(STRIPE-139): add mandate.updated case to webhook dispatcher"
```

---

### Task 3: Write failing tests for mandate webhook handler

**Files:**
- Modify: `tests/phpunit/class-wc-stripe-webhook-handler-test.php` (add at end of class, before closing `}`)

- [ ] **Step 1: Write the data provider**

Add the following data provider to `tests/phpunit/class-wc-stripe-webhook-handler-test.php`, before the closing `}` of the class:

```php
/**
 * Provider for `test_process_webhook_mandate_updated`.
 *
 * @return array
 */
public function provide_test_process_webhook_mandate_updated() {
	return [
		'mandate revoked (inactive + revocation_reason) cancels order' => [
			'order_status'          => OrderStatus::PROCESSING,
			'mandate_status'        => 'inactive',
			'payment_method_type'   => 'card',
			'revocation_reason'     => 'customer_request',
			'expected_order_status' => OrderStatus::CANCELLED,
			'expected_note_pattern' => '/revoked by the customer.*Reason: customer_request/',
		],
		'mandate paused (inactive, no revocation) puts order on hold' => [
			'order_status'          => OrderStatus::PROCESSING,
			'mandate_status'        => 'inactive',
			'payment_method_type'   => 'card',
			'revocation_reason'     => null,
			'expected_order_status' => OrderStatus::ON_HOLD,
			'expected_note_pattern' => '/is now inactive/',
		],
		'mandate active adds note but does not change order status'   => [
			'order_status'          => OrderStatus::PROCESSING,
			'mandate_status'        => 'active',
			'payment_method_type'   => 'card',
			'revocation_reason'     => null,
			'expected_order_status' => OrderStatus::PROCESSING,
			'expected_note_pattern' => '/is now active/',
		],
		'mandate pending adds note but does not change order status'  => [
			'order_status'          => OrderStatus::PROCESSING,
			'mandate_status'        => 'pending',
			'payment_method_type'   => 'card',
			'revocation_reason'     => null,
			'expected_order_status' => OrderStatus::PROCESSING,
			'expected_note_pattern' => '/status updated to pending/',
		],
		'duplicate inactive webhook does not re-update on-hold order' => [
			'order_status'          => OrderStatus::ON_HOLD,
			'mandate_status'        => 'inactive',
			'payment_method_type'   => 'card',
			'revocation_reason'     => null,
			'expected_order_status' => OrderStatus::ON_HOLD,
			'expected_note_pattern' => null,
		],
		'revocation escalates on-hold order to cancelled'             => [
			'order_status'          => OrderStatus::ON_HOLD,
			'mandate_status'        => 'inactive',
			'payment_method_type'   => 'card',
			'revocation_reason'     => 'customer_request',
			'expected_order_status' => OrderStatus::CANCELLED,
			'expected_note_pattern' => '/revoked by the customer/',
		],
	];
}
```

- [ ] **Step 2: Write the test method**

Add the following test method right before the data provider:

```php
/**
 * Test for `process_webhook_mandate_updated`.
 *
 * @param string      $order_status          The initial order status.
 * @param string      $mandate_status        The mandate status from Stripe.
 * @param string      $payment_method_type   The payment method type.
 * @param string|null $revocation_reason     The revocation reason, or null.
 * @param string      $expected_order_status The expected order status after processing.
 * @param string|null $expected_note_pattern The expected note regex pattern, or null if no new note.
 * @return void
 * @dataProvider provide_test_process_webhook_mandate_updated
 */
public function test_process_webhook_mandate_updated( $order_status, $mandate_status, $payment_method_type, $revocation_reason, $expected_order_status, $expected_note_pattern ) {
	$mandate_id = 'mandate_mock_123';

	$order = WC_Helper_Order::create_order();
	$order->set_status( $order_status );
	$order->update_meta_data( '_stripe_mandate_id', $mandate_id );
	$order->save();

	$payment_method_details = (object) [
		$payment_method_type => (object) [],
	];

	if ( $revocation_reason ) {
		$payment_method_details->$payment_method_type->revocation_reason = $revocation_reason;
	}

	$notification = (object) [
		'type' => 'mandate.updated',
		'data' => (object) [
			'object' => (object) [
				'id'                     => $mandate_id,
				'status'                 => $mandate_status,
				'payment_method_type'    => $payment_method_type,
				'payment_method_details' => $payment_method_details,
			],
		],
	];

	$this->mock_webhook_handler->process_webhook_mandate_updated( $notification );

	$final_order = wc_get_order( $order->get_id() );
	$this->assertSame( $expected_order_status, $final_order->get_status() );

	if ( $expected_note_pattern ) {
		$notes = wc_get_order_notes(
			[
				'order_id' => $final_order->get_id(),
				'limit'    => 1,
			]
		);
		$this->assertNotEmpty( $notes, 'Expected an order note but none was found.' );
		$this->assertMatchesRegularExpression( $expected_note_pattern, $notes[0]->content );
	}
}
```

- [ ] **Step 3: Write the no-matching-order test**

```php
/**
 * Test that process_webhook_mandate_updated handles no matching order gracefully.
 */
public function test_process_webhook_mandate_updated_no_matching_order() {
	$notification = (object) [
		'type' => 'mandate.updated',
		'data' => (object) [
			'object' => (object) [
				'id'                     => 'mandate_nonexistent',
				'status'                 => 'inactive',
				'payment_method_type'    => 'card',
				'payment_method_details' => (object) [
					'card' => (object) [],
				],
			],
		],
	];

	// Should not throw an exception.
	$this->mock_webhook_handler->process_webhook_mandate_updated( $notification );

	// Verify no errors — test passes if no exception thrown.
	$this->assertTrue( true );
}
```

- [ ] **Step 4: Write the malformed-payload test**

```php
/**
 * Test that process_webhook_mandate_updated handles malformed payload gracefully.
 */
public function test_process_webhook_mandate_updated_malformed_payload() {
	// Missing mandate ID.
	$notification = (object) [
		'type' => 'mandate.updated',
		'data' => (object) [
			'object' => (object) [
				'status' => 'inactive',
			],
		],
	];

	$this->mock_webhook_handler->process_webhook_mandate_updated( $notification );
	$this->assertTrue( true );

	// Missing status.
	$notification2 = (object) [
		'type' => 'mandate.updated',
		'data' => (object) [
			'object' => (object) [
				'id' => 'mandate_mock_456',
			],
		],
	];

	$this->mock_webhook_handler->process_webhook_mandate_updated( $notification2 );
	$this->assertTrue( true );
}
```

- [ ] **Step 5: Write the multiple-orders test**

```php
/**
 * Test that process_webhook_mandate_updated only updates the most recent non-terminal order.
 */
public function test_process_webhook_mandate_updated_multiple_orders() {
	$mandate_id = 'mandate_shared_123';

	// Create an older order (completed — should be skipped by query).
	$old_order = WC_Helper_Order::create_order();
	$old_order->set_status( OrderStatus::COMPLETED );
	$old_order->update_meta_data( '_stripe_mandate_id', $mandate_id );
	$old_order->save();

	// Create a newer order (processing — should be the one updated).
	$new_order = WC_Helper_Order::create_order();
	$new_order->set_status( OrderStatus::PROCESSING );
	$new_order->update_meta_data( '_stripe_mandate_id', $mandate_id );
	$new_order->save();

	$notification = (object) [
		'type' => 'mandate.updated',
		'data' => (object) [
			'object' => (object) [
				'id'                     => $mandate_id,
				'status'                 => 'inactive',
				'payment_method_type'    => 'card',
				'payment_method_details' => (object) [
					'card' => (object) [],
				],
			],
		],
	];

	$this->mock_webhook_handler->process_webhook_mandate_updated( $notification );

	// The newer order should be updated to on-hold.
	$final_new_order = wc_get_order( $new_order->get_id() );
	$this->assertSame( OrderStatus::ON_HOLD, $final_new_order->get_status() );

	// The older completed order should remain unchanged.
	$final_old_order = wc_get_order( $old_order->get_id() );
	$this->assertSame( OrderStatus::COMPLETED, $final_old_order->get_status() );
}
```

- [ ] **Step 6: Run tests to verify they fail**

Run: `npm run test:php -- --filter=test_process_webhook_mandate_updated`
Expected: At least 3 tests FAIL (the ones expecting status changes: revoked->cancelled, paused->on-hold, escalation->cancelled). Tests that expect no status change (active, pending) and guard tests (no-order, malformed) will pass against the stub — this is expected.

- [ ] **Step 7: Commit**

```bash
git add tests/phpunit/class-wc-stripe-webhook-handler-test.php
git commit -m "test(STRIPE-139): add failing tests for mandate.updated webhook handler"
```

---

### Task 4: Implement `process_webhook_mandate_updated()`

**Files:**
- Modify: `includes/class-wc-stripe-webhook-handler.php` (replace stub from Task 2)

- [ ] **Step 1: Implement the handler method**

Replace the stub `process_webhook_mandate_updated()` method with:

```php
/**
 * Process webhook for mandate status updates.
 *
 * Handles mandate cancellation (revocation), pause (inactive), and
 * reactivation (active) for Indian recurring payments and other
 * mandate-based payment methods.
 *
 * @since 10.2.0
 * @param object $notification The webhook notification from Stripe.
 */
public function process_webhook_mandate_updated( $notification ) {
	$mandate = $notification->data->object ?? null;

	if ( ! isset( $mandate->id, $mandate->status ) ) {
		WC_Stripe_Logger::warning( 'mandate.updated webhook received with missing mandate ID or status.' );
		return;
	}

	$mandate_id     = $mandate->id;
	$mandate_status = $mandate->status;

	WC_Stripe_Logger::info(
		sprintf( 'Processing mandate.updated webhook for mandate %s with status %s', $mandate_id, $mandate_status )
	);

	$order = WC_Stripe_Order_Helper::get_instance()->get_order_by_mandate_id( $mandate_id );

	if ( ! $order ) {
		WC_Stripe_Logger::warning( 'Could not find order via mandate ID: ' . $mandate_id );
		return;
	}

	$this->resolved_order = $order;

	// Determine if this is a revocation by checking payment_method_details.
	$revocation_reason = $this->get_mandate_revocation_reason( $mandate );

	// Compute target order status.
	$target_order_status = null;
	if ( 'inactive' === $mandate_status && $revocation_reason ) {
		$target_order_status = OrderStatus::CANCELLED;
	} elseif ( 'inactive' === $mandate_status ) {
		$target_order_status = OrderStatus::ON_HOLD;
	}

	// Idempotency guard: skip if order is already in the target status (for actionable states)
	// or if a non-actionable state webhook is redelivered.
	if ( $target_order_status && $order->has_status( $target_order_status ) ) {
		WC_Stripe_Logger::info(
			sprintf( 'Order %d already has status %s, skipping mandate update.', $order->get_id(), $target_order_status )
		);
		return;
	}

	// For non-actionable states (active, pending), check if the last order note already
	// matches this mandate status to avoid duplicate notes on webhook redelivery.
	if ( ! $target_order_status ) {
		$existing_notes = wc_get_order_notes( [ 'order_id' => $order->get_id(), 'limit' => 1 ] );
		$note_preview   = $this->get_mandate_order_note( $mandate_id, $mandate_status, $revocation_reason );
		if ( ! empty( $existing_notes ) && $existing_notes[0]->content === $note_preview ) {
			WC_Stripe_Logger::info(
				sprintf( 'Duplicate mandate.updated webhook for order %d with status %s, skipping.', $order->get_id(), $mandate_status )
			);
			return;
		}
	}

	// Generate order note for this mandate state change.
	$note = $this->get_mandate_order_note( $mandate_id, $mandate_status, $revocation_reason );

	// Update order status for actionable states (note is passed to update_status which adds it).
	// For non-actionable states, add the note manually and save.
	if ( $target_order_status ) {
		$order->update_status( $target_order_status, $note );
	} else {
		$order->add_order_note( $note );
		$order->save();
	}

	// Update subscription status if applicable.
	$this->update_subscription_for_mandate( $order, $mandate_status, $revocation_reason );

	WC_Stripe_Logger::info(
		sprintf(
			'Mandate %s update processed for order %d. Mandate status: %s, payment method type: %s',
			$mandate_id,
			$order->get_id(),
			$mandate_status,
			$mandate->payment_method_type ?? 'unknown'
		)
	);
}

/**
 * Extracts the revocation reason from a mandate's payment_method_details.
 *
 * The revocation reason is nested under payment_method_details.{type}
 * where {type} varies by payment method (e.g., card, au_becs_debit, acss_debit).
 *
 * @since 10.2.0
 * @param object $mandate The Stripe mandate object.
 * @return string|null The revocation reason, or null if not revoked.
 */
private function get_mandate_revocation_reason( $mandate ): ?string {
	if ( ! isset( $mandate->payment_method_details, $mandate->payment_method_type ) ) {
		return null;
	}

	$type = $mandate->payment_method_type;

	if ( isset( $mandate->payment_method_details->$type->revocation_reason ) ) {
		return $mandate->payment_method_details->$type->revocation_reason;
	}

	return null;
}

/**
 * Generates the order note message for a mandate status change.
 *
 * @since 10.2.0
 * @param string      $mandate_id        The Stripe mandate ID.
 * @param string      $mandate_status    The new mandate status.
 * @param string|null $revocation_reason The revocation reason, if applicable.
 * @return string The order note message.
 */
private function get_mandate_order_note( string $mandate_id, string $mandate_status, ?string $revocation_reason ): string {
	if ( 'inactive' === $mandate_status && $revocation_reason ) {
		return sprintf(
			/* translators: 1) Stripe mandate ID 2) revocation reason */
			__( 'Stripe mandate %1$s was revoked by the customer (via webhook). Reason: %2$s', 'woocommerce-gateway-stripe' ),
			$mandate_id,
			$revocation_reason
		);
	}

	if ( 'inactive' === $mandate_status ) {
		return sprintf(
			/* translators: %s Stripe mandate ID */
			__( 'Stripe mandate %s is now inactive (via webhook)', 'woocommerce-gateway-stripe' ),
			$mandate_id
		);
	}

	if ( 'active' === $mandate_status ) {
		return sprintf(
			/* translators: %s Stripe mandate ID */
			__( 'Stripe mandate %s is now active (via webhook)', 'woocommerce-gateway-stripe' ),
			$mandate_id
		);
	}

	return sprintf(
		/* translators: 1) Stripe mandate ID 2) mandate status */
		__( 'Stripe mandate %1$s status updated to %2$s (via webhook)', 'woocommerce-gateway-stripe' ),
		$mandate_id,
		$mandate_status
	);
}

/**
 * Updates the subscription status based on a mandate status change.
 *
 * Only updates if WooCommerce Subscriptions is active and the order
 * has associated subscriptions.
 *
 * @since 10.2.0
 * @param WC_Order    $order             The WooCommerce order.
 * @param string      $mandate_status    The new mandate status.
 * @param string|null $revocation_reason The revocation reason, if applicable.
 */
private function update_subscription_for_mandate( WC_Order $order, string $mandate_status, ?string $revocation_reason ): void {
	if ( ! WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
		return;
	}

	$subscriptions = wcs_get_subscriptions_for_order( $order );

	if ( empty( $subscriptions ) ) {
		return;
	}

	foreach ( $subscriptions as $subscription ) {
		if ( 'inactive' === $mandate_status && $revocation_reason ) {
			$subscription->update_status( 'cancelled', __( 'Subscription cancelled due to mandate revocation.', 'woocommerce-gateway-stripe' ) );
		} elseif ( 'inactive' === $mandate_status ) {
			$subscription->update_status( 'on-hold', __( 'Subscription paused due to mandate becoming inactive.', 'woocommerce-gateway-stripe' ) );
		} elseif ( 'active' === $mandate_status && $subscription->has_status( 'on-hold' ) ) {
			$subscription->update_status( 'active', __( 'Subscription reactivated due to mandate becoming active.', 'woocommerce-gateway-stripe' ) );
		}
	}
}
```

- [ ] **Step 2: Run tests to verify they pass**

Run: `npm run test:php -- --filter=test_process_webhook_mandate_updated`
Expected: All 10 test cases PASS (6 from data provider + no-matching-order + malformed-payload + multiple-orders + duplicate active note guard via data provider "active" case run twice if needed)

Note: The subscription-related test cases (data provider cases) won't test subscription behavior because WooCommerce Subscriptions is not loaded in the test environment. The subscription path is tested implicitly by the `is_subscriptions_enabled()` guard returning false. If you need subscription tests, they would require mocking `wcs_get_subscriptions_for_order`, which is beyond the scope of this initial implementation.

- [ ] **Step 3: Run PHPStan**

Run: `npm run phpstan`
Expected: No new errors. If there are unavoidable errors (e.g., from `wcs_get_subscriptions_for_order` not being recognized), add them to the baseline with `npm run phpstan:baseline`.

- [ ] **Step 4: Run PHP linting**

Run: `npm run lint:php`
Expected: No linting errors in modified files. If there are auto-fixable issues: `npm run lint:php-fix`

- [ ] **Step 5: Commit**

```bash
git add includes/class-wc-stripe-webhook-handler.php
git commit -m "feat(STRIPE-139): implement process_webhook_mandate_updated handler

Handles mandate.updated webhooks for Indian recurring payments.
Maps inactive+revocation to cancelled, inactive to on-hold,
and active to reactivation (with on-hold safety guard).
Adds order notes for all mandate state changes."
```

---

### Task 5: Final verification and cleanup

- [ ] **Step 1: Run the full PHP test suite**

Run: `npm run test:php`
Expected: All tests pass, no regressions

- [ ] **Step 2: Run JS tests (sanity check)**

Run: `npm run test:js`
Expected: All tests pass (no JS changes, but verify no broken state)

- [ ] **Step 3: Run all linters**

Run: `npm run lint:php && npm run lint:js`
Expected: No errors

- [ ] **Step 4: Run PHPStan**

Run: `npm run phpstan`
Expected: No new errors

- [ ] **Step 5: Review the diff**

Run: `git diff develop...HEAD --stat` and `git log develop...HEAD --oneline`
Expected: 3 files changed, 3-4 commits, changes scoped to the mandate webhook handler
