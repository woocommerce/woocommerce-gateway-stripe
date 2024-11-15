<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles Multibanco payment method.
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 4.1.0
 */
class WC_Gateway_Stripe_Multibanco extends WC_Stripe_Payment_Gateway {

	const ID = 'stripe_multibanco';

	/**
	 * Notices (array)
	 *
	 * @var array
	 */
	public $notices = [];

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
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = self::ID;
		$this->method_title       = __( 'Stripe Multibanco', 'woocommerce-gateway-stripe' );
		$this->method_description = sprintf(
		/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
			__( 'All other general Stripe settings can be adjusted %1$shere%2$s.', 'woocommerce-gateway-stripe' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) ) . '">',
			'</a>'
		);
		$this->supports = [
			'products',
			'refunds',
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings              = WC_Stripe_Helper::get_stripe_settings();
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = WC_Stripe_Mode::is_test();
		$this->saved_cards          = ( ! empty( $main_settings['saved_cards'] ) && 'yes' === $main_settings['saved_cards'] ) ? true : false;
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
		add_action( 'woocommerce_thankyou_stripe_multibanco', [ $this, 'thankyou_page' ] );

		// Customer Emails
		add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
	}

	/**
	 * Returns all supported currencies for this payment method.
	 *
	 * @since 4.1.0
	 * @version 4.1.0
	 * @return array
	 */
	public function get_supported_currency() {
		return apply_filters(
			'wc_stripe_multibanco_supported_currencies',
			[
				WC_Stripe_Currency_Code::EURO,
			]
		);
	}

	/**
	 * Checks to see if all criteria is met before showing payment method.
	 *
	 * @since 4.1.0
	 * @version 4.1.0
	 * @return bool
	 */
	public function is_available() {
		if ( ! in_array( get_woocommerce_currency(), $this->get_supported_currency() ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Get_icon function.
	 *
	 * @since 1.0.0
	 * @version 4.1.0
	 * @return string
	 */
	public function get_icon() {
		$icons = $this->payment_icons();

		$icons_str = '';

		$icons_str .= isset( $icons['multibanco'] ) ? $icons['multibanco'] : '';

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * Outputs scripts used for stripe payment
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! parent::is_valid_pay_for_order_endpoint() && ! is_add_payment_method_page() ) {
			return;
		}

		wp_enqueue_style( 'stripe_styles' );
		wp_enqueue_script( 'woocommerce_stripe' );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require WC_STRIPE_PLUGIN_PATH . '/includes/admin/stripe-multibanco-settings.php';
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		global $wp;
		$user        = wp_get_current_user();
		$total       = WC()->cart->total;
		$description = $this->get_description();

		// If paying from order, we need to get total from order not cart.
		if ( parent::is_valid_pay_for_order_endpoint() ) {
			$order = wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) );
			$total = $order->get_total();
		}

		if ( is_add_payment_method_page() ) {
			$pay_button_text = __( 'Add Payment', 'woocommerce-gateway-stripe' );
			$total           = '';
		} else {
			$pay_button_text = '';
		}

		echo '<div
			id="stripe-multibanco-payment-data"
			data-amount="' . esc_attr( WC_Stripe_Helper::get_stripe_amount( $total ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '">';

		if ( $description ) {
			echo wp_kses( wpautop( apply_filters( 'wc_stripe_description', $description, $this->id ) ), [ 'p' => [] ] );
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
	 * @since 4.1.0
	 * @version 4.1.0
	 * @param WC_Order $order
	 * @param bool     $sent_to_admin
	 * @param bool     $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		$order_id = $order->get_id();

		$payment_method = $order->get_payment_method();

		if ( ! $sent_to_admin && 'stripe_multibanco' === $payment_method && $order->has_status( 'on-hold' ) ) {
			WC_Stripe_Logger::log( 'Sending multibanco email for order #' . $order_id );

			$this->get_instructions( $order, $plain_text );
		}
	}

	/**
	 * Gets the Multibanco instructions for customer to pay.
	 *
	 * @since 4.1.0
	 * @version 4.1.0
	 * @param int|WC_Order $order
	 */
	public function get_instructions( $order, $plain_text = false ) {
		if ( true === is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$data = $order->get_meta( '_stripe_multibanco' );

		if ( $plain_text ) {
			esc_html_e( 'MULTIBANCO INFORMAÇÕES DE ENCOMENDA:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
			esc_html_e( 'Montante:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo wp_kses_post( $data['amount'] ) . "\n\n";
			echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
			esc_html_e( 'Entidade:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo esc_html( $data['entity'] ) . "\n\n";
			echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
			esc_html_e( 'Referencia:', 'woocommerce-gateway-stripe' ) . "\n\n";
			echo esc_html( $data['reference'] ) . "\n\n";
		} else {
			?>
			<h3><?php esc_html_e( 'MULTIBANCO INFORMAÇÕES DE ENCOMENDA:', 'woocommerce-gateway-stripe' ); ?></h3>
			<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
			<li class="woocommerce-order-overview__order order">
				<?php esc_html_e( 'Montante:', 'woocommerce-gateway-stripe' ); ?>
				<strong><?php echo wp_kses_post( $data['amount'] ); ?></strong>
			</li>
			<li class="woocommerce-order-overview__order order">
				<?php esc_html_e( 'Entidade:', 'woocommerce-gateway-stripe' ); ?>
				<strong><?php echo esc_html( $data['entity'] ); ?></strong>
			</li>
			<li class="woocommerce-order-overview__order order">
				<?php esc_html_e( 'Referencia:', 'woocommerce-gateway-stripe' ); ?>
				<strong><?php echo esc_html( $data['reference'] ); ?></strong>
			</li>
			</ul>
			<?php
		}
	}

	/**
	 * Saves Multibanco information to the order meta for later use.
	 *
	 * @since 4.1.0
	 * @version 4.1.0
	 * @param object $order
	 * @param object $source_object
	 */
	public function save_instructions( $order, $source_object ) {
		$data = [
			'amount'    => $order->get_formatted_order_total(),
			'entity'    => $source_object->multibanco->entity,
			'reference' => $source_object->multibanco->reference,
		];

		$order_id = $order->get_id();

		$order->update_meta_data( '_stripe_multibanco', $data );
	}

	/**
	 * Creates the source for charge.
	 *
	 * @since 4.1.0
	 * @version 4.1.0
	 * @param object $order
	 * @return mixed
	 */
	public function create_source( $order ) {
		$currency              = $order->get_currency();
		$return_url            = $this->get_stripe_return_url( $order );
		$post_data             = [];
		$post_data['amount']   = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency );
		$post_data['currency'] = strtolower( $currency );
		$post_data['type']     = WC_Stripe_Payment_Methods::MULTIBANCO;
		$post_data['owner']    = $this->get_owner_details( $order );
		$post_data['redirect'] = [ 'return_url' => $return_url ];

		if ( ! empty( $this->statement_descriptor ) ) {
			$post_data['statement_descriptor'] = WC_Stripe_Helper::clean_statement_descriptor( $this->statement_descriptor );
		}

		WC_Stripe_Logger::log( 'Info: Begin creating Multibanco source' );

		return WC_Stripe_API::request( $post_data, 'sources' );
	}

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force payment source to be saved.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false ) {
		try {
			$order = wc_get_order( $order_id );

			// This will throw exception if not valid.
			$this->validate_minimum_order_amount( $order );

			// This comes from the create account checkbox in the checkout page.
			$create_account = ! empty( $_POST['createaccount'] ) ? true : false;

			if ( $create_account ) {
				$new_customer_id     = $order->get_customer_id();
				$new_stripe_customer = new WC_Stripe_Customer( $new_customer_id );
				$new_stripe_customer->create_customer();
			}

			$response = $this->create_source( $order );

			if ( ! empty( $response->error ) ) {
				$order->add_order_note( $response->error->message );

				throw new Exception( $response->error->message );
			}

			$order->update_meta_data( '_stripe_source_id', $response->id );
			$order->save();

			$this->save_instructions( $order, $response );

			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting Multibanco payment', 'woocommerce-gateway-stripe' ) );

			// Reduce stock levels
			wc_reduce_stock_levels( $order_id );

			// Remove cart
			WC()->cart->empty_cart();

			WC_Stripe_Logger::log( 'Info: Redirecting to Multibanco...' );

			return [
				'result'   => 'success',
				'redirect' => esc_url_raw( $response->redirect->url ),
			];
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			if ( $order->has_status(
				apply_filters(
					'wc_stripe_allowed_payment_processing_statuses',
					[ 'pending', 'failed' ],
					$order
				)
			) ) {
				$this->send_failed_order_email( $order_id );
			}

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}
}
