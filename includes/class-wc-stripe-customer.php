<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Customer class.
 *
 * Represents a Stripe Customer.
 */
class WC_Stripe_Customer {

	/**
	 * Stripe customer ID
	 * @var string
	 */
	private $id = '';

	/**
	 * WP User ID
	 * @var integer
	 */
	private $user_id = 0;

	/**
	 * Data from API
	 * @var array
	 */
	private $customer_data = array();

	/**
	 * Constructor
	 * @param int $user_id The WP user ID
	 */
	public function __construct( $user_id = 0 ) {
		if ( $user_id ) {
			$this->set_user_id( $user_id );
			$this->set_id( get_user_meta( $user_id, '_stripe_customer_id', true ) );
		}
	}

	/**
	 * Get Stripe customer ID.
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set Stripe customer ID.
	 * @param [type] $id [description]
	 */
	public function set_id( $id ) {
		// Backwards compat for customer ID stored in array format. (Pre 3.0)
		if ( is_array( $id ) && isset( $id['customer_id'] ) ) {
			$id = $id['customer_id'];

			update_user_meta( $this->get_user_id(), '_stripe_customer_id', $id );
		}

		$this->id = wc_clean( $id );
	}

	/**
	 * User ID in WordPress.
	 * @return int
	 */
	public function get_user_id() {
		return absint( $this->user_id );
	}

	/**
	 * Set User ID used by WordPress.
	 * @param int $user_id
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = absint( $user_id );
	}

	/**
	 * Get user object.
	 * @return WP_User
	 */
	protected function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Store data from the Stripe API about this customer
	 */
	public function set_customer_data( $data ) {
		$this->customer_data = $data;
	}

