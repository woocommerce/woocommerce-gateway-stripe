<?php
/**
 * Test double for WC_Stripe_Key_Constants.
 *
 * @package WooCommerce/Stripe/Tests
 */

/**
 * Exposes the constant-reading seam so tests can vary the "defined" constants
 * per case; real constants would leak across the process.
 */
class WC_Stripe_Key_Constants_Fake extends WC_Stripe_Key_Constants {
	/**
	 * The constants the fake reports as defined, name => value.
	 *
	 * @var array<string, mixed>
	 */
	public $constants = [];

	/**
	 * Reads a constant from the fake map.
	 *
	 * @param string $constant The constant name.
	 * @return mixed|null Null when the fake does not define it.
	 */
	protected function get_constant_value( string $constant ) {
		return $this->constants[ $constant ] ?? null;
	}
}
