<?php
/**
 * Object fixture used to corrupt integer-typed options in tests.
 *
 * @package WooCommerce_Stripe/Tests/Helpers
 */

/**
 * An object that stringifies to a run of digits.
 *
 * This is the nastiest shape a corrupted integer option can take: a `(string)` cast succeeds and
 * looks like a valid integer, so a digit check alone waves it through — and the subsequent `(int)`
 * cast is then a fatal, because objects cannot be converted to int. Only a type check before the
 * cast rejects it.
 */
class WC_Stripe_Stringable_Option_Value {

	/**
	 * Digits this object stringifies to.
	 *
	 * @var string
	 */
	public $digits;

	/**
	 * @param string $digits Digits to stringify to.
	 */
	public function __construct( string $digits = '1234567890' ) {
		$this->digits = $digits;
	}

	/**
	 * @return string
	 */
	public function __toString(): string {
		return $this->digits;
	}
}
