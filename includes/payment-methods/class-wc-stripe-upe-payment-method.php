<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract UPE Payment Method class
 *
 * Handles general functionality for UPE payment methods
 */


/**
 * Extendable abstract class for payment methods.
 */
abstract class WC_Stripe_UPE_Payment_Method extends WC_Payment_Gateway {

	use WC_Stripe_Subscriptions_Utilities_Trait;
	use WC_Stripe_Pre_Orders_Trait;

	/**
	 * Stripe key name
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * Display title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Method label
	 *
	 * @var string
	 */
	protected $label;

	/**
	 * Method description
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Can payment method be saved or reused?
	 *
	 * @var bool
	 */
	protected $is_reusable;

	/**
	 * Array of currencies supported by this UPE method
	 *
	 * @var array
	 */
	protected $supported_currencies;

	/**
	 * Can this payment method be refunded?
	 *
	 * @var array
	 */
	protected $can_refund = true;

	/**
	 * Wether this UPE method is enabled
	 *
	 * @var bool
	 */
	public $enabled;

	/**
	 * Supported customer locations for which charges for a payment method can be processed.
	 * Empty if all customer locations are supported.
	 *
	 * @var string[]
	 */
	protected $supported_countries = [];

	/**
	 * Should payment method be restricted to only domestic payments.
	 * E.g. only to Stripe's connected account currency.
	 *
	 * @var boolean
	 */
	protected $accept_only_domestic_payment = false;

	/**
	 * Represent payment total limitations for the payment method (per-currency).
	 *
	 * @var array<string,array<string,array<string,int>>>
	 */
	protected $limits_per_currency = [];

	/**
	 * Wether this UPE method is in testmode.
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Wether this payment method supports deferred intent creation.
	 *
	 * @var bool
	 */
	protected $supports_deferred_intent;

	/**
	 * Whether Optimized Checkout is enabled.
	 *
	 * @var bool
	 */
	protected $oc_enabled;

	/**
	 * Create instance of payment method
	 */
	public function __construct() {
		$main_settings     = WC_Stripe_Helper::get_stripe_settings();
		$is_stripe_enabled = ! empty( $main_settings['enabled'] ) && 'yes' === $main_settings['enabled'];

		$this->enabled                  = $is_stripe_enabled && in_array( static::STRIPE_ID, $this->get_upe_enabled_payment_method_ids(), true ) ? 'yes' : 'no'; // @phpstan-ignore-line (STRIPE_ID is defined in classes using this class)
		$this->id                       = WC_Gateway_Stripe::ID . '_' . static::STRIPE_ID; // @phpstan-ignore-line (STRIPE_ID is defined in classes using this class)
		$this->has_fields               = true;
		$this->testmode                 = WC_Stripe_Mode::is_test();
		$this->supports                 = [ 'products', 'refunds' ];
		$this->supports_deferred_intent = true;
		$this->oc_enabled               = WC_Stripe_Feature_Flags::is_oc_available() && 'yes' === $this->get_option( 'optimized_checkout_element' );
	}

	/**
	 * Magic method to call methods from the main UPE Stripe gateway.
	 *
	 * Calling methods on the UPE method instance should forward the call to the main UPE Stripe gateway.
	 * Because the UPE methods are not actual gateways, they don't have the methods to handle payments, so we need to forward the calls to
	 * the main UPE Stripe gateway.
	 *
	 * That would suggest we should use a class inheritance structure, however, we don't want to extend the UPE Stripe gateway class
	 * because we don't want the UPE method instance of the gateway to process those calls, we want the actual main instance of the
	 * gateway to process them.
	 *
	 * @param string $method    The method name.
	 * @param array  $arguments The method arguments.
	 */
	public function __call( $method, $arguments ) {
		$upe_gateway_instance = WC_Stripe::get_instance()->get_main_stripe_gateway();

		if ( in_array( $method, get_class_methods( $upe_gateway_instance ) ) ) {
			return call_user_func_array( [ $upe_gateway_instance, $method ], $arguments );
		}

		// In development/staging environments, throw errors for undefined methods to help catch bugs.
		// In production, fail gracefully to prevent third-party plugin compatibility issues.
		$is_dev_environment = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			|| in_array( wp_get_environment_type(), [ 'development', 'staging', 'local' ], true );

		if ( $is_dev_environment ) {
			$message = method_exists( $upe_gateway_instance, $method ) ? 'Call to private method ' : 'Call to undefined method ';
			throw new \Error( $message . get_class( $this ) . '::' . $method );
		}

		WC_Stripe_Logger::error( 'Call to undefined method ' . get_class( $this ) . '::' . $method );
		return false;
	}

