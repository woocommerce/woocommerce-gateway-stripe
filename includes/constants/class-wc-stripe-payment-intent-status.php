<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Stripe_Payment_Intent_Status
 */
class WC_Stripe_Payment_Intent_Status {
	/**
	 * Payment intent status that indicates the payment was canceled.
	 *
	 * @var string
	 */
	const CANCELED = 'canceled';

	/**
	 * Payment intent status that indicates the payment is processing.
	 *
	 * @var string
	 */
	const PROCESSING = 'processing';

	/**
	 * Payment intent status that indicates the payment requires confirmation.
	 *
	 * @var string
	 */
	const REQUIRES_CONFIRMATION = 'requires_confirmation';

	/**
	 * Payment intent status that indicates the payment requires action.
	 *
	 * @var string
	 */
	const REQUIRES_ACTION = 'requires_action';

	/**
	 * Payment intent status that indicates the payment requires capture.
	 *
	 * @var string
	 */
	const REQUIRES_CAPTURE = 'requires_capture';

	/**
	 * Payment intent status that indicates the payment requires payment method.
	 *
	 * @var string
	 */
	const REQUIRES_PAYMENT_METHOD = 'requires_payment_method';

	/**
	 * Payment intent status that indicates the payment was successful.
	 *
	 * @var string
	 */
	const SUCCEEDED = 'succeeded';

	/**
	 * Stripe intents that are treated as successfully created.
	 *
	 * @var array
	 */
	const SUCCESSFUL_STATUSES = [ self::SUCCEEDED, self::REQUIRES_CAPTURE, self::PROCESSING ];
}
