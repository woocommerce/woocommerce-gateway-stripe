<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Stripe_Blocks_Support class.
 *
 * @extends AbstractPaymentMethodType
 */
final class WC_Stripe_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'stripe';

	/**
	 * The Payment Request configuration class used for Shortcode PRBs. We use it here to retrieve
	 * the same configurations.
	 *
	 * @var WC_Stripe_Payment_Request
	 */
	private $payment_request_configuration;

	/**
	 * Constructor
	 *
	 * @param WC_Stripe_Payment_Request  The Stripe Payment Request configuration used for Payment
	 *                                   Request buttons.
	 */
	public function __construct( $payment_request_configuration = null ) {
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_payment_request_order_meta' ], 8, 2 );
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_stripe_intents' ], 9999, 2 );
		$this->payment_request_configuration = null !== $payment_request_configuration ? $payment_request_configuration : new WC_Stripe_Payment_Request();
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_stripe_settings', [] );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$asset_path   = WC_STRIPE_PLUGIN_PATH . '/build/index.asset.php';
		$version      = WC_STRIPE_VERSION;
		$dependencies = [];
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}
		wp_register_script(
			'wc-stripe-blocks-integration',
			WC_STRIPE_PLUGIN_URL . '/build/index.js',
			$dependencies,
			$version,
			true
		);
		wp_set_script_translations(
			'wc-stripe-blocks-integration',
			'woocommerce-gateway-stripe'
		);

		return [ 'wc-stripe-blocks-integration' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		// We need to call array_merge_recursive so the blocks 'button' setting doesn't overwrite
		// what's provided from the gateway or payment request configuration.
		return array_merge_recursive(
			$this->get_gateway_javascript_params(),
			$this->get_payment_request_javascript_params(),
			// Blocks-specific options
			[
				'title'          => $this->get_title(),
				'icons'          => $this->get_icons(),
				'supports'       => $this->get_supported_features(),
				'showSavedCards' => $this->get_show_saved_cards(),
				'showSaveOption' => $this->get_show_save_option(),
				'isAdmin'        => is_admin(),
				'button'         => [
					'customLabel' => $this->payment_request_configuration->get_button_label(),
				],
			]
		);
	}

	/**
	 * Returns the Stripe Payment Gateway JavaScript configuration object.
	 *
	 * @return array  the JS configuration from the Stripe Payment Gateway.
	 */
	private function get_gateway_javascript_params() {
		$js_configuration = [];

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['stripe'] ) ) {
			$js_configuration = $gateways['stripe']->javascript_params();
		}

		return apply_filters(
			'wc_stripe_params',
			$js_configuration
		);
	}

	/**
	 * Returns the Stripe Payment Request JavaScript configuration object.
	 *
	 * @return array  the JS configuration for Stripe Payment Requests.
	 */
	private function get_payment_request_javascript_params() {
		return apply_filters(
			'wc_stripe_payment_request_params',
			$this->payment_request_configuration->javascript_params()
		);
	}

	/**
	 * Determine if store allows cards to be saved during checkout.
	 *
	 * @return bool True if merchant allows shopper to save card (payment method) during checkout.
	 */
	private function get_show_saved_cards() {
		return isset( $this->settings['saved_cards'] ) ? 'yes' === $this->settings['saved_cards'] : false;
	}

	/**
	 * Determine if the checkbox to enable the user to save their payment method should be shown.
	 *
	 * @return bool True if the save payment checkbox should be displayed to the user.
	 */
	private function get_show_save_option() {
		$saved_cards = $this->get_show_saved_cards();
		// This assumes that Stripe supports `tokenization` - currently this is true, based on
		// https://github.com/woocommerce/woocommerce-gateway-stripe/blob/master/includes/class-wc-gateway-stripe.php#L95 .
		// See https://github.com/woocommerce/woocommerce-gateway-stripe/blob/ad19168b63df86176cbe35c3e95203a245687640/includes/class-wc-gateway-stripe.php#L271 and
		// https://github.com/woocommerce/woocommerce/wiki/Payment-Token-API .
		return apply_filters( 'wc_stripe_display_save_payment_method_checkbox', filter_var( $saved_cards, FILTER_VALIDATE_BOOLEAN ) );
	}

	/**
	 * Returns the title string to use in the UI (customisable via admin settings screen).
	 *
	 * @return string Title / label string
	 */
	private function get_title() {
		return isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Credit / Debit Card', 'woocommerce-gateway-stripe' );
	}

	/**
	 * Return the icons urls.
	 *
	 * @return array Arrays of icons metadata.
	 */
	private function get_icons() {
		$icons_src = [
			'visa'       => [
				'src' => WC_STRIPE_PLUGIN_URL . '/assets/images/visa.svg',
				'alt' => __( 'Visa', 'woocommerce-gateway-stripe' ),
			],
			'amex'       => [
				'src' => WC_STRIPE_PLUGIN_URL . '/assets/images/amex.svg',
				'alt' => __( 'American Express', 'woocommerce-gateway-stripe' ),
			],
			'mastercard' => [
				'src' => WC_STRIPE_PLUGIN_URL . '/assets/images/mastercard.svg',
				'alt' => __( 'Mastercard', 'woocommerce-gateway-stripe' ),
			],
		];

		if ( 'USD' === get_woocommerce_currency() ) {
			$icons_src['discover'] = [
				'src' => WC_STRIPE_PLUGIN_URL . '/assets/images/discover.svg',
				'alt' => __( 'Discover', 'woocommerce-gateway-stripe' ),
			];
			$icons_src['jcb']      = [
				'src' => WC_STRIPE_PLUGIN_URL . '/assets/images/jcb.svg',
				'alt' => __( 'JCB', 'woocommerce-gateway-stripe' ),
			];
			$icons_src['diners']   = [
				'src' => WC_STRIPE_PLUGIN_URL . '/assets/images/diners.svg',
				'alt' => __( 'Diners', 'woocommerce-gateway-stripe' ),
			];
		}
		return $icons_src;
	}

	/**
	 * Add payment request data to the order meta as hooked on the
	 * woocommerce_rest_checkout_process_payment_with_context action.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 */
	public function add_payment_request_order_meta( PaymentContext $context, PaymentResult &$result ) {
		$data = $context->payment_data;
		if ( ! empty( $data['payment_request_type'] ) && 'stripe' === $context->payment_method ) {
			$this->add_order_meta( $context->order, $data['payment_request_type'] );
		}

		// hook into stripe error processing so that we can capture the error to
		// payment details (which is added to notices and thus not helpful for
		// this context).
		if ( 'stripe' === $context->payment_method ) {
			add_action(
				'wc_gateway_stripe_process_payment_error',
				function( $error ) use ( &$result ) {
					$payment_details                 = $result->payment_details;
					$payment_details['errorMessage'] = wp_strip_all_tags( $error->getLocalizedMessage() );
					$result->set_payment_details( $payment_details );
				}
			);
		}
	}

	/**
	 * Handles any potential stripe intents on the order that need handled.
	 *
	 * This is configured to execute after legacy payment processing has
	 * happened on the woocommerce_rest_checkout_process_payment_with_context
	 * action hook.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 */
	public function add_stripe_intents( PaymentContext $context, PaymentResult &$result ) {
		if ( 'stripe' === $context->payment_method
			&& (
				! empty( $result->payment_details['payment_intent_secret'] )
				|| ! empty( $result->payment_details['setup_intent_secret'] )
			)
		) {
			$payment_details                          = $result->payment_details;
			$payment_details['verification_endpoint'] = add_query_arg(
				[
					'order'       => $context->order->get_id(),
					'nonce'       => wp_create_nonce( 'wc_stripe_confirm_pi' ),
					'redirect_to' => rawurlencode( $result->redirect_url ),
				],
				home_url() . \WC_Ajax::get_endpoint( 'wc_stripe_verify_intent' )
			);
			$result->set_payment_details( $payment_details );
			$result->set_status( 'success' );
		}
	}

	/**
	 * Handles adding information about the payment request type used to the order meta.
	 *
	 * @param \WC_Order $order The order being processed.
	 * @param string    $payment_request_type The payment request type used for payment.
	 */
	private function add_order_meta( \WC_Order $order, $payment_request_type ) {
		if ( 'apple_pay' === $payment_request_type ) {
			$order->set_payment_method_title( 'Apple Pay (Stripe)' );
			$order->save();
		} elseif ( 'google_pay' === $payment_request_type ) {
			$order->set_payment_method_title( 'Google Pay (Stripe)' );
			$order->save();
		} elseif ( 'payment_request_api' === $payment_request_type ) {
			$order->set_payment_method_title( 'Payment Request (Stripe)' );
			$order->save();
		}
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['stripe'] ) ) {
			$gateway = $gateways['stripe'];
			return array_filter( $gateway->supports, [ $gateway, 'supports' ] );
		}
		return [];
	}
}
