<?php

/**
 * Abstract data transfer object for Stripe API responses.
 */
abstract class WC_Stripe_DTO {
	/**
	 * The raw object returned by Stripe's API.
	 *
	 * @var object
	 */
	protected object $object;

	/**
	 * Constructor.
	 *
	 * @param object $object
	 */
	public function __construct( object $object ) {
		$this->object = $object;
	}

	/**
	 * Returns the error object if the API response contains an error, or null if there is no error.
	 *
	 * @return WC_Stripe_Error|null
	 */
	public function get_error(): ?WC_Stripe_Error {
		return $this->object->error ? new WC_Stripe_Error( $this->object->error ) : null;
	}
}
