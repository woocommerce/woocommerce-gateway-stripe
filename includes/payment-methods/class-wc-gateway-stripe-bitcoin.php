<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles Bitcoin payment method.
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 4.0.0
 */
class WC_Gateway_Stripe_Bitcoin extends WC_Stripe_Payment_Gateway {
	/**
	 * Notices (array)
	 * @var array
	 */
	public $notices = array();

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Alternate credit card statement name
	 *
	 * @var bool
	 */
	public $statement_descriptor;

	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Api access publishable key
	 *
	 * @var string
	 */
	public $publishable_key;

	/**
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/**
	 * Instructions for Bitcoin payment.
	 *
	 * @var string
	 */
	public $instructions;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'stripe_bitcoin';
		$this->method_title         = __( 'Stripe Bitcoin', 'woocommerce-gateway-stripe' );
		$this->method_description   = sprintf( __( 'All other general Stripe settings can be adjusted <a href="%s">here</a>.', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings              = get_option( 'woocommerce_stripe_settings' );
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = ( ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'] ) ? true : false;
		$this->saved_cards          = ( ! empty( $main_settings['saved_cards'] ) && 'yes' === $main_settings['saved_cards'] ) ? true : false;
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_notices', array( $this, 'check_environment' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_thankyou_stripe_bitcoin', array( $this, 'thankyou_page' ) );

		// Customer Emails
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Checks to make sure environment is setup correctly to use this payment method.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function check_environment() {
		$environment_warning = $this->get_environment_warning();

		if ( $environment_warning ) {
			$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
		}

		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
			echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
			echo '</p></div>';
		}
	}

	/**
	 * Checks the environment for compatibility problems. Returns a string with the first incompatibility
	 * found or false if the environment has no problems.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_environment_warning() {
		if ( 'yes' === $this->enabled && 'EUR' !== get_woocommerce_currency() ) {
			$message = __( 'Bitcoin is enabled - it requires store currency to be set to Euros.', 'woocommerce-gateway-stripe' );

			return $message;
		}

		return false;
	}

	/**
	 * All payment icons that work with Stripe.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return array
	 */
	public function payment_icons() {
		return apply_filters( 'wc_stripe_payment_icons', array(
			'bitcoin' => '<i class="stripe-pf stripe-pf-bitcoin stripe-pf-right" alt="Bitcoin" aria-hidden="true"></i>',
		) );
	}

	/**
	 * Get_icon function.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public function get_icon() {
		$icons = $this->payment_icons();

		$icons_str = '';

		$icons_str .= $icons['bitcoin'];

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for stripe payment
	 *
	 * @access public
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'stripe_paymentfonts' );

		if ( apply_filters( 'wc_stripe_use_elements_checkout_form', true ) ) {
			wp_enqueue_script( 'stripev3' );
			wp_enqueue_script( 'woocommerce_stripe_elements' );
		} else {
			wp_enqueue_script( 'woocommerce_stripe' );
		}
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require( WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-bitcoin-settings.php' );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$user                 = wp_get_current_user();
		$total                = WC()->cart->total;

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
			$total = $order->get_total();
		}

		if ( is_add_payment_method_page() ) {
			$pay_button_text = __( 'Add Payment', 'woocommerce-gateway-stripe' );
			$total        = '';
		} else {
			$pay_button_text = '';
		}

		echo '<div
			id="stripe-bitcoin-payment-data"
			data-amount="' . esc_attr( WC_Stripe_Helper::get_stripe_amount( $total ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '">';

		if ( $this->description ) {
			echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( $this->description ) ) );
		}

		echo '</div>';
	}

	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id
	 */
	public function thankyou_page( $order_id ) {
		$this->get_instructions( $order_id );
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

		if ( ! $sent_to_admin && 'stripe_bitcoin' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
			$this->get_instructions( $order_id, $plain_text );
		}
	}

	/**
	 * Gets the Bitcoin instructions for customer to pay.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param int $order_id
	 */
	public function get_instructions( $order_id, $plain_text = false ) {
		$data = get_post_meta( $order_id, '_stripe_bitcoin', true );

		if ( $plain_text ) {
			esc_html_e( 'Please pay the following:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
			esc_html_e( 'Bitcoin Amount:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo $data['amount'] . "\n\n";
			echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
			esc_html_e( 'Receiver:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo $data['address'] . "\n\n";
			echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
			esc_html_e( 'URI:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo $data['uri'] . "\n\n";
		} else {
			?>
			<h3><?php esc_html_e( 'Please pay the following:', 'woocommerce-gateway-stripe' ); ?></h3>
			<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
			<li class="woocommerce-order-overview__order order">
				<?php esc_html_e( 'Bitcoin Amount:', 'woocommerce-gateway-stripe' ); ?>
				<strong><?php echo $data['amount']; ?></strong>
			</li>
			<li class="woocommerce-order-overview__order order">
				<?php esc_html_e( 'Receiver:', 'woocommerce-gateway-stripe' ); ?>
				<strong><?php echo $data['address']; ?></strong>
			</li>
			<li class="woocommerce-order-overview__order order">
				<?php esc_html_e( 'URI:', 'woocommerce-gateway-stripe' ); ?>
				<strong><?php printf( __( '<a href="%s">Pay Bitcoin</a>', 'woocommerce-gateway-stripe' ), $data['uri'] ); ?></strong>
			</li>
			</ul>
			<?php
		}
	}

	/**
	 * Saves Bitcoin information to the order meta for later use.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order
	 * @param object $source_object
	 */
	public function save_instructions( $order, $source_object ) {
		$data = array(
			'amount'  => $source_object->bitcoin->amount,
			'address' => $source_object->bitcoin->address,
			'uri'     => $source_object->bitcoin->uri,
		);

		$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

		update_post_meta( $order_id, '_stripe_bitcoin', $data );
	}

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_customer Force user creation.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		try {
			$order = wc_get_order( $order_id );
			$source_object = ! empty( $_POST['stripe_source_object'] ) ? json_decode( stripslashes( $_POST['stripe_source_object'] ) ) : false;

			$source = $this->get_source( get_current_user_id(), $force_customer );

			if ( empty( $source->source ) && empty( $source->customer ) ) {
				$error_msg = __( 'Please enter your card details to make a payment.', 'woocommerce-gateway-stripe' );
				$error_msg .= ' ' . __( 'Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', 'woocommerce-gateway-stripe' );
				throw new Exception( $error_msg );
			}

			// Store source to order meta.
			$this->save_source( $order, $source );

			// Result from Stripe API request.
			$response = null;

			// Handle payment.
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
				}

				$this->save_instructions( $order, $source_object );

				// Mark as on-hold (we're awaiting the payment)
				$order->update_status( 'on-hold', __( 'Awaiting Bitcoin payment', 'woocommerce-gateway-stripe' ) );

				// Reduce stock levels
				wc_reduce_stock_levels( $order_id );

				// Remove cart
				WC()->cart->empty_cart();

				// Return thankyou redirect
				return array(
					'result'    => 'success',
					'redirect'  => $this->get_return_url( $order ),
				);
			} else {
				$order->payment_complete();
			}

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order_id );
			}

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}
}
