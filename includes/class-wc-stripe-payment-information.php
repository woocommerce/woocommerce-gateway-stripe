<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Payment_Information
 *
 * Represents a payment information object.
 */
class WC_Stripe_Payment_Information {
	/**
	 * The payment method.
	 *
	 * @var string The payment method.
	 */
	private $payment_method;

	/**
	 * The payment method details.
	 *
	 * @var object The payment method details.
	 */
	private $payment_method_details;

	/**
	 * The selected payment type.
	 *
	 * @var string The selected payment type.
	 */
	private $selected_payment_type;

	/**
	 * The customer ID.
	 *
	 * @var string The customer ID.
	 */
	private $customer;

	/**
	 * Whether the customer is using a saved payment method.
	 *
	 * @var boolean Whether the customer is using a saved payment method.
	 */
	private $is_using_saved_payment_method;

	/**
	 * Whether to save the payment method to the store.
	 *
	 * @var boolean Whether to save the payment method to the store.
	 */
	private $save_payment_method_to_store;

	/**
	 * The payment token.
	 *
	 * @var WC_Payment_Token|false
	 */
	private $token;


	/**
	 * Constructor
	 *
	 * @param $payment_information array The payment information object.
	 */
	public function __construct( $payment_information ) {
		$this->payment_method = $payment_information['payment_method'];
		$this->payment_method_details = $payment_information['payment_method_details'];
		$this->selected_payment_type = $payment_information['selected_payment_type'];
		$this->customer = $payment_information['customer'];
		$this->is_using_saved_payment_method = $payment_information['is_using_saved_payment_method'];
		$this->save_payment_method_to_store = $payment_information['save_payment_method_to_store'];

		if ( $payment_information['token'] ) {
			$this->token = $payment_information['token'];
		} else {
			$this->token = false;
		}
	}

	/**
	 * The selected payment method type.
	 *
	 * @return string The selected payment method type.
	 */
	public function get_selected_payment_type() {
		return $this->selected_payment_type;
	}

	/**
	 * The payment method ID.
	 *
	 * @return string The payment method ID.
	 */
	public function get_payment_method() {
		return $this->payment_method;
	}

	/**
	 * The payment method details.
	 *
	 * @return object The payment method details.
	 */
	public function get_payment_method_details() {
		return $this->payment_method_details;
	}

	/**
	 * The customer ID.
	 *
	 * @return string The customer ID.
	 */
	public function get_customer() {
		return $this->customer;
	}

	/**
	 * The payment token.
	 *
	 * @return WC_Payment_Token|false The payment token.
	 */
	public function get_token() {
		return $this->token;
	}

	/**
	 * Whether to save the payment method to the store.
	 *
	 * @return bool Whether to save the payment method to the store.
	 */
	public function save_payment_method_to_store() {
		return $this->save_payment_method_to_store;
	}

	/**
	 * Whether the customer is using a saved payment method.
	 *
	 * @return bool Whether the customer is using a saved payment method.
	 */
	public function is_using_saved_payment_method() {
		return $this->is_using_saved_payment_method;
	}
}

