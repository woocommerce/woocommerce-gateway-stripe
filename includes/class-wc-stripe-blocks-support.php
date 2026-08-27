<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;
use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Stripe_Blocks_Support class.
 */
final class WC_Stripe_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Handle for our integration script for block cart and checkout.
	 *
	 * @var string
	 */
	private const BLOCKS_SCRIPT_HANDLE = 'wc-stripe-blocks-integration';

	/**
	 * Handle for our stylesheet for block cart and checkout.
	 *
	 * @var string
	 */
	private const BLOCKS_STYLE_HANDLE = 'wc-stripe-blocks-checkout-style';

	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'stripe';

	/**
	 * The Express Checkout configuration class used for Shortcode PRBs. We use it here to retrieve
	 * the same configurations.
	 *
	 * @var WC_Stripe_Express_Checkout_Element
	 */
	private $express_checkout_configuration;

	/**
	 * Local cache for the UPE blocks asset metadata. Used to avoid multiple file reads.
	 *
	 * @var array{version: string, dependencies: array}|null
	 */
	private $blocks_asset = null;

	/**
	 * Constructor
	 *
	 * @param mixed                                   $payment_request_configuration The Stripe Payment Request configuration used for Payment Request buttons (removed).
	 * @param WC_Stripe_Express_Checkout_Element|null $express_checkout_configuration The Stripe Express Checkout configuration used for Express Checkout buttons.
	 *
	 * @deprecated Parameter $payment_request_configuration is deprecated since version 10.4.0 and will be removed in later versions.
	 */
	public function __construct( $payment_request_configuration = null, ?WC_Stripe_Express_Checkout_Element $express_checkout_configuration = null ) {
		if ( null !== $payment_request_configuration ) {
			_deprecated_argument(
				__FUNCTION__,
				'10.4.0'
			);
		}

		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_express_checkout_order_meta' ], 8, 2 );
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'add_stripe_intents' ], 9999, 2 );
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', [ $this, 'fail_unprocessed_payment' ], 10000, 2 );

		if ( null === $express_checkout_configuration ) {
			$helper                         = new WC_Stripe_Express_Checkout_Helper();
			$ajax_handler                   = new WC_Stripe_Express_Checkout_Ajax_Handler( $helper );
			$express_checkout_configuration = new WC_Stripe_Express_Checkout_Element( $ajax_handler, $helper );
		}
		$this->express_checkout_configuration = $express_checkout_configuration;
	}

	/**
	 * Initializes the payment method type.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = WC_Stripe_Helper::get_stripe_settings();

		// Hooks to manually enqueue the block CSS, as WooCommerce doesn't have an API for styles.
		add_filter( 'render_block_woocommerce/checkout', [ $this, 'maybe_enqueue_blocks_style' ] );
		add_filter( 'render_block_woocommerce/cart', [ $this, 'maybe_enqueue_blocks_style' ] );
		// Note that this is hooked at priority 20 so we run after WooCommerce has registered and enqueued the Cart and Checkout editor scripts.
		add_action( 'enqueue_block_editor_assets', [ $this, 'maybe_enqueue_blocks_style_for_editor' ], 20 );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		// If Stripe isn't enabled, then we don't need to check anything else - it isn't active.
		if ( empty( $this->settings['enabled'] ) || 'yes' !== $this->settings['enabled'] ) {
			return false;
		}

		// If UPE is disabled, then we don't need to go further - we know the gateway is enabled.
		$stripe_gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();

		if ( ! is_a( $stripe_gateway, 'WC_Stripe_UPE_Payment_Gateway' ) ) {
			return true;
		}

		// With OC the single OC element stands in for the gateway, so the per-method checks below don't
		// apply. Needed in the block editor where those methods may all be unavailable (e.g. no cart).
		if ( $stripe_gateway->should_render_optimized_checkout() ) {
			return true;
		}

		// This payment method is active if there is at least 1 UPE method available.
		foreach ( $stripe_gateway->payment_methods as $upe_method ) {
			if ( $upe_method->is_enabled() && $upe_method->is_available() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		// Ensure Stripe JS is registered
		WC_Stripe_Helper::register_stripe_js();

		$this->register_upe_payment_method_script_handles();

		return [ self::BLOCKS_SCRIPT_HANDLE ];
	}

	/**
	 * Registers the UPE JS scripts.
	 *
	 * @return void
	 */
	private function register_upe_payment_method_script_handles(): void {
		$this->register_upe_payment_method_style_handle();

		$asset = $this->get_upe_blocks_asset();

		wp_register_script(
			self::BLOCKS_SCRIPT_HANDLE,
			WC_STRIPE_PLUGIN_URL . '/build/upe-blocks.js',
			array_merge( [ 'stripe' ], $asset['dependencies'] ),
			$asset['version'],
			true
		);
		wp_set_script_translations(
			self::BLOCKS_SCRIPT_HANDLE,
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Registers our stylesheet for block cart and checkout.
	 *
	 * @return void
	 */
	private function register_upe_payment_method_style_handle(): void {
		if ( wp_style_is( self::BLOCKS_STYLE_HANDLE, 'registered' ) ) {
			return;
		}

		$asset = $this->get_upe_blocks_asset();

		wp_register_style(
			self::BLOCKS_STYLE_HANDLE,
			WC_STRIPE_PLUGIN_URL . '/build/upe-blocks.css',
			[],
			$asset['version']
		);

		// Register RTL support.
		wp_style_add_data( self::BLOCKS_STYLE_HANDLE, 'rtl', 'replace' );
	}

	/**
	 * Get the build metadata for the UPE blocks bundle, with fallback logic when the file doesn't exist.
	 *
	 * @return array{version: string, dependencies: array} The build version and script dependencies.
	 */
	private function get_upe_blocks_asset(): array {
		if ( null !== $this->blocks_asset ) {
			return $this->blocks_asset;
		}

		$asset_path   = WC_STRIPE_PLUGIN_PATH . '/build/upe-blocks.asset.php';
		$version      = WC_STRIPE_VERSION;
		$dependencies = [];

		if ( file_exists( $asset_path ) ) {
			$asset = require $asset_path;

			if ( is_array( $asset ) ) {
				$version      = $asset['version'] ?? $version;
				$dependencies = is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : $dependencies;
			}
		}

		$this->blocks_asset = [
			'version'      => $version,
			'dependencies' => $dependencies,
		];

		return $this->blocks_asset;
	}

	/**
	 * Enqueue the block stylesheet when the Cart or Checkout block renders AND
	 * our blocks script has also been enqueued.
	 *
	 * Implemented to run as a filter callback for `render_block_woocommerce/cart`
	 * and `render_block_woocommerce/checkout`, so the content MUST be returned as-is.
	 *
	 * @since 10.9.0
	 *
	 * @param string $content The rendered block content.
	 *
	 * @return string The unmodified block content.
	 */
	public function maybe_enqueue_blocks_style( $content ) {
		$this->enqueue_blocks_style_if_script_enqueued();

		return $content;
	}

	/**
	 * Block editor counterpart to {@see maybe_enqueue_blocks_style()},
	 * which enqueues our block stylesheet when the block script has been enqueued.
	 *
	 * @since 10.9.0
	 *
	 * @return void
	 */
	public function maybe_enqueue_blocks_style_for_editor(): void {
		$this->enqueue_blocks_style_if_script_enqueued();
	}

	/**
	 * Enqueues the blocks stylesheet if the block script has been enqueued.
	 *
	 * @return void
	 */
	private function enqueue_blocks_style_if_script_enqueued(): void {
		// Check if our script handle has been enqueued. Note that wp_script_is()
		// also checks dependencies and catches cases where the script will be
		// enqueued as a dependency of another script.
		if ( ! wp_script_is( self::BLOCKS_SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		$this->register_upe_payment_method_style_handle();

		wp_enqueue_style( self::BLOCKS_STYLE_HANDLE );
	}

	/**
	 * Registers the classic JS scripts.
	 *
	 * @return void
	 */
	private function register_legacy_payment_method_script_handles() {
		$asset_path   = WC_STRIPE_PLUGIN_PATH . '/build/index.asset.php';
		$version      = WC_STRIPE_VERSION;
		$dependencies = [];
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}
		wp_register_script(
			'wc-stripe-blocks-integration',
			WC_STRIPE_PLUGIN_URL . '/build/index.js',
			array_merge( [ 'stripe' ], $dependencies ),
			$version,
			true
		);
		wp_set_script_translations(
			'wc-stripe-blocks-integration',
			'woocommerce-gateway-stripe'
		);
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		// We need to call array_merge_recursive so the blocks 'button' setting doesn't overwrite
		// what's provided from the gateway or payment request configuration.
		return array_replace_recursive(
			$this->get_gateway_javascript_params(),
			$this->get_express_checkout_javascript_params(),
			// Blocks-specific options
			[
				'icons'                           => $this->get_icons(),
				'plugin_url'                      => WC_STRIPE_PLUGIN_URL,
				'supports'                        => $this->get_supported_features(),
				'showSavedCards'                  => $this->get_show_saved_cards(),
				'showSaveOption'                  => $this->get_show_save_option(),
				'isAdmin'                         => is_admin(),
				'shouldShowExpressCheckoutButton' => $this->should_show_express_checkout_button(),
				'button'                          => [
					'customLabel' => '',
				],
				'style'                           => $this->get_style(),
				'baseLocation'                    => wc_get_base_location(),
			]
		);
	}

	/**
	 * Returns an array of style properties supported by the payment method.
	 * This method is used only when rendering the payment method in the editor.
	 *
	 * @return array Array of style properties.
	 */
	private function get_style() {
		return [
			'height',
			'borderRadius',
		];
	}

	/**
	 * Returns true if the ECE should be shown on the current page, false otherwise.
	 *
	 * @return boolean True if ECEs should be displayed, false otherwise.
	 */
	private function should_show_express_checkout_button() {
		return $this->express_checkout_configuration->express_checkout_helper->should_show_express_checkout_button();
	}

	/**
	 * Returns the Stripe Payment Gateway JavaScript configuration object.
	 *
	 * @return array  the JS configuration from the Stripe Payment Gateway.
	 */
	private function get_gateway_javascript_params() {
		$js_configuration   = [];
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$main_gateway       = WC_Stripe::get_instance()->get_main_stripe_gateway();

		if ( isset( $available_gateways['stripe'] ) ) {
			$js_configuration = $available_gateways['stripe']->javascript_params();
		} elseif ( $this->is_upe_method_available( $available_gateways ) ) {
			$js_configuration = $main_gateway->javascript_params();
		} elseif (
			is_a( $main_gateway, 'WC_Stripe_UPE_Payment_Gateway' )
			&& $main_gateway->should_render_optimized_checkout()
		) {
			// In the Cart/Checkout block editor with OC enabled and card disabled, the consolidated
			// gateway is unavailable (is_available() requires the card method) and the per-method
			// gateways are filtered out of the editor, so neither branch above fires. Build the config
			// anyway so the OC element registers as 'stripe' and the block reports it as supported.
			$js_configuration = $main_gateway->javascript_params();
		}

		/** This filter is documented in includes/abstracts/abstract-wc-stripe-payment-gateway.php. */
		return apply_filters(
			'wc_stripe_params',
			$js_configuration
		);
	}

	/**
	 * Returns the Stripe Express Checkout JavaScript configuration object.
	 *
	 * @return array  the JS configuration for Stripe Express Checkout.
	 */
	private function get_express_checkout_javascript_params() {
		/**
		 * Filters the Express Checkout JavaScript parameters.
		 *
		 * @param array $params Express Checkout JavaScript parameters.
		 */
		return apply_filters(
			'wc_stripe_express_checkout_params',
			$this->express_checkout_configuration->javascript_params()
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
		// https://github.com/woocommerce/woocommerce-gateway-stripe/blob/master/includes/payment-methods/class-wc-stripe-upe-payment-gateway.php#L222.
		// See https://github.com/woocommerce/woocommerce-gateway-stripe/blob/master/includes/payment-methods/class-wc-stripe-upe-payment-gateway.php#L905 and
		// https://github.com/woocommerce/woocommerce/wiki/Payment-Token-API .
		/**
		 * Filters whether the save payment method checkbox should be displayed.
		 *
		 * @param bool $display Whether the save payment method checkbox should be displayed.
		 */
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

		if ( WC_Stripe_Currency_Code::UNITED_STATES_DOLLAR === get_woocommerce_currency() ) {
			$icons_src['discover'] = [
				'src' => WC_STRIPE_PLUGIN_URL . '/assets/images/discover.svg',
				'alt' => _x( 'Discover', 'Name of credit card', 'woocommerce-gateway-stripe' ),
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
	 * Add express checkout data to the order meta as hooked on the
	 * woocommerce_rest_checkout_process_payment_with_context action.
	 *
	 * @deprecated 10.6.0 Use add_express_checkout_order_meta() instead.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 *
	 * @return void
	 */
	public function add_payment_request_order_meta( PaymentContext $context, PaymentResult &$result ) {
		wc_deprecated_function( __METHOD__, '10.6.0', 'WC_Stripe_Blocks_Support::add_express_checkout_order_meta' );
		$this->add_express_checkout_order_meta( $context, $result );
	}

	/**
	 * Add express checkout data to the order meta as hooked on the
	 * woocommerce_rest_checkout_process_payment_with_context action.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 *
	 * @return void
	 */
	public function add_express_checkout_order_meta( PaymentContext $context, PaymentResult &$result ) {
		$data = $context->payment_data;
		if ( ! empty( $data['express_checkout_type'] ) && 'stripe' === $context->payment_method ) {
			$this->add_order_meta( $context->order, $data['express_checkout_type'] );
		}

		$is_stripe_payment_method = $this->name === $context->payment_method;
		$main_gateway             = WC_Stripe::get_instance()->get_main_stripe_gateway();
		$is_upe                   = $main_gateway instanceof WC_Stripe_UPE_Payment_Gateway;

		// Check if the payment method is a UPE payment method. UPE methods start with `stripe_`.
		if ( $is_upe && ! $is_stripe_payment_method && 0 === strpos( $context->payment_method, "{$this->name}_" ) ) {
			// Strip "Stripe_" from the payment method name to get the payment method type.
			$payment_method_type      = substr( $context->payment_method, strlen( $this->name ) + 1 );
			$is_stripe_payment_method = isset( $main_gateway->payment_methods[ $payment_method_type ] );
		}

		if ( ! $is_stripe_payment_method ) {
			return;
		}

		/**
		 * When using UPE on the block checkout and a saved token is being used, we need to set a flag
		 * to indicate that deferred intent should be used.
		 */
		$is_using_saved_token = isset( $data['issavedtoken'] ) && $data['issavedtoken'];

		// For split UPE gateways (e.g., stripe_us_bank_account), WooCommerce Blocks doesn't set the isSavedToken flag.
		// Check if a payment token is being used by looking for the wc-{gateway_id}-payment-token field.
		if ( ! $is_using_saved_token && ! empty( $data['token'] ) ) {
			// Payment data keys use underscores, not hyphens (e.g., wc-stripe_us_bank_account-payment-token).
			$token_key = 'wc-' . $context->payment_method . '-payment-token';
			if ( isset( $data[ $token_key ] ) && ! empty( $data[ $token_key ] ) ) {
				$is_using_saved_token = true;
			}
		}

		// Hook into Stripe error processing so that we can capture the error to payment details.
		// This error would have been registered via wc_add_notice() and thus is not helpful for block checkout processing.
		add_action(
			'wc_gateway_stripe_process_payment_error',
			function ( $error ) use ( &$result ) {
				$payment_details                 = $result->payment_details;
				$payment_details['errorMessage'] = wp_strip_all_tags( $error->getLocalizedMessage() );
				$result->set_payment_details( $payment_details );
			}
		);
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
	 *
	 * @return void
	 */
	public function add_stripe_intents( PaymentContext $context, PaymentResult &$result ) {
		if ( 'stripe' === $context->payment_method
			&& (
				! empty( $result->payment_details['payment_intent_secret'] )
				|| ! empty( $result->payment_details['setup_intent_secret'] )
			)
		) {
			$payment_details       = $result->payment_details;
			$verification_endpoint = add_query_arg(
				[
					'order'       => $context->order->get_id(),
					'nonce'       => wp_create_nonce( 'wc_stripe_confirm_pi' ),
					'intent_id'   => $payment_details['payment_intent_id'],
					'redirect_to' => rawurlencode( $result->redirect_url ),
				],
				home_url() . \WC_AJAX::get_endpoint( 'wc_stripe_verify_intent' )
			);

			if ( ! empty( $payment_details['save_payment_method'] ) ) {
				$verification_endpoint = add_query_arg(
					[ 'save_payment_method' => true ],
					$verification_endpoint
				);
			}

			$payment_details['verification_endpoint'] = $verification_endpoint;
			$result->set_payment_details( $payment_details );
			$result->set_status( 'success' );
		}
	}

	/**
	 * Whether the given Store API payment method id belongs to this gateway.
	 *
	 * A bare `stripe_` prefix test is not enough: other vendors register gateways under
	 * that prefix too, so split UPE ids are matched against the methods this gateway
	 * actually registers.
	 *
	 * @param string $payment_method The payment method id from the payment context.
	 *
	 * @return bool
	 */
	private function is_stripe_payment_method( string $payment_method ): bool {
		if ( $this->name === $payment_method ) {
			return true;
		}

		$prefix = $this->name . '_';
		if ( 0 !== strpos( $payment_method, $prefix ) ) {
			return false;
		}

		$main_gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
		if ( ! $main_gateway instanceof WC_Stripe_UPE_Payment_Gateway ) {
			return false;
		}

		return isset( $main_gateway->payment_methods[ substr( $payment_method, strlen( $prefix ) ) ] );
	}

	/**
	 * Fails the payment when WooCommerce never handed it to the gateway.
	 *
	 * `Legacy::process_legacy_payment()` returns without setting a status when it cannot
	 * resolve the payment method to an available gateway. The Store API then answers 200
	 * with an empty payment status, leaving the order unpaid with no charge attempted and
	 * nothing logged; express checkout reads that as a failure it cannot explain and the
	 * wallet sheet is left with no reason to show. Turn it into a real error instead.
	 *
	 * @param PaymentContext $context Holds context for the payment.
	 * @param PaymentResult  $result  Result object for the payment.
	 *
	 * @throws Exception When the payment was never processed.
	 *
	 * @return void
	 */
	public function fail_unprocessed_payment( PaymentContext $context, PaymentResult &$result ) {
		if ( '' !== (string) $result->status ) {
			return;
		}

		$payment_method = (string) $context->payment_method;
		if ( ! $this->is_stripe_payment_method( $payment_method ) ) {
			return;
		}

		WC_Stripe_Logger::error(
			'Payment was never processed for order: ' . $context->order->get_id(),
			[
				'payment_method' => $payment_method,
				'reason'         => 'WooCommerce could not resolve the payment method to an available gateway.',
			]
		);

		throw new Exception( __( 'This payment method is not available right now. Please try again or use a different one.', 'woocommerce-gateway-stripe' ) );
	}

	/**
	 * Handles adding information about the express checkout type used to the order meta.
	 *
	 * @param \WC_Order $order                The order being processed.
	 * @param string    $express_checkout_type The express checkout type used for payment.
	 *
	 * @return void
	 */
	private function add_order_meta( \WC_Order $order, $express_checkout_type ) {
		$payment_method_title = '';
		switch ( $express_checkout_type ) {
			case WC_Stripe_Payment_Methods::APPLE_PAY:
				$payment_method_title = WC_Stripe_Payment_Methods::APPLE_PAY_LABEL;
				break;
			case WC_Stripe_Payment_Methods::GOOGLE_PAY:
				$payment_method_title = WC_Stripe_Payment_Methods::GOOGLE_PAY_LABEL;
				break;
			case 'payment_request_api':
				$payment_method_title = WC_Stripe_Payment_Methods::PAYMENT_REQUEST_LABEL;
				break;
		}

		if ( $payment_method_title ) {
			$payment_method_suffix = WC_Stripe_Express_Checkout_Helper::get_payment_method_title_suffix();
			$order->set_payment_method_title( $payment_method_title . $payment_method_suffix );
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
		} elseif ( $this->is_upe_method_available( $gateways ) ) {
			$gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();
		} else {
			return [];
		}

		return array_filter( $gateway->supports, [ $gateway, 'supports' ] );
	}

	/**
	 * Determines if the UPE gateway is being used and if there is at least 1 UPE method available.
	 *
	 * @param array $available_gateways The available gateways.
	 * @return bool True if there is at least 1 UPE method available, false otherwise.
	 */
	private function is_upe_method_available( $available_gateways ) {
		$stripe_gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();

		if ( ! is_a( $stripe_gateway, 'WC_Stripe_UPE_Payment_Gateway' ) ) {
			return false;
		}

		foreach ( $stripe_gateway->payment_methods as $upe_method ) {
			// Exit once we've found one of our UPE methods.
			if ( isset( $available_gateways[ $upe_method->id ] ) ) {
				return true;
			}
		}

		return false;
	}
}
