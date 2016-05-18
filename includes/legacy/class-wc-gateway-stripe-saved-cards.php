<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Stripe_Saved_Cards class.
 */
class WC_Gateway_Stripe_Saved_Cards {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'delete_card' ) );
		add_action( 'woocommerce_after_my_account', array( $this, 'output' ) );
		add_action( 'wp', array( $this, 'default_card' ) );
	}

	/**
	 * Display saved cards
	 */
	public function output() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$stripe_customer = new WC_Stripe_Customer( get_current_user_id() );
		$stripe_cards    = $stripe_customer->get_cards();
		$default_card    = $stripe_customer->get_default_card();

		if ( $stripe_cards ) {
			wc_get_template( 'saved-cards.php', array( 'cards' => $stripe_cards, 'default_card' => $default_card ), 'woocommerce-gateway-stripe/', untrailingslashit( plugin_dir_path( WC_STRIPE_MAIN_FILE ) ) . '/includes/legacy/templates/' );
		}
	}

	/**
	 * Delete a card
	 */
	public function delete_card() {
		if ( ! isset( $_POST['stripe_delete_card'] ) || ! is_account_page() ) {
			return;
		}

		$stripe_customer    = new WC_Stripe_Customer( get_current_user_id() );
		$stripe_customer_id = $stripe_customer->get_id();
		$delete_card        = sanitize_text_field( $_POST['stripe_delete_card'] );

		if ( ! is_user_logged_in() || ! $stripe_customer_id || ! wp_verify_nonce( $_POST['_wpnonce'], "stripe_del_card" ) ) {
			wp_die( __( 'Unable to make default card, please try again', 'woocommerce-gateway-stripe' ) );
		}

		if ( ! $stripe_customer->delete_card( $delete_card ) ) {
			wc_add_notice( __( 'Unable to delete card.', 'woocommerce-gateway-stripe' ), 'error' );
		} else {
			wc_add_notice( __( 'Card deleted.', 'woocommerce-gateway-stripe' ), 'success' );
		}
	}

	/**
	 * Make a card as default method
	 */
	public function default_card() {
		if ( ! isset( $_POST['stripe_default_card'] ) || ! is_account_page() ) {
			return;
		}

		$stripe_customer    = new WC_Stripe_Customer( get_current_user_id() );
		$stripe_customer_id = $stripe_customer->get_id();
		$default_source     = sanitize_text_field( $_POST['stripe_default_card'] );

		if ( ! is_user_logged_in() || ! $stripe_customer_id || ! wp_verify_nonce( $_POST['_wpnonce'], "stripe_default_card" ) ) {
			wp_die( __( 'Unable to make default card, please try again', 'woocommerce-gateway-stripe' ) );
		}

		if ( ! $stripe_customer->set_default_card( $default_source ) ) {
			wc_add_notice( __( 'Unable to update default card.', 'woocommerce-gateway-stripe' ), 'error' );
		} else {
			wc_add_notice( __( 'Default card updated.', 'woocommerce-gateway-stripe' ), 'success' );
		}
	}
}
new WC_Gateway_Stripe_Saved_Cards();
