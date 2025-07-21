<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Subscription_Detached_Bulk_Action
 */
class WC_Stripe_Subscription_Detached_Bulk_Action {
	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( WC_Stripe_Woo_Compat_Utils::is_custom_orders_table_enabled() ) {
			add_filter( 'bulk_actions-woocommerce_page_wc-orders--shop_subscription', [ $this, 'subscriptions_bulk_actions' ] );
			add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders--shop_subscription', [ $this, 'handle_subscription_detachment_check' ], 10, 3 );
		} else {
			add_filter( 'bulk_actions-edit-shop_subscription', [ $this, 'subscriptions_bulk_actions' ] );
			add_filter( 'handle_bulk_actions-edit-shop_subscription', [ $this, 'handle_subscription_detachment_check' ], 10, 3 );
		}
	}

	/**
	 * Add custom bulk action to check for detachment of subscriptions' payment methods.
	 *
	 * @param array $bulk_actions An associative array of actions which can be performed on the subscription post type.
	 * @return array
	 */
	public function subscriptions_bulk_actions( $bulk_actions ) {
		$bulk_actions['check-for-payment-method-detachment'] = __( 'Check for payment method detachment', 'woocommerce-gateway-stripe' );
		return $bulk_actions;
	}

	/**
	 * Handle the custom bulk action to check for detachment of subscriptions' payment methods.
	 *
	 * @param string $redirect_url The URL to redirect to after the action is performed.
	 * @param string $action The action being performed.
	 * @param array $post_ids The IDs of the posts being acted upon.
	 * @return string
	 */
	public function handle_subscription_detachment_check( $redirect_url, $action, $post_ids ) {
		if ( 'check-for-payment-method-detachment' === $action ) {
			update_option( 'wc_stripe_show_subscription_detached_bulk_action_notice', 'yes' );

			$detached_subscriptions_ids = [];
			foreach ( $post_ids as $post_id ) {
				$subscription = wcs_get_subscription( $post_id );

				if ( ! $subscription instanceof WC_Subscription ) {
					continue;
				}

				if ( WC_Stripe_Subscriptions_Helper::is_subscription_payment_method_detached( $subscription ) ) {
					$detached_subscriptions_ids[] = $subscription->get_id();
				}
			}
			return add_query_arg(
				'detached-subscriptions',
				implode( ',', $detached_subscriptions_ids ),
				$redirect_url
			);
		}
		return $redirect_url;
	}
}
