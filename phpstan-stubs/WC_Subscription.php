<?php

/**
 * PHPStan stub for WC_Subscription class from WooCommerce Subscriptions plugin.
 * This file is not present in the Stripe plugin code, so we have this stub for PHPStan.
 */

if ( ! class_exists( 'WC_Subscription' ) ) {
	/**
	 * Subscription Object
	 *
	 * Extends WC_Order to provide subscription-specific functionality.
	 *
	 * @class    WC_Subscription
	 * @extends  WC_Order
	 */
	class WC_Subscription extends WC_Order {
		/**
		 * Get the subscription ID.
		 *
		 * @return int Subscription ID.
		 */
		public function get_id() {
			return 123;
		}

        /**
         * Get parent order object.
         *
         * @return WC_Order
         */
        public function get_parent() {
            return new WC_Order();
        }

        /**
	     * Get the URL to add or change the subscription's payment method from the my account page.
         *
         * @return string
         */
        public function get_change_payment_method_url() {
            return 'https://www.example.com';
        }

        /**
         * Get the timestamp for the subscriptions schedule
         *
         * @param string $date_type 'date_created', 'trial_end', 'next_payment', 'last_order_date_created', 'end' or 'end_of_prepaid_term'
         * @param string $timezone The timezone of the $datetime param. Default 'gmt'.
         * @param array $exclude_statuses An array of subscription statuses to exclude from the date calculation.
         * @return int The timestamp for the subscriptions schedule.
		 */
		public function get_time( $date_type, $timezone = 'gmt', $exclude_statuses = array() ) {
			return time();
		}
	}
}

