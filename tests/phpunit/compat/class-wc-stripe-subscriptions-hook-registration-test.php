<?php

require_once __DIR__ . '/../helpers/class-wc-stripe-subscriptions-trait-non-upe-stub.php';

/**
 * Tests the concrete usage of {@see WC_Stripe_Subscriptions_Trait} and {@see WC_Stripe_Hook_Manager},
 * especially via {@see WC_Stripe_Subscriptions_Trait::maybe_init_subscriptions()}. *
 */
class WC_Stripe_Subscriptions_Hook_Registration_Test extends WP_UnitTestCase {

	/**
	 * A test payment method ID.
	 *
	 * @var string
	 */
	protected const TEST_PAYMENT_METHOD_ID = 'stripe_test_subs';

	/**
	 * A second test payment method ID.
	 *
	 * @var string
	 */
	protected const TEST_OTHER_PAYMENT_METHOD_ID = 'stripe_test_other';

	/**
	 * A map of hook templates to their expected method and priority.
	 *
	 * @var array
	 */
	protected const PAYMENT_METHOD_TEMPLATE_METHOD_MAP = [
		'woocommerce_scheduled_subscription_payment_%s'              => [
			'method'   => 'scheduled_subscription_payment',
			'priority' => 10,
			'sprintf'  => true,
		],
		'woocommerce_subscription_failing_payment_method_updated_%s' => [
			'method'   => 'update_failing_payment_method',
			'priority' => 10,
			'sprintf'  => true,
		],
		'wc_stripe_payment_fields_%s'                                => [
			'method'   => 'display_update_subs_payment_checkout',
			'priority' => 10,
			'sprintf'  => true,
		],
		'wc_stripe_add_payment_method_%s_success'                    => [
			'method'   => 'handle_add_payment_method_success',
			'priority' => 10,
			'sprintf'  => true,
		],
		'woocommerce_stripe_add_payment_method'                      => [
			'method'   => 'handle_upe_add_payment_method_success',
			'priority' => 10,
			'sprintf'  => false,
		],
		'woocommerce_my_subscriptions_payment_method'                => [
			'method'   => 'maybe_render_subscription_payment_method',
			'priority' => 10,
			'sprintf'  => false,
		],
		'woocommerce_subscription_payment_meta'                      => [
			'method'   => 'add_subscription_payment_meta',
			'priority' => 10,
			'sprintf'  => false,
		],
		'woocommerce_subscription_validate_payment_meta'             => [
			'method'   => 'validate_subscription_payment_meta',
			'priority' => 10,
			'sprintf'  => false,
		],
	];