	/**
	 * Returns payment method ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->stripe_id;
	}

	/**
	 * Returns true if the UPE method is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->enabled
			// When OC is enabled, we use the OC payment container to render all the methods.
			|| ( $this->oc_enabled && WC_Stripe_Payment_Methods::OC === $this->stripe_id );
	}

	/**
	 * Returns true if the UPE method is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		// When OC is enabled, we use the OC payment container to render all the methods.
		if ( $this->oc_enabled ) {
			$enabled_methods     = WC_Stripe::get_instance()->get_main_stripe_gateway()->get_upe_enabled_at_checkout_payment_method_ids();
			$non_express_methods = array_filter(
				$enabled_methods,
				function ( $method_id ) {
					return ! in_array( $method_id, WC_Stripe_Payment_Methods::EXPRESS_PAYMENT_METHODS, true );
				}
			);
			return WC_Stripe_Payment_Methods::OC === $this->stripe_id && ( has_block( 'woocommerce/checkout' ) || count( $non_express_methods ) > 0 );
		}

		if ( is_add_payment_method_page() && ! $this->is_reusable() ) {
			return false;
		}

		return $this->is_enabled_at_checkout() && parent::is_available();
	}

	/**
	 * Returns payment method title
	 *
	 * @param stdClass|array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		return $this->title;
	}

	/**
	 * Returns payment method label
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Returns payment method description
	 *
	 * @return string
	 */
	public function get_description() {
		return '';
	}

	/**
	 * Gets the payment method's icon.
	 *
	 * @return string The icon HTML.
	 */
	public function get_icon() {
		$icons = WC_Stripe::get_instance()->get_main_stripe_gateway()->payment_icons();
		return apply_filters( 'woocommerce_gateway_icon', isset( $icons[ $this->get_id() ] ) ? $icons[ $this->get_id() ] : '', $this->id );
	}

