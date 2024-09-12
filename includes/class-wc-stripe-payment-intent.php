<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Payment_Intent
 *
 * Represents a Stripe PaymentIntent object.
 */
class WC_Stripe_Payment_Intent {
	/**
	 * @var string The client secret.
	 */
	private string $client_secret;

	/**
	 * @var string The payment intent status.
	 */
	private string $status;

	/**
	 * @var array The payment method types.
	 */
	private array $payment_method_types;

	/**
	 * @var object The next action object.
	 */
	private object $next_action;

	/**
	 * @var object The charges object.
	 */
	private object $latest_charge;

	/**
	 * Constructor
	 *
	 * @param $payment_intent_response object The payment intent response object.
	 */
	public function __construct( $payment_intent_response ) {
		$this->client_secret        = $payment_intent_response->client_secret;
		$this->status               = $payment_intent_response->status;
		$this->payment_method_types = $payment_intent_response->payment_method_types;

		if ( isset( $payment_intent_response->next_action ) ) {
			$this->next_action = $payment_intent_response->next_action;
		}

		if ( ! empty( $payment_intent_response->charges->data ) ) {
			$this->latest_charge = end( $payment_intent_response->charges->data );
		} elseif ( ! empty( $payment_intent_response->latest_charge ) ) {
			$this->latest_charge = $this->get_charge_object( $payment_intent_response->latest_charge );
		}
	}

	/**
	 * @return string The client secret.
	 */
	public function get_client_secret() {
		return $this->client_secret;
	}

	/**
	 * @return string The payment intent status.
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * @return string The next action type.
	 */
	public function get_next_action_type_url() {
		return isset( $this->next_action->type )
			&& in_array( $this->next_action->type, [ 'redirect_to_url', 'alipay_handle_redirect' ], true )
			&& ! empty( $this->next_action->{$this->next_action->type}->url ) ? $this->next_action->{$this->next_action->type}->url : '';
	}

	/**
	 * @return bool Whether the payment methods contain a voucher or wallet payment method.
	 */
	public function contains_voucher_or_wallet_payment() {
		return $this->contains_voucher_payment() || $this->contains_wallet_payment();
	}

	/**
	 * @return bool Whether the payment methods contain a voucher payment method.
	 */
	public function contains_voucher_payment() {
		return count( array_intersect( [ 'boleto', 'oxxo', 'multibanco' ], $this->payment_method_types ) ) !== 0;
	}

	/**
	 * @return bool Whether the payment methods contain a wallet payment method.
	 */
	public function contains_wallet_payment() {
		return count( array_intersect( [ 'wechat_pay', 'cashapp' ], $this->payment_method_types ) ) !== 0;
	}

	/**
	 * Get latest charge object from payment intent.
	 *
	 * Since API version 2022-11-15, the `charges` property was replaced with `latest_charge`.
	 * We can remove this method once we drop support for API versions prior to 2022-11-15.
	 *
	 * @since 7.0.2
	 *
	 * @return string|object
	 */
	public function get_latest_charge() {
		return $this->latest_charge;
	}

	/**
	 * Get charge object by charge ID.
	 *
	 * @since 7.0.2
	 * @param string $charge_id The charge ID to get charge object for.
	 * @param array  $params    The parameters to pass to the request.
	 *
	 * @throws WC_Stripe_Exception Error while retrieving charge object.
	 * @return string|object
	 */
	public function get_charge_object( $charge_id = '', $params = [] ) {
		if ( empty( $charge_id ) ) {
			return '';
		}

		$charge_object = WC_Stripe_API::request( $params, 'charges/' . $charge_id, 'GET' );

		if ( ! empty( $charge_object->error ) ) {
			throw new WC_Stripe_Exception( print_r( $charge_object, true ), $charge_object->error->message );
		}

		return $charge_object;
	}
}
