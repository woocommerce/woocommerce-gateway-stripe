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
			'pre-orders',
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

		WC_Stripe_API::set_secret_key( $this->secret_key );

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
			$icon .= '<img src="' . WC_HTTPS::force_https_url( plugins_url( '/assets/images/bitcoin' . $ext, WC_STRIPE_MAIN_FILE ) ) . '" alt="Bitcoin" width="32" ' . $style . ' />';
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
			echo '<div class="error"><p>' . sprintf( __( 'Stripe is enabled, but the <a href="%1$s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid <a href="%2$s" target="_blank">SSL certificate</a> - Stripe will only work in test mode.', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ) . '</p></div>';
		}
	}

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
		return false;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( untrailingslashit( plugin_dir_path( WC_STRIPE_MAIN_FILE ) ) . '/includes/settings-stripe.php' );

		wc_enqueue_js( "
			jQuery( function( $ ) {
				$( '#woocommerce_stripe_stripe_checkout' ).change(function(){
					if ( $( this ).is( ':checked' ) ) {
						$( '#woocommerce_stripe_stripe_checkout_locale, #woocommerce_stripe_stripe_bitcoin, #woocommerce_stripe_stripe_checkout_image' ).closest( 'tr' ).show();
					} else {
						$( '#woocommerce_stripe_stripe_checkout_locale, #woocommerce_stripe_stripe_bitcoin, #woocommerce_stripe_stripe_checkout_image' ).closest( 'tr' ).hide();
					}
				}).change();
			});
		" );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		?>
		<fieldset class="stripe-legacy-payment-fields">
			<?php
				if ( $this->description ) {
					echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( $this->description ) ) );
				}
				if ( $this->saved_cards && is_user_logged_in() ) {
					$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
					?>
					<p class="form-row form-row-wide">
						<a class="<?php echo apply_filters( 'wc_stripe_manage_saved_cards_class', 'button' ); ?>" style="float:right;" href="<?php echo apply_filters( 'wc_stripe_manage_saved_cards_url', get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); ?>#saved-cards"><?php esc_html_e( 'Manage cards', 'woocommerce-gateway-stripe' ); ?></a>
						<?php
						if ( $cards = $stripe_customer->get_cards() ) {
							$default_card = $cards[0]->id;
							foreach ( (array) $cards as $card ) {
								if ( 'card' !== $card->object ) {
									continue;
								}
								?>
								<label for="stripe_card_<?php echo $card->id; ?>" class="brand-<?php echo esc_attr( strtolower( $card->brand ) ); ?>">
									<input type="radio" id="stripe_card_<?php echo $card->id; ?>" name="wc-stripe-payment-token" value="<?php echo $card->id; ?>" <?php checked( $default_card, $card->id ) ?> />
									<?php printf( __( '%s card ending in %s (Expires %s/%s)', 'woocommerce-gateway-stripe' ), $card->brand, $card->last4, $card->exp_month, $card->exp_year ); ?>
								</label>
								<?php
							}
						}
						?>
						<label for="new">
							<input type="radio" id="new" name="wc-stripe-payment-token" value="new" />
							<?php _e( 'Use a new credit card', 'woocommerce-gateway-stripe' ); ?>
						</label>
					</p>
					<?php
				}

				$user = wp_get_current_user();

				if ( $user ) {
					$user_email = get_user_meta( $user->ID, 'billing_email', true );
					$user_email = $user_email ? $user_email : $user->user_email;
				} else {
					$user_email = '';
				}

				$display = '';

				if ( $this->stripe_checkout || $this->saved_cards && ! empty( $cards ) ) {
					$display = 'style="display:none;"';
				}

				echo '<div ' . $display . ' id="stripe-payment-data"
					data-description=""
					data-email="' . esc_attr( $user_email ) . '"
					data-amount="' . esc_attr( $this->get_stripe_amount( WC()->cart->total ) ) . '"
					data-name="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '"
					data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '"
					data-image="' . esc_attr( $this->stripe_checkout_image ) . '"
					data-bitcoin="' . esc_attr( $this->bitcoin ? 'true' : 'false' ) . '"
					data-locale="' . esc_attr( $this->stripe_checkout_locale ? $this->stripe_checkout_locale : 'en' ) . '">';

				if ( ! $this->stripe_checkout ) {
					$this->credit_card_form( array( 'fields_have_names' => false ) );
				}

				echo '</div>';
			?>
		</fieldset>
		<?php
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for stripe payment
	 *
	 * @access public
	 */
	public function payment_scripts() {
		if ( $this->stripe_checkout ) {
			wp_enqueue_script( 'stripe', 'https://checkout.stripe.com/v2/checkout.js', '', '2.0', true );
			wp_enqueue_script( 'woocommerce_stripe', plugins_url( 'assets/js/stripe-checkout.js', WC_STRIPE_MAIN_FILE ), array( 'stripe' ), WC_STRIPE_VERSION, true );
		} else {
			wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', '', '1.0', true );
			wp_enqueue_script( 'woocommerce_stripe', plugins_url( 'assets/js/stripe.js', WC_STRIPE_MAIN_FILE ), array( 'jquery-payment', 'stripe' ), WC_STRIPE_VERSION, true );
		}

		$stripe_params = array(
			'key'                  => $this->publishable_key,
			'i18n_terms'           => __( 'Please accept the terms and conditions first', 'woocommerce-gateway-stripe' ),
			'i18n_required_fields' => __( 'Please fill in required checkout fields first', 'woocommerce-gateway-stripe' ),
		);

		// If we're on the pay page we need to pass stripe.js the address of the order.
		if ( is_checkout_pay_page() && isset( $_GET['order'] ) && isset( $_GET['order_id'] ) ) {
			$order_key = urldecode( $_GET['order'] );
			$order_id  = absint( $_GET['order_id'] );
			$order     = wc_get_order( $order_id );

			if ( $order->id === $order_id && $order->order_key === $order_key ) {
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
	 * Generate the request for the payment.
	 * @param  WC_Order $order
	 * @param  object $source
	 * @return array()
	 */
	protected function generate_payment_request( $order, $source ) {
		$post_data                = array();
		$post_data['currency']    = strtolower( $order->get_order_currency() ? $order->get_order_currency() : get_woocommerce_currency() );
		$post_data['amount']      = $this->get_stripe_amount( $order->get_total(), $post_data['currency'] );
		$post_data['description'] = sprintf( __( '%s - Order %s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
		$post_data['capture']     = $this->capture ? 'true' : 'false';

		if ( ! empty( $order->billing_email ) && apply_filters( 'wc_stripe_send_stripe_receipt', false ) ) {
			$post_data['receipt_email'] = $order->billing_email;
		}

		$post_data['expand[]']    = 'balance_transaction';

		if ( $source->customer ) {
			$post_data['customer'] = $source->customer;
		}

		if ( $source->source ) {
			$post_data['source'] = $source->source;
		}

		return apply_filters( 'wc_stripe_generate_payment_request', $post_data, $order, $source );
	}

	/**
	 * Get payment source. This can be a new token or existing card.
	 * @param  bool $force_customer Should we force customer creation?
	 * @return object
	 */
	protected function get_source( $user_id, $force_customer = false ) {
		$stripe_customer = new WC_Stripe_Customer( $user_id );
		$stripe_source   = false;
		$token_id        = false;

		// New CC info was entered and we have a new token to process
		if ( isset( $_POST['stripe_token'] ) ) {
			$stripe_token     = wc_clean( $_POST['stripe_token'] );
			$maybe_saved_card = isset( $_POST['wc-stripe-new-payment-method'] ) && ! empty( $_POST['wc-stripe-new-payment-method'] );

			// This is true if the user wants to store the card to their account.
			if ( ( $user_id && $this->saved_cards && $maybe_saved_card ) || $force_customer ) {
				$stripe_source = $stripe_customer->add_card( $stripe_token );

				if ( is_wp_error( $stripe_source ) ) {
					throw new Exception( $stripe_source->get_error_message() );
				}

			} else {
				// Not saving token, so don't define customer either.
				$stripe_source   = $stripe_token;
				$stripe_customer = false;
			}
		}

		// Use an existing token, and then process the payment
		elseif ( isset( $_POST['wc-stripe-payment-token'] ) && 'new' !== $_POST['wc-stripe-payment-token'] ) {
			$stripe_source = wc_clean( $_POST['wc-stripe-payment-token'] );
		}

		return (object) array(
			'token_id' => $token_id,
			'customer' => $stripe_customer ? $stripe_customer->get_id() : false,
			'source'   => $stripe_source,
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
	 * @param object $order
	 * @return object
	 */
	protected function get_order_source( $order = null ) {
		$stripe_customer = new WC_Stripe_Customer();
		$stripe_source   = false;
		$token_id        = false;

		if ( $order ) {
			if ( $meta_value = get_post_meta( $order->id, '_stripe_customer_id', true ) ) {
				$stripe_customer->set_id( $meta_value );
			}
			if ( $meta_value = get_post_meta( $order->id, '_stripe_card_id', true ) ) {
				$stripe_source = $meta_value;
			}
		}

		return (object) array(
			'token_id' => $token_id,
			'customer' => $stripe_customer ? $stripe_customer->get_id() : false,
			'source'   => $stripe_source,
		);
	}

	/**
	 * Process the payment
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		try {
			$order  = wc_get_order( $order_id );
			$source = $this->get_source( get_current_user_id(), $force_customer );

			if ( empty( $source->source ) && empty( $source->customer ) ) {
				$error_msg = __( 'Please enter your card details to make a payment.', 'woocommerce-gateway-stripe' );
				$error_msg .= ' ' . __( 'Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', 'woocommerce-gateway-stripe' );
				throw new Exception( $error_msg );
			}

			// Store source to order meta
			$this->save_source( $order, $source );

			// Handle payment
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() * 100 < WC_Stripe::get_minimum_amount() ) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe::get_minimum_amount() / 100 ) ) );
				}

				WC_Stripe::log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

				// Make the request
				$response = WC_Stripe_API::request( $this->generate_payment_request( $order, $source ) );

				if ( is_wp_error( $response ) ) {
					// Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id. Can be retried without.
					if ( 'customer' === $response->get_error_code() && $retry ) {
						delete_user_meta( get_current_user_id(), '_stripe_customer_id' );
						return $this->process_payment( $order_id, false, $force_customer );
					}
					throw new Exception( $response->get_error_code() . ': ' . $response->get_error_message() );
				}

				// Process valid response
				$this->process_response( $response, $order );
			} else {
				$order->payment_complete();
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
			WC()->session->set( 'refresh_totals', true );
			WC_Stripe::log( sprintf( __( 'Error: %s', 'woocommerce-gateway-stripe' ), $e->getMessage() ) );
			return;
		}
	}

	/**
	 * Save source to order.
	 */
	protected function save_source( $order, $source ) {
		// Store source in the order
		if ( $source->customer ) {
			update_post_meta( $order->id, '_stripe_customer_id', $source->customer );
		}
		if ( $source->source ) {
			update_post_meta( $order->id, '_stripe_card_id', $source->source->id );
		}
	}

	/**
	 * Store extra meta data for an order from a Stripe Response.
	 */
	public function process_response( $response, $order ) {
		WC_Stripe::log( "Processing response: " . print_r( $response, true ) );

		// Store charge data
		update_post_meta( $order->id, '_stripe_charge_id', $response->id );
		update_post_meta( $order->id, '_stripe_charge_captured', $response->captured ? 'yes' : 'no' );

		// Store other data such as fees
		if ( isset( $response->balance_transaction ) && isset( $response->balance_transaction->fee ) ) {
			$fee = number_format( $response->balance_transaction->fee / 100, 2, '.', '' );
			update_post_meta( $order->id, 'Stripe Fee', $fee );
			update_post_meta( $order->id, 'Net Revenue From Stripe', $order->get_total() - $fee );
		}

		if ( $response->captured ) {
			$order->payment_complete( $response->id );
			WC_Stripe::log( "Successful charge: $response->id" );
		} else {
			update_post_meta( $order->id, '_transaction_id', $response->id, true );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$order->reduce_order_stock();
			}

			$order->update_status( 'on-hold', sprintf( __( 'Stripe charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-stripe' ), $response->id ) );
			WC_Stripe::log( "Successful auth: $response->id" );
		}

		return $response;
	}

	/**
	 * Add payment method via account screen.
	 * We don't store the token locally, but to the Stripe API.
	 * @since 3.0.0
	 */
	public function add_payment_method() {
		if ( empty( $_POST['stripe_token'] ) || ! is_user_logged_in() ) {
			wc_add_notice( __( 'There was a problem adding the card.', 'woocommerce-gateway-stripe' ), 'error' );
			return;
		}

		$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
		$result          = $stripe_customer->add_card( wc_clean( $_POST['stripe_token'] ) );

		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}

		return array(
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		);
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

		WC_Stripe::log( "Info: Beginning refund for order $order_id for the amount of {$amount}" );

		$response = WC_Stripe_API::request( $body, 'charges/' . $order->get_transaction_id() . '/refunds' );

		if ( is_wp_error( $response ) ) {
			WC_Stripe::log( "Error: " . $response->get_error_message() );
			return $response;
		} elseif ( ! empty( $response->id ) ) {
			$refund_message = sprintf( __( 'Refunded %s - Refund ID: %s - Reason: %s', 'woocommerce-gateway-stripe' ), wc_price( $response->amount / 100 ), $response->id, $reason );
			$order->add_order_note( $refund_message );
			WC_Stripe::log( "Success: " . html_entity_decode( strip_tags( $refund_message ) ) );
			return true;
		}
	}
}
