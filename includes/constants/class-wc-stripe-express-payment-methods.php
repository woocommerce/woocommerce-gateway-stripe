<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Stripe_Express_Payment_Methods
 */
class WC_Stripe_Express_Payment_Methods {
	/**
	 * Amazon Pay payment method.
	 *
	 * @string
	 */
	const AMAZON_PAY = 'amazon_pay';

	/**
	 * Google Pay payment method.
	 *
	 * @string
	 */
	const GOOGLE_PAY = 'google_pay';

	/**
	 * Apple Pay payment method.
	 *
	 * @string
	 */
	const APPLE_PAY = 'apple_pay';

	/**
	 * Link payment method.
	 *
	 * @string
	 */
	const LINK = 'link';
}
