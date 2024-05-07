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
	 * Create instance of payment method
	 */
	public function __construct() {
		$main_settings     = get_option( 'woocommerce_stripe_settings' );
		$is_stripe_enabled = ! empty( $main_settings['enabled'] ) && 'yes' === $main_settings['enabled'];

		$this->enabled  = $is_stripe_enabled && in_array( static::STRIPE_ID, $this->get_option( 'upe_checkout_experience_accepted_payments', [ 'card' ] ), true ) ? 'yes' : 'no';
		$this->id       = WC_Gateway_Stripe::ID . '_' . static::STRIPE_ID;
		$this->testmode = ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'];
		$this->supports = [ 'products', 'refunds' ];
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
		} else {
			$message = method_exists( $upe_gateway_instance, $method ) ? 'Call to private method ' : 'Call to undefined method ';
			throw new \Error( $message . get_class( $this ) . '::' . $method );
		}
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
		return 'yes' === $this->enabled;
	}

	/**
	 * Returns true if the UPE method is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( is_add_payment_method_page() && ! $this->is_reusable() ) {
			return false;
		}

		return $this->is_enabled_at_checkout() && parent::is_available();
	}

	/**
	 * Returns payment method title
	 *
	 * @param array|bool $payment_details Optional payment details from charge object.
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
		return $this->description;
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

		// Check currency compatibility.
		$current_store_currency = $this->get_woocommerce_currency();
		$currencies             = $this->get_supported_currencies();
		if ( ! empty( $currencies ) && ! in_array( $current_store_currency, $currencies, true ) ) {
			return false;
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

		// If cart or order contains subscription, enable payment method if it's reusable.
		if ( $this->is_subscription_item_in_cart() || ( ! empty( $order_id ) && $this->has_subscription( $order_id ) ) ) {
			return $this->is_reusable();
		}

		// If cart or order contains pre-order, enable payment method if it's reusable.
		if ( $this->is_pre_order_item_in_cart() || ( ! empty( $order_id ) && $this->has_pre_order( $order_id ) ) ) {
			return $this->is_reusable();
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
		$plugin_settings   = get_option( 'woocommerce_stripe_settings' );
		$test_mode_setting = ! empty( $plugin_settings['testmode'] ) ? $plugin_settings['testmode'] : 'no';

		if ( 'yes' === $test_mode_setting ) {
			return true;
		}

		// Otherwise, make sure the capability is available.
		$capabilities = $this->get_capabilities_response();
		if ( empty( $capabilities ) ) {
			return false;
		}
		$key = $this->get_id() . '_payments';
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
		return $this->is_reusable() ? WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID : static::STRIPE_ID;
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
			'wc_stripe_' . static::STRIPE_ID . '_upe_supported_currencies',
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
	public function get_testing_instructions() {
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
	 * Overrides @see WC:Settings_API::get_option_key() to use the same option key as the main Stripe gateway.
	 *
	 * @return string
	 */
	public function get_option_key() {
		return 'woocommerce_stripe_settings';
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
			<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-upe-form" class="wc-upe-form wc-payment-form">
				<div class="wc-stripe-upe-element" data-payment-method-type="<?php echo esc_attr( $this->stripe_id ); ?>"></div>
				<div id="wc-<?php echo esc_attr( $this->id ); ?>-upe-errors" role="alert"></div>
				<input type="hidden" class="wc-stripe-is-deferred-intent" name="wc-stripe-is-deferred-intent" value="1" />
			</fieldset>
			<?php
			if ( $this->should_show_save_option() ) {
				$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page();
				if ( is_user_logged_in() ) {
					$this->save_payment_method_checkbox( $force_save_payment );
				}
			}
			if ( $display_tokenization ) {
				$this->tokenization_script();
				$this->saved_payment_methods();
			}
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
			<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" type="checkbox" value="true" style="width:auto;" <?php echo $force_checked ? 'checked' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?> />
				<label for="<?php echo esc_attr( $id ); ?>" style="display:inline;">
					<?php echo esc_html( apply_filters( 'wc_stripe_save_to_account_text', __( 'Save payment information to my account for future purchases.', 'woocommerce-gateway-stripe' ) ) ); ?>
				</label>
			</p>
		</fieldset>
		<?php
	}
}
