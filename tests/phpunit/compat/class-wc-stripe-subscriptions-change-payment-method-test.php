<?php
/**
 * Class WC_Stripe_Subscriptions_Change_Payment_Method_Test
 *
 * @package WooCommerce_Stripe/Tests/Compat
 */

/**
 * Tests for changing a subscription's payment method to a redirect-based payment method.
 */
class WC_Stripe_Subscriptions_Change_Payment_Method_Test extends WP_UnitTestCase {
	/**
	 * Gateway under test.
	 *
	 * @var WC_Stripe_UPE_Payment_Gateway&PHPUnit\Framework\MockObject\MockObject
	 */
	private $gateway;

	/**
	 * Subscription whose payment method is being changed.
	 *
	 * @var WC_Subscription
	 */
	private $subscription;

	/**
	 * URL the last wp_redirect() call targeted.
	 *
	 * @var string|null
	 */
	private $redirect_url;

	/**
	 * @inheritDoc
	 */
	public function set_up() {
		parent::set_up();

		$upe_helper = new UPE_Test_Helper();
		$upe_helper->enable_upe();
		$upe_helper->reload_payment_gateways();

		$this->gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->onlyMethods(
				[
					'get_return_url',
					'get_upe_gateway_id_for_order',
					'is_changing_payment_method_for_subscription',
					'maybe_disallow_prepaid_card',
					'prepare_payment_information_from_request',
					'process_setup_intent_for_order',
					'stripe_request',
					'validate_selected_payment_method_type',
				]
			)
			->getMock();

		$this->gateway->intent_controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->onlyMethods( [ 'complete_change_payment_from_setup_intent' ] )
			->getMock();

		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$this->subscription = new WC_Subscription();
		$this->subscription->set_customer_id( $user_id );
		$this->subscription->set_status( 'active' );
		$this->subscription->save();

		$subscription = $this->subscription;
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $subscription ) {
				return ( (int) $id === $subscription->get_id() ) ? $subscription : false;
			}
		);

		$this->redirect_url = null;
		add_filter(
			'wp_redirect',
			function ( $location ) {
				$this->redirect_url = $location;
				throw new \RuntimeException( 'redirect_intercepted' );
			}
		);
	}

	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		remove_all_filters( 'wp_redirect' );
		WC_Subscriptions::set_wcs_get_subscription( null );
		WC_Subscriptions_Helpers::$wcs_is_subscription = null;
		$_GET = [];
		wc_clear_notices();

		parent::tear_down();
	}

	/**
	 * Provides setup intents that require action, and where the customer should be sent.
	 *
	 * @return array
	 */
	public function provide_change_payment_setup_intents_requiring_action(): array {
		$stripe_url = 'https://pm-redirects.stripe.com/authorize/acct_123/sa_nonce_123';

		return [
			'redirect-based method goes to Stripe' => [
				'klarna',
				[
					'type'            => 'redirect_to_url',
					'redirect_to_url' => (object) [ 'url' => $stripe_url ],
				],
				$stripe_url,
			],
			'non-Stripe redirect stays blocked'    => [
				'klarna',
				[
					'type'            => 'redirect_to_url',
					'redirect_to_url' => (object) [ 'url' => 'https://evil-stripe.com/authorize' ],
				],
				admin_url(),
			],
			'card authentication keeps the hash'   => [
				'card',
				[ 'type' => 'use_stripe_sdk' ],
				'#wc-stripe-confirm-si:',
			],
		];
	}

	/**
	 * A setup intent that needs a redirect sends the customer to Stripe, not admin_url().
	 *
	 * @dataProvider provide_change_payment_setup_intents_requiring_action
	 *
	 * @param string $payment_method_type The selected payment method type.
	 * @param array  $next_action         The setup intent's next action.
	 * @param string $expected_redirect   The expected redirect URL, or its prefix for hash URLs.
	 */
	public function test_change_payment_redirect_for_setup_intent_requiring_action( string $payment_method_type, array $next_action, string $expected_redirect ) {
		$this->gateway->method( 'prepare_payment_information_from_request' )->willReturn(
			[
				'payment_method'               => 'pm_123',
				'selected_payment_type'        => $payment_method_type,
				'save_payment_method_to_store' => false,
				'customer'                     => 'cus_123',
				'token'                        => false,
				'payment_method_details'       => null,
			]
		);
		$this->gateway->method( 'stripe_request' )->willReturn( (object) [ 'id' => 'pm_123' ] );
		$this->gateway->method( 'get_return_url' )->willReturn( 'https://store.test/return' );
		$this->gateway->method( 'get_upe_gateway_id_for_order' )->willReturn( 'stripe_' . $payment_method_type );
		$this->gateway->method( 'process_setup_intent_for_order' )->willReturn(
			(object) [
				'id'                   => 'seti_123',
				'object'               => 'setup_intent',
				'status'               => WC_Stripe_Intent_Status::REQUIRES_ACTION,
				'client_secret'        => 'seti_123_secret_456',
				'payment_method_types' => [ $payment_method_type ],
				'next_action'          => (object) $next_action,
			]
		);

		try {
			$this->gateway->process_change_subscription_payment_with_deferred_intent( $this->subscription->get_id() );
			$this->fail( 'Expected a redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		}

		$this->assertStringStartsWith( $expected_redirect, $this->redirect_url );
	}

	/**
	 * Provides new gateway IDs and whether Subscriptions may update right away.
	 *
	 * @return array
	 */
	public function provide_new_payment_methods(): array {
		return [
			'card'              => [ 'stripe', false ],
			'klarna'            => [ 'stripe_klarna', false ],
			'other gateway'     => [ 'paypal', true ],
			'non-string filter' => [ null, true ],
		];
	}

	/**
	 * Subscriptions must not switch to any Stripe gateway before the setup intent is confirmed.
	 *
	 * @dataProvider provide_new_payment_methods
	 *
	 * @param mixed $new_payment_method The gateway ID the customer chose.
	 * @param bool  $expected           Whether Subscriptions may update the subscription right away.
	 */
	public function test_update_payment_after_deferred_intent( $new_payment_method, bool $expected ) {
		$this->gateway->method( 'is_changing_payment_method_for_subscription' )->willReturn( true );

		$this->assertSame( $expected, $this->gateway->update_payment_after_deferred_intent( true, $new_payment_method, $this->subscription ) );
	}

	/**
	 * A successful return applies the setup intent and lands on the subscription.
	 */
	public function test_change_payment_method_redirect_success_updates_subscription() {
		$this->set_redirect_return_request( 'succeeded' );

		$this->gateway->intent_controller->expects( $this->once() )
			->method( 'complete_change_payment_from_setup_intent' )
			->with( $this->subscription, 'seti_123' )
			->willReturn( 'Payment method updated.' );

		$this->assert_redirect_return_handled();

		$this->assertSame( $this->subscription->get_view_order_url(), $this->redirect_url );
		$this->assertSame( [ 'Payment method updated.' ], wp_list_pluck( wc_get_notices( 'success' ), 'notice' ) );
	}

	/**
	 * When the customer cancels or fails the redirect, the subscription is left unchanged.
	 */
	public function test_change_payment_method_redirect_failure_keeps_subscription() {
		$this->set_redirect_return_request( 'failed' );

		$this->gateway->intent_controller->expects( $this->never() )->method( 'complete_change_payment_from_setup_intent' );

		$this->assert_redirect_return_handled();

		$this->assertSame( $this->subscription->get_view_order_url(), $this->redirect_url );
		$this->assertCount( 1, wc_get_notices( 'error' ) );
	}

	/**
	 * Provides changes to an otherwise valid return request that must stop it being handled.
	 *
	 * @return array
	 */
	public function provide_invalid_redirect_returns(): array {
		return [
			'invalid nonce'             => [ [ '_wpnonce' => 'invalid' ] ],
			'another gateway'           => [ [ 'wc_payment_method' => 'paypal' ] ],
			'not a subscription'        => [ [], false ],
			'another user subscription' => [ [], true, true ],
		];
	}

	/**
	 * Requests that are not a valid change payment method return are left to the other handlers.
	 *
	 * @dataProvider provide_invalid_redirect_returns
	 *
	 * @param array $overrides           Query args to override.
	 * @param bool  $is_subscription     Whether the order ID is a subscription.
	 * @param bool  $owned_by_other_user Whether the subscription belongs to another user.
	 */
	public function test_change_payment_method_redirect_ignores_invalid_requests( array $overrides, bool $is_subscription = true, bool $owned_by_other_user = false ) {
		$this->set_redirect_return_request( 'succeeded', $overrides );
		WC_Subscriptions_Helpers::$wcs_is_subscription = $is_subscription;

		if ( $owned_by_other_user ) {
			wp_set_current_user( $this->factory->user->create() );
		}

		$this->gateway->intent_controller->expects( $this->never() )->method( 'complete_change_payment_from_setup_intent' );

		$this->assertFalse( $this->gateway->maybe_process_change_payment_method_redirect() );
		$this->assertNull( $this->redirect_url );
	}

	/**
	 * A non-card payment method gets its title, not its gateway ID.
	 */
	public function test_complete_change_payment_sets_title_for_non_card_payment_methods() {
		$gateway_id = WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_Payment_Methods::KLARNA;

		$registered_gateways = WC()->payment_gateways()->payment_gateways();
		$this->assertArrayHasKey( $gateway_id, $registered_gateways );

		$this->subscription->set_payment_method_title( $gateway_id );
		$this->subscription->save();

		$token = new WC_Stripe_Payment_Token_CC();
		$token->set_gateway_id( $gateway_id );
		$token->set_token( 'pm_klarna_123' );

		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'create_token_from_setup_intent' ] )
			->getMock();
		$gateway->method( 'create_token_from_setup_intent' )->willReturn( $token );

		$controller = $this->getMockBuilder( WC_Stripe_Intent_Controller::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_upe_gateway' ] )
			->getMock();
		$controller->method( 'get_upe_gateway' )->willReturn( $gateway );

		$controller->complete_change_payment_from_setup_intent( $this->subscription, 'seti_123' );

		$this->assertSame( $registered_gateways[ $gateway_id ]->get_title(), $this->subscription->get_payment_method_title() );
		$this->assertSame( 'pm_klarna_123', $this->subscription->get_meta( '_stripe_source_id' ) );
	}

	/**
	 * Sets up the query args Stripe adds when returning the customer from the redirect.
	 *
	 * @param string $redirect_status The redirect status Stripe reports.
	 * @param array  $overrides       Query args to override.
	 */
	private function set_redirect_return_request( string $redirect_status, array $overrides = [] ): void {
		WC_Subscriptions_Helpers::$wcs_is_subscription = true;

		$_GET = array_merge(
			[
				'order_id'          => (string) $this->subscription->get_id(),
				'wc_payment_method' => WC_Stripe_UPE_Payment_Gateway::ID,
				'_wpnonce'          => wp_create_nonce( 'wc_stripe_process_redirect_order_nonce' ),
				'setup_intent'      => 'seti_123',
				'redirect_status'   => $redirect_status,
			],
			$overrides
		);
	}

	/**
	 * Runs the return handler and checks that it handled the request with a redirect.
	 */
	private function assert_redirect_return_handled(): void {
		try {
			$this->gateway->maybe_process_change_payment_method_redirect();
			$this->fail( 'Expected a redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		}
	}
}
