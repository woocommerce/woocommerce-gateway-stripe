<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class that will be inherited by all payment methods.
 *
 * @extends WC_Payment_Gateway_CC
 *
 * @since 4.0.0
 */
abstract class WC_Stripe_Payment_Gateway extends WC_Payment_Gateway_CC {
	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! $this->testmode && is_checkout() && ! is_ssl() ) {
				return false;
			}
			if ( ! $this->secret_key || ! $this->publishable_key ) {
				return false;
			}
			return true;
		}

		return parent::is_available();
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function add_admin_notice( $slug, $class, $message ) {
		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message,
		);
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
		$customer = get_user_meta( WC_Stripe_Helper::is_pre_30() ? $order->customer_user : $order->get_customer_id(), '_stripe_customer_id', true );

		if ( empty( $customer ) ) {
			// Try to get it via the order.
			if ( WC_Stripe_Helper::is_pre_30() ) {
				return get_post_meta( $order->id, '_stripe_customer_id', true );
			} else {
				return $order->get_meta( '_stripe_customer_id', true );
			}
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

			$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

			return esc_url_raw( add_query_arg( array( 'utm_nooverride' => '1', 'order_id' => $order_id ), $this->get_return_url( $order ) ) );
		}

		return esc_url_raw( add_query_arg( array( 'utm_nooverride' => '1' ), $this->get_return_url() ) );
	}

	/**
	 * Generate the request for the payment.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param  WC_Order $order
	 * @param  object $source
	 * @return array()
	 */
	public function generate_payment_request( $order, $source ) {
		$settings                          = get_option( 'woocommerce_stripe_settings', array() );
		$statement_descriptor              = ! empty( $settings['statement_descriptor'] ) ? str_replace( "'", '', $settings['statement_descriptor'] ) : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$capture                           = ! empty( $settings['capture'] ) && 'yes' === $settings['capture'] ? true : false; 
		$post_data                         = array();
		$post_data['currency']             = strtolower( WC_Stripe_Helper::is_pre_30() ? $order->get_order_currency() : $order->get_currency() );
		$post_data['amount']               = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $post_data['currency'] );
		$post_data['description']          = sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
		$billing_email      = WC_Stripe_Helper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
		$billing_first_name = WC_Stripe_Helper::is_pre_30() ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name  = WC_Stripe_Helper::is_pre_30() ? $order->billing_last_name : $order->get_billing_last_name();

		if ( ! empty( $billing_email ) && apply_filters( 'wc_stripe_send_stripe_receipt', false ) ) {
			$post_data['receipt_email'] = $billing_email;
		}

		switch ( WC_Stripe_Helper::is_pre_30() ? $order->payment_method : $order->get_payment_method() ) {
			case 'stripe':
				$post_data['statement_descriptor'] = substr( $statement_descriptor, 0, 22 );
				$post_data['capture']              = $capture ? 'true' : 'false';
				break;
		}

		$post_data['expand[]'] = 'balance_transaction';

		$metadata = array(
			__( 'customer_name', 'woocommerce-gateway-stripe' ) => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
			__( 'customer_email', 'woocommerce-gateway-stripe' ) => sanitize_email( $billing_email ),
			'order_id' => WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id(),
		);

		$post_data['metadata'] = apply_filters( 'wc_stripe_payment_metadata', $metadata, $order, $source );

		if ( $source->customer ) {
			$post_data['customer'] = $source->customer;
		}

		if ( $source->source ) {
			$post_data['source'] = $source->source;
		}

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
		 *
		 * @since 3.1.0
		 * @param array $post_data
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_stripe_generate_payment_request', $post_data, $order, $source );
	}

	/**
	 * Store extra meta data for an order from a Stripe Response.
	 */
	public function process_response( $response, $order ) {
		WC_Stripe_Logger::log( 'Processing response: ' . print_r( $response, true ) );

		$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

		// Store charge data
		WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, '_stripe_charge_captured', $response->captured ? 'yes' : 'no' ) : $order->update_meta_data( '_stripe_charge_captured', $response->captured ? 'yes' : 'no' );

		// Store other data such as fees
		if ( isset( $response->balance_transaction ) && isset( $response->balance_transaction->fee ) ) {
			// Fees and Net needs to both come from Stripe to be accurate as the returned
			// values are in the local currency of the Stripe account, not from WC.
			$fee = ! empty( $response->balance_transaction->fee ) ? WC_Stripe_Helper::format_balance_fee( $response->balance_transaction, 'fee' ) : 0;
			$net = ! empty( $response->balance_transaction->net ) ? WC_Stripe_Helper::format_balance_fee( $response->balance_transaction, 'net' ) : 0;
			WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, 'Stripe Fee', $fee ) : $order->update_meta_data( 'Stripe Fee', $fee );
			WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, 'Net Revenue From Stripe', $net ) : $order->update_meta_data( 'Net Revenue From Stripe', $net );
		}

		if ( $response->captured ) {
			$order->payment_complete( $response->id );

			$message = sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $response->id );
			$order->add_order_note( $message );
			WC_Stripe_Logger::log( 'Success: ' . $message );

		} else {
			WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, '_transaction_id', $response->id, true ) : $order->set_transaction_id( $response->id );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				WC_Stripe_Helper::is_pre_30() ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order_id );
			}

			$order->update_status( 'on-hold', sprintf( __( 'Stripe charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-stripe' ), $response->id ) );
			WC_Stripe_Logger::log( "Successful auth: $response->id" );
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
		$billing_first_name = WC_Stripe_Helper::is_pre_30() ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name  = WC_Stripe_Helper::is_pre_30() ? $order->billing_last_name : $order->get_billing_last_name();

		$details = array();

		$details['name']                   = $billing_first_name . ' ' . $billing_last_name;
		$details['email']                  = WC_Stripe_Helper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
		$details['phone']                  = WC_Stripe_Helper::is_pre_30() ? $order->billing_phone : $order->get_billing_phone();
		$details['address']['line1']       = WC_Stripe_Helper::is_pre_30() ? $order->billing_address_1 : $order->get_billing_address_1();
		$details['address']['line2']       = WC_Stripe_Helper::is_pre_30() ? $order->billing_address_2 : $order->get_billing_address_2();
		$details['address']['state']       = WC_Stripe_Helper::is_pre_30() ? $order->billing_state : $order->get_billing_state();
		$details['address']['city']        = WC_Stripe_Helper::is_pre_30() ? $order->billing_city : $order->get_billing_city();
		$details['address']['postal_code'] = WC_Stripe_Helper::is_pre_30() ? $order->billing_postcode : $order->get_billing_postcode();
		$details['address']['country']     = WC_Stripe_Helper::is_pre_30() ? $order->billing_country : $order->get_billing_country();

		return (object) $details;
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
		$customer           = new WC_Stripe_Customer( $user_id );
		$force_save_source  = apply_filters( 'wc_stripe_force_save_source', $force_save_source, $customer );
		$source             = '';
		$wc_token_id        = false;

		// New CC info was entered and we have a new source to process.
		if ( ! empty( $_POST['stripe_source'] ) ) {
			// This gets the source object from Stripe.
			$source = json_decode( wc_clean( stripslashes( $_POST['stripe_source'] ) ) );

			// This checks to see if customer opted to save the payment method to file.
			$maybe_saved_card = isset( $_POST['wc-stripe-new-payment-method'] ) && ! empty( $_POST['wc-stripe-new-payment-method'] );

			/**
			 * This is true if the user wants to store the card to their account.
			 * Criteria to save to file is they are logged in, they opted to save or product requirements and the source is
			 * actually reusable. Either that or force_save_source is true.
			 */
			if ( ( $user_id && $this->saved_cards && $maybe_saved_card && 'reusable' === $source->usage ) || $force_save_source ) {
				$source = $customer->add_source( $source->id );

				if ( ! empty( $source->error ) ) {
					throw new Exception( $source->error->message );
				}
			} else {
				$source   = $source->id;
			}
		} elseif ( isset( $_POST['wc-stripe-payment-token'] ) && 'new' !== $_POST['wc-stripe-payment-token'] ) {
			// Use an existing token, and then process the payment

			$wc_token_id = wc_clean( $_POST['wc-stripe-payment-token'] );
			$wc_token    = WC_Payment_Tokens::get( $wc_token_id );

			if ( ! $wc_token || $wc_token->get_user_id() !== get_current_user_id() ) {
				WC()->session->set( 'refresh_totals', true );
				throw new Exception( __( 'Invalid payment method. Please input a new card number.', 'woocommerce-gateway-stripe' ) );
			}

			$source = $wc_token->get_token();
		} elseif ( isset( $_POST['stripe_token'] ) && 'new' !== $_POST['stripe_token'] ) {
			$stripe_token     = wc_clean( $_POST['stripe_token'] );
			$maybe_saved_card = isset( $_POST['wc-stripe-new-payment-method'] ) && ! empty( $_POST['wc-stripe-new-payment-method'] );

			// This is true if the user wants to store the card to their account.
			if ( ( $user_id && $this->saved_cards && $maybe_saved_card ) || $force_save_source ) {
				$stripe_source = $stripe_customer->add_source( $stripe_token );
				if ( ! empty( $stripe_source->error ) ) {
					throw new Exception( $stripe_source->error->message );
				}
			} else {
				$source = $stripe_token;
			}
		}

		return (object) array(
			'token_id' => $wc_token_id,
			'customer' => $customer->get_id() ? $customer->get_id() : false,
			'source'   => $source,
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
	public function save_source( $order, $source ) {
		$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

		// Store source in the order.
		if ( $source->customer ) {
			if ( WC_Stripe_Helper::is_pre_30() ) {
				update_post_meta( $order_id, '_stripe_customer_id', $source->customer );
			} else {
				$order->update_meta_data( '_stripe_customer_id', $source->customer );
			}
		}

		if ( $source->source ) {
			if ( WC_Stripe_Helper::is_pre_30() ) {
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

		if ( $order ) {
			$order_id = WC_Stripe_Helper::is_pre_30() ? $order->id : $order->get_id();

			if ( $stripe_customer_id = get_post_meta( $order_id, '_stripe_customer_id', true ) ) {
				$stripe_customer->set_id( $stripe_customer_id );
			}

			$source_id = WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_stripe_source_id', true ) : $order->get_meta( '_stripe_source_id', true );

			// Since 4.0.0, we changed card to source so we need to account for that.
			if ( empty( $source_id ) ) {
				$source_id = WC_Stripe_Helper::is_pre_30() ? get_post_meta( $order_id, '_stripe_card_id', true ) : $order->get_meta( '_stripe_card_id', true );

				// Take this opportunity to update the key name.
				WC_Stripe_Helper::is_pre_30() ? update_post_meta( $order_id, '_stripe_source_id', $source_id ) : $order->update_meta_data( '_stripe_source_id', $source_id );

				if ( is_callable( array( $order, 'save' ) ) ) {
					$order->save();
				}
			}

			if ( $source_id ) {
				$stripe_source = $source_id;
			}
		}

		return (object) array(
			'token_id' => $token_id,
			'customer' => $stripe_customer ? $stripe_customer->get_id() : false,
			'source'   => $stripe_source,
		);
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

		$body = array();

		if ( ! is_null( $amount ) ) {
			$body['amount']	= WC_Stripe_Helper::get_stripe_amount( $amount );
		}

		if ( $reason ) {
			$body['metadata'] = array(
				'reason'	=> $reason,
			);
		}

		WC_Stripe_Logger::log( "Info: Beginning refund for order $order_id for the amount of {$amount}" );

		$response = WC_Stripe_API::request( $body, 'charges/' . $order->get_transaction_id() . '/refunds' );

		if ( ! empty( $response->error ) ) {
			WC_Stripe_Logger::log( 'Error: ' . $response->error->message );
			return $response;
		} elseif ( ! empty( $response->id ) ) {
			$amount = wc_price( $response->amount / 100 );

			if ( in_array( strtolower( $order->get_currency() ), WC_Stripe_Helper::no_decimal_currencies() ) ) {
				$amount = wc_price( $response->amount );
			}

			$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-stripe' ), $amount, $response->id, $reason );
			$order->add_order_note( $refund_message );
			WC_Stripe_Logger::log( 'Success: ' . html_entity_decode( strip_tags( $refund_message ) ) );
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
		if ( empty( $_POST['stripe_source'] ) || ! is_user_logged_in() ) {
			wc_add_notice( __( 'There was a problem adding the card.', 'woocommerce-gateway-stripe' ), 'error' );
			return;
		}

		$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
		$source          = json_decode( wc_clean( stripslashes( $_POST['stripe_source'] ) ) );

		if ( ! empty( $source->error ) ) {
			$localized_messages = WC_Stripe_Helper::get_localized_messages();
			$error_msg = __( 'There was a problem adding the card.', 'woocommerce-gateway-stripe' );

			wc_add_notice( $error_msg, 'error' );
			return;
		}

		$stripe_customer->add_source( $source->id );

		return array(
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		);
	}
}
