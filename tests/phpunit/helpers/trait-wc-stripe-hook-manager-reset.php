<?php
/**
 * Resets the WC_Stripe_Hook_Manager singleton around each test.
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * WP_UnitTestCase restores `$wp_filter` between tests but not the hook
 * manager's registered state, so without this reset the first test to
 * register a payment method id short-circuits registration for every
 * later test in the process.
 */
trait WC_Stripe_Hook_Manager_Reset_Trait {

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
}