	/**
	 * Create a customer via API.
	 * @param array $args
	 * @return WP_Error|int
	 */
	public function create_customer( $args = array() ) {
		$billing_email = isset( $_POST['billing_email'] ) ? filter_var( $_POST['billing_email'], FILTER_SANITIZE_EMAIL ) : '';
		$user          = $this->get_user();

		if ( $user ) {
			$billing_first_name = get_user_meta( $user->ID, 'billing_first_name', true );
			$billing_last_name  = get_user_meta( $user->ID, 'billing_last_name', true );

			// If billing first name does not exists try the user first name.
			if ( empty( $billing_first_name ) ) {
				$billing_first_name = get_user_meta( $user->ID, 'first_name', true );
			}

			// If billing last name does not exists try the user last name.
			if ( empty( $billing_last_name ) ) {
				$billing_last_name = get_user_meta( $user->ID, 'last_name', true );
			}

			// translators: %1$s First name, %2$s Second name, %3$s Username.
			$description = sprintf( __( 'Name: %1$s %2$s, Username: %s', 'woocommerce-gateway-stripe' ), $billing_first_name, $billing_last_name, $user->user_login );

			$defaults = array(
				'email'       => $user->user_email,
				'description' => $description,
			);
		} else {
			$billing_first_name = isset( $_POST['billing_first_name'] ) ? filter_var( wp_unslash( $_POST['billing_first_name'] ), FILTER_SANITIZE_STRING ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$billing_last_name  = isset( $_POST['billing_last_name'] ) ? filter_var( wp_unslash( $_POST['billing_last_name'] ), FILTER_SANITIZE_STRING ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			// translators: %1$s First name, %2$s Second name.
			$description = sprintf( __( 'Name: %1$s %2$s, Guest', 'woocommerce-gateway-stripe' ), $billing_first_name, $billing_last_name );

			$defaults = array(
				'email'       => $billing_email,
				'description' => $description,
			);
		}

		$metadata = array();

		$defaults['metadata'] = apply_filters( 'wc_stripe_customer_metadata', $metadata, $user );

		$args     = wp_parse_args( $args, $defaults );
		$response = WC_Stripe_API::request( apply_filters( 'wc_stripe_create_customer_args', $args ), 'customers' );

		if ( ! empty( $response->error ) ) {
			throw new WC_Stripe_Exception( print_r( $response, true ), $response->error->message );
		}

		$this->set_id( $response->id );
		$this->clear_cache();
		$this->set_customer_data( $response );

		if ( $this->get_user_id() ) {
			update_user_meta( $this->get_user_id(), '_stripe_customer_id', $response->id );
		}

		do_action( 'woocommerce_stripe_add_customer', $args, $response );

		return $response->id;
	}

	/**
	 * Checks to see if error is of invalid request
	 * error and it is no such customer.
	 *
	 * @since 4.1.2
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
	 * Add a source for this stripe customer.
	 * @param string $source_id
	 * @return WP_Error|int
	 */
	public function add_source( $source_id ) {
		if ( ! $this->get_id() ) {
			$this->set_id( $this->create_customer() );
		}

		$response = WC_Stripe_API::request(
			array(
				'source' => $source_id,
			),
			'customers/' . $this->get_id() . '/sources'
		);

		$wc_token = false;

		if ( ! empty( $response->error ) ) {
			// It is possible the WC user once was linked to a customer on Stripe
			// but no longer exists. Instead of failing, lets try to create a
			// new customer.
			if ( $this->is_no_such_customer_error( $response->error ) ) {
				delete_user_meta( $this->get_user_id(), '_stripe_customer_id' );
				$this->create_customer();
				return $this->add_source( $source_id );
			} else {
				return $response;
			}
		} elseif ( empty( $response->id ) ) {
			return new WP_Error( 'error', __( 'Unable to add payment source.', 'woocommerce-gateway-stripe' ) );
		}

		// Add token to WooCommerce.
		if ( $this->get_user_id() && class_exists( 'WC_Payment_Token_CC' ) ) {
			if ( ! empty( $response->type ) ) {
				switch ( $response->type ) {
					case 'alipay':
						break;
					case 'sepa_debit':
						$wc_token = new WC_Payment_Token_SEPA();
						$wc_token->set_token( $response->id );
						$wc_token->set_gateway_id( 'stripe_sepa' );
						$wc_token->set_last4( $response->sepa_debit->last4 );
						break;
					default:
						if ( 'source' === $response->object && 'card' === $response->type ) {
							$wc_token = new WC_Payment_Token_CC();
							$wc_token->set_token( $response->id );
							$wc_token->set_gateway_id( 'stripe' );
							$wc_token->set_card_type( strtolower( $response->card->brand ) );
							$wc_token->set_last4( $response->card->last4 );
							$wc_token->set_expiry_month( $response->card->exp_month );
							$wc_token->set_expiry_year( $response->card->exp_year );
						}
						break;
				}
			} else {
				// Legacy.
				$wc_token = new WC_Payment_Token_CC();
				$wc_token->set_token( $response->id );
				$wc_token->set_gateway_id( 'stripe' );
				$wc_token->set_card_type( strtolower( $response->brand ) );
				$wc_token->set_last4( $response->last4 );
				$wc_token->set_expiry_month( $response->exp_month );
				$wc_token->set_expiry_year( $response->exp_year );
			}

			$wc_token->set_user_id( $this->get_user_id() );
			$wc_token->save();
		}

		$this->clear_cache();

		do_action( 'woocommerce_stripe_add_source', $this->get_id(), $wc_token, $response, $source_id );

		return $response->id;
	}

	/**
	 * Get a customers saved sources using their Stripe ID.
	 *
	 * @param  string $customer_id
	 * @return array
	 */
	public function get_sources() {
		if ( ! $this->get_id() ) {
			return array();
		}

		$sources = get_transient( 'stripe_sources_' . $this->get_id() );

		$response = WC_Stripe_API::request(
			array(
				'limit' => 100,
			),
			'customers/' . $this->get_id() . '/sources',
			'GET'
		);

		if ( ! empty( $response->error ) ) {
			return array();
		}

		if ( is_array( $response->data ) ) {
			$sources = $response->data;
		}

		return empty( $sources ) ? array() : $sources;
	}

	/**
	 * Delete a source from stripe.
	 * @param string $source_id
	 */
	public function delete_source( $source_id ) {
		if ( ! $this->get_id() ) {
			return false;
		}

		$response = WC_Stripe_API::request( array(), 'customers/' . $this->get_id() . '/sources/' . sanitize_text_field( $source_id ), 'DELETE' );

		$this->clear_cache();

		if ( empty( $response->error ) ) {
			do_action( 'wc_stripe_delete_source', $this->get_id(), $response );

			return true;
		}

		return false;
	}

	/**
	 * Set default source in Stripe
	 * @param string $source_id
	 */
	public function set_default_source( $source_id ) {
		$response = WC_Stripe_API::request(
			array(
				'default_source' => sanitize_text_field( $source_id ),
			),
			'customers/' . $this->get_id(),
			'POST'
		);

		$this->clear_cache();

		if ( empty( $response->error ) ) {
			do_action( 'wc_stripe_set_default_source', $this->get_id(), $response );

			return true;
		}

		return false;
	}

	/**
	 * Deletes caches for this users cards.
	 */
	public function clear_cache() {
		delete_transient( 'stripe_sources_' . $this->get_id() );
		delete_transient( 'stripe_customer_' . $this->get_id() );
		$this->customer_data = array();
	}
}
