<?php
/**
 * Class WC_Stripe_Refund
 *
 * Typed domain wrapper around a Stripe Refund object.
 *
 * @package WooCommerce_Stripe
 * @since   10.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed, null-safe access to Stripe Refund data.
 *
 * Wraps the `stdClass` produced by `json_decode` of a Stripe Refund response and
 * consolidates the status predicates, decimal-aware amount conversion, and
 * expanded/string ID resolution that were previously repeated across the webhook
 * handler and order-handler refund paths.
 *
 * @since 10.8.0
 */
class WC_Stripe_Refund {

	/**
	 * The underlying Refund payload.
	 *
	 * @var stdClass
	 */
	private stdClass $refund;

	/**
	 * Constructor.
	 *
	 * @since 10.8.0
	 * @param stdClass $refund The Stripe refund payload (as returned by `json_decode`).
	 */
	public function __construct( stdClass $refund ) {
		$this->refund = $refund;
	}

	/**
	 * Returns the underlying raw object for legacy callers that still need arbitrary field access.
	 *
	 * Prefer named getters; this is an escape hatch for incremental migration.
	 *
	 * @since 10.8.0
	 */
	public function raw(): stdClass {
		return $this->refund;
	}

	/**
	 * Returns the refund ID, or null when absent.
	 *
	 * @since 10.8.0
	 */
	public function get_id(): ?string {
		return isset( $this->refund->id ) ? (string) $this->refund->id : null;
	}

	/**
	 * Returns the Stripe refund status (e.g. `succeeded`, `pending`, `failed`, `canceled`).
	 *
	 * @since 10.8.0
	 */
	public function get_status(): ?string {
		return isset( $this->refund->status ) ? (string) $this->refund->status : null;
	}

	public function is_succeeded(): bool {
		return 'succeeded' === $this->get_status();
	}

	public function is_pending(): bool {
		return 'pending' === $this->get_status();
	}

	public function is_failed(): bool {
		return 'failed' === $this->get_status();
	}

	public function is_canceled(): bool {
		return 'canceled' === $this->get_status();
	}

	public function is_requires_action(): bool {
		return 'requires_action' === $this->get_status();
	}

	/**
	 * Whether the refund is in a terminal failure state.
	 *
	 * The webhook handler branches on this to update order refund state and
	 * surface a friendly failure reason; consolidating it here avoids
	 * `in_array( $status, [ 'failed', 'canceled' ], true )` duplication.
	 *
	 * @since 10.8.0
	 */
	public function is_failed_or_canceled(): bool {
		$status = $this->get_status();
		return null !== $status && in_array( $status, [ 'failed', 'canceled' ], true );
	}

	/**
	 * Returns the currency (lowercase, Stripe's canonical form), or null.
	 *
	 * @since 10.8.0
	 */
	public function get_currency(): ?string {
		return isset( $this->refund->currency ) ? strtolower( (string) $this->refund->currency ) : null;
	}

	/**
	 * Returns the raw amount in the smallest currency unit, or null.
	 *
	 * @since 10.8.0
	 */
	public function get_amount(): ?int {
		return isset( $this->refund->amount ) ? (int) $this->refund->amount : null;
	}

	/**
	 * Returns the refund amount in presentation units (e.g. dollars, not cents).
	 *
	 * Decimal-currency-aware: zero-decimal currencies (JPY, KRW, etc.) are not divided.
	 * An optional $currency_override is supported for webhook flows that have the
	 * charge's currency readily available but the refund payload may lack its own.
	 *
	 * @since 10.8.0
	 * @param string|null $currency_override Currency code to use instead of the refund's own.
	 * @return float|null
	 */
	public function get_amount_decimal( ?string $currency_override = null ): ?float {
		$amount = $this->get_amount();
		if ( null === $amount ) {
			return null;
		}

		$currency = null !== $currency_override ? strtolower( $currency_override ) : $this->get_currency();
		if ( null !== $currency && in_array( $currency, WC_Stripe_Helper::no_decimal_currencies(), true ) ) {
			return (float) $amount;
		}

		return $amount / 100;
	}

	/**
	 * Returns the charge ID associated with the refund, handling expanded and string forms.
	 *
	 * @since 10.8.0
	 */
	public function get_charge_id(): ?string {
		return $this->resolve_id( $this->refund->charge ?? null );
	}

	/**
	 * Returns the payment intent ID associated with the refund, handling expanded and string forms.
	 *
	 * @since 10.8.0
	 */
	public function get_payment_intent_id(): ?string {
		return $this->resolve_id( $this->refund->payment_intent ?? null );
	}

	/**
	 * Returns the successful balance transaction ID, handling expanded and string forms.
	 *
	 * @since 10.8.0
	 */
	public function get_balance_transaction_id(): ?string {
		return $this->resolve_id( $this->refund->balance_transaction ?? null );
	}

	/**
	 * Returns the failure balance transaction ID (present when a refund fails after
	 * being issued), handling expanded and string forms.
	 *
	 * @since 10.8.0
	 */
	public function get_failure_balance_transaction_id(): ?string {
		return $this->resolve_id( $this->refund->failure_balance_transaction ?? null );
	}

	/**
	 * Returns the Stripe-supplied `reason` for the refund
	 * (e.g. `requested_by_customer`, `duplicate`, `fraudulent`), or null when absent.
	 *
	 * @since 10.8.0
	 */
	public function get_reason(): ?string {
		return isset( $this->refund->reason ) ? (string) $this->refund->reason : null;
	}

	/**
	 * Returns the Stripe-supplied `failure_reason` (only populated on failed refunds).
	 *
	 * @since 10.8.0
	 */
	public function get_failure_reason(): ?string {
		return isset( $this->refund->failure_reason ) ? (string) $this->refund->failure_reason : null;
	}

	/**
	 * Returns the end-user-friendly translation of `failure_reason` via
	 * `WC_Stripe_Helper::get_refund_reason_description()`, or null when no failure.
	 *
	 * @since 10.8.0
	 */
	public function get_friendly_failure_reason(): ?string {
		$reason = $this->get_failure_reason();
		if ( null === $reason ) {
			return null;
		}
		return WC_Stripe_Helper::get_refund_reason_description( $reason );
	}

	/**
	 * Returns the refund's receipt number, or null when absent.
	 *
	 * @since 10.8.0
	 */
	public function get_receipt_number(): ?string {
		return isset( $this->refund->receipt_number ) ? (string) $this->refund->receipt_number : null;
	}

	/**
	 * Returns an arbitrary metadata value by key, or null when absent.
	 *
	 * @since 10.8.0
	 */
	public function get_metadata_value( string $key ): ?string {
		$value = $this->refund->metadata->$key ?? null;
		return null === $value ? null : (string) $value;
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
