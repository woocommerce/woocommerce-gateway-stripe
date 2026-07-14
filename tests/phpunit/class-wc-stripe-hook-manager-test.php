<?php

/**
 * Unit tests for WC_Stripe_Hook_Manager.
 *
 * @package WooCommerce/Stripe/WC_Stripe_Hook_Manager
 */
class WC_Stripe_Hook_Manager_Test extends WP_UnitTestCase {

	/**
	 * A second arbitrary hook category used to assert category isolation.
	 *
	 * WC_Stripe_Hook_Categories only ships a single constant today, so we use a
	 * raw string here to verify the manager keys state per category.
	 *
	 * @var string
	 */
	const OTHER_CATEGORY = 'pre_orders';

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
	 * Resets the private static singleton so each test starts from a clean slate.
	 *
	 * @return void
	 */
	private function reset_hook_manager_singleton() {
		$reflection = new ReflectionProperty( WC_Stripe_Hook_Manager::class, 'instance' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, null );
	}

	/**
	 * `get_instance()` always returns the same instance.
	 *
	 * @return void
	 */
	public function test_get_instance_returns_singleton() {
		$instance = WC_Stripe_Hook_Manager::get_instance();

		$this->assertInstanceOf( WC_Stripe_Hook_Manager::class, $instance );
		$this->assertSame( $instance, WC_Stripe_Hook_Manager::get_instance() );
	}

	/**
	 * Plugin hooks are not registered until they are explicitly registered.
	 *
	 * @return void
	 */
	public function test_plugin_hooks_are_not_registered_by_default() {
		$manager = WC_Stripe_Hook_Manager::get_instance();

		$this->assertFalse( $manager->are_plugin_hooks_registered( WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) );
	}

	/**
	 * Registering plugin hooks marks them as registered and is idempotent.
	 *
	 * @return void
	 */
	public function test_register_plugin_hooks_is_idempotent() {
		$manager  = WC_Stripe_Hook_Manager::get_instance();
		$category = WC_Stripe_Hook_Categories::SUBSCRIPTIONS;

		// First registration succeeds.
		$this->assertTrue( $manager->register_plugin_hooks( $category ) );
		$this->assertTrue( $manager->are_plugin_hooks_registered( $category ) );

		// Subsequent registrations are no-ops.
		$this->assertFalse( $manager->register_plugin_hooks( $category ) );
		$this->assertTrue( $manager->are_plugin_hooks_registered( $category ) );
	}

	/**
	 * Plugin hook state is tracked independently per category.
	 *
	 * @return void
	 */
	public function test_register_plugin_hooks_is_scoped_per_category() {
		$manager = WC_Stripe_Hook_Manager::get_instance();

		$manager->register_plugin_hooks( WC_Stripe_Hook_Categories::SUBSCRIPTIONS );

		$this->assertTrue( $manager->are_plugin_hooks_registered( WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) );
		$this->assertFalse( $manager->are_plugin_hooks_registered( self::OTHER_CATEGORY ) );
	}

	/**
	 * An empty plugin hook category is rejected.
	 *
	 * @return void
	 */
	public function test_plugin_hooks_reject_empty_category() {
		$manager = WC_Stripe_Hook_Manager::get_instance();

		$this->assertFalse( $manager->register_plugin_hooks( '' ) );
		$this->assertFalse( $manager->are_plugin_hooks_registered( '' ) );
	}

	/**
	 * Tests for {@see WC_Stripe_Hook_Manager::is_valid_payment_method_id()}.
	 *
	 * @param string $payment_method_id The payment method ID to check.
	 * @param bool   $expected          The expected result.
	 * @dataProvider provide_payment_method_ids
	 */
	public function test_is_valid_payment_method_id( string $payment_method_id, bool $expected ): void {
		$manager = WC_Stripe_Hook_Manager::get_instance();

		$this->assertEquals( $expected, $manager->is_valid_payment_method_id( $payment_method_id ) );
	}

	/**
	 * Data provider for {@see test_is_valid_payment_method_id()}.
	 *
	 * @return array
	 */
	public function provide_payment_method_ids(): array {
		return [
			'stripe'      => [ 'stripe', true ],
			'stripe_sepa' => [ 'stripe_sepa', true ],
			'stripe_card' => [ 'stripe_card', true ],
			'other'       => [ 'other', false ],
			'stripebad'   => [ 'stripebad', false ],
			'empty'       => [ '', false ],
			'0'           => [ '0', false ],
		];
	}

