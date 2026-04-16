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
 * Wraps either a `\Stripe\Charge` (SDK) or a `stdClass` (legacy `json_decode`)
 * and exposes domain getters, status predicates, decimal-aware amount conversion,
 * and fallback chains that would otherwise be repeated at every call site.
 *
 * @since 10.7.0
 */
class WC_Stripe_Charge {

	/**
	 * The underlying Charge payload.
	 *
	 * @var \Stripe\StripeObject|stdClass
	 */
	private object $charge;

	/**
	 * Constructor.
	 *
	 * @since 10.7.0
	 * @param \Stripe\StripeObject|stdClass $charge The Stripe charge payload (e.g. \Stripe\Charge or a nested SDK object).
	 * @throws InvalidArgumentException When $charge is not a stdClass or \Stripe\StripeObject instance.
	 */
	public function __construct( object $charge ) {
		if ( ! $charge instanceof stdClass && ! $charge instanceof \Stripe\StripeObject ) {
			throw new InvalidArgumentException(
				sprintf( 'Expected stdClass or \Stripe\StripeObject, got %s.', get_class( $charge ) )
			);
		}

		$this->charge = $charge;
	}

	/**
	 * Returns the underlying raw object for legacy callers that still need arbitrary field access.
	 *
	 * Prefer named getters; this is an escape hatch for incremental migration.
	 *
	 * @since 10.7.0
	 * @return \Stripe\StripeObject|stdClass
	 */
	public function raw(): object {
		return $this->charge;
	}

	/**
	 * Returns the charge ID.
	 *
	 * @since 10.7.0
	 * @return string|null
	 */
	public function get_id(): ?string {
		return isset( $this->charge->id ) ? (string) $this->charge->id : null;
	}

	/**
	 * Returns the charge status (`succeeded`, `pending`, `failed`).
	 *
	 * @since 10.7.0
	 * @return string|null
	 */
	public function get_status(): ?string {
		return isset( $this->charge->status ) ? (string) $this->charge->status : null;
	}

	/**
	 * Whether the charge succeeded.
	 *
	 * @since 10.7.0
	 * @return bool
	 */
	public function is_succeeded(): bool {
		return 'succeeded' === $this->get_status();
	}

	/**
	 * Whether the charge has been captured.
	 *
	 * @since 10.7.0
	 * @return bool
	 */
	public function is_captured(): bool {
		return ! empty( $this->charge->captured );
	}

	/**
	 * Whether the charge has been fully refunded (the `refunded` flag).
	 *
	 * @since 10.7.0
	 * @return bool
	 */
	public function is_refunded(): bool {
		return ! empty( $this->charge->refunded );
	}

	/**
	 * Whether Stripe Radar has blocked the charge.
	 *
	 * @since 10.7.0
	 * @return bool
	 */
	public function is_blocked_by_radar(): bool {
		return isset( $this->charge->outcome->type ) && 'blocked' === $this->charge->outcome->type;
	}

	/**
	 * The reason Radar blocked the charge, or null when none.
	 *
	 * @since 10.7.0
	 * @return string|null
	 */
	public function get_radar_block_reason(): ?string {
		return isset( $this->charge->outcome->reason ) ? (string) $this->charge->outcome->reason : null;
	}

	/**
	 * Returns the currency (lowercase, Stripe's canonical form), or null.
	 *
	 * @since 10.7.0
	 * @return string|null
	 */
	public function get_currency(): ?string {
		return isset( $this->charge->currency ) ? strtolower( (string) $this->charge->currency ) : null;
	}

	/**
	 * Returns the raw amount in the smallest currency unit, or null.
	 *
	 * @since 10.7.0
	 * @return int|null
	 */
	public function get_amount(): ?int {
		return isset( $this->charge->amount ) ? (int) $this->charge->amount : null;
	}

	/**
	 * Returns the raw amount captured in the smallest currency unit, or null.
	 *
	 * @since 10.7.0
	 * @return int|null
	 */
	public function get_amount_captured(): ?int {
		return isset( $this->charge->amount_captured ) ? (int) $this->charge->amount_captured : null;
	}

	/**
	 * Returns the raw amount refunded in the smallest currency unit, or null.
	 *
	 * @since 10.7.0
	 * @return int|null
	 */
	public function get_amount_refunded(): ?int {
		return isset( $this->charge->amount_refunded ) ? (int) $this->charge->amount_refunded : null;
	}

