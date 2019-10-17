<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Failed Renewal/Pre-Order Authentication Notification
 *
 * @extends WC_Email_Customer_Invoice
 */
class WC_Stripe_Email_Failed_Renewal_Authentication extends WC_Stripe_Email_Failed_Authentication {
	/**
	 * Constructor.
	 *
	 * @param WC_Email[] $email_classes All existing instances of WooCommerce emails.
	 */
	public function __construct( $email_classes = array() ) {
		$this->id             = 'failed_renewal_authentication';
		$this->title          = __( 'Failed Subscription Renewal SCA Authentication', 'woocommerce-gateway-stripe' );
		$this->description    = __( 'Sent to a customer when a renewal fails because the transaction requires an SCA verification. The email contains renewal order information and payment links.', 'woocommerce-gateway-stripe' );
		$this->customer_email = true;

		$this->template_html  = 'emails/failed-renewal-authentication.php';
		$this->template_plain = 'emails/plain/failed-renewal-authentication.php';
		$this->template_base  = plugin_dir_path( WC_STRIPE_MAIN_FILE ) . 'templates/';

		// Triggers the email at the correct hook.
		add_action( 'wc_gateway_stripe_process_payment_authentication_required', array( $this, 'trigger' ) );

		if ( isset( $email_classes['WCS_Email_Customer_Renewal_Invoice'] ) ) {
			$this->original_email = $email_classes['WCS_Email_Customer_Renewal_Invoice'];
		}

		// We want all the parent's methods, with none of its properties, so call its parent's constructor, rather than my parent constructor.
		parent::__construct();
	}

	/**
	 * Triggers the email while also disconnecting the original Subscriptions email.
	 *
	 * @param WC_Order $order The order that is being paid.
	 */
	public function trigger( $order ) {
		if ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order->get_id() ) || wcs_is_subscription( $order->get_id() ) || wcs_order_contains_renewal( $order->get_id() ) ) ) {
			parent::trigger( $order );

			// Prevent the renewal email from WooCommerce Subscriptions from being sent.
			if ( isset( $this->original_email ) ) {
				remove_action( 'woocommerce_generated_manual_renewal_order_renewal_notification', array( $this->original_email, 'trigger' ) );
				remove_action( 'woocommerce_order_status_failed_renewal_notification', array( $this->original_email, 'trigger' ) );
			}

			// Prevent the retry email from WooCommerce Subscriptions from being sent.
			add_filter( 'wcs_get_retry_rule_raw', array( $this, 'prevent_retry_notification_email' ), 100, 3 );

			// Send email to store owner indicating communication is happening with the customer to request authentication.
			add_filter( 'wcs_get_retry_rule_raw', array( $this, 'set_store_owner_custom_email' ), 100, 3 );
		}
	}

	/**
	 * Returns the default subject of the email (modifyable in settings).
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Payment authorization needed for renewal of {site_title} order {order_number}', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Returns the default heading of the email (modifyable in settings).
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Payment authorization needed for renewal of order {order_number}', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Prevent all customer-facing retry notifications from being sent after this email.
	 *
	 * @param array $rule_array   The raw details about the retry rule.
	 * @param int   $retry_number The number of the retry.
	 * @param int   $order_id     The ID of the order that needs payment.
	 * @return array
	 */
	public function prevent_retry_notification_email( $rule_array, $retry_number, $order_id ) {
		if ( wcs_get_objects_property( $this->object, 'id' ) === $order_id ) {
			$rule_array['email_template_customer'] = '';
		}

		return $rule_array;
	}

	/**
	 * Send store owner a different email when the retry is related to an authentication required error.
	 *
	 * @param array $rule_array   The raw details about the retry rule.
	 * @param int   $retry_number The number of the retry.
	 * @param int   $order_id     The ID of the order that needs payment.
	 * @return array
	 */
	public function set_store_owner_custom_email( $rule_array, $retry_number, $order_id ) {
		if (
			wcs_get_objects_property( $this->object, 'id' ) === $order_id &&
			'' !== $rule_array['email_template_admin'] // Only send our email if a retry admin email was already going to be sent.
		) {
			$rule_array['email_template_admin'] = 'WC_Stripe_Email_Failed_Authentication_Retry';
		}

		return $rule_array;
	}
}
