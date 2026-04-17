<?php
/**
 * Class WC_Stripe_Setup_Intent
 *
 * Typed domain wrapper around a Stripe SetupIntent object.
 *
 * @package WooCommerce_Stripe
 * @since   10.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed, null-safe access to Stripe SetupIntent data.
 *
 * Wraps the raw `stdClass` returned by `json_decode()` on Stripe API responses
 * and exposes the status predicates, client-secret/customer/payment-method
 * accessors, and mandate extraction needed by the intent controller and the
 * save_intent_to_order flow.
 *
 * @since 10.7.0
 */
class WC_Stripe_Setup_Intent {

	/**
	 * The underlying SetupIntent payload.
	 *
	 * @var stdClass
	 */
	private stdClass $intent;

	/**
	 * Constructor.
	 *
	 * @since 10.7.0
	 * @param stdClass $intent The Stripe setup intent payload, as returned by `json_decode`.
	 */
	public function __construct( stdClass $intent ) {
		$this->intent = $intent;
	}

	/**
	 * Returns the underlying raw object for legacy callers.
	 *
	 * @since 10.7.0
	 */
	public function raw(): stdClass {
		return $this->intent;
	}

	/**
	 * Returns the setup intent ID, or null when absent.
	 *
	 * @since 10.7.0
	 */
	public function get_id(): ?string {
		return isset( $this->intent->id ) ? (string) $this->intent->id : null;
	}

	/**
	 * Returns the setup intent status, or null when absent.
	 *
	 * @since 10.7.0
	 */
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
	 * Whether this SetupIntent is in a status considered successful for future off-session use.
	 *
	 * Matches `WC_Stripe_Intent_Status::SUCCESSFUL_SETUP_INTENT_STATUSES`, which includes
	 * REQUIRES_ACTION / REQUIRES_CONFIRMATION (the front-end finishes those) in addition to
	 * the terminal SUCCEEDED / PROCESSING values.
	 *
	 * @since 10.7.0
	 */
	public function is_successful_for_setup(): bool {
		$status = $this->get_status();
		return null !== $status && in_array( $status, WC_Stripe_Intent_Status::SUCCESSFUL_SETUP_INTENT_STATUSES, true );
	}

	/**
	 * Returns the client secret (used by the front-end to finalize the intent).
	 *
	 * @since 10.7.0
	 */
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
	 * Returns the mandate ID associated with this SetupIntent.
	 *
	 * Used by the free-trial subscription flow to store a mandate for future
	 * off-session payments without a completing Charge to source it from.
	 *
	 * @since 10.7.0
	 */
	public function get_mandate_id(): ?string {
		return $this->resolve_id( $this->intent->mandate ?? null );
	}

	/**
	 * Returns the raw `next_action` object (e.g. 3DS redirect), or null when absent.
	 *
	 * @since 10.7.0
	 */
	public function get_next_action(): ?object {
		$next = $this->intent->next_action ?? null;
		return is_object( $next ) ? $next : null;
	}

	/**
	 * Whether the SetupIntent response carries a Stripe API `error` payload.
	 *
	 * @since 10.7.0
	 */
	public function has_error(): bool {
		return ! empty( $this->intent->error );
	}

	/**
	 * Returns the Stripe error message, or null when no error.
	 *
	 * @since 10.7.0
	 */
	public function get_error_message(): ?string {
		$message = $this->intent->error->message ?? null;
		return null === $message ? null : (string) $message;
	}

	/**
	 * Returns the raw Stripe error object for callers that need to introspect it
	 * (e.g. for logging), or null when no error.
	 *
	 * @since 10.7.0
	 */
	public function get_error_object(): ?object {
		$error = $this->intent->error ?? null;
		return is_object( $error ) ? $error : null;
	}

	public function get_order_id_from_metadata(): ?int {
		$order_id = $this->intent->metadata->order_id ?? null;
		return null === $order_id ? null : absint( $order_id );
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
