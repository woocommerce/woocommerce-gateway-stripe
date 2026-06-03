<?php

require_once __DIR__ . '/../helpers/class-wc-stripe-subscriptions-trait-non-upe-stub.php';

/**
 * Tests the subscription trait's downstream usage of WC_Stripe_Hook_Manager.
 *
 * These assert that `maybe_init_subscriptions()` registers the subscription
 * hooks exactly once, tracks that state in the hook manager, and respects the
 * UPE-gateway guard around the plugin-level hooks.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Subscriptions_Trait
 */
class WC_Stripe_Subscriptions_Hook_Registration_Test extends WP_UnitTestCase {

	/**
	 * A payment method ID unique to these tests.
	 *
	 * Using a dedicated ID keeps the per-method hook names (e.g.
	 * `woocommerce_scheduled_subscription_payment_<id>`) from colliding with
	 * hooks registered by the real gateways during bootstrap.
	 *
	 * @var string
	 */
	const TEST_PAYMENT_METHOD_ID = 'stripe_test_subs';

	/**
	 * @inheritDoc
	 */
	public function set_up() {
		parent::set_up();
		$this->reset_hook_manager_singleton();
	}

	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		$this->reset_hook_manager_singleton();
		parent::tear_down();
	}

	/**
	 * Resets the hook manager singleton so each test starts from a clean slate.
	 *
	 * @return void
	 */
	private function reset_hook_manager_singleton() {
		$reflection = new ReflectionProperty( WC_Stripe_Hook_Manager::class, 'instance' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, null );
	}

	/**
	 * Builds a UPE gateway instance with the constructor disabled and a test ID.
	 *
	 * @return WC_Stripe_UPE_Payment_Gateway
	 */
	private function get_upe_gateway_with_test_id() {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();

		$gateway->id       = self::TEST_PAYMENT_METHOD_ID;
		$gateway->supports = [];

		return $gateway;
	}

	/**
	 * Counts the callbacks registered against a hook at a given priority.
	 *
	 * @param string $hook_name The action/filter name.
	 * @param int    $priority  The priority bucket to inspect.
	 * @return int
	 */
	private function count_hook_callbacks( string $hook_name, int $priority = 10 ): int {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook_name ]->callbacks[ $priority ] ) ) {
			return 0;
		}

		return count( $wp_filter[ $hook_name ]->callbacks[ $priority ] );
	}

	/**
	 * `maybe_init_subscriptions()` registers the per-method hooks and records the state.
	 *
	 * @return void
	 */
	public function test_maybe_init_subscriptions_registers_payment_method_hooks() {
		$gateway = $this->get_upe_gateway_with_test_id();

		$scheduled_payment_hook = 'woocommerce_scheduled_subscription_payment_' . self::TEST_PAYMENT_METHOD_ID;
		$this->assertFalse( has_action( $scheduled_payment_hook ) );

		$gateway->maybe_init_subscriptions();

		// The WP action is now wired up to the gateway callback.
		$this->assertSame( 10, has_action( $scheduled_payment_hook, [ $gateway, 'scheduled_subscription_payment' ] ) );

		// And the hook manager records the per-method registration.
		$this->assertTrue(
			WC_Stripe_Hook_Manager::get_instance()->are_payment_method_hooks_registered(
				self::TEST_PAYMENT_METHOD_ID,
				WC_Stripe_Hook_Categories::SUBSCRIPTIONS
			)
		);
	}

	/**
	 * `maybe_init_subscriptions()` registers the plugin-level hooks for a UPE gateway.
	 *
	 * @return void
	 */
	public function test_maybe_init_subscriptions_registers_plugin_hooks_for_upe_gateway() {
		$gateway = $this->get_upe_gateway_with_test_id();

		$gateway->maybe_init_subscriptions();

		$this->assertTrue(
			WC_Stripe_Hook_Manager::get_instance()->are_plugin_hooks_registered(
				WC_Stripe_Hook_Categories::SUBSCRIPTIONS
			)
		);
		$this->assertSame(
			10,
			has_action( 'wcs_resubscribe_order_created', [ $gateway, 'delete_resubscribe_meta' ] )
		);
	}

	/**
	 * Re-initialising the same gateway does not register the per-method hooks twice.
	 *
	 * @return void
	 */
	public function test_maybe_init_subscriptions_does_not_duplicate_payment_method_hooks() {
		$gateway                = $this->get_upe_gateway_with_test_id();
		$scheduled_payment_hook = 'woocommerce_scheduled_subscription_payment_' . self::TEST_PAYMENT_METHOD_ID;

		$gateway->maybe_init_subscriptions();
		$this->assertSame( 1, $this->count_hook_callbacks( $scheduled_payment_hook ) );

		// A second call should short-circuit via the hook manager.
		$gateway->maybe_init_subscriptions();
		$this->assertSame( 1, $this->count_hook_callbacks( $scheduled_payment_hook ) );
	}

	/**
	 * Two gateways with different IDs each register their own per-method hooks.
	 *
	 * @return void
	 */
	public function test_maybe_init_subscriptions_registers_hooks_per_payment_method() {
		$first = $this->get_upe_gateway_with_test_id();
		$first->maybe_init_subscriptions();

		$second     = $this->get_upe_gateway_with_test_id();
		$second->id = 'stripe_test_subs_other';
		$second->maybe_init_subscriptions();

		$manager = WC_Stripe_Hook_Manager::get_instance();

		$this->assertTrue( $manager->are_payment_method_hooks_registered( self::TEST_PAYMENT_METHOD_ID, WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) );
		$this->assertTrue( $manager->are_payment_method_hooks_registered( 'stripe_test_subs_other', WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) );

		$this->assertSame( 10, has_action( 'woocommerce_scheduled_subscription_payment_' . self::TEST_PAYMENT_METHOD_ID, [ $first, 'scheduled_subscription_payment' ] ) );
		$this->assertSame( 10, has_action( 'woocommerce_scheduled_subscription_payment_stripe_test_subs_other', [ $second, 'scheduled_subscription_payment' ] ) );
	}

	/**
	 * The plugin hooks are skipped for trait consumers that are not UPE gateways,
	 * while the per-method hooks are still registered.
	 *
	 * @return void
	 */
	public function test_plugin_hooks_are_skipped_for_non_upe_gateway() {
		$stub = new WC_Stripe_Subscriptions_Trait_Non_UPE_Stub();

		$stub->maybe_init_subscriptions();

		$manager = WC_Stripe_Hook_Manager::get_instance();

		// Per-method hooks are registered for any consumer.
		$this->assertTrue(
			$manager->are_payment_method_hooks_registered( $stub->id, WC_Stripe_Hook_Categories::SUBSCRIPTIONS )
		);

		// Plugin hooks must NOT be registered when the consumer is not a UPE gateway.
		$this->assertFalse(
			$manager->are_plugin_hooks_registered( WC_Stripe_Hook_Categories::SUBSCRIPTIONS )
		);
	}
}
