<?php

/**
 * Data transfer object representing a Stripe Checkout Session.
 */
class WC_Stripe_Checkout_Session extends WC_Stripe_DTO {
	/**
	 * Get the client secret from the Checkout Session.
	 *
	 * @return string|null
	 */
	public function get_client_secret(): ?string {
		return $this->object->client_secret ?? null;
	}
}
