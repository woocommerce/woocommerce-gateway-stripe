<?php

/**
 * Stub file for WCS_Background_Repairer class that may be present in WooCommerce Subscriptions.
 * This file is not present in the Stripe plugin code, so we have this stub for PHPStan.
 */

class WCS_Background_Repairer {
	/**
	 * WC Logger instance for logging messages.
	 *
	 * @var WC_Logger_Interface
	 */
	protected $logger;

	/**
	 * @var string The log file handle to write messages to.
	 */
	protected $log_handle;

	public function schedule_repair() {
		$this->schedule_background_update();
	}

	/**
	 * @param string $message The message to be logged
	 */
	protected function log( $message ) {
		$this->logger->add( $this->log_handle, $message );
	}

	/**
	 * An internal cache of items which need to be repaired. Used in cases where the updater runs out of processing time, so we can ensure remaining items are processed in the next request.
	 *
	 * @var array
	 */
	protected $items_to_repair = array();
}
