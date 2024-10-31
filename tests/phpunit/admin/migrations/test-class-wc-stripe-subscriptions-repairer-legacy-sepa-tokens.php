<?php
/**
 * Class WC_Stripe_Subscriptions_Repairer_Legacy_SEPA_Tokens_Test
 */

/**
 * WC_Stripe_Subscriptions_Repairer_Legacy_SEPA_Tokens_Test unit tests.
 */
class WC_Stripe_Subscriptions_Repairer_Legacy_SEPA_Tokens_Test extends WP_UnitTestCase {

	/**
	 * Subscription meta key used to store the associated source ID.
	 *
	 * @var string
	 */
	const SOURCE_ID_META_KEY = '_stripe_source_id';

	/**
	 * Logger mock.
	 *
	 * @var MockObject|WC_Logger
	 */
	private $logger_mock;

	/**
	 * Subscriptions IDs that must be migrated.
	 *
	 * @var array
	 */
	private $subs_ids_to_migrate = [];

	/**
	 * @var WC_Stripe_Subscriptions_Legacy_SEPA_Token_Update
	 */
	private $updater;

	/**
	 * @var UPE_Test_Helper
	 */
	private $upe_helper;

	/**
	 * Gateway ID for the updated SEPA payment method.
	 *
	 * @var string
	 */
	private $updated_sepa_gateway_id = WC_Stripe_UPE_Payment_Gateway::ID . '_' . WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID;

	/**
	 * Gateway ID for the legacy SEPA payment method.
	 *
	 * @var string
	 */
	private $legacy_sepa_gateway_id = WC_Gateway_Stripe_Sepa::ID;

	public function set_up() {
		parent::set_up();

		$this->upe_helper = new UPE_Test_Helper();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/migrations/class-wc-stripe-subscriptions-repairer-legacy-sepa-tokens.php';

		$this->logger_mock = $this->getMockBuilder( 'WC_Logger' )
								   ->disableOriginalConstructor()
								   ->setMethods( [ 'add' ] )
								   ->getMock();
		$this->updater     = $this->getMockBuilder( 'WC_Stripe_Subscriptions_Repairer_Legacy_SEPA_Tokens' )
								   ->setConstructorArgs( [ $this->logger_mock ] )
								   ->setMethods( [ 'init', 'schedule_repair' ] )
								   ->getMock();
	}

