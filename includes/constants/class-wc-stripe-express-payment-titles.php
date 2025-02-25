<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Stripe_Express_Payment_Titles
 */
class WC_Stripe_Express_Payment_Titles {
	/**
	 * Default title for Google Pay.
	 *
	 * @string
	 */
	const GOOGLE_PAY = 'Google Pay';

	/**
	 * Default title for Apple Pay.
	 *
	 * @string
	 */
	const APPLE_PAY = 'Apple Pay';

	/**
	 * Default title for Amazon Pay.
	 *
	 * @string
	 */
	const AMAZON_PAY = 'Amazon Pay';

	/**
	 * Default title for Link.
	 *
	 * @string
	 */
	const LINK = 'Link';

	/**
	 * Default title for Bacs Direct Debit.
	 *
	 * @string
	 */
	const BACS_DEBIT = 'Bacs Direct Debit';
}
