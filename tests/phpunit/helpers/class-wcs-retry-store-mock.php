<?php
/**
 * Mock for the retry store returned by WCS_Retry_Manager::store().
 *
 * This helper should ONLY be used for unit tests.
 */

/**
 * Minimal stand-in for the retry store. Returns whatever WCS_Retry the test
 * seeded via WCS_Retry_Manager::$mock_last_retry.
 */
class WCS_Retry_Store_Mock {
	/**
	 * @param int $order_id Order ID (ignored by the mock; tests seed a single retry).
	 * @return WCS_Retry|null
	 */
	public function get_last_retry_for_order( $order_id ) {
		return WCS_Retry_Manager::$mock_last_retry;
	}
}
