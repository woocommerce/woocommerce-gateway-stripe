<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles OXXO payment method.
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 5.8.0
 */
class WC_Gateway_Stripe_Oxxo extends WC_Stripe_Payment_Gateway_Voucher {

	/**
	 * ID used by UPE
	 *
	 * @var string
	 */
	const ID = 'stripe_oxxo';

	/**
	 * ID used by WooCommerce to identify the payment method
	 *
	 * @var string
	 */
	public $id = 'stripe_oxxo';

	/**
	 * ID used by stripe
	 */
	protected $stripe_id = 'oxxo';

	/**
	 * List of accepted currencies
	 *
	 * @var array
	 */
	protected $supported_currencies = [ 'MXN' ];

	/**
	 * List of accepted countries
	 */
	protected $supported_countries = [ 'MX' ];

	/**
	 * Constructor
	 *
	 * @since 5.8.0
	 */
	public function __construct() {
		$this->method_title = __( 'Stripe OXXO', 'woocommerce-gateway-stripe' );
		parent::__construct();

		add_filter( 'wc_stripe_allowed_payment_processing_statuses', [ $this, 'add_allowed_payment_processing_statuses' ], 10, 2 );
	}

	/**
	 * Adds on-hold as accepted status during webhook handling on orders paid with voucher
	 *
	 * @param $allowed_statuses
	 * @param $order
	 *
	 * @return mixed
	 */
	public function add_allowed_payment_processing_statuses( $allowed_statuses, $order ) {
		if ( $this->stripe_id === $order->get_meta( '_stripe_upe_payment_type' ) && ! in_array( 'on-hold', $allowed_statuses ) ) {
			$allowed_statuses[] = 'on-hold';
		}

		return $allowed_statuses;
	}

	/**
	 * Payment form on checkout page
	 *
	 * @since 5.8.0
	 */
	public function payment_fields() {
		$description = $this->get_description();
		apply_filters( 'wc_stripe_description', wp_kses_post( $description ), $this->id )
		?>
		<div class="stripe-source-errors" role="alert"></div>

		<div id="stripe-boleto-payment-data"><?php echo wpautop( esc_html( $description ) ); ?></div>
		<?php
	}

	/**
	 * Validates the minimum and maximum amount. Throws exception when out of range value is added
	 *
	 * @since 5.8.0
	 *
	 * @param $amount
	 *
	 * @throws WC_Stripe_Exception
	 */
	protected function validate_amount_limits( $amount ) {

		if ( $amount < 10.00 ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_Stripe_Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( 10.0 ) ) );
		} elseif ( $amount > 10000.00 ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_Stripe_Exception( sprintf( __( 'Sorry, the maximum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( 10000.00 ) ) );
		}
	}

	/**
	 * Gather the data necessary to confirm the payment via javascript
	 * Override this when extending the class
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	protected function get_confirm_payment_data( $order ) {
		return [
			'payment_method' => [
				'billing_details' => [
					'name'  => $order->get_formatted_billing_full_name(),
					'email' => $order->get_billing_email(),
				],
			],
		];
	}
}
