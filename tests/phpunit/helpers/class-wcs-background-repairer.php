<?php
/**
 * WCS_Background_Repairer helper.
 */

/**
 * Class WCS_Background_Repairer.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WCS_Background_Repairer {

	/**
	 * The WC_Logger instance.
	 *
	 * @var WC_Logger_Interface
	 */
	protected $logger;

	/**
	 * The log handle.
	 *
	 * @var string
	 */
	protected $log_handle;

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
