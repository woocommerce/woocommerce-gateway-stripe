<?php

/**
 * Data transfer object representing an error returned from Stripe's API.
 */
class WC_Stripe_Error {
	/**
	 * The raw error object returned by Stripe's API.
	 *
	 * @var object
	 */
	private object $object;

	/**
	 * Constructor.
	 *
	 * @param object $error
	 */
	public function __construct( object $object ) {
		$this->object = $object;
	}

	/**
	 * Get the error message.
	 *
	 * @return string
	 */
	public function get_message(): string {
		return $this->object->message ?? '';
	}
}
