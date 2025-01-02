<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Multibanco Payment Method class extending UPE base class
 */
class WC_Stripe_UPE_Payment_Method_Multibanco extends WC_Stripe_UPE_Payment_Method {

	const STRIPE_ID = WC_Stripe_Payment_Methods::MULTIBANCO;

	const LPM_GATEWAY_CLASS = WC_Gateway_Stripe_Multibanco::class;

	/**
	 * Constructor for Multibanco payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->stripe_id            = self::STRIPE_ID;
		$this->title                = __( 'Multibanco', 'woocommerce-gateway-stripe' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ WC_Stripe_Currency_Code::EURO ];
		$this->supported_countries  = [ 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GI', 'GR', 'HU', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'US' ];
		$this->label                = __( 'Multibanco', 'woocommerce-gateway-stripe' );
		$this->description          = __(
			'Multibanco is an interbank network that links the ATMs of all major banks in Portugal, allowing customers to pay through either their ATM or online banking environment.',
			'woocommerce-gateway-stripe'
		);

		add_filter( 'wc_stripe_allowed_payment_processing_statuses', [ $this, 'add_allowed_payment_processing_statuses' ], 10, 2 );

		add_action( 'wc_gateway_stripe_process_payment_intent_requires_action', [ $this, 'save_instructions' ], 10, 2 );
		add_action( 'woocommerce_thankyou_stripe_multibanco', [ $this, 'thankyou_page' ] );
		add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
	}

	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$this->get_instructions( $order );
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order
	 * @param bool     $sent_to_admin
	 * @param bool     $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		$payment_method = $order->get_payment_method();
		if ( ! $sent_to_admin && 'stripe_multibanco' === $payment_method && $order->has_status( 'on-hold' ) ) {
			$this->get_instructions( $order, $plain_text );
		}
	}

	/**
	 * Gets Multibanco payment instructions for the customer.
	 *
	 * @param WC_Order $order
	 * @param bool     $plain_text
	 */
	public function get_instructions( $order, $plain_text = false ) {
		$data = $order->get_meta( '_stripe_multibanco' );
		if ( ! $data ) {
			return;
		}

		if ( $plain_text ) {
			esc_html_e( 'MULTIBANCO ORDER INFORMATION', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
			esc_html_e( 'Amount:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo wp_kses_post( $data['amount'] ) . "\n\n";
			echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
			esc_html_e( 'Entity:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo esc_html( $data['entity'] ) . "\n\n";
			echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
			esc_html_e( 'Reference:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo esc_html( $data['reference'] ) . "\n\n";
		} else {
			?>
			<h3><?php esc_html_e( 'MULTIBANCO ORDER INFORMATION', 'woocommerce-gateway-stripe' ); ?></h3>
			<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
				<li class="woocommerce-order-overview__order order">
					<?php esc_html_e( 'Amount:', 'woocommerce-gateway-stripe' ); ?>
					<strong><?php echo wp_kses_post( $data['amount'] ); ?></strong>
				</li>
				<li class="woocommerce-order-overview__order order">
					<?php esc_html_e( 'Entity:', 'woocommerce-gateway-stripe' ); ?>
					<strong><?php echo esc_html( $data['entity'] ); ?></strong>
				</li>
				<li class="woocommerce-order-overview__order order">
					<?php esc_html_e( 'Reference:', 'woocommerce-gateway-stripe' ); ?>
					<strong><?php echo esc_html( $data['reference'] ); ?></strong>
				</li>
			</ul>
			<?php
		}
	}


	/**
	 * Saves Multibanco information to the order meta for later use.
	 *
	 * @param object $order
	 * @param object $payment_intent. The PaymentIntent object.
	 */
	public function save_instructions( $order, $payment_intent ) {
		if ( empty( $payment_intent->next_action->multibanco_display_details ) ) {
			return;
		}

		$data = [
			'amount'    => $order->get_formatted_order_total(),
			'entity'    => $payment_intent->next_action->multibanco_display_details->entity,
			'reference' => $payment_intent->next_action->multibanco_display_details->reference,
		];

		$order->update_meta_data( '_stripe_multibanco', $data );
	}

	/**
	 * Adds on-hold as accepted status during webhook handling on orders paid with Mukltibanco
	 *
	 * @param $allowed_statuses
	 * @param $order
	 *
	 * @return mixed
	 */
	public function add_allowed_payment_processing_statuses( $allowed_statuses, $order ) {
		if ( WC_Stripe_Payment_Methods::MULTIBANCO === $order->get_meta( '_stripe_upe_payment_type' ) && ! in_array( 'on-hold', $allowed_statuses, true ) ) {
			$allowed_statuses[] = 'on-hold';
		}

		return $allowed_statuses;
	}

	/**
	 * Returns whether the payment method is available for the Stripe account's country.
	 *
	 * Multibanco is available for the following countries: 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GI', 'GR', 'HU', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'US'.
	 *
	 * @return bool True if the payment method is available for the account's country, false otherwise.
	 */
	public function is_available_for_account_country() {
		return in_array( WC_Stripe::get_instance()->account->get_account_country(), $this->supported_countries, true );
	}
}
