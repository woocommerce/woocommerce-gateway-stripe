<?php
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
	/**
	 * Displays the admin settings webhook description.
	 *
	 * @since 4.1.0
	 * @return mixed
	 */
	public function display_admin_settings_webhook_description() {
		/* translators: 1) webhook url */
		return sprintf( __( 'You must add the following webhook endpoint <strong style="background-color:#ddd;">&nbsp;%s&nbsp;</strong> to your <a href="https://dashboard.stripe.com/account/webhooks" target="_blank">Stripe account settings</a>. This will enable you to receive notifications on the charge statuses.', 'woocommerce-gateway-stripe' ), WC_Stripe_Helper::get_webhook_url() );
	}

	/**
	 * Displays the save to account checkbox.
	 *
	 * @since 4.1.0
	 */
	public function save_payment_method_checkbox() {
		printf(
			'<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
			esc_attr( $this->id ),
			esc_html( apply_filters( 'wc_stripe_save_to_account_text', __( 'Save payment information to my account for future purchases.', 'woocommerce-gateway-stripe' ) ) )
		);
	}

	/**
	 * Checks to see if request is invalid and that
	 * they are worth retrying.
	 *
	 * @since 4.0.5
	 * @param array $error
	 */
	public function is_retryable_error( $error ) {
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
	 * @param array $error
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
	 * error and source is already consumed.
	 *
	 * @since 4.1.0
	 * @param array $error
	 */
	public function is_source_already_consumed_error( $error ) {
		return (
			$error &&
			'invalid_request_error' === $error->type &&
			preg_match( '/The reusable source you provided is consumed because it was previously charged without being attached to a customer or was detached from a customer. To charge a reusable source multiple time you must attach it to a customer first./i', $error->message )
		);
	}

	/**
	 * Checks to see if error is of invalid request
	 * error and it is no such customer.
	 *
	 * @since 4.1.0
	 * @param array $error
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
	 * @param array $error
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
	 * @param array $error
	 */
	public function is_no_such_source_error( $error ) {
		return (
			$error &&
			'invalid_request_error' === $error->type &&
			preg_match( '/No such source/i', $error->message )
		);
	}

	/**
	 * Checks to see if error is of invalid request
	 * error and it is no such source linked to customer.
	 *
	 * @since 4.1.0
	 * @param array $error
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
			! empty( $source_object ) &&
			'chargeable' === $source_object->status &&
			self::is_same_idempotency_error( $error )
		);
	}

	/**
	 * Check if we need to make gateways available.
	 *
	 * @since 4.1.3
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! $this->secret_key || ! $this->publishable_key ) {
				return false;
			}
			return true;
		}

		return parent::is_available();
	}

	/**
	 * Checks if we need to process pre orders when
	 * pre orders is in the cart.
	 *
	 * @since 4.1.0
	 * @param int $order_id
	 * @return bool
	 */
	public function maybe_process_pre_orders( $order_id ) {
		return (
			WC_Stripe_Helper::is_pre_orders_exists() &&
			$this->pre_orders->is_pre_order( $order_id ) &&
			WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) &&
			! is_wc_endpoint_url( 'order-pay' )
		);
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function add_admin_notice( $slug, $class, $message, $dismissible = false ) {
		$this->notices[ $slug ] = array(
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
		);
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
		return apply_filters(
			'wc_stripe_payment_icons',
			array(
				'visa'       => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/visa.svg" class="stripe-visa-icon stripe-icon" alt="Visa" />',
				'amex'       => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/amex.svg" class="stripe-amex-icon stripe-icon" alt="American Express" />',
				'mastercard' => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/mastercard.svg" class="stripe-mastercard-icon stripe-icon" alt="Mastercard" />',
				'discover'   => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/discover.svg" class="stripe-discover-icon stripe-icon" alt="Discover" />',
				'diners'     => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/diners.svg" class="stripe-diners-icon stripe-icon" alt="Diners" />',
				'jcb'        => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/jcb.svg" class="stripe-jcb-icon stripe-icon" alt="JCB" />',
				'alipay'     => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/alipay.svg" class="stripe-alipay-icon stripe-icon" alt="Alipay" />',
				'wechat'     => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/wechat.svg" class="stripe-wechat-icon stripe-icon" alt="Wechat Pay" />',
				'bancontact' => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/bancontact.svg" class="stripe-bancontact-icon stripe-icon" alt="Bancontact" />',
				'ideal'      => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/ideal.svg" class="stripe-ideal-icon stripe-icon" alt="iDeal" />',
				'p24'        => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/p24.svg" class="stripe-p24-icon stripe-icon" alt="P24" />',
				'giropay'    => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/giropay.svg" class="stripe-giropay-icon stripe-icon" alt="Giropay" />',
				'eps'        => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/eps.svg" class="stripe-eps-icon stripe-icon" alt="EPS" />',
				'multibanco' => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/multibanco.svg" class="stripe-multibanco-icon stripe-icon" alt="Multibanco" />',
				'sofort'     => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/sofort.svg" class="stripe-sofort-icon stripe-icon" alt="SOFORT" />',
				'sepa'       => '<img src="' . WC_STRIPE_PLUGIN_URL . '/assets/images/sepa.svg" class="stripe-sepa-icon stripe-icon" alt="SEPA" />',
			)
		);
	}

	/**
	 * Validates that the order meets the minimum order amount
	 * set by Stripe.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order
	 */
	public function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
			/* translators: 1) dollar amount */
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
		if ( $this->testmode ) {
			$this->view_transaction_url = 'https://dashboard.stripe.com/test/payments/%s';
		} else {
			$this->view_transaction_url = 'https://dashboard.stripe.com/payments/%s';
		}

		return parent::get_transaction_url( $order );
	}

	/**
	 * Gets the saved customer id if exists.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_stripe_customer_id( $order ) {
		$customer = get_user_meta( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->customer_user : $order->get_customer_id(), '_stripe_customer_id', true );

		if ( empty( $customer ) ) {
			// Try to get it via the order.
			if ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ) {
				return get_post_meta( $order->id, '_stripe_customer_id', true );
			} else {
				return $order->get_meta( '_stripe_customer_id', true );
			}
		} else {
			return $customer;
		}

		return false;
	}

	/**
	 * Builds the return URL from redirects.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order
	 * @param int $id Stripe session id.
	 */
	public function get_stripe_return_url( $order = null, $id = null ) {
		if ( is_object( $order ) ) {
			if ( empty( $id ) ) {
				$id = uniqid();
			}

			$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

			$args = array(
				'utm_nooverride' => '1',
				'order_id'       => $order_id,
			);

			return esc_url_raw( add_query_arg( $args, $this->get_return_url( $order ) ) );
		}

		return esc_url_raw( add_query_arg( array( 'utm_nooverride' => '1' ), $this->get_return_url() ) );
	}

	/**
	 * Is $order_id a subscription?
	 * @param  int  $order_id
	 * @return boolean
	 */
	public function has_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Generate the request for the payment.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param  WC_Order $order
	 * @param  object $prepared_source
	 * @return array()
	 */
	public function generate_payment_request( $order, $prepared_source ) {
		$settings              = get_option( 'woocommerce_stripe_settings', array() );
		$statement_descriptor  = ! empty( $settings['statement_descriptor'] ) ? str_replace( "'", '', $settings['statement_descriptor'] ) : '';
		$capture               = ! empty( $settings['capture'] ) && 'yes' === $settings['capture'] ? true : false;
		$post_data             = array();
		$post_data['currency'] = strtolower( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->get_order_currency() : $order->get_currency() );
		$post_data['amount']   = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $post_data['currency'] );
		/* translators: 1) blog name 2) order number */
		$post_data['description'] = sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
		$billing_email            = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_email : $order->get_billing_email();
		$billing_first_name       = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name        = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_last_name : $order->get_billing_last_name();

		if ( ! empty( $billing_email ) && apply_filters( 'wc_stripe_send_stripe_receipt', false ) ) {
			$post_data['receipt_email'] = $billing_email;
		}

		switch ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->payment_method : $order->get_payment_method() ) {
			case 'stripe':
				if ( ! empty( $statement_descriptor ) ) {
					$post_data['statement_descriptor'] = WC_Stripe_Helper::clean_statement_descriptor( $statement_descriptor );
				}

				$post_data['capture'] = $capture ? 'true' : 'false';
				break;
			case 'stripe_sepa':
				if ( ! empty( $statement_descriptor ) ) {
					$post_data['statement_descriptor'] = WC_Stripe_Helper::clean_statement_descriptor( $statement_descriptor );
				}
				break;
		}

		$post_data['expand[]'] = 'balance_transaction';

		$metadata = array(
			__( 'customer_name', 'woocommerce-gateway-stripe' ) => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
			__( 'customer_email', 'woocommerce-gateway-stripe' ) => sanitize_email( $billing_email ),
			'order_id' => $order->get_order_number(),
		);

		if ( $this->has_subscription( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id() ) ) {
			$metadata += array(
				'payment_type' => 'recurring',
				'site_url'     => esc_url( get_site_url() ),
			);
		}

		$post_data['metadata'] = apply_filters( 'wc_stripe_payment_metadata', $metadata, $order, $prepared_source );

		if ( $prepared_source->customer ) {
			$post_data['customer'] = $prepared_source->customer;
		}

		if ( $prepared_source->source ) {
			$post_data['source'] = $prepared_source->source;
		}

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
		 *
		 * @since 3.1.0
		 * @param array $post_data
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_stripe_generate_payment_request', $post_data, $order, $prepared_source );
	}

	/**
	 * Store extra meta data for an order from a Stripe Response.
	 */
	public function process_response( $response, $order ) {
		WC_Stripe_Logger::log( 'Processing response: ' . print_r( $response, true ) );

		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();
		$captured = ( isset( $response->captured ) && $response->captured ) ? 'yes' : 'no';

		// Store charge data.
		WC_Stripe_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_stripe_charge_captured', $captured ) : $order->update_meta_data( '_stripe_charge_captured', $captured );

		// Store other data such as fees.
		if ( isset( $response->balance_transaction ) && isset( $response->balance_transaction->fee ) ) {
			// Fees and Net needs to both come from Stripe to be accurate as the returned
			// values are in the local currency of the Stripe account, not from WC.
			$fee = ! empty( $response->balance_transaction->fee ) ? WC_Stripe_Helper::format_balance_fee( $response->balance_transaction, 'fee' ) : 0;
			$net = ! empty( $response->balance_transaction->net ) ? WC_Stripe_Helper::format_balance_fee( $response->balance_transaction, 'net' ) : 0;
			WC_Stripe_Helper::update_stripe_fee( $order, $fee );
			WC_Stripe_Helper::update_stripe_net( $order, $net );

			// Store currency stripe.
			$currency = ! empty( $response->balance_transaction->currency ) ? strtoupper( $response->balance_transaction->currency ) : null;
			WC_Stripe_Helper::update_stripe_currency( $order, $currency );
		}

		if ( 'yes' === $captured ) {
			/**
			 * Charge can be captured but in a pending state. Payment methods
			 * that are asynchronous may take couple days to clear. Webhook will
			 * take care of the status changes.
			 */
			if ( 'pending' === $response->status ) {
				$order_stock_reduced = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? get_post_meta( $order_id, '_order_stock_reduced', true ) : $order->get_meta( '_order_stock_reduced', true );

				if ( ! $order_stock_reduced ) {
					WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order_id );
				}

				WC_Stripe_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_transaction_id', $response->id ) : $order->set_transaction_id( $response->id );
				/* translators: transaction id */
				$order->update_status( 'on-hold', sprintf( __( 'Stripe charge awaiting payment: %s.', 'woocommerce-gateway-stripe' ), $response->id ) );
			}

			if ( 'succeeded' === $response->status ) {
				$order->payment_complete( $response->id );

				/* translators: transaction id */
				$message = sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $response->id );
				$order->add_order_note( $message );
			}

			if ( 'failed' === $response->status ) {
				$localized_message = __( 'Payment processing failed. Please retry.', 'woocommerce-gateway-stripe' );
				$order->add_order_note( $localized_message );
				throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
			}
		} else {
			WC_Stripe_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_transaction_id', $response->id ) : $order->set_transaction_id( $response->id );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order_id );
			}

			/* translators: transaction id */
			$order->update_status( 'on-hold', sprintf( __( 'Stripe charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-stripe' ), $response->id ) );
		}

		if ( is_callable( array( $order, 'save' ) ) ) {
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
	 * @return null
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}

	/**
	 * Get owner details.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order
	 * @return object $details
	 */
	public function get_owner_details( $order ) {
		$billing_first_name = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name  = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_last_name : $order->get_billing_last_name();

		$details = array();

		$name  = $billing_first_name . ' ' . $billing_last_name;
		$email = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_email : $order->get_billing_email();
		$phone = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_phone : $order->get_billing_phone();

		if ( ! empty( $phone ) ) {
			$details['phone'] = $phone;
		}

		if ( ! empty( $name ) ) {
			$details['name'] = $name;
		}

		if ( ! empty( $email ) ) {
			$details['email'] = $email;
		}

		$details['address']['line1']       = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_address_1 : $order->get_billing_address_1();
		$details['address']['line2']       = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_address_2 : $order->get_billing_address_2();
		$details['address']['state']       = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_state : $order->get_billing_state();
		$details['address']['city']        = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_city : $order->get_billing_city();
		$details['address']['postal_code'] = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_postcode : $order->get_billing_postcode();
		$details['address']['country']     = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_country : $order->get_billing_country();

		return (object) apply_filters( 'wc_stripe_owner_details', $details, $order );
	}

	/**
	 * Get source object by source id.
	 *
	 * @since 4.0.3
	 * @param string $source_id The source ID to get source object for.
	 */
	public function get_source_object( $source_id = '' ) {
		if ( empty( $source_id ) ) {
			return '';
		}

		$source_object = WC_Stripe_API::retrieve( 'sources/' . $source_id );

		if ( ! empty( $source_object->error ) ) {
			throw new WC_Stripe_Exception( print_r( $source_object, true ), $source_object->error->message );
		}

		return $source_object;
	}

	/**
	 * Checks if card is a prepaid card.
	 *
	 * @since 4.0.6
	 * @param object $source_object
	 * @return bool
	 */
	public function is_prepaid_card( $source_object ) {
		return ( $source_object && 'token' === $source_object->object && 'prepaid' === $source_object->card->funding );
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
	 * Checks if payment is via saved payment source.
	 *
	 * @since 4.1.0
	 * @return bool
	 */
	public function is_using_saved_payment_method() {
		$payment_method = isset( $_POST['payment_method'] ) ? wc_clean( $_POST['payment_method'] ) : 'stripe';

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
	 * @param bool $force_save_source Should we force save payment source.
	 *
	 * @throws Exception When card was not added or for and invalid card.
	 * @return object
	 */
	public function prepare_source( $user_id, $force_save_source = false ) {
		$customer          = new WC_Stripe_Customer( $user_id );
		$set_customer      = true;
		$force_save_source = apply_filters( 'wc_stripe_force_save_source', $force_save_source, $customer );
		$source_object     = '';
		$source_id         = '';
		$wc_token_id       = false;
		$payment_method    = isset( $_POST['payment_method'] ) ? wc_clean( $_POST['payment_method'] ) : 'stripe';
		$is_token          = false;

		// New CC info was entered and we have a new source to process.
		if ( ! empty( $_POST['stripe_source'] ) ) {
			$source_object = self::get_source_object( wc_clean( $_POST['stripe_source'] ) );
			$source_id     = $source_object->id;

			// This checks to see if customer opted to save the payment method to file.
			$maybe_saved_card = isset( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] ) && ! empty( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] );

			/**
			 * This is true if the user wants to store the card to their account.
			 * Criteria to save to file is they are logged in, they opted to save or product requirements and the source is
			 * actually reusable. Either that or force_save_source is true.
			 */
			if ( ( $user_id && $this->saved_cards && $maybe_saved_card && 'reusable' === $source_object->usage ) || $force_save_source ) {
				$response = $customer->add_source( $source_object->id );

				if ( ! empty( $response->error ) ) {
					throw new WC_Stripe_Exception( print_r( $response, true ), $response->error->message );
				}
			}
		} elseif ( $this->is_using_saved_payment_method() ) {
			// Use an existing token, and then process the payment.
			$wc_token_id = wc_clean( $_POST[ 'wc-' . $payment_method . '-payment-token' ] );
			$wc_token    = WC_Payment_Tokens::get( $wc_token_id );

			if ( ! $wc_token || $wc_token->get_user_id() !== get_current_user_id() ) {
				WC()->session->set( 'refresh_totals', true );
				throw new WC_Stripe_Exception( 'Invalid payment method', __( 'Invalid payment method. Please input a new card number.', 'woocommerce-gateway-stripe' ) );
			}

			$source_id = $wc_token->get_token();

			if ( $this->is_type_legacy_card( $source_id ) ) {
				$is_token = true;
			}
		} elseif ( isset( $_POST['stripe_token'] ) && 'new' !== $_POST['stripe_token'] ) {
			$stripe_token     = wc_clean( $_POST['stripe_token'] );
			$maybe_saved_card = isset( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] ) && ! empty( $_POST[ 'wc-' . $payment_method . '-new-payment-method' ] );

			// This is true if the user wants to store the card to their account.
			if ( ( $user_id && $this->saved_cards && $maybe_saved_card ) || $force_save_source ) {
				$response = $customer->add_source( $stripe_token );

				if ( ! empty( $response->error ) ) {
					throw new WC_Stripe_Exception( print_r( $response, true ), $response->error->message );
				}
			} else {
				$set_customer = false;
				$source_id    = $stripe_token;
				$is_token     = true;
			}
		}

		if ( ! $set_customer ) {
			$customer_id = false;
		} else {
			$customer_id = $customer->get_id() ? $customer->get_id() : false;
		}

		if ( empty( $source_object ) && ! $is_token ) {
			$source_object = self::get_source_object( $source_id );
		}

		return (object) array(
			'token_id'      => $wc_token_id,
			'customer'      => $customer_id,
			'source'        => $source_id,
			'source_object' => $source_object,
		);
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
			$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

			$stripe_customer_id = get_post_meta( $order_id, '_stripe_customer_id', true );

			if ( $stripe_customer_id ) {
				$stripe_customer->set_id( $stripe_customer_id );
			}

			$source_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? get_post_meta( $order_id, '_stripe_source_id', true ) : $order->get_meta( '_stripe_source_id', true );

			// Since 4.0.0, we changed card to source so we need to account for that.
			if ( empty( $source_id ) ) {
				$source_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? get_post_meta( $order_id, '_stripe_card_id', true ) : $order->get_meta( '_stripe_card_id', true );

				// Take this opportunity to update the key name.
				WC_Stripe_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_stripe_source_id', $source_id ) : $order->update_meta_data( '_stripe_source_id', $source_id );

				if ( is_callable( array( $order, 'save' ) ) ) {
					$order->save();
				}
			}

			if ( $source_id ) {
				$stripe_source = $source_id;
				$source_object = WC_Stripe_API::retrieve( 'sources/' . $source_id );
			} elseif ( apply_filters( 'wc_stripe_use_default_customer_source', true ) ) {
				/*
				 * We can attempt to charge the customer's default source
				 * by sending empty source id.
				 */
				$stripe_source = '';
			}
		}

		return (object) array(
			'token_id'      => $token_id,
			'customer'      => $stripe_customer ? $stripe_customer->get_id() : false,
			'source'        => $stripe_source,
			'source_object' => $source_object,
		);
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
		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

		// Store source in the order.
		if ( $source->customer ) {
			if ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ) {
				update_post_meta( $order_id, '_stripe_customer_id', $source->customer );
			} else {
				$order->update_meta_data( '_stripe_customer_id', $source->customer );
			}
		}

		if ( $source->source ) {
			if ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ) {
				update_post_meta( $order_id, '_stripe_source_id', $source->source );
			} else {
				$order->update_meta_data( '_stripe_source_id', $source->source );
			}
		}

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}
	}

	/**
	 * Updates Stripe fees/net.
	 * e.g usage would be after a refund.
	 *
	 * @since 4.0.0
	 * @version 4.0.6
	 * @param object $order The order object
	 * @param int $balance_transaction_id
	 */
	public function update_fees( $order, $balance_transaction_id ) {
		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

		$balance_transaction = WC_Stripe_API::retrieve( 'balance/history/' . $balance_transaction_id );

		if ( empty( $balance_transaction->error ) ) {
			if ( isset( $balance_transaction ) && isset( $balance_transaction->fee ) ) {
				// Fees and Net needs to both come from Stripe to be accurate as the returned
				// values are in the local currency of the Stripe account, not from WC.
				$fee_refund = ! empty( $balance_transaction->fee ) ? WC_Stripe_Helper::format_balance_fee( $balance_transaction, 'fee' ) : 0;
				$net_refund = ! empty( $balance_transaction->net ) ? WC_Stripe_Helper::format_balance_fee( $balance_transaction, 'net' ) : 0;

				// Current data fee & net.
				$fee_current = WC_Stripe_Helper::get_stripe_fee( $order );
				$net_current = WC_Stripe_Helper::get_stripe_net( $order );

				// Calculation.
				$fee = (float) $fee_current + (float) $fee_refund;
				$net = (float) $net_current + (float) $net_refund;

				WC_Stripe_Helper::update_stripe_fee( $order, $fee );
				WC_Stripe_Helper::update_stripe_net( $order, $net );

				$currency = ! empty( $balance_transaction->currency ) ? strtoupper( $balance_transaction->currency ) : null;
				WC_Stripe_Helper::update_stripe_currency( $order, $currency );

				if ( is_callable( array( $order, 'save' ) ) ) {
					$order->save();
				}
			}
		} else {
			WC_Stripe_Logger::log( "Unable to update fees/net meta for order: {$order_id}" );
		}
	}

	/**
	 * Refund a charge.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param  int $order_id
	 * @param  float $amount
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_transaction_id() ) {
			return false;
		}

		$request = array();

		if ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ) {
			$order_currency = get_post_meta( $order_id, '_order_currency', true );
			$captured       = get_post_meta( $order_id, '_stripe_charge_captured', true );
		} else {
			$order_currency = $order->get_currency();
			$captured       = $order->get_meta( '_stripe_charge_captured', true );
		}

		if ( ! is_null( $amount ) ) {
			$request['amount'] = WC_Stripe_Helper::get_stripe_amount( $amount, $order_currency );
		}

		// If order is only authorized, don't pass amount.
		if ( 'yes' !== $captured ) {
			unset( $request['amount'] );
		}

		if ( $reason ) {
			$request['metadata'] = array(
				'reason' => $reason,
			);
		}

		$request['charge'] = $order->get_transaction_id();

		WC_Stripe_Logger::log( "Info: Beginning refund for order {$order->get_transaction_id()} for the amount of {$amount}" );

		$request = apply_filters( 'wc_stripe_refund_request', $request, $order );

		$response = WC_Stripe_API::request( $request, 'refunds' );

		if ( ! empty( $response->error ) ) {
			WC_Stripe_Logger::log( 'Error: ' . $response->error->message );

			return $response;

		} elseif ( ! empty( $response->id ) ) {
			WC_Stripe_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order_id, '_stripe_refund_id', $response->id ) : $order->update_meta_data( '_stripe_refund_id', $response->id );

			$amount = wc_price( $response->amount / 100 );

			if ( in_array( strtolower( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->get_order_currency() : $order->get_currency() ), WC_Stripe_Helper::no_decimal_currencies() ) ) {
				$amount = wc_price( $response->amount );
			}

			if ( isset( $response->balance_transaction ) ) {
				$this->update_fees( $order, $response->balance_transaction );
			}

			/* translators: 1) dollar amount 2) transaction id 3) refund message */
			$refund_message = ( isset( $captured ) && 'yes' === $captured ) ? sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-stripe' ), $amount, $response->id, $reason ) : __( 'Pre-Authorization Released', 'woocommerce-gateway-stripe' );

			$order->add_order_note( $refund_message );
			WC_Stripe_Logger::log( 'Success: ' . html_entity_decode( wp_strip_all_tags( $refund_message ) ) );

			return true;
		}
	}

	/**
	 * Add payment method via account screen.
	 * We don't store the token locally, but to the Stripe API.
	 *
	 * @since 3.0.0
	 * @version 4.0.0
	 */
	public function add_payment_method() {
		$error     = false;
		$error_msg = __( 'There was a problem adding the payment method.', 'woocommerce-gateway-stripe' );
		$source_id = '';

		if ( empty( $_POST['stripe_source'] ) && empty( $_POST['stripe_token'] ) || ! is_user_logged_in() ) {
			$error = true;
		}

		$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );

		$source = ! empty( $_POST['stripe_source'] ) ? wc_clean( $_POST['stripe_source'] ) : '';

		$source_object = WC_Stripe_API::retrieve( 'sources/' . $source );

		if ( isset( $source_object ) ) {
			if ( ! empty( $source_object->error ) ) {
				$error = true;
			}

			$source_id = $source_object->id;
		} elseif ( isset( $_POST['stripe_token'] ) ) {
			$source_id = wc_clean( $_POST['stripe_token'] );
		}

		$response = $stripe_customer->add_source( $source_id );

		if ( ! $response || is_wp_error( $response ) || ! empty( $response->error ) ) {
			$error = true;
		}

		if ( $error ) {
			wc_add_notice( $error_msg, 'error' );
			WC_Stripe_Logger::log( 'Add payment method Error: ' . $error_msg );
			return;
		}

		do_action( 'wc_stripe_add_payment_method_' . $_POST['payment_method'] . '_success', $source_id, $source_object );

		return array(
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		);
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
	 * @param array $request
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
}
