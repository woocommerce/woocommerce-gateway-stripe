<?php
/**
 * Class WC_Stripe_API_Address.
 *
 * Typed wrapper around raw Stripe address objects.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents an address, as returned by the Stripe API.
 *
 * @since 10.5.0
 */
class WC_Stripe_API_Address {
	private $address;

	public function __construct( $address ) {
		$this->address = $address;
	}

	public function get_country(): ?string {
		$country = $this->address->country;

		if ( ! empty( $country ) ) {
			return $country;
		}

		return null;
	}

	public function get_state(): ?string {
		$state = $this->address->state;

		if ( ! empty( $state ) ) {
			return $state;
		}

		return null;
	}

	public function get_postal_code(): ?string {
		$postal_code = $this->address->postal_code;

		if ( ! empty( $postal_code ) ) {
			return $postal_code;
		}

		return null;
	}

	public function get_city(): ?string {
		$city = $this->address->city;

		if ( ! empty( $city ) ) {
			return $city;
		}

		return null;
	}
}
