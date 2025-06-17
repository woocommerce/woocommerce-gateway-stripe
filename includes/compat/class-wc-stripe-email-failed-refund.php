<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for the email sent to the admin and customers when a refund fails.
 *
 * @since 9.6.0
 */
abstract class WC_Stripe_Email_Failed_Refund extends WC_Email_Failed_Order {
	/**
	 * Returns the list of template parameters.
	 *
	 * @return array
	 */
	abstract public function get_template_params();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->title   = __( 'Refund failed', 'woocommerce-gateway-stripe' );
		$this->heading = __( 'Refund failed', 'woocommerce-gateway-stripe' );
		$this->subject = __( '[{site_title}] Refund failed for #{order_number}.', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Get the default e-mail subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return $this->subject;
	}

	/**
	 * Get the default e-mail heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return $this->heading;
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			$this->get_template_params(),
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			$this->get_template_params(),
			'',
			$this->template_base
		);
	}

	/**
	 * Trigger.
	 *
	 * @param int $order_id The order ID.
	 * @param WC_Order|false $order Order object.
	 */
	public function trigger( $order_id, $order = false ) {
		$this->object = $order;

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->find['order-number']    = '{order_number}';
		$this->replace['order-number'] = $this->object->get_order_number();

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Returns the refund failure reason in a human-readable form.
	 *
	 * @param object $order The order object whose refund request failed.
	 * @return string
	 */
	protected static function get_reason( $order ) {
		if ( ! is_a( $order, WC_Order::class ) ) {
			return __( 'Unknown reason', 'woocommerce-gateway-stripe' );
		}

		$refund_failure_key = $order->get_meta( '_stripe_refund_failure_reason', true );
		return self::get_refund_reason_description( $refund_failure_key );
	}

	/**
	 * Returns the description for a refund reason.
	 *
	 * @return string
	 */
	protected static function get_refund_reason_description( $refund_reason_key ) {
		switch ( $refund_reason_key ) {
			case 'charge_for_pending_refund_disputed':
				return __( 'Customer disputed the charge while the refund is pending.', 'woocommerce-gateway-stripe' );
			case 'declined':
				return __( 'Declined by financial partners.', 'woocommerce-gateway-stripe' );
			case 'expired_or_canceled_card':
				return __( 'Payment method is canceled by the customer or expired by the merchant.', 'woocommerce-gateway-stripe' );
			case 'insufficient_funds':
				return __( 'Insufficient funds and has crossed the pending refund expiry window.', 'woocommerce-gateway-stripe' );
			case 'lost_or_stolen_card':
				return __( 'Failed due to loss or theft of the original card.', 'woocommerce-gateway-stripe' );
			case 'merchant_request':
				return __( 'Failed upon the merchant’s request.', 'woocommerce-gateway-stripe' );
			case 'unknown':
			default:
				return __( 'Unknown reason', 'woocommerce-gateway-stripe' );
		}
	}
}
