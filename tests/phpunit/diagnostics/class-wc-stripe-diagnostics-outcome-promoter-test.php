<?php

/**
 * Tests for WC_Stripe_Diagnostics_Outcome_Promoter.
 *
 * @package WooCommerce/Stripe/Diagnostics
 */
class WC_Stripe_Diagnostics_Outcome_Promoter_Test extends WP_UnitTestCase {

	/**
	 * Trace store fixture.
	 *
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	/**
	 * Promoter under test.
	 *
	 * @var WC_Stripe_Diagnostics_Outcome_Promoter
	 */
	private $promoter;

	public function set_up() {
		parent::set_up();
		$this->store    = new WC_Stripe_Diagnostics_Trace_Store();
		$this->promoter = new WC_Stripe_Diagnostics_Outcome_Promoter( $this->store );
		$this->store->delete_all();
	}

	public function tear_down() {
		$this->store->delete_all();
		parent::tear_down();
	}

	/**
	 * Full classification matrix — single test, one data row per recognized
	 * (or pointedly-not-recognized) signal. The label key on each row points
	 * at the specific shape under test when something fails.
	 *
	 * @dataProvider event_classification_matrix
	 *
	 * @param array       $event    Event payload to classify.
	 * @param string|null $expected Expected status, or null for no signal.
	 */
	public function test_classify_event( array $event, ?string $expected ) {
		$this->assertSame( $expected, WC_Stripe_Diagnostics_Outcome_Promoter::classify_event( $event ) );
	}

	public function event_classification_matrix(): array {
		$failed    = WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED;
		$completed = WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED;
		$abandoned = WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED;

		return [
			'api_response_with_error_failed'           => [
				[
					'kind'  => 'stripe.api.response',
					'error' => [ 'code' => 'card_declined' ],
				],
				$failed,
			],
			'api_response_without_error_null'          => [
				[
					'kind' => 'stripe.api.response',
					'id'   => 'pi_123',
				],
				null,
			],
			'webhook_payment_failed_failed'            => [
				[
					'kind' => 'webhook.received',
					'type' => 'payment_intent.payment_failed',
				],
				$failed,
			],
			'webhook_succeeded_completed'              => [
				[
					'kind' => 'webhook.received',
					'type' => 'payment_intent.succeeded',
				],
				$completed,
			],
			'webhook_charge_succeeded_completed'       => [
				[
					'kind' => 'webhook.received',
					'type' => 'charge.succeeded',
				],
				$completed,
			],
			'webhook_setup_intent_succeeded_null'      => [
				// Save-card flow never charges; must NOT mark a checkout
				// as completed via the generic `.succeeded` suffix.
				[
					'kind' => 'webhook.received',
					'type' => 'setup_intent.succeeded',
				],
				null,
			],
			'create_payment_method_error_failed'       => [
				[
					'kind' => 'stripe.createPaymentMethod.resolve',
					'data' => [ 'has_error' => true ],
				],
				$failed,
			],
			'confirm_payment_error_failed'             => [
				[
					'kind' => 'stripe.confirmPayment.resolve',
					'data' => [ 'has_error' => true ],
				],
				$failed,
			],
			'blocks_payment_setup_result_failure'      => [
				[
					'kind' => 'blocks.payment_setup.end',
					'data' => [ 'result_type' => 'failure' ],
				],
				$failed,
			],
			'blocks_payment_setup_result_error'        => [
				[
					'kind' => 'blocks.payment_setup.end',
					'data' => [ 'result_type' => 'error' ],
				],
				$failed,
			],
			'blocks_payment_setup_result_success_null' => [
				[
					'kind' => 'blocks.payment_setup.end',
					'data' => [ 'result_type' => 'success' ],
				],
				null,
			],
			'express_cancel_abandoned'                 => [
				[ 'kind' => 'express.cancel' ],
				$abandoned,
			],
			'stripe_call_throw_failed'                 => [
				// aroundStripeCall() emits stripe.<method>.throw on Promise
				// rejection (network drop, JS exception). Caught by the
				// prefix/suffix check, not the case list.
				[ 'kind' => 'stripe.confirmPayment.throw' ],
				$failed,
			],
			'unrelated_event_kind_null'                => [
				[
					'kind' => 'element.change',
					'data' => [ 'complete' => true ],
				],
				null,
			],
			'event_without_kind_null'                  => [
				[],
				null,
			],
		];
	}

	/**
	 * Precedence matrix for {@see WC_Stripe_Diagnostics_Outcome_Promoter::maybe_promote()}.
	 *
	 * Encodes the rules:
	 *   - failed/completed are last-write-wins (so 3DS retry promotes
	 *     failed → completed)
	 *   - abandoned only promotes from pending (a late wallet-cancel
	 *     never downgrades a confirmed terminal status)
	 *   - unrecognized events are no-ops
	 *
	 * @dataProvider promotion_precedence_matrix
	 *
	 * @param string|null $starting_status Status to seed before firing the event, or null for fresh.
	 * @param array       $event           Event to promote.
	 * @param string      $expected_after  Expected status after promotion.
	 */
	public function test_maybe_promote_applies_precedence_rules( ?string $starting_status, array $event, string $expected_after ) {
		$this->store->create( 'precedence' );
		if ( null !== $starting_status ) {
			$this->store->set_status( 'precedence', $starting_status );
		}

		$this->promoter->maybe_promote( 'precedence', $event );

		$this->assertSame( $expected_after, $this->store->get( 'precedence' )['status'] );
	}

	public function promotion_precedence_matrix(): array {
		$failed    = WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED;
		$completed = WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED;
		$abandoned = WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED;

		$failure_signal     = [
			'kind'  => 'stripe.api.response',
			'error' => [ 'code' => 'card_declined' ],
		];
		$success_signal     = [
			'kind' => 'webhook.received',
			'type' => 'payment_intent.succeeded',
		];
		$abandoned_signal   = [ 'kind' => 'express.cancel' ];
		$unrecognized_event = [ 'kind' => 'element.change' ];

		return [
			'pending_to_failed'              => [ null, $failure_signal, $failed ],
			'failed_to_completed_3ds_retry'  => [ $failed, $success_signal, $completed ],
			'completed_to_failed_last_write' => [ $completed, $failure_signal, $failed ],
			'pending_to_abandoned_express'   => [ null, $abandoned_signal, $abandoned ],
			'failed_blocks_abandoned'        => [ $failed, $abandoned_signal, $failed ],
			'completed_blocks_abandoned'     => [ $completed, $abandoned_signal, $completed ],
			'unrecognized_event_is_noop'     => [ $failed, $unrecognized_event, $failed ],
		];
	}

	/**
	 * Missing trace must be a silent no-op — the recorder may fire events
	 * for sessions that haven't been created yet (e.g. lazy webhook
	 * creation paths).
	 */
	public function test_maybe_promote_is_noop_when_trace_is_missing() {
		$this->promoter->maybe_promote(
			'ghost',
			[
				'kind' => 'webhook.received',
				'type' => 'payment_intent.succeeded',
			]
		);
		$this->assertNull( $this->store->get( 'ghost' ) );
	}
}