	/**
	 * Returns the charge amount in presentation units (e.g. dollars, not cents).
	 *
	 * Decimal-currency-aware: zero-decimal currencies (JPY, KRW, etc.) are not divided.
	 *
	 * @since 10.7.0
	 * @return float|null
	 */
	public function get_amount_decimal(): ?float {
		return $this->to_decimal_amount( $this->get_amount() );
	}

	/**
	 * Returns the captured amount in presentation units, decimal-aware.
	 *
	 * @since 10.7.0
	 * @return float|null
	 */
	public function get_amount_captured_decimal(): ?float {
		return $this->to_decimal_amount( $this->get_amount_captured() );
	}

	/**
	 * Returns the refunded amount in presentation units, decimal-aware.
	 *
	 * @since 10.7.0
	 * @return float|null
	 */
	public function get_amount_refunded_decimal(): ?float {
		return $this->to_decimal_amount( $this->get_amount_refunded() );
	}

	/**
	 * Returns the Stripe customer ID, handling expanded and string forms.
	 *
	 * @since 10.7.0
	 * @return string|null
	 */
	public function get_customer_id(): ?string {
		return $this->get_id_or_expanded_id( $this->charge->customer ?? null );
	}

	/**
	 * Returns the Stripe payment intent ID, handling expanded and string forms.
	 *
	 * @since 10.7.0
	 * @return string|null
	 */
	public function get_payment_intent_id(): ?string {
		return $this->get_id_or_expanded_id( $this->charge->payment_intent ?? null );
	}

	/**
	 * Returns the Stripe payment method ID, handling expanded and string forms.
	 *
	 * @since 10.7.0
	 * @return string|null
	 */
	public function get_payment_method_id(): ?string {
		return $this->get_id_or_expanded_id( $this->charge->payment_method ?? null );
	}

	/**
	 * Returns the balance transaction ID, handling expanded and string forms.
	 *
	 * @since 10.7.0
	 * @return string|null
	 */
	public function get_balance_transaction_id(): ?string {
		return $this->get_id_or_expanded_id( $this->charge->balance_transaction ?? null );
	}

	/**
	 * Returns the mandate ID from payment method details.
	 *
	 * Checks the `card` and `acss_debit` sub-objects, which are the two methods
	 * where Stripe exposes a mandate on the Charge. Returns null when absent.
	 *
	 * @since 10.7.0
	 * @return string|null
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
	 * @return int|null
	 */
	public function get_order_id_from_metadata(): ?int {
		$order_id = $this->charge->metadata->order_id ?? null;
		return null === $order_id ? null : absint( $order_id );
	}

	/**
	 * Returns the receipt URL, or null when absent.
	 *
	 * @since 10.7.0
	 * @return string|null
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
	 * @return object|null
	 */
	public function get_latest_refund(): ?object {
		$data = $this->charge->refunds->data ?? [];
		if ( ! is_array( $data ) || empty( $data ) ) {
			return null;
		}

		$last = end( $data );
		return is_object( $last ) ? $last : null;
	}

	/**
	 * Returns the first refund object, or null.
	 *
	 * Used by webhook handlers that treat the first refund as the triggering event.
	 *
	 * @since 10.7.0
	 * @return object|null
	 */
	public function get_first_refund(): ?object {
		$first = $this->charge->refunds->data[0] ?? null;
		return is_object( $first ) ? $first : null;
	}

	/**
	 * Converts a smallest-unit amount to presentation units, respecting zero-decimal currencies.
	 *
	 * @param int|null $amount Smallest-unit amount (e.g. cents) or null.
	 * @return float|null
	 */
	private function to_decimal_amount( ?int $amount ): ?float {
		if ( null === $amount ) {
			return null;
		}

		$currency = $this->get_currency();
		if ( null !== $currency && in_array( $currency, WC_Stripe_Helper::no_decimal_currencies(), true ) ) {
			return (float) $amount;
		}

		return $amount / 100;
	}

	/**
	 * Resolves an expanded-or-string reference to its ID.
	 *
	 * @param mixed $value The raw value (string ID, object with ->id, or null).
	 * @return string|null
	 */
	private function get_id_or_expanded_id( $value ): ?string {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
		if ( is_object( $value ) && isset( $value->id ) ) {
			return (string) $value->id;
		}
		return null;
	}
}
