<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Stripe_Intent_Status
 *
 * For a documentation on the possible intent statuses, please refer to the following link:
 * https://docs.stripe.com/api/payment_intents/object#payment_intent_object-status
 */
class WC_Stripe_Intent_Status {
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

	/**
	 * Grouping of statuses that require confirmation or action.
	 */
	const REQUIRES_CONFIRMATION_OR_ACTION_STATUSES = [ self::REQUIRES_CONFIRMATION, self::REQUIRES_ACTION ];

	/**
	 * Grouping of statuses that are considered successful when setting up intents.
	 */
	const SUCCESSFUL_SETUP_INTENT_STATUSES = [ self::SUCCEEDED, self::PROCESSING, self::REQUIRES_ACTION, self::REQUIRES_CONFIRMATION ];
}
