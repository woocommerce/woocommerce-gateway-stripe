<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base for Failed Renewal/Pre-Order Authentication Notifications.
 *
 * @extends WC_Email
 */
abstract class WC_Stripe_Email_Failed_Authentication extends WC_Email {
	/**
	 * An instance of the email, which would normally be sent after a failed payment.
	 *
	 * @var WC_Email
	 */
	public $original_email;

	/**
	 * Generates the HTML for the email while keeping the `template_base` in mind.
	 *
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'order'             => $this->object,
				'email_heading'     => $this->get_heading(),
				'sent_to_admin'     => false,
				'plain_text'        => false,
				'authorization_url' => $this->get_authorization_url( $this->object ),
				'email'             => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Generates the plain text for the email while keeping the `template_base` in mind.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'order'             => $this->object,
				'email_heading'     => $this->get_heading(),
				'sent_to_admin'     => false,
				'plain_text'        => true,
				'authorization_url' => $this->get_authorization_url( $this->object ),
				'email'             => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Generates the URL, which will be used to authenticate the payment.
	 *
	 * @param WC_Order $order The order whose payment needs authentication.
	 * @return string
	 */
	public function get_authorization_url( $order ) {
		return add_query_arg( 'wc-stripe-confirmation', 1, $order->get_checkout_payment_url( false ) );
	}

	/**
	 * Uses specific fields from `WC_Email_Customer_Invoice` for this email.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$base_fields = $this->form_fields;

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => _x( 'Enable/Disable', 'an email notification', 'woocommerce-gateway-stripe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'woocommerce-gateway-stripe' ),
				'default' => 'yes',
			),

			'subject'    => $base_fields['subject'],
			'heading'    => $base_fields['heading'],
			'email_type' => $base_fields['email_type'],
		);
	}

	/**
	 * Triggers the email.
	 *
	 * @param WC_Stripe_Exception $error An exception that occured.
	 * @param WC_Order            $order The renewal order whose payment failed.
	 */
	public function trigger( $error, $order ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( false === strpos( $error->getMessage(), 'authentication_required' ) ) {
			return;
		}

		$this->object    = $order;
		$this->recipient = wcs_get_objects_property( $this->object, 'billing_email' );

		$this->find['order_date']    = '{order_date}';
		$this->replace['order_date'] = wcs_format_datetime( wcs_get_objects_property( $this->object, 'date_created' ) );

		$this->find['order_number']    = '{order_number}';
		$this->replace['order_number'] = $this->object->get_order_number();

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}
}
