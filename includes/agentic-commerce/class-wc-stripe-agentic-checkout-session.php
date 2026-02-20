<?php
/**
 * Class WC_Stripe_Agentic_Checkout_Session
 *
 * Typed wrapper around the raw Stripe checkout session object.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed access to Stripe checkout session data.
 *
 * The Stripe API returns untyped objects from json_decode(). This class
 * wraps that raw object and provides small, testable getter methods with
 * proper return types and fallback logic.
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Checkout_Session {

	/**
	 * The raw Stripe checkout session object.
	 *
	 * @var object
	 */
	private object $session;

	/**
	 * Constructor.
	 *
	 * @since 10.5.0
	 * @param object $session The raw Stripe checkout session object.
	 */
	public function __construct( object $session ) {
		$this->session = $session;
	}

	/**
	 * Returns the fields to expand when retrieving the checkout session.
	 *
	 * @since 10.5.0
	 * @return array The fields to expand.
	 */
	public static function get_fields_to_expand(): array {
		return [
			'line_items.data.price.product',
		];
	}

	/**
	 * Returns the checkout session ID.
	 *
	 * @since 10.5.0
	 * @return string
	 */
	public function get_id(): string {
		return (string) ( $this->session->id ?? '' );
	}

	/**
	 * Returns the session currency in uppercase.
	 *
	 * @since 10.5.0
	 * @return string
	 */
	public function get_currency(): string {
		return strtoupper( (string) ( $this->session->currency ?? '' ) );
	}

	/**
	 * Returns the session currency in lowercase (for Stripe metadata storage).
	 *
	 * @since 10.5.0
	 * @return string
	 */
	public function get_currency_lowercase(): string {
		return strtolower( (string) ( $this->session->currency ?? '' ) );
	}

	/**
	 * Returns the total amount in the smallest currency unit.
	 *
	 * @since 10.5.0
	 * @return int
	 */
	public function get_amount_total(): int {
		return (int) ( $this->session->amount_total ?? 0 );
	}

	/**
	 * Returns the customer email, falling back from customer_details to customer_email.
	 *
	 * @since 10.5.0
	 * @return string
	 */
	public function get_customer_email(): string {
		return (string) (
			$this->session->customer_details->email
			?? $this->session->customer_email
			?? ''
		);
	}

	/**
	 * Returns the customer name, falling back to the shipping name.
	 *
	 * @since 10.5.0
	 * @return string
	 */
	public function get_customer_name(): string {
		return (string) (
			$this->session->customer_details->name
			?? $this->get_shipping_name()
		);
	}

	/**
	 * Returns the billing phone number.
	 *
	 * @since 10.5.0
	 * @return string
	 */
	public function get_billing_phone(): string {
		return (string) ( $this->session->customer_details->phone ?? '' );
	}

	/**
	 * Returns the billing address object.
	 *
	 * @since 10.5.0
	 * @return object|null
	 */
	public function get_billing_address(): ?object {
		return $this->session->customer_details->address ?? null;
	}

	/**
	 * Returns the resolved shipping details.
	 *
	 * Falls back from top-level shipping_details to
	 * collected_information.shipping_details.
	 *
	 * @since 10.5.0
	 * @return object|null
	 */
	public function get_shipping_details(): ?object {
		return $this->session->shipping_details
			?? $this->session->collected_information->shipping_details
			?? null;
	}

	/**
	 * Returns the shipping recipient name.
	 *
	 * @since 10.5.0
	 * @return string
	 */
	public function get_shipping_name(): string {
		return (string) ( $this->get_shipping_details()->name ?? '' );
	}

	/**
	 * Returns the shipping phone, falling back to the billing phone.
	 *
	 * @since 10.5.0
	 * @return string
	 */
	public function get_shipping_phone(): string {
		return (string) ( $this->get_shipping_details()->phone ?? $this->get_billing_phone() );
	}

	/**
	 * Returns the shipping address object.
	 *
	 * @since 10.5.0
	 * @return object|null
	 */
	public function get_shipping_address(): ?object {
		return $this->get_shipping_details()->address ?? null;
	}

	/**
	 * Returns the line items array.
	 *
	 * @since 10.5.0
	 * @return array
	 */
	public function get_line_items(): array {
		return $this->session->line_items->data ?? [];
	}

	/**
	 * Returns the expanded payment intent ID.
	 *
	 * @since 10.5.0
	 * @return string
	 */
	public function get_payment_intent_id(): string {
		return (string) ( $this->session->payment_intent->id ?? '' );
	}

	/**
	 * Returns the Stripe customer ID.
	 *
	 * @since 10.5.0
	 * @return string
	 */
	public function get_customer_id(): string {
		$customer = $this->session->customer ?? '';
		return is_string( $customer ) ? $customer : '';
	}

	/**
	 * Returns the shipping amount in the smallest currency unit.
	 *
	 * @since 10.5.0
	 * @return int
	 */
	public function get_shipping_amount(): int {
		return (int) ( $this->session->total_details->amount_shipping ?? 0 );
	}

	/**
	 * Checks whether this checkout session originates from agentic commerce.
	 *
	 * A session is agentic when at least one line item has an
	 * external_reference that resolves to a nonzero integer (product ID).
	 *
	 * @since 10.5.0
	 * @return bool
	 */
	public function is_agentic(): bool {
		foreach ( $this->get_line_items() as $line_item ) {
			if (
				isset( $line_item->price )
				&& is_object( $line_item->price )
				&& property_exists( $line_item->price, 'external_reference' )
				&& intval( $line_item->price->external_reference )
			) {
				return true;
			}
		}

		return false;
	}
}
