<?php
/**
 * Defends against currency mismatches between the Stripe Express Checkout
 * Element's boot currency and the cart's resolved currency at order
 * placement, which can happen when a multi-currency plugin flips the cart
 * based on the shipping address chosen inside the wallet sheet.
 *
 * @package WooCommerce_Stripe/Express_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

/**
 * Asserts that the order's currency matches the currency that the Stripe
 * Express Checkout Element was created with. Throws a RouteException on
 * mismatch so order placement fails cleanly with a clear message.
 */
class WC_Stripe_Express_Checkout_Currency_Guard {

	/**
	 * Express checkout helper. Used to scope the assertion to ECE requests.
	 *
	 * @var WC_Stripe_Express_Checkout_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Constructor.
	 *
	 * @param WC_Stripe_Express_Checkout_Helper $express_checkout_helper Express checkout helper.
	 */
	public function __construct( WC_Stripe_Express_Checkout_Helper $express_checkout_helper ) {
		$this->express_checkout_helper = $express_checkout_helper;
	}

	/**
	 * Hook the assertion onto Store API checkout order builds.
	 *
	 * @return void
	 */
	public function init() {
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this, 'assert_currency_matches_element' ],
			20,
			2
		);
	}

	/**
	 * Compare the boot currency carried on the request to the order's
	 * resolved currency. Fail-open when no header was sent (older client,
	 * non-ECE caller).
	 *
	 * @param WC_Order                              $order   The order being created.
	 * @param WP_REST_Request<array<string, mixed>> $request The Store API request (unused).
	 *
	 * @return void
	 *
	 * @throws RouteException When the currencies disagree.
	 */
	public function assert_currency_matches_element( $order, $request ) {
		if ( ! $this->express_checkout_helper->is_express_checkout_context() ) {
			return;
		}

		$expected = strtolower(
			sanitize_text_field(
				wp_unslash( $_SERVER['HTTP_X_WCSTRIPE_PAYMENT_CURRENCY'] ?? '' )
			)
		);
		if ( '' === $expected ) {
			return;
		}

		$actual = strtolower( $order->get_currency() );
		if ( $expected === $actual ) {
			return;
		}

		WC_Stripe_Logger::error(
			'Express checkout currency mismatch at order placement.',
			[
				'order_id'         => $order->get_id(),
				'element_currency' => $expected,
				'order_currency'   => $actual,
			]
		);

		throw new RouteException(
			'wc_stripe_express_checkout_currency_mismatch',
			sprintf(
				/* translators: 1: expected currency code, 2: actual currency code */
				__(
					'Your shipping address requires a different currency (%2$s) than this payment was started in (%1$s). You haven\'t been charged. Please reload the page and try again.',
					'woocommerce-gateway-stripe'
				),
				strtoupper( $expected ),
				strtoupper( $actual )
			),
			400
		);
	}
}