	/**
	 * For the repair to be scheduled, WC_Subscriptions must be active, UPE must be enabled, and the action must not have been scheduled before.
	 *
	 * We can't mock the check for WC_Subscriptions, so we'll test the rest of the conditions.
	 */
	public function test_updater_gets_scheduled_on_right_conditions() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'upe_checkout_experience_enabled' => 'yes' ] );
		delete_option( 'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated' );

		$this->updater
			 ->expects( $this->once() )
			 ->method( 'schedule_repair' );

		$this->updater->maybe_update();
	}

	public function test_updater_doesn_not_get_scheduled_when_legacy_is_enabled() {
		delete_option( 'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated' );

		$this->updater
			 ->expects( $this->never() )
			 ->method( 'schedule_repair' );

		$this->updater->maybe_update();
	}

	public function test_updater_doesn_not_get_scheduled_when_already_done() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'upe_checkout_experience_enabled' => 'yes' ] );
		update_option( 'woocommerce_stripe_subscriptions_legacy_sepa_tokens_updated', 'yes' );

		$this->updater
			 ->expects( $this->never() )
			 ->method( 'schedule_repair' );

		$this->updater->maybe_update();
	}

	/**
	 * Run this test before creating the orders and subscriptions.
	 */
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
		$expected_ids = $this->get_subs_ids_to_migrate();

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

	public function test_maybe_update_subscription_legacy_payment_method_logs_on_exception() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'upe_checkout_experience_enabled' => 'yes' ] );

		// Throw an arbitrary exception to confirm the logger is called with the Exception's message.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) {
				throw new Exception( 'Mistakes were made' );
			}
		);

		$ids_to_migrate = $this->get_subs_ids_to_migrate();

		$this->logger_mock
			->expects( $this->exactly( 2 ) )
			->method( 'add' )
			->withConsecutive(
				[
					$this->anything(),
					$this->anything(),
				],
				[
					$this->equalTo( 'woocommerce-gateway-stripe-subscriptions-legacy-sepa-tokens-repairs' ),
					$this->equalTo( 'Mistakes were made' ),
				],
			);

		$this->updater->repair_item( $ids_to_migrate[0] );
	}

	public function test_maybe_update_subscription_legacy_payment_method_bails_when_the_legacy_experience_is_enabled() {
		$ids_to_migrate  = $this->get_subs_ids_to_migrate();
		$subscription_id = $ids_to_migrate[0];

		// We didn't set upe_checkout_experience_enabled to 'yes', which means the Legacy experience is enabled.
		$this->logger_mock
			->expects( $this->exactly( 2 ) )
			->method( 'add' )
			->withConsecutive(
				[
					$this->anything(),
					$this->anything(),
				],
				[
					$this->equalTo( 'woocommerce-gateway-stripe-subscriptions-legacy-sepa-tokens-repairs' ),
					$this->equalTo( sprintf( '---- Skipping migration of subscription #%d. The Legacy experience is enabled.', $subscription_id ) ),
				],
			);

		$this->updater->repair_item( $subscription_id );
	}

	public function test_maybe_update_subscription_legacy_payment_method_bails_when_the_subscription_is_not_found() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'upe_checkout_experience_enabled' => 'yes' ] );

		// Mock the subscription not being found.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) {
				return false;
			}
		);

		$ids_to_migrate  = $this->get_subs_ids_to_migrate();
		$subscription_id = $ids_to_migrate[0];

		$this->logger_mock
			->expects( $this->exactly( 2 ) )
			->method( 'add' )
			->withConsecutive(
				[
					$this->anything(),
					$this->anything(),
				],
				[
					$this->equalTo( 'woocommerce-gateway-stripe-subscriptions-legacy-sepa-tokens-repairs' ),
					$this->equalTo( sprintf( '---- Skipping migration of subscription #%d. Subscription not found.', $subscription_id ) ),
				],
			);

		$this->updater->repair_item( $subscription_id );
	}

	public function test_maybe_update_subscription_legacy_payment_method_bails_when_the_payment_method_is_not_sepa() {
		WC_Stripe_Helper::update_main_stripe_settings( [ 'upe_checkout_experience_enabled' => 'yes' ] );

		$ids_to_migrate  = $this->get_subs_ids_to_migrate();
		$subscription_id = $ids_to_migrate[0];

		$subscription = new WC_Subscription( $subscription_id );
		$subscription->set_payment_method( 'stripe' );
		$subscription->save();

		// Retrieve the actual subscription.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) {
				return new WC_Subscription( $id );
			}
		);

		// The payment method associated with the subscription isn't SEPA, so no migration is needed.
		$this->logger_mock
			->expects( $this->exactly( 2 ) )
			->method( 'add' )
			->withConsecutive(
				[
					$this->anything(),
					$this->anything(),
				],
				[
					$this->equalTo( 'woocommerce-gateway-stripe-subscriptions-legacy-sepa-tokens-repairs' ),
					$this->equalTo( sprintf( '---- Skipping migration of subscription #%d. Subscription is not using the legacy SEPA payment method.', $subscription_id ) ),
				],
			);

		$this->updater->repair_item( $subscription_id );
	}

	public function test_get_updated_sepa_token_by_source_id_returns_the_updated_token() {
		$this->upe_helper->enable_upe_feature_flag();
		$this->upe_helper->enable_upe();

		// Retrieve the actual subscription.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) {
				return new WC_Subscription( $id );
			}
		);

		$ids_to_migrate     = $this->get_subs_ids_to_migrate();
		$subscription_id    = $ids_to_migrate[0];
		$subscription       = new WC_Subscription( $subscription_id );
		$customer_id        = $subscription->get_user_id();
		$original_source_id = $subscription->get_meta( self::SOURCE_ID_META_KEY );

		$this->logger_mock
			->expects( $this->exactly( 2 ) )
			->method( 'add' )
			->withConsecutive(
				[
					$this->equalTo( 'woocommerce-gateway-stripe-subscriptions-legacy-sepa-tokens-repairs' ),
					$this->equalTo( sprintf( 'Migrating subscription #%1$d.', $subscription_id ) ),
				],
				[
					$this->equalTo( 'woocommerce-gateway-stripe-subscriptions-legacy-sepa-tokens-repairs' ),
					$this->equalTo( sprintf( 'Successful migration of subscription #%1$d.', $subscription_id ) ),
				],
			);

		$this->updater->repair_item( $subscription_id );

		$subscription = new WC_Subscription( $subscription_id );

		// Confirm the subscription's payment method was updated.
		$this->assertEquals( $this->updated_sepa_gateway_id, $subscription->get_payment_method() );

		// Confirm the subscription's source ID remains the same.
		$this->assertEquals( $original_source_id, $subscription->get_meta( self::SOURCE_ID_META_KEY ) );

		// Confirm the flag for the migration was set.
		$this->assertEquals( $this->legacy_sepa_gateway_id, $subscription->get_meta( '_migrated_sepa_payment_method' ) );
	}

	public function test_maybe_update_subscription_legacy_payment_method_adds_note_when_not_using_src() {
		$this->upe_helper->enable_upe_feature_flag();
		$this->upe_helper->enable_upe();

		// Retrieve the actual subscription.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) {
				return new WC_Subscription( $id );
			}
		);

		$ids_to_migrate  = $this->get_subs_ids_to_migrate();
		$subscription_id = $ids_to_migrate[0];
		$subscription    = new WC_Subscription( $subscription_id );
		$pm_id           = 'pm_123';

		$subscription->update_meta_data( self::SOURCE_ID_META_KEY, $pm_id );
		$subscription->save();

		$this->updater->maybe_migrate_before_renewal( $subscription_id );

		$subscription = new WC_Subscription( $subscription_id );

		// Confirm the subscription's payment method remains the same.
		$this->assertEquals( $pm_id, $subscription->get_meta( self::SOURCE_ID_META_KEY ) );
	}

	public function test_maybe_update_subscription_legacy_payment_method_adds_note_when_source_not_migrated() {
		$this->upe_helper->enable_upe_feature_flag();
		$this->upe_helper->enable_upe();

		// Retrieve the actual subscription.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) {
				return new WC_Subscription( $id );
			}
		);

		$ids_to_migrate  = $this->get_subs_ids_to_migrate();
		$subscription_id = $ids_to_migrate[0];
		$subscription    = new WC_Subscription( $subscription_id );
		$source_id       = $subscription->get_meta( self::SOURCE_ID_META_KEY );

		$this->updater->maybe_migrate_before_renewal( $subscription_id );

		$subscription = new WC_Subscription( $subscription_id );

		// Confirm the subscription's payment method remains the same.
		$this->assertEquals( $source_id, $subscription->get_meta( self::SOURCE_ID_META_KEY ) );
	}

	/**
	 * Creates orders and subscriptions, and returns the IDs of the subscriptions that must be updated.
	 *
	 * @return array The expected subscriptions IDs.
	 */
	private function create_orders_and_subscriptions() {
		$first_customer_id  = $this->factory->user->create();
		$second_customer_id = $this->factory->user->create();

		$payment_methods = [ $this->legacy_sepa_gateway_id, 'stripe', $this->updated_sepa_gateway_id ];
		$customers       = [ $first_customer_id, $second_customer_id ];
		$sources         = [
			$first_customer_id  => [ 'src_111', 'src_222' ],
			$second_customer_id => [ 'src_333', 'src_444' ],
		];

		$expected = [];

		// Create 25 subscriptions with the legacy SEPA gateway, 'stripe_sepa'.
		for ( $i = 0; $i < 25; $i++ ) {
			$customer_id  = $customers[ array_rand( $customers ) ];
			$source_id    = 'src_' . rand( 100, 999 );
			$subscription = $this->create_subscription( $this->legacy_sepa_gateway_id, $customer_id, $source_id );
			$expected[]   = $subscription->get_id();
		}

		// Create subscriptions with other payment methods and orders.
		foreach ( $customers as $customer_id ) {

			foreach ( $payment_methods as $payment_method_id ) {

				// Create subscriptions with other payment methods.
				if ( $this->legacy_sepa_gateway_id !== $payment_method_id ) {
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

	/**
	 * Creates a subscription with the given payment method, customer ID, and source ID.
	 *
	 * @param string $payment_method The gateway ID of the payment method to use.
	 * @param int    $customer_id    The ID of the customer the subscription is for.
	 * @param string $source_id      The source ID to associate with the subscription.
	 *
	 * @return WC_Subscription The created subscription.
	*/
	private function create_subscription( $payment_method, $customer_id, $source_id ) {
		$subscription = new WC_Subscription();
		$subscription->set_customer_id( $customer_id );
		$subscription->set_payment_method( $payment_method );

		$subscription->update_meta_data( self::SOURCE_ID_META_KEY, $source_id );
		$subscription->save();

		return $subscription;
	}

	/**
	 * Returns the subscriptions IDs that must be migrated.
	 *
	 * @return array
	 */
	private function get_subs_ids_to_migrate() {
		if ( empty( $this->subs_ids_to_migrate ) ) {
			$this->subs_ids_to_migrate = $this->create_orders_and_subscriptions();
		}
		return $this->subs_ids_to_migrate;
	}
}
