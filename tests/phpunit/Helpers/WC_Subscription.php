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
	 * An array storing the times for specific fields.
	 *
	 * @var array
	 */
	private $times = [];

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
			function ( $order_types ) {
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

	/**
	 * Get billing period.
	 *
	 * @return string
	 */
	public function get_billing_period() {
		return 'month';
	}

	/**
	 * Get billing interval.
	 *
	 * @return int
	 */
	public function get_billing_interval() {
		return 1;
	}

	/**
	 * Generates a URL to add or change the subscription's payment method from the my account page.
	 *
	 * @return string
	 */
	public function get_change_payment_method_url() {
		$change_payment_method_url = wc_get_endpoint_url( 'subscription-payment-method', $this->get_id(), wc_get_page_permalink( 'myaccount' ) );
		return apply_filters( 'wcs_get_change_payment_method_url', $change_payment_method_url, $this->get_id() );
	}

	/**
	 * Sets the time for a specific field.
	 *
	 * @param $field string Field to set the time for.
	 * @param $time int|false Time to set for the field.
	 * @return void
	 */
	public function set_time( $field, $time ) {
		$this->times[ $field ] = $time;
	}

	/**
	 * Get the time for a specific field.
	 *
	 * @param $field string Field to get the time for.
	 * @return false|int
	 */
	public function get_time( $field ) {
		return $this->times[ $field ] ?? false;
	}
}
