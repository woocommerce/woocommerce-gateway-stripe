<?php

use Automattic\WooCommerce\Admin\Overrides\OrderRefund;
use Automattic\WooCommerce\Enums\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName

/**
 * Abstract class that will be inherited by all payment methods.
 *
 * @extends WC_Payment_Gateway_CC
 *
 * @since 4.0.0
 */
abstract class WC_Stripe_Payment_Gateway extends WC_Payment_Gateway_CC {

	use WC_Stripe_Subscriptions_Trait;
	use WC_Stripe_Pre_Orders_Trait;

	/**
	 * The delay between retries.
	 *
	 * @var int
	 */
	protected $retry_interval = 1;

	/**
	 * Fallback method to be inherited by all payment methods. Stripe UPE will override it.
	 *
	 * @return string[]
	 */
	public function get_upe_enabled_payment_method_ids( $force_refresh = false ) {
		return [ WC_Stripe_Payment_Methods::CARD ];
	}

	/**
	 * Fallback method to be inherited by all payment methods. Stripe UPE will override it.
	 *
	 * @return string[]
	 */
	public function get_upe_available_payment_methods() {
		return [ WC_Stripe_Payment_Methods::CARD ];
	}

	/**
	 * Checks whether the gateway is enabled.
	 *
	 * @return bool The result.
	 */
	public function is_enabled() {
		return 'yes' === $this->get_option( 'enabled' );
	}

	/**
	 * Disables gateway.
	 */
	public function disable() {
		$this->update_option( 'enabled', 'no' );
	}

	/**
	 * Enables gateway.
	 */
	public function enable() {
		$this->update_option( 'enabled', 'yes' );
	}

	/**
	 * Add additional field to payment gateway settings which is unique to the gateway.
	 *
	 * @param array $settings Settings array.
	 * @return array
	 */
	public function get_unique_settings( $settings = [] ) {
		return $settings;
	}

	/**
	 * Displays the admin settings webhook description.
	 *
	 * @since 4.1.0
	 * @version 5.0.0
	 * @return mixed
	 */
	public function display_admin_settings_webhook_description() {
		/* translators: 1) webhook url */
		$description = sprintf( __( 'You must add the following webhook endpoint <strong style="background-color:#ddd;">&nbsp;%s&nbsp;</strong> to your <a href="https://dashboard.stripe.com/account/webhooks" target="_blank">Stripe account settings</a> (if there isn\'t one already enabled). This will enable you to receive notifications on the charge statuses.', 'woocommerce-gateway-stripe' ), WC_Stripe_Helper::get_webhook_url() );

		$webhook_status = WC_Stripe_Webhook_State::get_webhook_status_message();

		return $description . '<br><br>' . $webhook_status;
	}

	/**
	 * Prints the admin options for the gateway.
	 * Inserts an empty placeholder div feature flag is enabled.
	 */
	public function admin_options() {
		$form_fields = $this->get_form_fields();
		$return_url  = admin_url( 'admin.php?page=wc-settings&tab=checkout' );
		$header      = $this->get_method_title();
		$return_text = __( 'Return to payments', 'woocommerce-gateway-stripe' );

		WC_Stripe_Helper::render_admin_header( $header, $return_text, $return_url );

		$this->render_upe_settings();
	}

	/**
	 * Inserts an empty placeholder div for new account card when Stripe is not connected.
	 * Inserts an empty placeholder div for UPE opt-in banner within the existing form fields, otherwise.
	 */
	public function render_upe_settings() {
		global $hide_save_button;
		$hide_save_button    = true;
		$is_stripe_connected = woocommerce_gateway_stripe()->connect->is_connected();

		echo $is_stripe_connected ? '<div id="wc-stripe-payment-gateway-container"></div>' : '<div id="wc-stripe-new-account-container"></div>';
	}

	/**
	 * Displays the save to account checkbox.
	 *
	 * @since 4.1.0
	 * @version 5.6.0
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

	/**
	 * Checks to see if request is invalid and that
	 * they are worth retrying.
	 *
	 * @since 4.0.5
	 * @param object $error
	 */
	public function is_retryable_error( $error ) {
		// Note that this check is required since the error type is 'invalid_request_error' which
		// would otherwise return true.
		if (
			isset( $error->code ) &&
			(
				'payment_intent_mandate_invalid' === $error->code || // Don't retry payments when a 3DS mandate is invalid.
				'charge_exceeds_transaction_limit' === $error->code || // Don't retry payments when the charge exceeds the transaction limit.
				'amount_too_small' === $error->code
			)
		) {
			return false;
		}

		return (
			'invalid_request_error' === $error->type ||
			'idempotency_error' === $error->type ||
			'rate_limit_error' === $error->type ||
			'api_connection_error' === $error->type ||
			'api_error' === $error->type
		);
	}

	/**
	 * Checks to see if error is of same idempotency key
	 * error due to retries with different parameters.
	 *
	 * @since 4.1.0
	 * @param object $error
	 */
	public function is_same_idempotency_error( $error ) {
		return (
			$error &&
			'idempotency_error' === $error->type &&
			preg_match( '/Keys for idempotent requests can only be used with the same parameters they were first used with./i', $error->message )
		);
	}

	/**
	 * Checks to see if error is of invalid request
	 * error and it is no such customer.
	 *
	 * @since 4.1.0
	 * @param object $error
	 */
	public function is_no_such_customer_error( $error ) {
		return (
			$error &&
			'invalid_request_error' === $error->type &&
			preg_match( '/No such customer/i', $error->message )
		);
	}

	/**
	 * Checks to see if error is of invalid request
	 * error and it is no such token.
	 *
	 * @since 4.1.0
	 * @param object $error
	 */
	public function is_no_such_token_error( $error ) {
		return (
			$error &&
			'invalid_request_error' === $error->type &&
			preg_match( '/No such token/i', $error->message )
		);
	}

	/**
	 * Checks to see if error is of invalid request
	 * error and it is no such source.
	 *
	 * @since 4.1.0
	 * @param object $error
	 */
	public function is_no_such_source_error( $error ) {
		return (
			$error &&
			'invalid_request_error' === $error->type &&
			preg_match( '/No such (source|PaymentMethod)/i', $error->message )
		);
	}

	/**
	 * Checks to see if error is of invalid request
	 * error and it is no such source linked to customer.
	 *
	 * @since 4.1.0
	 * @param object $error
	 */
	public function is_no_linked_source_error( $error ) {
		return (
			$error &&
			'invalid_request_error' === $error->type &&
			preg_match( '/does not have a linked source with ID/i', $error->message )
		);
	}

	/**
	 * Check to see if we need to update the idempotency
	 * key to be different from previous charge request.
	 *
	 * @since 4.1.0
	 * @param object $source_object
	 * @param object $error
	 * @return bool
	 */
	public function need_update_idempotency_key( $source_object, $error ) {
		return (
			$error &&
			1 < $this->retry_interval &&
			! empty( $source_object->status ) &&
			'chargeable' === $source_object->status &&
			self::is_same_idempotency_error( $error )
		);
	}

	/**
	 * Checks if keys are set and valid.
	 *
	 * @since 4.0.6
	 * @return bool True if the keys are set *and* valid, false otherwise (for example, if keys are empty or the secret key was pasted as publishable key).
	 */
	public function are_keys_set() {
		// NOTE: updates to this function should be added to are_keys_set()
		// in includes/payment-methods/class-wc-stripe-payment-request.php

		if ( $this->testmode ) { // @phpstan-ignore-line (testmode is defined in the classes that use this class)
			return preg_match( '/^pk_test_/', $this->publishable_key ) // @phpstan-ignore-line (publishable_key is defined in the classes that use this class)
				&& preg_match( '/^[rs]k_test_/', $this->secret_key ); // @phpstan-ignore-line (secret_key is defined in the classes that use this class)
		} else {
			return preg_match( '/^pk_live_/', $this->publishable_key ) // @phpstan-ignore-line (publishable_key is defined in the classes that use this class)
				&& preg_match( '/^[rs]k_live_/', $this->secret_key ); // @phpstan-ignore-line (secret_key is defined in the classes that use this class)
		}
	}

	/**
	 * Check if we need to make gateways available.
	 *
	 * @since 4.1.3
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {

			// Not available if the keys aren't set.
			if ( ! $this->are_keys_set() ) {
				return false;
			}

			// Not available if using live mode without SSL.
			if ( $this->needs_ssl_setup() ) {
				return false;
			}

			return true;
		}

		return parent::is_available();
	}

	public function save_payment_method_requested() {
		$payment_method = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : 'stripe';

		return isset( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] ) && ! empty( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] );
	}

	/**
	 * Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id. Can be retried without.
	 *
	 * @since 4.2.0
	 * @param object   $error The error that was returned from Stripe's API.
	 * @param WC_Order $order The order those payment is being processed.
	 * @return bool           A flag that indicates that the customer does not exist and should be removed.
	 */
	public function maybe_remove_non_existent_customer( $error, $order ) {
		if ( ! $this->is_no_such_customer_error( $error ) ) {
			return false;
		}

		delete_user_option( $order->get_customer_id(), '_stripe_customer_id' );
		$order->delete_meta_data( '_stripe_customer_id' );
		$order->save();

		return true;
	}

	/**
	 * All payment icons that work with Stripe. Some icons references
	 * WC core icons.
	 *
	 * @since 4.0.0
	 * @since 4.1.0 Changed to using img with svg (colored) instead of fonts.
	 * @return array
	 */
	public function payment_icons() {
		$icon_list  = [
			WC_Stripe_Payment_Methods::ACH         => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bank-debit.svg" class="stripe-ach-icon stripe-icon" alt="ACH" />',
			WC_Stripe_Payment_Methods::ACSS_DEBIT  => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bank-debit.svg" class="stripe-ach-icon stripe-icon" alt="' . __( 'Pre-Authorized Debit', 'woocommerce-gateway-stripe' ) . '" />',
			WC_Stripe_Payment_Methods::ALIPAY      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/alipay.svg" class="stripe-alipay-icon stripe-icon" alt="Alipay" />',
			WC_Stripe_Payment_Methods::BECS_DEBIT  => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bank-debit.svg" class="stripe-ach-icon stripe-icon" alt="' . __( 'BECS Direct Debit', 'woocommerce-gateway-stripe' ) . '" />',
			WC_Stripe_Payment_Methods::BLIK        => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/blik.svg" class="stripe-blik-icon stripe-icon" alt="BLIK" />',
			WC_Stripe_Payment_Methods::WECHAT_PAY  => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/wechat.svg" class="stripe-wechat-icon stripe-icon" alt="Wechat Pay" />',
			WC_Stripe_Payment_Methods::BANCONTACT  => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bancontact.svg" class="stripe-bancontact-icon stripe-icon" alt="Bancontact" />',
			WC_Stripe_Payment_Methods::IDEAL       => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/ideal.svg" class="stripe-ideal-icon stripe-icon" alt="iDEAL" />',
			WC_Stripe_Payment_Methods::P24         => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/p24.svg" class="stripe-p24-icon stripe-icon" alt="P24" />',
			WC_Stripe_Payment_Methods::GIROPAY     => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/giropay.svg" class="stripe-giropay-icon stripe-icon" alt="giropay" />',
			WC_Stripe_Payment_Methods::KLARNA      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/klarna.svg" class="stripe-klarna-icon stripe-icon" alt="Klarna" />',
			WC_Stripe_Payment_Methods::AFFIRM      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/affirm.svg" class="stripe-affirm-icon stripe-icon" alt="Affirm" />',
			WC_Stripe_Payment_Methods::EPS         => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/eps.svg" class="stripe-eps-icon stripe-icon" alt="EPS" />',
			WC_Stripe_Payment_Methods::MULTIBANCO  => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/multibanco.svg" class="stripe-multibanco-icon stripe-icon" alt="Multibanco" />',
			WC_Stripe_Payment_Methods::SOFORT      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/sofort.svg" class="stripe-sofort-icon stripe-icon" alt="Sofort" />',
			WC_Stripe_Payment_Methods::SEPA        => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/sepa.svg" class="stripe-sepa-icon stripe-icon" alt="SEPA" />',
			WC_Stripe_Payment_Methods::BOLETO      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/boleto.svg" class="stripe-boleto-icon stripe-icon" alt="Boleto" />',
			WC_Stripe_Payment_Methods::OXXO        => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/oxxo.svg" class="stripe-oxxo-icon stripe-icon" alt="OXXO" />',
			'cards'                                => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/cards.svg" class="stripe-cards-icon stripe-icon" alt="' . __( 'Credit / Debit Card', 'woocommerce-gateway-stripe' ) . '" />',
			WC_Stripe_Payment_Methods::CASHAPP_PAY => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/cashapp.svg" class="stripe-cashapp-icon stripe-icon" alt="Cash App Pay" />',
		];
		if ( 'yes' === $this->get_option( 'optimized_checkout_element' ) ) {
			$icon_list['cards'] = '';
		}
		return apply_filters( 'wc_stripe_payment_icons', $icon_list );
	}

