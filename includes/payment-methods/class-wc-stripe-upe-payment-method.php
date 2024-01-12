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
	 * List of supported countries
	 *
	 * @var array
	 */
	protected $supported_countries;

	/**
	 * Create instance of payment method
	 */
	public function __construct() {
		$main_settings = get_option( 'woocommerce_stripe_settings' );

		$this->enabled  = in_array( static::STRIPE_ID, $this->get_upe_enabled_payment_method_ids(), true );
		$this->id       = WC_Gateway_Stripe::ID . '_' . static::STRIPE_ID;
		$this->testmode = ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'];
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
		return $this->enabled;
	}

	/**
	 * Returns true if the UPE method is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return $this->is_enabled_at_checkout();
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
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @param int|null $order_id
	 * @return bool
	 */
	public function is_enabled_at_checkout( $order_id = null ) {
		// Check capabilities first.
		if ( ! $this->is_capability_active() ) {
			return false;
		}

		// Check currency compatibility.
		$currencies = $this->get_supported_currencies();
		if ( ! empty( $currencies ) && ! in_array( $this->get_woocommerce_currency(), $currencies, true ) ) {
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

		return true;
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
		$account = WC_Stripe::get_instance()->account;
		$data    = $account->get_cached_account_data();
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
		return $this->is_reusable() ? WC_Stripe_UPE_Payment_Method_Sepa::STRIPE_ID : null;
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
		$token->set_gateway_id( WC_Stripe_UPE_Payment_Gateway::ID );
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
	 * Determines if the Stripe Account country supports this UPE method.
	 *
	 * @return bool
	 */
	public function is_available_for_account_country() {
		return true;
	}

	public function get_option_key() {
		return 'woocommerce_stripe_settings';
	}

	/**
	 * Renders the UPE input fields needed to get the user's payment information on the checkout page
	 */
	public function payment_fields() {
		try {

			// Output the form HTML.
			?>
			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
			<?php endif; ?>
			<fieldset id="wc-<?php echo esc_attr( $this->get_id() ); ?>-upe-form" class="wc-upe-form wc-payment-form">
				<div class="wc-stripe-upe-element" data-payment-method-type="<?php echo esc_attr( $this->stripe_id ); ?>"></div>
				<div id="wc-<?php echo esc_attr( $this->get_id() ); ?>-upe-errors" role="alert"></div>
			</fieldset>
			<?php
			if ( $this->is_saved_cards_enabled() && $this->is_reusable() ) {
				$force_save_payment = ( $this->is_reusable() && ! apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $this->is_reusable() ) ) || is_add_payment_method_page();
				if ( is_user_logged_in() ) {
					$this->save_payment_method_checkbox( $force_save_payment );
				}
			}
		} catch ( Exception $e ) {
			// Output the error message.
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
			?>
			<div>
				<?php
				echo esc_html__( 'An error was encountered when preparing the payment form. Please try again later.', 'woocommerce-gateway-stripe' );
				?>
			</div>
			<?php
		}
	}

	/**
	 * Returns the list of enabled payment method types for UPE.
	 *
	 * @return string[]
	 */
	public function get_upe_enabled_payment_method_ids() {
		return $this->get_option( 'upe_checkout_experience_accepted_payments', [ 'card' ] );
	}

	public function is_saved_cards_enabled() {
		return 'yes' === $this->get_option( 'saved_cards' );
	}

	/**
	 * Displays the save to account checkbox.
	 *
	 * @since 4.1.0
	 * @version 5.6.0
	 */
	public function save_payment_method_checkbox( $force_checked = false ) {
		$id = 'wc-' . $this->get_id() . '-new-payment-method';
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
