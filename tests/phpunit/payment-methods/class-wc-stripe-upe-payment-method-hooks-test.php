<?php
/**
 * Tests that UPE payment method constructor hooks register only once.
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_UPE_Payment_Method_Hooks_Test
 */
class WC_Stripe_UPE_Payment_Method_Hooks_Test extends WP_UnitTestCase {

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
	 * Resets the hook manager singleton so each test's first instantiation
	 * actually exercises the registration path: WP_UnitTestCase restores
	 * `$wp_filter` between tests but not the singleton's registered state.
	 *
	 * @return void
	 */
	private function reset_hook_manager_singleton() {
		$reflection = new ReflectionProperty( WC_Stripe_Hook_Manager::class, 'instance' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, null );
	}

	/**
	 * Data provider mapping each hook-registering payment method class to a
	 * hook only its constructor registers.
	 *
	 * @return array<string, array{class: string, hook: string}>
	 */
	public function payment_method_hook_provider(): array {
		return [
			'multibanco email instructions' => [
				'class' => WC_Stripe_UPE_Payment_Method_Multibanco::class,
				'hook'  => 'woocommerce_email_before_order_table',
			],
			'oxxo processing statuses'      => [
				'class' => WC_Stripe_UPE_Payment_Method_Oxxo::class,
				'hook'  => 'wc_stripe_allowed_payment_processing_statuses',
			],
			'boleto processing statuses'    => [
				'class' => WC_Stripe_UPE_Payment_Method_Boleto::class,
				'hook'  => 'wc_stripe_allowed_payment_processing_statuses',
			],
			'link gateway title'            => [
				'class' => WC_Stripe_UPE_Payment_Method_Link::class,
				'hook'  => 'woocommerce_gateway_title',
			],
			'cash app thankyou text'        => [
				'class' => WC_Stripe_UPE_Payment_Method_Cash_App_Pay::class,
				'hook'  => 'woocommerce_thankyou_order_received_text',
			],
		];
	}

	/**
	 * Test that instantiating a payment method repeatedly does not stack
	 * duplicate hook callbacks — only the first instance registers.
	 *
	 * @dataProvider payment_method_hook_provider
	 */
	public function test_repeated_instantiation_does_not_duplicate_hooks( string $class, string $hook ) {
		new $class();
		$after_first = $this->count_hook_callbacks( $hook );

		new $class();
		new $class();

		$this->assertSame( $after_first, $this->count_hook_callbacks( $hook ) );
	}

	/**
	 * Test that the factory used on the payment path does not stack duplicate
	 * hook callbacks either.
	 */
	public function test_get_payment_method_instance_does_not_duplicate_hooks() {
		WC_Stripe_UPE_Payment_Gateway::get_payment_method_instance( WC_Stripe_Payment_Methods::MULTIBANCO );
		$after_first = $this->count_hook_callbacks( 'woocommerce_email_before_order_table' );

		WC_Stripe_UPE_Payment_Gateway::get_payment_method_instance( WC_Stripe_Payment_Methods::MULTIBANCO );

		$this->assertSame( $after_first, $this->count_hook_callbacks( 'woocommerce_email_before_order_table' ) );
	}

	/**
	 * Test that a subclass whose id the hook manager can't track (no `stripe`
	 * prefix) still registers its hooks — the guard fails open, per instance.
	 */
	public function test_foreign_id_subclass_registers_hooks_per_instance() {
		$make = function () {
			return new class() extends WC_Stripe_UPE_Payment_Method_Multibanco {
				public function __construct() {
					parent::__construct();
					$this->id = 'foreign_multibanco';
					$this->register_instance_hooks_once(
						function () {
							// A fresh closure per instance: identical string
							// callbacks would collapse into one wp_filter slot.
							add_action(
								'wc_stripe_test_foreign_hook',
								static function () {}
							);
						}
					);
				}
			};
		};

		$make();
		$this->assertSame( 1, $this->count_hook_callbacks( 'wc_stripe_test_foreign_hook' ) );

		// No dedup possible for untracked ids: each instance registers.
		$make();
		$this->assertSame( 2, $this->count_hook_callbacks( 'wc_stripe_test_foreign_hook' ) );
	}

	/**
	 * Helper: count callbacks registered on `$hook` across all priorities.
	 *
	 * @param string $hook Hook name.
	 * @return int
	 */
	private function count_hook_callbacks( string $hook ): int {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $wp_filter[ $hook ]->callbacks as $priority_callbacks ) {
			$count += count( $priority_callbacks );
		}

		return $count;
	}
}
