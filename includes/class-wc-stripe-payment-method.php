<?php
/**
 * Class WC_Stripe_Payment_Method
 *
 * Typed domain wrapper around a Stripe PaymentMethod object.
 *
 * @package WooCommerce_Stripe
 * @since   10.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides typed, null-safe access to Stripe PaymentMethod data.
 *
 * Wraps either a `\Stripe\PaymentMethod` (SDK) or a `stdClass` (legacy
 * `json_decode`) and consolidates the fallback chains (card brand, billing
 * email), null-guarded sub-object accessors (sepa_debit, us_bank_account,
 * link), and type predicates that were previously repeated across the
 * payment-tokens layer, UPE payment-method classes, subscription compat
 * trait, and the UPE gateway.
 *
 * @since 10.7.0
 */
class WC_Stripe_Payment_Method {

	/**
	 * The underlying PaymentMethod payload.
	 *
	 * @var \Stripe\StripeObject|stdClass
	 */
	private object $payment_method;

	/**
	 * Constructor.
	 *
	 * @since 10.7.0
	 * @param \Stripe\StripeObject|stdClass $payment_method The Stripe payment method payload.
	 * @throws InvalidArgumentException When $payment_method is not a stdClass or \Stripe\StripeObject instance.
	 */
	public function __construct( object $payment_method ) {
		if ( ! $payment_method instanceof stdClass && ! $payment_method instanceof \Stripe\StripeObject ) {
			throw new InvalidArgumentException(
				sprintf( 'Expected stdClass or \Stripe\StripeObject, got %s.', get_class( $payment_method ) )
			);
		}

		$this->payment_method = $payment_method;
	}

	/**
	 * Returns the underlying raw object for legacy callers.
	 *
	 * Prefer named getters; this is an escape hatch for incremental migration.
	 *
	 * @since 10.7.0
	 * @return \Stripe\StripeObject|stdClass
	 */
	public function raw(): object {
		return $this->payment_method;
	}

	/**
	 * Returns the payment method ID, or null when absent.
	 *
	 * @since 10.7.0
	 */
	public function get_id(): ?string {
		return isset( $this->payment_method->id ) ? (string) $this->payment_method->id : null;
	}

	/**
	 * Returns the payment method type (e.g. `card`, `sepa_debit`, `us_bank_account`,
	 * `link`, `amazon_pay`), or null when absent.
	 *
	 * @since 10.7.0
	 */
	public function get_type(): ?string {
		return isset( $this->payment_method->type ) ? (string) $this->payment_method->type : null;
	}

	/**
	 * Returns the `object` field — usually `payment_method` for PaymentMethod objects.
	 *
	 * Used to disambiguate legacy `source` objects that share some field layout.
	 *
	 * @since 10.7.0
	 */
	public function get_object(): ?string {
		return isset( $this->payment_method->object ) ? (string) $this->payment_method->object : null;
	}

	/**
	 * Whether this object is a Stripe PaymentMethod (as opposed to a legacy `source`).
	 *
	 * @since 10.7.0
	 */
	public function is_payment_method_object(): bool {
		return 'payment_method' === $this->get_object();
	}

	/**
	 * Whether the payment method is of a given type.
	 *
	 * @since 10.7.0
	 * @param string $type A type constant (e.g. `WC_Stripe_Payment_Methods::CARD`).
	 */
	public function is_type( string $type ): bool {
		return $type === $this->get_type();
	}

	/**
	 * Whether the payment method is a card.
	 *
	 * @since 10.7.0
	 */
	public function is_card(): bool {
		return $this->is_type( WC_Stripe_Payment_Methods::CARD );
	}

	/**
	 * Returns the Stripe customer ID, handling expanded and string forms.
	 *
	 * @since 10.7.0
	 */
	public function get_customer_id(): ?string {
		return $this->resolve_id( $this->payment_method->customer ?? null );
	}

	// ---------------------------------------------------------------------
	// Card
	// ---------------------------------------------------------------------

	/**
	 * Returns the card brand, applying the canonical fallback chain used by
	 * payment-tokens and the UPE CC payment method:
	 *
	 * `card.display_brand` → `card.networks.preferred` → `card.brand`.
	 *
	 * `display_brand` is the user-facing brand returned by Stripe for
	 * co-branded cards; when absent, the preferred network (e.g. cartes_bancaires
	 * over visa) takes precedence over the primary `brand` string.
	 *
	 * @since 10.7.0
	 * @return string|null
	 */
	public function get_card_brand(): ?string {
		$brand = $this->payment_method->card->display_brand
			?? $this->payment_method->card->networks->preferred
			?? $this->payment_method->card->brand
			?? null;

		return null === $brand ? null : (string) $brand;
	}

	public function get_card_last4(): ?string {
		return isset( $this->payment_method->card->last4 ) ? (string) $this->payment_method->card->last4 : null;
	}

	public function get_card_fingerprint(): ?string {
		return isset( $this->payment_method->card->fingerprint ) ? (string) $this->payment_method->card->fingerprint : null;
	}

	public function get_card_exp_month(): ?int {
		return isset( $this->payment_method->card->exp_month ) ? (int) $this->payment_method->card->exp_month : null;
	}

