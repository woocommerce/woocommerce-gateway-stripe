<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Failed Renewal/Pre-Order Authentication Notification
 *
 * @extends WC_Stripe_Email_Failed_Authentication
 */
class WC_Stripe_Email_Failed_Preorder_Authentication extends WC_Stripe_Email_Failed_Authentication {
	/**
	 * Holds the message, which is entered by admins when sending the email.
	 *
	 * @var string
	 */
	protected $custom_message;

	/**
	 * Constructor.
	 *
	 * @param WC_Email[] $email_classes All existing instances of WooCommerce emails.
	 */
	public function __construct( $email_classes = [] ) {
		$this->id             = 'failed_preorder_sca_authentication';
		$this->title          = __( 'Pre-order Payment Action Needed', 'woocommerce-gateway-stripe' );
		$this->description    = __( 'This is an order notification sent to the customer once a pre-order is complete, but additional payment steps are required.', 'woocommerce-gateway-stripe' );
		$this->customer_email = true;

		$this->template_html  = 'emails/failed-preorder-authentication.php';
		$this->template_plain = 'emails/plain/failed-preorder-authentication.php';
		$this->template_base  = plugin_dir_path( WC_STRIPE_MAIN_FILE ) . 'templates/';

		// Use the "authentication required" hook to add the correct, later hook.
		add_action( 'wc_gateway_stripe_process_payment_authentication_required', [ $this, 'trigger' ] );

		if ( isset( $email_classes['WC_Pre_Orders_Email_Pre_Order_Available'] ) ) {
			$this->original_email = $email_classes['WC_Pre_Orders_Email_Pre_Order_Available'];
		}

		// We want all the parent's methods, with none of its properties, so call its parent's constructor, rather than my parent constructor.
		parent::__construct();
	}

	/**
	 * When autnentication is required, this adds another action to `wc_pre_orders_pre_order_completed`
	 * in order to send the authentication required email when the custom pre-orders message is available.
	 *
	 * @param WC_Order $order The order whose payment is failing.
	 */
	public function trigger( $order ) {
		if ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order->get_id() ) ) {
			if ( isset( $this->original_email ) ) {
				remove_action( 'wc_pre_order_status_completed_notification', [ $this->original_email, 'trigger' ], 10, 2 );
			}

			add_action( 'wc_pre_orders_pre_order_completed', [ $this, 'send_email' ], 10, 2 );
		}
	}

	/**
	 * Triggers the email while also disconnecting the original Pre-Orders email.
	 *
	 * @param WC_Order $order The order that is being paid.
	 * @param string   $message The message, which should be added to the email.
	 */
	public function send_email( $order, $message ) {
		$this->custom_message = $message;

		parent::trigger( $order );

		// Restore the action of the original email for other bulk actions.
		if ( isset( $this->original_email ) ) {
			add_action( 'wc_pre_order_status_completed_notification', [ $this->original_email, 'trigger' ], 10, 2 );
		}
	}

	/**
	 * Returns the default subject of the email (modifyable in settings).
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Payment authorization needed for pre-order {order_number}', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Returns the default heading of the email (modifyable in settings).
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Payment authorization needed for pre-order {order_number}', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Returns the custom message, entered by the admin.
	 *
	 * @return string
	 */
	public function get_custom_message() {
		return $this->custom_message;
	}
}
