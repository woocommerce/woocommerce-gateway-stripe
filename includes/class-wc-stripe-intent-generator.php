<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Intent_Generator class.
 *
 * Handles in-checkout AJAX calls, which generate PaymentIntents.
 */
class WC_Stripe_Intent_Generator {
	/**
	 * Class constructor.
	 *
	 * Adds the necessary hooks.
	 */
	public function __construct() {
		add_action( 'wc_ajax_wc_stripe_create_intent', array( $this, 'create_intent' ) );
	}

	/**
	 * Handles the "create_event" AJAX action.
	 */
	public function create_intent() {
		$gateway = new WC_Gateway_Stripe;

		try {
			$intent = $gateway->get_intent_and_secret();
		} catch ( WC_Stripe_Exception $e ) {
			$intent = array(
				'error' => array(
					'error'   => $e->getMessage(),
					'message' => $e->getLocalizedMessage(),
				),
			);
		}

		echo wp_json_encode( $intent );
		exit;
	}
}

new WC_Stripe_Intent_Generator;
