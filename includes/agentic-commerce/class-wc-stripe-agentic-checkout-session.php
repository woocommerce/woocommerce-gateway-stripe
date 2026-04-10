<?php
/**
 * Class WC_Stripe_Agentic_Checkout_Session
 *
 * Typed wrapper around the raw Stripe checkout session object.
 *
 * @package WooCommerce_Stripe/Agentic_Commerce
 * @since   10.6.0
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
 * @since 10.6.0
 */
class WC_Stripe_Agentic_Checkout_Session {

	/**
	 * The raw Stripe checkout session object.
	 *
	 * @var stdClass
	 */
	private stdClass $session;

	/**
	 * Constructor.
	 *
	 * @since 10.6.0
	 * @param stdClass $session The raw Stripe checkout session object.
	 */
	public function __construct( stdClass $session ) {
		$this->session = $session;
	}

	/**
	 * Returns the fields to expand when retrieving the checkout session.
	 *
	 * @since 10.6.0
	 * @return array The fields to expand.
	 */
	public static function get_fields_to_expand(): array {
		return [
			'line_items.data.price.product',
			'shipping_cost.shipping_rate',
		];
	}

	/**
	 * Returns the checkout session ID, or null when absent.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_id(): ?string {
		return isset( $this->session->id ) ? (string) $this->session->id : null;
	}

	/**
	 * Returns the session currency in uppercase, or null when absent.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_currency(): ?string {
		return isset( $this->session->currency ) ? strtoupper( (string) $this->session->currency ) : null;
	}

	/**
	 * Returns the session currency in lowercase (for Stripe metadata storage), or null when absent.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_currency_lowercase(): ?string {
		return isset( $this->session->currency ) ? strtolower( (string) $this->session->currency ) : null;
	}

	/**
	 * Returns the total amount in the smallest currency unit, or null when absent.
	 *
	 * @since 10.6.0
	 * @return int|null
	 */
	public function get_amount_total(): ?int {
		return isset( $this->session->amount_total ) ? (int) $this->session->amount_total : null;
	}

	/**
	 * Returns the customer email, falling back from customer_details to customer_email.
	 * Returns null when neither source is present.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_customer_email(): ?string {
		$customer_details = $this->session->customer_details ?? null;
		$email            = $customer_details->email ?? $this->session->customer_email ?? null;
		return null !== $email ? (string) $email : null;
	}

	/**
	 * Returns the customer name, falling back to the shipping name.
	 * Returns null when all sources are absent.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_customer_name(): ?string {
		$customer_details = $this->session->customer_details ?? null;
		$name             = $customer_details->name ?? $this->get_shipping_name();
		return null !== $name ? (string) $name : null;
	}

	/**
	 * Returns the billing phone number, or null when absent.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_billing_phone(): ?string {
		return isset( $this->session->customer_details->phone ) ? (string) $this->session->customer_details->phone : null;
	}

	/**
	 * Returns the billing address object.
	 *
	 * @since 10.6.0
	 * @return WC_Stripe_API_Address
	 */
	public function get_billing_address(): WC_Stripe_API_Address {
		$address = $this->session->customer_details->address ?? null;
		if ( null === $address ) {
			throw new Exception(
				sprintf(
					'Checkout session %s has no billing address.',
					$this->get_id()
				)
			);
		}
		return new WC_Stripe_API_Address( $address );
	}

	/**
	 * Returns the resolved shipping details.
	 *
	 * Falls back from top-level shipping_details to
	 * collected_information.shipping_details.
	 *
	 * @since 10.6.0
	 * @return object|null
	 */
	public function get_shipping_details(): ?object {
		return $this->session->shipping_details
			?? $this->session->collected_information->shipping_details
			?? null;
	}

	/**
	 * Returns the shipping recipient name, or null when absent.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_shipping_name(): ?string {
		$details = $this->get_shipping_details();
		return isset( $details->name ) ? (string) $details->name : null;
	}

	/**
	 * Returns the shipping phone, falling back to the billing phone.
	 * Returns null when both are absent.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_shipping_phone(): ?string {
		$details = $this->get_shipping_details();
		if ( isset( $details->phone ) ) {
			return (string) $details->phone;
		}
		return $this->get_billing_phone();
	}

	/**
	 * Returns the shipping address object.
	 *
	 * @since 10.6.0
	 * @return WC_Stripe_API_Address|null
	 */
	public function get_shipping_address(): ?WC_Stripe_API_Address {
		$details = $this->get_shipping_details();
		$address = $details->address ?? null;
		if ( null === $address ) {
			return null;
		}
		return new WC_Stripe_API_Address( $address );
	}

	/**
	 * Returns the line items array.
	 *
	 * @since 10.6.0
	 * @return WC_Stripe_Agentic_Line_Item[]
	 */
	public function get_line_items(): array {
		$raw_items = $this->session->line_items->data ?? [];

		return array_map(
			function ( $item ) {
				return new WC_Stripe_Agentic_Line_Item( $item );
			},
			$raw_items
		);
	}

	/**
	 * Returns the expanded payment intent ID, or null when absent.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_payment_intent_id(): ?string {
		return isset( $this->session->payment_intent->id ) ? (string) $this->session->payment_intent->id : null;
	}

	/**
	 * Returns the Stripe customer ID, or null when absent.
	 *
	 * Handles both a plain string customer ID and an expanded customer object.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_customer_id(): ?string {
		$customer = $this->session->customer ?? null;
		if ( is_string( $customer ) ) {
			return $customer;
		}
		if ( is_object( $customer ) && isset( $customer->id ) ) {
			return (string) $customer->id;
		}
		return null;
	}

	/**
	 * Returns the WooCommerce rate ID from the chosen Stripe shipping rate metadata.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_chosen_shipping_rate_wc_id(): ?string {
		$rate_id = $this->session->shipping_cost->shipping_rate->metadata->wc_rate_id ?? null;
		return is_string( $rate_id ) && '' !== $rate_id ? $rate_id : null;
	}

	/**
	 * Returns the display name of the chosen Stripe shipping rate.
	 *
	 * @since 10.6.0
	 * @return string|null
	 */
	public function get_chosen_shipping_rate_display_name(): ?string {
		$name = $this->session->shipping_cost->shipping_rate->display_name ?? null;
		return is_string( $name ) && '' !== $name ? $name : null;
	}

	/**
	 * Returns the shipping amount in the smallest currency unit, or null when absent.
	 *
	 * @since 10.6.0
	 * @return int|null
	 */
	public function get_shipping_amount(): ?int {
		return isset( $this->session->total_details->amount_shipping ) ? (int) $this->session->total_details->amount_shipping : null;
	}

	/**
	 * Checks whether this checkout session originates from agentic commerce.
	 *
	 * A session is agentic when at least one line item has an
	 * external_reference that resolves to a nonzero integer (product ID).
	 *
	 * @since 10.6.0
	 * @return bool
	 */
	public function is_agentic(): bool {
		foreach ( $this->get_line_items() as $line_item ) {
			if ( $line_item->has_product_id() ) {
				return true;
			}
		}

		return false;
	}
}
