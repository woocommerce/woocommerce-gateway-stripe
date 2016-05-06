<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Stripe class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Stripe extends WC_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'stripe';
		$this->method_title         = __( 'Stripe', 'woocommerce-gateway-stripe' );
		$this->method_description   = __( 'Stripe works by adding credit card fields on the checkout and then sending the details to Stripe for verification.', 'woocommerce-gateway-stripe' );
		$this->has_fields           = true;
		$this->api_endpoint         = 'https://api.stripe.com/';
		$this->view_transaction_url = 'https://dashboard.stripe.com/payments/%s';
		$this->supports             = array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change', // Subs 1.n compatibility
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'subscription_date_changes',
			'multiple_subscriptions',
			'pre-orders'
		);

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                  = $this->get_option( 'title' );
		$this->description            = $this->get_option( 'description' );
		$this->enabled                = $this->get_option( 'enabled' );
		$this->testmode               = 'yes' === $this->get_option( 'testmode' );
		$this->capture                = 'yes' === $this->get_option( 'capture', 'yes' );
		$this->stripe_checkout        = 'yes' === $this->get_option( 'stripe_checkout' );
		$this->stripe_checkout_locale = $this->get_option( 'stripe_checkout_locale' );
		$this->stripe_checkout_image  = $this->get_option( 'stripe_checkout_image', '' );
		$this->saved_cards            = 'yes' === $this->get_option( 'saved_cards' );
		$this->secret_key             = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->publishable_key        = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
		$this->bitcoin                = 'USD' === strtoupper( get_woocommerce_currency() ) && 'yes' === $this->get_option( 'stripe_bitcoin' );
		$this->logging                = 'yes' === $this->get_option( 'logging' );

		if ( $this->stripe_checkout ) {
			$this->order_button_text = __( 'Continue to payment', 'woocommerce-gateway-stripe' );
		}

		if ( $this->testmode ) {
			$this->description .= ' ' . sprintf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the documentation "<a href="%s">Testing Stripe</a>" for more card numbers.', 'woocommerce-gateway-stripe' ), 'https://stripe.com/docs/testing' );
			$this->description  = trim( $this->description );
		}

		// Hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

	}

	/**
	 * get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$ext   = version_compare( WC()->version, '2.6', '>=' ) ? '.svg' : '.png';
		$style = version_compare( WC()->version, '2.6', '>=' ) ? 'style="margin-left: 0.3em"' : '';

		$icon  = '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa' . $ext ) . '" alt="Visa" width="32" ' . $style . ' />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard' . $ext ) . '" alt="Mastercard" width="32" ' . $style . ' />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex' . $ext ) . '" alt="Amex" width="32" ' . $style . ' />';

		if ( 'USD' === get_woocommerce_currency() ) {
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover' . $ext ) . '" alt="Discover" width="32" ' . $style . ' />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb' . $ext ) . '" alt="JCB" width="32" ' . $style . ' />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/diners' . $ext ) . '" alt="Diners" width="32" ' . $style . ' />';
		}

		if ( $this->bitcoin ) {
			$icon .= '<img src="' . WC_HTTPS::force_https_url( plugins_url( '/assets/images/bitcoin' . $ext, dirname( __FILE__ ) ) ) . '" alt="Bitcoin" width="32" ' . $style . ' />';
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Get Stripe amount to pay
	 * @return float
	 */
	public function get_stripe_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}
		switch ( strtoupper( $currency ) ) {
			// Zero decimal currencies
			case 'BIF' :
			case 'CLP' :
			case 'DJF' :
			case 'GNF' :
			case 'JPY' :
			case 'KMF' :
			case 'KRW' :
			case 'MGA' :
			case 'PYG' :
			case 'RWF' :
			case 'VND' :
			case 'VUV' :
			case 'XAF' :
			case 'XOF' :
			case 'XPF' :
				$total = absint( $total );
				break;
			default :
				$total = round( $total, 2 ) * 100; // In cents
				break;
		}
		return $total;
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( $this->enabled == 'no' ) {
			return;
		}

		$addons = ( class_exists( 'WC_Subscriptions_Order' ) || class_exists( 'WC_Pre_Orders_Order' ) ) ? '_addons' : '';

		// Check required fields
		if ( ! $this->secret_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'Stripe error: Please enter your secret key <a href="%s">here</a>', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_stripe' . $addons ) ) . '</p></div>';
			return;

		} elseif ( ! $this->publishable_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'Stripe error: Please enter your publishable key <a href="%s">here</a>', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_stripe' . $addons ) ) . '</p></div>';
			return;
		}

		// Simple check for duplicate keys
		if ( $this->secret_key == $this->publishable_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'Stripe error: Your secret and publishable keys match. Please check and re-enter.', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_stripe' . $addons ) ) . '</p></div>';
			return;
		}

		// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
		if ( ( function_exists( 'wc_site_is_https' ) && ! wc_site_is_https() ) && ( 'no' === get_option( 'woocommerce_force_ssl_checkout' ) && ! class_exists( 'WordPressHTTPS' ) ) ) {
			echo '<div class="error"><p>' . sprintf( __( 'Stripe is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - Stripe will only work in test mode.', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
		}
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( $this->enabled == "yes" ) {
			if ( ! $this->testmode && is_checkout() && ! is_ssl() ) {
				return false;
			}
			// Required fields check
			if ( ! $this->secret_key || ! $this->publishable_key ) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( 'settings-stripe.php' );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		?>
		<fieldset>
			<?php
				$allowed = array(
					'a' => array(
						'href'  => array(),
						'title' => array()
					),
					'br'     => array(),
					'em'     => array(),
					'strong' => array(),
					'span'   => array(
						'class' => array(),
					),
				);
				if ( $this->description ) {
					echo apply_filters( 'wc_stripe_description', wpautop( wp_kses( $this->description, $allowed ) ) );
				}
				if ( $this->saved_cards && is_user_logged_in() && ( $customer_id = get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) ) && is_string( $customer_id ) && ( $cards = $this->get_saved_cards( $customer_id ) ) ) {

					$default_card = get_user_meta( get_current_user_id(), '_wc_stripe_default_card', true );
					?>
					<p class="form-row form-row-wide">
						<a class="<?php echo apply_filters( 'wc_stripe_manage_saved_cards_class', 'button' ); ?>" style="float:right;" href="<?php echo apply_filters( 'wc_stripe_manage_saved_cards_url', get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); ?>#saved-cards"><?php esc_html_e( 'Manage cards', 'woocommerce-gateway-stripe' ); ?></a>
						<?php if ( $cards ) : ?>
							<?php foreach ( (array) $cards as $card ) :
								if ( 'card' !== $card->object ) {
									continue;
								}

								// subscription compatibility to select the card used for previous subs payment
								if ( ! empty( $_GET['change_payment_method'] ) ) {
									$subs_id = absint( $_GET['change_payment_method'] );

									$current_card = get_post_meta( $subs_id, '_stripe_card_id', true );

									// this overrides the customer default card with
									// the one used in the current subscription
									if ( ! empty( $current_card ) ) {
										$default_card = $current_card;
									}
								}
								?>
								<label for="stripe_card_<?php echo $card->id; ?>" class="brand-<?php echo esc_attr( strtolower( $card->brand ) ); ?>">
									<input type="radio" id="stripe_card_<?php echo $card->id; ?>" name="stripe_card_id" value="<?php echo $card->id; ?>" <?php checked( $default_card, $card->id ) ?> />
									<?php printf( __( '%s card ending in %s (Expires %s/%s)', 'woocommerce-gateway-stripe' ), $card->brand, $card->last4, $card->exp_month, $card->exp_year ); ?>
								</label>
								<?php endforeach; ?>
						<?php endif; ?>
						<label for="new">
							<input type="radio" id="new" name="stripe_card_id" value="new" />
							<?php _e( 'Use a new credit card', 'woocommerce-gateway-stripe' ); ?>
						</label>
					</p>
					<?php
				}

				$display = '';

				if ( $this->stripe_checkout || $this->saved_cards && ! empty( $cards ) ) {
					$display = 'style="display:none;"';
				}
			?>
			<div class="stripe_new_card" <?php echo $display; ?>
				data-description=""
				data-amount="<?php echo esc_attr( $this->get_stripe_amount( WC()->cart->total ) ); ?>"
				data-name="<?php echo esc_attr( sprintf( __( '%s', 'woocommerce-gateway-stripe' ), get_bloginfo( 'name', 'display' ) ) ); ?>"
				data-currency="<?php echo esc_attr( strtolower( get_woocommerce_currency() ) ); ?>"
				data-image="<?php echo esc_attr( $this->stripe_checkout_image ); ?>"
				data-bitcoin="<?php echo esc_attr( $this->bitcoin ? 'true' : 'false' ); ?>"
				data-locale="<?php echo esc_attr( $this->stripe_checkout_locale ? $this->stripe_checkout_locale : 'en' ); ?>"
				>
				<?php if ( ! $this->stripe_checkout ) : ?>
					<?php $this->credit_card_form( array( 'fields_have_names' => false ) ); ?>
				<?php endif; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Get a customers saved cards using their Stripe ID. Cached.
	 *
	 * @param  string $customer_id
	 * @return bool|array
	 */
	public function get_saved_cards( $customer_id ) {
		if ( false === ( $cards = get_transient( 'stripe_cards_' . $customer_id ) ) ) {
			$response = $this->stripe_request( array(
				'limit'       => 100
			), 'customers/' . $customer_id . '/sources', 'GET' );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$cards = $response->data;

			set_transient( 'stripe_cards_' . $customer_id, $cards, HOUR_IN_SECONDS * 48 );
		}

		return $cards;
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for stripe payment
	 *
	 * @access public
	 */
	public function payment_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( $this->stripe_checkout ) {
			wp_enqueue_script( 'stripe', 'https://checkout.stripe.com/v2/checkout.js', '', '2.0', true );
			wp_enqueue_script( 'woocommerce_stripe', plugins_url( 'assets/js/stripe_checkout.js', dirname( __FILE__ ) ), array( 'stripe' ), WC_STRIPE_VERSION, true );
		} else {
			wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', '', '1.0', true );
			wp_enqueue_script( 'woocommerce_stripe', plugins_url( 'assets/js/stripe.js', dirname( __FILE__ ) ), array( 'wc-credit-card-form', 'stripe' ), WC_STRIPE_VERSION, true );
		}

		$stripe_params = array(
			'key'                    => $this->publishable_key,
			'i18n_terms'             => __( 'Please accept the terms and conditions first', 'woocommerce-gateway-stripe' ),
			'i18n_required_fields'   => __( 'Please fill in required checkout fields first', 'woocommerce-gateway-stripe' ),
			'error_response_handler' => 'stripeErrorHandler',
		);

		// If we're on the pay page we need to pass stripe.js the address of the order.
		if ( is_checkout_pay_page() && isset( $_GET['order'] ) && isset( $_GET['order_id'] ) ) {
			$order_key = urldecode( $_GET['order'] );
			$order_id  = absint( $_GET['order_id'] );
			$order     = wc_get_order( $order_id );

			if ( $order->id == $order_id && $order->order_key == $order_key ) {
				$stripe_params['billing_first_name'] = $order->billing_first_name;
				$stripe_params['billing_last_name']  = $order->billing_last_name;
				$stripe_params['billing_address_1']  = $order->billing_address_1;
				$stripe_params['billing_address_2']  = $order->billing_address_2;
				$stripe_params['billing_state']      = $order->billing_state;
				$stripe_params['billing_city']       = $order->billing_city;
				$stripe_params['billing_postcode']   = $order->billing_postcode;
				$stripe_params['billing_country']    = $order->billing_country;
			}
		}

		wp_localize_script( 'woocommerce_stripe', 'wc_stripe_params', apply_filters( 'wc_stripe_params', $stripe_params ) );
	}

	/**
	 * Process the payment
	 */
	public function process_payment( $order_id, $retry = true ) {
		$order        = wc_get_order( $order_id );
		$stripe_token = isset( $_POST['stripe_token'] ) ? wc_clean( $_POST['stripe_token'] ) : '';
		$card_id      = isset( $_POST['stripe_card_id'] ) ? wc_clean( $_POST['stripe_card_id'] ) : '';
		$customer_id  = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) : 0;

		if ( ! $customer_id || ! is_string( $customer_id ) ) {
			$customer_id = 0;
		}

		$this->log( "Info: Beginning processing payment for order $order_id for the amount of {$order->order_total}" );

		// Use Stripe CURL API for payment
		try {
			$post_data = array();

			// Check amount
			if ( $order->order_total * 100 < 50 ) {
				throw new Exception( __( 'Sorry, the minimum allowed order total is 0.50 to use this payment method.', 'woocommerce-gateway-stripe' ) );
			}

			// Pay using a saved card!
			if ( $card_id !== 'new' && $card_id && $customer_id ) {
				$post_data['customer'] = $customer_id;
				$post_data['source']   = $card_id;
			}

			// If not using a saved card, we need a token
			elseif ( empty( $stripe_token ) ) {
				$error_msg = __( 'Please make sure your card details have been entered correctly and that your browser supports JavaScript.', 'woocommerce-gateway-stripe' );

				if ( $this->testmode ) {
					$error_msg .= ' ' . __( 'Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', 'woocommerce-gateway-stripe' );
				}

				throw new Exception( $error_msg );
			}

			// Use token
			else {

				// Save token if logged in
				if ( is_user_logged_in() && $this->saved_cards ) {
					if ( ! $customer_id ) {
						$customer_id = $this->add_customer( $order, $stripe_token );

						if ( is_wp_error( $customer_id ) ) {
							throw new Exception( $customer_id->get_error_message() );
						}
					} else {
						$card_id = $this->add_card( $customer_id, $stripe_token );

						if ( is_wp_error( $card_id ) ) {
							// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id and retry.
							if ( 'customer' === $card_id->get_error_code() && $retry ) {
								delete_user_meta( get_current_user_id(), '_stripe_customer_id' );
								return $this->process_payment( $order_id, false ); // false to prevent retry again (endless loop)
							}
							throw new Exception( $card_id->get_error_message() );
						}

						$post_data['source'] = $card_id;
					}

					$post_data['customer'] = $customer_id;
				} else {
					$post_data['source'] = $stripe_token;
				}
			}

			// Store the ID in the order
			if ( $customer_id ) {
				update_post_meta( $order_id, '_stripe_customer_id', $customer_id );
			}
			if ( $card_id ) {
				update_post_meta( $order_id, '_stripe_card_id', $card_id );
			}

			// Other charge data
			$post_data['currency']       = strtolower( $order->get_order_currency() ? $order->get_order_currency() : get_woocommerce_currency() );
			$post_data['amount']         = $this->get_stripe_amount( $order->order_total, $post_data['currency'] );
			$post_data['description']    = sprintf( __( '%s - Order %s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
			$post_data['capture']        = $this->capture ? 'true' : 'false';

			if ( ! empty( $order->billing_email ) && apply_filters( 'wc_stripe_send_stripe_receipt', false ) ) {
				$post_data['receipt_email'] = $order->billing_email;
			}

			$post_data['expand[]']    = 'balance_transaction';

			// Make the request
			$response = $this->stripe_request( $post_data );

			if ( is_wp_error( $response ) ) {
				// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id and retry.
				if ( 'customer' === $response->get_error_code() && $retry ) {
					delete_user_meta( get_current_user_id(), '_stripe_customer_id' );
					return $this->process_payment( $order_id, false ); // false to prevent retry again (endless loop)
				}
				throw new Exception( $response->get_error_message() );
			}

			// Store charge ID
			update_post_meta( $order->id, '_stripe_charge_id', $response->id );

			// Store other data such as fees
			update_post_meta( $order->id, 'Stripe Payment ID', $response->id );

			if ( isset( $response->balance_transaction ) && isset( $response->balance_transaction->fee ) ) {
				$fee = number_format( $response->balance_transaction->fee / 100, 2, '.', '' );
				update_post_meta( $order->id, 'Stripe Fee', $fee );
				update_post_meta( $order->id, 'Net Revenue From Stripe', $order->order_total - $fee );
			}

			if ( $response->captured ) {

				// Store captured value
				update_post_meta( $order->id, '_stripe_charge_captured', 'yes' );

				// Payment complete
				$order->payment_complete( $response->id );

				// Add order note
				$complete_message = sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $response->id );
				$order->add_order_note( $complete_message );
				$this->log( "Success: $complete_message" );

			} else {

				// Store captured value
				update_post_meta( $order->id, '_stripe_charge_captured', 'no' );
				add_post_meta( $order->id, '_transaction_id', $response->id, true );

				// Mark as on-hold
				$authorized_message = sprintf( __( 'Stripe charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-stripe' ), $response->id );
				$order->update_status( 'on-hold', $authorized_message );
				$this->log( "Success: $authorized_message" );

				// Reduce stock levels
				$order->reduce_order_stock();
			}

			// Remove cart
			WC()->cart->empty_cart();

			// Return thank you page redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			$this->log( sprintf( __( 'Error: %s', 'woocommerce-gateway-stripe' ), $e->getMessage() ) );

			$order_note = $e->getMessage();
			$order->update_status( 'failed', $order_note );

			return;
		}
	}

	/**
	 * Refund a charge
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
			$body['amount']	= $this->get_stripe_amount( $amount );
		}

		if ( $reason ) {
			$body['metadata'] = array(
				'reason'	=> $reason,
			);
		}

		$this->log( "Info: Beginning refund for order $order_id for the amount of {$amount}" );

		$response = $this->stripe_request( $body, 'charges/' . $order->get_transaction_id() . '/refunds' );

		if ( is_wp_error( $response ) ) {
			$this->log( "Error: " . $response->get_error_message() );
			return $response;
		} elseif ( ! empty( $response->id ) ) {
			$refund_message = sprintf( __( 'Refunded %s - Refund ID: %s - Reason: %s', 'woocommerce' ), wc_price( $response->amount / 100 ), $response->id, $reason );
			$order->add_order_note( $refund_message );
			$this->log( "Success: " . html_entity_decode( strip_tags( $refund_message ) ) );
			return true;
		}
	}

	/**
	 * Add a customer to Stripe via the API.
	 *
	 * @param int $order
	 * @param string $stripe_token
	 * @return int|WP_ERROR
	 */
	public function add_customer( $order, $stripe_token ) {
		if ( $stripe_token ) {
			$response = $this->stripe_request( array(
				'email'       => $order->billing_email,
				'description' => 'Customer: ' . $order->billing_first_name . ' ' . $order->billing_last_name,
				'source'      => $stripe_token
			), 'customers' );

			if ( is_wp_error( $response ) ) {
				return $response;
			} elseif ( ! empty( $response->id ) ) {

				// Store the ID on the user account if logged in
				if ( is_user_logged_in() ) {
					update_user_meta( get_current_user_id(), '_stripe_customer_id', $response->id );
				}

				// Store the ID in the order
				update_post_meta( $order->id, '_stripe_customer_id', $response->id );

				do_action( 'wc_stripe_add_customer', $order, $stripe_token, $response );

				return $response->id;
			}
		}
		$error_message = __( 'Unable to add customer', 'woocommerce-gateway-stripe' );
		$this->log( sprintf( __( 'Error: %s', 'woocommerce-gateway-stripe' ), $error_message ) );
		return new WP_Error( 'error', $error_message );
	}

	/**
	 * Add a card to a customer via the API.
	 *
	 * @param int $order
	 * @param string $stripe_token
	 * @return int|WP_ERROR
	 */
	public function add_card( $customer_id, $stripe_token ) {
		if ( $stripe_token ) {
			$response = $this->stripe_request( array(
				'source' => $stripe_token
			), 'customers/' . $customer_id . '/sources' );

			delete_transient( 'stripe_cards_' . $customer_id );

			do_action( 'wc_stripe_add_card', $customer_id, $stripe_token, $response );

			if ( is_wp_error( $response ) ) {
				return $response;
			} elseif ( ! empty( $response->id ) ) {
				return $response->id;
			}
		}
		return new WP_Error( 'error', __( 'Unable to add card', 'woocommerce-gateway-stripe' ) );
	}

	/**
	 * Send the request to Stripe's API
	 *
	 * @param array $request
	 * @param string $api
	 * @return array|WP_Error
	 */
	public function stripe_request( $request, $api = 'charges', $method = 'POST' ) {
		$response = wp_safe_remote_post(
			$this->api_endpoint . 'v1/' . $api,
			array(
				'method'        => $method,
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( $this->secret_key . ':' ),
					'Stripe-Version' => '2015-04-07'
				),
				'body'       => apply_filters( 'wc_stripe_request_body', $request, $api ),
				'timeout'    => 70,
				'user-agent' => 'WooCommerce ' . WC()->version
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'stripe_error', __( 'There was a problem connecting to the payment gateway.', 'woocommerce-gateway-stripe' ) );
		}

		if ( empty( $response['body'] ) ) {
			return new WP_Error( 'stripe_error', __( 'Empty response.', 'woocommerce-gateway-stripe' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			return new WP_Error( ! empty( $parsed_response->error->code ) ? $parsed_response->error->code : 'stripe_error', $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

	/**
	 * Make new log entry.
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			$loader = WC_Gateway_Stripe_Loader::get_instance();
			$loader->log( $message );
		}
	}
}
