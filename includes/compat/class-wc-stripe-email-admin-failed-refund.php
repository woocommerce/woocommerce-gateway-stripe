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

		$this->id          = 'failed_refund';
		$this->description = __( 'Refund request failure emails are sent to chosen recipient(s) when an attempt to process refund fails.', 'woocommerce-gateway-stripe' );

		$this->template_html  = 'emails/failed-refund.php';
		$this->template_plain = 'emails/plain/failed-refund.php';
		$this->template_base  = plugin_dir_path( WC_STRIPE_MAIN_FILE ) . 'templates/';

		WC_Email::__construct();

		// Set after calling the parent constructor, so it is not override.
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
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
				'reason'        => $this->get_reason( $this->object ),
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
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
				'reason'        => self::get_reason( $this->object ),
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
				'plain_text'    => true,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}
}
