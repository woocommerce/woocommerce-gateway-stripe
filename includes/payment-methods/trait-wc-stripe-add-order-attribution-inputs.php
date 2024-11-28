<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Trait for adding order attribution inputs.
 */
trait WC_Stripe_Add_Order_Attribution_Inputs {
	/**
	 * Add order attribution inputs to the page.
	 *
	 * @return void
	 */
	public function add_order_attribution_inputs() {
		echo '<wc-order-attribution-inputs id="wc-stripe-express-checkout__order-attribution-inputs"></wc-order-attribution-inputs>';
	}
}