	/**
	 * A map of plugin hooks to their expected method and priority.
	 *
	 * @var array
	 */
	protected const PLUGIN_HOOK_METHOD_MAP = [
		'woocommerce_subscriptions_change_payment_before_submit'     => [
			'method'   => 'differentiate_change_payment_method_form',
			'priority' => 10,
		],
		'wcs_resubscribe_order_created'                              => [
			'method'   => 'delete_resubscribe_meta',
			'priority' => 10,
		],
		'wcs_renewal_order_created'                                  => [
			'method'   => 'delete_renewal_meta',
			'priority' => 10,
		],
		'wc_stripe_display_save_payment_method_checkbox'             => [
			'method'   => 'display_save_payment_method_checkbox',
			'priority' => 10,
		],
		'wc_stripe_generate_create_intent_request'                   => [
			'method'   => 'add_subscription_information_to_intent',
			'priority' => 10,
		],
		'template_redirect'                                          => [
			'method'   => 'remove_order_pay_var',
			'priority' => 99,
		],
		'wc_order_is_editable'                                       => [
			'method'   => 'disable_subscription_edit_for_india',
			'priority' => 10,
		],
		'woocommerce_subscriptions_update_payment_via_pay_shortcode' => [
			'method'   => 'update_payment_after_deferred_intent',
			'priority' => 10,
		],
	];

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
	 * @param string $test_id The test payment method ID.
	 * @return WC_Stripe_UPE_Payment_Gateway
	 */
	private function get_upe_gateway_with_test_id( string $test_id ) {
		$gateway = $this->getMockBuilder( WC_Stripe_UPE_Payment_Gateway::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();

		$gateway->id       = $test_id;
		$gateway->supports = [];

		return $gateway;
	}

	/**
	 * Builds a payment method instance with the constructor disabled and a test ID.
	 *
	 * @param string $test_id The test payment method ID.
	 * @return WC_Stripe_UPE_Payment_Method_CC
	 */
	private function get_payment_method_with_test_id( string $test_id ) {
		$payment_method = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method_CC::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();

		$payment_method->id       = $test_id;
		$payment_method->supports = [];

		return $payment_method;
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
	 * Gets the payment method hooks for a given payment method ID.
	 *
	 * @param string $payment_method_id The payment method ID.
	 * @return array
	 */
	private function get_payment_method_hooks( string $payment_method_id ): array {
		$hooks = [];
		foreach ( self::PAYMENT_METHOD_TEMPLATE_METHOD_MAP as $hook_template => $hook_details ) {
			$hook_name           = ( $hook_details['sprintf'] ?? false ) ? sprintf( $hook_template, $payment_method_id ) : $hook_template;
			$hooks[ $hook_name ] = [
				'method'   => $hook_details['method'],
				'priority' => $hook_details['priority'],
			];
		}

		return $hooks;
	}

	/**
	 * Data provider that specifies the object type to use for the test.
	 *
	 * @return array
	 */
	public function provide_payment_method_object_types(): array {
		return [
			'gateway'        => [ 'gateway' ],
			'payment_method' => [ 'payment_method' ],
		];
	}

	/**
	 * `maybe_init_subscriptions()` registers the plugin-level hooks for a UPE gateway.
	 *
	 * @return void
	 */
	public function test_maybe_init_subscriptions_registers_plugin_hooks_for_upe_gateway(): void {
		$gateway = $this->get_upe_gateway_with_test_id( self::TEST_PAYMENT_METHOD_ID );

		$this->assertFalse(
			WC_Stripe_Hook_Manager::get_instance()->are_plugin_hooks_registered(
				WC_Stripe_Hook_Categories::SUBSCRIPTIONS
			)
		);

		$gateway->maybe_init_subscriptions();

		$this->assertTrue(
			WC_Stripe_Hook_Manager::get_instance()->are_plugin_hooks_registered(
				WC_Stripe_Hook_Categories::SUBSCRIPTIONS
			)
		);

		foreach ( self::PLUGIN_HOOK_METHOD_MAP as $hook => $template_details ) {
			$this->assertSame( $template_details['priority'], has_action( $hook, [ $gateway, $template_details['method'] ] ) );
		}
	}

	/**
	 * When a payment method is used, plugin hooks are not registered.
	 *
	 * @return void
	 */
	public function test_maybe_init_subscriptions_does_not_register_plugin_hooks_for_payment_method(): void {
		$payment_method = $this->get_payment_method_with_test_id( self::TEST_PAYMENT_METHOD_ID );

		$this->assertFalse(
			WC_Stripe_Hook_Manager::get_instance()->are_plugin_hooks_registered(
				WC_Stripe_Hook_Categories::SUBSCRIPTIONS
			)
		);

		$payment_method->maybe_init_subscriptions();

		$this->assertFalse(
			WC_Stripe_Hook_Manager::get_instance()->are_plugin_hooks_registered(
				WC_Stripe_Hook_Categories::SUBSCRIPTIONS
			)
		);
	}

	/**
	 * Re-initialising the same object does not register the per-method hooks twice.
	 *
	 * @dataProvider provide_payment_method_object_types
	 * @param string $object_type The type of object to use for the test.
	 * @return void
	 */
	public function test_maybe_init_subscriptions_does_not_duplicate_payment_method_hooks( string $object_type ): void {
		if ( 'gateway' === $object_type ) {
			$object = $this->get_upe_gateway_with_test_id( self::TEST_PAYMENT_METHOD_ID );
		} else {
			$object = $this->get_payment_method_with_test_id( self::TEST_PAYMENT_METHOD_ID );
		}

		$payment_method_hooks = $this->get_payment_method_hooks( self::TEST_PAYMENT_METHOD_ID );

		$start_counts = [];
		foreach ( $payment_method_hooks as $hook_name => $hook_details ) {
			$start_counts[ $hook_name ] = $this->count_hook_callbacks( $hook_name, $hook_details['priority'] );
		}

		$object->maybe_init_subscriptions();

		foreach ( $payment_method_hooks as $hook_name => $hook_details ) {
			$this->assertSame( 1 + $start_counts[ $hook_name ], $this->count_hook_callbacks( $hook_name, $hook_details['priority'] ) );
		}

		// A second call should not add more hooks.
		$object->maybe_init_subscriptions();

		foreach ( $payment_method_hooks as $hook_name => $hook_details ) {
			$this->assertSame( 1 + $start_counts[ $hook_name ], $this->count_hook_callbacks( $hook_name, $hook_details['priority'] ) );
		}
	}

	/**
	 * Two gateways with different IDs each register their own per-method hooks.
	 *
	 * @dataProvider provide_payment_method_object_types
	 * @param string $object_type The type of object to use for the test.
	 * @return void
	 */
	public function test_maybe_init_subscriptions_registers_hooks_per_payment_method( string $object_type ): void {
		if ( 'gateway' === $object_type ) {
			$first  = $this->get_upe_gateway_with_test_id( self::TEST_PAYMENT_METHOD_ID );
			$second = $this->get_upe_gateway_with_test_id( self::TEST_OTHER_PAYMENT_METHOD_ID );
		} else {
			$first  = $this->get_payment_method_with_test_id( self::TEST_PAYMENT_METHOD_ID );
			$second = $this->get_payment_method_with_test_id( self::TEST_OTHER_PAYMENT_METHOD_ID );
		}

		$manager = WC_Stripe_Hook_Manager::get_instance();

		$this->assertFalse( $manager->are_payment_method_hooks_registered( self::TEST_PAYMENT_METHOD_ID, WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) );
		$this->assertFalse( $manager->are_payment_method_hooks_registered( self::TEST_OTHER_PAYMENT_METHOD_ID, WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) );

		$first->maybe_init_subscriptions();
		$second->maybe_init_subscriptions();

		$this->assertTrue( $manager->are_payment_method_hooks_registered( self::TEST_PAYMENT_METHOD_ID, WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) );
		$this->assertTrue( $manager->are_payment_method_hooks_registered( self::TEST_OTHER_PAYMENT_METHOD_ID, WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) );

		$first_payment_method_hooks  = $this->get_payment_method_hooks( self::TEST_PAYMENT_METHOD_ID );
		$second_payment_method_hooks = $this->get_payment_method_hooks( self::TEST_OTHER_PAYMENT_METHOD_ID );

		foreach ( $first_payment_method_hooks as $hook_name => $hook_details ) {
			$this->assertSame( $hook_details['priority'], has_action( $hook_name, [ $first, $hook_details['method'] ] ) );
		}

		foreach ( $second_payment_method_hooks as $hook_name => $hook_details ) {
			$this->assertSame( $hook_details['priority'], has_action( $hook_name, [ $second, $hook_details['method'] ] ) );
		}
	}

	/**
	 * The plugin hooks are skipped for trait consumers that are not UPE gateways,
	 * while the per-method hooks are still registered.
	 *
	 * @return void
	 */
	public function test_plugin_hooks_are_skipped_for_non_upe_gateway() {
		$stub = new WC_Stripe_Subscriptions_Trait_Non_UPE_Stub();

		$manager = WC_Stripe_Hook_Manager::get_instance();

		$this->assertFalse(
			$manager->are_payment_method_hooks_registered( $stub->id, WC_Stripe_Hook_Categories::SUBSCRIPTIONS )
		);
		$this->assertFalse(
			$manager->are_plugin_hooks_registered( WC_Stripe_Hook_Categories::SUBSCRIPTIONS )
		);

		$stub->maybe_init_subscriptions();

		// Per-method hooks are registered for any consumer.
		$this->assertTrue(
			$manager->are_payment_method_hooks_registered( $stub->id, WC_Stripe_Hook_Categories::SUBSCRIPTIONS )
		);

		// Plugin hooks must NOT be registered when the consumer is not a UPE gateway.
		$this->assertFalse(
			$manager->are_plugin_hooks_registered( WC_Stripe_Hook_Categories::SUBSCRIPTIONS )
		);
	}

	/**
	 * All payment method-specific subscription hooks are wired to the gateway callbacks.
	 *
	 * @dataProvider provide_payment_method_object_types
	 * @param string $object_type The type of object to use for the test.
	 * @return void
	 */
	public function test_maybe_init_subscriptions_registers_all_payment_method_hooks( string $object_type ): void {
		$payment_method_id = self::TEST_PAYMENT_METHOD_ID;
		if ( 'gateway' === $object_type ) {
			$object = $this->get_upe_gateway_with_test_id( $payment_method_id );
		} else {
			$object = $this->get_payment_method_with_test_id( $payment_method_id );
		}

		$payment_method_hooks = $this->get_payment_method_hooks( $payment_method_id );

		$this->assertFalse(
			WC_Stripe_Hook_Manager::get_instance()->are_payment_method_hooks_registered(
				$payment_method_id,
				WC_Stripe_Hook_Categories::SUBSCRIPTIONS
			)
		);

		$object->maybe_init_subscriptions();

		$this->assertTrue(
			WC_Stripe_Hook_Manager::get_instance()->are_payment_method_hooks_registered(
				$payment_method_id,
				WC_Stripe_Hook_Categories::SUBSCRIPTIONS
			)
		);

		foreach ( $payment_method_hooks as $hook_name => $hook_details ) {
			$this->assertSame( $hook_details['priority'], has_action( $hook_name, [ $object, $hook_details['method'] ] ) );
		}
	}

	/**
	 * When subscriptions are disabled, `maybe_init_subscriptions()` registers nothing.
	 *
	 * @dataProvider provide_payment_method_object_types
	 * @param string $object_type The type of object to use for the test.
	 * @return void
	 */
	public function test_maybe_init_subscriptions_registers_nothing_when_subscriptions_disabled( string $object_type ): void {
		if ( 'gateway' === $object_type ) {
			$object = $this->get_upe_gateway_with_test_id( self::TEST_PAYMENT_METHOD_ID );
		} else {
			$object = $this->get_payment_method_with_test_id( self::TEST_PAYMENT_METHOD_ID );
		}

		// `WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled()` requires WC Subscriptions
		// >= 2.2.0; dropping the stubbed version below that makes it return false.
		$original_version          = WC_Subscriptions::$version;
		WC_Subscriptions::$version = '2.1.0';

		try {
			$manager = WC_Stripe_Hook_Manager::get_instance();

			$this->assertFalse(
				$manager->are_payment_method_hooks_registered( self::TEST_PAYMENT_METHOD_ID, WC_Stripe_Hook_Categories::SUBSCRIPTIONS )
			);
			$this->assertFalse(
				$manager->are_plugin_hooks_registered( WC_Stripe_Hook_Categories::SUBSCRIPTIONS )
			);

			$object->maybe_init_subscriptions();

			$payment_method_hooks = $this->get_payment_method_hooks( self::TEST_PAYMENT_METHOD_ID );

			foreach ( $payment_method_hooks as $hook_name => $hook_details ) {
				$this->assertFalse( has_action( $hook_name, [ $object, $hook_details['method'] ] ) );
			}

			$this->assertFalse(
				$manager->are_payment_method_hooks_registered( self::TEST_PAYMENT_METHOD_ID, WC_Stripe_Hook_Categories::SUBSCRIPTIONS )
			);
			$this->assertFalse(
				$manager->are_plugin_hooks_registered( WC_Stripe_Hook_Categories::SUBSCRIPTIONS )
			);
		} finally {
			WC_Subscriptions::$version = $original_version;
		}
	}

	/**
	 * The gateway, not the OC method, owns the plugin-level and payment method hooks.
	 *
	 * @return void
	 */
	public function test_gateway_owns_plugin_hooks_over_oc_payment_method() {
		$stripe_id = WC_Stripe_UPE_Payment_Gateway::ID;
		$gateway   = $this->get_upe_gateway_with_test_id( $stripe_id );

		$oc_method           = $this->getMockBuilder( WC_Stripe_UPE_Payment_Method_OC::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$oc_method->id       = $stripe_id; // OC uses the main gateway ID.
		$oc_method->supports = [];

		$payment_method_hooks = $this->get_payment_method_hooks( $stripe_id );

		$starting_counts = [];
		foreach ( $payment_method_hooks as $hook_name => $hook_details ) {
			$starting_counts[ $hook_name ] = $this->count_hook_callbacks( $hook_name, $hook_details['priority'] );
		}

		$plugin_hook_counts = [];
		foreach ( self::PLUGIN_HOOK_METHOD_MAP as $hook => $hook_details ) {
			$plugin_hook_counts[ $hook ] = $this->count_hook_callbacks( $hook, $hook_details['priority'] );
		}

		// Production ordering: the gateway registers first, then the lazily-built OC method.
		$gateway->maybe_init_subscriptions();
		$oc_method->maybe_init_subscriptions();

		foreach ( $payment_method_hooks as $hook_name => $hook_details ) {
			$this->assertSame( 1 + $starting_counts[ $hook_name ], $this->count_hook_callbacks( $hook_name, $hook_details['priority'] ) );
			$this->assertSame( $hook_details['priority'], has_action( $hook_name, [ $gateway, $hook_details['method'] ] ) );
			$this->assertFalse( has_action( $hook_name, [ $oc_method, $hook_details['method'] ] ) );
		}

		foreach ( self::PLUGIN_HOOK_METHOD_MAP as $hook => $hook_details ) {
			$this->assertSame( 1 + $plugin_hook_counts[ $hook ], $this->count_hook_callbacks( $hook, $hook_details['priority'] ) );
			$this->assertSame( $hook_details['priority'], has_action( $hook, [ $gateway, $hook_details['method'] ] ) );
			$this->assertFalse( has_action( $hook, [ $oc_method, $hook_details['method'] ] ) );
		}
	}
}
