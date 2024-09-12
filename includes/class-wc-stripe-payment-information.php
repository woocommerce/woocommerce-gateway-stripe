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
	 * @var string The payment method.
	 */
	private $payment_method;

	/**
	 * @var object The payment method details.
	 */
	private $payment_method_details;

	/**
	 * @var string The selected payment type.
	 */
	private $selected_payment_type;

	/**
	 * @var string The customer ID.
	 */
	private $customer;

	/**
	 * @var boolean Whether the customer is using a saved payment method.
	 */
	private $is_using_saved_payment_method;

	/**
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

		if ( $payment_information['token'] ) {
			$this->token = $payment_information['token'];
		} else {
			$this->token = false;
		}
	}

	/**
	 * @return string The selected payment method type.
	 */
	public function get_selected_payment_type() {
		return $this->selected_payment_type;
	}

	/**
	 * @return string The payment method ID.
	 */
	public function get_payment_method() {
		return $this->payment_method;
	}

	/**
	 * @return object The payment method details.
	 */
	public function get_payment_method_details() {
		return $this->payment_method_details;
	}

	/**
	 * @return string The customer ID.
	 */
	public function get_customer() {
		return $this->customer;
	}

	/**
	 * @return WC_Payment_Token|false The payment token.
	 */
	public function get_token() {
		return $this->token;
	}
}