	/**
	 * Gets the payment method's icon url.
	 * Each payment method should override this method to return the icon url.
	 *
	 * @return string The icon url.
	 */
	public function get_icon_url() {
		return '';
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @param int|null    $order_id
	 * @param string|null $account_domestic_currency The account's default currency.
	 * @return bool
	 */
	public function is_enabled_at_checkout( $order_id = null, $account_domestic_currency = null ) {
		// Check capabilities first.
		if ( ! $this->is_capability_active() ) {
			return false;
		}

		// Check currency compatibility. On the pay for order page, check the order currency.
		// Otherwise, check the store currency.
		$current_store_currency = $this->get_woocommerce_currency();
		$currencies             = $this->get_supported_currencies();
		if ( ! empty( $currencies ) ) {
			if ( is_wc_endpoint_url( 'order-pay' ) && isset( $_GET['key'] ) ) {
				$order = wc_get_order( $order_id ? $order_id : absint( get_query_var( 'order-pay' ) ) );
				if ( ! $order || ! in_array( $order->get_currency(), $currencies, true ) ) {
					return false;
				}
			} elseif ( ! in_array( $current_store_currency, $currencies, true ) ) {
				return false;
			}
		}

		// For payment methods that only support domestic payments, check if the store currency matches the account's default currency.
		if ( $this->has_domestic_transactions_restrictions() ) {
			if ( null === $account_domestic_currency ) {
				$account_domestic_currency = WC_Stripe::get_instance()->account->get_account_default_currency();
			}

			if ( strtolower( $current_store_currency ) !== strtolower( $account_domestic_currency ) ) {
				return false;
			}
		}

		// This part ensures that when payment limits for the currency declared, those will be respected (e.g. BNPLs).
		if ( [] !== $this->get_limits_per_currency() && ! $this->is_inside_currency_limits( $current_store_currency ) ) {
			return false;
		}

		// If cart or order contains subscription, enable payment method if it's reusable, or if manual renewals are enabled.
		if ( $this->is_subscription_item_in_cart() || ( ! empty( $order_id ) && $this->has_subscription( $order_id ) ) ) {
			return $this->is_reusable() || WC_Stripe_Subscriptions_Helper::is_manual_renewal_enabled();
		}

		// If cart or order contains pre-order, enable payment method if it's reusable.
		// BLIK supports pre-order when product is charged upfront. We're handling availability in WC_Stripe_UPE_Payment_Method_BLIK.
		if ( $this->is_pre_order_item_in_cart() || ( ! empty( $order_id ) && $this->has_pre_order( $order_id ) ) ) {
			return $this->is_reusable() || WC_Stripe_Payment_Methods::BLIK === $this->stripe_id;
		}

		// Note: this $this->is_automatic_capture_enabled() call will be handled by $this->__call() and fall through to the UPE gateway class.
		if ( $this->requires_automatic_capture() && ! $this->is_automatic_capture_enabled() ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the supported customer locations for which charges for a payment method can be processed.
	 *
	 * @return array Supported customer locations.
	 */
	public function get_available_billing_countries() {
		$account         = WC_Stripe::get_instance()->account->get_cached_account_data();
		$account_country = isset( $account['country'] ) ? strtoupper( $account['country'] ) : '';

		return $this->has_domestic_transactions_restrictions() ? [ $account_country ] : $this->supported_countries;
	}

	/**
	 * Validates if a payment method is available on a given country
	 *
	 * @param string $country a two-letter country code
	 *
	 * @return bool Will return true if supported_countries is empty on payment method
	 */
	public function is_allowed_on_country( $country ) {
		if ( ! empty( $this->supported_countries ) ) {
			return in_array( $country, $this->supported_countries );
		}

		return true;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * will support saved payments/subscription payments
	 *
	 * @return bool
	 */
	public function is_reusable() {
		return $this->is_reusable;
	}

	/**
	 * Returns boolean dependent on whether capability
	 * for site account is enabled for payment method.
	 *
	 * @return bool
	 */
	public function is_capability_active() {
		// Treat all capabilities as active when in test mode.
		if ( WC_Stripe_Mode::is_test() ) {
			return true;
		}

		// Otherwise, make sure the capability is available.
		$capabilities = $this->get_capabilities_response();
		if ( empty( $capabilities ) ) {
			return false;
		}
		$key = WC_Stripe_Helper::get_payment_method_capability_id( $this->get_id() );
		return isset( $capabilities[ $key ] ) && 'active' === $capabilities[ $key ];
	}

	/**
	 * Returns capabilities response object for site account.
	 *
	 * @return object
	 */
	public function get_capabilities_response() {
		$data = WC_Stripe::get_instance()->account->get_cached_account_data();
		if ( empty( $data ) || ! isset( $data['capabilities'] ) ) {
			return [];
		}
		return $data['capabilities'];
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from Stripe.
	 */
	public function get_retrievable_type() {
		return $this->is_reusable() ? WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID : static::STRIPE_ID; // @phpstan-ignore-line (STRIPE_ID is defined in classes using this class)
	}

	/**
	 * Create new WC payment token and add to user.
	 *
	 * @param int $user_id        WP_User ID
	 * @param object $payment_method Stripe payment method object
	 *
	 * @return WC_Payment_Token_SEPA
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$token = new WC_Payment_Token_SEPA();
		$token->set_last4( $payment_method->sepa_debit->last4 );
		$token->set_gateway_id( $this->id );
		$token->set_token( $payment_method->id );
		$token->set_payment_method_type( $this->get_id() );
		$token->set_user_id( $user_id );
		$token->set_fingerprint( $payment_method->sepa_debit->fingerprint );
		$token->save();
		return $token;
	}

	/**
	 * Updates a payment token.
	 *
	 * @param WC_Payment_Token $token   The token to update.
	 * @param string $payment_method_id The new payment method ID.
	 * @return WC_Payment_Token
	 */
	public function update_payment_token( $token, $payment_method_id ) {
		$token->set_token( $payment_method_id );
		$token->save();
		return $token;
	}

	/**
	 * Returns the currencies this UPE method supports.
	 *
	 * @return array|null
	 */
	public function get_supported_currencies() {
		return apply_filters(
			'wc_stripe_' . static::STRIPE_ID . '_upe_supported_currencies', // @phpstan-ignore-line (STRIPE_ID is defined in classes using this class)
			$this->supported_currencies
		);
	}

	/**
	 * Determines whether the payment method is restricted to the Stripe account's currency.
	 * E.g.: Afterpay/Clearpay and Affirm only supports domestic payments; Klarna also implements a simplified version of these market restrictions.
	 *
	 * @return bool
	 */
	public function has_domestic_transactions_restrictions(): bool {
		return $this->accept_only_domestic_payment;
	}

	/**
	 * Wrapper function for get_woocommerce_currency global function
	 */
	public function get_woocommerce_currency() {
		return get_woocommerce_currency();
	}

	/**
	 * Returns whether the payment method requires automatic capture.
	 * By default all the UPE payment methods require automatic capture, except for "card".
	 *
	 * @return bool
	 */
	public function requires_automatic_capture() {
		return true;
	}

	/**
	 * Returns the HTML for the subtext messaging in the old settings UI.
	 *
	 * @param string $stripe_method_status (optional) Status of this payment method based on the Stripe's account capabilities
	 * @return string
	 */
	public function get_subtext_messages( $stripe_method_status ) {
		// can be either a `currency` or `activation` messaging, to be displayed in the old settings UI.
		$messages = [];

		if ( ! empty( $stripe_method_status ) && 'active' !== $stripe_method_status ) {
			$text            = __( 'Pending activation', 'woocommerce-gateway-stripe' );
			$tooltip_content = sprintf(
				/* translators: %1: Payment method name */
				esc_attr__( '%1$s won\'t be visible to your customers until you provide the required information. Follow the instructions Stripe has sent to your e-mail address.', 'woocommerce-gateway-stripe' ),
				$this->get_label()
			);
			$messages[] = $text . '<span class="tips" data-tip="' . $tooltip_content . '"><span class="woocommerce-help-tip" style="margin-top: 0;"></span></span>';
		}

		$currencies = $this->get_supported_currencies();
		if ( ! empty( $currencies ) && ! in_array( get_woocommerce_currency(), $currencies, true ) ) {
			/* translators: %s: List of comma-separated currencies. */
			$tooltip_content = sprintf( esc_attr__( 'In order to be used at checkout, the payment method requires the store currency to be set to one of: %s', 'woocommerce-gateway-stripe' ), implode( ', ', $currencies ) );
			$text            = __( 'Requires currency', 'woocommerce-gateway-stripe' );

			$messages[] = $text . '<span class="tips" data-tip="' . $tooltip_content . '"><span class="woocommerce-help-tip" style="margin-top: 0;"></span></span>';
		}

		return count( $messages ) > 0 ? implode( '&nbsp;–&nbsp;', $messages ) : '';
	}

	/**
	 * Checks if payment method allows refund via stripe
	 *
	 * @return bool
	 */
	public function can_refund_via_stripe() {
		return $this->can_refund;
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @return string
	 */
	public function get_testing_instructions( bool $show_optimized_checkout_instruction = false ) {
		if ( $show_optimized_checkout_instruction ) {
			_deprecated_argument(
				__FUNCTION__,
				'9.9.0'
			);
		}

		return '';
	}

	/**
	 * Processes an order payment.
	 *
	 * UPE Payment methods use the WC_Stripe_UPE_Payment_Gateway::process_payment() function.
	 *
	 * @param int $order_id The order ID to process.
	 * @return array The payment result.
	 */
	public function process_payment( $order_id ) {
		return WC_Stripe::get_instance()->get_main_stripe_gateway()->process_payment( $order_id );
	}

	/**
	 * Process a refund.
	 *
	 * UPE Payment methods use the WC_Stripe_UPE_Payment_Gateway::process_payment() function.
	 *
	 * @param int        $order_id Order ID.
	 * @param float|null $amount Refund amount.
	 * @param string     $reason Refund reason.
	 *
	 * @return bool|\WP_Error True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		if ( ! $this->can_refund_via_stripe() ) {
			return false;
		}

		return WC_Stripe::get_instance()->get_main_stripe_gateway()->process_refund( $order_id, $amount, $reason );
	}

	/**
	 * Process the add payment method request.
	 *
	 * UPE Payment methods use the WC_Stripe_UPE_Payment_Gateway::process_payment() function.
	 *
	 * @return array The add payment method result.
	 */
	public function add_payment_method() {
		$upe_gateway_instance = WC_Stripe::get_instance()->get_main_stripe_gateway();
		return $upe_gateway_instance->add_payment_method();
	}

	/**
	 * Determines if the Stripe Account country supports this UPE method.
	 *
	 * @return bool
	 */
	public function is_available_for_account_country() {
		return true;
	}

	/**
	 * Returns the UPE Payment Method settings option.
	 *
	 * @return string
	 */
	public function get_option_key() {
		return 'woocommerce_stripe_' . $this->stripe_id . '_settings';
	}

	/**
	 * Get option from the main Stripe gateway if it exists.
	 *
	 * @param string $key Option key.
	 * @param mixed  $empty_value Value when empty.
	 * @return string The value specified for the option or a default value for the option.
	 */
	public function get_option( $key, $empty_value = null ) {
		$main_settings = WC_Stripe_Helper::get_stripe_settings();

		if ( empty( $main_settings ) ) {
			return $empty_value;
		}

		if ( isset( $main_settings[ $key ] ) && ! is_null( $empty_value ) && '' === $main_settings[ $key ] ) {
			return $empty_value;
		}

		return $main_settings[ $key ] ?? $empty_value;
	}

	/**
	 * Renders the UPE payment fields.
	 */
	public function payment_fields() {
		try {
			$display_tokenization = $this->is_reusable() && is_checkout();

			if ( $this->testmode && ! empty( $this->get_testing_instructions() ) ) : ?>
				<p class="testmode-info"><?php echo wp_kses_post( $this->get_testing_instructions() ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
			<?php endif; ?>

			<?php
			if ( $display_tokenization ) {
				$this->tokenization_script();
				$this->saved_payment_methods();
			}
			?>
			<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-upe-form" class="wc-upe-form wc-payment-form">
				<div class="wc-stripe-upe-element" data-payment-method-type="<?php echo esc_attr( $this->stripe_id ); ?>"></div>
				<div id="wc-<?php echo esc_attr( $this->id ); ?>-upe-errors" role="alert"></div>
				<input type="hidden" class="wc-stripe-is-deferred-intent" name="wc-stripe-is-deferred-intent" value="1" />
			</fieldset>
			<?php
			if ( $this->should_show_save_option() ) {
				$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page() || WC_Stripe_Helper::should_force_save_payment_method();
				if ( is_user_logged_in() ) {
					$this->save_payment_method_checkbox( $force_save_payment );
				}
			}

			do_action( 'wc_stripe_payment_fields_' . $this->id, $this->id );
		} catch ( Exception $e ) {
			// Output the error message.
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
			?>
			<div>
				<?php echo esc_html__( 'An error was encountered when preparing the payment form. Please try again later.', 'woocommerce-gateway-stripe' ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Returns true if the saved cards feature is enabled.
	 *
	 * @return bool
	 */
	public function is_saved_cards_enabled() {
		return 'yes' === $this->get_option( 'saved_cards' );
	}

	/**
	 * Returns true if the SEPA tokens for iDEAL feature is enabled.
	 *
	 * @return bool
	 *
	 * @deprecated 10.0.0 Use is_sepa_tokens_for_ideal and is_sepa_tokens_for_bancontact instead.
	 */
	public function is_sepa_tokens_for_other_methods_enabled() {
		return 'yes' === $this->get_option( 'sepa_tokens_for_other_methods' );
	}

	/**
	 * Returns true if the SEPA tokens for iDEAL feature is enabled.
	 *
	 * @return bool
	 */
	public function is_sepa_tokens_for_ideal_enabled() {
		return 'yes' === $this->get_option( 'sepa_tokens_for_ideal' );
	}

	/**
	 * Returns true if the SEPA tokens for Bancontact feature is enabled.
	 *
	 * @return bool
	 */
	public function is_sepa_tokens_for_bancontact_enabled() {
		return 'yes' === $this->get_option( 'sepa_tokens_for_bancontact' );
	}

	/**
	 * Determines if this payment method should show the save to account checkbox.
	 *
	 * @return bool
	 */
	public function should_show_save_option() {
		return $this->is_reusable() && $this->is_saved_cards_enabled();
	}

	/**
	 * Returns the payment method's limits per currency.
	 *
	 * @return int[][][]
	 */
	public function get_limits_per_currency(): array {
		return $this->limits_per_currency;
	}

	/**
	 * Returns the current order amount (from the "pay for order" page or from the current cart).
	 *
	 * @return float|int|string
	 */
	public function get_current_order_amount() {
		if ( is_wc_endpoint_url( 'order-pay' ) && isset( $_GET['key'] ) ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
			return $order->get_total( '' );
		} elseif ( WC()->cart ) {
			return WC()->cart->get_total( '' );
		}
		return 0;
	}

	/**
	 * Determines if the payment method is inside the currency limits.
	 *
	 * @param  string $current_store_currency The store's currency.
	 * @return bool True if the payment method is inside the currency limits, false otherwise.
	 */
	public function is_inside_currency_limits( $current_store_currency ): bool {
		// Pay for order page will check for the current order total instead of the cart's.
		$order_amount = $this->get_current_order_amount();
		$amount       = WC_Stripe_Helper::get_stripe_amount( $order_amount, strtolower( $current_store_currency ) );

		// Don't engage in limits verification in non-checkout context (cart is not available or empty).
		if ( $amount <= 0 ) {
			return true;
		}

		$account_country     = WC_Stripe::get_instance()->account->get_account_country();
		$range               = null;
		$limits_per_currency = $this->get_limits_per_currency();

		if ( isset( $limits_per_currency[ $current_store_currency ][ $account_country ] ) ) {
			$range = $limits_per_currency[ $current_store_currency ][ $account_country ];
		} elseif ( isset( $limits_per_currency[ $current_store_currency ]['default'] ) ) {
			$range = $limits_per_currency[ $current_store_currency ]['default'];
		}

		// If there is no range specified for the currency-country pair we don't support it and return false.
		if ( null === $range ) {
			return false;
		}

		$is_valid_minimum = null === $range['min'] || $amount >= $range['min'];
		$is_valid_maximum = null === $range['max'] || $amount <= $range['max'];

		return $is_valid_minimum && $is_valid_maximum;
	}

	/**
	 * Displays the save to account checkbox.
	 *
	 * @param bool $force_checked Whether the checkbox should be checked by default.
	 */
	public function save_payment_method_checkbox( $force_checked = false ) {
		$id = 'wc-' . $this->id . '-new-payment-method';
		?>
		<fieldset <?php echo $force_checked ? 'style="display:none;"' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>>
			<p class="form-row woocommerce-SavedPaymentMethods-saveNew" <?php echo ! is_user_logged_in() ? 'style="display:none;"' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>>
				<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" type="checkbox" value="true" style="width:auto;" <?php echo $force_checked ? 'checked' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?> />
				<label for="<?php echo esc_attr( $id ); ?>" style="display:inline;">
					<?php echo esc_html( apply_filters( 'wc_stripe_save_to_account_text', __( 'Save payment information to my account for future purchases.', 'woocommerce-gateway-stripe' ) ) ); ?>
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Gets the transaction URL.
	 * Overrides WC_Payment_Gateway::get_transaction_url().
	 *
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		$this->view_transaction_url = WC_Stripe_Helper::get_transaction_url( $this->testmode );

		return parent::get_transaction_url( $order );
	}

	/**
	 * Whether this payment method supports deferred intent creation.
	 *
	 * @return bool
	 */
	public function supports_deferred_intent() {
		return $this->supports_deferred_intent;
	}

	/**
	 * Returns UPE enabled payment method IDs.
	 *
	 * @return string[]
	 */
	public function get_upe_enabled_payment_method_ids() {
		return WC_Stripe_Payment_Method_Configurations::get_upe_enabled_payment_method_ids();
	}

	/**
	 * Returns the title for the card wallet type.
	 * This is used to display the title for Apple Pay and Google Pay.
	 *
	 * @param $express_payment_type string The type of express payment method.
	 *
	 * @return string The title for the card wallet type.
	 */
	protected function get_card_wallet_type_title( $express_payment_type ) {
		$express_payment_titles = WC_Stripe_Payment_Methods::EXPRESS_METHODS_LABELS;
		$payment_method_title   = $express_payment_titles[ $express_payment_type ] ?? false;

		if ( ! $payment_method_title ) {
			return parent::get_title();
		}

		return $payment_method_title . WC_Stripe_Express_Checkout_Helper::get_payment_method_title_suffix();
	}
}
