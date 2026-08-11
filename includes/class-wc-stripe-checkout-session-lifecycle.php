<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

/**
 * Connects Checkout Session synchronization to native WooCommerce checkout responses.
 */
class WC_Stripe_Checkout_Session_Lifecycle {
	/**
	 * Store API extension namespace.
	 *
	 * @var string
	 */
	private const EXTENSION_NAMESPACE = 'wc-stripe/checkout-session';

	/**
	 * Classic checkout fragment selector.
	 *
	 * @var string
	 */
	private const CLASSIC_FRAGMENT_SELECTOR = '#wc-stripe-checkout-session-data';

	/**
	 * Whether this Store API request explicitly requested initial session creation.
	 *
	 * @var bool
	 */
	private static $store_api_sync_requested = false;

	/**
	 * Checkout Session manager.
	 *
	 * @var WC_Stripe_Checkout_Session_Manager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param WC_Stripe_Checkout_Session_Manager|null $manager Checkout Session manager.
	 */
	public function __construct( ?WC_Stripe_Checkout_Session_Manager $manager = null ) {
		$this->manager = $manager ?? WC_Stripe_Checkout_Session_Manager::get_instance();
	}

	/**
	 * Register hooks used by classic checkout.
	 *
	 * @return void
	 */
	public function init_classic_hooks(): void {
		add_action( 'woocommerce_review_order_after_payment', [ $this, 'render_classic_placeholder' ] );
		add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'add_classic_fragment' ], 20 );
	}

	/**
	 * Register Checkout Session data and synchronization with the Store API.
	 *
	 * This must run on woocommerce_blocks_loaded, before the plugin's regular initialization.
	 *
	 * @return void
	 */
	public static function init_store_api(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			[
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => self::EXTENSION_NAMESPACE,
				'schema_callback' => [ self::class, 'get_store_api_schema' ],
				'data_callback'   => [ self::class, 'get_store_api_data' ],
				'schema_type'     => ARRAY_A,
			]
		);

		woocommerce_store_api_register_update_callback(
			[
				'namespace' => self::EXTENSION_NAMESPACE,
				'callback'  => [ self::class, 'request_store_api_sync' ],
			]
		);
	}

	/**
	 * Render the stable DOM target replaced by classic update-order-review responses.
	 *
	 * @return void
	 */
	public function render_classic_placeholder(): void {
		echo $this->get_classic_fragment_html( $this->manager->get_current_data() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_classic_fragment_html().
	}

	/**
	 * Synchronize after WooCommerce has updated customer, shipping, and totals, then embed the result.
	 *
	 * @param array $fragments Checkout fragments.
	 * @return array
	 */
	public function add_classic_fragment( array $fragments ): array {
		if ( ! self::is_adaptive_pricing_enabled() ) {
			return $fragments;
		}

		try {
			$data = $this->manager->synchronize();
		} catch ( Throwable $e ) {
			WC_Stripe_Logger::error( 'Native classic checkout session synchronization failed.', [ 'error_message' => $e->getMessage() ] );
			$data = $this->manager->mark_failed( $e->getMessage() );
		}

		$fragments[ self::CLASSIC_FRAGMENT_SELECTOR ] = $this->get_classic_fragment_html( $data );
		return $fragments;
	}

	/**
	 * Store API schema for the embedded Checkout Session data.
	 *
	 * @return array
	 */
	public static function get_store_api_schema(): array {
		return [
			'session_id'    => [
				'description' => __( 'Stripe Checkout Session ID.', 'woocommerce-gateway-stripe' ),
				'type'        => 'string',
				'readonly'    => true,
			],
			'client_secret' => [
				'description' => __( 'Stripe Checkout Session client secret.', 'woocommerce-gateway-stripe' ),
				'type'        => 'string',
				'readonly'    => true,
			],
			'revision'      => [
				'description' => __( 'Checkout Session revision.', 'woocommerce-gateway-stripe' ),
				'type'        => 'integer',
				'readonly'    => true,
			],
			'status'        => [
				'description' => __( 'Checkout Session synchronization status.', 'woocommerce-gateway-stripe' ),
				'type'        => 'string',
				'readonly'    => true,
			],
			'message'       => [
				'description' => __( 'Checkout Session synchronization message.', 'woocommerce-gateway-stripe' ),
				'type'        => 'string',
				'readonly'    => true,
			],
		];
	}

	/**
	 * Synchronize while WooCommerce is serializing its fully calculated Cart response.
	 *
	 * Once initialized, every native Cart response can update the Stripe session. This covers
	 * address, shipping, coupon, and quantity changes without guessing which client event caused it.
	 *
	 * @return array
	 */
	public static function get_store_api_data(): array {
		$manager = WC_Stripe_Checkout_Session_Manager::get_instance();
		$current = $manager->get_current_data();

		if ( ! self::$store_api_sync_requested && empty( $current['session_id'] ) ) {
			return $current;
		}

		if ( ! self::is_adaptive_pricing_enabled() ) {
			return $manager->mark_failed( WC_Stripe_Checkout_Session_Context::get_unavailable_message() );
		}

		try {
			return $manager->synchronize();
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Native Store API checkout session synchronization failed.', [ 'error_message' => $e->getMessage() ] );
			return $manager->mark_failed( $e->getMessage() );
		}
	}

	/**
	 * Mark this Cart Extensions request as the checkout initialization request.
	 *
	 * Synchronization is deferred to the CartSchema data callback because WooCommerce calculates
	 * totals after update callbacks and before serializing the response.
	 *
	 * @param array $data Extension request data.
	 * @return void
	 */
	public static function request_store_api_sync( array $data ): void {
		if ( 'sync' === ( $data['action'] ?? '' ) && self::is_adaptive_pricing_enabled() ) {
			self::$store_api_sync_requested = true;
		}
	}

	/**
	 * Build the classic checkout fragment with safely encoded session data.
	 *
	 * @param array $data Public session data.
	 * @return string
	 */
	private function get_classic_fragment_html( array $data ): string {
		$encoded_data = wp_json_encode( $data );
		if ( false === $encoded_data ) {
			$encoded_data = '{}';
		}

		return sprintf(
			'<div id="%s" data-checkout-session="%s" hidden></div>',
			esc_attr( ltrim( self::CLASSIC_FRAGMENT_SELECTOR, '#' ) ),
			esc_attr( $encoded_data )
		);
	}

	/**
	 * Validate the same checkout/cart eligibility used to render Adaptive Pricing on the page.
	 *
	 * Every caller runs without WordPress's page query: classic checkout refreshes arrive as
	 * wc-ajax requests against the home URL, and Store API cart requests are REST requests. In both
	 * is_checkout() is false even though we are serving checkout, so it has to be forced here. The
	 * temporary conditional is limited to eligibility checks; WooCommerce has already calculated
	 * the cart natively.
	 *
	 * @return bool
	 */
	private static function is_adaptive_pricing_enabled(): bool {
		$force_checkout_context = static function (): bool {
			return true;
		};

		add_filter( 'woocommerce_is_checkout', $force_checkout_context );
		try {
			$gateway = WC_Stripe::get_instance()->get_main_stripe_gateway();

			return $gateway instanceof WC_Stripe_UPE_Payment_Gateway
				&& $gateway->should_render_optimized_checkout()
				&& WC_Stripe_Helper::is_adaptive_pricing_supported();
		} finally {
			remove_filter( 'woocommerce_is_checkout', $force_checkout_context );
		}
	}
}
