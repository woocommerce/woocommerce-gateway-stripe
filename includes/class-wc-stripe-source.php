<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Stripe_Source class.
 *
 * Wrapper methods to create, retrieve and update a source.
 *
 * @since 4.0.0
 */
class WC_Gateway_Stripe_Source {
	/**
	 * Creates a source.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function create_source( $source_object = null, $order ) {
		$stripe_settings       = get_option( 'woocommerce_stripe_settings', array() );
		$currency              = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency();
		$order_id              = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();
		$post_data             = array();
		$post_data['amount']   = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency );
		$post_data['currency'] = strtolower( $currency );
		$post_data['type']     = $source_object->type;
		$post_data['metadata'] = array( 'order_id' => $order_id );
		$post_data['owner']    = $source_object->owner;

		switch ( $source_object->type ) {
			case 'card':
				if ( 'required' === $source_object->card->three_d_secure || 'optional' === $source_object->card->three_d_secure ) {
					$post_data['three_d_secure'] = array( 'card' => $source_object->id );
					$post_data['redirect']       = array( 'return_url' => $source_object->redirect->return_url );
					$post_data['type']           = 'three_d_secure';
				}

				break;
			case 'bancontact':
				$post_data['redirect'] = array( 'return_url' => $source_object->redirect->return_url );
				break;
		}

		return WC_Stripe_API::request( $post_data, 'sources' );
	}

	public static function get_source() {

	}

	public static function update_source() {

	}
}
