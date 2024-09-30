<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Payment_Intent
 */
class WC_Stripe_Payment_Intent extends \Stripe\PaymentIntent {
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
	 * Retrieves a payment intent and returns it as a WC_Stripe_Payment_Intent object.
	 *
	 * @param string $id The ID of the payment intent to retrieve.
	 * @param array  $opts Optional parameters to pass to the request.
	 * @return WC_Stripe_Payment_Intent The payment intent object.
	 * @throws \Stripe\Exception\ApiErrorException If the request fails.
	 */
	public static function retrieve( $id, $opts = null ) {
		$response = parent::retrieve( $id, $opts );
		$obj      = new self();
		foreach ( get_object_vars( $response ) as $key => $name ) {
			$obj->$key = $name;
		}
		return $obj;
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
		return $this->contains_wallet_method() || $this->contains_voucher_method();
	}

	/**
	 * Checks whether the payment intent contains a wallet payment method.
	 *
	 * @return bool Whether the payment intent contains a wallet payment method.
	 */
	public function contains_wallet_method() {
		return isset( $this->payment_method_types ) && count( array_intersect( WC_Stripe_Payment_Methods::WALLET_PAYMENT_METHODS, $this->payment_method_types ) ) !== 0;
	}

	/**
	 * Checks whether the payment intent contains a voucher payment method.
	 *
	 * @return bool Whether the payment intent contains a voucher payment method.
	 */
	public function contains_voucher_method() {
		return isset( $this->payment_method_types ) && count( array_intersect( WC_Stripe_Payment_Methods::VOUCHER_PAYMENT_METHODS, $this->payment_method_types ) ) !== 0;
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
}
