<?php
/**
 * Tests for the Pre-Orders charge-upon-release retry behavior.
 *
 * @package WooCommerce_Stripe/Tests
 */

/**
 * Covers the retry error-class gate in
 * WC_Stripe_Pre_Orders_Trait::process_pre_order_release_payment().
 */
class WC_Stripe_Pre_Orders_Release_Payment_Test extends WP_UnitTestCase {

	/**
	 * Builds a gateway mock with only the collaborators the release path calls
	 * stubbed, so the real trait logic under test runs unchanged.
	 *
	 * @param object $error_response The error the first off-session attempt returns.
	 * @return array{gateway: object, order: WC_Order}
	 */
	private function make_gateway_and_order( $error_response ): array {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'prepare_order_source',
					'create_and_confirm_intent_for_off_session',
					'is_authentication_required_for_payment',
					'remove_order_source_before_retry',
					'get_latest_charge_from_intent',
					'process_response',
				]
			)
			->getMock();

		$gateway->method( 'prepare_order_source' )->willReturn(
			(object) [
				'customer'       => 'cus_test',
				'source'         => 'pm_test',
				'source_object'  => (object) [ 'type' => 'card' ],
				'payment_method' => null,
			]
		);
		$gateway->method( 'create_and_confirm_intent_for_off_session' )->willReturn( $error_response );
		$gateway->method( 'is_authentication_required_for_payment' )->willReturn( false );

		$order = WC_Helper_Order::create_order();

		return [
			'gateway' => $gateway,
			'order'   => $order,
		];
	}

	/**
	 * A generic decline fails the order rather than clearing the source and
	 * retrying against the customer's default source.
	 *
	 * @return void
	 */
	public function test_generic_decline_does_not_fall_back_to_customer_default() {
		$error   = (object) [
			'error' => (object) [
				'type'    => 'card_error',
				'code'    => 'card_declined',
				'message' => 'Your card was declined.',
			],
		];
		$context = $this->make_gateway_and_order( $error );

		$context['gateway']->expects( $this->never() )->method( 'remove_order_source_before_retry' );

		$context['gateway']->process_pre_order_release_payment( $context['order'] );

		$this->assertTrue(
			$context['order']->has_status( 'failed' ),
			'A declined release charge should fail the order rather than retry against the customer default.'
		);
	}

	/**
	 * Provides one error fixture per gone-source error class the gate accepts.
	 *
	 * @return array
	 */
	public function provide_gone_source_errors(): array {
		return [
			'no such source'   => [ 'No such PaymentMethod: pm_test' ],
			'no linked source' => [ 'The customer does not have a linked source with ID pm_test.' ],
		];
	}

	/**
	 * A gone-source error still clears the source and retries, preserving the
	 * existing graceful fallback.
	 *
	 * @param string $error_message The Stripe error message.
	 *
	 * @dataProvider provide_gone_source_errors
	 *
	 * @return void
	 */
	public function test_missing_source_error_still_clears_source_and_retries( string $error_message ) {
		$error   = (object) [
			'error' => (object) [
				'type'    => 'invalid_request_error',
				'message' => $error_message,
			],
		];
		$context = $this->make_gateway_and_order( $error );

		$context['gateway']->expects( $this->once() )->method( 'remove_order_source_before_retry' );

		$context['gateway']->process_pre_order_release_payment( $context['order'] );
	}

	/**
	 * The default-source fallback stays gated behind wc_stripe_use_default_customer_source:
	 * with the filter disabled, even a gone-source error must not retry.
	 *
	 * @param string $error_message The Stripe error message.
	 *
	 * @dataProvider provide_gone_source_errors
	 *
	 * @return void
	 */
	public function test_missing_source_error_respects_disabled_default_source_filter( string $error_message ) {
		$error   = (object) [
			'error' => (object) [
				'type'    => 'invalid_request_error',
				'message' => $error_message,
			],
		];
		$context = $this->make_gateway_and_order( $error );

		add_filter( 'wc_stripe_use_default_customer_source', '__return_false' );

		$context['gateway']->expects( $this->never() )->method( 'remove_order_source_before_retry' );

		try {
			$context['gateway']->process_pre_order_release_payment( $context['order'] );
		} finally {
			remove_filter( 'wc_stripe_use_default_customer_source', '__return_false' );
		}
	}
}
