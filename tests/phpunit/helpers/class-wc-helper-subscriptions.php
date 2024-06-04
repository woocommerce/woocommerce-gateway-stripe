<?php
/**
 * Subscriptions helpers.
 */

/**
 * Class WC_Subscriptions.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Subscriptions {

	/**
	 * @var string
	 */
	public static $version = '6.3.2';

}


/**
 * Class WCS_Background_Repairer.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WCS_Background_Repairer {

	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	public function init() {}

	public function schedule_repair() {}

	public function get_items_to_update( $page ) {
		return $this->get_items_to_repair( $page );
	}

	protected function log( $message ) {
		$this->logger->add( $this->log_handle, $message );
	}
}
