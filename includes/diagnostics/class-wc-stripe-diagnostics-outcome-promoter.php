<?php

defined( 'ABSPATH' ) || exit;

/**
 * Promotes a diagnostics trace to a terminal status based on the events
 * flowing through it.
 *
 * Both the server-side recorder and the client-event REST controller call
 * {@see self::maybe_promote()} after appending each event. That keeps the
 * status field correct regardless of which side captured the failure
 * (frontend createPaymentMethod errors, server-side Stripe API errors,
 * webhook results) and lets the merchant-facing "Copy failed traces" filter
 * work off a single stored field instead of re-walking events at copy time.
 *
 * Precedence rules:
 * - `failed` and `completed` are last-write-wins, so a 3DS retry that errors
 *   then succeeds correctly ends as `completed`.
 * - `abandoned` only promotes from `pending`. A late `express.cancel` after
 *   a confirmed failure or success must not downgrade the trace.
 */
class WC_Stripe_Diagnostics_Outcome_Promoter {

	/**
	 * Trace store that holds the status field being mutated.
	 *
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	/**
	 * Construct with the trace store the promoter mutates.
	 *
	 * @param WC_Stripe_Diagnostics_Trace_Store $store Trace store.
	 */
	public function __construct( WC_Stripe_Diagnostics_Trace_Store $store ) {
		$this->store = $store;
	}

	/**
	 * Apply the precedence rules and write the new status to the trace
	 * store, when the event carries a recognized signal.
	 *
	 * @param string $session_id Trace identifier.
	 * @param array  $event      The event just appended to the trace.
	 */
	public function maybe_promote( string $session_id, array $event ): void {
		$signal = self::classify_event( $event );
		if ( null === $signal ) {
			return;
		}

		// `abandoned` only promotes from `pending`; never downgrade a
		// known failure or success when a late wallet-cancel arrives.
		if ( WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED === $signal ) {
			$trace = $this->store->get( $session_id );
			if ( null === $trace ) {
				return;
			}
			$current = isset( $trace['status'] ) ? (string) $trace['status'] : WC_Stripe_Diagnostics_Trace_Store::STATUS_PENDING;
			if ( WC_Stripe_Diagnostics_Trace_Store::STATUS_PENDING !== $current ) {
				return;
			}
		}

		$this->store->set_status( $session_id, $signal );
	}

	/**
	 * Map an event to the status it implies, or null when the event
	 * carries no terminal signal. Pure function — exposed (and tested)
	 * separately from {@see self::maybe_promote()} so the classification
	 * matrix is verifiable without touching the trace store.
	 *
	 * @param array $event Event payload.
	 * @return string|null One of the WC_Stripe_Diagnostics_Trace_Store::STATUS_* constants, or null.
	 */
	public static function classify_event( array $event ): ?string {
		$kind = isset( $event['kind'] ) ? (string) $event['kind'] : '';
		if ( '' === $kind ) {
			return null;
		}

		// Stripe.js wrapped-call rejections, emitted by aroundStripeCall()
		// as `stripe.<method>.throw` for any thrown error (network drop, JS
		// exception). Treat as failure regardless of which method threw.
		if ( str_starts_with( $kind, 'stripe.' ) && str_ends_with( $kind, '.throw' ) ) {
			return WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED;
		}

		switch ( $kind ) {
			case 'stripe.api.response':
				if ( isset( $event['error'] ) && ! empty( $event['error'] ) ) {
					return WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED;
				}
				return null;

			case 'webhook.received':
				$type = isset( $event['type'] ) ? (string) $event['type'] : '';
				if ( '' === $type ) {
					return null;
				}
				if ( str_ends_with( $type, '.payment_failed' ) ) {
					return WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED;
				}
				// Explicit allowlist — `setup_intent.succeeded` etc. fire
				// for save-card flows that never charge, so a generic
				// `.succeeded` match would mislabel those as completed
				// checkouts.
				if ( in_array( $type, [ 'payment_intent.succeeded', 'charge.succeeded' ], true ) ) {
					return WC_Stripe_Diagnostics_Trace_Store::STATUS_COMPLETED;
				}
				return null;

			case 'stripe.createPaymentMethod.resolve':
			case 'stripe.confirmPayment.resolve':
				$data = isset( $event['data'] ) && is_array( $event['data'] ) ? $event['data'] : [];
				if ( ! empty( $data['has_error'] ) ) {
					return WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED;
				}
				return null;

			case 'blocks.payment_setup.end':
				$data   = isset( $event['data'] ) && is_array( $event['data'] ) ? $event['data'] : [];
				$result = isset( $data['result_type'] ) ? (string) $data['result_type'] : '';
				if ( in_array( $result, [ 'failure', 'error' ], true ) ) {
					return WC_Stripe_Diagnostics_Trace_Store::STATUS_FAILED;
				}
				return null;

			case 'express.cancel':
				return WC_Stripe_Diagnostics_Trace_Store::STATUS_ABANDONED;
		}

		return null;
	}
}