	public function get_card_exp_year(): ?int {
		return isset( $this->payment_method->card->exp_year ) ? (int) $this->payment_method->card->exp_year : null;
	}

	public function get_card_country(): ?string {
		return isset( $this->payment_method->card->country ) ? (string) $this->payment_method->card->country : null;
	}

	public function get_card_funding(): ?string {
		return isset( $this->payment_method->card->funding ) ? (string) $this->payment_method->card->funding : null;
	}

	/**
	 * Whether the underlying card is a prepaid card (relevant for prepaid-card
	 * restrictions enforced by the UPE gateway).
	 *
	 * @since 10.7.0
	 */
	public function is_prepaid_card(): bool {
		return 'prepaid' === $this->get_card_funding();
	}

	/**
	 * Whether the card is issued in India (relevant for Indian SCA flows).
	 *
	 * @since 10.7.0
	 */
	public function is_india_card(): bool {
		return $this->is_card() && WC_Stripe_Country_Code::INDIA === $this->get_card_country();
	}

	/**
	 * Returns the preferred card network (e.g. `cartes_bancaires`, `visa`), or null.
	 *
	 * @since 10.7.0
	 */
	public function get_card_preferred_network(): ?string {
		$preferred = $this->payment_method->card->networks->preferred ?? null;
		return null === $preferred ? null : (string) $preferred;
	}

	// ---------------------------------------------------------------------
	// SEPA Direct Debit
	// ---------------------------------------------------------------------

	public function get_sepa_last4(): ?string {
		return isset( $this->payment_method->sepa_debit->last4 ) ? (string) $this->payment_method->sepa_debit->last4 : null;
	}

	public function get_sepa_fingerprint(): ?string {
		return isset( $this->payment_method->sepa_debit->fingerprint ) ? (string) $this->payment_method->sepa_debit->fingerprint : null;
	}

	// ---------------------------------------------------------------------
	// US bank account (ACH)
	// ---------------------------------------------------------------------

	public function get_us_bank_last4(): ?string {
		return isset( $this->payment_method->us_bank_account->last4 ) ? (string) $this->payment_method->us_bank_account->last4 : null;
	}

	public function get_us_bank_fingerprint(): ?string {
		return isset( $this->payment_method->us_bank_account->fingerprint ) ? (string) $this->payment_method->us_bank_account->fingerprint : null;
	}

	public function get_us_bank_bank_name(): ?string {
		return isset( $this->payment_method->us_bank_account->bank_name ) ? (string) $this->payment_method->us_bank_account->bank_name : null;
	}

	public function get_us_bank_account_type(): ?string {
		return isset( $this->payment_method->us_bank_account->account_type ) ? (string) $this->payment_method->us_bank_account->account_type : null;
	}

	/**
	 * Whether the payload contains a populated `us_bank_account` sub-object.
	 *
	 * Used by the ACH payment-token early-return guard so callers don't have
	 * to re-check `isset( $payment_method->id ) && isset( $payment_method->us_bank_account )`.
	 *
	 * @since 10.7.0
	 */
	public function has_us_bank_details(): bool {
		return null !== $this->get_id() && isset( $this->payment_method->us_bank_account );
	}

	// ---------------------------------------------------------------------
	// Link
	// ---------------------------------------------------------------------

	/**
	 * Returns the Link email (only populated for Link payment methods).
	 *
	 * @since 10.7.0
	 */
	public function get_link_email(): ?string {
		return isset( $this->payment_method->link->email ) ? (string) $this->payment_method->link->email : null;
	}

	// ---------------------------------------------------------------------
	// Billing details
	// ---------------------------------------------------------------------

	/**
	 * Returns the billing email. When absent, returns the provided $fallback
	 * (call sites historically use `?? ''` to avoid nulls at persistence time).
	 *
	 * @since 10.7.0
	 * @param string|null $fallback Value returned when no email is present. Defaults to null.
	 */
	public function get_billing_email( ?string $fallback = null ): ?string {
		$email = $this->payment_method->billing_details->email ?? null;
		if ( null === $email || '' === $email ) {
			return $fallback;
		}
		return (string) $email;
	}

	public function get_billing_name(): ?string {
		return isset( $this->payment_method->billing_details->name ) ? (string) $this->payment_method->billing_details->name : null;
	}

	public function get_billing_phone(): ?string {
		return isset( $this->payment_method->billing_details->phone ) ? (string) $this->payment_method->billing_details->phone : null;
	}

	/**
	 * Returns the raw billing address object, or null when absent.
	 *
	 * @since 10.7.0
	 */
	public function get_billing_address(): ?object {
		$address = $this->payment_method->billing_details->address ?? null;
		return is_object( $address ) ? $address : null;
	}

	// ---------------------------------------------------------------------
	// Metadata
	// ---------------------------------------------------------------------

	/**
	 * Returns an arbitrary metadata value by key, or null when absent.
	 *
	 * @since 10.7.0
	 */
	public function get_metadata_value( string $key ): ?string {
		$value = $this->payment_method->metadata->$key ?? null;
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
