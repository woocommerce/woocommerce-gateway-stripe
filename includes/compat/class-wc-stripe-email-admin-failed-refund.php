<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An email sent to the admin when a refund fails.
 *
 * @since 9.6.0
 */
class WC_Stripe_Email_Admin_Failed_Refund extends WC_Stripe_Email_Failed_Refund {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->id          = 'wc_stripe_failed_refund_admin';
		$this->description = __( 'Refund failure emails are sent to the admin when an attempt to process a refund fails.', 'woocommerce-gateway-stripe' );

		$this->template_html  = 'emails/failed-refund-admin.php';
		$this->template_plain = 'emails/plain/failed-refund-admin.php';
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
			'sent_to_admin' => true,
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
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		parent::trigger( $order_id, $order );
	}
}
