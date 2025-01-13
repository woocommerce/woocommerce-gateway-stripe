<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Express_Checkout_Helper class.
 */
class WC_Stripe_Express_Checkout_Helper {

	use WC_Stripe_Pre_Orders_Trait;

	/**
	 * Stripe settings.
	 *
	 * @var
	 */
	public $stripe_settings;

	/**
	 * Total label
	 *
	 * @var
	 */
	public $total_label;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$this->testmode        = WC_Stripe_Mode::is_test();
		$this->total_label     = ! empty( $this->stripe_settings['statement_descriptor'] ) ? WC_Stripe_Helper::clean_statement_descriptor( $this->stripe_settings['statement_descriptor'] ) : '';

		$this->total_label = str_replace( "'", '', $this->total_label ) . apply_filters( 'wc_stripe_payment_request_total_label_suffix', ' (via WooCommerce)' );

	}

	/**
	 * Checks whether authentication is required for checkout.
	 *
	 * @return bool
	 */
	public function is_authentication_required() {
		// If guest checkout is enabled, authentication is not required.
		if ( 'yes' === get_option( 'woocommerce_enable_guest_checkout', 'yes' ) ) {
			return false;
		}

		// If guest checkout is disabled and account creation upon checkout is not possible, authentication is required.
		return 'no' === get_option( 'woocommerce_enable_guest_checkout', 'yes' ) && ! $this->is_account_creation_possible();
	}

	/**
	 * Checks whether account creation is possible upon checkout.
	 *
	 * @return bool
	 */
	public function is_account_creation_possible() {
		// Check if account creation is allowed on checkout.
		$is_signup_on_checkout_allowed =
			'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' ) ||
			( $this->has_subscription_product() &&
				'yes' === get_option( 'woocommerce_enable_signup_from_checkout_for_subscriptions', 'no' ) );

		// Account creation is not possible for express checkout if we cannot automatically generate the username and password.
		$username_password_generation_enabled =
			'yes' === get_option( 'woocommerce_registration_generate_username', 'yes' ) &&
			'yes' === get_option( 'woocommerce_registration_generate_password', 'yes' );

		return $is_signup_on_checkout_allowed && $username_password_generation_enabled;
	}

	/**
	 * Gets the button type.
	 *
	 * @return  string
	 */
	public function get_button_type() {
		return isset( $this->stripe_settings['payment_request_button_type'] ) ? $this->stripe_settings['payment_request_button_type'] : 'default';
	}

	/**
	 * Gets the button theme.
	 *
	 * @return  string
	 */
	public function get_button_theme() {
		return isset( $this->stripe_settings['payment_request_button_theme'] ) ? $this->stripe_settings['payment_request_button_theme'] : 'dark';
	}

	/**
	 * Gets the button height.
	 *
	 * @return  string
	 */
	public function get_button_height() {
		$height = isset( $this->stripe_settings['payment_request_button_size'] ) ? $this->stripe_settings['payment_request_button_size'] : 'default';
		if ( 'small' === $height ) {
			return '40';
		}

		if ( 'large' === $height ) {
			return '56';
		}

		return '48';
	}

	/**
	 * Gets the button radius.
	 *
	 * @return string
	 */
	public function get_button_radius() {
		$height = isset( $this->stripe_settings['payment_request_button_size'] ) ? $this->stripe_settings['payment_request_button_size'] : 'default';
		if ( 'small' === $height ) {
			return '2';
		}

		if ( 'large' === $height ) {
			return '6';
		}

		return '4';
	}

	/**
	 * Gets total label.
	 *
	 * @return string
	 */
	public function get_total_label() {
		return $this->total_label;
	}

	/**
	 * Gets the product total price.
	 *
	 * @param object    $product         WC_Product_* object.
	 * @param bool|null $is_deposit      Whether this is a deposit.
	 * @param int       $deposit_plan_id Deposit plan ID.
	 *
	 * @return float Total price.
	 */
	public function get_product_price( $product, $is_deposit = null, $deposit_plan_id = 0 ) {
		// If prices should include tax, using tax inclusive price.
		if ( $this->cart_prices_include_tax() ) {
			$product_price = wc_get_price_including_tax( $product );
		} else {
			$product_price = wc_get_price_excluding_tax( $product );
		}

		// If WooCommerce Deposits is active, we need to get the correct price for the product.
		if ( class_exists( 'WC_Deposits_Product_Manager' ) && class_exists( 'WC_Deposits_Plans_Manager' ) && WC_Deposits_Product_Manager::deposits_enabled( $product->get_id() ) ) {
			// If is_deposit is null, we use the default deposit type for the product.
			if ( is_null( $is_deposit ) ) {
				$is_deposit = 'deposit' === WC_Deposits_Product_Manager::get_deposit_selected_type( $product->get_id() );
			}
			if ( $is_deposit ) {
				$deposit_type       = WC_Deposits_Product_Manager::get_deposit_type( $product->get_id() );
				$available_plan_ids = WC_Deposits_Plans_Manager::get_plan_ids_for_product( $product->get_id() );
				// Default to first (default) plan if no plan is specified.
				if ( 'plan' === $deposit_type && 0 === $deposit_plan_id && ! empty( $available_plan_ids ) ) {
					$deposit_plan_id = $available_plan_ids[0];
				}

				// Ensure the selected plan is available for the product.
				if ( 0 === $deposit_plan_id || in_array( $deposit_plan_id, $available_plan_ids, true ) ) {
					$product_price = WC_Deposits_Product_Manager::get_deposit_amount( $product, $deposit_plan_id, 'display', $product_price );
				}
			}
		}

		// Add subscription sign-up fees to product price.
		if ( in_array( $product->get_type(), [ 'subscription', 'subscription_variation' ] ) && class_exists( 'WC_Subscriptions_Product' ) ) {
			$product_price = (float) $product_price + (float) WC_Subscriptions_Product::get_sign_up_fee( $product );
		}

		return (float) $product_price;
	}

	/**
	 * Gets the product data for the currently viewed page
	 *
	 * @return  mixed Returns false if not on a product page, the product information otherwise.
	 */
	public function get_product_data() {
		if ( ! $this->is_product() ) {
			return false;
		}

		$product      = $this->get_product();
		$variation_id = 0;

		if ( in_array( $product->get_type(), [ 'variable', 'variable-subscription' ], true ) ) {
			$variation_attributes = $product->get_variation_attributes();
			$attributes           = [];

			foreach ( $variation_attributes as $attribute_name => $attribute_values ) {
				$attribute_key = 'attribute_' . sanitize_title( $attribute_name );

				// Passed value via GET takes precedence, then POST, otherwise get the default value for given attribute
				if ( isset( $_GET[ $attribute_key ] ) ) {
					$attributes[ $attribute_key ] = wc_clean( wp_unslash( $_GET[ $attribute_key ] ) );
				} elseif ( isset( $_POST[ $attribute_key ] ) ) {
					$attributes[ $attribute_key ] = wc_clean( wp_unslash( $_POST[ $attribute_key ] ) );
				} else {
					$attributes[ $attribute_key ] = $product->get_variation_default_attribute( $attribute_name );
				}
			}

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			if ( ! empty( $variation_id ) ) {
				$product = wc_get_product( $variation_id );
			}
		}

		$data     = [];
		$items    = [];
		$price    = $this->get_product_price( $product );
		$currency = get_woocommerce_currency();
		$total_tax = 0;

		$items[] = [
			'label'  => $product->get_name(),
			'amount' => WC_Stripe_Helper::get_stripe_amount( $price ),
		];

		foreach ( $this->get_taxes_like_cart( $product, $price ) as $tax ) {
			$total_tax += $tax;

			$items[] = [
				'label'   => __( 'Tax', 'woocommerce-gateway-stripe' ),
				'amount'  => WC_Stripe_Helper::get_stripe_amount( $tax, $currency ),
				'pending' => 0 === $tax,
			];
		}

		if ( wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping() ) {
			$items[] = [
				'label'   => __( 'Shipping', 'woocommerce-gateway-stripe' ),
				'amount'  => 0,
				'pending' => true,
			];

			$data['shippingOptions'] = [ $this->get_default_shipping_option() ];
		}

		$data['displayItems'] = $items;
		$data['total']        = [
			'label'  => apply_filters( 'wc_stripe_payment_request_total_label', $this->total_label ),
			'amount' => WC_Stripe_Helper::get_stripe_amount( $price + $total_tax, $currency ),
			'pending' => true,
		];

		$data['requestShipping'] = ( wc_shipping_enabled() && $product->needs_shipping() && 0 !== wc_get_shipping_method_count( true ) );
		$data['currency']        = strtolower( $currency );
		$data['country_code']    = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

		// On product page load, if there's a variation already selected, check if it's supported.
		$data['validVariationSelected'] = ! empty( $variation_id ) ? $this->is_product_supported( $product ) : true;

		return apply_filters( 'wc_stripe_payment_request_product_data', $data, $product );
	}

	/**
	 * JS params data used by cart and checkout pages.
	 *
	 * @param array $data
	 */
	public function get_checkout_data() {
		$data = [
			'url'                     => wc_get_checkout_url(),
			'currency_code'           => strtolower( get_woocommerce_currency() ),
			'country_code'            => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			'needs_shipping'          => 'no',
			'needs_payer_phone'       => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ),
			'default_shipping_option' => $this->get_default_shipping_option(),
		];

		if ( ! is_null( WC()->cart ) && WC()->cart->needs_shipping() ) {
			$data['needs_shipping'] = 'yes';
		}

		return $data;
	}

	/**
	 * Default shipping option, used by product, cart and checkout pages.
	 *
	 * @return void|array
	 */
	private function get_default_shipping_option() {
		if ( wc_get_shipping_method_count( true, true ) === 0 ) {
			return null;
		}

		return [
			'id'          => 'pending',
			'displayName' => __( 'Pending', 'woocommerce-gateway-stripe' ),
			'amount'      => 0,
		];
	}

	/**
	 * Normalizes postal code in case of redacted data from Apple Pay.
	 *
	 * @param string $postcode Postal code.
	 * @param string $country Country.
	 */
	public function get_normalized_postal_code( $postcode, $country ) {
		/**
		 * Currently, Apple Pay truncates the UK and Canadian postal codes to the first 4 and 3 characters respectively
		 * Apple Pay also truncates Canadian postal codes to the first 4 characters.
		 * when passing it back from the shippingcontactselected object. This causes WC to invalidate
		 * the postal code and not calculate shipping zones correctly.
		 */
		if ( 'GB' === $country ) {
			// UK Postcodes returned from Apple Pay can be alpha numeric 2 chars, 3 chars, or 4 chars long will optionally have a trailing space,
			// depending on whether the customer put a space in their postcode between the outcode and incode part.
			// See https://assets.publishing.service.gov.uk/media/5a7b997d40f0b62826a049e0/ILRSpecification2013_14Appendix_C_Dec2012_v1.pdf for more details.

			// Here is a table showing the functionality by example:
			//  Original  | Apple Pay |  Normalized
			// 'LN10 1AA' |  'LN10 '  |  'LN10 ***'
			// 'LN101AA'  |  'LN10'   |  'LN10 ***'
			// 'W10 2AA'  |  'W10 '   |  'W10 ***'
			// 'W102AA'   |  'W10'    |  'W10 ***'
			// 'N2 3AA    |  'N2 '    |  'N2 ***'
			// 'N23AA     |  'N2'     |  'N2 ***'

			$spaceless_postcode = preg_replace( '/\s+/', '', $postcode );

			if ( strlen( $spaceless_postcode ) < 5 ) {
				// Always reintroduce the space so that Shipping Zones regex like 'N1 *' work to match N1 postcodes like N1 1AA, but don't match N10 postcodes like N10 1AA
				return $spaceless_postcode . ' ***';
			}

			return $postcode; // 5 or more chars means it probably wasn't redacted and will likely validate unchanged.
		}

		if ( 'CA' === $country ) {
			// Replaces a redacted string with something like L4Y***.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 6, '*' );
		}

		return $postcode;
	}

	/**
	 * Checks to make sure product type is supported.
	 *
	 * @return  array
	 */
	public function supported_product_types() {
		return apply_filters(
			'wc_stripe_payment_request_supported_types',
			[
				'simple',
				'variable',
				'variation',
				'subscription',
				'variable-subscription',
				'subscription_variation',
				'booking',
				'bundle',
				'composite',
			]
		);
	}

	/**
	 * Checks the cart to see if all items are allowed to be used.
	 *
	 * @return  boolean
	 */
	public function allowed_items_in_cart() {
		// Pre Orders compatibility where we don't support charge upon release.
		if ( $this->is_pre_order_item_in_cart() && $this->is_pre_order_product_charged_upon_release( $this->get_pre_order_product_from_cart() ) ) {
			return false;
		}

		// If the cart is not available or if the cart is empty we don't have any unsupported products in the cart, so we
		// return true. This can happen e.g. when loading the cart or checkout blocks in Gutenberg.
		if ( is_null( WC()->cart ) || WC()->cart->is_empty() ) {
			return true;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( ! in_array( $_product->get_type(), $this->supported_product_types() ) ) {
				return false;
			}

			// Subscriptions with a trial period that need shipping are not supported.
			if ( $this->is_invalid_subscription_product( $_product ) ) {
				return false;
			}
		}

		// We don't support multiple packages with express checkout buttons because we can't offer
		// a good UX.
		$packages = WC()->cart->get_shipping_packages();
		if ( 1 < count( $packages ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns true if the given product is a subscription that cannot be purchased with express checkout buttons.
	 *
	 * Invalid subscription products include those with:
	 *  - a free trial that requires shipping (synchronised subscriptions with a delayed first payment are considered to have a free trial)
	 *  - a synchronised subscription with no upfront payment and is virtual (this limitation only applies to the product page as we cannot calculate totals correctly)
	 *
	 * If the product is a variable subscription, this function will return true if all of its variations have a trial and require shipping.
	 *
	 * @since 7.8.0
	 *
	 * @param WC_Product|null $product                 Product object.
	 * @param boolean         $is_product_page_request Whether this is a request from the product page.
	 *
	 * @return boolean
	 */
	public function is_invalid_subscription_product( $product, $is_product_page_request = false ) {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) || ! class_exists( 'WC_Subscriptions_Synchroniser' ) || ! WC_Subscriptions_Product::is_subscription( $product ) ) {
			return false;
		}

		$is_invalid = true;

		if ( $product->get_type() === 'variable-subscription' ) {
			$products = $product->get_available_variations( 'object' );
		} else {
			$products = [ $product ];
		}

		foreach ( $products as $product ) {
			$needs_shipping     = $product->needs_shipping();
			$is_synced          = WC_Subscriptions_Synchroniser::is_product_synced( $product );
			$is_payment_upfront = WC_Subscriptions_Synchroniser::is_payment_upfront( $product );
			$has_trial_period   = WC_Subscriptions_Product::get_trial_length( $product ) > 0;

			if ( $is_product_page_request && $is_synced && ! $is_payment_upfront && ! $needs_shipping ) {
				/**
				 * This condition prevents the purchase of virtual synced subscription products with no upfront costs via express checkout buttons from the product page.
				 *
				 * The main issue is that calling $product->get_price() on a synced subscription does not take into account a mock trial period or prorated price calculations
				 * until the product is in the cart. This means that the totals passed to express checkout element are incorrect when purchasing from the product page.
				 * Another part of the problem is because the product is virtual this stops the Stripe PaymentRequest API from triggering the necessary `shippingaddresschange` event
				 * which is when we call WC()->cart->calculate_totals(); which would fix the totals.
				 *
				 * The fix here is to not allow virtual synced subscription products with no upfront costs to be purchased via express checkout buttons on the product page.
				 */
				continue;
			} elseif ( $is_synced && ! $is_payment_upfront && $needs_shipping ) {
				continue;
			} elseif ( $has_trial_period && $needs_shipping ) {
				continue;
			} else {
				// If we made it this far, the product is valid. Break out of the foreach and return early as we only care about invalid cases.
				$is_invalid = false;
				break;
			}
		}

		return $is_invalid;
	}

	/**
	 * Checks whether cart contains a subscription product or this is a subscription product page.
	 *
	 * @return boolean
	 */
	public function has_subscription_product() {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}

		if ( $this->is_product() ) {
			$product = $this->get_product();
			if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
				return true;
			}
		} elseif ( WC_Stripe_Helper::has_cart_or_checkout_on_current_page() ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				if ( WC_Subscriptions_Product::is_subscription( $_product ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks if this is a product page or content contains a product_page shortcode.
	 *
	 * @return boolean
	 */
	public function is_product() {
		return is_product() || wc_post_content_has_shortcode( 'product_page' );
	}

	/**
	 * Get product from product page or product_page shortcode.
	 *
	 * @return WC_Product Product object.
	 */
	public function get_product() {
		global $post;

		if ( is_product() ) {
			return wc_get_product( $post->ID );
		} elseif ( wc_post_content_has_shortcode( 'product_page' ) ) {
			// Get id from product_page shortcode.
			preg_match( '/\[product_page id="(?<id>\d+)"\]/', $post->post_content, $shortcode_match );

			if ( ! isset( $shortcode_match['id'] ) ) {
				return false;
			}

			return wc_get_product( $shortcode_match['id'] );
		}

		return false;
	}

	/**
	 * Returns true if the current page supports Express Checkout Buttons, false otherwise.
	 *
	 * @return  boolean  True if the current page is supported, false otherwise.
	 */
	public function is_page_supported() {
		return $this->is_product()
			|| WC_Stripe_Helper::has_cart_or_checkout_on_current_page()
			|| is_wc_endpoint_url( 'order-pay' );
	}

	/**
	 * Returns true if express checkout elements are supported on the current page, false
	 * otherwise.
	 *
	 * @return  boolean  True if express checkout elements are supported on current page, false otherwise
	 */
	public function should_show_express_checkout_button() {
		// Bail if account is not connected.
		if ( ! WC_Stripe::get_instance()->connect->is_connected() ) {
			WC_Stripe_Logger::log( 'Account is not connected.' );
			return false;
		}

		// If no SSL bail.
		if ( ! $this->testmode && ! is_ssl() ) {
			WC_Stripe_Logger::log( 'Stripe Express Checkout live mode requires SSL. ' . print_r( [ 'url' => get_permalink() ], true ) );
			return false;
		}

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( ! isset( $available_gateways['stripe'] ) ) {
			return false;
		}

		// Don't show if on the cart or checkout page, or if page contains the cart or checkout
		// shortcodes, with items in the cart that aren't supported.
		if (
			WC_Stripe_Helper::has_cart_or_checkout_on_current_page()
			&& ! $this->allowed_items_in_cart()
		) {
			return false;
		}

		// Don't show on cart if disabled.
		if ( is_cart() && ! $this->should_show_ece_on_cart_page() ) {
			return false;
		}

		// Don't show on checkout if disabled.
		if ( is_checkout() && ! $this->should_show_ece_on_checkout_page() ) {
			return false;
		}

		// Don't show if product page ECE is disabled.
		if ( $this->is_product() && ! $this->should_show_ece_on_product_pages() ) {
			return false;
		}

		// Don't show if product on current page is not supported.
		if ( $this->is_product() && ! $this->is_product_supported( $this->get_product() ) ) {
			return false;
		}

		// Don't show if the total price is 0.
		// ToDo: support free trials. Free trials should be supported if the product does not require shipping.
		if ( ( ! ( $this->is_pay_for_order_page() || $this->is_product() ) && isset( WC()->cart ) && 0.0 === (float) WC()->cart->get_total( false ) )
			|| ( $this->is_product() && 0.0 === (float) $this->get_product()->get_price() )
		) {
			return false;
		}

		if ( $this->is_product() && in_array( $this->get_product()->get_type(), [ 'variable', 'variable-subscription' ], true ) ) {
			$stock_availability = array_column( $this->get_product()->get_available_variations(), 'is_in_stock' );
			// Don't show if all product variations are out-of-stock.
			if ( ! in_array( true, $stock_availability, true ) ) {
				return false;
			}
		}

		// Hide if cart/product doesn't require shipping and tax is based on billing or shipping address.
		if (
			! $this->is_pay_for_order_page() &&
			(
				( is_product() && ! $this->product_needs_shipping( $this->get_product() ) ) ||
				( ( is_cart() || is_checkout() ) && ! WC()->cart->needs_shipping() )
			) &&
			wc_tax_enabled() &&
			in_array( get_option( 'woocommerce_tax_based_on' ), [ 'billing', 'shipping' ], true )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the passed product needs to be shipped.
	 *
	 * @param WC_Product $product The product to check.
	 *
	 * @return bool Returns true if the product requires shipping; otherwise, returns false.
	 */
	public function product_needs_shipping( WC_Product $product ) {
		if ( ! $product ) {
			return false;
		}

		return wc_shipping_enabled() && 0 !== wc_get_shipping_method_count( true ) && $product->needs_shipping();
	}

	/**
	 * Returns true if express checkout buttons are enabled on the cart page, false
	 * otherwise.
	 *
	 * @return  boolean  True if express checkout buttons are enabled on the cart page, false otherwise
	 */
	public function should_show_ece_on_cart_page() {
		$should_show_on_cart_page = in_array( 'cart', $this->get_button_locations(), true );

		return apply_filters(
			'wc_stripe_show_payment_request_on_cart',
			$should_show_on_cart_page
		);
	}

	/**
	 * Returns true if express checkout buttons are enabled on the checkout page, false
	 * otherwise.
	 *
	 * @return  boolean  True if express checkout buttons are enabled on the checkout page, false otherwise
	 */
	public function should_show_ece_on_checkout_page() {
		global $post;

		$should_show_on_checkout_page = in_array( 'checkout', $this->get_button_locations(), true );

		return apply_filters(
			'wc_stripe_show_payment_request_on_checkout',
			$should_show_on_checkout_page,
			$post
		);
	}

	/**
	 * Returns true if express checkout buttons are enabled on product pages, false
	 * otherwise.
	 *
	 * @return  boolean  True if express checkout buttons are enabled on product pages, false otherwise
	 */
	public function should_show_ece_on_product_pages() {
		global $post;

		$should_show_on_product_page = in_array( 'product', $this->get_button_locations(), true );

		// Note the negation because if the filter returns `true` that means we should hide the PRB.
		return ! apply_filters(
			'wc_stripe_hide_payment_request_on_product_page',
			! $should_show_on_product_page,
			$post
		);
	}

	/**
	 * Returns true if a the provided product is supported, false otherwise.
	 *
	 * @param WC_Product $param  The product that's being checked for support.
	 *
	 * @return boolean  True if the provided product is supported, false otherwise.
	 */
	public function is_product_supported( $product ) {
		if ( ! is_object( $product ) || ! in_array( $product->get_type(), $this->supported_product_types() ) ) {
			return false;
		}

		// Trial subscriptions with shipping are not supported.
		if ( $this->is_invalid_subscription_product( $product, true ) ) {
			return false;
		}

		// Pre Orders charge upon release not supported.
		if ( $this->is_pre_order_product_charged_upon_release( $product ) ) {
			return false;
		}

		// Composite products are not supported on the product page.
		if ( class_exists( 'WC_Composite_Products' ) && function_exists( 'is_composite_product' ) && is_composite_product() ) {
			return false;
		}

		// File upload addon not supported
		if ( class_exists( 'WC_Product_Addons_Helper' ) ) {
			$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );
			foreach ( $product_addons as $addon ) {
				if ( 'file_upload' === $addon['type'] ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Gets shipping options available for specified shipping address
	 *
	 * @param array   $shipping_address       Shipping address.
	 * @param boolean $itemized_display_items Indicates whether to show subtotals or itemized views.
	 *
	 * @return array Shipping options data.
	 *
	 * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag
	 */
	public function get_shipping_options( $shipping_address, $itemized_display_items = false ) {
		try {
			// Set the shipping options.
			$data = [];

			// Remember current shipping method before resetting.
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', [] );
			$this->calculate_shipping( apply_filters( 'wc_stripe_payment_request_shipping_posted_values', $shipping_address ) );

			$packages          = WC()->shipping->get_packages();
			$shipping_rate_ids = [];

			if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
				foreach ( $packages as $package ) {
					if ( empty( $package['rates'] ) ) {
						throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-gateway-stripe' ) );
					}

					foreach ( $package['rates'] as $rate ) {
						if ( in_array( $rate->id, $shipping_rate_ids, true ) ) {
							// The Payment Requests will try to load indefinitely if there are duplicate shipping option IDs.
							throw new Exception( __( 'Unable to provide shipping options for Payment Requests.', 'woocommerce-gateway-stripe' ) );
						}

						$shipping_rate_ids[]        = $rate->id;
						$data['shipping_options'][] = [
							'id'          => $rate->id,
							'displayName' => $rate->label,
							'amount'      => WC_Stripe_Helper::get_stripe_amount( $rate->cost, get_woocommerce_currency() ),
						];
					}
				}
			} else {
				throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-gateway-stripe' ) );
			}

			// The first shipping option is automatically applied on the client.
			// Keep chosen shipping method by sorting shipping options if the method still available for new address.
			// Fallback to the first available shipping method.
			if ( isset( $data['shipping_options'][0] ) ) {
				if ( isset( $chosen_shipping_methods[0] ) ) {
					$chosen_method_id         = $chosen_shipping_methods[0];
					$compare_shipping_options = function ( $a, $b ) use ( $chosen_method_id ) {
						if ( $a['id'] === $chosen_method_id ) {
							return -1;
						}

						if ( $b['id'] === $chosen_method_id ) {
							return 1;
						}

						return 0;
					};
					usort( $data['shipping_options'], $compare_shipping_options );
				}

				$first_shipping_method_id = $data['shipping_options'][0]['id'];
				$this->update_shipping_method( [ $first_shipping_method_id ] );
			}

			WC()->cart->calculate_totals();

			$this->maybe_restore_recurring_chosen_shipping_methods( $chosen_shipping_methods );

			$data          += $this->build_display_items( $itemized_display_items );
			$data['result'] = 'success';
		} catch ( Exception $e ) {
			$data          += $this->build_display_items( $itemized_display_items );
			$data['result'] = 'invalid_shipping_address';
		}

		return $data;
	}

	/**
	 * Updates shipping method in WC session
	 *
	 * @param array $shipping_methods Array of selected shipping methods ids.
	 */
	public function update_shipping_method( $shipping_methods ) {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $shipping_methods ) ) {
			foreach ( $shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}

	/**
	 * Normalizes billing and shipping state fields.
	 */
	public function normalize_state() {
		$billing_country  = ! empty( $_POST['billing_country'] ) ? wc_clean( wp_unslash( $_POST['billing_country'] ) ) : '';
		$shipping_country = ! empty( $_POST['shipping_country'] ) ? wc_clean( wp_unslash( $_POST['shipping_country'] ) ) : '';
		$billing_state    = ! empty( $_POST['billing_state'] ) ? wc_clean( wp_unslash( $_POST['billing_state'] ) ) : '';
		$shipping_state   = ! empty( $_POST['shipping_state'] ) ? wc_clean( wp_unslash( $_POST['shipping_state'] ) ) : '';

		// Due to a bug in Apple Pay, the "Region" part of a Hong Kong address is delivered in
		// `shipping_postcode`, so we need some special case handling for that. According to
		// our sources at Apple Pay people will sometimes use the district or even sub-district
		// for this value. As such we check against all regions, districts, and sub-districts
		// with both English and Mandarin spelling.
		//
		// @reykjalin: The check here is quite elaborate in an attempt to make sure this doesn't break once
		// Apple Pay fixes the bug that causes address values to be in the wrong place. Because of that the
		// algorithm becomes:
		//   1. Use the supplied state if it's valid (in case Apple Pay bug is fixed)
		//   2. Use the value supplied in the postcode if it's a valid HK region (equivalent to a WC state).
		//   3. Fall back to the value supplied in the state. This will likely cause a validation error, in
		//      which case a merchant can reach out to us so we can either: 1) add whatever the customer used
		//      as a state to our list of valid states; or 2) let them know the customer must spell the state
		//      in some way that matches our list of valid states.
		//
		// @reykjalin: This HK specific sanitazation *should be removed* once Apple Pay fix
		// the address bug. More info on that in pc4etw-bY-p2.
		if ( 'HK' === $billing_country ) {
			include_once WC_STRIPE_PLUGIN_PATH . '/includes/constants/class-wc-stripe-hong-kong-states.php';

			if ( ! WC_Stripe_Hong_Kong_States::is_valid_state( strtolower( $billing_state ) ) ) {
				$billing_postcode = ! empty( $_POST['billing_postcode'] ) ? wc_clean( wp_unslash( $_POST['billing_postcode'] ) ) : '';
				if ( WC_Stripe_Hong_Kong_States::is_valid_state( strtolower( $billing_postcode ) ) ) {
					$billing_state = $billing_postcode;
				}
			}
		}
		if ( 'HK' === $shipping_country ) {
			include_once WC_STRIPE_PLUGIN_PATH . '/includes/constants/class-wc-stripe-hong-kong-states.php';

			if ( ! WC_Stripe_Hong_Kong_States::is_valid_state( strtolower( $shipping_state ) ) ) {
				$shipping_postcode = ! empty( $_POST['shipping_postcode'] ) ? wc_clean( wp_unslash( $_POST['shipping_postcode'] ) ) : '';
				if ( WC_Stripe_Hong_Kong_States::is_valid_state( strtolower( $shipping_postcode ) ) ) {
					$shipping_state = $shipping_postcode;
				}
			}
		}

		// Finally we normalize the state value we want to process.
		if ( $billing_state && $billing_country ) {
			$_POST['billing_state'] = $this->get_normalized_state( $billing_state, $billing_country );
		}

		if ( $shipping_state && $shipping_country ) {
			$_POST['shipping_state'] = $this->get_normalized_state( $shipping_state, $shipping_country );
		}
	}

	/**
	 * Checks if given state is normalized.
	 *
	 * @param string $state State.
	 * @param string $country Two-letter country code.
	 *
	 * @return bool Whether state is normalized or not.
	 */
	public function is_normalized_state( $state, $country ) {
		$wc_states = WC()->countries->get_states( $country );
		return (
			is_array( $wc_states ) &&
			in_array( $state, array_keys( $wc_states ), true )
		);
	}

	/**
	 * Sanitize string for comparison.
	 *
	 * @param string $string String to be sanitized.
	 *
	 * @return string The sanitized string.
	 */
	public function sanitize_string( $string ) {
		return trim( wc_strtolower( remove_accents( $string ) ) );
	}

	/**
	 * Get normalized state from express checkout API dropdown list of states.
	 *
	 * @param string $state   Full state name or state code.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state or original state input value.
	 */
	public function get_normalized_state_from_pr_states( $state, $country ) {
		// Include Payment Request API State list for compatibility with WC countries/states.
		include_once WC_STRIPE_PLUGIN_PATH . '/includes/constants/class-wc-stripe-payment-request-button-states.php';
		$pr_states = WC_Stripe_Payment_Request_Button_States::STATES;

		if ( ! isset( $pr_states[ $country ] ) ) {
			return $state;
		}

		foreach ( $pr_states[ $country ] as $wc_state_abbr => $pr_state ) {
			$sanitized_state_string = $this->sanitize_string( $state );
			// Checks if input state matches with Payment Request state code (0), name (1) or localName (2).
			if (
				( ! empty( $pr_state[0] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[0] ) ) ||
				( ! empty( $pr_state[1] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[1] ) ) ||
				( ! empty( $pr_state[2] ) && $sanitized_state_string === $this->sanitize_string( $pr_state[2] ) )
			) {
				return $wc_state_abbr;
			}
		}

		return $state;
	}

	/**
	 * Get normalized state from WooCommerce list of translated states.
	 *
	 * @param string $state   Full state name or state code.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state or original state input value.
	 */
	public function get_normalized_state_from_wc_states( $state, $country ) {
		$wc_states = WC()->countries->get_states( $country );

		if ( is_array( $wc_states ) ) {
			foreach ( $wc_states as $wc_state_abbr => $wc_state_value ) {
				if ( preg_match( '/' . preg_quote( $wc_state_value, '/' ) . '/i', $state ) ) {
					return $wc_state_abbr;
				}
			}
		}

		return $state;
	}

	/**
	 * Gets the normalized state/county field because in some
	 * cases, the state/county field is formatted differently from
	 * what WC is expecting and throws an error. An example
	 * for Ireland, the county dropdown in Chrome shows "Co. Clare" format.
	 *
	 * @param string $state   Full state name or an already normalized abbreviation.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state abbreviation.
	 */
	public function get_normalized_state( $state, $country ) {
		// If it's empty or already normalized, skip.
		if ( ! $state || $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// Try to match state from the Payment Request API list of states.
		$state = $this->get_normalized_state_from_pr_states( $state, $country );

		// If it's normalized, return.
		if ( $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// If the above doesn't work, fallback to matching against the list of translated
		// states from WooCommerce.
		return $this->get_normalized_state_from_wc_states( $state, $country );
	}

	/**
	 * The express checkout API provides its own validation for the address form.
	 * For some countries, it might not provide a state field, so we need to return a more descriptive
	 * error message, indicating that the express checkout button is not supported for that country.
	 */
	public function validate_state() {
		$wc_checkout     = WC_Checkout::instance();
		$posted_data     = $wc_checkout->get_posted_data();
		$checkout_fields = $wc_checkout->get_checkout_fields();
		$countries       = WC()->countries->get_countries();

		$is_supported = true;
		// Checks if billing state is missing and is required.
		if ( ! empty( $checkout_fields['billing']['billing_state']['required'] ) && '' === $posted_data['billing_state'] ) {
			$is_supported = false;
		}

		// Checks if shipping state is missing and is required.
		if ( WC()->cart->needs_shipping_address() && ! empty( $checkout_fields['shipping']['shipping_state']['required'] ) && '' === $posted_data['shipping_state'] ) {
			$is_supported = false;
		}

		if ( ! $is_supported ) {
			wc_add_notice(
				sprintf(
					/* translators: 1) country. */
					__( 'The Express Checkout button is not supported in %1$s because some required fields couldn\'t be verified. Please proceed to the checkout page and try again.', 'woocommerce-gateway-stripe' ),
					isset( $countries[ $posted_data['billing_country'] ] ) ? $countries[ $posted_data['billing_country'] ] : $posted_data['billing_country']
				),
				'error'
			);
		}
	}

	/**
	 * Performs special mapping for address fields for specific contexts.
	 */
	public function fix_address_fields_mapping() {
		$billing_country  = ! empty( $_POST['billing_country'] ) ? wc_clean( wp_unslash( $_POST['billing_country'] ) ) : '';
		$shipping_country = ! empty( $_POST['shipping_country'] ) ? wc_clean( wp_unslash( $_POST['shipping_country'] ) ) : '';

		// For UAE, Google Pay stores the emirate in "region", which gets mapped to the "state" field,
		// but WooCommerce expects it in the "city" field.
		if ( 'AE' === $billing_country ) {
			$billing_state = ! empty( $_POST['billing_state'] ) ? wc_clean( wp_unslash( $_POST['billing_state'] ) ) : '';
			$billing_city  = ! empty( $_POST['billing_city'] ) ? wc_clean( wp_unslash( $_POST['billing_city'] ) ) : '';

			// Move the state (emirate) to the city field.
			if ( empty( $billing_city ) && ! empty( $billing_state ) ) {
				$_POST['billing_city']  = $billing_state;
				$_POST['billing_state'] = '';
			}
		}

		if ( 'AE' === $shipping_country ) {
			$shipping_state = ! empty( $_POST['shipping_state'] ) ? wc_clean( wp_unslash( $_POST['shipping_state'] ) ) : '';
			$shipping_city  = ! empty( $_POST['shipping_city'] ) ? wc_clean( wp_unslash( $_POST['shipping_city'] ) ) : '';

			// Move the state (emirate) to the city field.
			if ( empty( $shipping_city ) && ! empty( $shipping_state ) ) {
				$_POST['shipping_city']  = $shipping_state;
				$_POST['shipping_state'] = '';
			}
		}
	}

	/**
	 * Calculate and set shipping method.
	 *
	 * @param array $address Shipping address.
	 */
	protected function calculate_shipping( $address = [] ) {
		$country   = $address['country'];
		$state     = $address['state'];
		$postcode  = $address['postcode'];
		$city      = $address['city'];
		$address_1 = $address['address'];
		$address_2 = $address['address_2'];

		// Normalizes state to calculate shipping zones.
		$state = $this->get_normalized_state( $state, $country );

		// Normalizes postal code in case of redacted data from Apple Pay.
		$postcode = $this->get_normalized_postal_code( $postcode, $country );

		WC()->shipping->reset_shipping();

		if ( $postcode && WC_Validation::is_postcode( $postcode, $country ) ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		$packages = [];

		$packages[0]['contents']                 = WC()->cart->get_cart();
		$packages[0]['contents_cost']            = 0;
		$packages[0]['applied_coupons']          = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']               = get_current_user_id();
		$packages[0]['destination']['country']   = $country;
		$packages[0]['destination']['state']     = $state;
		$packages[0]['destination']['postcode']  = $postcode;
		$packages[0]['destination']['city']      = $city;
		$packages[0]['destination']['address']   = $address_1;
		$packages[0]['destination']['address_2'] = $address_2;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $item['data']->needs_shipping() ) {
				if ( isset( $item['line_total'] ) ) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		$packages = apply_filters( 'woocommerce_cart_shipping_packages', $packages );

		WC()->shipping->calculate_shipping( $packages );
	}

	/**
	 * The settings for the `button` attribute.
	 *
	 * @return array
	 */
	public function get_button_settings() {
		$button_type = $this->get_button_type();
		return [
			'type'         => $button_type,
			'theme'        => $this->get_button_theme(),
			'height'       => $this->get_button_height(),
			'radius'       => $this->get_button_radius(),
			// Default format is en_US.
			'locale'       => apply_filters( 'wc_stripe_payment_request_button_locale', substr( get_locale(), 0, 2 ) ),
		];
	}

	/**
	 * Checks if this is the Pay for Order page.
	 *
	 * @return boolean
	 */
	public function is_pay_for_order_page() {
		return is_checkout() && isset( $_GET['pay_for_order'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Checks if this is the checkout page or content contains a checkout block.
	 *
	 * @return boolean
	 */
	public function is_checkout() {
		return is_checkout() || has_block( 'woocommerce/checkout' );
	}

	/**
	 * Builds the shippings methods to pass to express checkout elements.
	 */
	protected function build_shipping_methods( $shipping_methods ) {
		if ( empty( $shipping_methods ) ) {
			return [];
		}

		$shipping = [];

		foreach ( $shipping_methods as $method ) {
			$shipping[] = [
				'id'     => $method['id'],
				'label'  => $method['label'],
				'detail' => '',
				'amount' => WC_Stripe_Helper::get_stripe_amount( $method['amount']['value'] ),
			];
		}

		return $shipping;
	}

	/**
	 * Builds the line items to pass to express checkout elements.
	 */
	public function build_display_items( $itemized_display_items = false ) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$items         = [];
		$lines         = [];
		$subtotal      = 0;
		$discounts     = 0;
		$display_items = ! apply_filters( 'wc_stripe_payment_request_hide_itemization', true ) || $itemized_display_items;
		$has_deposits  = false;

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			// Hide itemization/subtotals for Apple Pay and Google Pay when deposits are present.
			if ( ! empty( $cart_item['is_deposit'] ) ) {
				$has_deposits = true;
				continue;
			}

			$subtotal      += $cart_item['line_subtotal'];
			$amount         = $cart_item['line_subtotal'];
			$quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';
			$product_name   = $cart_item['data']->get_name();

			$lines[] = [
				'label'  => $product_name . $quantity_label,
				'amount' => WC_Stripe_Helper::get_stripe_amount( $amount ),
			];
		}

		if ( $display_items && ! $has_deposits ) {
			$items = array_merge( $items, $lines );
		} elseif ( ! $has_deposits ) { // If the cart contains a deposit, the subtotal will be different to the cart total and will throw an error.
			$items[] = [
				'label'  => 'Subtotal',
				'amount' => WC_Stripe_Helper::get_stripe_amount( $subtotal ),
			];
		}

		$applied_coupons = array_values( WC()->cart->get_coupon_discount_totals() );

		foreach ( $applied_coupons as $amount ) {
			$discounts += (float) $amount;
		}

		$discounts   = wc_format_decimal( $discounts, WC()->cart->dp );
		$tax         = wc_format_decimal( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp );
		$shipping    = wc_format_decimal( WC()->cart->shipping_total, WC()->cart->dp );
		$items_total = wc_format_decimal( WC()->cart->cart_contents_total, WC()->cart->dp ) + $discounts;
		$order_total = WC()->cart->get_total( false );

		if ( wc_tax_enabled() ) {
			$items[] = [
				'label'  => esc_html( __( 'Tax', 'woocommerce-gateway-stripe' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $tax ),
			];
		}

		if ( WC()->cart->needs_shipping() ) {
			$items[] = [
				'key'    => 'total_shipping',
				'label'  => esc_html( __( 'Shipping', 'woocommerce-gateway-stripe' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $shipping ),
			];
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = [
				'key'    => 'total_discount',
				'label'  => esc_html( __( 'Discount', 'woocommerce-gateway-stripe' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $discounts ),
			];
		}

		$cart_fees = WC()->cart->get_fees();

		// Include fees and taxes as display items.
		foreach ( $cart_fees as $key => $fee ) {
			$items[] = [
				'label'  => $fee->name,
				'amount' => WC_Stripe_Helper::get_stripe_amount( $fee->amount ),
			];
		}

		return [
			'displayItems' => $items,
			'total'        => [
				'label'   => $this->total_label,
				'amount'  => max( 0, apply_filters( 'woocommerce_stripe_calculated_total', WC_Stripe_Helper::get_stripe_amount( $order_total ), $order_total, WC()->cart ) ),
				'pending' => false,
			],
		];
	}

	/**
	 * Settings array for the user authentication dialog and redirection.
	 *
	 * @return array
	 */
	public function get_login_confirmation_settings() {
		if ( is_user_logged_in() || ! $this->is_authentication_required() ) {
			return false;
		}

		/* translators: The text encapsulated in `**` can be replaced with "Apple Pay" or "Google Pay". Please translate this text, but don't remove the `**`. */
		$message      = __( 'To complete your transaction with **the selected payment method**, you must log in or create an account with our site.', 'woocommerce-gateway-stripe' );
		$redirect_url = add_query_arg(
			[
				'_wpnonce'                               => wp_create_nonce( 'wc-stripe-set-redirect-url' ),
				'wc_stripe_express_checkout_redirect_url' => rawurlencode( home_url( add_query_arg( [] ) ) ), // Current URL to redirect to after login.
			],
			home_url()
		);

		return [
			'message'      => $message,
			'redirect_url' => wp_sanitize_redirect( esc_url_raw( $redirect_url ) ),
		];
	}

	/**
	 * Pages where the express checkout buttons should be displayed.
	 *
	 * @return array
	 */
	public function get_button_locations() {
		// If the locations have not been set return the default setting.
		if ( ! isset( $this->stripe_settings['payment_request_button_locations'] ) ) {
			return [ 'product', 'cart' ];
		}

		// If all locations are removed through the settings UI the location config will be set to
		// an empty string "". If that's the case (and if the settings are not an array for any
		// other reason) we should return an empty array.
		if ( ! is_array( $this->stripe_settings['payment_request_button_locations'] ) ) {
			return [];
		}

		return $this->stripe_settings['payment_request_button_locations'];
	}

	/**
	 * Returns whether Stripe express checkout element is enabled.
	 *
	 * This option defines whether Apple Pay and Google Pay buttons are enabled.
	 *
	 * @return boolean
	 */
	public function is_express_checkout_enabled() {
		return isset( $this->stripe_settings['payment_request'] ) && 'yes' === $this->stripe_settings['payment_request'];
	}

	/**
	 * Restores the shipping methods previously chosen for each recurring cart after shipping was reset and recalculated
	 * during the express checkout get_shipping_options flow.
	 *
	 * When the cart contains multiple subscriptions with different billing periods, customers are able to select different shipping
	 * methods for each subscription, however, this is not supported when purchasing with Apple Pay and Google Pay as it's
	 * only concerned about handling the initial purchase.
	 *
	 * In order to avoid Woo Subscriptions's `WC_Subscriptions_Cart::validate_recurring_shipping_methods` throwing an error, we need to restore
	 * the previously chosen shipping methods for each recurring cart.
	 *
	 * This function needs to be called after `WC()->cart->calculate_totals()` is run, otherwise `WC()->cart->recurring_carts` won't exist yet.
	 *
	 * @param array $previous_chosen_methods The previously chosen shipping methods.
	 */
	public function maybe_restore_recurring_chosen_shipping_methods( $previous_chosen_methods = [] ) {
		if ( empty( WC()->cart->recurring_carts ) || ! method_exists( 'WC_Subscriptions_Cart', 'get_recurring_shipping_package_key' ) ) {
			return;
		}

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', [] );

		foreach ( WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart ) {
			foreach ( $recurring_cart->get_shipping_packages() as $recurring_cart_package_index => $recurring_cart_package ) {
				if ( class_exists( 'WC_Subscriptions_Cart' ) ) {
					$package_key = WC_Subscriptions_Cart::get_recurring_shipping_package_key( $recurring_cart_key, $recurring_cart_package_index );

					// If the recurring cart package key is found in the previous chosen methods, but not in the current chosen methods, restore it.
					if ( isset( $previous_chosen_methods[ $package_key ] ) && ! isset( $chosen_shipping_methods[ $package_key ] ) ) {
						$chosen_shipping_methods[ $package_key ] = $previous_chosen_methods[ $package_key ];
					}
				}
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}

	/**
	 * Calculates taxes as displayed on cart, based on a product and a particular price.
	 *
	 * @param WC_Product $product The product, for retrieval of tax classes.
	 * @param float      $price   The price, which to calculate taxes for.
	 * @return array              An array of final taxes.
	 */
	public function get_taxes_like_cart( $product, $price ) {
		if ( ! wc_tax_enabled() || $this->cart_prices_include_tax() ) {
			// Only proceed when taxes are enabled, but not included.
			return [];
		}

		// Follows the way `WC_Cart_Totals::get_item_tax_rates()` works.
		$tax_class = $product->get_tax_class();
		$rates     = WC_Tax::get_rates( $tax_class );
		// No cart item, `woocommerce_cart_totals_get_item_tax_rates` can't be applied here.

		// Normally there should be a single tax, but `calc_tax` returns an array, let's use it.
		return WC_Tax::calc_tax( $price, $rates, false );
	}

	/**
	* Whether tax should be displayed on separate line in cart.
	* returns true if tax is disabled or display of tax in checkout is set to inclusive.
	*
	* @return boolean
	*/
	public function cart_prices_include_tax() {
		return ! wc_tax_enabled() || 'incl' === get_option( 'woocommerce_tax_display_cart' );
	}

	/**
	 * Gets the booking id from the cart.
	 *
	 * It's expected that the cart only contains one item which was added via ajax_add_to_cart.
	 * Used to remove the booking from WC Bookings in-cart status.
	 *
	 * @return int|false
	 */
	public function get_booking_id_from_cart() {
		$cart      = WC()->cart->get_cart();
		$cart_item = reset( $cart );

		if ( $cart_item && isset( $cart_item['booking']['_booking_id'] ) ) {
			return $cart_item['booking']['_booking_id'];
		}

		return false;
	}
}
