<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Payment_Intent
 */
class WC_Stripe_Payment_Intent {
	/**
	 * The status of the payment intent.
	 *
	 * @var string
	 */
	const STATUS_REQUIRES_PAYMENT_METHOD = 'requires_payment_method';

	/**
	 * The status of the payment intent.
	 *
	 * @var string
	 */
	const STATUS_REQUIRES_CONFIRMATION = 'requires_confirmation';

	/**
	 * The status of the payment intent.
	 *
	 * @var string
	 */
	const STATUS_REQUIRES_ACTION = 'requires_action';

	/**
	 * The status of the payment intent.
	 *
	 * @var string
	 */
	const STATUS_PROCESSING = 'processing';

	/**
	 * The status of the payment intent.
	 *
	 * @var string
	 */
	const STATUS_REQUIRES_CAPTURE = 'requires_capture';

	/**
	 * The status of the payment intent.
	 *
	 * @var string
	 */
	const STATUS_CANCELED = 'canceled';

	/**
	 * The status of the payment intent.
	 *
	 * @var string
	 */
	const STATUS_SUCCEEDED = 'succeeded';

	/**
	 * The next action type of the payment intent.
	 *
	 * @var string
	 */
	const NEXT_ACTION_TYPE_REDIRECT_TO_URL = 'redirect_to_url';

	/**
	 * The next action type of the payment intent.
	 *
	 * @var string
	 */
	const NEXT_ACTION_TYPE_ALIPAY_HANDLE_REDIRECT = 'alipay_handle_redirect';

	/**
	 * Class constructor.
	 *
	 * @param $data array The payment intent data.
	 */
	public function __construct( $data ) {
		foreach ( $data as $key => $value ) {
			$this->{$key} = $value;
		}
	}

	/**
	 * Creates a new payment intent object from the Stripe API response.
	 *
	 * @param $response object The response from the Stripe API.
	 * @return WC_Stripe_Payment_Intent The payment intent object.
	 */
	public static function from_response( $response ) {
		return new self( (array) $response );
	}

	/**
	 * Converts the payment intent data to an object.
	 *
	 * @return object The payment intent data as an object.
	 */
	public function to_object() {
		return WC_Stripe_Utils::array_to_object( (array) $this );
	}

	/**
	 * Checks whether the payment intent requires a confirmation or action.
	 *
	 * @return bool Whether the payment intent requires a confirmation or action.
	 */
	public function requires_confirmation_or_action() {
		return isset( $this->status ) && in_array( $this->status, [ self::STATUS_REQUIRES_CONFIRMATION, self::STATUS_REQUIRES_ACTION ], true );
	}

	/**
	 * Checks whether the payment intent contains a wallet or voucher payment method.
	 *
	 * @return bool Whether the payment intent contains a wallet or voucher payment method.
	 */
	public function contains_wallet_or_voucher_method() {
		$wallet_and_voucher_methods = array_merge( WC_Stripe_Payment_Methods::VOUCHER_PAYMENT_METHODS, WC_Stripe_Payment_Methods::WALLET_PAYMENT_METHODS );
		return isset( $this->payment_method_types ) && count( array_intersect( $wallet_and_voucher_methods, $this->payment_method_types ) ) !== 0;
	}

	/**
	 * Checks whether the payment intent contains a redirect next action.
	 *
	 * @return bool Whether the payment intent contains a redirect next action.
	 */
	public function contains_redirect_next_action() {
		return isset( $this->get_next_action->type )
			&& in_array( $this->get_next_action->type, [ self::NEXT_ACTION_TYPE_REDIRECT_TO_URL, self::NEXT_ACTION_TYPE_ALIPAY_HANDLE_REDIRECT ], true )
			&& ! empty( $this->get_next_action->{$this->get_next_action->type}->url );
	}

	/**
	 * Checks whether the payment intent is successful.
	 *
	 * @return bool Whether the payment intent is successful.
	 */
	public function is_successful() {
		return isset( $this->status ) && in_array(
			$this->status,
			[
				self::STATUS_SUCCEEDED,
				self::STATUS_PROCESSING,
				self::STATUS_REQUIRES_CAPTURE,
			],
			true
		);
	}

	/**
	 * Returns the latest charge.
	 *
	 * @return object|null The latest charge.
	 * @throws WC_Stripe_Exception If the charge object cannot be created.
	 */
	public function get_latest_charge() {
		if ( ! empty( $this->charges->data ) ) {
			return end( $this->charges->data );
		}

		if ( ! empty( $this->latest_charge ) ) {
			return WC_Stripe_Payment_Gateway::get_charge_object( $this->latest_charge );
		}

		return null;
	}
}
