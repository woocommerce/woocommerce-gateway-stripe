<?php
/**
 * Class WC_Stripe_Charge
 *
 * Typed domain wrapper around a Stripe Charge object.
 *
 * @package WooCommerce_Stripe
 * @since   10.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed, null-safe access to Stripe Charge data.
 *
 * Wraps the raw `stdClass` returned by `json_decode()` on Stripe API responses
 * and consolidates the fallback chains and normalization logic that were
 * previously repeated at every call site (gateway, webhook handler,
 * order handler, subscriptions trait).
 *
 * @since 10.7.0
 */
class WC_Stripe_Charge {

	/**
	 * The underlying Charge payload.
	 *
	 * @var stdClass
	 */
	private stdClass $charge;

	/**
	 * Constructor.
	 *
	 * @since 10.7.0
	 * @param stdClass $charge The Stripe charge payload, as returned by `json_decode`.
	 */
	public function __construct( stdClass $charge ) {
		$this->charge = $charge;
	}

	/**
	 * Returns the underlying raw object for legacy callers that still need arbitrary field access.
	 *
	 * @since 10.7.0
	 */
	public function raw(): stdClass {
		return $this->charge;
	}

	public function get_id(): ?string {
		return isset( $this->charge->id ) ? (string) $this->charge->id : null;
	}

	public function get_status(): ?string {
		return isset( $this->charge->status ) ? (string) $this->charge->status : null;
	}

	public function is_succeeded(): bool {
		return 'succeeded' === $this->get_status();
	}

	public function is_captured(): bool {
		return ! empty( $this->charge->captured );
	}

	public function is_refunded(): bool {
		return ! empty( $this->charge->refunded );
	}

	/**
	 * Whether Stripe Radar has blocked the charge.
	 *
	 * Used by the subscription compat trait to detect renewals that were stopped
	 * by Radar rules (so the store can choose a friendlier order note).
	 *
	 * @since 10.7.0
	 */
	public function is_blocked_by_radar(): bool {
		return isset( $this->charge->outcome->type ) && 'blocked' === $this->charge->outcome->type;
	}

	public function get_radar_block_reason(): ?string {
		return isset( $this->charge->outcome->reason ) ? (string) $this->charge->outcome->reason : null;
	}

	/**
	 * Returns the currency in uppercase (ISO-4217), or null.
	 *
	 * @since 10.7.0
	 */
	public function get_currency(): ?string {
		return isset( $this->charge->currency ) ? strtoupper( (string) $this->charge->currency ) : null;
	}

	public function get_amount(): ?int {
		return isset( $this->charge->amount ) ? (int) $this->charge->amount : null;
	}

	public function get_amount_captured(): ?int {
		return isset( $this->charge->amount_captured ) ? (int) $this->charge->amount_captured : null;
	}

	public function get_amount_refunded(): ?int {
		return isset( $this->charge->amount_refunded ) ? (int) $this->charge->amount_refunded : null;
	}

	/**
	 * Returns the Stripe customer ID, handling expanded and string forms.
	 *
	 * @since 10.7.0
	 */
	public function get_customer_id(): ?string {
		return $this->resolve_id( $this->charge->customer ?? null );
	}

	/**
	 * Returns the payment intent ID, handling expanded and string forms.
	 *
	 * @since 10.7.0
	 */
	public function get_payment_intent_id(): ?string {
		return $this->resolve_id( $this->charge->payment_intent ?? null );
	}

	/**
	 * Returns the payment method ID, handling expanded and string forms.
	 *
	 * @since 10.7.0
	 */
	public function get_payment_method_id(): ?string {
		return $this->resolve_id( $this->charge->payment_method ?? null );
	}

	/**
	 * Returns the balance transaction ID, handling expanded and string forms.
	 *
	 * @since 10.7.0
	 */
	public function get_balance_transaction_id(): ?string {
		return $this->resolve_id( $this->charge->balance_transaction ?? null );
	}

	/**
	 * Returns the mandate ID from payment_method_details.
	 *
	 * Checks the `card` and `acss_debit` sub-objects, which are the two methods
	 * where Stripe exposes a mandate on the Charge. Returns null when absent.
	 *
	 * Used by the gateway save_intent_to_order flow to store the mandate ID on
	 * the order for subsequent renewal payments (Indian cards, ACSS).
	 *
	 * @since 10.7.0
	 */
	public function get_mandate_id(): ?string {
		$card_mandate = $this->charge->payment_method_details->card->mandate ?? null;
		if ( ! empty( $card_mandate ) ) {
			return (string) $card_mandate;
		}

		$acss_mandate = $this->charge->payment_method_details->acss_debit->mandate ?? null;
		if ( ! empty( $acss_mandate ) ) {
			return (string) $acss_mandate;
		}

		return null;
	}

	/**
	 * Returns the WooCommerce order ID stored in charge metadata, or null.
	 *
	 * @since 10.7.0
	 */
	public function get_order_id_from_metadata(): ?int {
		$order_id = $this->charge->metadata->order_id ?? null;
		return null === $order_id ? null : absint( $order_id );
	}

	/**
	 * Returns the receipt URL, or null when absent.
	 *
	 * @since 10.7.0
	 */
	public function get_receipt_url(): ?string {
		return isset( $this->charge->receipt_url ) ? (string) $this->charge->receipt_url : null;
	}

	/**
	 * Returns the most-recent refund object (last entry in refunds.data), or null.
	 *
	 * Requires the charge to be retrieved with `expand[]=refunds`.
	 *
	 * @since 10.7.0
	 */
	public function get_latest_refund(): ?object {
		$data = $this->charge->refunds->data ?? null;
		if ( ! is_array( $data ) || empty( $data ) ) {
			return null;
		}

		$last = end( $data );
		return is_object( $last ) ? $last : null;
	}

	/**
	 * Returns the first refund object, or null.
	 *
	 * @since 10.7.0
	 */
	public function get_first_refund(): ?object {
		$first = $this->charge->refunds->data[0] ?? null;
		return is_object( $first ) ? $first : null;
	}

	/**
	 * Resolves an expanded-or-string reference to its ID.
	 *
	 * @param mixed $value The raw value (string ID, object with ->id, or null).
	 */
	private function resolve_id( $value ): ?string {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
		if ( is_object( $value ) && isset( $value->id ) ) {
			return (string) $value->id;
		}
		return null;
	}
}
