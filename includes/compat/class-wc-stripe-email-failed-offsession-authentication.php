<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Failed Renewal/Pre-Order Authentication Notification
 *
 * @extends WC_Email_Customer_Invoice
 */
class WC_Stripe_Email_Failed_Offsession_Authentication extends WC_Email_Customer_Invoice {
	/**
	 * An instance of the (failed) renewal email class.
	 *
	 * @var WCS_Email_Customer_Renewal_Invoice
	 */
	public $renewal_email;

	/**
	 * Constructor.
	 *
	 * @param WCS_Email_Customer_Renewal_Invoice $renewal_email The instalce of the renewal invoice email (Optional).
	 */
	public function __construct( $renewal_email = null ) {
		$this->id             = 'failed_sca_authentication';
		$this->title          = __( 'Failed SCA Authentication', 'woocommerce-gateway-stripe' );
		$this->description    = __( 'Sent to a customer when a renewal fails because the transaction requires an SCA verification. The email contains renewal order information and payment links.', 'woocommerce-gateway-stripe' );
		$this->customer_email = true;
		$this->renewal_email  = $renewal_email;

		$this->template_html  = 'emails/failed-renewal-authentication.php';
		$this->template_plain = 'emails/plain/failed-renewal-authentication.php';
		$this->template_base  = plugin_dir_path( WC_STRIPE_MAIN_FILE ) . 'templates/';

		// Triggers the email at the correct hook.
		add_action( 'wc_gateway_stripe_process_payment_error', array( $this, 'trigger_email' ), 10, 2 );

		// We want all the parent's methods, with none of its properties, so call its parent's constructor, rather than my parent constructor.
		WC_Email::__construct();
	}

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
				'authorization_url' => add_query_arg( 'open-sca-modal', 'yes', $this->object->get_view_order_url() ),
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
				'authorization_url' => add_query_arg( 'open-sca-modal', 'yes', $this->object->get_view_order_url() ),
				'email'             => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Returns the default subject of the email (modifyable in settings).
	 *
	 * @param bool $paid An indicator if the renewal was paid (for compatibility).
	 * @return string
	 */
	public function get_default_subject( $paid = false ) {
		return __( 'Payment authorization needed for renewal order {order_number}', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Returns the default heading of the email (modifyable in settings).
	 *
	 * @param bool $paid An indicator if the renewal was paid (for compatibility).
	 * @return string
	 */
	public function get_default_heading( $paid = false ) {
		return __( 'Payment authorization needed for renewal order {order_number}', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Uses specific fields from `WC_Email_Customer_Invoice` for this email.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$base_fields = $this->form_fields;

		$this->form_fields = array(
			'enabled' => array(
				'title'   => _x( 'Enable/Disable', 'an email notification', 'woocommerce-gateway-stripe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'woocommerce-gateway-stripe' ),
				'default' => 'yes',
			),

			'enabled'            => $base_fields['enabled'],
			'subject'            => $base_fields['subject'],
			'heading'            => $base_fields['heading'],
			'additional_content' => $base_fields['additional_content'],
			'email_type'         => $base_fields['email_type'],
		);
	}

	/**
	 * Triggers the email.
	 *
	 * @param WC_Stripe_Exception $error An exception that occured.
	 * @param WC_Order            $order The renewal order whose payment failed.
	 */
	public function trigger_email( $error, $order ) {
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

		// Prevent the renewal email from WooCommerce Subscriptions from being sent.
		if ( isset( $this->renewal_email ) ) {
			remove_action( 'woocommerce_generated_manual_renewal_order_renewal_notification', array( $this->renewal_email, 'trigger' ) );
			remove_action( 'woocommerce_order_status_failed_renewal_notification', array( $this->renewal_email, 'trigger' ) );
		}
	}
}
