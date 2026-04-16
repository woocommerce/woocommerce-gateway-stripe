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
 * Wraps either a `\Stripe\SetupIntent` (SDK) or a `stdClass` (legacy `json_decode`)
 * and exposes the status predicates, client-secret/customer/payment-method
 * accessors, and mandate extraction needed by the free-trial subscription flow.
 *
 * @since 10.7.0
 */
class WC_Stripe_Setup_Intent {

	/**
	 * The underlying SetupIntent payload.
	 *
	 * @var \Stripe\StripeObject|stdClass
	 */
	private object $intent;

	/**
	 * Constructor.
	 *
	 * @since 10.7.0
	 * @param \Stripe\StripeObject|stdClass $intent The Stripe setup intent payload (e.g. \Stripe\SetupIntent).
	 * @throws InvalidArgumentException When $intent is not a stdClass or \Stripe\StripeObject instance.
	 */
	public function __construct( object $intent ) {
		if ( ! $intent instanceof stdClass && ! $intent instanceof \Stripe\StripeObject ) {
			throw new InvalidArgumentException(
				sprintf( 'Expected stdClass or \Stripe\StripeObject, got %s.', get_class( $intent ) )
			);
		}

		$this->intent = $intent;
	}

	/**
	 * Returns the underlying raw object for legacy callers.
	 *
	 * @since 10.7.0
	 * @return \Stripe\StripeObject|stdClass
	 */
	public function raw(): object {
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

	public function get_order_id_from_metadata(): ?int {
		$order_id = $this->intent->metadata->order_id ?? null;
		return null === $order_id ? null : absint( $order_id );
	}

	/**
	 * Whether the intent recorded a setup error on the last attempt.
	 */
	public function has_setup_error(): bool {
		return ! empty( $this->intent->last_setup_error );
	}

	public function get_setup_error_code(): ?string {
		$code = $this->intent->last_setup_error->code ?? null;
		return null === $code ? null : (string) $code;
	}

	public function get_setup_error_message(): ?string {
		$message = $this->intent->last_setup_error->message ?? null;
		return null === $message ? null : (string) $message;
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
