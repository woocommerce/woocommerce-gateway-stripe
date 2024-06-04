<?php
/**
 * Class WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update_Test
 */

/**
 * WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update unit tests.
 */
class WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update_Test extends WP_UnitTestCase {

	/**
	 * Logger mock.
	 *
	 * @var MockObject|WC_Logger
	 */
	private $logger_mock;

	/**
	 * @var WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update
	 */
	private $updater;

	public function set_up() {
		parent::set_up();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-subscriptions-legacy-sepa-tokens-update.php';

		$this->logger_mock = $this->getMockBuilder( 'WC_Logger' )
								   ->disableOriginalConstructor()
								   ->setMethods( [ 'add' ] )
								   ->getMock();
		$this->updater     = $this->getMockBuilder( 'WC_Stripe_Subscriptions_Legacy_SEPA_Tokens_Update' )
								   ->setConstructorArgs( [ $this->logger_mock ] )
								   ->setMethods( [ 'init', 'schedule_repair' ] )
								   ->getMock();
	}

	/**
	 * For the repair to be scheduled, WC_Subscriptions must be active and UPE must be enabled.
	 *
	 * We can't mock the check for WC_Subscriptions, so we'll only test the UPE check.
	 */
	public function test_updater_gets_initiated_on_right_conditions() {
		update_option( 'woocommerce_stripe_settings', [ 'upe_checkout_experience_enabled' => 'yes' ] );

		$this->updater
			 ->expects( $this->once() )
			 ->method( 'init' );

		$this->updater
			 ->expects( $this->once() )
			 ->method( 'schedule_repair' );

		$this->updater->maybe_update();
	}

	public function test_updater_doesn_not_get_initiated_when_legacy_is_enabled() {
		$this->updater
			 ->expects( $this->never() )
			 ->method( 'init' );

		$this->updater
			 ->expects( $this->never() )
			 ->method( 'schedule_repair' );

		$this->updater->maybe_update();
	}

	public function test_get_items_to_repair_logs_on_empty_results() {
		$this->logger_mock
			->expects( $this->once() )
			->method( 'add' )
			->with(
				$this->equalTo( 'woocommerce-gateway-stripe-subscriptions-legacy-sepa-tokens-repairs' ),
				$this->equalTo( 'Finished scheduling subscription migrations.' )
			);

		$this->updater->get_items_to_update( 1 );
	}

	public function test_get_items_to_repair_return_the_right_items() {
		$expected_ids = $this->create_orders_and_subscriptions();

		$first_page_items_to_repair  = $this->updater->get_items_to_update( 1 );
		$second_page_items_to_repair = $this->updater->get_items_to_update( 2 );

		$expected_items_per_page = 20;

		$expected_ids_first_page = array_slice( $expected_ids, 0, $expected_items_per_page );
		$expected_ids_second_page = array_slice( $expected_ids, $expected_items_per_page );

		// Confirm the number of items on each page are the expected ones.
		$this->assertCount( $expected_items_per_page, $first_page_items_to_repair );
		$this->assertCount( count( $expected_ids_second_page ), $second_page_items_to_repair );

		// Confirm the items are the expected ones.
		$this->assertEmpty( array_diff( $expected_ids_first_page, $first_page_items_to_repair ) );
		$this->assertEmpty( array_diff( $expected_ids_second_page, $second_page_items_to_repair ) );

		// Confirm the items on the first and second page are different.
		$this->assertEmpty( array_intersect( $first_page_items_to_repair, $second_page_items_to_repair ) );
	}

	// public function test_maybe_update_subscription_legacy_payment_method_logs_on_exception() {}

	// public function test_maybe_update_subscription_legacy_payment_method_bails_when_the_legacy_experience_is_enabled() {}

	// public function test_maybe_update_subscription_legacy_payment_method_bails_when_the_subscription_is_not_found() {}

	// public function test_get_updated_sepa_token_by_source_id_bails_when_no_token_is_found() {}

	// public function test_get_updated_sepa_token_by_source_id_returns_the_right_token() {}

	// public function test_get_updated_sepa_token_by_source_id_returns_a_default_token() {}

	// public function test_subscription_payment_method_gets_correctly_updated() {}

	/**
	 * Creates orders and subscriptions, and returns the IDs of the subscriptions that must be updated.
	 *
	 * @return array The expected subscriptions IDs.
	 */
	private function create_orders_and_subscriptions() {
		$first_customer_id  = $this->factory->user->create();
		$second_customer_id = $this->factory->user->create();

		$payment_methods = [ 'stripe_sepa', 'stripe', 'stripe_sepa_debit' ];
		$customers       = [ $first_customer_id, $second_customer_id ];
		$sources         = [
			$first_customer_id  => [ 'src_111', 'src_222' ],
			$second_customer_id => [ 'src_333', 'src_444' ],
		];

		$expected = [];

		// Create 25 subscriptions with the 'stripe_sepa' payment method.
		for ( $i = 0; $i < 25; $i++ ) {
			$customer_id  = $customers[ array_rand( $customers ) ];
			$source_id    = array_rand( $sources[ $customer_id ] );
			$subscription = $this->create_subscription( 'stripe_sepa', $customer_id, $source_id );
			$expected[]   = $subscription->get_id();
		}

		// Create subscriptions with other payment methods and orders.
		foreach ( $customers as $customer_id ) {

			foreach ( $payment_methods as $payment_method_id ) {

				// Create subscriptions with other payment methods.
				if ( 'stripe_sepa' !== $payment_method_id ) {
					$source_id   = array_rand( $sources[ $customer_id ] );
					$subscription = $this->create_subscription( $payment_method_id, $customer_id, $source_id );
				}

				// Create orders.
				$order = WC_Helper_Order::create_order( $customer_id );
				$order->set_payment_method( $payment_method_id );
			}
		}

		return $expected;
	}


	private function create_subscription( $payment_method, $customer_id, $source_id ) {
		$subscription = new WC_Subscription();
		$subscription->set_customer_id( $customer_id );
		$subscription->set_payment_method( $payment_method );

		$subscription->update_meta_data( '_stripe_source_id', $source_id );
		$subscription->save();

		return $subscription;
	}
}
