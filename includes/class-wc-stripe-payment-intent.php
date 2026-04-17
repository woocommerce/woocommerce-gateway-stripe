<?php
/**
 * Class WC_Stripe_Payment_Intent
 *
 * Typed domain wrapper around a Stripe PaymentIntent object.
 *
 * @package WooCommerce_Stripe
 * @since   10.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed, null-safe access to Stripe PaymentIntent data.
 *
 * Wraps the raw `stdClass` returned by `json_decode()` on Stripe API responses
 * and consolidates the status predicates, error introspection, payment-method
 * fallback chains, and latest-charge resolution that were previously repeated
 * across the intent controller, the payment gateway, the webhook handler, and
 * the subscriptions/pre-orders compat traits.
 *
 * @since 10.7.0
 */
class WC_Stripe_Payment_Intent {

	/**
	 * The underlying PaymentIntent payload.
	 *
	 * @var stdClass
	 */
	private stdClass $intent;

	/**
	 * Constructor.
	 *
	 * @since 10.7.0
	 * @param stdClass $intent The Stripe payment intent payload, as returned by `json_decode`.
	 */
	public function __construct( stdClass $intent ) {
		$this->intent = $intent;
	}

	/**
	 * Returns the underlying raw object.
	 *
	 * @since 10.7.0
	 */
	public function raw(): stdClass {
		return $this->intent;
	}

	public function get_id(): ?string {
		return isset( $this->intent->id ) ? (string) $this->intent->id : null;
	}

	public function get_status(): ?string {
		return isset( $this->intent->status ) ? (string) $this->intent->status : null;
	}

	public function is_succeeded(): bool {
		return WC_Stripe_Intent_Status::SUCCEEDED === $this->get_status();
	}

	public function is_processing(): bool {
		return WC_Stripe_Intent_Status::PROCESSING === $this->get_status();
	}

	public function is_requires_action(): bool {
		return WC_Stripe_Intent_Status::REQUIRES_ACTION === $this->get_status();
	}

	public function is_requires_capture(): bool {
		return WC_Stripe_Intent_Status::REQUIRES_CAPTURE === $this->get_status();
	}

	public function is_requires_confirmation(): bool {
		return WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION === $this->get_status();
	}

	public function is_requires_payment_method(): bool {
		return WC_Stripe_Intent_Status::REQUIRES_PAYMENT_METHOD === $this->get_status();
	}

	public function is_canceled(): bool {
		return WC_Stripe_Intent_Status::CANCELED === $this->get_status();
	}

	/**
	 * Whether the intent's status is in the successful set (SUCCEEDED, REQUIRES_CAPTURE, PROCESSING).
	 *
	 * @since 10.7.0
	 */
	public function is_successful_status(): bool {
		$status = $this->get_status();
		return null !== $status && in_array( $status, WC_Stripe_Intent_Status::SUCCESSFUL_STATUSES, true );
	}

	/**
	 * Whether the status is REQUIRES_CONFIRMATION or REQUIRES_ACTION (subscriptions trait uses this).
	 *
	 * @since 10.7.0
	 */
	public function is_requires_confirmation_or_action(): bool {
		$status = $this->get_status();
		return null !== $status && in_array( $status, WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION_OR_ACTION_STATUSES, true );
	}

	public function get_client_secret(): ?string {
		return isset( $this->intent->client_secret ) ? (string) $this->intent->client_secret : null;
	}

	public function get_customer_id(): ?string {
		return $this->resolve_id( $this->intent->customer ?? null );
	}

	public function get_payment_method_id(): ?string {
		return $this->resolve_id( $this->intent->payment_method ?? null );
	}

	/**
	 * Returns the list of allowed payment method types on the intent.
	 *
	 * @since 10.7.0
	 * @return string[]
	 */
	public function get_payment_method_types(): array {
		$types = $this->intent->payment_method_types ?? [];
		if ( is_object( $types ) ) {
			$types = (array) $types;
		}
		return is_array( $types ) ? array_values( array_map( 'strval', $types ) ) : [];
	}

	public function get_currency(): ?string {
		return isset( $this->intent->currency ) ? strtoupper( (string) $this->intent->currency ) : null;
	}

	public function get_amount(): ?int {
		return isset( $this->intent->amount ) ? (int) $this->intent->amount : null;
	}

	public function get_order_id_from_metadata(): ?int {
		$order_id = $this->intent->metadata->order_id ?? null;
		return null === $order_id ? null : absint( $order_id );
	}

	public function get_metadata_value( string $key ): ?string {
		$value = $this->intent->metadata->$key ?? null;
		return null === $value ? null : (string) $value;
	}

	/**
	 * Whether the intent recorded a payment error on the last attempt.
	 *
	 * @since 10.7.0
	 */
	public function has_payment_error(): bool {
		return ! empty( $this->intent->last_payment_error );
	}

	public function get_payment_error_code(): ?string {
		$code = $this->intent->last_payment_error->code ?? null;
		return null === $code ? null : (string) $code;
	}

	public function get_payment_error_message(): ?string {
		$message = $this->intent->last_payment_error->message ?? null;
		return null === $message ? null : (string) $message;
	}

	/**
	 * Whether the last payment error was an `authentication_required` failure
	 * (the canonical trigger for re-confirmation in SCA flows).
	 *
	 * @since 10.7.0
	 */
	public function is_authentication_required_error(): bool {
		return 'authentication_required' === $this->get_payment_error_code();
	}

	/**
	 * Returns the payment-method-or-source ID associated with the last payment error.
	 *
	 * Prefers `last_payment_error.payment_method.id`; falls back to
	 * `last_payment_error.source.id` for backwards compatibility.
	 *
	 * @since 10.7.0
	 */
	public function get_payment_error_source_id(): ?string {
		$last_error = $this->intent->last_payment_error ?? null;
		if ( null === $last_error ) {
			return null;
		}

		$pm_id = $last_error->payment_method->id ?? null;
		if ( ! empty( $pm_id ) ) {
			return (string) $pm_id;
		}

		$source_id = $last_error->source->id ?? null;
		if ( ! empty( $source_id ) ) {
			return (string) $source_id;
		}

		return null;
	}

	/**
	 * Returns the latest charge ID associated with the intent, handling both the
	 * pre-2022-11-15 `charges.data` form and the current `latest_charge` field
	 * (string or expanded object).
	 *
	 * @since 10.7.0
	 */
	public function get_latest_charge_id(): ?string {
		$data = $this->intent->charges->data ?? null;
		if ( is_array( $data ) && ! empty( $data ) ) {
			$last = end( $data );
			if ( is_object( $last ) && isset( $last->id ) ) {
				return (string) $last->id;
			}
		}

		return $this->resolve_id( $this->intent->latest_charge ?? null );
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
