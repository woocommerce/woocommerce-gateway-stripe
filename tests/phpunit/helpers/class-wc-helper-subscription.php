<?php
/**
 * Subscription helpers.
 */

/**
 * Class WC_Subscription.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Subscription extends WC_Order {

	/**
	 * Order type
	 *
	 * @var string
	 */
	public $order_type = 'shop_subscription';

	/**
	 * Initializes a specific subscription if the ID is passed, otherwise a new and empty instance of a subscription.
	 *
	 * This class should NOT be instantiated, instead the functions wcs_create_subscription() and wcs_get_subscription()
	 * should be used.
	 *
	 * @param int|WC_Subscription $subscription Subscription to read.
	 */
	public function __construct( $subscription = 0 ) {
		// Add the subscription to the order types so retrieving the subscription doesn't trigger an "Invalid order" exception.
		add_filter(
			'wc_order_types',
			function( $order_types ) {
				if ( ! in_array( $this->order_type, $order_types, true ) ) {
					$order_types[] = $this->order_type;
				}

				return $order_types;
			}
		);
		parent::__construct( $subscription );
	}

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->order_type;
	}
}