	/**
	 * Payment method hooks are not registered until they are explicitly registered.
	 *
	 * @return void
	 */
	public function test_payment_method_hooks_are_not_registered_by_default() {
		$manager = WC_Stripe_Hook_Manager::get_instance();

		$this->assertFalse( $manager->are_payment_method_hooks_registered( 'stripe', WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) );
	}

	/**
	 * Registering payment method hooks marks them as registered and is idempotent.
	 *
	 * @return void
	 */
	public function test_register_payment_method_hooks_is_idempotent() {
		$manager  = WC_Stripe_Hook_Manager::get_instance();
		$category = WC_Stripe_Hook_Categories::SUBSCRIPTIONS;

		// First registration succeeds.
		$this->assertTrue( $manager->register_payment_method_hooks( 'stripe', $category ) );
		$this->assertTrue( $manager->are_payment_method_hooks_registered( 'stripe', $category ) );

		// Subsequent registrations are no-ops.
		$this->assertFalse( $manager->register_payment_method_hooks( 'stripe', $category ) );
		$this->assertTrue( $manager->are_payment_method_hooks_registered( 'stripe', $category ) );
	}

	/**
	 * Payment method hook state is tracked independently per payment method.
	 *
	 * @return void
	 */
	public function test_register_payment_method_hooks_is_scoped_per_payment_method() {
		$manager  = WC_Stripe_Hook_Manager::get_instance();
		$category = WC_Stripe_Hook_Categories::SUBSCRIPTIONS;

		$manager->register_payment_method_hooks( 'stripe', $category );

		$this->assertTrue( $manager->are_payment_method_hooks_registered( 'stripe', $category ) );
		$this->assertFalse( $manager->are_payment_method_hooks_registered( 'stripe_sepa', $category ) );

		// Registering for the second method succeeds and does not affect the first.
		$this->assertTrue( $manager->register_payment_method_hooks( 'stripe_sepa', $category ) );
		$this->assertTrue( $manager->are_payment_method_hooks_registered( 'stripe', $category ) );
		$this->assertTrue( $manager->are_payment_method_hooks_registered( 'stripe_sepa', $category ) );
	}

	/**
	 * Payment method hook state is tracked independently per category.
	 *
	 * @return void
	 */
	public function test_register_payment_method_hooks_is_scoped_per_category() {
		$manager = WC_Stripe_Hook_Manager::get_instance();

		$manager->register_payment_method_hooks( 'stripe', WC_Stripe_Hook_Categories::SUBSCRIPTIONS );

		$this->assertTrue( $manager->are_payment_method_hooks_registered( 'stripe', WC_Stripe_Hook_Categories::SUBSCRIPTIONS ) );
		$this->assertFalse( $manager->are_payment_method_hooks_registered( 'stripe', self::OTHER_CATEGORY ) );
	}

	/**
	 * Plugin hook registration and payment method hook registration do not bleed into each other.
	 *
	 * @return void
	 */
	public function test_plugin_and_payment_method_hooks_are_independent() {
		$manager  = WC_Stripe_Hook_Manager::get_instance();
		$category = WC_Stripe_Hook_Categories::SUBSCRIPTIONS;

		$manager->register_plugin_hooks( $category );

		// Registering plugin hooks must not imply the payment method hooks are registered.
		$this->assertTrue( $manager->are_plugin_hooks_registered( $category ) );
		$this->assertFalse( $manager->are_payment_method_hooks_registered( 'stripe', $category ) );
	}

	/**
	 * Empty payment method IDs or categories are rejected for both the check and the registration.
	 *
	 * @dataProvider provide_empty_payment_method_arguments
	 *
	 * @param string $payment_method_id The payment method ID under test.
	 * @param string $hook_category     The hook category under test.
	 * @return void
	 */
	public function test_payment_method_hooks_reject_empty_arguments( string $payment_method_id, string $hook_category ) {
		$manager = WC_Stripe_Hook_Manager::get_instance();

		$this->assertFalse( $manager->register_payment_method_hooks( $payment_method_id, $hook_category ) );
		$this->assertFalse( $manager->are_payment_method_hooks_registered( $payment_method_id, $hook_category ) );
	}

	/**
	 * Data provider for `test_payment_method_hooks_reject_empty_arguments`.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function provide_empty_payment_method_arguments(): array {
		return [
			'empty payment method id' => [ '', WC_Stripe_Hook_Categories::SUBSCRIPTIONS ],
			'empty hook category'     => [ 'stripe', '' ],
			'both empty'              => [ '', '' ],
		];
	}
}
