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
		add_action( 'wp', array( $this, 'default_card' ) );
		add_action( 'woocommerce_after_my_account', array( $this, 'output' ) );
		add_action( 'woocommerce_init', array( $this, 'add_notice' ) );
	}

	/**
	 * Adds notice when default card is saved
	 *
	 */
	public function add_notice() {
		if ( ! empty( $_POST['stripe_default_card'] ) ) {
			wc_add_notice( __( 'Default card saved', 'woocommerce-gateway-stripe' ) );
		}
		
		return true;
	}

	/**
	 * Display saved cards
	 */
	public function output() {
		if ( ! is_user_logged_in() || ( ! $customer_id = get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) ) || ! is_string( $customer_id ) ) {
			return;
		}

		$stripe       = new WC_Gateway_Stripe();
		$cards        = $stripe->get_saved_cards( $customer_id );
		$default_card = get_user_meta( get_current_user_id(), '_wc_stripe_default_card', true );

		if ( $cards ) {
			wc_get_template( 'saved-cards.php', array( 'cards' => $cards, 'default_card' => $default_card ), 'woocommerce-gateway-stripe/', WC_STRIPE_TEMPLATE_PATH );
		}
	}

	/**
	 * Delete a card
	 */
	public function delete_card() {
		if ( ! isset( $_POST['stripe_delete_card'] ) || ! is_account_page() ) {
			return;
		}
		if ( ! is_user_logged_in() || ( ! $customer_id = get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) ) || ! is_string( $customer_id ) || ! wp_verify_nonce( $_POST['_wpnonce'], "stripe_del_card" ) ) {
			wp_die( __( 'Unable to verify deletion, please try again', 'woocommerce-gateway-stripe' ) );
		}
		$stripe = new WC_Gateway_Stripe();
		$result = $stripe->stripe_request( array(), 'customers/' . $customer_id . '/sources/' . sanitize_text_field( $_POST['stripe_delete_card'] ), 'DELETE' );

		delete_transient( 'stripe_cards_' . $customer_id );

		do_action( 'wc_stripe_delete_card', $customer_id, $result );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( __( 'Unable to delete card.', 'woocommerce-gateway-stripe' ), 'error' );
		} else {
			wc_add_notice( __( 'Card deleted.', 'woocommerce-gateway-stripe' ), 'success' );
		}

		wp_safe_redirect( apply_filters( 'wc_stripe_manage_saved_cards_url', get_permalink( woocommerce_get_page_id( 'myaccount' ) ) ) );
		exit;
	}

	/**
	 * Make a card as default method
	 */
	public function default_card() {
		if ( ! isset( $_POST['stripe_default_card'] ) || ! is_account_page() ) {
			return;
		}

		if ( ! is_user_logged_in() || ( ! $customer_id = get_user_meta( get_current_user_id(), '_stripe_customer_id', true ) ) || ! is_string( $customer_id ) || ! wp_verify_nonce( $_POST['_wpnonce'], "stripe_default_card" ) ) {
			wp_die( __( 'Unable to make default card, please try again', 'woocommerce-gateway-stripe' ) );
		}

		do_action( 'wc_stripe_default_card', $customer_id, sanitize_text_field( $_POST['stripe_default_card'] ) );

		update_user_meta( get_current_user_id(), '_wc_stripe_default_card', sanitize_text_field( $_POST['stripe_default_card'] ) );

		wp_safe_redirect( apply_filters( 'wc_stripe_manage_saved_cards_url', get_permalink( woocommerce_get_page_id( 'myaccount' ) ) ) );
		exit;
	}
}
new WC_Gateway_Stripe_Saved_Cards();
