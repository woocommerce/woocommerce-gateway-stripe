<?php
/**
 * Class WC_Stripe_API_Address.
 *
 * Typed wrapper around raw Stripe address objects.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents an address, as returned by the Stripe API.
 *
 * @since 10.6.0
 */
class WC_Stripe_API_Address {
	/**
	 * The raw Stripe address object.
	 *
	 * @var stdClass
	 */
	private stdClass $address;

	/**
	 * Constructor.
	 *
	 * @param object $address The raw Stripe address object.
	 * @throws \InvalidArgumentException If the address is not a stdClass instance.
	 */
	public function __construct( $address ) {
		if ( ! $address instanceof stdClass ) {
			throw new \InvalidArgumentException( 'Address must be a stdClass instance.' );
		}
		$this->address = $address;
	}

	/**
	 * Returns the country code.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_country(): ?string {
		return $this->sanitize_field( $this->address->country ?? null );
	}

	/**
	 * Returns the state or province.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_state(): ?string {
		return $this->sanitize_field( $this->address->state ?? null );
	}

	/**
	 * Returns the postal code.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_postal_code(): ?string {
		return $this->sanitize_field( $this->address->postal_code ?? null );
	}

	/**
	 * Returns the city.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_city(): ?string {
		return $this->sanitize_field( $this->address->city ?? null );
	}

	/**
	 * Returns address line 1.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_line1(): ?string {
		return $this->sanitize_field( $this->address->line1 ?? null );
	}

	/**
	 * Returns address line 2.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_line2(): ?string {
		return $this->sanitize_field( $this->address->line2 ?? null );
	}

	/**
	 * Sanitizes an address field value.
	 *
	 * @param mixed $value The raw value.
	 * @return string|null The sanitized string, or null if empty.
	 */
	private function sanitize_field( $value ): ?string {
		if ( empty( $value ) ) {
			return null;
		}

		return sanitize_text_field( (string) $value );
	}
}