	/**
	 * Validates that the order meets the minimum order amount
	 * set by Stripe.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order
	 *
	 * @deprecated 10.0.0 Use WC_Stripe_Order_Helper::validate_minimum_order_amount() instead.
	 */
	public function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_Stripe_Exception( 'Did not meet minimum amount', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ) );
		}
	}

	/**
	 * Gets the transaction URL linked to Stripe dashboard.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_transaction_url( $order ) {
		$this->view_transaction_url = WC_Stripe_Helper::get_transaction_url( $this->testmode );

		return parent::get_transaction_url( $order );
	}

	/**
	 * Gets the saved customer id if exists.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_stripe_customer_id( $order ) {
		// Try to get it via the order first.
		$customer = $order->get_meta( '_stripe_customer_id', true );

		if ( empty( $customer ) ) {
			$customer = get_user_option( '_stripe_customer_id', $order->get_customer_id() );
		}

		return $customer;
	}

	/**
	 * Builds the return URL from redirects.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order
	 * @param int    $id Stripe session id.
	 */
	public function get_stripe_return_url( $order = null, $id = null ) {
		if ( is_object( $order ) ) {
			if ( empty( $id ) ) {
				$id = uniqid();
			}

			$order_id = $order->get_id();

			$args = [
				'utm_nooverride' => '1',
				'order_id'       => $order_id,
			];

			return wp_sanitize_redirect( esc_url_raw( add_query_arg( $args, $this->get_return_url( $order ) ) ) );
		}

		return wp_sanitize_redirect( esc_url_raw( add_query_arg( [ 'utm_nooverride' => '1' ], $this->get_return_url() ) ) );
	}

	/**
	 * Generate the request for the payment.
	 *
	 * @since 3.1.0
	 * @version 4.5.4
	 * @param  WC_Order $order
	 * @param  object   $prepared_payment_method Stripe Payment Method or Source.
	 * @return array()
	 */
	public function generate_payment_request( $order, $prepared_payment_method ) {
		$settings                              = WC_Stripe_Helper::get_stripe_settings();
		$is_short_statement_descriptor_enabled = ! empty( $settings['is_short_statement_descriptor_enabled'] ) && 'yes' === $settings['is_short_statement_descriptor_enabled'];
		$capture                               = ! empty( $settings['capture'] ) && 'yes' === $settings['capture'] ? true : false;
		$post_data                             = [];
		$post_data['currency']                 = strtolower( $order->get_currency() );
		$post_data['amount']                   = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $post_data['currency'] );

		/* translators: 1) blog name 2) order number */
		$post_data['description'] = sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
		$billing_email            = $order->get_billing_email();
		$billing_first_name       = $order->get_billing_first_name();
		$billing_last_name        = $order->get_billing_last_name();

		if ( ! empty( $billing_email ) && apply_filters( 'wc_stripe_send_stripe_receipt', false ) ) {
			$post_data['receipt_email'] = $billing_email;
		}

		if ( WC_Stripe_Helper::payment_method_allows_manual_capture( $order->get_payment_method() ) ) {
			$post_data['capture'] = $capture ? 'true' : 'false';
			if ( $is_short_statement_descriptor_enabled ) {
				$post_data['statement_descriptor_suffix'] = WC_Stripe_Helper::get_dynamic_statement_descriptor_suffix( $order );
			}
		}

		if ( method_exists( $order, 'get_shipping_postcode' ) && ! empty( $order->get_shipping_postcode() ) ) {
			$post_data['shipping'] = [
				'name'    => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
				'address' => [
					'line1'       => $order->get_shipping_address_1(),
					'line2'       => $order->get_shipping_address_2(),
					'city'        => $order->get_shipping_city(),
					'country'     => $order->get_shipping_country(),
					'postal_code' => $order->get_shipping_postcode(),
					'state'       => $order->get_shipping_state(),
				],
			];
		}

		$post_data['expand[]'] = 'balance_transaction';

		$metadata = [
			__( 'customer_name', 'woocommerce-gateway-stripe' ) => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
			__( 'customer_email', 'woocommerce-gateway-stripe' ) => sanitize_email( $billing_email ),
			'order_id'  => $order->get_order_number(),
			'site_url'  => esc_url( get_site_url() ),
			'signature' => $this->get_order_signature( $order ),
		];

		if ( $this->has_subscription( $order->get_id() ) ) {
			$metadata += [
				'payment_type' => 'recurring',
			];
		}

		$post_data['metadata'] = apply_filters( 'wc_stripe_payment_metadata', $metadata, $order, $prepared_payment_method );

		if ( $prepared_payment_method->customer ) {
			$post_data['customer'] = $prepared_payment_method->customer;
		}

		if ( ! empty( $prepared_payment_method->source ) ) {
			$post_data['source'] = $prepared_payment_method->source;
		}

		if ( ! empty( $prepared_payment_method->payment_method ) ) {
			$post_data['payment_method'] = $prepared_payment_method->payment_method;
		}

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
		 *
		 * @since 3.1.0
		 * @param array $post_data
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_stripe_generate_payment_request', $post_data, $order, $prepared_payment_method );
	}

	/**
	 * Store extra meta data for an order from a Stripe charge response.
	 *
	 * @param object $response The Charge response from Stripe.
	 * @param WC_Order $order The Order object.
	 *
	 * @return object The Charge response from Stripe.
	 *
	 * @throws WC_Stripe_Exception
	 */
	public function process_response( $response, $order ) {
		WC_Stripe_Logger::log( 'Processing response: ' . print_r( $response, true ) );

		$potential_order = WC_Stripe_Helper::get_order_by_charge_id( $response->id );
		if ( $potential_order && $potential_order->get_id() !== $order->get_id() ) {
			WC_Stripe_Logger::log( 'Aborting, transaction already consumed by another order.' );
			$localized_message = __( 'Payment processing failed. Please retry.', 'woocommerce-gateway-stripe' );
			throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
		}

		/**
		 * Allow third-party code to add custom logic before processing the charge data from Stripe.
		 *
		 * @param object $response The Charge response from Stripe.
		 * @param WC_Order $order The Order object.
		 */
		do_action( 'wc_gateway_stripe_process_payment_charge', $response, $order );

		$order_id = $order->get_id();
		$captured = ( isset( $response->captured ) && $response->captured ) ? 'yes' : 'no';

		// Store charge data.
		$order->update_meta_data( '_stripe_charge_captured', $captured );

		if ( isset( $response->balance_transaction ) ) {
			$this->update_fees( $order, is_string( $response->balance_transaction ) ? $response->balance_transaction : $response->balance_transaction->id );
		}

		// TODO: Refactor and add mandate ID support for other payment methods, if necessary.
		// The mandate ID is not available for the intent object, so we need to fetch the charge.
		// Mandate ID is necessary for renewal payments for certain payment methods and Indian cards.
		if ( isset( $response->payment_method_details->card->mandate ) ) {
			$order->update_meta_data( '_stripe_mandate_id', $response->payment_method_details->card->mandate );
		} elseif ( isset( $response->payment_method_details->acss_debit->mandate ) ) {
			$order->update_meta_data( '_stripe_mandate_id', $response->payment_method_details->acss_debit->mandate );
		}

		if ( isset( $response->payment_method, $response->payment_method_details ) ) {
			WC_Stripe_Payment_Tokens::update_token_from_method_details( $order->get_customer_id(), $response->payment_method, $response->payment_method_details );
		}

		if ( 'yes' === $captured ) {
			/**
			 * Charge can be captured but in a pending state. Payment methods
			 * that are asynchronous may take couple days to clear. Webhook will
			 * take care of the status changes.
			 */
			if ( OrderStatus::PENDING === $response->status ) {
				$order_stock_reduced = $order->get_meta( '_order_stock_reduced', true );

				if ( ! $order_stock_reduced ) {
					wc_reduce_stock_levels( $order_id );
				}

				$order->set_transaction_id( $response->id );
				/* translators: transaction id */
				$order->update_status( OrderStatus::ON_HOLD, sprintf( __( 'Stripe charge awaiting payment: %s.', 'woocommerce-gateway-stripe' ), $response->id ) );
			}

			if ( 'succeeded' === $response->status ) {
				/**
				 * If the response has a succeeded status but also has a risk/fraud outcome that requires manual review, don't mark the order as
				 * processing/completed. This will be handled by the incoming review.open webhook.
				 *
				 * Depending on when Stripe sends their events and how quickly it is processed by the store, the review.open webhook (which marks orders as on-hold)
				 * can be processed before or after the payment_intent.success webhook. This difference can lead to orders being incorrectly marked as processing/completed
				 * in WooCommerce, but flagged for manual renewal in Stripe.
				 *
				 * If the review.open webhook was processed before the payment_intent.success, set the processing/completed status in `_stripe_status_before_hold`
				 * to ensure the review.closed event handler will update the status to the proper status.
				 */
				if ( 'manual_review' === $this->get_risk_outcome( $response ) ) {
					$this->set_stripe_order_status_before_hold( $order, 'default_payment_complete' );
					$order->set_transaction_id( $response->id ); // Save the transaction ID to link the order to the Stripe charge ID. This is to fix reviews that result in refund.
				} else {
					$order->payment_complete( $response->id );

					/* translators: transaction id */
					$message = sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $response->id );
					if ( isset( $response->is_webhook_response ) ) {
						$message .= ' (via webhook)';
					}
					$order->add_order_note( $message );
				}
			}

			if ( 'failed' === $response->status ) {
				$localized_message = __( 'Payment processing failed. Please retry.', 'woocommerce-gateway-stripe' );
				$order->add_order_note(
					property_exists( $response, 'outcome' ) && isset( $response->outcome->seller_message )
						? $response->outcome->seller_message
						: $localized_message
				);

				throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
			}
		} else {
			$order->set_transaction_id( $response->id );

			if ( $order->has_status( [ OrderStatus::PENDING, OrderStatus::FAILED ] ) ) {
				wc_reduce_stock_levels( $order_id );
			}

			/* translators: transaction id */
			$order->update_status( OrderStatus::ON_HOLD, sprintf( __( 'Stripe charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization. Refunding is unavailable until payment has been captured.', 'woocommerce-gateway-stripe' ), $response->id ) );
		}

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}

		do_action( 'wc_gateway_stripe_process_response', $response, $order );

		return $response;
	}

	/**
	 * Sends the failed order email to admin.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param int $order_id
	 * @param array $status_update Optional. Use fields 'to' and 'from' to provide context
	 *    when a status change has occurred for the order.
	 * @return void
	 */
	public function send_failed_order_email( $order_id, $status_update = [] ) {
		$emails = WC()->mailer()->get_emails();

		if ( empty( $emails ) || empty( $order_id ) || ! isset( $emails['WC_Email_Failed_Order'] ) ) {
			return;
		}

		// To prevent duplicate failed order emails, check if a status update
		// will trigger its own failed order email.
		if (
			isset( $status_update['to'], $status_update['from'] ) &&
			OrderStatus::FAILED === $status_update['to'] &&
			in_array( $status_update['from'], [ OrderStatus::ON_HOLD, OrderStatus::PENDING ], true )
		) {
			$callback  = [ $emails['WC_Email_Failed_Order'], 'trigger' ];
			$hook_name = "woocommerce_order_status_{$status_update['from']}_to_failed_notification";
			if ( has_action( $hook_name, $callback ) ) {
				return;
			}
		}

		$emails['WC_Email_Failed_Order']->trigger( $order_id );
	}

	/**
	 * Sends the failed refund email to both admin and shopper.
	 *
	 * @since 9.6.0
	 * @param WC_Order $order The order object for which the refund failed.
	 * @return void
	 */
	public function send_failed_refund_emails( $order ) {
		$emails = WC()->mailer()->get_emails();

		if ( empty( $emails ) || empty( $order ) ) {
			return;
		}

		if ( isset( $emails['WC_Stripe_Email_Admin_Failed_Refund'] ) ) {
			$emails['WC_Stripe_Email_Admin_Failed_Refund']->trigger( $order->get_id(), $order );
		}

		if ( isset( $emails['WC_Stripe_Email_Customer_Failed_Refund'] ) ) {
			$emails['WC_Stripe_Email_Customer_Failed_Refund']->trigger( $order->get_id(), $order );
		}
	}

	/**
	 * Get owner details.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order
	 * @return object $details
	 *
	 * @deprecated 10.0.0 Use WC_Stripe_Order_Helper::get_owner_details() instead.
	 */
	public function get_owner_details( $order ) {
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();

		$details = [];

		$name  = $billing_first_name . ' ' . $billing_last_name;
		$email = $order->get_billing_email();
		$phone = $order->get_billing_phone();

		if ( ! empty( $phone ) ) {
			$details['phone'] = $phone;
		}

		if ( ! empty( $name ) ) {
			$details['name'] = $name;
		}

		if ( ! empty( $email ) ) {
			$details['email'] = $email;
		}

		$details['address']['line1']       = $order->get_billing_address_1();
		$details['address']['line2']       = $order->get_billing_address_2();
		$details['address']['state']       = $order->get_billing_state();
		$details['address']['city']        = $order->get_billing_city();
		$details['address']['postal_code'] = $order->get_billing_postcode();
		$details['address']['country']     = $order->get_billing_country();

		return (object) apply_filters( 'wc_stripe_owner_details', $details, $order );
	}

	/**
	 * Get source object by source ID.
	 *
	 * @since 4.0.3
	 * @param string $source_id The source ID to get source object for.
	 *
	 * @throws WC_Stripe_Exception Error while retrieving source object.
	 * @return string|object
	 */
	public function get_source_object( $source_id = '' ) {
		if ( empty( $source_id ) ) {
			return '';
		}

		$source_object = WC_Stripe_API::get_payment_method( $source_id );

		if ( ! empty( $source_object->error ) ) {
			throw new WC_Stripe_Exception( print_r( $source_object, true ), $source_object->error->message );
		}

		return $source_object;
	}

	/**
	 * Get charge object by charge ID.
	 *
	 * @since 7.0.2
	 * @param string $charge_id The charge ID to get charge object for.
	 * @param array  $params    The parameters to pass to the request.
	 *
	 * @throws WC_Stripe_Exception Error while retrieving charge object.
	 * @return string|object
	 */
	public function get_charge_object( $charge_id = '', $params = [] ) {
		if ( empty( $charge_id ) ) {
			return '';
		}

		$charge_object = WC_Stripe_API::request( $params, 'charges/' . $charge_id, 'GET' );

		if ( ! empty( $charge_object->error ) ) {
			throw new WC_Stripe_Exception( print_r( $charge_object, true ), $charge_object->error->message );
		}

		return $charge_object;
	}

	/**
	 * Get latest charge object from payment intent.
	 *
	 * Since API version 2022-11-15, the `charges` property was replaced with `latest_charge`.
	 * We can remove this method once we drop support for API versions prior to 2022-11-15.
	 *
	 * @since 7.0.2
	 * @param object $intent Stripe API Payment Intent object response.
	 *
	 * @return string|object
	 */
	public function get_latest_charge_from_intent( $intent ) {
		$latest_charge = null;

		if ( ! empty( $intent->charges->data ) ) {
			$latest_charge = end( $intent->charges->data );
		} elseif ( ! empty( $intent->latest_charge ) ) {
			$latest_charge = $this->get_charge_object( $intent->latest_charge );
		}

		return $latest_charge;
	}

	/**
	 * Checks if card is a prepaid card.
	 *
	 * @since 4.0.6
	 * @param object $source_object
	 * @return bool
	 */
	public function is_prepaid_card( $source_object ) {
		return (
			$source_object
			&& in_array( $source_object->object, [ 'token', 'source', 'payment_method' ], true )
			&& 'prepaid' === $source_object->card->funding
		);
	}

	/**
	 * Checks if a payment method object represents a prepaid credit card and
	 * throws an exception if it is one, but that is not allowed.
	 *
	 * @since 4.2.0
	 * @param object $prepared_source The object with source details.
	 * @throws WC_Stripe_Exception An exception if the card is prepaid, but prepaid cards are not allowed.
	 */
	public function maybe_disallow_prepaid_card( $payment_method ) {
		// Check if we don't allow prepaid credit cards.
		if ( apply_filters( 'wc_stripe_allow_prepaid_card', true ) || ! $this->is_prepaid_card( $payment_method ) ) {
			return;
		}

		$localized_message = __( 'Sorry, we\'re not accepting prepaid cards at this time. Your credit card has not been charged. Please try with alternative payment method.', 'woocommerce-gateway-stripe' );
		throw new WC_Stripe_Exception( print_r( $payment_method, true ), $localized_message );
	}

	/**
	 * Checks if source is of legacy type card.
	 *
	 * @since 4.0.8
	 * @param string $source_id
	 * @return bool
	 */
	public function is_type_legacy_card( $source_id ) {
		return ( preg_match( '/^card_/', $source_id ) );
	}

	/**
	 * Checks if source is payment method (pm_).
	 *
	 * @since 5.6.0
	 * @param string $source_id
	 * @return bool
	 */
	public function is_type_payment_method( $source_id ) {
		return ( preg_match( '/^pm_/', $source_id ) );
	}

	/**
	 * Checks if payment is via saved payment source.
	 *
	 * @since 4.1.0
	 * @return bool
	 */
	public function is_using_saved_payment_method() {
		$payment_method = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : 'stripe';

		return ( isset( $_POST[ 'wc-' . $payment_method . '-payment-token' ] ) && 'new' !== $_POST[ 'wc-' . $payment_method . '-payment-token' ] );
	}

	/**
	 * Get payment source. This can be a new token/source or existing WC token.
	 * If user is logged in and/or has WC account, create an account on Stripe.
	 * This way we can attribute the payment to the user to better fight fraud.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param string $user_id
	 * @param bool   $force_save_source Should we force save payment source.
	 *
	 * @throws Exception When card was not added or for and invalid card.
	 * @return object
	 */
	public function prepare_source( $user_id, $force_save_source = false, $existing_customer_id = null ) {
		$customer = new WC_Stripe_Customer( $user_id );
		if ( ! empty( $existing_customer_id ) ) {
			$customer->set_id( $existing_customer_id );
		}

		$force_save_source = apply_filters( 'wc_stripe_force_save_source', $force_save_source, $customer );
		$source_object     = '';
		$source_id         = '';
		$wc_token_id       = false;
		$payment_method    = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : 'stripe';
		$is_token          = false;

		// New CC info was entered and we have a new source to process.
		if ( ! empty( $_POST['stripe_source'] ) ) {
			$source_object = self::get_source_object( wc_clean( wp_unslash( $_POST['stripe_source'] ) ) );
			$source_id     = $source_object->id;
			$customer->maybe_create_customer();

			// Check if the customer opted to save the payment method to file.
			$maybe_saved_card = isset( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] ) && ! empty( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] );

			if ( $force_save_source || ( $user_id && $this->saved_cards && $maybe_saved_card ) ) {
				$was_attached = $this->maybe_attach_source_to_customer( $source_object, $customer );
				if ( $was_attached ) {
					// Save the payment method to the customer.
					$this->save_payment_method( $source_object );
				}
			}
		} elseif ( $this->is_using_saved_payment_method() ) {
			// Use an existing token, and then process the payment.
			$wc_token_id = isset( $_POST[ 'wc-' . $payment_method . '-payment-token' ] ) ? wc_clean( wp_unslash( $_POST[ 'wc-' . $payment_method . '-payment-token' ] ) ) : '';
			$wc_token    = WC_Payment_Tokens::get( $wc_token_id );

			if ( ! $wc_token || $wc_token->get_user_id() !== get_current_user_id() ) {
				WC()->session->set( 'refresh_totals', true );
				throw new WC_Stripe_Exception( 'Invalid payment method', __( 'Invalid payment method. Please input a new card number.', 'woocommerce-gateway-stripe' ) );
			}

			$source_id = $wc_token->get_token();

			if ( $this->is_type_legacy_card( $source_id ) || $this->is_type_payment_method( $source_id ) ) {
				$is_token = true;
			}
		} elseif ( isset( $_POST['stripe_token'] ) && 'new' !== $_POST['stripe_token'] ) {
			$stripe_token     = wc_clean( wp_unslash( $_POST['stripe_token'] ) );
			$maybe_saved_card = isset( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] ) && ! empty( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] );

			// This is true if the user wants to store the card to their account.
			if ( ( $user_id && $this->saved_cards && $maybe_saved_card ) || $force_save_source ) { // @phpstan-ignore-line (saved_cards is defined in the classes that use this class)
				$response = $customer->attach_source( $stripe_token );

				if ( ! empty( $response->error ) ) {
					throw new WC_Stripe_Exception( print_r( $response, true ), $response->error->message );
				}
				if ( is_wp_error( $response ) ) {
					throw new WC_Stripe_Exception( $response->get_error_message(), $response->get_error_message() );
				}
				$source_id = $response->id;
			} else {
				$source_id = $stripe_token;
				$is_token  = true;
			}
		}

		if ( ! $customer->get_id() ) {
			$customer_id = $customer->create_customer();
		} else {
			$customer_id = $customer->update_customer();
		}

		if ( empty( $source_object ) && ! $is_token ) {
			$source_object = self::get_source_object( $source_id );
		}

		return (object) [
			'token_id'       => $wc_token_id,
			'customer'       => $customer_id,
			'source'         => $source_id,
			'source_object'  => $source_object,
			'payment_method' => null,
		];
	}

	/**
	 * Get payment source from an order. This could be used in the future for
	 * a subscription as an example, therefore using the current user ID would
	 * not work - the customer won't be logged in :)
	 *
	 * Not using 2.6 tokens for this part since we need a customer AND a card
	 * token, and not just one.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param object $order
	 * @return object
	 */
	public function prepare_order_source( $order = null ) {
		$stripe_customer = new WC_Stripe_Customer();
		$stripe_source   = false;
		$token_id        = false;
		$source_object   = false;

		if ( $order ) {
			$stripe_customer_id = $this->get_stripe_customer_id( $order );

			if ( $stripe_customer_id ) {
				$stripe_customer->set_id( $stripe_customer_id );
			}

			$order_helper = WC_Stripe_Order_Helper::get_instance();
			$source_id    = $order_helper->get_stripe_source( $order );

			// Since 4.0.0, we changed card to source so we need to account for that.
			if ( empty( $source_id ) ) {
				$source_id = $order->get_meta( '_stripe_card_id', true );

				// Take this opportunity to update the key name.
				$order_helper->update_stripe_source( $order, $source_id );

				if ( is_callable( [ $order, 'save' ] ) ) {
					$order->save();
				}
			}

			if ( $source_id ) {
				$stripe_source = $source_id;
				$source_object = WC_Stripe_API::get_payment_method( $source_id );
			} elseif ( apply_filters( 'wc_stripe_use_default_customer_source', true ) ) {
				/*
				 * We can attempt to charge the customer's default source
				 * by sending empty source id.
				 */
				$stripe_source = '';
			}
		}

		return (object) [
			'token_id'       => $token_id,
			'customer'       => $stripe_customer ? $stripe_customer->get_id() : false,
			'source'         => $stripe_source,
			'source_object'  => $source_object,
			'payment_method' => null,
		];
	}

	/**
	 * Checks whether a source exists.
	 *
	 * @since 4.2.0
	 * @param  object $prepared_source The source that should be verified.
	 * @throws WC_Stripe_Exception     An exception if the source ID is missing.
	 */
	public function check_source( $prepared_source ) {
		if ( empty( $prepared_source->source ) ) {
			$localized_message = __( 'Payment processing failed. Please retry.', 'woocommerce-gateway-stripe' );
			throw new WC_Stripe_Exception( print_r( $prepared_source, true ), $localized_message );
		}
	}

	/**
	 * Save source to order.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param WC_Order $order For to which the source applies.
	 * @param stdClass $source Source information.
	 */
	public function save_source_to_order( $order, $source ) {
		// Store source in the order.
		if ( $source->customer ) {
			$order->update_meta_data( '_stripe_customer_id', $source->customer );
		}

		if ( $source->source ) {
			WC_Stripe_Order_Helper::get_instance()->update_stripe_source( $order, $source->source );
		}

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}

		$this->maybe_update_source_on_subscription_order( $order, $source );
	}

	/**
	 * Updates Stripe fees/net.
	 * e.g usage would be after a refund.
	 *
	 * @since 4.0.0
	 * @version 4.0.6
	 * @param WC_Order $order The order object
	 * @param int    $balance_transaction_id
	 */
	public function update_fees( $order, $balance_transaction_id ) {
		$balance_transaction = WC_Stripe_API::retrieve( 'balance/history/' . $balance_transaction_id );

		if ( empty( $balance_transaction->error ) ) {
			if ( isset( $balance_transaction ) && isset( $balance_transaction->fee ) ) {
				// Fees and Net needs to both come from Stripe to be accurate as the returned
				// values are in the local currency of the Stripe account, not from WC.
				$fee_refund = ! empty( $balance_transaction->fee ) ? WC_Stripe_Helper::format_balance_fee( $balance_transaction, 'fee' ) : 0;
				$net_refund = ! empty( $balance_transaction->net ) ? WC_Stripe_Helper::format_balance_fee( $balance_transaction, 'net' ) : 0;

				// Current data fee & net.
				$order_helper = WC_Stripe_Order_Helper::get_instance();
				$fee_current  = $order_helper->get_stripe_fee( $order );
				$net_current  = $order_helper->get_stripe_net( $order );

				// Calculation.
				$fee = (float) $fee_current + (float) $fee_refund;
				$net = (float) $net_current + (float) $net_refund;

				$order_helper->update_stripe_fee( $order, $fee );
				$order_helper->update_stripe_net( $order, $net );

				$currency = ! empty( $balance_transaction->currency ) ? strtoupper( $balance_transaction->currency ) : null;
				$order_helper->update_stripe_currency( $order, $currency );

				if ( is_callable( [ $order, 'save' ] ) ) {
					$order->save();
				}
			}
		} else {
			WC_Stripe_Logger::log( 'Unable to update fees/net meta for order: ' . $order->get_id() );
		}
	}

	/**
	 * Refund a charge.
	 *
	 * @since 3.1.0
	 * @version 4.9.0
	 * @param  int $order_id
	 * @param  float $amount
	 *
	 * @return bool True or false based on success.
	 * @throws Exception Throws exception when charge wasn't captured.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$request = [];

		$order_currency = $order->get_currency();
		$captured       = $order->get_meta( '_stripe_charge_captured', true );
		$charge_id      = $order->get_transaction_id();

		if ( ! $charge_id ) {
			return false;
		}

		if ( ! is_null( $amount ) ) {
			$request['amount'] = WC_Stripe_Helper::get_stripe_amount( $amount, $order_currency );
		}

		// If order is only authorized, don't pass amount.
		if ( 'yes' !== $captured ) {
			unset( $request['amount'] );
		}

		if ( $reason ) {
			// Trim the refund reason to a max of 500 characters due to Stripe limits: https://docs.stripe.com/api/metadata.
			if ( strlen( $reason ) > 500 ) {
				$reason = function_exists( 'mb_substr' ) ? mb_substr( $reason, 0, 450 ) : substr( $reason, 0, 450 );
				// Add some explainer text indicating where to find the full refund reason.
				$reason = $reason . '... [See WooCommerce order page for full text.]';
			}

			$request['metadata'] = [
				'reason' => $reason,
			];
		}

		// Only treat zero-amount as a no-op for captured charges (real refunds), not for voiding pre-auths.
		if ( 'yes' === $captured && '0.00' === sprintf( '%0.2f', $amount ?? 0 ) ) {
			return true;
		}

		$order_helper = WC_Stripe_Order_Helper::get_instance();

		$request['charge'] = $charge_id;
		WC_Stripe_Logger::log( "Info: Beginning refund for order {$charge_id} for the amount of {$amount}" );
		$response = new stdClass();
		try {
			$request = apply_filters( 'wc_stripe_refund_request', $request, $order );

			$intent           = $this->get_intent_from_order( $order );
			$intent_cancelled = false;
			if ( $intent ) {
				// If the order has a Payment Intent pending capture, then the Intent itself must be refunded (cancelled), not the Charge.
				if ( ! empty( $intent->error ) ) {
					$response         = $intent;
					$intent_cancelled = true;
				} elseif ( WC_Stripe_Intent_Status::REQUIRES_CAPTURE === $intent->status ) {
					$result           = WC_Stripe_API::request(
						[],
						'payment_intents/' . $intent->id . '/cancel'
					);
					$intent_cancelled = true;

					if ( ! empty( $result->error ) ) {
						$response = $result;
					} else {
						$charge = $this->get_charge_object( $result->latest_charge, [ 'expand' => [ 'refunds' ] ] );

						if ( isset( $charge->refunds->data ) ) {
							$response = end( $charge->refunds->data );
						}
					}
				}
			}

			if ( ! $intent_cancelled && 'yes' === $captured ) {
				$order_helper->lock_order_refund( $order );
				$response = WC_Stripe_API::request( $request, 'refunds' );
			}
		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );
			$order_helper->unlock_order_refund( $order );

			return new WP_Error(
				'stripe_error',
				sprintf(
					/* translators: %1$s is a stripe error message */
					__( 'There was a problem initiating a refund: %1$s', 'woocommerce-gateway-stripe' ),
					$e->getMessage()
				)
			);
		}

		if ( ! empty( $response->error ) ) { // @phpstan-ignore-line (return statement is added)
			WC_Stripe_Logger::log( 'Error: ' . $response->error->message );
			$order_helper->unlock_order_refund( $order );

			return new WP_Error(
				'stripe_error',
				sprintf(
					/* translators: %1$s is a stripe error message */
					__( 'There was a problem initiating a refund: %1$s', 'woocommerce-gateway-stripe' ),
					$response->error->message
				)
			);

		} elseif ( ! empty( $response->id ) ) {
			$formatted_amount = wc_price( $response->amount / 100 );
			if ( in_array( strtolower( $order->get_currency() ), WC_Stripe_Helper::no_decimal_currencies(), true ) ) {
				$formatted_amount = wc_price( $response->amount );
			}

			// If charge wasn't captured, skip creating a refund and cancel order.
			if ( 'yes' !== $captured ) {
				/* translators: amount (including currency symbol) */
				$order->add_order_note( sprintf( __( 'Pre-Authorization for %s voided.', 'woocommerce-gateway-stripe' ), $formatted_amount ) );
				$order->update_status( OrderStatus::CANCELLED );
				// If amount is set, that means this function was called from the manual refund form.
				if ( ! is_null( $amount ) ) {
					// Throw an exception to provide a custom message on why the refund failed.
					throw new Exception( __( 'The authorization was voided and the order cancelled. Click okay to continue, then refresh the page.', 'woocommerce-gateway-stripe' ) );
				} else {
					// If refund was initiaded by changing order status, prevent refund without errors.
					return false;
				}
			}

			$order_helper->update_stripe_refund( $order, $response->id );

			if ( isset( $response->balance_transaction ) ) {
				$this->update_fees( $order, $response->balance_transaction );
			}

			/* translators: 1) amount (including currency symbol) 2) transaction id 3) refund message */
			$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-stripe' ), $formatted_amount, $response->id, $reason );

			$order->add_order_note( $refund_message );
			$order_helper->unlock_order_refund( $order );

			WC_Stripe_Logger::log( 'Success: ' . html_entity_decode( wp_strip_all_tags( $refund_message ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ) );

			return true;
		}
	}

	/**
	 * Add payment method via account screen.
	 * We don't store the token locally, but to the Stripe API.
	 *
	 * @since 3.0.0
	 * @version 4.0.0
	 *
	 * @return array
	 */
	public function add_payment_method() {
		try {
			if ( ! is_user_logged_in() ) {
				throw new WC_Stripe_Exception( 'No logged-in user found.' );
			}

			// Retrieve the source object from the submitted $_POST data.
			$source_object = $this->get_source_object_from_request();

			if ( empty( $source_object ) || empty( $source_object->id ) ) {
				throw new WC_Stripe_Exception( "The retrieved source doesn't contain an ID." );
			}

			// Non-reusable payment methods won't be attached.
			if ( ! WC_Stripe_Helper::is_reusable_payment_method( $source_object ) ) {
				throw new WC_Stripe_Exception(
					"The provided payment method isn't reausable." .
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					PHP_EOL . 'Source object: ' . print_r( $source_object, true )
				);
			}

			$this->maybe_attach_source_to_customer( $source_object );

			// Now that we've got the source object, attach it to the user.
			$this->save_payment_method( $source_object );

		} catch ( WC_Stripe_Exception $e ) {
			WC_Stripe_Logger::log(
				sprintf(
					'Add payment method Error: %s',
					$e->getMessage()
				)
			);

			return [ 'result' => 'failure' ];
		}

		$payment_method_name = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '';

		do_action( 'wc_stripe_add_payment_method_' . $payment_method_name . '_success', $source_object->id, $source_object );

		return [
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		];
	}

	/**
	 * Gets the locale with normalization that only Stripe accepts.
	 *
	 * @since 4.0.6
	 * @return string $locale
	 */
	public function get_locale() {
		$locale = get_locale();

		/*
		 * Stripe expects Norwegian to only be passed NO.
		 * But WP has different dialects.
		 */
		if ( 'NO' === substr( $locale, 3, 2 ) ) {
			$locale = 'no';
		} else {
			$locale = substr( get_locale(), 0, 2 );
		}

		return $locale;
	}

	/**
	 * Change the idempotency key so charge can
	 * process order as a different transaction.
	 *
	 * @since 4.0.6
	 * @param string $idempotency_key
	 * @param array  $request
	 */
	public function change_idempotency_key( $idempotency_key, $request ) {
		$customer = ! empty( $request['customer'] ) ? $request['customer'] : '';
		$source   = ! empty( $request['source'] ) ? $request['source'] : $customer;
		$count    = $this->retry_interval;

		return $request['metadata']['order_id'] . '-' . $count . '-' . $source;
	}

	/**
	 * Checks if request is the original to prevent double processing
	 * on WC side. The original-request header and request-id header
	 * needs to be the same to mean its the original request.
	 *
	 * @since 4.0.6
	 * @param array $headers
	 */
	public function is_original_request( $headers ) {
		if ( $headers['original-request'] === $headers['request-id'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Generates the request when creating a new payment intent.
	 *
	 * @param WC_Order $order           The order that is being paid for.
	 * @param object   $prepared_source The source that is used for the payment.
	 * @return array                    The arguments for the request.
	 */
	public function generate_create_intent_request( $order, $prepared_source ) {
		// The request for a charge contains metadata for the intent.
		$full_request = $this->generate_payment_request( $order, $prepared_source );

		$payment_method_types = [ WC_Stripe_Payment_Methods::CARD ];
		if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			$payment_method_types = $this->get_upe_enabled_at_checkout_payment_method_ids(); // @phpstan-ignore-line (get_upe_enabled_at_checkout_payment_method_ids is defined in the classes that use this class)
		} elseif ( isset( $prepared_source->source_object->type ) ) {
			$payment_method_types = [ $prepared_source->source_object->type ];
		}

		$currency = strtolower( $order->get_currency() );

		$request = [
			'amount'               => WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency ),
			'currency'             => $currency,
			'description'          => $full_request['description'],
			'metadata'             => $full_request['metadata'],
			'capture_method'       => ( 'true' === $full_request['capture'] ) ? 'automatic' : 'manual',
			'payment_method_types' => $payment_method_types,
		];

		$request = WC_Stripe_Helper::add_payment_method_to_request_array( $prepared_source->source, $request );

		$force_save_source = apply_filters( 'wc_stripe_force_save_source', false, $prepared_source->source );

		if ( $this->save_payment_method_requested() || $this->has_subscription( $order->get_id() ) || $force_save_source ) {
			$request['setup_future_usage']              = 'off_session';
			$request['metadata']['save_payment_method'] = 'true';
		}

		if ( $prepared_source->customer ) {
			$request['customer'] = $prepared_source->customer;
		}

		if ( isset( $full_request['statement_descriptor_suffix'] ) ) {
			$request['statement_descriptor_suffix'] = $full_request['statement_descriptor_suffix'];
		}

		if ( isset( $full_request['shipping'] ) ) {
			$request['shipping'] = $full_request['shipping'];
		}

		if ( isset( $full_request['receipt_email'] ) ) {
			$request['receipt_email'] = $full_request['receipt_email'];
		}

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_create_intent_request.
		 *
		 * @since 3.1.0
		 * @param array $request
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_stripe_generate_create_intent_request', $request, $order, $prepared_source );
	}

	/**
	 * Create the level 3 data array to send to Stripe when making a purchase.
	 *
	 * @param WC_Order $order The order that is being paid for.
	 * @return array          The level 3 data to send to Stripe.
	 * @throws WC_Stripe_Exception If an order item has no quantity set.
	 */
	public function get_level3_data_from_order( $order ) {
		// Get the order items. Don't need their keys, only their values.
		// Order item IDs are used as keys in the original order items array.
		$order_items = array_values( $order->get_items( [ 'line_item', 'fee' ] ) );
		$currency    = $order->get_currency();
		$order_id    = $order->get_id();

		$stripe_line_items = array_map(
			function ( $item ) use ( $currency, $order_id ) {
				if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
					$product_id = $item->get_variation_id()
						? $item->get_variation_id()
						: $item->get_product_id();
					$subtotal   = $item->get_subtotal();
				} else {
					$product_id = substr( sanitize_title( $item->get_name() ), 0, 12 );
					$subtotal   = $item->get_total();
				}
				$product_description = substr( $item->get_name(), 0, 26 );
				$quantity            = $item->get_quantity();
				if ( ! $quantity ) {
					$error_msg = "Stripe Level 3 data: Order item with ID {$item->get_id()} from order ID {$order_id} has no quantity set.";
					WC_Stripe_Logger::error( $error_msg );
					throw new WC_Stripe_Exception( $error_msg );
				}
				$unit_cost       = WC_Stripe_Helper::get_stripe_amount( ( $subtotal / $quantity ), $currency );
				$tax_amount      = WC_Stripe_Helper::get_stripe_amount( $item->get_total_tax(), $currency );
				$discount_amount = WC_Stripe_Helper::get_stripe_amount( $subtotal - $item->get_total(), $currency );

				return (object) [
					'product_code'        => (string) $product_id, // Up to 12 characters that uniquely identify the product.
					'product_description' => $product_description, // Up to 26 characters long describing the product.
					'unit_cost'           => $unit_cost, // Cost of the product, in cents, as a non-negative integer.
					'quantity'            => $quantity, // The number of items of this type sold, as a non-negative integer.
					'tax_amount'          => $tax_amount, // The amount of tax this item had added to it, in cents, as a non-negative integer.
					'discount_amount'     => $discount_amount, // The amount an item was discounted—if there was a sale,for example, as a non-negative integer.
				];
			},
			$order_items
		);

		$level3_data = [
			'merchant_reference' => $order->get_id(), // An alphanumeric string of up to  characters in length. This unique value is assigned by the merchant to identify the order. Also known as an “Order ID”.
			'shipping_amount'    => WC_Stripe_Helper::get_stripe_amount( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(), $currency ), // The shipping cost, in cents, as a non-negative integer.
			'line_items'         => $stripe_line_items,
		];

		// The customer’s U.S. shipping ZIP code.
		$shipping_address_zip = $order->get_shipping_postcode();
		if ( $this->is_valid_us_zip_code( $shipping_address_zip ) ) {
			$level3_data['shipping_address_zip'] = $shipping_address_zip;
		}

		// The merchant’s U.S. shipping ZIP code.
		$store_postcode = get_option( 'woocommerce_store_postcode' );
		if ( $this->is_valid_us_zip_code( $store_postcode ) ) {
			$level3_data['shipping_from_zip'] = $store_postcode;
		}

		/**
		 * Filters the Level 3 data based on order.
		 *
		 * Example usage: Enables updating the discount based on the products in the order,
		 * if any of the products are gift cards.
		 *
		 * @since 8.6.0
		 *
		 * @param array $level3_data Precalculated Level 3 data based on order.
		 * @param WC_Order $order    The order object.
		 */
		return apply_filters( 'wc_stripe_payment_request_level3_data', $level3_data, $order );
	}

	/**
	 * Create a new PaymentIntent.
	 *
	 * @param WC_Order $order           The order that is being paid for.
	 * @param object   $prepared_source The source that is used for the payment.
	 * @return object                   An intent or an error.
	 */
	public function create_intent( $order, $prepared_source ) {
		$request = $this->generate_create_intent_request( $order, $prepared_source );

		// Create an intent that awaits an action.
		$intent = WC_Stripe_API::request( $request, 'payment_intents' );
		if ( ! empty( $intent->error ) ) {
			return $intent;
		}

		$order_id = $order->get_id();
		WC_Stripe_Logger::log( "Stripe PaymentIntent $intent->id initiated for order $order_id" );

		// Save the intent ID to the order.
		$this->save_intent_to_order( $order, $intent );

		return $intent;
	}

	/**
	 * Updates an existing intent with updated amount, source, and customer.
	 *
	 * @param object   $intent          The existing intent object.
	 * @param WC_Order $order           The order.
	 * @param object   $prepared_source Currently selected source.
	 * @return object                   An updated intent.
	 */
	public function update_existing_intent( $intent, $order, $prepared_source ) {
		$request = [];

		if ( $prepared_source->source !== $intent->source ) {
			$request = WC_Stripe_Helper::add_payment_method_to_request_array( $prepared_source->source, $request );
		}

		$currency   = strtolower( $order->get_currency() );
		$new_amount = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency );
		if ( $intent->amount !== $new_amount ) {
			$request['amount'] = $new_amount;
		}

		if ( $prepared_source->customer && $intent->customer !== $prepared_source->customer ) {
			$request['customer'] = $prepared_source->customer;
		}

		$request['payment_method_types'] = [ WC_Stripe_Payment_Methods::CARD ];

		if ( $this->has_subscription( $order->get_id() ) ) {
			// If this is a failed subscription order payment, the intent should be
			// prepared for future usage.
			$request['setup_future_usage'] = 'off_session';
		}

		if ( empty( $request ) ) {
			return $intent;
		}

		/**
		 * Filter the value of the request.
		 *
		 * @since 6.1.0
		 * @param array $request  Request to send to Stripe API.
		 * @param WC_Order $order Order that the intent is associated with.
		 * @param object $source  Currently selected source.
		 */
		$request = apply_filters( 'wc_stripe_update_existing_intent_request', $request, $order, $prepared_source );

		$level3_data = $this->get_level3_data_from_order( $order );
		return WC_Stripe_API::request_with_level3_data(
			$request,
			"payment_intents/$intent->id",
			$level3_data,
			$order
		);
	}

	/**
	 * Confirms an intent if it is the `requires_confirmation` state.
	 *
	 * @since 4.2.1
	 * @param object   $intent          The intent to confirm.
	 * @param WC_Order $order           The order that the intent is associated with.
	 * @param object   $prepared_source The source that is being charged.
	 * @return object                   Either an error or the updated intent.
	 */
	public function confirm_intent( $intent, $order, $prepared_source ) {
		if ( WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION !== $intent->status ) {
			return $intent;
		}

		// Try to confirm the intent & capture the charge (if 3DS is not required).
		$confirm_request = WC_Stripe_Helper::add_payment_method_to_request_array( $prepared_source->source, [] );

		$level3_data      = $this->get_level3_data_from_order( $order );
		$confirmed_intent = WC_Stripe_API::request_with_level3_data(
			$confirm_request,
			"payment_intents/$intent->id/confirm",
			$level3_data,
			$order
		);

		if ( ! empty( $confirmed_intent->error ) ) {
			return $confirmed_intent;
		}

		// Save a note about the status of the intent.
		$order_id = $order->get_id();
		if ( WC_Stripe_Intent_Status::SUCCEEDED === $confirmed_intent->status ) {
			WC_Stripe_Logger::log( "Stripe PaymentIntent $intent->id succeeded for order $order_id" );
		} elseif ( WC_Stripe_Intent_Status::REQUIRES_ACTION === $confirmed_intent->status ) {
			WC_Stripe_Logger::log( "Stripe PaymentIntent $intent->id requires authentication for order $order_id" );
		}

		return $confirmed_intent;
	}

	/**
	 * Saves intent to order.
	 *
	 * @since 3.2.0
	 * @param WC_Order $order For to which the source applies.
	 * @param stdClass $intent Payment intent information.
	 */
	public function save_intent_to_order( $order, $intent ) {
		// Don't save any intent information on a subscription.
		if ( $this->is_subscription( $order ) ) {
			return;
		}

		$order_helper = WC_Stripe_Order_Helper::get_instance();
		if ( 'payment_intent' === $intent->object ) {
			$order_helper->add_payment_intent_to_order( $intent->id, $order );

			// TODO: Refactor and add mandate ID support for other payment methods, if necessary.
			// The mandate ID is not available for the intent object, so we need to fetch the charge.
			// Mandate ID is necessary for renewal payments for certain payment methods and Indian cards.
			$charge = $this->get_latest_charge_from_intent( $intent );

			if ( isset( $charge->payment_method_details->card->mandate ) ) {
				$order->update_meta_data( '_stripe_mandate_id', $charge->payment_method_details->card->mandate );
			} elseif ( isset( $charge->payment_method_details->acss_debit->mandate ) ) {
				$order->update_meta_data( '_stripe_mandate_id', $charge->payment_method_details->acss_debit->mandate );
			}
		} elseif ( 'setup_intent' === $intent->object ) {
			$order_helper->update_stripe_setup_intent( $order, $intent->id );

			// Add mandate for free trial subscriptions.
			if ( isset( $intent->mandate ) ) {
				$order->update_meta_data( '_stripe_mandate_id', $intent->mandate );
			}
		}

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}
	}

	/**
	 * Retrieves the payment intent, associated with an order.
	 *
	 * @since 4.2
	 * @param WC_Order $order The order to retrieve an intent for.
	 * @return obect|bool     Either the intent object or `false`.
	 */
	public function get_intent_from_order( $order ) {
		$order_helper = WC_Stripe_Order_Helper::get_instance();
		$intent_id    = $order_helper->get_stripe_intent( $order );
		if ( $intent_id ) {
			return $this->get_intent( 'payment_intents', $intent_id );
		}

		// The order doesn't have a payment intent, but it may have a setup intent.
		$intent_id = $order_helper->get_stripe_setup_intent( $order );
		if ( $intent_id ) {
			return $this->get_intent( 'setup_intents', $intent_id );
		}

		return false;
	}

	/**
	 * Retrieves intent from Stripe API by intent id.
	 *
	 * @param string $intent_type   Either 'payment_intents' or 'setup_intents'.
	 * @param string $intent_id     Intent id.
	 * @return object|bool          Either the intent object or `false`.
	 * @throws Exception            Throws exception for unknown $intent_type.
	 */
	private function get_intent( $intent_type, $intent_id ) {
		if ( ! in_array( $intent_type, [ 'payment_intents', 'setup_intents' ], true ) ) {
			throw new Exception( "Failed to get intent of type $intent_type. Type is not allowed" );
		}

		$response = WC_Stripe_API::request( [], "$intent_type/$intent_id?expand[]=payment_method", 'GET' );

		if ( $response && isset( $response->{ 'error' } ) ) {
			$error_response_message = print_r( $response, true );
			WC_Stripe_Logger::log( "Failed to get Stripe intent $intent_type/$intent_id." );
			WC_Stripe_Logger::log( "Response: $error_response_message" );
			return false;
		}

		return $response;
	}

	/**
	 * Locks an order for payment intent processing for 5 minutes.
	 *
	 * @since 4.2
	 * @param WC_Order $order  The order that is being paid.
	 * @return bool            A flag that indicates whether the order is already locked.
	 *
	 * @deprecated 10.0.0 Deprecated in favor of WC_Stripe_Order_Helper::lock_order_payment().
	 */
	public function lock_order_payment( $order ) {
		if ( $this->is_order_payment_locked( $order ) ) {
			// If the order is already locked, return true.
			return true;
		}

		$new_lock = ( time() + 5 * MINUTE_IN_SECONDS );

		$order->update_meta_data( '_stripe_lock_payment', $new_lock );
		$order->save_meta_data();

		return false;
	}

	/**
	 * Unlocks an order for processing by payment intents.
	 *
	 * @since 4.2
	 * @param WC_Order $order The order that is being unlocked.
	 *
	 * @deprecated 10.0.0 Deprecated in favor of WC_Stripe_Order_Helper::unlock_order_payment().
	 */
	public function unlock_order_payment( $order ) {
		$order->delete_meta_data( '_stripe_lock_payment' );
		$order->save_meta_data();
	}

	/**
	 * Retrieves the existing lock for an order.
	 *
	 * @param WC_Order $order The order to retrieve the lock for
	 * @return mixed
	 *
	 * @deprecated 10.0.0 Deprecated in favor of WC_Stripe_Order_Helper::get_order_payment_lock().
	 */
	protected function get_order_existing_lock( $order ) {
		$order->read_meta_data( true );
		return $order->get_meta( '_stripe_lock_payment', true );
	}

	/**
	 * Checks if an order is locked for payment processing.
	 *
	 * @param WC_Order $order The order to check the lock for
	 * @return bool
	 *
	 * @deprecated 10.0.0 Deprecated in favor of WC_Stripe_Order_Helper::is_order_payment_locked().
	 */
	protected function is_order_payment_locked( $order ) {
		$existing_lock = $this->get_order_existing_lock( $order );
		if ( $existing_lock ) {
			$parts      = explode( '|', $existing_lock ); // Format is: "{expiry_timestamp}"
			$expiration = (int) $parts[0];

			// If the lock is still active, return true.
			if ( time() <= $expiration ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Locks an order for refund processing for 5 minutes.
	 *
	 * @since 9.1.0
	 * @param WC_Order $order  The order that is being refunded.
	 * @return bool            A flag that indicates whether the order is already locked.
	 *
	 * @deprecated 10.0.0 Deprecated in favor of WC_Stripe_Order_Helper::lock_order_refund().
	 */
	public function lock_order_refund( $order ) {
		$order->read_meta_data( true );

		$existing_lock = $order->get_meta( '_stripe_lock_refund', true );

		if ( $existing_lock ) {
			$expiration = (int) $existing_lock;

			// If the lock is still active, return true.
			if ( time() <= $expiration ) {
				return true;
			}
		}

		$new_lock = time() + 5 * MINUTE_IN_SECONDS;

		$order->update_meta_data( '_stripe_lock_refund', $new_lock );
		$order->save_meta_data();

		return false;
	}

	/**
	 * Unlocks an order for processing refund.
	 *
	 * @since 9.1.0
	 * @param WC_Order $order The order that is being unlocked.
	 *
	 * @deprecated 10.0.0 Deprecated in favor of WC_Stripe_Order_Helper::unlock_order_refund().
	 */
	public function unlock_order_refund( $order ) {
		$order->delete_meta_data( '_stripe_lock_refund' );
		$order->save_meta_data();
	}

	/**
	 * Given a response from Stripe, check if it's a card error where authentication is required
	 * to complete the payment.
	 *
	 * @param object $response The response from Stripe.
	 * @return boolean Whether or not it's a 'authentication_required' error
	 */
	public function is_authentication_required_for_payment( $response ) {
		return ( ! empty( $response->error->code ) && 'authentication_required' === $response->error->code )
			|| ( ! empty( $response->last_payment_error->code ) && 'authentication_required' === $response->last_payment_error->code );
	}

	/**
	 * Creates a SetupIntent for future payments, and saves it to the order.
	 *
	 * @param WC_Order $order           The ID of the (free/pre- order).
	 * @param object   $prepared_source The source, entered/chosen by the customer.
	 * @return string|null              The client secret of the intent, used for confirmation in JS.
	 */
	public function setup_intent( $order, $prepared_source ) {
		// SEPA Direct Debit payments do not require any customer action after the source has been created.
		// Once the customer has provided their IBAN details and accepted the mandate, no further action is needed and the resulting source is directly chargeable.
		if ( WC_Stripe_Payment_Methods::SEPA_DEBIT === $prepared_source->source_object->type ) {
			return;
		}

		$order_id     = $order->get_id();
		$setup_intent = WC_Stripe_API::request(
			[
				'payment_method' => $prepared_source->source,
				'return_url'     => $this->get_stripe_return_url( $order ),
				'customer'       => $prepared_source->customer,
				'confirm'        => 'true',
			],
			'setup_intents'
		);

		if ( is_wp_error( $setup_intent ) ) {
			WC_Stripe_Logger::log( "Unable to create SetupIntent for Order #$order_id: " . print_r( $setup_intent, true ) );
		} elseif ( WC_Stripe_Intent_Status::REQUIRES_ACTION === $setup_intent->status ) {
			WC_Stripe_Order_Helper::get_instance()->update_stripe_setup_intent( $order, $setup_intent->id );
			$order->save();

			return $setup_intent->client_secret;
		}
	}

	/**
	 * Create and confirm a new PaymentIntent.
	 *
	 * @param WC_Order $order           The order that is being paid for.
	 * @param object   $prepared_source The source that is used for the payment.
	 * @param float    $amount          The amount to charge. If not specified, it will be read from the order.
	 * @return object                   An intent or an error.
	 */
	public function create_and_confirm_intent_for_off_session( $order, $prepared_source, $amount = null ) {
		// The request for a charge contains metadata for the intent.
		$full_request = $this->generate_payment_request( $order, $prepared_source );

		$payment_method_types = [ WC_Stripe_Payment_Methods::CARD ];

		if ( isset( $prepared_source->source_object->type ) ) {
			$payment_method_types = [ $prepared_source->source_object->type ];
		}

		$request = [
			'amount'               => $amount ? WC_Stripe_Helper::get_stripe_amount( $amount, $full_request['currency'] ) : $full_request['amount'],
			'currency'             => $full_request['currency'],
			'description'          => $full_request['description'],
			'metadata'             => $full_request['metadata'],
			'payment_method_types' => $payment_method_types,
			'off_session'          => 'true',
			'confirm'              => 'true',
			'confirmation_method'  => 'automatic',
			'capture_method'       => 'automatic',
		];

		if ( isset( $full_request['statement_descriptor_suffix'] ) ) {
			$request['statement_descriptor_suffix'] = $full_request['statement_descriptor_suffix'];
		}

		if ( isset( $full_request['customer'] ) ) {
			$request['customer'] = $full_request['customer'];
		}

		if ( isset( $full_request['source'] ) ) {
			$request = WC_Stripe_Helper::add_payment_method_to_request_array( $full_request['source'], $request );
		}

		// Add mandate if it exists.
		$mandate = $order->get_meta( '_stripe_mandate_id', true );
		if ( ! empty( $mandate ) ) {
			$request['mandate'] = $mandate;
		}

		/**
		 * Filter the value of the request.
		 *
		 * @since 4.5.0
		 * @param array $request
		 * @param WC_Order $order
		 * @param object $source
		 */
		$request = apply_filters( 'wc_stripe_generate_create_intent_request', $request, $order, $prepared_source );

		if ( isset( $full_request['shipping'] ) ) {
			$request['shipping'] = $full_request['shipping'];
		}

		$level3_data                = $this->get_level3_data_from_order( $order );
		$intent                     = WC_Stripe_API::request_with_level3_data(
			$request,
			'payment_intents',
			$level3_data,
			$order
		);
		$is_authentication_required = $this->is_authentication_required_for_payment( $intent );

		if ( ! empty( $intent->error ) && ! $is_authentication_required ) {
			return $intent;
		}

		$intent_id      = ( ! empty( $intent->error )
			? $intent->error->payment_intent->id
			: $intent->id
		);
		$payment_intent = ( ! empty( $intent->error )
			? $intent->error->payment_intent
			: $intent
		);
		$order_id       = $order->get_id();
		WC_Stripe_Logger::log( "Stripe PaymentIntent $intent_id initiated for order $order_id" );

		// Save the intent ID to the order.
		$this->save_intent_to_order( $order, $payment_intent );

		return $intent;
	}

	/** Verifies whether a certain ZIP code is valid for the US, incl. 4-digit extensions.
	 *
	 * @param string $zip The ZIP code to verify.
	 * @return boolean
	 */
	public function is_valid_us_zip_code( $zip ) {
		return ! empty( $zip ) && preg_match( '/^\d{5,5}(-\d{4,4})?$/', $zip );
	}

	/**
	 * Checks if the current page is the pay for order page and the current user is allowed to pay for the order.
	 *
	 * @return bool
	 */
	public function is_valid_pay_for_order_endpoint(): bool {

		// If not on the pay for order page, return false.
		if ( ! is_wc_endpoint_url( 'order-pay' ) || ! isset( $_GET['key'] ) ) {
			return false;
		}

		$order_id = absint( get_query_var( 'order-pay' ) );
		$order    = wc_get_order( $order_id );

		// If the order is not found or the param `key` is not set or the order key does not match the order key in the URL param, return false.
		if ( ! $order || ! isset( $_GET['key'] ) || wc_clean( wp_unslash( $_GET['key'] ) ) !== $order->get_order_key() ) {
			return false;
		}

		// If the order doesn't need payment, we don't need to prepare the payment page.
		if ( ! $order->needs_payment() ) {
			return false;
		}

		return current_user_can( 'pay_for_order', $order->get_id() );
	}

	/**
	 * Checks if the current page is the order received page and the current user is allowed to manage the order.
	 *
	 * @return bool
	 */
	public function is_valid_order_received_endpoint(): bool {
		// Verify nonce. Duplicated here in order to avoid PHPCS warnings.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wpnonce'] ) ), 'wc_stripe_process_redirect_order_nonce' ) ) {
			return false;
		}

		// If not on the order-received page, return false.
		if ( ! is_wc_endpoint_url( 'order-received' ) || ! isset( $_GET['key'] ) ) {
			return false;
		}

		$order_id_from_order_key = absint( wc_get_order_id_by_order_key( wc_clean( wp_unslash( $_GET['key'] ) ) ) );
		$order_id_from_query_var = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : null;

		// If the order ID is not found or the order ID does not match the given order ID, return false.
		if ( ! $order_id_from_order_key || ( $order_id_from_query_var !== $order_id_from_order_key ) ) {
			return false;
		}

		$order = wc_get_order( $order_id_from_order_key );

		// If the order doesn't need payment, return false.
		if ( ! $order->needs_payment() ) {
			return false;
		}

		return current_user_can( 'pay_for_order', $order->get_id() );
	}

	/**
	 * Gets a localized message for an error from a response, adds it as a note to the order, and throws it.
	 *
	 * @since 4.2.0
	 * @param  stdClass $response  The response from the Stripe API.
	 * @param  WC_Order $order     The order to add a note to.
	 * @throws WC_Stripe_Exception An exception with the right message.
	 */
	public function throw_localized_message( $response, $order ) {
		$localized_message = $this->get_localized_error_message_from_response( $response );

		$order->add_order_note( $localized_message );

		throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
	}

	/**
	 * Generates a localized message for an error from a response.
	 *
	 * @since 4.3.2
	 *
	 * @param stdClass $response The response from the Stripe API.
	 *
	 * @return string The localized error message.
	 */
	public function get_localized_error_message_from_response( $response ) {
		$localized_messages = WC_Stripe_Helper::get_localized_messages();

		if ( 'card_error' === $response->error->type ) {
			$localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
		} else {
			$localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
		}

		return $localized_message;
	}

	/**
	 * Payment_scripts function.
	 *
	 * Outputs scripts used for stripe payment
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 */
	public function payment_scripts() {
		if ( ( ! is_product()
				&& ! WC_Stripe_Helper::has_cart_or_checkout_on_current_page()
				&& ! $this->is_valid_pay_for_order_endpoint()
				&& ! is_add_payment_method_page()
				&& ! isset( $_GET['change_payment_method'] ) // phpcs:ignore WordPress.Security.NonceVerification
				&& ! ( ! empty( get_query_var( 'view-subscription' ) ) && is_callable( 'WCS_Early_Renewal_Manager::is_early_renewal_via_modal_enabled' ) && WCS_Early_Renewal_Manager::is_early_renewal_via_modal_enabled() ) // @phpstan-ignore-line (Class WCS_Early_Renewal_Manager is checked already)
			) || ( is_order_received_page() )
		) {
			return;
		}

		if ( is_product() && ! WC_Stripe_Helper::should_load_scripts_on_product_page() ) {
			return;
		}

		if ( is_cart() && ! WC_Stripe_Helper::should_load_scripts_on_cart_page() ) {
			return;
		}

		// Bail if no Stripe payment method is enabled.
		if ( 'no' === $this->enabled && empty( WC_Stripe_Helper::get_legacy_enabled_payment_methods() ) ) {
			return;
		}

		// If keys are not set bail.
		if ( ! $this->are_keys_set() ) {
			WC_Stripe_Logger::log( 'Keys are not set correctly.' );
			return;
		}

		// If no SSL bail.
		if ( $this->needs_ssl_setup() ) {
			WC_Stripe_Logger::log( 'Stripe live mode requires SSL.' );
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style( 'stripe_styles', plugins_url( 'assets/css/stripe-styles.css', WC_STRIPE_MAIN_FILE ), [], WC_STRIPE_VERSION );
		wp_enqueue_style( 'stripe_styles' );

		wp_register_script( 'stripe', 'https://js.stripe.com/v3/', '', '3.0', true );
		wp_register_script( 'woocommerce_stripe', plugins_url( 'assets/js/stripe' . $suffix . '.js', WC_STRIPE_MAIN_FILE ), [ 'jquery-payment', 'stripe' ], WC_STRIPE_VERSION, true );

		wp_localize_script(
			'woocommerce_stripe',
			'wc_stripe_params',
			apply_filters( 'wc_stripe_params', $this->javascript_params() )
		);

		$this->tokenization_script();
		wp_enqueue_script( 'woocommerce_stripe' );
	}

	/**
	 * Attaches the given payment method to the currently logged-in user.
	 *
	 * @param object $source_object The payment method to be attached.
	 * @throws WC_Stripe_Exception
	 */
	public function save_payment_method( $source_object ) {
		$user_id  = get_current_user_id();
		$customer = new WC_Stripe_Customer( $user_id );

		if ( $user_id && WC_Stripe_Helper::is_reusable_payment_method( $source_object ) ) {
			$response = $customer->add_source( $source_object->id );

			if ( ! empty( $response->error ) ) {
				// Formatting the response for the debug log entry.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				throw new WC_Stripe_Exception( print_r( $response, true ), $this->get_localized_error_message_from_response( $response ) );
			}
			if ( is_wp_error( $response ) ) {
				throw new WC_Stripe_Exception( $response->get_error_message(), $response->get_error_message() );
			}
		}
	}

	/**
	 * Returns the JavaScript configuration object used on the product, cart, and checkout pages.
	 *
	 * @return array  The configuration object to be loaded to JS.
	 */
	public function javascript_params() {
		global $wp;

		$order_id = absint( get_query_var( 'order-pay' ) );

		$stripe_params = [
			'title'                    => $this->title,
			'key'                      => $this->publishable_key, // @phpstan-ignore-line (publishable_key is defined in the classes that use this class)
			'i18n_terms'               => __( 'Please accept the terms and conditions first', 'woocommerce-gateway-stripe' ),
			'i18n_required_fields'     => __( 'Please fill in required checkout fields first', 'woocommerce-gateway-stripe' ),
			'updateFailedOrderNonce'   => wp_create_nonce( 'wc_stripe_update_failed_order_nonce' ),
			'updatePaymentIntentNonce' => wp_create_nonce( 'wc_stripe_update_payment_intent_nonce' ),
			'orderId'                  => $order_id,
			'checkout_url'             => WC_AJAX::get_endpoint( 'checkout' ),
		];

		// If we're on the pay page we need to pass stripe.js the address of the order.
		if ( $this->is_valid_pay_for_order_endpoint() || $this->is_changing_payment_method_for_subscription() ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			if ( is_a( $order, 'WC_Order' ) ) {
				$stripe_params['billing_first_name'] = $order->get_billing_first_name();
				$stripe_params['billing_last_name']  = $order->get_billing_last_name();
				$stripe_params['billing_address_1']  = $order->get_billing_address_1();
				$stripe_params['billing_address_2']  = $order->get_billing_address_2();
				$stripe_params['billing_state']      = $order->get_billing_state();
				$stripe_params['billing_city']       = $order->get_billing_city();
				$stripe_params['billing_postcode']   = $order->get_billing_postcode();
				$stripe_params['billing_country']    = $order->get_billing_country();
			}
		}

		$sepa_elements_options = apply_filters(
			'wc_stripe_sepa_elements_options',
			[
				'supportedCountries' => [ 'SEPA' ],
				'placeholderCountry' => WC()->countries->get_base_country(),
				'style'              => [ 'base' => [ 'fontSize' => '15px' ] ],
			]
		);

		$stripe_params['stripe_locale']               = WC_Stripe_Helper::convert_wc_locale_to_stripe_locale( get_locale() );
		$stripe_params['no_prepaid_card_msg']         = __( 'Sorry, we\'re not accepting prepaid cards at this time. Your credit card has not been charged. Please try with alternative payment method.', 'woocommerce-gateway-stripe' );
		$stripe_params['no_sepa_owner_msg']           = __( 'Please enter your IBAN account name.', 'woocommerce-gateway-stripe' );
		$stripe_params['no_sepa_iban_msg']            = __( 'Please enter your IBAN account number.', 'woocommerce-gateway-stripe' );
		$stripe_params['payment_intent_error']        = __( 'We couldn\'t initiate the payment. Please try again.', 'woocommerce-gateway-stripe' );
		$stripe_params['sepa_mandate_notification']   = apply_filters( 'wc_stripe_sepa_mandate_notification', 'email' );
		$stripe_params['allow_prepaid_card']          = apply_filters( 'wc_stripe_allow_prepaid_card', true ) ? 'yes' : 'no';
		$stripe_params['inline_cc_form']              = ( isset( $this->inline_cc_form ) && $this->inline_cc_form ) ? 'yes' : 'no';
		$stripe_params['is_checkout']                 = ( is_checkout() && empty( $_GET['pay_for_order'] ) ) ? 'yes' : 'no'; // wpcs: csrf ok.
		$stripe_params['return_url']                  = $this->get_stripe_return_url();
		$stripe_params['ajaxurl']                     = WC_AJAX::get_endpoint( '%%endpoint%%' );
		$stripe_params['stripe_nonce']                = wp_create_nonce( '_wc_stripe_nonce' );
		$stripe_params['statement_descriptor']        = $this->statement_descriptor; // @phpstan-ignore-line (statement_descriptor is defined in the classes that use this class)
		$stripe_params['elements_options']            = apply_filters( 'wc_stripe_elements_options', [] );
		$stripe_params['sepa_elements_options']       = $sepa_elements_options;
		$stripe_params['invalid_owner_name']          = __( 'Billing First Name and Last Name are required.', 'woocommerce-gateway-stripe' );
		$stripe_params['is_change_payment_page']      = isset( $_GET['change_payment_method'] ) ? 'yes' : 'no'; // wpcs: csrf ok.
		$stripe_params['is_add_payment_page']         = is_wc_endpoint_url( 'add-payment-method' ) ? 'yes' : 'no';
		$stripe_params['is_pay_for_order_page']       = is_wc_endpoint_url( 'order-pay' ) ? 'yes' : 'no';
		$stripe_params['elements_styling']            = apply_filters( 'wc_stripe_elements_styling', false );
		$stripe_params['elements_classes']            = apply_filters( 'wc_stripe_elements_classes', false );
		$stripe_params['add_card_nonce']              = wp_create_nonce( 'wc_stripe_create_si' );
		$stripe_params['create_payment_intent_nonce'] = wp_create_nonce( 'wc_stripe_create_payment_intent_nonce' );
		$stripe_params['cpf_cnpj_required_msg']       = __( 'CPF/CNPJ is a required field', 'woocommerce-gateway-stripe' );

		// Merge localized messages to be use in JS.
		$stripe_params = array_merge( $stripe_params, WC_Stripe_Helper::get_localized_messages() );

		return $stripe_params;
	}

	/**
	 * Retrieves and returns the source_id for the given $_POST variables.
	 *
	 * @throws WC_Stripe_Exception Error while attempting to retrieve the source_id.
	 * @return object
	 */
	private function get_source_object_from_request() {
		if ( empty( $_POST['stripe_source'] ) && empty( $_POST['stripe_token'] ) ) {
			throw new WC_Stripe_Exception( 'Missing stripe_source and stripe_token from the request.' );
		}

		$source = isset( $_POST['stripe_source'] ) ? wc_clean( wp_unslash( $_POST['stripe_source'] ) ) : '';

		if ( ! empty( $source ) ) {
			// This method throws a WC_Stripe_Exception when there's an error. It's intended to be caught by the calling method.
			$source_object = $this->get_source_object( $source );

			// We better make get_source_object() handle wp_errors to reduce redundancy here.
			if ( is_wp_error( $source_object ) ) {
				throw new WC_Stripe_Exception( $source_object->get_error_message() . ' Code: ' . $source_object->get_error_code() );
			}

			return $source_object;
		}

		$stripe_token_as_source_id = isset( $_POST['stripe_token'] ) ? wc_clean( wp_unslash( $_POST['stripe_token'] ) ) : '';

		if ( ! empty( $stripe_token_as_source_id ) ) {
			// This method throws a WC_Stripe_Exception when there's an error. It's intended to be caught by the calling method.
			$source_object = $this->get_source_object( $stripe_token_as_source_id );

			// We better make get_source_object() handle wp_errors to reduce redundancy here.
			if ( is_wp_error( $source_object ) ) {
				throw new WC_Stripe_Exception( $source_object->get_error_message() . ' Code: ' . $source_object->get_error_code() );
			}

			return $source_object;
		}

		throw new WC_Stripe_Exception( "The source object couldn't be retrieved." );
	}

	/**
	 * Whether the store needs to use SSL.
	 *
	 * @since 7.5.0
	 * @return bool True if SSL is needed but not set.
	 */
	private function needs_ssl_setup() {
		return ! $this->testmode && ! is_ssl(); // @phpstan-ignore-line (testmode is defined in the classes that use this class)
	}

	/**
	 * Retrieves or stores the status of the order before a specific event (hold or refund).
	 *
	 * @param WC_Order $order The order.
	 * @param string   $target_status The target status the order will be set to.
	 * @return string The status of the order before the event.
	 * @throws InvalidArgumentException If the target status is unsupported.
	 */
	protected function get_stripe_order_status_before_event( $order, $target_status ) {
		if ( OrderStatus::ON_HOLD === $target_status ) {
			$meta_key = '_stripe_status_before_hold';
		} elseif ( OrderStatus::REFUNDED === $target_status ) {
			$meta_key = '_stripe_status_before_refund';
		} else {
			// Handle unsupported target_status values.
			throw new InvalidArgumentException( sprintf( 'Unsupported target_status: %s', $target_status ) );
		}

		$status = $order->get_meta( $meta_key );
		if ( ! empty( $status ) ) {
			return $status;
		}

		$default_status = $order->needs_processing() ? OrderStatus::PROCESSING : OrderStatus::COMPLETED;
		return apply_filters( 'woocommerce_payment_complete_order_status', $default_status, $order->get_id(), $order );
	}

	/**
	 * Stores the status of the order before a specific event (hold or refund) in metadata.
	 *
	 * @param WC_Order $order The order.
	 * @param string   $target_status The target status the order will be set to.
	 * @param string   $current_status The order status to store. Accepts 'default_payment_complete' which will fetch the default status for payment complete orders.
	 * @return void
	 */
	protected function set_stripe_order_status_before_event( $order, $target_status, $current_status ) {
		if ( 'default_payment_complete' === $current_status ) {
			$payment_complete_status = $order->needs_processing() ? OrderStatus::PROCESSING : OrderStatus::COMPLETED;
			$current_status          = apply_filters( 'woocommerce_payment_complete_order_status', $payment_complete_status, $order->get_id(), $order );
		}
		if ( OrderStatus::ON_HOLD === $target_status ) {
			$meta_key = '_stripe_status_before_hold';
		}
		if ( OrderStatus::REFUNDED === $target_status ) {
			$meta_key = '_stripe_status_before_refund';
		}
		if ( empty( $meta_key ) ) {
			$log_message = sprintf( 'Error: Unable to set the order status for order %d when transitioning from %s to %s.', $order->get_id(), $current_status, $target_status );
			WC_Stripe_Logger::log( $log_message );
			return;
		}
		$order->update_meta_data( $meta_key, $current_status );
	}

	/**
	 * Helper method to retrieve the status of the order before it was put on hold.
	 *
	 * @since 8.3.0
	 *
	 * @param WC_Order $order The order.
	 * @return string The status of the order before it was put on hold.
	 */
	protected function get_stripe_order_status_before_hold( $order ) {
		return $this->get_stripe_order_status_before_event( $order, OrderStatus::ON_HOLD );
	}

	/**
	 * Stores the status of the order before being put on hold in metadata.
	 *
	 * @since 8.3.0
	 *
	 * @param WC_Order  $order  The order.
	 * @param string    $status The order status to store.
	 * @return void
	 */
	protected function set_stripe_order_status_before_hold( $order, $status ) {
		$this->set_stripe_order_status_before_event( $order, OrderStatus::ON_HOLD, $status );
	}

	/**
	 * Helper method to retrieve the status of the order before it was refunded.
	 *
	 * @since 9.6.0
	 *
	 * @param WC_Order $order The order.
	 * @return string The status of the order before it was refunded.
	 */
	protected function get_stripe_order_status_before_refund( $order ) {
		return $this->get_stripe_order_status_before_event( $order, OrderStatus::REFUNDED );
	}

	/**
	 * Stores the status of the order before being refunded.
	 *
	 * @since 9.6.0
	 *
	 * @param WC_Order  $order  The order.
	 * @param string    $status The order status to store.
	 * @return void
	 */
	protected function set_stripe_order_status_before_refund( $order, $status ) {
		$this->set_stripe_order_status_before_event( $order, OrderStatus::REFUNDED, $status );
	}

	/**
	 * Retrieves the risk/fraud outcome from the webhook payload.
	 *
	 * @param object $event_data The event data from the webhook.
	 *
	 * @return string The fraud type.
	 */
	protected function get_risk_outcome( $event_data ) {
		$fraud_type = '';

		if ( isset( $event_data->data->object->outcome->type ) ) { // Gets thCharge.succeeded event.
			$fraud_type = $event_data->data->object->outcome->type;
		} elseif ( isset( $event_data->outcome->type ) ) { // Payment_intent.succeeded event.
			$fraud_type = $event_data->outcome->type;
		}

		return $fraud_type;
	}

	/**
	 * Update the saved payment method information with updated billing values.
	 *
	 * @param string       $payment_method_id The payment method to update.
	 * @param WC_Order|int $order             Order object or id.
	 */
	public function update_saved_payment_method( $payment_method_id, $order ) {
		$order = ! is_a( $order, 'WC_Order' ) ? wc_get_order( $order ) : $order;

		if ( ! $order || ! $this->is_type_payment_method( $payment_method_id ) ) {
			return;
		}

		try {
			// Get the billing details from the order.
			$billing_details = [
				'address' => [
					'city'        => $order->get_billing_city(),
					'country'     => $order->get_billing_country(),
					'line1'       => $order->get_billing_address_1(),
					'line2'       => $order->get_billing_address_2(),
					'postal_code' => $order->get_billing_postcode(),
					'state'       => $order->get_billing_state(),
				],
				'email'   => $order->get_billing_email(),
				'name'    => trim( $order->get_formatted_billing_full_name() ),
				'phone'   => $order->get_billing_phone(),
			];

			$billing_details = array_filter( $billing_details );
			if ( empty( $billing_details ) ) {
				return;
			}

			// Update the billing details of the selected payment method in Stripe.
			WC_Stripe_API::update_payment_method(
				$payment_method_id,
				[
					'billing_details' => $billing_details,
				]
			);

		} catch ( WC_Stripe_Exception $e ) {
			// If updating the payment method fails, log the error message.
			WC_Stripe_Logger::log( 'Error when updating saved payment method: ' . $e->getMessage() );
		}
	}

	/**
	 * Attaches a source to the Stripe Customer object if the source type needs manual attachment.
	 *
	 * SEPA sources need to be manually attached to the customer object as they use legacy source objects.
	 * Other reusable payment methods (eg cards), are attached to the customer object via the setup/payment intent.
	 *
	 * @param stdClass           $source   The source object to attach.
	 * @param WC_Stripe_Customer $customer The customer object to attach the source to. Optional.
	 *
	 * @throws WC_Stripe_Exception If the source could not be attached to the customer.
	 * @return bool True if the source was successfully attached to the customer.
	 */
	private function maybe_attach_source_to_customer( $source, $customer = null ) {
		if ( ! isset( $source->type ) || WC_Stripe_Payment_Methods::SEPA_DEBIT !== $source->type ) {
			return false;
		}

		if ( ! $customer ) {
			$user_id  = get_current_user_id();
			$customer = new WC_Stripe_Customer( $user_id );
		}

		$response = $customer->attach_source( $source->id );

		if ( ! empty( $response->error ) ) {
			throw new WC_Stripe_Exception( print_r( $response, true ), $this->get_localized_error_message_from_response( $response ) );
		}

		if ( is_wp_error( $response ) ) {
			throw new WC_Stripe_Exception( $response->get_error_message(), $response->get_error_message() );
		}

		return true;
	}

	/**
	 * Retrieves the balance transaction ID from the Stripe charge.
	 *
	 * @param stdClass $charge The charge object.
	 *
	 * @return string|null The balance transaction ID.
	 */
	public function get_balance_transaction_id_from_charge( $charge ) {
		$balance_transaction_id = null;

		if ( ! empty( $charge->balance_transaction->id ) ) {
			$balance_transaction_id = $charge->balance_transaction->id;
		} elseif ( ! empty( $charge->balance_transaction ) && is_string( $charge->balance_transaction ) ) {
			$balance_transaction_id = $charge->balance_transaction;
		}

		return $balance_transaction_id;
	}

	/**
	 * Generates a unique signature for an order.
	 *
	 * This signature is included as metadata in Stripe requests and used to identify the order when webhooks are received.
	 *
	 * @param WC_Order|OrderRefund $order The Order object.
	 * @return string The order's unique signature. Format: order_id:md5(order_id-order_key-customer_id-order_total).
	 */
	protected function get_order_signature( $order ) {
		if ( is_a( $order, 'WC_Order_Refund' ) ) {
			$order = wc_get_order( $order->get_parent_id() );
		} elseif ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order );
		}

		$signature = [
			absint( $order->get_id() ),
			$order->get_order_key(),
			$order->get_customer_id() ?? '',
			WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $order->get_currency() ),
		];

		return sprintf( '%d:%s', $order->get_id(), md5( implode( '-', $signature ) ) );
	}
}
