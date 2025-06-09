<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Failed Refund Notification (shopper)
 *
 * @since 9.6.0
 */
class WC_Stripe_Email_Customer_Failed_Refund extends WC_Email_Failed_Order {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'failed_refund_customer';
		$this->title          = __( 'Refund request failed', 'woocommerce-gateway-stripe' );
		$this->description    = __( 'Sent to a customer when a refund request fails. The email contains the original order information.', 'woocommerce-gateway-stripe' );
		$this->customer_email = true;

		$this->template_html  = 'emails/failed-refund-customer.php';
		$this->template_plain = 'emails/plain/failed-refund-customer.php';
		$this->template_base  = plugin_dir_path( WC_STRIPE_MAIN_FILE ) . 'templates/';

		parent::__construct();
	}

	/**
	 * Trigger.
	 *
	 * @param int $order_id The order ID.
	 * @param WC_Order|false $order Order object.
	 */
	public function trigger( $order_id, $order = false ) {
		$this->object = $order;

		$this->find['reason']    = '{reason}';
		$this->replace['reason'] = '';

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			],
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
			[
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}
