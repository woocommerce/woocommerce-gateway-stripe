<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Failed Refund Notification (shopper)
 *
 * @since 9.6.0
 */
class WC_Stripe_Email_Customer_Failed_Refund extends WC_Stripe_Email_Failed_Refund {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->id          = 'wc_stripe_failed_refund_customer';
		$this->description = __( 'Sent to a customer when a refund fails or is cancelled. The email contains the original order information.', 'woocommerce-gateway-stripe' );

		$this->customer_email = true;

		$this->template_html  = 'emails/failed-refund-customer.php';
		$this->template_plain = 'emails/plain/failed-refund-customer.php';
		$this->template_base  = plugin_dir_path( WC_STRIPE_MAIN_FILE ) . 'templates/';

		WC_Email::__construct();
	}

	/**
	 * Returns the list of template parameters.
	 *
	 * @inheritDoc
	 */
	public function get_template_params() {
		return [
			'order'         => $this->object,
			'reason'        => $this->get_reason( $this->object ),
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this,
		];
	}

	/**
	 * Trigger.
	 *
	 * @inheritDoc
	 */
	public function trigger( $order_id, $order = false ) {
		// Set before calling the parent trigger, so it is not override.
		$this->recipient = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
		parent::trigger( $order_id, $order );
	}
}
