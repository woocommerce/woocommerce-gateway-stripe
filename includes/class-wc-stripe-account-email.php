<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Account email class.
 *
 * @category Emails
 * @since 4.0.0
 */
class WC_Stripe_Account_Email {
	/**
	 * The order.
	 *
	 * @var
	 */
	public $order;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function __construct( $order ) {
		$this->order = $order;
	}

	/**
	 * Returns the content type.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function get_content_type() {
		return 'text/plain';
	}

	/**
	 * Returns the "from" name.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function get_from_name() {
		$from_name = apply_filters( 'wc_stripe_email_from_name', get_option( 'woocommerce_email_from_name' ), $this );
		return wp_specialchars_decode( esc_html( $from_name ), ENT_QUOTES );
	}

	/**
	 * Get the from address for outgoing emails.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function get_from_address() {
		$from_address = apply_filters( 'wc_stripe_email_from_address', get_option( 'woocommerce_email_from_address' ), $this );
		return sanitize_email( $from_address );
	}

	/**
	 * Creates the customer account.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return int User ID
	 */
	public function create_account() {
		$user_login   = WC_Stripe_Helper::is_pre_30() ? $this->order->billing_first_name : $this->order->get_billing_first_name();
		$user_email   = WC_Stripe_Helper::is_pre_30() ? $this->order->billing_email : $this->order->get_billing_email();
		$password     = wp_generate_password();
		$username     = sanitize_user( current( explode( '@', $user_email ) ), true );

		// Ensure username is unique.
		$append     = 1;
		$o_username = $username;
		while ( username_exists( $username ) ) {
			$username = $o_username . $append;
			$append++;
		}

		$new_customer_data = apply_filters( 'wc_stripe_create_account_args', array(
			'user_login'      => $username,
			'user_email'      => $user_email,
			'user_pass'       => $password,
			'role'            => 'customer',
		) );

		$user_id = wp_insert_user( $new_customer_data );

		if ( is_wp_error( $user_id ) ) {
			throw new Exception( $user_id->get_error_message() );
		}

		do_action( 'woocommerce_created_customer', $user_id, $new_customer_data, $password );

		return $user_id;
	}
}
